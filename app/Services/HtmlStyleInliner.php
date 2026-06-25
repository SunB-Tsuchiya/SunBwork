<?php

namespace App\Services;

/**
 * body_html の class 属性をインライン style に変換する。
 * CSSファイルからクラス名・スタイル定義を隠蔽するために使用。
 *
 * 注意: 疑似要素(::after)・子孫セレクタ・要素セレクタ(ruby/rt等)は
 * インライン style では表現不可能なため変換対象外。
 * square/inline-box の値は n_sample.css 原文ではなく School.vue の
 * :deep() オーバーライド値を使用（意図的な乖離）。
 */
class HtmlStyleInliner
{
    // class名 → inline style のマッピング
    // square/inline-box/right-figure は Vue :deep() 側の値を優先
    private const MAP = [
        // 構造系
        'q_dai'           => 'clear:both;margin-top:20px;line-height:1.7',
        'q_chu'           => 'clear:both;margin:1.2em 0 0 1.5em;line-height:1.7',
        'q_sho'           => 'clear:both;margin:1em 0 0 3em;line-height:1.7',
        'a_all'           => 'line-height:1.7',
        'a_block'         => 'clear:both;margin-top:16px',
        'a_score'         => 'margin-top:12px;padding:4px 8px;background:#f0f0f0;font-size:.85em;color:#555;line-height:1.6',
        'right-figure'    => 'text-align:right;margin:.75rem 0',
        'inline-figure'   => 'display:block;clear:both;max-width:100%;height:auto;margin:.5em 0',

        // 番号ボックス系（School.vue の :deep() 値）
        'square'          => 'display:inline-flex;align-items:center;justify-content:center;width:1.5em;height:1.5em;border:2px solid #1a3a6b;font-weight:bold;margin-right:.25em;vertical-align:middle;flex-shrink:0',
        'inline-box'      => 'display:inline-flex;align-items:center;justify-content:center;min-width:1.8em;height:1.4em;border:1.5px solid #374151;padding:0 .15em;font-weight:bold;vertical-align:middle',
        'blank'           => 'display:inline-block;vertical-align:text-bottom;margin:0 2px;min-width:3.6em;height:1.2em;border:solid .1em #333;background:#fff;font-size:.833em;line-height:1.1em;text-align:center',
        'round'           => 'display:inline-block;vertical-align:text-bottom;margin:0 1px;text-align:center;font-size:.75em;line-height:1.333em;width:1.333em;height:1.333em;border:.1em solid #333;border-radius:50%',
        'encircle'        => 'display:block;margin:.5em 0;padding:.5em .8em;border:solid .1em #333;background:#fff',
        'img-placeholder' => 'display:inline-block;margin:4px 2px;padding:2px 6px;border:1px dashed #aaa;color:#888;font-size:.8em;background:#fafafa',

        // 分数
        'fraction'        => 'display:inline-flex;flex-direction:column;vertical-align:middle;text-align:center;font-size:.85em;margin:0 2px',
        'numerator'       => 'border-bottom:1px solid #333;padding:0 2px;line-height:1.4',
        'denominator'     => 'padding:0 2px;line-height:1.4',
        'big-paren'       => 'display:inline-block;font-size:2em;line-height:1;vertical-align:middle',

        // テキスト装飾
        'underline'       => 'font-weight:normal;border-bottom:solid 1px #333;line-height:1',
        'wavyline'        => 'font-weight:normal;text-decoration:underline wavy',
        'kenten'          => '-webkit-text-emphasis:filled sesame;text-emphasis:filled sesame;-webkit-text-emphasis-position:over right;text-emphasis-position:over right',
        'kenten-maru'     => '-webkit-text-emphasis:filled circle;text-emphasis:filled circle',

        // 表
        'idml-table'      => 'margin:.5em 0 .5em 1em;border-collapse:collapse;font-size:.95em;line-height:1.5',
    ];

    // idml-table の子 td に付与するパディング
    private const TD_STYLE = 'padding:3px 6px;min-width:2em';

    public static function process(string $html): string
    {
        $result = preg_replace_callback(
            '/<[a-zA-Z][^>]*>/',
            [self::class, 'convertTag'],
            $html
        );

        // PCRE エラー時は変換をスキップして元の HTML を返す
        return $result ?? $html;
    }

    private static function convertTag(array $m): string
    {
        $tag  = $m[0];
        $isTd = (bool) preg_match('/^<td[\s>\/]/i', $tag);

        if (!preg_match('/\bclass="([^"]*)"/', $tag, $cm)) {
            return $isTd ? self::mergeStyle($tag, self::TD_STYLE) : $tag;
        }

        $classes = array_filter(explode(' ', trim($cm[1])));
        $styles  = array_values(array_filter(
            array_map(fn ($c) => self::MAP[$c] ?? null, $classes)
        ));

        // class 属性を除去
        $tag = preg_replace('/\s*class="[^"]*"/', '', $tag);

        // td パディングをクラス由来スタイルと同一の mergeStyle 呼び出しで合成
        if ($isTd) {
            $styles[] = self::TD_STYLE;
        }

        if (empty($styles)) {
            return $tag;
        }

        return self::mergeStyle($tag, implode(';', $styles));
    }

    private static function mergeStyle(string $tag, string $newStyle): string
    {
        if (preg_match('/\bstyle="([^"]*)"/', $tag, $sm)) {
            $combined = rtrim($sm[1], ';') . ';' . $newStyle;
            // preg_replace_callback で replacement を literal 扱いにし
            // $combined 内の $ や \ をバックリファレンスとして展開しない
            return preg_replace_callback(
                '/\bstyle="[^"]*"/',
                fn () => 'style="' . $combined . '"',
                $tag
            );
        }
        return preg_replace_callback(
            '/(\s*\/?>)$/',
            fn (array $n) => ' style="' . $newStyle . '"' . $n[1],
            $tag
        );
    }
}
