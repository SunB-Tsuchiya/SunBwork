<?php

namespace App\Services\SunA\Sales;

use App\Models\SunA\Sales\AuditLog;
use App\Models\SunA\Sales\Fact;
use App\Models\SunA\Sales\Import;
use App\Models\SunA\Sales\ImportChange;
use App\Models\SunA\Sales\ImportSnapshot;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * 売上レポート（サンエー印刷）の取込。流れ: アップロード → 解析・検証 → 差分プレビュー → 確定。
 * 設計: SUNASALES_PLAN1.md §6。会社のコンテキスト（SunaCompanyContext）が必要。
 *
 * 取込のルール:
 * - **得意先×年の行単位で追加・更新**。ファイルにある得意先×年の行は、その行の内容で更新する（その行の月のうち、
 *   ファイルで空欄になった月は削除）。ファイルに無い得意先×年の行は残す（補完用の一部だけのファイルに対応）
 * - 「この年はファイルの内容で入れ替える」（replace_years。既定なし）を指定した年は、その年のファイルに無い得意先×年の行も削除
 * - 担当部門は年（行）ごと。取込で変わったら、その得意先×年の全月の担当部門を更新
 * - プレビューと確定は、同じ「取込計画」（buildPlan）から作る。確定時にもファイルを読み直して計画を作り直す
 * - 取込ファイルは保存しない: アップロードは一時領域に置き、確定・破棄・1時間経過で削除する
 * - 変更ログ（suna_sl_import_changes）には、変更のあった行だけを残す。監査ログには件数だけ（得意先名・金額は入れない）
 */
class SunaSalesImportService
{
    private const PREVIEW_TTL_MINUTES = 60;

    private const DETAIL_LIMIT = 500;

    private const AMOUNT_EPSILON = 0.005;

    /** @var array<string, ?array{department_key:string, section_key:?string}> */
    private array $departmentCache = [];

    public function __construct(
        private ClientSalesV1Reader $reader,
        private DepartmentInitializer $departments,
    ) {
    }

    // ------------------------------------------------------------------ プレビュー

    /**
     * アップロードされたファイルを一時領域に保存し、解析して差分プレビューを返す。
     *
     * @param  array<int, int>  $replaceYears
     * @return array<string, mixed>
     */
    public function preview(string $uploadedPath, string $originalFilename, int $userId, array $replaceYears = []): array
    {
        $companyId = SunaCompanyContext::requireId();
        $this->pruneOldUploads($companyId);
        $this->departments->ensureDefaults(); // 初回でも部門マスタがそろうように

        $token = Str::random(40);
        $relative = $this->tempPath($companyId, $token);
        Storage::disk('local')->put($relative, file_get_contents($uploadedPath));
        Cache::put($this->cacheKey($companyId, $userId, $token), [
            'path' => $relative,
            'original_filename' => $originalFilename,
            'sha256' => hash_file('sha256', $uploadedPath),
        ], now()->addMinutes(self::PREVIEW_TTL_MINUTES));

        try {
            return $this->previewToken($token, $userId, $replaceYears);
        } catch (\Throwable $e) {
            $this->discard($token, $userId); // 形式エラーなどのときは、ファイルを残さない
            throw $e;
        }
    }

    /**
     * 保存済みの一時ファイルを、別の「入れ替える年」の指定で、プレビューし直す。
     *
     * @param  array<int, int>  $replaceYears
     * @return array<string, mixed>
     */
    public function previewToken(string $token, int $userId, array $replaceYears = []): array
    {
        $companyId = SunaCompanyContext::requireId();
        $meta = $this->meta($companyId, $userId, $token);
        $parsed = $this->reader->read(Storage::disk('local')->path($meta['path']));
        $resolved = $this->resolveDepartments($parsed);
        $errors = $resolved['errors'];

        $replaceYears = $this->normalizeReplaceYears($replaceYears, $parsed);
        $plan = $errors === [] ? $this->buildPlan($resolved['rows'], $replaceYears) : null;

        return [
            'token' => $token,
            'original_filename' => $meta['original_filename'],
            'file_sha256' => $meta['sha256'],
            'duplicate_file' => Import::query()->where('file_sha256', $meta['sha256'])->exists(),
            'format' => $parsed['format'],
            'row_count' => $parsed['row_count'],
            'year_min' => $parsed['year_min'],
            'year_max' => $parsed['year_max'],
            'years' => array_values(array_unique(array_column($parsed['rows'], 'year'))),
            'replace_years' => $replaceYears,
            'errors' => $errors,
            'warnings' => $parsed['warnings'],
            'can_confirm' => $errors === [],
            'summary' => $plan['summary'] ?? null,
            'details' => $plan ? array_slice($plan['details'], 0, self::DETAIL_LIMIT) : [],
            'details_total' => $plan ? count($plan['details']) : 0,
            'department_labels' => $this->departmentLabels(),
        ];
    }

    // ------------------------------------------------------------------ 確定

    /**
     * 確定: ファイルを読み直して取込計画を作り直し、トランザクションで反映する。
     *
     * @param  array<int, int>  $replaceYears
     */
    public function confirm(string $token, int $userId, array $replaceYears = []): Import
    {
        $companyId = SunaCompanyContext::requireId();
        $meta = $this->meta($companyId, $userId, $token);
        $parsed = $this->reader->read(Storage::disk('local')->path($meta['path']));
        $resolved = $this->resolveDepartments($parsed);

        if ($resolved['errors'] !== []) {
            throw new SunaSalesImportException('取込できない内容が残っています。指摘を修正したExcelで、もう一度アップロードしてください。');
        }

        $replaceYears = $this->normalizeReplaceYears($replaceYears, $parsed);
        $plan = $this->buildPlan($resolved['rows'], $replaceYears);

        $import = DB::connection('sales')->transaction(function () use ($plan, $parsed, $meta, $userId, $replaceYears) {
            $import = Import::create([
                'format' => $parsed['format'],
                'original_filename' => $meta['original_filename'],
                'file_sha256' => $meta['sha256'],
                'status' => 'completed',
                'imported_by' => $userId,
                'imported_at' => now(),
                'year_min' => $parsed['year_min'],
                'year_max' => $parsed['year_max'],
                'row_count' => $parsed['row_count'],
                'added_count' => $plan['summary']['months_added'],
                'changed_count' => $plan['summary']['months_changed'],
                'removed_count' => $plan['summary']['months_removed'],
                'unchanged_count' => $plan['summary']['months_unchanged'],
                'replace_years' => $replaceYears ?: null,
                'warnings' => $parsed['warnings'] ?: null,
            ]);

            $now = now();
            // 取り消し用に、変更・削除される行の「変更前の姿」を保存する（SUNASALES_PLAN1.md §16）。追加される行は保存しない
            $this->saveSnapshots($import->id, array_keys($plan['updates']), $plan['deletes']);

            $inserts = array_map(fn ($f) => $f + ['import_id' => $import->id, 'created_at' => $now, 'updated_at' => $now], $plan['inserts']);
            Fact::insertScoped($inserts);

            foreach ($plan['updates'] as $id => $fields) {
                Fact::query()->whereKey($id)->update($fields + ['import_id' => $import->id, 'updated_at' => $now]);
            }
            foreach (array_chunk($plan['deletes'], 500) as $ids) {
                Fact::query()->whereIn('id', $ids)->delete();
            }

            $changes = array_map(fn ($c) => $c + ['import_id' => $import->id], $plan['changes']);
            ImportChange::insertScoped($changes);

            AuditLog::create([
                'user_id' => $userId,
                'action' => 'import',
                'target_type' => 'suna_sl_imports',
                'target_id' => $import->id,
                // 件数だけ（得意先名・金額・ファイル内容は入れない）
                'context' => $plan['summary'] + ['rows' => $parsed['row_count'], 'replace_years' => $replaceYears],
            ]);

            return $import;
        });

        $this->discard($token, $userId);

        return $import;
    }

    /**
     * 取込で変更・削除される行の、変更前の姿を保存する（取込の取り消し用）。
     *
     * @param  array<int, int>  $updatedIds
     * @param  array<int, int>  $deletedIds
     */
    private function saveSnapshots(int $importId, array $updatedIds, array $deletedIds): void
    {
        $rows = [];
        foreach ([['updated', $updatedIds], ['deleted', $deletedIds]] as [$kind, $ids]) {
            foreach (array_chunk($ids, 500) as $chunk) {
                foreach (Fact::query()->whereIn('id', $chunk)->get() as $f) {
                    $rows[] = [
                        'import_id' => $importId, 'kind' => $kind, 'fact_id' => $f->id,
                        'client_key' => $f->client_key, 'client_name' => $f->client_name,
                        'sales_year' => $f->sales_year, 'sales_month' => $f->sales_month,
                        'department_key' => $f->department_key, 'section_key' => $f->section_key,
                        'amount' => $f->amount, 'order_count' => $f->order_count,
                        'fact_import_id' => $f->import_id, 'fact_created_at' => $f->created_at, 'fact_updated_at' => $f->updated_at,
                    ];
                }
            }
        }
        ImportSnapshot::insertScoped($rows);
    }

    /** 一時ファイルと確認用の情報を削除する（確定・破棄・失敗時） */
    public function discard(string $token, int $userId): void
    {
        $companyId = SunaCompanyContext::requireId();
        $key = $this->cacheKey($companyId, $userId, $token);
        $meta = Cache::get($key);
        if ($meta !== null) {
            Storage::disk('local')->delete($meta['path']);
        }
        Storage::disk('local')->delete($this->tempPath($companyId, $token));
        Cache::forget($key);
    }

    // ------------------------------------------------------------------ 取込計画

    /**
     * ファイルの行（部・課を解決済み）と、現在のデータを突き合わせて、取込計画を作る。
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, int>  $replaceYears
     * @return array{inserts: array, updates: array<int, array>, deletes: array<int, int>, changes: array, details: array, summary: array}
     */
    private function buildPlan(array $rows, array $replaceYears): array
    {
        $years = array_values(array_unique(array_column($rows, 'year')));
        $existing = $this->loadExisting($rows, $replaceYears);

        $inserts = [];
        $updates = [];
        $deletes = [];
        $changes = [];
        $details = [];
        $s = ['months_added' => 0, 'months_changed' => 0, 'months_removed' => 0, 'months_unchanged' => 0,
            'client_years_new' => 0, 'client_years_updated' => 0, 'client_years_unchanged' => 0,
            'client_years_removed' => 0, 'department_changes' => 0];
        $inFile = [];

        foreach ($rows as $row) {
            $key = $row['client_key'];
            $year = $row['year'];
            $inFile["{$key}|{$year}"] = true;
            $dept = $row['department_key'];
            $section = $row['section_key'];
            $had = $existing[$key][$year] ?? []; // month => Fact
            $rowChanged = false;

            // 担当部門が変わるか（既存の行のうち、最初の月のものと比べる）
            $deptChanged = false;
            if ($had !== []) {
                $first = reset($had);
                $deptChanged = $first->department_key !== $dept || $first->section_key !== $section;
                if ($deptChanged) {
                    $s['department_changes']++;
                    $rowChanged = true;
                    $this->logChange($changes, $details, $row, null, 'changed', 'department', $this->deptKey($first->department_key, $first->section_key), $this->deptKey($dept, $section));
                }
            }

            foreach ($row['months'] as $month => $m) {
                $current = $had[$month] ?? null;
                if ($current === null) {
                    $s['months_added']++;
                    $rowChanged = true;
                    $inserts[] = [
                        'client_key' => $key, 'client_name' => $row['client_name'], 'sales_year' => $year, 'sales_month' => $month,
                        'department_key' => $dept, 'section_key' => $section, 'amount' => $m['amount'], 'order_count' => $m['orders'],
                    ];
                    $this->logChange($changes, $details, $row, $month, 'added', 'amount', null, $this->fmt($m['amount']));
                    continue;
                }

                $amountChanged = abs($current->amount - $m['amount']) >= self::AMOUNT_EPSILON;
                $ordersChanged = $current->order_count !== $m['orders'];
                if ($amountChanged || $ordersChanged) {
                    $s['months_changed']++;
                    $rowChanged = true;
                    $updates[$current->id] = [
                        'client_name' => $row['client_name'], 'department_key' => $dept, 'section_key' => $section,
                        'amount' => $m['amount'], 'order_count' => $m['orders'],
                    ];
                    if ($amountChanged) {
                        $this->logChange($changes, $details, $row, $month, 'changed', 'amount', $this->fmt($current->amount), $this->fmt($m['amount']));
                    }
                    if ($ordersChanged) {
                        $this->logChange($changes, $details, $row, $month, 'changed', 'order_count', (string) $current->order_count, (string) $m['orders']);
                    }
                } else {
                    $s['months_unchanged']++;
                    if ($deptChanged) {
                        $updates[$current->id] = ['department_key' => $dept, 'section_key' => $section];
                    }
                }
            }

            // ファイルの行で空欄になった月（既存にあって、ファイルに無い月）は削除
            foreach ($had as $month => $current) {
                if (! isset($row['months'][$month])) {
                    $s['months_removed']++;
                    $rowChanged = true;
                    $deletes[] = $current->id;
                    unset($updates[$current->id]);
                    $this->logChange($changes, $details, $row, $month, 'removed', 'amount', $this->fmt($current->amount), null);
                }
            }

            if ($had === []) {
                $s['client_years_new']++;
            } elseif ($rowChanged) {
                $s['client_years_updated']++;
            } else {
                $s['client_years_unchanged']++;
            }
        }

        // 「この年は入れ替える」: 指定した年の、ファイルに無い得意先×年の行を削除
        foreach ($replaceYears as $year) {
            foreach ($existing as $key => $byYear) {
                if (! isset($byYear[$year]) || isset($inFile["{$key}|{$year}"])) {
                    continue;
                }
                $s['client_years_removed']++;
                $name = reset($byYear[$year])->client_name;
                foreach ($byYear[$year] as $month => $current) {
                    $s['months_removed']++;
                    $deletes[] = $current->id;
                    $this->logChange($changes, $details, ['client_key' => $key, 'client_name' => $name, 'year' => $year], $month, 'removed', 'amount', $this->fmt($current->amount), null);
                }
            }
        }

        // 画面に出す詳細は、金額の変化が大きい順（追加・削除は金額そのもの）
        usort($details, fn ($a, $b) => abs($b['delta']) <=> abs($a['delta']));

        return ['inserts' => $inserts, 'updates' => $updates, 'deletes' => $deletes, 'changes' => $changes, 'details' => $details, 'summary' => $s];
    }

    /** @param  array<int, array<string, mixed>>  $changes */
    private function logChange(array &$changes, array &$details, array $row, ?int $month, string $kind, string $field, ?string $old, ?string $new): void
    {
        $changes[] = [
            'client_key' => $row['client_key'], 'client_name' => $row['client_name'], 'sales_year' => $row['year'], 'sales_month' => $month,
            'kind' => $kind, 'field' => $field, 'old_value' => $old, 'new_value' => $new,
        ];
        $details[] = [
            'client_name' => $row['client_name'], 'year' => $row['year'], 'month' => $month, 'kind' => $kind, 'field' => $field, 'old' => $old, 'new' => $new,
            'delta' => ($field === 'amount') ? ((float) ($new ?? 0) - (float) ($old ?? 0)) : 0.0,
        ];
    }

    /**
     * 現在のデータのうち、ファイルの得意先×年に該当するもの（＋入れ替える年の全て）。
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, int>  $replaceYears
     * @return array<string, array<int, array<int, Fact>>> client_key => year => month => Fact
     */
    private function loadExisting(array $rows, array $replaceYears): array
    {
        $map = [];
        $collect = function ($facts) use (&$map) {
            foreach ($facts as $f) {
                $map[$f->client_key][$f->sales_year][$f->sales_month] = $f;
            }
        };

        $byYear = [];
        foreach ($rows as $row) {
            $byYear[$row['year']][] = $row['client_key'];
        }
        foreach ($byYear as $year => $keys) {
            foreach (array_chunk(array_unique($keys), 500) as $chunk) {
                $collect(Fact::query()->where('sales_year', $year)->whereIn('client_key', $chunk)->get());
            }
        }
        foreach ($replaceYears as $year) {
            $collect(Fact::query()->where('sales_year', $year)->get());
        }

        return $map;
    }

    // ------------------------------------------------------------------ 部門の対応付け・補助

    /**
     * ファイルの行の「担当部門」の表記を、部・課に対応付ける。登録されていない表記はエラー（取込を止める）。
     * 読み取りのエラー（reader の errors）もまとめて返す。
     *
     * @param  array<string, mixed>  $parsed
     * @return array{rows: array<int, array<string, mixed>>, errors: array<int, string>}
     */
    private function resolveDepartments(array $parsed): array
    {
        $errors = $parsed['errors'];
        $unknown = []; // 表記 => 行番号
        $rows = [];

        foreach ($parsed['rows'] as $row) {
            $label = $row['department_label'];
            if ($label === '') {
                continue; // 空は reader がエラーにしている
            }
            if (! array_key_exists($label, $this->departmentCache)) {
                $this->departmentCache[$label] = $this->departments->resolve($label);
            }
            $resolved = $this->departmentCache[$label];
            if ($resolved === null) {
                $unknown[$label][] = $row['row_number'];
                continue;
            }
            $rows[] = $row + $resolved;
        }

        foreach ($unknown as $label => $rowNumbers) {
            $shown = implode('、', array_slice($rowNumbers, 0, 5)) . (count($rowNumbers) > 5 ? '…' : '');
            $errors[] = "担当部門「{$label}」は、部門の設定に登録されていません（" . count($rowNumbers) . "行: {$shown}行目）。管理者に部門の追加を依頼してください。";
        }

        return ['rows' => $rows, 'errors' => $errors];
    }

    /** 画面表示用: 部・課のキー → 表示名 */
    private function departmentLabels(): array
    {
        return \App\Models\SunA\Sales\Department::query()->pluck('label', 'key')->all();
    }

    private function deptKey(string $department, ?string $section): string
    {
        return $section === null ? $department : "{$department}/{$section}";
    }

    private function fmt(float $amount): string
    {
        return rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.');
    }

    /**
     * 「入れ替える年」の指定を、ファイルに含まれる年だけに絞る（ファイルに無い年を入れ替えると、その年が丸ごと消えるため）。
     *
     * @param  array<int, int|string>  $replaceYears
     * @param  array<string, mixed>  $parsed
     * @return array<int, int>
     */
    private function normalizeReplaceYears(array $replaceYears, array $parsed): array
    {
        $inFile = array_unique(array_column($parsed['rows'], 'year'));

        return array_values(array_unique(array_filter(array_map('intval', $replaceYears), fn ($y) => in_array($y, $inFile, true))));
    }

    /** @return array{path: string, original_filename: string, sha256: string} */
    private function meta(int $companyId, int $userId, string $token): array
    {
        $meta = Cache::get($this->cacheKey($companyId, $userId, $token));
        if ($meta === null || ! Storage::disk('local')->exists($meta['path'])) {
            throw new SunaSalesImportException('確認の有効期限が切れました（または、すでに取り込み済みです）。もう一度、Excelをアップロードしてください。');
        }

        return $meta;
    }

    private function cacheKey(int $companyId, int $userId, string $token): string
    {
        return "suna_sl_import:{$companyId}:{$userId}:{$token}";
    }

    private function tempPath(int $companyId, string $token): string
    {
        return "suna_sales_imports/{$companyId}/{$token}.xlsx";
    }

    /** 1時間以上前の一時ファイルを削除する（確定・破棄されずに残ったもの） */
    private function pruneOldUploads(int $companyId): void
    {
        $disk = Storage::disk('local');
        foreach ($disk->files("suna_sales_imports/{$companyId}") as $file) {
            if ($disk->lastModified($file) < now()->subMinutes(self::PREVIEW_TTL_MINUTES + 5)->getTimestamp()) {
                $disk->delete($file);
            }
        }
    }
}
