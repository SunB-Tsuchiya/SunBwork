<?php

namespace App\Services\SalesAnalysis;

use App\Models\Sales\SalesDepartmentDefinition;
use Illuminate\Support\Facades\Cache;

/**
 * 売上分析の対象部署定義。
 *
 * 2026-09-05変更: 従来は企画/制作/オンデマンドの3部署をハードコードした固定値だったが、
 * これはサン・ブレーン専用の制作ライン区分であり、会社別データ分離（サンエー印刷追加）に伴い
 * 会社ごとに異なる区分を持てるよう`sales_department_definitions`テーブルへ切り出した。
 * 呼び出し側は必ず`$companyId`（`ResolvesSalesAnalysisCompany::salesAnalysisCompanyId()`で解決した値）を渡すこと。
 */
class SalesDepartments
{
    /** @return array<string, string> key => label（sort_order順） */
    public static function labelsFor(int $companyId): array
    {
        return Cache::remember("sales_departments.labels.{$companyId}", 60, function () use ($companyId) {
            return SalesDepartmentDefinition::where('company_id', $companyId)
                ->orderBy('sort_order')
                ->pluck('label', 'key')
                ->all();
        });
    }

    /** @return array<int, string> 選択・取込を許可する部署キー */
    public static function enabledKeysFor(int $companyId): array
    {
        return array_keys(self::labelsFor($companyId));
    }

    public static function labelForKey(int $companyId, string $key): ?string
    {
        return self::labelsFor($companyId)[$key] ?? null;
    }

    public static function isEnabledFor(int $companyId, string $key): bool
    {
        return in_array($key, self::enabledKeysFor($companyId), true);
    }

    public static function keyFromLabel(int $companyId, string $label): ?string
    {
        $key = array_search(trim($label), self::labelsFor($companyId), true);

        return $key === false ? null : $key;
    }
}
