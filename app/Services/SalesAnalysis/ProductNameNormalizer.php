<?php

namespace App\Services\SalesAnalysis;

/**
 * 商品名から「年度」を表す部分だけを取り除く決定的な正規化。
 * 印刷・組版会社の教材・テキスト類は年度だけ変えて毎年ほぼ同じ内容で作られるため、
 * 正規化しないと年度が変わるたびに「新規商品」「取扱終了商品」として誤検知される
 * （2026-09-05 事務・経理からの指摘。実例:「2027年度用中学入試問題集組版代」対
 * 「2026年度用中学入試問題集組版代」は同一商品として扱うべき）。
 * 対象は2000〜2040年の4桁数値のみとし、「3.4.5.6年」のような学年表記（1桁）は除去しない。
 * `SalesQueryService::productYearOverYearComparison()`専用（ランキング・個別推移は原名のまま）。
 */
class ProductNameNormalizer
{
    public static function normalize(string $name): string
    {
        $text = trim($name);
        $text = mb_convert_kana($text, 'KVa');
        // 「2027年度用」「2026年度」「2026年」「2025」（接尾辞なしの単独年）の順で長いものから除去
        $text = preg_replace('/(?:20[0-3]\d|2040)(?:年度用|年度|年)?/u', '', $text);
        // 除去でできた連続空白を1つにまとめる
        $text = preg_replace('/[ 　]+/u', ' ', $text);

        return trim($text);
    }
}
