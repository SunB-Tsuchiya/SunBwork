<?php

namespace App\Services\SalesAnalysis;

use App\Models\Sales\SalesExpenseImport;
use App\Models\Sales\SalesExpenseLineItem;

/**
 * 部署別経費データ（Phase 21）の集計。売上とは別の集計軸のため`SalesQueryService`とは
 * 独立させている。粗利計算は行わない（売上と並べて表示する参考情報としての集計のみ）。
 */
class SalesExpenseQueryService
{
    private ?int $companyId = null;

    private ?SalesExpenseAliasService $aliasService = null;

    public function forCompany(int $companyId): self
    {
        $this->companyId = $companyId;
        $this->aliasService = null;

        return $this;
    }

    private function canonicalVendorName(?string $name): ?string
    {
        return $name === null ? null : $this->aliases()->canonicalVendor($name);
    }

    /**
     * 支払先別の行（vendor_name, subtotal_group, subtotal_label, amount, is_shared_split）を、
     * 支払先名の名寄せ後の名前で合算し直す。小計グループは変えない（Excelの構造を保つ）。
     *
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function mergeVendorRows($rows)
    {
        return $rows
            ->groupBy(fn ($r) => ($this->canonicalVendorName($r->vendor_name) ?? '') . "\0" . $r->subtotal_group)
            ->map(fn ($group) => (object) [
                'vendor_name' => $this->canonicalVendorName($group->first()->vendor_name),
                'subtotal_group' => $group->first()->subtotal_group,
                'subtotal_label' => $group->max('subtotal_label'),
                'amount' => $group->sum(fn ($r) => (float) $r->amount),
                'is_shared_split' => $group->max('is_shared_split'),
            ])
            ->values();
    }

    /** 科目名の名寄せ（Phase24）。取込データは元の名前のまま持ち、集計時に正式名へ読み替える */
    private function aliases(): SalesExpenseAliasService
    {
        return $this->aliasService ??= (new SalesExpenseAliasService())->forCompany($this->requireCompanyId());
    }

    /**
     * 月別集計の行を、科目名の名寄せ後の名前で合算し直す（別名の行は同じ科目として月ごとに足す）。
     * category_orderは別名どうしで小さい方（Excelでより前に登場した位置）を採る。
     *
     * @param  \Illuminate\Support\Collection<int, object>  $rows category, calendar_year, calendar_month, category_order, amount
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function mergeCategoryRows($rows, string $departmentKey)
    {
        return $rows
            ->groupBy(fn ($r) => $this->aliases()->canonicalCategory($departmentKey, $r->category) . '|' . $r->calendar_year . '|' . $r->calendar_month)
            ->map(fn ($group) => (object) [
                'category' => $this->aliases()->canonicalCategory($departmentKey, $group->first()->category),
                'calendar_year' => $group->first()->calendar_year,
                'calendar_month' => $group->first()->calendar_month,
                'category_order' => $group->min('category_order'),
                'amount' => $group->sum(fn ($r) => (float) $r->amount),
            ])
            ->values();
    }

    private function requireCompanyId(): int
    {
        if ($this->companyId === null) {
            throw new \LogicException('SalesExpenseQueryService: forCompany()が呼ばれる前にクエリメソッドが実行されました。');
        }

        return $this->companyId;
    }

    /** 指定会社・会計年度で現在有効な版のID（無ければnull） */
    private function activeImportId(int $fiscalYear): ?int
    {
        return SalesExpenseImport::where('company_id', $this->requireCompanyId())
            ->where('fiscal_year', $fiscalYear)
            ->orderByDesc('version')
            ->value('id');
    }

    /** その会計年度に経費データが1件でもあるか */
    public function hasData(int $fiscalYear): bool
    {
        return $this->activeImportId($fiscalYear) !== null;
    }

    /**
     * 指定会計年度に実データがある部署一覧（企画/制作/オンデマンド/共通費のうち該当するもの）。
     * @return array<int, string>
     */
    public function availableDepartments(int $fiscalYear): array
    {
        $importId = $this->activeImportId($fiscalYear);
        if ($importId === null) {
            return [];
        }

        return SalesExpenseLineItem::where('sales_expense_import_id', $importId)
            ->distinct()
            ->pluck('department_key')
            ->all();
    }

    /**
     * 部署×科目×月の金額表。会計年度の4月始まり順で返す。
     *
     * @return array{has_data: bool, categories: array<int, array>, total_amount: float}
     */
    public function departmentMonthlyTotals(int $fiscalYear, string $departmentKey): array
    {
        $importId = $this->activeImportId($fiscalYear);
        if ($importId === null) {
            return ['has_data' => false, 'categories' => [], 'total_amount' => 0.0];
        }

        $rows = SalesExpenseLineItem::where('sales_expense_import_id', $importId)
            ->where('department_key', $departmentKey)
            ->selectRaw('category, calendar_year, calendar_month, MIN(category_order) as category_order, SUM(amount) as amount')
            ->groupBy('category', 'calendar_year', 'calendar_month')
            ->get();

        $rows = $this->mergeCategoryRows($rows, $departmentKey);

        // インポート自体は存在しても、その部署に該当する行が無い場合は「データ無し」として扱う
        // （例: 共有シートの経費が無いまま企画/制作/オンデマンドだけ取り込まれているケース）
        if ($rows->isEmpty()) {
            return ['has_data' => false, 'categories' => [], 'total_amount' => 0.0];
        }

        $splitCategories = SalesExpenseLineItem::where('sales_expense_import_id', $importId)
            ->where('department_key', $departmentKey)
            ->where('is_shared_split', true)
            ->distinct()
            ->pluck('category')
            ->map(fn ($c) => $this->aliases()->canonicalCategory($departmentKey, $c))
            ->unique()
            ->values()
            ->all();

        $sequence = $this->fiscalMonthSequence($fiscalYear);

        $categories = $rows->groupBy('category')->map(function ($categoryRows, $category) use ($sequence, $splitCategories) {
            $byCalendar = $categoryRows->keyBy(fn ($r) => "{$r->calendar_year}-{$r->calendar_month}");

            $total = 0.0;
            $months = [];
            foreach ($sequence as $i => [$calendarYear, $calendarMonth]) {
                $row = $byCalendar->get("{$calendarYear}-{$calendarMonth}");
                $amount = $row ? (float) $row->amount : 0.0;
                $months[] = [
                    'fiscal_month_index' => $i + 1,
                    'calendar_year' => $calendarYear,
                    'calendar_month' => $calendarMonth,
                    'amount' => $amount,
                ];
                $total += $amount;
            }

            return [
                'category' => $category,
                'category_order' => (int) $categoryRows->first()->category_order,
                'months' => $months,
                'total_amount' => $total,
                'is_shared_split' => in_array($category, $splitCategories, true),
            ];
        })->sortBy('category_order')->values()->map(function ($c) {
            unset($c['category_order']);

            return $c;
        })->all();

        return [
            'has_data' => true,
            'categories' => $categories,
            'total_amount' => array_sum(array_column($categories, 'total_amount')),
        ];
    }

    /**
     * 指定科目の会社名（支払先）別内訳。「どこにどれだけ払っているか」の確認用。
     * Excel上に小計（小計①等）の区切りがあった科目は、その区切りをそのままグループとして
     * 再現する（グループはExcel登場順、グループ内は金額の大きい順）。区切りが無かった
     * 科目は常にグループ数1になり、`subtotal_label`はnullのまま（小計行は表示しない）。
     *
     * @return array{has_data: bool, groups: array<int, array{vendors: array<int, array>, subtotal_label: ?string, subtotal_amount: float}>, total_amount: float, is_shared_split: bool}
     */
    public function vendorBreakdown(int $fiscalYear, string $departmentKey, string $category): array
    {
        $importId = $this->activeImportId($fiscalYear);
        if ($importId === null) {
            return ['has_data' => false, 'groups' => [], 'total_amount' => 0.0, 'is_shared_split' => false];
        }

        $rows = SalesExpenseLineItem::where('sales_expense_import_id', $importId)
            ->where('department_key', $departmentKey)
            ->whereIn('category', $this->aliases()->categoryNamesFor($departmentKey, $category))
            ->selectRaw('vendor_name, subtotal_group, MAX(subtotal_label) as subtotal_label, SUM(amount) as amount, MAX(is_shared_split) as is_shared_split')
            ->groupBy('vendor_name', 'subtotal_group')
            ->get();

        if ($rows->isEmpty()) {
            return ['has_data' => false, 'groups' => [], 'total_amount' => 0.0, 'is_shared_split' => false];
        }

        $rows = $this->mergeVendorRows($rows);

        $total = (float) $rows->sum('amount');
        $isSplit = $rows->contains(fn ($r) => (bool) $r->is_shared_split);

        $groups = $rows->groupBy('subtotal_group')->sortKeys()->map(function ($groupRows) use ($total) {
            $vendors = $groupRows->map(fn ($r) => [
                'vendor_name' => $r->vendor_name ?? '（内訳未記入）',
                'amount' => (float) $r->amount,
                'share_pct' => $total > 0 ? round($r->amount / $total * 100, 1) : null,
            ])->sortByDesc('amount')->values()->all();

            return [
                'vendors' => $vendors,
                'subtotal_label' => $groupRows->first()->subtotal_label,
                'subtotal_amount' => array_sum(array_column($vendors, 'amount')),
            ];
        })->values()->all();

        return ['has_data' => true, 'groups' => $groups, 'total_amount' => $total, 'is_shared_split' => $isSplit];
    }

    /** 会計年度（開始暦年）の4月始まり12ヶ月を [暦年, 暦月] の配列で返す */
    private function fiscalMonthSequence(int $fiscalYear): array
    {
        $sequence = [];
        foreach (range(4, 12) as $m) {
            $sequence[] = [$fiscalYear, $m];
        }
        foreach (range(1, 3) as $m) {
            $sequence[] = [$fiscalYear + 1, $m];
        }

        return $sequence;
    }

    /** 対象会社で経費取込が存在する会計年度一覧（昇順。Phase22: 経費比較） */
    public function availableFiscalYears(): array
    {
        return SalesExpenseImport::where('company_id', $this->requireCompanyId())
            ->distinct()
            ->orderBy('fiscal_year')
            ->pluck('fiscal_year')
            ->all();
    }

    /**
     * 直近で取込実績がある会計年度の科目一覧（category_order順）。
     * 経費比較画面の科目セレクタ用（Phase22）。
     *
     * @return array<int, string>
     */
    public function categoriesForDepartment(string $departmentKey): array
    {
        foreach (array_reverse($this->availableFiscalYears()) as $fiscalYear) {
            $result = $this->departmentMonthlyTotals($fiscalYear, $departmentKey);
            if ($result['has_data']) {
                return array_column($result['categories'], 'category');
            }
        }

        return [];
    }

    /**
     * 選んだ科目の金額を、取込実績がある会計年度すべてで集計する（複数年推移。Phase22）。
     * その部署の総経費に占める割合もあわせて返す。
     *
     * @return array{years: array<int, array{fiscal_year:int, amount:float, department_total:float, share_pct:?float, is_shared_split:bool}>}
     */
    public function categoryMultiYearTrend(string $departmentKey, string $category): array
    {
        $years = [];
        foreach ($this->availableFiscalYears() as $fiscalYear) {
            $result = $this->departmentMonthlyTotals($fiscalYear, $departmentKey);
            $categoryRow = collect($result['categories'])->firstWhere('category', $category);
            $amount = $categoryRow['total_amount'] ?? 0.0;
            $departmentTotal = $result['total_amount'];

            $years[] = [
                'fiscal_year' => $fiscalYear,
                'amount' => $amount,
                'department_total' => $departmentTotal,
                'share_pct' => $departmentTotal > 0 ? round($amount / $departmentTotal * 100, 1) : null,
                'is_shared_split' => $categoryRow['is_shared_split'] ?? false,
            ];
        }

        return ['years' => $years];
    }

    /**
     * 支払先別の金額をExcelの小計区切りを無視してフラットに集計する（依存度ランキング用。Phase22）。
     * `vendorBreakdown()`はExcel構造の再現が目的のため別メソッドとして分離している。
     *
     * @return array{has_data: bool, vendors: array<int, array{vendor_name:string, amount:float, share_pct:?float}>, total_amount: float}
     */
    public function vendorRanking(int $fiscalYear, string $departmentKey, string $category): array
    {
        $importId = $this->activeImportId($fiscalYear);
        if ($importId === null) {
            return ['has_data' => false, 'vendors' => [], 'total_amount' => 0.0];
        }

        $rows = SalesExpenseLineItem::where('sales_expense_import_id', $importId)
            ->where('department_key', $departmentKey)
            ->whereIn('category', $this->aliases()->categoryNamesFor($departmentKey, $category))
            ->selectRaw('vendor_name, SUM(amount) as amount')
            ->groupBy('vendor_name')
            ->get();

        if ($rows->isEmpty()) {
            return ['has_data' => false, 'vendors' => [], 'total_amount' => 0.0];
        }

        // 支払先名の名寄せ後の名前で合算し直す
        $rows = $rows
            ->groupBy(fn ($r) => $this->canonicalVendorName($r->vendor_name) ?? '')
            ->map(fn ($group) => (object) [
                'vendor_name' => $this->canonicalVendorName($group->first()->vendor_name),
                'amount' => $group->sum(fn ($r) => (float) $r->amount),
            ])
            ->values();

        $total = (float) $rows->sum('amount');
        $vendors = $rows->map(fn ($r) => [
            'vendor_name' => $r->vendor_name ?? '（内訳未記入）',
            'amount' => (float) $r->amount,
            'share_pct' => $total > 0 ? round($r->amount / $total * 100, 1) : null,
        ])->sortByDesc('amount')->values()->all();

        return ['has_data' => true, 'vendors' => $vendors, 'total_amount' => $total];
    }

    /**
     * 選んだ会計年度の支払先ランキングに、前年度の同じ支払先の金額・増減を付加する（Phase22）。
     * 前年に無かった支払先はprevious_amountがnullになる。前年に存在し今年消えた支払先は
     * 「ざっくりで良い」という要件に合わせ、複雑な離脱判定はせず一覧に含めない。
     *
     * @return array{has_data: bool, vendors: array<int, array>, total_amount: float, previous_fiscal_year: int, previous_has_data: bool}
     */
    public function vendorComparison(int $fiscalYear, string $departmentKey, string $category): array
    {
        $current = $this->vendorRanking($fiscalYear, $departmentKey, $category);
        $previous = $this->vendorRanking($fiscalYear - 1, $departmentKey, $category);
        $previousByVendor = collect($previous['vendors'])->keyBy('vendor_name');

        $vendors = collect($current['vendors'])->map(function ($v) use ($previousByVendor) {
            $prev = $previousByVendor->get($v['vendor_name']);
            $prevAmount = $prev['amount'] ?? null;
            $diff = $prevAmount !== null ? $v['amount'] - $prevAmount : null;

            return array_merge($v, [
                'previous_amount' => $prevAmount,
                'diff' => $diff,
                'diff_pct' => ($prevAmount !== null && $prevAmount > 0) ? round($diff / $prevAmount * 100, 1) : null,
            ]);
        })->values()->all();

        return [
            'has_data' => $current['has_data'],
            'vendors' => $vendors,
            'total_amount' => $current['total_amount'],
            'previous_fiscal_year' => $fiscalYear - 1,
            'previous_has_data' => $previous['has_data'],
        ];
    }

    /**
     * その年度のファイル全体（全部署）で金額（>0）が入っている最後の会計月までの月数（1〜12。データ無しは0）。
     * 経費Excelは「数値が入っていれば確定」（2026-10-28ユーザー確認）なので、これを最終実績月とみなす。
     * 部署ごとではなくファイル全体で判定する（ある部署の月がたまたま0円でも実績月として扱うため）。
     */
    public function actualMonths(int $fiscalYear): int
    {
        $importId = $this->activeImportId($fiscalYear);
        if ($importId === null) {
            return 0;
        }

        $totals = SalesExpenseLineItem::where('sales_expense_import_id', $importId)
            ->selectRaw('calendar_year, calendar_month, SUM(amount) as amount')
            ->groupBy('calendar_year', 'calendar_month')
            ->get()
            ->mapWithKeys(fn ($r) => ["{$r->calendar_year}-{$r->calendar_month}" => (float) $r->amount]);

        $last = 0;
        foreach ($this->fiscalMonthSequence($fiscalYear) as $i => [$y, $m]) {
            if (($totals["{$y}-{$m}"] ?? 0.0) > 0) {
                $last = $i + 1;
            }
        }

        return $last;
    }

    /**
     * 部署シート（Phase23/24）: Excelの部署シート（科目→会社名→4月〜3月→累計、小計・合計）を1画面で
     * 再現するためのデータ。科目はExcel登場順、小計グループ・支払先もExcel登場順（支払先は明細の
     * 登録順=MIN(id)から復元）。比較年度（既定は前年度）の月別金額・合計を付加する。
     *
     * 期の途中の年度（最終実績月が3月より前）は、既定で比較年度も同じ月までの累計で比較する（YTD比較）。
     * `$comparePeriod`で'ytd'（同期間）/'full'（通年）を明示できる。
     *
     * @return array<string, mixed>
     */
    public function departmentSheet(int $fiscalYear, string $departmentKey, ?int $compareFiscalYear = null, ?string $comparePeriod = null): array
    {
        $compareFiscalYear ??= $fiscalYear - 1;
        $sequence = $this->fiscalMonthSequence($fiscalYear);
        $months = array_map(fn ($m) => ['calendar_year' => $m[0], 'calendar_month' => $m[1]], $sequence);
        $actualMonths = $this->actualMonths($fiscalYear);
        $isPartial = $actualMonths > 0 && $actualMonths < 12;
        $period = in_array($comparePeriod, ['ytd', 'full'], true) ? $comparePeriod : ($isPartial ? 'ytd' : 'full');
        // 通年の年度ではYTDと通年が同じ意味になるため、レスポンス上は常に'full'とする
        if (! $isPartial) {
            $period = 'full';
        }

        $empty = [
            'has_data' => false,
            'fiscal_year' => $fiscalYear,
            'previous_fiscal_year' => $compareFiscalYear,
            'previous_has_data' => false,
            'actual_months' => $actualMonths,
            'is_partial' => $isPartial,
            'compare_period' => $period,
            'months' => $months,
            'categories' => [],
            'month_totals' => array_fill(0, 12, 0.0),
            'previous_month_totals' => array_fill(0, 12, 0.0),
            'vanished_vendors' => [],
            'total_amount' => 0.0,
            'previous_total_amount' => null,
        ];

        $importId = $this->activeImportId($fiscalYear);
        if ($importId === null) {
            return $empty;
        }

        $rows = SalesExpenseLineItem::where('sales_expense_import_id', $importId)
            ->where('department_key', $departmentKey)
            ->selectRaw('category, subtotal_group, vendor_name, calendar_year, calendar_month, MIN(id) as first_id, MIN(category_order) as category_order, MAX(subtotal_label) as subtotal_label, MAX(is_shared_split) as is_shared_split, SUM(amount) as amount')
            ->groupBy('category', 'subtotal_group', 'vendor_name', 'calendar_year', 'calendar_month')
            ->get();

        if ($rows->isEmpty()) {
            return $empty;
        }

        // 科目名を名寄せ後の正式名へ読み替える（以降のグルーピングは正式名で行い、別名の行は月ごとに合算される）
        foreach ($rows as $r) {
            $r->category = $this->aliases()->canonicalCategory($departmentKey, $r->category);
            $r->vendor_name = $this->canonicalVendorName($r->vendor_name);
        }

        // 名寄せで統合した支払先が、同じ科目内の別の小計グループにまたがる場合は、最初に出てくるグループにまとめる
        // （前年比較で、統合した支払先の前年金額が二重に使われないようにするため。名寄せしていない支払先は変えない）
        $aliasTargets = array_flip(array_values($this->aliases()->vendorMap()));
        if ($aliasTargets !== []) {
            $firstGroup = [];
            foreach ($rows as $r) {
                if ($r->vendor_name !== null && isset($aliasTargets[$r->vendor_name])) {
                    $k = $r->category . "\0" . $r->vendor_name;
                    $firstGroup[$k] = min($firstGroup[$k] ?? PHP_INT_MAX, (int) $r->subtotal_group);
                }
            }
            foreach ($rows as $r) {
                if ($r->vendor_name !== null && isset($aliasTargets[$r->vendor_name])) {
                    $r->subtotal_group = $firstGroup[$r->category . "\0" . $r->vendor_name];
                }
            }
        }

        $monthIndex = [];
        foreach ($sequence as $i => [$y, $m]) {
            $monthIndex["{$y}-{$m}"] = $i;
        }

        // YTD比較: 比較年度の月を、当年の最終実績月より後ろはゼロにする
        $limit = $period === 'ytd' ? $actualMonths : 12;
        [$previousCategoryMonths, $previousVendorMonths, $previousHasData] = $this->previousSheetMonths($compareFiscalYear, $departmentKey, $limit);

        $monthTotals = array_fill(0, 12, 0.0);
        $categories = $rows->groupBy('category')->map(function ($categoryRows, $category) use ($monthIndex, &$monthTotals, $previousCategoryMonths, $previousVendorMonths, $previousHasData) {
            $categoryMonths = array_fill(0, 12, 0.0);

            $groups = $categoryRows->groupBy('subtotal_group')->sortKeys()->map(function ($groupRows) use ($monthIndex, &$categoryMonths, $category, $previousVendorMonths, $previousHasData) {
                $groupMonths = array_fill(0, 12, 0.0);

                $vendors = $groupRows->groupBy(fn ($r) => $r->vendor_name ?? '（内訳未記入）')->map(function ($vendorRows, $vendorName) use ($monthIndex, &$groupMonths, &$categoryMonths, $category, $previousVendorMonths, $previousHasData) {
                    $vendorMonths = array_fill(0, 12, 0.0);
                    foreach ($vendorRows as $r) {
                        $i = $monthIndex["{$r->calendar_year}-{$r->calendar_month}"] ?? null;
                        if ($i === null) {
                            continue; // 会計年度外の月（取込時に弾かれる想定だが念のため集計対象外）
                        }
                        $vendorMonths[$i] += (float) $r->amount;
                        $groupMonths[$i] += (float) $r->amount;
                        $categoryMonths[$i] += (float) $r->amount;
                    }
                    $total = array_sum($vendorMonths);
                    $previousMonths = $previousVendorMonths[$category][$vendorName] ?? null;

                    return array_merge(
                        ['vendor_name' => $vendorName, 'months' => $vendorMonths, 'total_amount' => $total, 'first_id' => (int) $vendorRows->min('first_id'), 'previous_months' => $previousMonths ?? array_fill(0, 12, 0.0)],
                        $this->previousComparison($total, $previousMonths === null ? null : array_sum($previousMonths), $previousHasData)
                    );
                })->sortBy('first_id')->values()->map(function ($v) {
                    unset($v['first_id']);

                    return $v;
                })->all();

                return [
                    'subtotal_label' => $groupRows->first()->subtotal_label,
                    'subtotal_months' => $groupMonths,
                    'subtotal_amount' => array_sum($groupMonths),
                    'vendors' => $vendors,
                ];
            })->values()->all();

            foreach ($categoryMonths as $i => $v) {
                $monthTotals[$i] += $v;
            }
            $total = array_sum($categoryMonths);
            $previousMonths = $previousCategoryMonths[$category] ?? null;

            return array_merge([
                'category' => $category,
                'category_order' => (int) $categoryRows->min('category_order'),
                'is_shared_split' => $categoryRows->contains(fn ($r) => (bool) $r->is_shared_split),
                'months' => $categoryMonths,
                'total_amount' => $total,
                'previous_months' => $previousMonths ?? array_fill(0, 12, 0.0),
                'groups' => $groups,
            ], $this->previousComparison($total, $previousMonths === null ? null : array_sum($previousMonths), $previousHasData));
        })->sortBy('category_order')->values()->map(function ($c) {
            unset($c['category_order']);

            return $c;
        })->all();

        $total = array_sum($monthTotals);
        foreach ($categories as &$c) {
            $c['share_pct'] = $total > 0 ? round($c['total_amount'] / $total * 100, 1) : null;
        }
        unset($c);

        // 比較年度の部署合計（当年に無くなった科目も含む正確な合計）
        $previousMonthTotals = array_fill(0, 12, 0.0);
        foreach ($previousCategoryMonths as $months12) {
            foreach ($months12 as $i => $v) {
                $previousMonthTotals[$i] += $v;
            }
        }

        return array_merge($empty, [
            'has_data' => true,
            'previous_has_data' => $previousHasData,
            'vanished_vendors' => $previousHasData ? $this->vanishedVendors($categories, $previousVendorMonths) : [],
            'categories' => $categories,
            'month_totals' => $monthTotals,
            'previous_month_totals' => $previousMonthTotals,
            'total_amount' => $total,
            'previous_total_amount' => $previousHasData ? array_sum($previousMonthTotals) : null,
        ]);
    }

    /**
     * 比較年度にあって当年（実績月まで）に無い支払先（注目ポイントの「消えた可能性」）。比較年度の月は
     * YTD時は最終実績月までに切ってあるので、「同じ期間に払っていたのに今年は払っていない」支払先になる。
     * 当年に科目ごと無い場合も含める（科目名の表記ゆれは名寄せ済み）。
     *
     * @param  array<int, array>  $categories 当年の科目（groups→vendors）
     * @param  array<string, array<string, array<int, float>>>  $previousVendorMonths
     * @return array<int, array{category: string, vendor_name: string, previous_total_amount: float, category_vanished: bool}>
     */
    private function vanishedVendors(array $categories, array $previousVendorMonths): array
    {
        $current = [];
        foreach ($categories as $c) {
            foreach ($c['groups'] as $g) {
                foreach ($g['vendors'] as $v) {
                    $current[$c['category']][$v['vendor_name']] = true;
                }
            }
        }

        $result = [];
        foreach ($previousVendorMonths as $category => $vendors) {
            foreach ($vendors as $vendorName => $months12) {
                $total = array_sum($months12);
                if ($total > 0 && ! isset($current[$category][$vendorName])) {
                    $result[] = [
                        'category' => $category,
                        'vendor_name' => $vendorName,
                        'previous_total_amount' => $total,
                        'category_vanished' => ! isset($current[$category]),
                    ];
                }
            }
        }
        usort($result, fn ($a, $b) => $b['previous_total_amount'] <=> $a['previous_total_amount']);

        return $result;
    }

    /**
     * 比較年度（無ければデータ無し）の、科目別・科目×支払先別の月別金額（会計年度順の12要素）。
     * `$limit`が12未満のときは、その月数より後ろの月をゼロにする（YTD比較用）。
     *
     * @return array{0: array<string, array<int, float>>, 1: array<string, array<string, array<int, float>>>, 2: bool}
     */
    private function previousSheetMonths(int $fiscalYear, string $departmentKey, int $limit): array
    {
        $importId = $this->activeImportId($fiscalYear);
        if ($importId === null) {
            return [[], [], false];
        }

        $rows = SalesExpenseLineItem::where('sales_expense_import_id', $importId)
            ->where('department_key', $departmentKey)
            ->selectRaw('category, vendor_name, calendar_year, calendar_month, SUM(amount) as amount')
            ->groupBy('category', 'vendor_name', 'calendar_year', 'calendar_month')
            ->get();

        if ($rows->isEmpty()) {
            return [[], [], false];
        }

        $monthIndex = [];
        foreach ($this->fiscalMonthSequence($fiscalYear) as $i => [$y, $m]) {
            $monthIndex["{$y}-{$m}"] = $i;
        }

        $categoryMonths = [];
        $vendorMonths = [];
        foreach ($rows as $r) {
            $i = $monthIndex["{$r->calendar_year}-{$r->calendar_month}"] ?? null;
            if ($i === null || $i >= $limit) {
                continue;
            }
            $name = $this->canonicalVendorName($r->vendor_name) ?? '（内訳未記入）';
            $category = $this->aliases()->canonicalCategory($departmentKey, $r->category);
            $categoryMonths[$category] ??= array_fill(0, 12, 0.0);
            $vendorMonths[$category][$name] ??= array_fill(0, 12, 0.0);
            $categoryMonths[$category][$i] += (float) $r->amount;
            $vendorMonths[$category][$name][$i] += (float) $r->amount;
        }

        // 比較年度にデータ自体はあるが、YTDの範囲に金額が無い支払先・科目も「あった」として扱うため、
        // 範囲外の行も存在だけは登録しておく（新規判定が「前年に存在しない」ことを意味するように）
        foreach ($rows as $r) {
            $name = $this->canonicalVendorName($r->vendor_name) ?? '（内訳未記入）';
            $category = $this->aliases()->canonicalCategory($departmentKey, $r->category);
            $categoryMonths[$category] ??= array_fill(0, 12, 0.0);
            $vendorMonths[$category][$name] ??= array_fill(0, 12, 0.0);
        }

        return [$categoryMonths, $vendorMonths, true];
    }

    /**
     * 前年比の共通項目。前年度データ自体が無ければ全てnull（画面は「—」）、
     * 前年度データはあるがこの科目・支払先が無ければprevious_total_amountがnull（画面は「新規」）。
     */
    private function previousComparison(float $current, ?float $previous, bool $previousHasData): array
    {
        if (! $previousHasData) {
            return ['previous_total_amount' => null, 'diff' => null, 'diff_pct' => null, 'is_new' => false];
        }
        if ($previous === null) {
            return ['previous_total_amount' => null, 'diff' => null, 'diff_pct' => null, 'is_new' => true];
        }

        return [
            'previous_total_amount' => $previous,
            'diff' => $current - $previous,
            'diff_pct' => $previous > 0 ? round(($current - $previous) / $previous * 100, 1) : null,
            'is_new' => false,
        ];
    }

    /**
     * 部署比較（Phase24-4）: 部署ごとの年間合計・月別・前年比と、科目×部署のクロス表。
     * 各部署の`departmentSheet()`を束ねる（比較年度・同期間比較の扱いは部署シートと同じ）。
     * 共通費（shared）も1部署として並べる（折半の注記は部署シート側のバッジで見える）。
     *
     * @param  array<int, string>  $departmentKeys
     * @return array<string, mixed>
     */
    public function departmentsOverview(int $fiscalYear, array $departmentKeys, ?int $compareFiscalYear = null, ?string $comparePeriod = null): array
    {
        $sheets = [];
        foreach ($departmentKeys as $key) {
            $sheets[$key] = $this->departmentSheet($fiscalYear, $key, $compareFiscalYear, $comparePeriod);
        }
        $first = $sheets !== [] ? reset($sheets) : $this->departmentSheet($fiscalYear, 'planning', $compareFiscalYear, $comparePeriod);

        $monthTotals = array_fill(0, 12, 0.0);
        $previousMonthTotals = array_fill(0, 12, 0.0);
        $cross = [];
        $departments = [];
        $previousHasData = false;
        foreach ($sheets as $key => $sheet) {
            $previousHasData = $previousHasData || $sheet['previous_has_data'];
            foreach ($sheet['month_totals'] as $i => $v) {
                $monthTotals[$i] += $v;
                $previousMonthTotals[$i] += $sheet['previous_month_totals'][$i];
            }

            $categories = [];
            foreach ($sheet['categories'] as $c) {
                $categories[] = [
                    'category' => $c['category'],
                    'total_amount' => $c['total_amount'],
                    'previous_total_amount' => $c['previous_total_amount'],
                    'diff_pct' => $c['diff_pct'],
                    'is_new' => $c['is_new'],
                ];
                $cross[$c['category']]['by_department'][$key] = $c['total_amount'];
                $cross[$c['category']]['total_amount'] = ($cross[$c['category']]['total_amount'] ?? 0.0) + $c['total_amount'];
            }

            $previousTotal = $sheet['previous_total_amount'];
            $departments[] = [
                'department_key' => $key,
                'has_data' => $sheet['has_data'],
                'total_amount' => $sheet['total_amount'],
                'previous_total_amount' => $previousTotal,
                'diff' => $previousTotal !== null ? $sheet['total_amount'] - $previousTotal : null,
                'diff_pct' => ($previousTotal !== null && $previousTotal > 0) ? round(($sheet['total_amount'] - $previousTotal) / $previousTotal * 100, 1) : null,
                'month_totals' => $sheet['month_totals'],
                'previous_month_totals' => $sheet['previous_month_totals'],
                'categories' => $categories,
            ];
        }

        $total = array_sum($monthTotals);
        $previousTotal = $previousHasData ? array_sum($previousMonthTotals) : null;
        foreach ($departments as &$d) {
            $d['share_pct'] = $total > 0 ? round($d['total_amount'] / $total * 100, 1) : null;
        }
        unset($d);

        $crossRows = [];
        foreach ($cross as $category => $row) {
            $crossRows[] = ['category' => $category, 'by_department' => $row['by_department'], 'total_amount' => $row['total_amount']];
        }
        usort($crossRows, fn ($a, $b) => $b['total_amount'] <=> $a['total_amount']);

        return [
            'has_data' => (bool) array_filter($departments, fn ($d) => $d['has_data']),
            'fiscal_year' => $fiscalYear,
            'previous_fiscal_year' => $first['previous_fiscal_year'],
            'previous_has_data' => $previousHasData,
            'actual_months' => $first['actual_months'],
            'is_partial' => $first['is_partial'],
            'compare_period' => $first['compare_period'],
            'months' => $first['months'],
            'departments' => $departments,
            'month_totals' => $monthTotals,
            'previous_month_totals' => $previousMonthTotals,
            'total_amount' => $total,
            'previous_total_amount' => $previousTotal,
            'diff_pct' => ($previousTotal !== null && $previousTotal > 0) ? round(($total - $previousTotal) / $previousTotal * 100, 1) : null,
            'cross' => $crossRows,
        ];
    }
}
