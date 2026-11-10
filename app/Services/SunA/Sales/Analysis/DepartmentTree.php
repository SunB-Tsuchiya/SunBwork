<?php

namespace App\Services\SunA\Sales\Analysis;

use App\Models\SunA\Sales\Department;

/**
 * 営業部門の階層（部 → 課）。部門比較・左右比較で、「第三部（全体）」の行と、開くと出る課・直轄の行を作るのに使う。
 * 部の金額は、その部の全部（課も含む）の合計。課の金額は、その課の行だけ。会社のコンテキスト（SunaCompanyContext）が必要。
 */
class DepartmentTree
{
    /**
     * 表示順のノード。部（level 0）の直後に、その部の課（level 1）が並ぶ。
     *
     * @return array<int, array{key:string, label:string, level:int, parent:?string, has_children:bool}>
     */
    public function nodes(): array
    {
        $all = Department::query()->orderBy('sort_order')->get();
        $children = $all->whereNotNull('parent_key')->groupBy('parent_key');
        $nodes = [];
        foreach ($all->whereNull('parent_key') as $d) {
            $kids = $children->get($d->key, collect());
            $nodes[] = ['key' => $d->key, 'label' => $d->label, 'level' => 0, 'parent' => null, 'has_children' => $kids->isNotEmpty()];
            foreach ($kids as $k) {
                $nodes[] = ['key' => $k->key, 'label' => $k->label, 'level' => 1, 'parent' => $d->key, 'has_children' => false];
            }
        }

        return $nodes;
    }

    /**
     * 売上の部門別の行（部・課）から、ノードごとの金額を求める。部 = その部の全行の合計、課 = その課の行。
     *
     * @param  array<int, array{department_key:string, section_key:?string, amount:float}>  $raw
     * @return array<string, float> ノードのキー → 金額
     */
    public function amounts(array $raw): array
    {
        $out = [];
        foreach ($this->nodes() as $n) {
            $out[$n['key']] = 0.0;
        }
        foreach ($raw as $r) {
            if (isset($out[$r['department_key']])) {
                $out[$r['department_key']] += $r['amount'];
            }
            if ($r['section_key'] !== null && isset($out[$r['section_key']])) {
                $out[$r['section_key']] += $r['amount'];
            }
        }

        return $out;
    }
}
