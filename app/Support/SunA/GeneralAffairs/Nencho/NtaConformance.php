<?php

namespace App\Support\SunA\GeneralAffairs\Nencho;

/**
 * 変換した値（NtaXmlMapper）が、国税庁 XML 定義書の制約（型・桁数・文字種・コード値・範囲）に適合しているかを検査する。
 */
class NtaConformance
{
    /** @return array{checked: int, empty: int, ng: list<array>, unknown: list<string>, byForm: array} */
    public static function check(array $entries): array
    {
        $ng = [];
        $unknown = [];
        $checked = $empty = 0;
        $byForm = [];
        foreach ($entries as $e) {
            $form = $e['form'];
            $byForm[$form] ??= ['checked' => 0, 'ng' => 0, 'empty' => 0];
            if (! $e['known']) {
                $unknown[] = "{$form}: {$e['path']}";
                continue;
            }
            if ($e['value'] === null) {
                $empty++;
                $byForm[$form]['empty']++;
                continue;
            }
            $item = NtaDefinition::item($form, $e['tag']);
            $problems = self::validate($e['value'], NtaDefinition::constraint($item));
            $checked++;
            $byForm[$form]['checked']++;
            foreach ($problems as $p) {
                $ng[] = ['form' => $form, 'tag' => $e['tag'], 'path' => $e['path'], 'row' => $e['row'], 'value' => $e['value'], 'problem' => $p];
                $byForm[$form]['ng']++;
            }
        }

        return compact('checked', 'empty', 'ng', 'unknown', 'byForm');
    }

    /** @return list<string> */
    private static function validate($value, array $c): array
    {
        $p = [];
        if ($c['type'] === 'integer') {
            if (! is_int($value) && ! (is_string($value) && preg_match('/^-?\d+$/', $value))) {
                return ['整数ではありません'];
            }
            $n = (int) $value;
            if (isset($c['digits'])) {
                $len = strlen((string) abs($n));
                if ($len < $c['digits'][0] || $len > $c['digits'][1]) {
                    $p[] = "桁数が{$c['digits'][0]}～{$c['digits'][1]}桁の範囲外です";
                }
            }
            if (isset($c['range']) && ($n < $c['range'][0] || $n > $c['range'][1])) {
                $p[] = "範囲 {$c['range'][0]}～{$c['range'][1]} の外です";
            }
            if (isset($c['codes']) && ! in_array($n, $c['codes'], true)) {
                $p[] = 'コード値（' . implode('・', $c['codes']) . '）にありません';
            }

            return $p;
        }
        $str = (string) $value;
        $len = mb_strlen($str);
        if (isset($c['len']) && ($len < $c['len'][0] || $len > $c['len'][1])) {
            $p[] = "文字数が{$c['len'][0]}～{$c['len'][1]}文字の範囲外です（{$len}文字）";
        }
        $charset = $c['charset'] ?? null;
        if ($charset === '全角' && preg_match('/[\x00-\x7F\x{FF61}-\x{FF9F}]/u', $str)) {
            $p[] = '全角ではありません（半角文字が含まれています）';
        } elseif ($charset === '全角カナ' && ! preg_match('/^[ァ-ヶー　]+$/u', $str)) {
            $p[] = '全角カナではありません';
        } elseif ($charset === '半角数字' && ! preg_match('/^\d+$/', $str)) {
            $p[] = '半角数字ではありません';
        } elseif ($charset === '半角英数字' && ! preg_match('/^[A-Za-z0-9]+$/', $str)) {
            $p[] = '半角英数字ではありません';
        }

        return $p;
    }
}
