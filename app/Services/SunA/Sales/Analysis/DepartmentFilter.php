<?php

namespace App\Services\SunA\Sales\Analysis;

use App\Models\SunA\Sales\Department;
use Illuminate\Database\Eloquent\Builder;

/**
 * 売上分析の「部門の絞り込み」（SUNASALES_PLAN1.md §15 S2-Q4）。
 * 選択肢: 全部門／各部／第三部の各課・直轄。「第三部」を選ぶと、課と直轄をまとめる。
 * 担当部門は年ごと（売上の行ごと）に持つので、絞り込みも行の部門で行う。会社のコンテキスト（SunaCompanyContext）が必要。
 */
class DepartmentFilter
{
    public const ALL = 'all';

    /** @return array<int, array{key:string, label:string}> */
    public function options(): array
    {
        $departments = Department::query()->orderBy('sort_order')->get();
        $labels = $departments->pluck('label', 'key');
        $hasChildren = $departments->pluck('parent_key')->filter()->unique()->all();

        $options = [['key' => self::ALL, 'label' => '全部門']];
        foreach ($departments as $d) {
            if ($d->parent_key === null) {
                $options[] = ['key' => $d->key, 'label' => $d->label . (in_array($d->key, $hasChildren, true) ? '（課・直轄を含む）' : '')];
            } else {
                $options[] = ['key' => $d->key, 'label' => ($labels[$d->parent_key] ?? $d->parent_key) . ' ' . $d->label];
            }
        }

        return $options;
    }

    public function isValid(string $key): bool
    {
        return in_array($key, array_column($this->options(), 'key'), true);
    }

    /** 売上の行（Fact）のクエリに、部門の絞り込みを足す。不明なキーは、何も返さない絞り込みにする（全件が見えてしまわないように） */
    public function apply(Builder $query, string $key): Builder
    {
        if ($key === self::ALL) {
            return $query;
        }
        $dept = Department::query()->where('key', $key)->first();
        if ($dept === null) {
            return $query->whereRaw('1 = 0');
        }

        return $dept->parent_key === null
            ? $query->where('department_key', $dept->key)
            : $query->where('section_key', $dept->key);
    }

    /** 部・課のキー → 表示名（「営業第三部 第一課」など）の対応表。キーは、課があれば課、なければ部 */
    public function rowLabels(): array
    {
        $departments = Department::query()->orderBy('sort_order')->get();
        $labels = $departments->pluck('label', 'key');
        $out = [];
        foreach ($departments as $d) {
            $out[$d->key] = $d->parent_key === null ? $d->label : ($labels[$d->parent_key] ?? $d->parent_key) . ' ' . $d->label;
        }

        return $out;
    }

    /** @return array<string,int> 部門キー → 並び順 */
    public function order(): array
    {
        return Department::query()->orderBy('sort_order')->pluck('sort_order', 'key')->all();
    }
}
