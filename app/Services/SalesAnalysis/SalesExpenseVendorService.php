<?php

namespace App\Services\SalesAnalysis;

use App\Models\Sales\SalesExpenseImport;
use App\Models\Sales\SalesExpenseLineItem;

/**
 * 経費「支払先カルテ」の集計（Phase24-3）。全部署・全科目・全年度を支払先（名寄せ後の名前）で横断して、
 * 「どの支払先に、いつ、どれだけ払っているか」を見るためのデータを返す。粗利計算はしない。
 * 取込データは書き換えず、科目名・支払先名とも名寄せ後の名前に読み替えて集計する。
 */
class SalesExpenseVendorService
{
    private ?int $companyId = null;

    private ?SalesExpenseAliasService $aliasService = null;

    /** @var array<int, array{fy:int, dept:string, category:string, vendor:string, month:int, amount:float}>|null */
    private ?array $rows = null;

    /** @var array<int, int>|null 取込のある会計年度（昇順） */
    private ?array $fiscalYears = null;

    public function forCompany(int $companyId): self
    {
        $this->companyId = $companyId;
        $this->aliasService = null;
        $this->rows = null;
        $this->fiscalYears = null;

        return $this;
    }

    private function requireCompanyId(): int
    {
        if ($this->companyId === null) {
            throw new \LogicException('SalesExpenseVendorService: forCompany()が呼ばれる前に実行されました。');
        }

        return $this->companyId;
    }

    private function aliases(): SalesExpenseAliasService
    {
        return $this->aliasService ??= (new SalesExpenseAliasService())->forCompany($this->requireCompanyId());
    }

    /**
     * 会社の全会計年度（各年度の現在有効な版）の明細を、名寄せ後の科目名・支払先名で月別に集計して持つ。
     * 支払先が空欄（内訳未記入）の行は、支払先ではないので対象外にする。
     *
     * @return array<int, array{fy:int, dept:string, category:string, vendor:string, month:int, amount:float}>
     */
    private function rows(): array
    {
        if ($this->rows !== null) {
            return $this->rows;
        }

        $imports = SalesExpenseImport::where('company_id', $this->requireCompanyId())
            ->orderBy('fiscal_year')->orderBy('version')
            ->get(['id', 'fiscal_year'])
            ->keyBy('fiscal_year');
        $this->fiscalYears = $imports->keys()->map(fn ($y) => (int) $y)->all();

        $result = [];
        foreach ($imports as $fiscalYear => $import) {
            $fiscalYear = (int) $fiscalYear;
            $rows = SalesExpenseLineItem::where('sales_expense_import_id', $import->id)
                ->whereNotNull('vendor_name')
                ->selectRaw('department_key, category, vendor_name, calendar_year, calendar_month, SUM(amount) as amount')
                ->groupBy('department_key', 'category', 'vendor_name', 'calendar_year', 'calendar_month')
                ->get();

            foreach ($rows as $r) {
                // 会計年度（4月始まり）の外の月は集計対象外（取込時に弾かれる想定だが念のため）
                $month = (int) $r->calendar_month;
                $expectedYear = $month >= 4 ? $fiscalYear : $fiscalYear + 1;
                if ((int) $r->calendar_year !== $expectedYear) {
                    continue;
                }
                $result[] = [
                    'fy' => $fiscalYear,
                    'dept' => $r->department_key,
                    'category' => $this->aliases()->canonicalCategory($r->department_key, $r->category),
                    'vendor' => $this->aliases()->canonicalVendor($r->vendor_name),
                    'month' => $month >= 4 ? $month - 4 : $month + 8, // 会計月の添字（4月=0 … 3月=11）
                    'amount' => (float) $r->amount,
                ];
            }
        }

        return $this->rows = $result;
    }

    /** @return array<int, int> */
    public function fiscalYears(): array
    {
        $this->rows();

        return $this->fiscalYears ?? [];
    }

    /** 検索用に、全角半角・空白・大文字小文字をそろえる */
    private static function searchKey(string $text): string
    {
        return mb_strtolower(str_replace([' ', '　'], '', mb_convert_kana($text, 'KVas')));
    }

    /**
     * 支払先ランキング（全部署・全科目の合計）。`$q`があれば、名前（名寄せした別名を含む）で絞り込み、
     * その年度に支払いがない支払先も出す。
     *
     * @return array<string, mixed>
     */
    public function ranking(int $fiscalYear, ?string $departmentKey = null, string $q = ''): array
    {
        $previousFiscalYear = $fiscalYear - 1;
        $years = $this->fiscalYears();
        $hasData = in_array($fiscalYear, $years, true);
        $previousHasData = in_array($previousFiscalYear, $years, true);

        // 支払先 => [会計年度 => 合計]、支払先 => [会計年度 => [部署 => 金額]]
        $totals = [];
        $departments = [];
        $yearTotals = [];
        foreach ($this->rows() as $r) {
            if ($departmentKey !== null && $r['dept'] !== $departmentKey) {
                continue;
            }
            $totals[$r['vendor']][$r['fy']] = ($totals[$r['vendor']][$r['fy']] ?? 0.0) + $r['amount'];
            $departments[$r['vendor']][$r['fy']][$r['dept']] = ($departments[$r['vendor']][$r['fy']][$r['dept']] ?? 0.0) + $r['amount'];
            $yearTotals[$r['fy']] = ($yearTotals[$r['fy']] ?? 0.0) + $r['amount'];
        }

        $query = self::searchKey($q);
        $vendors = [];
        foreach ($totals as $vendor => $byYear) {
            $current = $byYear[$fiscalYear] ?? 0.0;
            if ($query === '') {
                if ($current <= 0) {
                    continue;
                }
            } else {
                $names = array_merge([$vendor], $this->aliases()->vendorNamesFor($vendor));
                if (! array_filter($names, fn ($n) => str_contains(self::searchKey($n), $query))) {
                    continue;
                }
            }

            $previous = $byYear[$previousFiscalYear] ?? 0.0;
            $diff = $previousHasData ? $current - $previous : null;
            $activeYears = array_keys(array_filter($byYear, fn ($v) => $v > 0));
            // 部署のバッジは、その年度に払いがあればその年度の部署、無ければ全年度の部署（検索で出した支払先用）
            $deptSource = array_filter($departments[$vendor][$fiscalYear] ?? [], fn ($v) => $v > 0);
            if ($deptSource === []) {
                foreach ($departments[$vendor] as $yearDepts) {
                    foreach ($yearDepts as $dept => $amount) {
                        $deptSource[$dept] = ($deptSource[$dept] ?? 0.0) + $amount;
                    }
                }
            }
            $deptKeys = array_keys(array_filter($deptSource, fn ($v) => $v > 0));
            sort($deptKeys);

            $vendors[] = [
                'vendor_name' => $vendor,
                'amount' => $current,
                'share_pct' => ($yearTotals[$fiscalYear] ?? 0) > 0 ? round($current / $yearTotals[$fiscalYear] * 100, 1) : null,
                'previous_amount' => $previousHasData ? $previous : null,
                'diff' => $diff,
                'diff_pct' => ($previousHasData && $previous > 0) ? round(($current - $previous) / $previous * 100, 1) : null,
                'is_new' => $previousHasData && $previous <= 0 && $current > 0,
                'years_active' => count($activeYears),
                'first_fiscal_year' => $activeYears ? min($activeYears) : null,
                'departments' => $deptKeys,
            ];
        }

        usort($vendors, fn ($a, $b) => [$b['amount'], $a['vendor_name']] <=> [$a['amount'], $b['vendor_name']]);
        $vendors = array_slice($vendors, 0, 300);
        foreach ($vendors as $i => &$v) {
            $v['rank'] = $v['amount'] > 0 ? $i + 1 : null;
        }
        unset($v);

        return [
            'has_data' => $hasData,
            'fiscal_year' => $fiscalYear,
            'previous_fiscal_year' => $previousFiscalYear,
            'previous_has_data' => $previousHasData,
            'total_amount' => $yearTotals[$fiscalYear] ?? 0.0,
            'vendors' => $vendors,
        ];
    }

    /**
     * 支払先カルテ（1社）。`$vendorName`は正式名でも別名でもよい（正式名へ読み替える）。
     *
     * @return array<string, mixed>
     */
    public function detail(string $vendorName, int $fiscalYear): array
    {
        $vendor = $this->aliases()->canonicalVendor($vendorName);
        $aliasNames = array_values(array_diff($this->aliases()->vendorNamesFor($vendor), [$vendor]));
        sort($aliasNames);

        $yearTotals = [];      // 全支払先の年度合計（構成比用）
        $byYear = [];          // 年度 => 合計
        $byYearDept = [];      // 年度 => 部署 => 金額
        $monthly = [];         // 年度 => 12要素
        $breakdown = [];       // 選択年度の 部署|科目 => 金額
        foreach ($this->rows() as $r) {
            $yearTotals[$r['fy']] = ($yearTotals[$r['fy']] ?? 0.0) + $r['amount'];
            if ($r['vendor'] !== $vendor) {
                continue;
            }
            $byYear[$r['fy']] = ($byYear[$r['fy']] ?? 0.0) + $r['amount'];
            $byYearDept[$r['fy']][$r['dept']] = ($byYearDept[$r['fy']][$r['dept']] ?? 0.0) + $r['amount'];
            $monthly[$r['fy']] ??= array_fill(0, 12, 0.0);
            $monthly[$r['fy']][$r['month']] += $r['amount'];
            if ($r['fy'] === $fiscalYear) {
                $k = $r['dept'] . "\0" . $r['category'];
                $breakdown[$k] = ($breakdown[$k] ?? 0.0) + $r['amount'];
            }
        }

        $allYears = $this->fiscalYears();
        $hasData = $byYear !== [];

        $years = [];
        foreach ($allYears as $fy) {
            $total = $byYear[$fy] ?? 0.0;
            $previous = $byYear[$fy - 1] ?? null;
            $years[] = [
                'fiscal_year' => $fy,
                'total_amount' => $total,
                'share_pct' => ($yearTotals[$fy] ?? 0) > 0 ? round($total / $yearTotals[$fy] * 100, 2) : null,
                'diff_pct' => ($previous !== null && $previous > 0) ? round(($total - $previous) / $previous * 100, 1) : null,
                'by_department' => $byYearDept[$fy] ?? [],
            ];
        }

        $selectedTotal = $byYear[$fiscalYear] ?? 0.0;
        $rows = [];
        foreach ($breakdown as $k => $amount) {
            [$dept, $category] = explode("\0", $k, 2);
            $rows[] = [
                'department_key' => $dept,
                'category' => $category,
                'amount' => $amount,
                'share_pct' => $selectedTotal > 0 ? round($amount / $selectedTotal * 100, 1) : null,
            ];
        }
        usort($rows, fn ($a, $b) => $b['amount'] <=> $a['amount']);

        $activeYears = array_keys(array_filter($byYear, fn ($v) => $v > 0));
        $monthlySeries = [];
        foreach ($allYears as $fy) {
            $monthlySeries[] = ['fiscal_year' => $fy, 'months' => $monthly[$fy] ?? array_fill(0, 12, 0.0)];
        }

        return [
            'has_data' => $hasData,
            'vendor_name' => $vendor,
            'aliases' => $aliasNames,
            'fiscal_year' => $fiscalYear,
            'cumulative_total' => (float) array_sum($byYear),
            'first_fiscal_year' => $activeYears ? min($activeYears) : null,
            'years_active' => count($activeYears),
            'years' => $years,
            'breakdown' => $rows,
            'monthly' => $monthlySeries,
        ];
    }
}
