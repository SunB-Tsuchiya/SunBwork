<?php

namespace App\Services\SalesAnalysis;

/**
 * 得意先名の決定的な正規化（候補提示専用、自動確定はしない）。
 * 全角半角・空白・括弧幅の表記ゆれだけを吸収し、法人格表記や括弧内の文言は変更しない
 * （PLAN 2.7「括弧内の区分が違う名称を勝手に統合しない」を候補生成の段階から守るため）。
 */
class ClientNameNormalizer
{
    public static function normalize(string $name): string
    {
        $text = trim($name);
        // 半角カナ→全角（濁点結合込み）、全角英数字→半角（SalesWorkbookReader::parseTitle()の部署ラベル正規化と同じ考え方）
        $text = mb_convert_kana($text, 'KVa');
        $text = str_replace([' ', '　'], '', $text);
        $text = str_replace(['（', '）'], ['(', ')'], $text);

        return mb_strtoupper($text);
    }
}
