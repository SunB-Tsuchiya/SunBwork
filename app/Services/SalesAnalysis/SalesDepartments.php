<?php

namespace App\Services\SalesAnalysis;

/**
 * 売上分析の対象部署定義。将来「制作」「オンデマンド」を追加する際は
 * LABELS に追記するだけでよいが、ENABLED_KEYS に加えるまでは
 * UI・取込のどちらからも選択・取込できないようにする。
 */
class SalesDepartments
{
    public const LABELS = [
        'planning' => '企画',
        'production' => '制作',
        'ondemand' => 'オンデマンド',
    ];

    /** 初期実装で選択・取込を許可する部署キー */
    public const ENABLED_KEYS = ['planning'];

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
