<?php

namespace App\Models\SunA\Sales\Concerns;

use App\Services\SunA\Sales\SunaCompanyContext;
use Illuminate\Database\Eloquent\Builder;

/**
 * 売上レポート（SunA/Sales）の Model を、いまの会社で自動的に絞り込む（global scope）。
 *
 * - 会社が未設定なら、クエリの組み立て時に例外（fail-closed）
 * - 作成時は company_id を自動設定。別の会社の company_id を指定した作成は例外
 * - `withoutGlobalScopes()` はテスト・保守コマンド以外で使わない（絞り込み漏れ＝他社データの閲覧）
 * - 注意: `Model::insert()`（一括挿入）はイベントが走らないため、company_id を必ず自分で付けること
 *   （`insertScoped()` を使えば、会社の一致を検査して付与する）
 */
trait ScopedToCompany
{
    protected static function bootScopedToCompany(): void
    {
        static::addGlobalScope('suna_company', function (Builder $builder) {
            $builder->where($builder->getModel()->getTable() . '.company_id', SunaCompanyContext::requireId());
        });

        static::creating(function ($model) {
            $companyId = SunaCompanyContext::requireId();
            if ($model->company_id !== null && (int) $model->company_id !== $companyId) {
                throw new \LogicException('別の会社の売上レポートデータは作成できません。');
            }
            $model->company_id = $companyId;
        });
    }

    /**
     * 一括挿入。各行に company_id を付け、別の会社の company_id が混ざっていたら例外にする。
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public static function insertScoped(array $rows): void
    {
        $companyId = SunaCompanyContext::requireId();
        foreach ($rows as &$row) {
            if (isset($row['company_id']) && (int) $row['company_id'] !== $companyId) {
                throw new \LogicException('別の会社の売上レポートデータは挿入できません。');
            }
            $row['company_id'] = $companyId;
        }
        unset($row);

        foreach (array_chunk($rows, 500) as $chunk) {
            static::query()->insert($chunk);
        }
    }
}
