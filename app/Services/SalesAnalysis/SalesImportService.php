<?php

namespace App\Services\SalesAnalysis;

use App\Models\Sales\SalesActiveMonth;
use App\Models\Sales\SalesAuditLog;
use App\Models\Sales\SalesImport;
use App\Models\Sales\SalesOrder;
use App\Models\Sales\SalesOrderDetail;
use App\Services\SalesAnalysis\Exceptions\SalesImportConfirmException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Excel検証済みプレビューデータ（SalesImportValidator::validate() の結果）を
 * sales DB へ確定保存する。DB確定はここでのみ行う。
 */
class SalesImportService
{
    public function previewCacheKey(string $token): string
    {
        return "sales_import_preview:{$token}";
    }

    private function previewConfirmLockKey(string $token): string
    {
        return "sales_import_confirm_lock:{$token}";
    }

    /**
     * プレビュー結果（受注No・明細を含む暗号化JSON）はファイル数の多い部署（制作・オンデマンド等）では
     * 数百KB規模になり得るため、既定のDBキャッシュ（cacheテーブルのvalue列はTEXT=65KB上限）ではなく
     * サイズ制約の緩いfileストアを明示的に使う（2026-09-03実機検証: 大きいファイルでプレビューが
     * 500エラーになる不具合の原因だった）。
     *
     * ストア名は`config('sales_analysis.import_preview_cache_store')`経由（既定file）。
     * テストではphpunit.xmlでarrayストアへ切り替える。file固定のままだとrootユーザーで
     * テストを実行した際にstorage/framework/cache/data配下がroot所有になり、実サーバー
     * （sailユーザー）が書き込めなくなる事故を招くため（2026-09-03実機検証で発覚）。
     */
    public function previewCacheStore(): \Illuminate\Contracts\Cache\Repository
    {
        return Cache::store(config('sales_analysis.import_preview_cache_store', 'file'));
    }

    /**
     * @throws SalesImportConfirmException
     */
    public function confirm(string $previewToken, int $userId, int $companyId): SalesImport
    {
        // 同一トークンでの同時・連続確定を防ぐ排他ロック（Codexレビュー2回目 High-2対応）。
        // Cache::get()→forget()の間に競合すると二重登録され得るため、確定処理全体をロックで囲む。
        $lock = $this->previewCacheStore()->lock($this->previewConfirmLockKey($previewToken), 60);

        if (! $lock->get()) {
            throw new SalesImportConfirmException('この検証結果は現在処理中です。しばらくしてから再度お試しください。');
        }

        try {
            return $this->confirmLocked($previewToken, $userId, $companyId);
        } finally {
            $lock->release();
        }
    }

    /** @throws SalesImportConfirmException */
    private function confirmLocked(string $previewToken, int $userId, int $companyId): SalesImport
    {
        $cacheKey = $this->previewCacheKey($previewToken);
        $encrypted = $this->previewCacheStore()->get($cacheKey);

        if (! $encrypted) {
            throw new SalesImportConfirmException('プレビューの有効期限が切れました。再度検証してください。');
        }

        $result = Crypt::decrypt($encrypted);

        if (! ($result['valid'] ?? false)) {
            throw new SalesImportConfirmException('検証済みデータが無効です。再度検証してください。');
        }

        // プレビューを検証したユーザーと確定しようとしているユーザーが一致することを確認する
        // （Codexレビュー2回目 High-2対応: 他ユーザーが作ったプレビュートークンを確定できてしまう問題）
        if (($result['previewed_by'] ?? null) !== $userId) {
            throw new SalesImportConfirmException('このプレビューは別のユーザーが検証したものです。再度ご自身で検証してください。');
        }

        // プレビュー時点の会社と確定時点の会社が一致することを確認する（会社別データ分離、2026-09-05）。
        // SuperAdminがプレビュー後に画面右上の会社切替を操作してから確定した場合など、
        // 意図と異なる会社にデータが保存されるのを防ぐ。
        if (($result['company_id'] ?? null) !== $companyId) {
            throw new SalesImportConfirmException('プレビュー時点と会社が異なります。再度ご自身で検証してください。');
        }

        if (SalesImport::where('company_id', $companyId)->where('file_sha256', $result['file_sha256'])->exists()) {
            throw new SalesImportConfirmException('同一内容のファイルが既に取り込まれています。');
        }

        try {
            $import = $this->persistImport($result, $userId, $companyId);
        } catch (QueryException $e) {
            // 異なるプレビュートークン（同一ファイルを2回アップロード等）が競合した場合の最終防御。
            // 排他ロックはトークン単位のため、この経路はDB側のunique制約でのみ検知できる
            // （Codexレビュー2回目 High-2/8.3対応）。
            if ($this->isDuplicateFileHashViolation($e)) {
                throw new SalesImportConfirmException('同一内容のファイルが既に取り込まれています。');
            }

            throw $e;
        }

        $this->previewCacheStore()->forget($cacheKey);

        SalesAuditLog::create([
            'user_id' => $userId,
            'action' => 'import',
            'target_type' => 'sales_import',
            'target_id' => $import->id,
            'context' => [
                'department_key' => $import->department_key,
                'source_type' => $import->source_type,
                'source_year' => $import->source_year,
                'source_month' => $import->source_month,
                'version' => $import->version,
            ],
        ]);

        return $import;
    }

    private function isDuplicateFileHashViolation(QueryException $e): bool
    {
        return $e->getCode() === '23000' && str_contains($e->getMessage(), 'sales_imports_company_file_sha256_unique');
    }

    private function persistImport(array $result, int $userId, int $companyId): SalesImport
    {
        return DB::connection('sales')->transaction(function () use ($result, $userId, $companyId) {
            $version = $this->nextVersion(
                $companyId,
                $result['department_key'],
                $result['source_year'],
                $result['source_month'],
                $result['source_month_end'] ?? null
            );

            $import = SalesImport::create([
                'company_id' => $companyId,
                'department_key' => $result['department_key'],
                'source_type' => $result['source_type'],
                'source_year' => $result['source_year'],
                'source_month' => $result['source_month'],
                'source_month_end' => $result['source_month_end'] ?? null,
                'version' => $version,
                'original_filename' => $result['original_filename'],
                'file_sha256' => $result['file_sha256'],
                'status' => 'completed',
                'imported_by' => $userId,
                'imported_at' => now(),
                'order_count' => $result['summary']['order_count'],
                'detail_count' => $result['summary']['detail_count'],
                'total_amount' => $result['summary']['total_amount'],
                'warnings' => empty($result['warnings']) ? null : $result['warnings'],
            ]);

            foreach ($result['orders'] as $orderData) {
                $order = SalesOrder::create([
                    'sales_import_id' => $import->id,
                    'order_number' => $orderData['order_number'],
                    'client_name' => $orderData['client_name'],
                    'product_name' => $orderData['product_name'],
                    'plate_date' => $orderData['plate_date'],
                    'sales_year' => $orderData['sales_year'],
                    'sales_month' => $orderData['sales_month'],
                    'order_amount' => $orderData['order_amount'],
                    'unallocated_amount' => $orderData['unallocated_amount'] ?? 0,
                ]);

                foreach ($orderData['details'] as $detail) {
                    SalesOrderDetail::create([
                        'sales_order_id' => $order->id,
                        'source_row_number' => $detail['source_row_number'],
                        'client_name' => $detail['client_name'],
                        'product_name' => $detail['product_name'],
                        'part_name' => $detail['part_name'],
                        'category' => $detail['category'],
                        'item_name' => $detail['item_name'],
                        'progress' => $detail['progress'],
                        'remarks' => $detail['remarks'],
                        'format_size' => $detail['format_size'],
                        'color_count' => $detail['color_count'],
                        'quantity' => $detail['quantity'],
                        'unit_price' => $detail['unit_price'],
                        'line_amount' => $detail['line_amount'],
                        'order_amount_component' => $detail['order_amount_component'],
                        'plate_date' => $detail['plate_date'],
                    ]);
                }
            }

            // active month切替は「取込指定範囲全体」を対象にする（受注が0件の月も含む）。
            // 受注データが存在する月だけを対象にすると、修正版で0件になった月の
            // active pointerが旧版のまま残留してしまう（Codexレビュー6.2 High-4）。
            foreach ($this->targetMonths($result['source_type'], $result['source_year'], $result['source_month'], $result['source_month_end'] ?? null) as $month) {
                SalesActiveMonth::updateOrCreate(
                    [
                        'company_id' => $companyId,
                        'department_key' => $result['department_key'],
                        'sales_year' => $month['year'],
                        'sales_month' => $month['month'],
                    ],
                    [
                        'sales_import_id' => $import->id,
                        'activated_by' => $userId,
                        'activated_at' => now(),
                    ]
                );
            }

            return $import;
        });
    }

    /**
     * 同一部署・対象期間（rangeは開始月〜終了月の組み合わせ）内で次に採番すべき版番号を返す。
     */
    private function nextVersion(int $companyId, string $departmentKey, int $sourceYear, ?int $sourceMonth, ?int $sourceMonthEnd = null): int
    {
        $query = SalesImport::where('company_id', $companyId)
            ->where('department_key', $departmentKey)
            ->where('source_year', $sourceYear);

        $sourceMonth === null ? $query->whereNull('source_month') : $query->where('source_month', $sourceMonth);
        $sourceMonthEnd === null ? $query->whereNull('source_month_end') : $query->where('source_month_end', $sourceMonthEnd);

        return ((int) $query->max('version')) + 1;
    }

    /**
     * 受注データから実際に影響を受ける (年, 月) の一覧を返す。
     * 差分表示（calculateDiff）など「データが存在する月」だけを扱いたい用途で使う。
     * active month切替には targetMonths() を使うこと（受注0件の月も対象にする必要があるため）。
     *
     * @param  array<int, array>  $orders
     * @return Collection<int, array{year: int, month: int}>
     */
    public function affectedMonths(array $orders): Collection
    {
        return collect($orders)
            ->map(fn ($o) => ['year' => $o['sales_year'], 'month' => $o['sales_month']])
            ->unique(fn ($m) => "{$m['year']}-{$m['month']}")
            ->values();
    }

    /**
     * 取込指定範囲全体（monthly: 指定月のみ／range: 開始月〜終了月／annual: 1〜12月）の
     * 対象月一覧を返す。active month切替はこの範囲全体を対象にする（Codexレビュー6.2 High-4）。
     *
     * @return Collection<int, array{year: int, month: int}>
     */
    public function targetMonths(string $sourceType, int $sourceYear, ?int $sourceMonth, ?int $sourceMonthEnd): Collection
    {
        if ($sourceType === 'annual') {
            return collect(range(1, 12))->map(fn ($m) => ['year' => $sourceYear, 'month' => $m]);
        }

        if ($sourceType === 'range') {
            return collect(range($sourceMonth, $sourceMonthEnd))->map(fn ($m) => ['year' => $sourceYear, 'month' => $m]);
        }

        return collect([['year' => $sourceYear, 'month' => $sourceMonth]]);
    }

    /**
     * プレビュー中の受注データと、各対象月の現在有効な版との差分を計算する。
     * 既存版が無い月は has_existing=false を返す。
     *
     * @param  array<int, array>  $orders
     * @return array<int, array>
     */
    public function calculateDiff(array $orders, string $departmentKey, int $companyId): array
    {
        $diffs = [];

        foreach ($this->affectedMonths($orders) as $month) {
            $active = SalesActiveMonth::where('company_id', $companyId)
                ->where('department_key', $departmentKey)
                ->where('sales_year', $month['year'])
                ->where('sales_month', $month['month'])
                ->with('salesImport')
                ->first();

            if (! $active || ! $active->salesImport) {
                $diffs[] = [
                    'year' => $month['year'],
                    'month' => $month['month'],
                    'has_existing' => false,
                ];

                continue;
            }

            $existingImport = $active->salesImport;

            $existingOrders = SalesOrder::where('sales_import_id', $existingImport->id)
                ->where('sales_year', $month['year'])
                ->where('sales_month', $month['month'])
                ->get(['id', 'order_number', 'order_amount'])
                ->keyBy('order_number');

            $newOrdersInMonth = collect($orders)->filter(
                fn ($o) => $o['sales_year'] === $month['year'] && $o['sales_month'] === $month['month']
            );

            $newOrderNumbers = $newOrdersInMonth->pluck('order_number');
            $existingOrderNumbers = $existingOrders->keys();

            $addedCount = $newOrderNumbers->diff($existingOrderNumbers)->count();
            $removedCount = $existingOrderNumbers->diff($newOrderNumbers)->count();
            $changedCount = $newOrdersInMonth->filter(function ($o) use ($existingOrders) {
                $existing = $existingOrders->get($o['order_number']);

                return $existing && abs((float) $existing->order_amount - (float) $o['order_amount']) > 0.01;
            })->count();

            $existingDetailCount = SalesOrderDetail::whereIn('sales_order_id', $existingOrders->pluck('id'))->count();
            $existingTotalAmount = (float) $existingOrders->sum('order_amount');
            $newTotalAmount = (float) $newOrdersInMonth->sum('order_amount');

            $diffs[] = [
                'year' => $month['year'],
                'month' => $month['month'],
                'has_existing' => true,
                'existing_version' => $existingImport->version,
                'existing_imported_at' => optional($existingImport->imported_at)->toIso8601String(),
                'existing_order_count' => $existingOrders->count(),
                'existing_detail_count' => $existingDetailCount,
                'existing_total_amount' => $existingTotalAmount,
                'new_order_count' => $newOrdersInMonth->count(),
                'new_total_amount' => $newTotalAmount,
                'amount_diff' => $newTotalAmount - $existingTotalAmount,
                'added_order_count' => $addedCount,
                'removed_order_count' => $removedCount,
                'changed_order_count' => $changedCount,
            ];
        }

        return $diffs;
    }
}
