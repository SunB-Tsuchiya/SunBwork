<?php

namespace App\Services\SalesAnalysis;

use App\Models\Sales\SalesExpenseAlias;
use App\Models\Sales\SalesExpenseImport;
use App\Models\Sales\SalesExpenseLineItem;
use Illuminate\Support\Facades\DB;

/**
 * 経費の名寄せ（Phase24）。年度ごとに表記が変わる科目名を、正式名へ読み替える。
 * 取込済みデータは書き換えず、集計時（SalesExpenseQueryService）と設定画面でだけこの対応表を通す。
 * 科目名（kind=category、部署ごと）と、支払先名（kind=vendor、部署をまたぐのでdepartment_key='all'）を扱う。
 * 名寄せは利用者が承認したグループだけに適用する（機械的な候補は自動では適用しない）。
 */
class SalesExpenseAliasService
{
    private ?int $companyId = null;

    public const VENDOR_DEPARTMENT_KEY = 'all';

    /** @var array<string, array<string, string>> 部署キー=>[別名=>正式名] */
    private array $categoryMaps = [];

    /** @var array<string, string>|null 支払先の別名=>正式名 */
    private ?array $vendorMap = null;

    public function forCompany(int $companyId): self
    {
        $this->companyId = $companyId;
        $this->categoryMaps = [];
        $this->vendorMap = null;

        return $this;
    }

    private function requireCompanyId(): int
    {
        if ($this->companyId === null) {
            throw new \LogicException('SalesExpenseAliasService: forCompany()が呼ばれる前に実行されました。');
        }

        return $this->companyId;
    }

    /** @return array<string, string> 別名=>正式名 */
    public function categoryMap(string $departmentKey): array
    {
        return $this->categoryMaps[$departmentKey] ??= SalesExpenseAlias::where('company_id', $this->requireCompanyId())
            ->where('kind', SalesExpenseAlias::KIND_CATEGORY)
            ->where('department_key', $departmentKey)
            ->pluck('canonical_name', 'alias_name')
            ->all();
    }

    public function canonicalCategory(string $departmentKey, string $name): string
    {
        return $this->categoryMap($departmentKey)[$name] ?? $name;
    }

    /**
     * 正式名に読み替えられる元の科目名すべて（正式名自身を含む）。取込データを元の名前で検索するときに使う。
     *
     * @return array<int, string>
     */
    public function categoryNamesFor(string $departmentKey, string $canonical): array
    {
        $names = [$canonical];
        foreach ($this->categoryMap($departmentKey) as $alias => $target) {
            if ($target === $canonical) {
                $names[] = $alias;
            }
        }

        return $names;
    }

    /**
     * 会社の各会計年度で現在有効な版の科目名と、出現した会計年度。
     *
     * @return array<string, array<int, int>> 科目名=>[会計年度...]（昇順）
     */
    public function categoryNamesWithYears(string $departmentKey): array
    {
        $imports = SalesExpenseImport::where('company_id', $this->requireCompanyId())
            ->orderBy('fiscal_year')->orderBy('version')
            ->get(['id', 'fiscal_year'])
            ->keyBy('fiscal_year'); // 同年度は最大versionが残る

        $result = [];
        foreach ($imports as $fiscalYear => $import) {
            $names = SalesExpenseLineItem::where('sales_expense_import_id', $import->id)
                ->where('department_key', $departmentKey)
                ->distinct()
                ->pluck('category');
            foreach ($names as $name) {
                $result[$name][] = (int) $fiscalYear;
            }
        }
        ksort($result);

        return $result;
    }

    /**
     * 設定画面用（科目名）: 登録済みグループ・統合候補・どれにも属さない科目名。
     *
     * @return array{groups: array<int, array>, suggestions: array<int, array>, unmapped: array<int, array>}
     */
    public function overview(string $departmentKey): array
    {
        return $this->buildOverview($this->categoryNamesWithYears($departmentKey), $this->categoryMap($departmentKey), fn ($n) => self::candidateKey($n));
    }

    /** 設定画面用（支払先名）。 */
    public function vendorOverview(): array
    {
        return $this->buildOverview($this->vendorNamesWithYears(), $this->vendorMap(), fn ($n) => self::vendorCandidateKey($n));
    }

    /**
     * @param  array<string, array<int, int>>  $years 名前=>[会計年度...]
     * @param  array<string, string>  $map 別名=>正式名
     */
    private function buildOverview(array $years, array $map, callable $keyFn): array
    {
        $groups = [];
        foreach ($map as $alias => $canonical) {
            $groups[$canonical][] = $alias;
        }

        $groupList = [];
        foreach ($groups as $canonical => $aliases) {
            sort($aliases);
            $groupList[] = [
                'canonical' => $canonical,
                'canonical_years' => $years[$canonical] ?? [],
                'aliases' => array_map(fn ($a) => ['name' => $a, 'years' => $years[$a] ?? []], $aliases),
            ];
        }

        // 登録済みグループに属さない名前だけを候補・未割当の対象にする
        $inGroup = array_merge(array_keys($map), array_values($map));
        $free = array_diff_key($years, array_flip($inGroup));

        $suggestions = [];
        $suggested = [];
        foreach ($this->clusterByKey(array_keys($free), $keyFn) as $names) {
            if (count($names) < 2) {
                continue;
            }
            $suggestions[] = $this->suggestionFor($names, $years);
            $suggested = array_merge($suggested, $names);
        }

        // 候補として出している名前は「手動で統合」の一覧から除く（候補を承認した後に、必要なら追加する）
        $unmapped = [];
        foreach (array_diff_key($free, array_flip($suggested)) as $name => $ys) {
            $unmapped[] = ['name' => $name, 'years' => $ys];
        }

        return ['groups' => $groupList, 'suggestions' => $suggestions, 'unmapped' => $unmapped];
    }

    // ---- 支払先名（kind=vendor） ----

    /** @return array<string, string> 別名=>正式名 */
    public function vendorMap(): array
    {
        return $this->vendorMap ??= SalesExpenseAlias::where('company_id', $this->requireCompanyId())
            ->where('kind', SalesExpenseAlias::KIND_VENDOR)
            ->where('department_key', self::VENDOR_DEPARTMENT_KEY)
            ->pluck('canonical_name', 'alias_name')
            ->all();
    }

    public function canonicalVendor(string $name): string
    {
        return $this->vendorMap()[$name] ?? $name;
    }

    /**
     * 正式名に読み替えられる元の支払先名すべて（正式名自身を含む）。
     *
     * @return array<int, string>
     */
    public function vendorNamesFor(string $canonical): array
    {
        $names = [$canonical];
        foreach ($this->vendorMap() as $alias => $target) {
            if ($target === $canonical) {
                $names[] = $alias;
            }
        }

        return $names;
    }

    /**
     * 会社の各会計年度（現在有効な版）に出てくる支払先名（全部署）と、出現した会計年度。
     *
     * @return array<string, array<int, int>> 支払先名=>[会計年度...]（昇順）
     */
    public function vendorNamesWithYears(): array
    {
        $imports = SalesExpenseImport::where('company_id', $this->requireCompanyId())
            ->orderBy('fiscal_year')->orderBy('version')
            ->get(['id', 'fiscal_year'])
            ->keyBy('fiscal_year');

        $result = [];
        foreach ($imports as $fiscalYear => $import) {
            $names = SalesExpenseLineItem::where('sales_expense_import_id', $import->id)
                ->whereNotNull('vendor_name')
                ->distinct()
                ->pluck('vendor_name');
            foreach ($names as $name) {
                $result[$name][] = (int) $fiscalYear;
            }
        }
        ksort($result);

        return $result;
    }

    public function saveVendorGroup(string $canonical, array $aliases, ?int $userId = null): void
    {
        $this->saveGroupOf(SalesExpenseAlias::KIND_VENDOR, self::VENDOR_DEPARTMENT_KEY, $canonical, $aliases, $userId);
    }

    public function approveVendorSuggestions(?int $userId = null): int
    {
        $suggestions = $this->vendorOverview()['suggestions'];
        foreach ($suggestions as $s) {
            $this->saveVendorGroup($s['canonical'], array_column($s['aliases'], 'name'), $userId);
        }

        return count($suggestions);
    }

    /**
     * 正式名と別名の対応（科目名）を保存する。同じ部署内で、正式名が別の正式名の別名になることはない（連鎖させない）。
     * 別名リストが空なら、その正式名のグループを解除する。
     *
     * @param  array<int, string>  $aliases
     */
    public function saveGroup(string $departmentKey, string $canonical, array $aliases, ?int $userId = null): void
    {
        $this->saveGroupOf(SalesExpenseAlias::KIND_CATEGORY, $departmentKey, $canonical, $aliases, $userId);
    }

    /** @param  array<int, string>  $aliases */
    private function saveGroupOf(string $kind, string $departmentKey, string $canonical, array $aliases, ?int $userId): void
    {
        $companyId = $this->requireCompanyId();
        $aliases = array_values(array_unique(array_filter($aliases, fn ($a) => $a !== '' && $a !== $canonical)));

        DB::connection('sales')->transaction(function () use ($companyId, $kind, $departmentKey, $canonical, $aliases, $userId) {
            $base = SalesExpenseAlias::where('company_id', $companyId)
                ->where('kind', $kind)
                ->where('department_key', $departmentKey);

            // この正式名のグループを作り直す（解除も兼ねる）
            (clone $base)->where('canonical_name', $canonical)->delete();
            // 正式名が過去に別名として登録されていたら外す（連鎖防止）
            (clone $base)->where('alias_name', $canonical)->delete();

            foreach ($aliases as $alias) {
                // 新しい別名が別グループの正式名だったとき、そのグループごとこちらへ併合する
                (clone $base)->where('canonical_name', $alias)->update(['canonical_name' => $canonical]);
                (clone $base)->where('alias_name', $alias)->delete();
                SalesExpenseAlias::create([
                    'company_id' => $companyId,
                    'kind' => $kind,
                    'department_key' => $departmentKey,
                    'alias_name' => $alias,
                    'canonical_name' => $canonical,
                    'created_by' => $userId,
                ]);
            }
        });

        unset($this->categoryMaps[$departmentKey]);
        $this->vendorMap = null;
    }

    /** 現在の統合候補をすべて登録する。登録したグループ数を返す。 */
    public function approveSuggestions(string $departmentKey, ?int $userId = null): int
    {
        $suggestions = $this->overview($departmentKey)['suggestions'];
        foreach ($suggestions as $s) {
            $this->saveGroup($departmentKey, $s['canonical'], array_column($s['aliases'], 'name'), $userId);
        }

        return count($suggestions);
    }

    /**
     * 科目名の機械的な名寄せキー。安全に吸収できる表記ゆれだけを扱う:
     * 全角半角・空白、先頭の「主」「主要」、「費」「料」「費用」、「・」「/」区切りの順序。
     * 意味が変わり得るもの（外注→外注加工費、修理・メンテ→修繕 等）は候補にせず、画面で人が統合する。
     */
    public static function candidateKey(string $name): string
    {
        $t = mb_convert_kana($name, 'KVas');
        $t = str_replace([' ', '　'], '', $t);
        $t = preg_replace('/^(主要|主)/u', '', $t);
        $t = preg_replace('/(費用|費|料)/u', '', $t);
        $parts = array_filter(preg_split('/[・\/]/u', $t));
        sort($parts);

        return implode('・', $parts);
    }

    /**
     * 支払先名の機械的な名寄せキー。全角半角・空白・大文字小文字、法人格の表記（株式会社・㈱・(株)・有限会社・㈲・有限）、
     * 括弧内の注記（例:「（突発分）」）だけを無視する。社名変更のように名前そのものが違うものは候補にならず、画面で人が統合する。
     */
    public static function vendorCandidateKey(string $name): string
    {
        $t = mb_convert_kana($name, 'KVas');
        $t = str_replace([' ', '　'], '', $t);
        $t = preg_replace('/\([^)]*\)/u', '', $t);
        $t = str_replace(['株式会社', '有限会社', '合同会社', '㈱', '㈲'], '', $t);
        $t = preg_replace('/^有限|有限$/u', '', $t);

        return mb_strtolower($t);
    }

    /**
     * @param  array<int, string>  $names
     * @return array<int, array<int, string>>
     */
    private function clusterByKey(array $names, callable $keyFn): array
    {
        $clusters = [];
        foreach ($names as $name) {
            $clusters[$keyFn($name)][] = $name;
        }

        return array_values($clusters);
    }

    /**
     * 統合候補。正式名は最新年度に使われている名前（同年度なら出現年数が多い方）。
     *
     * @param  array<int, string>  $names
     * @param  array<string, array<int, int>>  $years
     */
    private function suggestionFor(array $names, array $years): array
    {
        usort($names, function ($a, $b) use ($years) {
            return [max($years[$b]), count($years[$b])] <=> [max($years[$a]), count($years[$a])];
        });
        $canonical = array_shift($names);
        sort($names);

        return [
            'canonical' => $canonical,
            'canonical_years' => $years[$canonical],
            'aliases' => array_map(fn ($n) => ['name' => $n, 'years' => $years[$n]], $names),
        ];
    }
}
