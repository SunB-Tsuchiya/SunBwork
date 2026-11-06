<?php

namespace App\Models\Portfolio;

use Illuminate\Database\Eloquent\Model;

/** ポートフォリオの個人設定（アクセント色・表示名・肩書き） */
class PortfolioProfile extends Model
{
    public const DEFAULT_ACCENT = '#2563eb';

    /** デザイナーが選べるアクセント色（モノトーン基調に映える9色） */
    public const ACCENT_PALETTE = [
        '#2563eb', // ブルー
        '#0891b2', // シアン
        '#059669', // グリーン
        '#65a30d', // ライム
        '#f59e0b', // アンバー
        '#ea580c', // オレンジ
        '#dc2626', // レッド
        '#db2777', // ピンク
        '#7c3aed', // パープル
    ];

    protected $fillable = ['user_id', 'accent_color', 'display_name', 'headline'];
}
