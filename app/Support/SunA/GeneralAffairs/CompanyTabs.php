<?php

namespace App\Support\SunA\GeneralAffairs;

use App\Models\Company;
use Illuminate\Database\Eloquent\Collection;

/**
 * 総務の画面（社員情報・年末調整の受付）で切り分けて表示する会社の一覧。
 * 並び順: サンエー印刷（general）→ サン・ブレーン（その他）。同じ種別の中は sort_order → id。
 */
class CompanyTabs
{
    /** @return Collection<int, Company> */
    public static function list(): Collection
    {
        return Company::where('code', '!=', 'SUPERADMIN')
            ->where('active', true)
            ->orderByRaw("CASE WHEN company_type = 'general' THEN 0 ELSE 1 END")
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'name', 'company_type']);
    }
}
