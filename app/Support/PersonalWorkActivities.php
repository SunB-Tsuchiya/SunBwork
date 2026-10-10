<?php

namespace App\Support;

class PersonalWorkActivities
{
    public const COLOR = '#fbcfe8';
    public const TEXT_COLOR = '#831843';
    public const BORDER_COLOR = '#f472b6';

    public const COMMON = [
        'intake_allocation' => '受付・差配',
        'preparation_specs' => '準備・仕様整理',
        'internal_improvement' => '社内改善・システム開発',
    ];

    public const GROUPS = [
        'dtp' => [
            'new_creation' => '新規作成', 'revision' => '修正',
            'data_preparation' => 'データ整備', 'delivery_check' => '納品チェック',
        ],
        'kousei' => [
            'proof_first' => '初校校正', 'proof_revision_heavy' => '修正校正（重）',
            'proof_revision_light' => '修正校正（軽）', 'manuscript_check' => '原稿チェック',
        ],
        'shinko' => [
            'progress_coordination' => '進行調整', 'manuscript_check' => '原稿チェック',
            'delivery_check' => '入稿・納品チェック', 'meeting' => '連絡・打合せ',
            'office_work' => '事務作業',
        ],
        'jimu' => [
            'voucher_check' => '伝票チェック', 'document_creation' => '書類作成',
            'document_processing' => '書類処理', 'orders_billing' => '受発注・請求対応',
            'office_work' => 'その他事務',
        ],
        'eigyo' => [
            'sales_visit' => '訪問・商談', 'sales_proposal' => '見積・提案',
            'sales_order' => '受注・納期調整', 'customer_contact' => '顧客連絡',
        ],
        'other' => ['other' => 'その他'],
    ];

    public static function codes(): array
    {
        return array_values(array_unique(array_merge(
            array_keys(self::COMMON),
            ...array_values(array_map('array_keys', self::GROUPS))
        )));
    }

    public static function allows(string $category, ?string $type): bool
    {
        return $type === null || $type === ''
            || array_key_exists($type, self::COMMON)
            || array_key_exists($type, self::GROUPS[$category] ?? []);
    }
}
