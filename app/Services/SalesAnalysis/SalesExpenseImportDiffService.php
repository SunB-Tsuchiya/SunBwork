<?php

namespace App\Services\SalesAnalysis;

use App\Models\Sales\SalesExpenseImport;
use App\Models\Sales\SalesExpenseLineItem;
use App\Models\User;

/**
 * 経費の再取込時の差分表示（Phase24-6）。同じ会計年度を取り込み直すと新しい版ができ、旧版は履歴として残る。
 * その2つの版の明細を、部署・科目・支払先・月で突き合わせて、金額が変わったところ・増えた/消えた支払先・科目を返す。
 * DBの変更は無く、旧版のデータからその都度計算する。科目名・支払先名は名寄せ後の名前に読み替えて比べる
 * （名寄せの対応表は新旧の版に同じものを使うので、表記ゆれが「追加/削除」に見えることはない）。
 */
class SalesExpenseImportDiffService
{
    /** 1円未満の端数は差分としない */
    private const EPSILON = 0.5;

    private ?int $companyId = null;

    private ?SalesExpenseAliasService $aliasService = null;

    public function forCompany(int $companyId): self
    {
        $this->companyId = $companyId;
        $this->aliasService = null;

        return $this;
    }

    private function requireCompanyId(): int
    {
        if ($this->companyId === null) {
            throw new \LogicException('SalesExpenseImportDiffService: forCompany()が呼ばれる前に実行されました。');
        }

        return $this->companyId;
    }

    private function aliases(): SalesExpenseAliasService
    {
        return $this->aliasService ??= (new SalesExpenseAliasService())->forCompany($this->requireCompanyId());
    }

    /**
     * 取込履歴（新しい順）。版が2つ以上ある年度の、2版目以降は前の版との差分を見られる。
     *
     * @return array<int, array<string, mixed>>
     */
    public function history(): array
    {
        $imports = SalesExpenseImport::where('company_id', $this->requireCompanyId())
            ->orderByDesc('fiscal_year')->orderByDesc('version')
            ->get();
        $names = User::whereIn('id', $imports->pluck('imported_by')->unique()->all())->pluck('name', 'id');
        $latest = $imports->groupBy('fiscal_year')->map(fn ($g) => $g->max('version'));

        return $imports->map(fn (SalesExpenseImport $i) => [
            'id' => $i->id,
            'fiscal_year' => $i->fiscal_year,
            'version' => $i->version,
            'is_current' => $latest[$i->fiscal_year] === $i->version, // 画面の集計に使われている版
            'has_previous' => $i->version > 1 && $imports->contains(fn ($o) => $o->fiscal_year === $i->fiscal_year && $o->version < $i->version),
            'original_filename' => $i->original_filename,
            'imported_by_name' => $names[$i->imported_by] ?? '（不明）',
            'imported_at' => $i->imported_at?->toIso8601String(),
            'category_count' => $i->category_count,
            'line_item_count' => $i->line_item_count,
            'total_amount' => (float) $i->total_amount,
        ])->values()->all();
    }

    /**
     * 指定した版と、その直前の版（同じ会計年度でversionが小さい方で最大のもの）との差分。
     * `$version`が空なら、その年度の最新版。
     *
     * @return array<string, mixed>
     */
    public function diff(int $fiscalYear, ?int $version = null): array
    {
        $versions = SalesExpenseImport::where('company_id', $this->requireCompanyId())
            ->where('fiscal_year', $fiscalYear)
            ->orderBy('version')
            ->get(['id', 'version', 'original_filename', 'total_amount']);

        $new = $version === null ? $versions->last() : $versions->firstWhere('version', $version);
        $old = $new === null ? null : $versions->where('version', '<', $new->version)->last();

        if ($new === null || $old === null) {
            return ['has_previous' => false, 'fiscal_year' => $fiscalYear, 'version' => $new?->version];
        }

        $newRows = $this->rows($new->id);
        $oldRows = $this->rows($old->id);

        $departments = [];
        foreach ($this->groupByDepartment($newRows, $oldRows) as $deptKey => $pair) {
            $d = $this->departmentDiff($deptKey, $pair['new'], $pair['old']);
            if ($d !== null) {
                $departments[] = $d;
            }
        }

        $totalNew = array_sum(array_column($newRows, 'amount'));
        $totalOld = array_sum(array_column($oldRows, 'amount'));

        return [
            'has_previous' => true,
            'fiscal_year' => $fiscalYear,
            'version' => $new->version,
            'previous_version' => $old->version,
            'original_filename' => $new->original_filename,
            'previous_original_filename' => $old->original_filename,
            'total_new' => $totalNew,
            'total_old' => $totalOld,
            'diff' => $totalNew - $totalOld,
            'has_changes' => $departments !== [],
            'summary' => $this->summarize($departments),
            'departments' => $departments,
        ];
    }

    /**
     * 版の明細を、名寄せ後の名前で（部署・科目・支払先・月）ごとに合算して持つ。
     *
     * @return array<int, array{dept:string, category:string, vendor:string, month:int, amount:float}>
     */
    private function rows(int $importId): array
    {
        $raw = SalesExpenseLineItem::where('sales_expense_import_id', $importId)
            ->selectRaw('department_key, category, vendor_name, calendar_month, SUM(amount) as amount')
            ->groupBy('department_key', 'category', 'vendor_name', 'calendar_month')
            ->get();

        $merged = [];
        foreach ($raw as $r) {
            $category = $this->aliases()->canonicalCategory($r->department_key, $r->category);
            $vendor = $this->aliases()->canonicalVendor($r->vendor_name ?? '（内訳未記入）');
            $key = implode("\0", [$r->department_key, $category, $vendor, $r->calendar_month]);
            $merged[$key] ??= ['dept' => $r->department_key, 'category' => $category, 'vendor' => $vendor, 'month' => (int) $r->calendar_month, 'amount' => 0.0];
            $merged[$key]['amount'] += (float) $r->amount;
        }

        return array_values($merged);
    }

    /** @return array<string, array{new: array, old: array}> */
    private function groupByDepartment(array $newRows, array $oldRows): array
    {
        $result = [];
        foreach ($newRows as $r) {
            $result[$r['dept']]['new'][] = $r;
        }
        foreach ($oldRows as $r) {
            $result[$r['dept']]['old'][] = $r;
        }
        foreach ($result as &$pair) {
            $pair['new'] ??= [];
            $pair['old'] ??= [];
        }
        unset($pair);
        ksort($result);

        return $result;
    }

    /**
     * 1部署ぶんの差分。変更が無ければnull。
     * 構造: 部署 → 科目 → 支払先。支払先ごとに status（added/removed/changed）と、金額が変わった月だけを持つ。
     *
     * @return array<string, mixed>|null
     */
    private function departmentDiff(string $deptKey, array $newRows, array $oldRows): ?array
    {
        // 科目 => 支払先 => [月 => [new, old]]
        $tree = [];
        foreach ($newRows as $r) {
            $tree[$r['category']][$r['vendor']][$r['month']]['new'] = $r['amount'];
        }
        foreach ($oldRows as $r) {
            $tree[$r['category']][$r['vendor']][$r['month']]['old'] = $r['amount'];
        }

        $categories = [];
        foreach ($tree as $category => $vendors) {
            $vendorDiffs = [];
            $catNew = 0.0;
            $catOld = 0.0;
            foreach ($vendors as $vendor => $months) {
                $vNew = 0.0;
                $vOld = 0.0;
                $changedMonths = [];
                foreach ($months as $month => $pair) {
                    $n = $pair['new'] ?? 0.0;
                    $o = $pair['old'] ?? 0.0;
                    $vNew += $n;
                    $vOld += $o;
                    if (abs($n - $o) >= self::EPSILON) {
                        $changedMonths[] = ['calendar_month' => $month, 'new' => $n, 'old' => $o];
                    }
                }
                $catNew += $vNew;
                $catOld += $vOld;
                if ($changedMonths === []) {
                    continue;
                }
                usort($changedMonths, fn ($a, $b) => (($a['calendar_month'] + 8) % 12) <=> (($b['calendar_month'] + 8) % 12)); // 4月始まりの順
                $vendorDiffs[] = [
                    'vendor_name' => $vendor,
                    'status' => $vOld < self::EPSILON ? 'added' : ($vNew < self::EPSILON ? 'removed' : 'changed'),
                    'total_new' => $vNew,
                    'total_old' => $vOld,
                    'diff' => $vNew - $vOld,
                    'months' => $changedMonths,
                ];
            }
            if ($vendorDiffs === []) {
                continue;
            }
            usort($vendorDiffs, fn ($a, $b) => abs($b['diff']) <=> abs($a['diff']));
            $categories[] = [
                'category' => $category,
                'status' => $catOld < self::EPSILON ? 'added' : ($catNew < self::EPSILON ? 'removed' : 'changed'),
                'total_new' => $catNew,
                'total_old' => $catOld,
                'diff' => $catNew - $catOld,
                'vendors' => $vendorDiffs,
            ];
        }

        if ($categories === []) {
            return null;
        }
        usort($categories, fn ($a, $b) => abs($b['diff']) <=> abs($a['diff']));

        $deptNew = array_sum(array_column($newRows, 'amount'));
        $deptOld = array_sum(array_column($oldRows, 'amount'));

        return [
            'department_key' => $deptKey,
            'total_new' => $deptNew,
            'total_old' => $deptOld,
            'diff' => $deptNew - $deptOld,
            'categories' => $categories,
        ];
    }

    /**
     * 差分の要約（件数）。取込直後の画面に出す。
     *
     * @param  array<int, array>  $departments
     * @return array{changed_vendors: int, added_vendors: int, removed_vendors: int, added_categories: int, removed_categories: int}
     */
    private function summarize(array $departments): array
    {
        $s = ['changed_vendors' => 0, 'added_vendors' => 0, 'removed_vendors' => 0, 'added_categories' => 0, 'removed_categories' => 0];
        foreach ($departments as $d) {
            foreach ($d['categories'] as $c) {
                if ($c['status'] === 'added') {
                    $s['added_categories']++;
                } elseif ($c['status'] === 'removed') {
                    $s['removed_categories']++;
                }
                foreach ($c['vendors'] as $v) {
                    $s[$v['status'] . '_vendors']++;
                }
            }
        }

        return $s;
    }
}
