<?php

namespace App\Services\SalesAnalysis;

/**
 * 売上分析の対象部署定義。企画・制作・オンデマンドの3部署すべてを
 * 初期版から選択・取込可能にする（2026-09-03変更、旧: 企画のみ）。
 */
class SalesDepartments
{
    public const LABELS = [
        'planning' => '企画',
        'production' => '制作',
        'ondemand' => 'オンデマンド',
    ];

    /** 選択・取込を許可する部署キー */
    public const ENABLED_KEYS = ['planning', 'production', 'ondemand'];

    public static function keyFromLabel(string $label): ?string
    {
        $key = array_search(trim($label), self::LABELS, true);

        return $key === false ? null : $key;
    }

    public static function labelFromKey(string $key): ?string
    {
        return self::LABELS[$key] ?? null;
    }

    public static function isEnabled(string $key): bool
    {
        return in_array($key, self::ENABLED_KEYS, true);
    }
}
