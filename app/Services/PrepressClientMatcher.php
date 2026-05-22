<?php

namespace App\Services;

use Illuminate\Support\Collection;

class PrepressClientMatcher
{
    /**
     * クライアント名を正規化する。
     * - 丸付き数字（①〜⑳等）を削除
     * - シングルクォートを削除
     * - 全角英数字 → 半角、半角カタカナ → 全角カタカナ、全角スペース → 半角スペース
     * - 括弧 （） → ()
     * - 前後 trim
     */
    public static function normalize(string $str): string
    {
        // 丸付き数字を削除（Unicode: ①-⑳, ㉑-㉟, ㊀-㊰ 等）
        $str = preg_replace('/[\x{2460}-\x{2473}\x{3251}-\x{325F}\x{3280}-\x{32B0}]/u', '', $str);

        // シングルクォートを削除
        $str = str_replace("'", '', $str);

        // mb_convert_kana: a=全角英数字→半角, K=半角カタカナ→全角, V=濁点統合, s=全角スペース→半角
        $str = mb_convert_kana($str, 'aKVs', 'UTF-8');

        // 括弧正規化
        $str = strtr($str, ['（' => '(', '）' => ')']);

        return trim($str);
    }

    /**
     * CSV の得意先名と DB クライアント一覧を照合する。
     *
     * @param  string      $rawName    CSV から読み取った得意先名（クレンジング前）
     * @param  Collection  $dbClients  DBクライアント一覧（id, name, client_code）
     * @return array{
     *   status: 'matched'|'candidates'|'unmatched',
     *   client?: object,
     *   candidates?: array
     * }
     */
    public static function match(string $rawName, Collection $dbClients): array
    {
        $normalized = self::normalize($rawName);

        if ($normalized === '') {
            return ['status' => 'unmatched'];
        }

        // 完全一致（正規化後）
        $exact = $dbClients->first(fn($c) => self::normalize($c->name) === $normalized);
        if ($exact) {
            return ['status' => 'matched', 'client' => $exact];
        }

        // 部分一致（正規化後に含む・含まれる）
        $partial = $dbClients->filter(function ($c) use ($normalized) {
            $cn = self::normalize($c->name);
            return $cn !== '' && (
                str_contains($cn, $normalized) || str_contains($normalized, $cn)
            );
        })->values()->take(5);

        if ($partial->isNotEmpty()) {
            return ['status' => 'candidates', 'candidates' => $partial->toArray()];
        }

        return ['status' => 'unmatched'];
    }

    /**
     * 氏名の正規化: normalize() に加えて姓名間のスペースを除去する。
     * 「田中 太郎」「田中　太郎」「田中太郎」すべて同じ結果になる。
     */
    public static function normalizeName(string $str): string
    {
        $str = self::normalize($str);
        // 全角・半角スペースを除去
        $str = preg_replace('/[\s\x{3000}]+/u', '', $str);
        return $str;
    }

    /**
     * CSV の担当営業名と DB 営業担当一覧を照合する。
     *
     * @param  string      $rawName    CSV から読み取った担当営業名
     * @param  Collection  $dbReps     DB営業担当一覧（id, name）
     * @return array{
     *   status: 'matched'|'candidates'|'unmatched',
     *   rep?: object,
     *   candidates?: array
     * }
     */
    public static function matchSalesRep(string $rawName, Collection $dbReps): array
    {
        $normalized = self::normalizeName($rawName);

        if ($normalized === '') {
            return ['status' => 'unmatched'];
        }

        // 完全一致（スペース除去後）
        $exact = $dbReps->first(fn($r) => self::normalizeName($r->name) === $normalized);
        if ($exact) {
            return ['status' => 'matched', 'rep' => $exact];
        }

        // 部分一致
        $partial = $dbReps->filter(function ($r) use ($normalized) {
            $rn = self::normalizeName($r->name);
            return $rn !== '' && (
                str_contains($rn, $normalized) || str_contains($normalized, $rn)
            );
        })->values()->take(5);

        if ($partial->isNotEmpty()) {
            return ['status' => 'candidates', 'candidates' => $partial->toArray()];
        }

        return ['status' => 'unmatched'];
    }

    /**
     * CSV の1行をクレンジングする。
     * - 丸付き数字・シングルクォートを削除
     * - 前後スペース trim
     */
    public static function cleanField(?string $value): string
    {
        if ($value === null) return '';
        $value = preg_replace('/[\x{2460}-\x{2473}\x{3251}-\x{325F}\x{3280}-\x{32B0}]/u', '', $value);
        $value = str_replace("'", '', $value);
        return trim($value);
    }
}
