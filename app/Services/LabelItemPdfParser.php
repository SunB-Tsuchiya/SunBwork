<?php

namespace App\Services;

class LabelItemPdfParser
{
    /**
     * アイテムPDFのOCRテキストを解析してテスト・アイテム・一式フラグを抽出する。
     *
     * @param string $ocrText    ocr.space から返ってきた生テキスト
     * @param array  $dbTestNames LabelTestName::get(['id','name']) の配列
     */
    public function parse(string $ocrText, array $dbTestNames = []): array
    {
        $lines     = preg_split('/\r?\n/', $ocrText);
        $tests     = [];
        $items     = [];
        $ichishiki = false;

        foreach ($lines as $raw) {
            // 全角英数 → 半角に正規化してトリム
            $line = trim(mb_convert_kana($raw, 'as', 'UTF-8'));
            if ($line === '') continue;

            // ── テスト行 ──────────────────────────────────────────────────
            // 例: "3/21 実施マイファーストテスト"
            //     "3/21・22 実施学習力育成テスト新4年"
            if (preg_match(
                '/(\d{1,2})\/(\d{1,2})(?:[・〜~](?:\d{1,2}\/)?(\d{1,2}))?\s*実施\s*(.+)/u',
                $line, $m
            )) {
                [$all, $m1, $m2, $m3, $nameRaw] = [$m[0], $m[1], $m[2], $m[3] ?? null, trim($m[4])];
                $dateRaw  = $m3 ? "{$m1}/{$m2}・{$m3}" : "{$m1}/{$m2}";
                $gradeRaw = '';

                // 末尾の学年表記を分離 ("新4年" / "4・5年" 等)
                if (preg_match('/^(.+?)\s*(新?\d+(?:[・〜~]\d+)*年)$/u', $nameRaw, $gm)) {
                    $nameRaw  = trim($gm[1]);
                    $gradeRaw = trim($gm[2]);
                }

                $tests[] = [
                    'date_raw'           => $dateRaw,
                    'name_raw'           => $nameRaw,
                    'grade_raw'          => $gradeRaw,
                    'matched_test_names' => $this->matchTestNames($nameRaw, $dbTestNames),
                ];
                continue;
            }

            // ── maxBox行（読み捨て: inferMaxBox でアイテムテキストから判定） ──
            // "B5用紙 【最大梱包数 100部】" など — アイテム番号行の判定に影響しないのでスキップ
            if (mb_strpos($line, '最大梱包数') !== false) {
                continue;
            }

            // ── 一式フラグ ──────────────────────────────────────────────
            if (mb_strpos($line, '一式表記部署あり') !== false) {
                $ichishiki = true;
                continue;
            }

            // ── アイテム行（丸囲み数字で始まる行）───────────────────────
            // 例: "①国算社理 解答" / "④ 国語 DI答案用紙"
            if (preg_match('/^([①-⑮])/u', $line, $m)) {
                $num     = $m[1];
                $textRaw = trim(preg_replace('/\s+/u', ' ', mb_substr($line, mb_strlen($num))));
                $items[] = [
                    'num'      => $num,
                    'text_raw' => $textRaw,
                    'max_box'  => $this->inferMaxBox($textRaw),
                ];
            }
        }

        return [
            'tests'     => $tests,
            'items'     => $items,
            'ichishiki' => $ichishiki,
        ];
    }

    private function inferMaxBox(string $text): int
    {
        if (preg_match('/DI答案|答案用紙/u', $text)) return 250;
        if (preg_match('/解答|解説/u', $text))         return 100;
        if (preg_match('/問題/u', $text))              return 50;
        return 100;
    }

    /**
     * テスト名を DB の label_test_names と similar_text でスコアリングして上位3件を返す。
     */
    private function matchTestNames(string $name, array $dbTestNames): array
    {
        if (empty($dbTestNames) || $name === '') return [];

        $matches = [];
        foreach ($dbTestNames as $tn) {
            $score = 0.0;
            similar_text($name, $tn['name'], $score);
            if ($score > 30.0) {
                $matches[] = ['id' => $tn['id'], 'name' => $tn['name'], 'score' => round($score, 1)];
            }
        }
        usort($matches, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($matches, 0, 3);
    }
}
