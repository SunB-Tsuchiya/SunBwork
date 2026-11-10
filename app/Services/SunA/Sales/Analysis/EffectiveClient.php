<?php

namespace App\Services\SunA\Sales\Analysis;

use Illuminate\Database\Eloquent\Builder;

/**
 * 分析の集計で、名寄せ（SUNASALES_PLAN1.md §17）を反映するための部品。
 * 売上の行（suna_sl_facts）に、メンバー表・グループ表を結合し、「実効キー」で得意先を数える。
 *   実効キー = 所属グループがあれば 'g:<グループID>'、なければ元の client_key
 *   実効名   = グループ名（なければ得意先名）
 * メンバーは会社内で1得意先につき1行（unique）なので、結合しても売上の行は増えない。会社の一致を結合条件に含める。
 * 結合すると client_key / client_name / company_id が両方の表にあるため、集計の式は必ず表名つきで書くこと。
 */
class EffectiveClient
{
    /** 実効キーの式 */
    public const KEY = "COALESCE(CONCAT('g:', cgm.group_id), suna_sl_facts.client_key)";

    /** 実効名の式（集計のなかで使う） */
    public const NAME = 'COALESCE(MAX(cg.name), MAX(suna_sl_facts.client_name))';

    public static function join(Builder $query): Builder
    {
        return $query
            ->leftJoin('suna_sl_client_group_members as cgm', function ($j) {
                $j->on('cgm.client_key', '=', 'suna_sl_facts.client_key')->on('cgm.company_id', '=', 'suna_sl_facts.company_id');
            })
            ->leftJoin('suna_sl_client_groups as cg', function ($j) {
                $j->on('cg.id', '=', 'cgm.group_id')->on('cg.company_id', '=', 'cgm.company_id');
            });
    }
}
