<?php

namespace App\Services\SalesAnalysis;

use App\Models\Company;
use Illuminate\Support\Facades\Cache;

/**
 * Phase 21: 部署別経費データ（外注加工費等）機能のゲート。
 *
 * 対象は会社コード SUNBRAIN（サン・ブレーン）のみ。`SalesOrderChannels::supportsChannelsFor()`と
 * 判定条件は同じだが、意図的に別クラスとして独立させている（将来どちらかの機能だけを
 * 他社へ展開する可能性があるため、同じ条件でも機能ごとにゲートを分けておく）。
 *
 * レガシーシート名（情報出版/製版/オンデマンド/共有）→現行department_keyの対応表もここに集約する。
 */
class SalesExpenses
{
    private const SUNBRAIN_CODE = 'SUNBRAIN';

    /** @var array<string, string> 経費表のシート名 => department_key */
    public const SHEET_DEPARTMENT_MAP = [
        '情報出版' => 'planning',
        '製版' => 'production',
        'オンデマンド' => 'ondemand',
        '共有' => 'shared',
    ];

    public const SHARED_DEPARTMENT_KEY = 'shared';

    public static function supportsExpensesFor(int $companyId): bool
    {
        return Cache::remember("sales_expenses.supports.{$companyId}", 60, function () use ($companyId) {
            return Company::where('id', $companyId)->where('code', self::SUNBRAIN_CODE)->exists();
        });
    }

    /** 経費表の部署タブ表示用ラベル（sales_department_definitionsとは別の固定表示名） */
    public static function departmentLabel(string $departmentKey): string
    {
        return match ($departmentKey) {
            'planning' => '企画',
            'production' => '制作',
            'ondemand' => 'オンデマンド',
            self::SHARED_DEPARTMENT_KEY => '共通費',
            default => $departmentKey,
        };
    }
}
