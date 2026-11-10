<?php

namespace App\Services\SunA\Sales;

use App\Models\SunA\Sales\Department;

/**
 * 営業部門マスタの初期化と、Excelの「担当部門」の表記 → (部, 課) の対応付け。
 *
 * 部は第一部/第二部/第三部。課は第三部だけにあり、第一課/第二課/直轄（課に属さない第三部の得意先。Excelでは
 * 「営業第三部」とだけ書かれる。ユーザー確定 2026-11-10）。会社のコンテキスト（SunaCompanyContext）が必要。
 */
class DepartmentInitializer
{
    /** @var array<int, array{key:string, parent_key:?string, label:string, source_label:?string, is_direct:bool, sort_order:int}> */
    public const DEFAULTS = [
        ['key' => 'dept1', 'parent_key' => null, 'label' => '営業第一部', 'source_label' => '営業第一部', 'is_direct' => false, 'sort_order' => 1],
        ['key' => 'dept2', 'parent_key' => null, 'label' => '営業第二部', 'source_label' => '営業第二部', 'is_direct' => false, 'sort_order' => 2],
        // 第三部そのものは、Excelの表記を持たない（「営業第三部」の表記は、直轄の課に対応させる）
        ['key' => 'dept3', 'parent_key' => null, 'label' => '営業第三部', 'source_label' => null, 'is_direct' => false, 'sort_order' => 3],
        ['key' => 'dept3_sec1', 'parent_key' => 'dept3', 'label' => '第一課', 'source_label' => '営業第三部第一課', 'is_direct' => false, 'sort_order' => 4],
        ['key' => 'dept3_sec2', 'parent_key' => 'dept3', 'label' => '第二課', 'source_label' => '営業第三部第二課', 'is_direct' => false, 'sort_order' => 5],
        ['key' => 'dept3_direct', 'parent_key' => 'dept3', 'label' => '直轄', 'source_label' => '営業第三部', 'is_direct' => true, 'sort_order' => 6],
    ];

    /** まだ無い部門だけを追加する（既存の部門・並び・表記は変えない）。追加した件数を返す */
    public function ensureDefaults(): int
    {
        $added = 0;
        foreach (self::DEFAULTS as $def) {
            $exists = Department::query()->where('key', $def['key'])->exists();
            if (! $exists) {
                Department::create($def);
                $added++;
            }
        }

        return $added;
    }

    /**
     * Excelの「担当部門」の表記 → 部・課のキー。登録されていない表記は null（取込を止める）。
     *
     * @return array{department_key: string, section_key: ?string}|null
     */
    public function resolve(string $sourceLabel): ?array
    {
        $label = trim($sourceLabel);
        $dept = Department::query()->where('source_label', $label)->first();
        if ($dept === null) {
            return null;
        }

        // 課（親がある）なら、部=親・課=自分。部（親が無い）なら、課は無し
        return $dept->parent_key !== null
            ? ['department_key' => $dept->parent_key, 'section_key' => $dept->key]
            : ['department_key' => $dept->key, 'section_key' => null];
    }

    /** 画面表示用: 部・課のキー → 表示名（「営業第三部 第一課」など） */
    public function labelFor(string $departmentKey, ?string $sectionKey): string
    {
        $labels = Department::query()->pluck('label', 'key');
        $base = $labels[$departmentKey] ?? $departmentKey;

        return $sectionKey === null ? $base : $base . ' ' . ($labels[$sectionKey] ?? $sectionKey);
    }
}
