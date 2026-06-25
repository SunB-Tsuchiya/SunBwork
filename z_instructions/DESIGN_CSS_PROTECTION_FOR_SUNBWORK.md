# SunBWork CSS保護 実装指示書

作成日: 2026-06-25  
対象プロジェクト: SunBWork (`/home/w229/SunBwork/`)  
目的: NDBSystemで実装したCSS隠蔽処理を、SunBWorkのn-demo機能にも適用する

---

## 背景・問題

SunBWorkの `https://sun-brain.co.jp/members/n-demo` では、ブラウザのデベロッパーツールやソース表示を使うと、問題HTMLに使われているCSSクラス名（`fraction`, `square`, `inline-box` 等）と、それらのスタイル定義（`public/n_sample.css`）が両方公開されてしまっている。

**漏れているもの：**
- `public/n_sample.css` — 分数（`.fraction`）・番号ボックス（`.square`, `.inline-box`）・空欄（`.blank`）等の独自レイアウトの実装
- HTML内のクラス名（`class="fraction"` 等）

**ゴール：**
- `body_html` に含まれるクラス属性をサーバー側でインラインstyleに変換してからクライアントに送信
- `public/n_sample.css` をpublicから撤去（直接アクセス不可に）
- ブラウザDevToolsで見えるのはインラインstyleのみ。クラス名もCSSファイルも見えない

---

## NDBSystemでの実装（参考）

NDBSystemでは `app/Services/HtmlStyleInliner.php` を作成し、コントローラーで `body_html` をサーバー側で変換してからInertiaに渡している。同じアプローチをSunBWorkに適用する。

---

## 変更対象ファイル（SunBWork）

| ファイル | 変更内容 |
|---|---|
| `app/Services/HtmlStyleInliner.php` | 新規作成 |
| `app/Http/Controllers/NSystem/NdemoController.php` | `HtmlStyleInliner::process()` を適用 |
| `public/n_sample.css` | `z_instructions/n_sample_reference.css` に移動 |
| `resources/js/layouts/NSystemDemoLayout.vue` | CSSリンクを削除 |
| `resources/views/n_system/demo/layout.blade.php` | CSSリンクを削除 |
| `resources/js/Pages/NSystem/School.vue` | **変更不要**（class-based `:deep()` は存在しない） |

---

## Step 1: HtmlStyleInliner.php を作成

`app/Services/HtmlStyleInliner.php` を新規作成する。

```php
<?php

namespace App\Services;

/**
 * body_html の class 属性をインライン style に変換する。
 * CSSファイルからクラス名・スタイル定義を隠蔽するために使用。
 */
class HtmlStyleInliner
{
    // class名 → inline style のマッピング（n_sample.css の全クラスを網羅）
    private const MAP = [
        // 構造系
        'q_dai'         => 'clear:both;margin-top:20px;line-height:1.7',
        'q_chu'         => 'clear:both;margin:1.2em 0 0 1.5em;line-height:1.7',
        'q_sho'         => 'clear:both;margin:1em 0 0 3em;line-height:1.7',
        'a_all'         => 'line-height:1.7',
        'a_block'       => 'clear:both;margin-top:16px',
        'a_score'       => 'margin-top:12px;padding:4px 8px;background:#f0f0f0;font-size:.85em;color:#555;line-height:1.6',
        'right-figure'  => 'text-align:right;margin:.75rem 0',
        'inline-figure' => 'display:block;clear:both;max-width:100%;height:auto;margin:.5em 0',

        // 番号ボックス系
        'square'        => 'display:inline-flex;align-items:center;justify-content:center;width:1.5em;height:1.5em;border:2px solid #1a3a6b;font-weight:bold;margin-right:.25em;vertical-align:middle;flex-shrink:0',
        'inline-box'    => 'display:inline-flex;align-items:center;justify-content:center;min-width:1.8em;height:1.4em;border:1.5px solid #374151;padding:0 .15em;font-weight:bold;vertical-align:middle',
        'blank'         => 'display:inline-block;vertical-align:text-bottom;margin:0 2px;min-width:3.6em;height:1.2em;border:solid .1em #333;background:#fff;font-size:.833em;line-height:1.1em;text-align:center',
        'round'         => 'display:inline-block;vertical-align:text-bottom;margin:0 1px;text-align:center;font-size:.75em;line-height:1.333em;width:1.333em;height:1.333em;border:.1em solid #333;border-radius:50%',
        'encircle'      => 'display:block;margin:.5em 0;padding:.5em .8em;border:solid .1em #333;background:#fff',

        // 分数
        'fraction'      => 'display:inline-flex;flex-direction:column;vertical-align:middle;text-align:center;font-size:.85em;margin:0 2px',
        'numerator'     => 'border-bottom:1px solid #333;padding:0 2px;line-height:1.4',
        'denominator'   => 'padding:0 2px;line-height:1.4',
        'big-paren'     => 'display:inline-block;font-size:2em;line-height:1;vertical-align:middle',

        // テキスト装飾
        'underline'     => 'font-weight:normal;border-bottom:solid 1px #333;line-height:1',
        'wavyline'      => 'font-weight:normal;text-decoration:underline wavy',
        'kenten'        => '-webkit-text-emphasis:filled sesame;text-emphasis:filled sesame;-webkit-text-emphasis-position:over right;text-emphasis-position:over right',
        'kenten-maru'   => '-webkit-text-emphasis:filled circle;text-emphasis:filled circle',

        // 表
        'idml-table'    => 'margin:.5em 0 .5em 1em;border-collapse:collapse;font-size:.95em;line-height:1.5',
    ];

    public static function process(string $html): string
    {
        // 1. class 属性 → inline style 変換
        $html = preg_replace_callback(
            '/<[a-zA-Z][^>]*>/',
            [self::class, 'convertTag'],
            $html
        );

        // 2. td 要素にパディングを付与（idml-table の子セル）
        $html = preg_replace_callback(
            '/<td(\s[^>]*)?>/',
            function (array $m): string {
                return self::mergeStyle('<td' . ($m[1] ?? '') . '>', 'padding:3px 6px;min-width:2em');
            },
            $html
        );

        return $html;
    }

    private static function convertTag(array $m): string
    {
        $tag = $m[0];

        if (!preg_match('/\bclass="([^"]*)"/', $tag, $cm)) {
            return $tag;
        }

        $classes = array_filter(explode(' ', trim($cm[1])));
        $styles  = array_values(array_filter(
            array_map(fn ($c) => self::MAP[$c] ?? null, $classes)
        ));

        // class 属性を除去
        $tag = preg_replace('/\s*class="[^"]*"/', '', $tag);

        if (empty($styles)) {
            return $tag;
        }

        return self::mergeStyle($tag, implode(';', $styles));
    }

    private static function mergeStyle(string $tag, string $newStyle): string
    {
        if (preg_match('/\bstyle="([^"]*)"/', $tag, $sm)) {
            $combined = rtrim($sm[1], ';') . ';' . $newStyle;
            return preg_replace('/\bstyle="[^"]*"/', 'style="' . $combined . '"', $tag);
        }
        // 閉じ > の直前に style を挿入
        return preg_replace('/(\s*\/?>)$/', ' style="' . $newStyle . '"$1', $tag);
    }
}
```

---

## Step 2: NdemoController.php を更新

`app/Http/Controllers/NSystem/NdemoController.php` の `school()` メソッドを変更する。

**変更前（現在の該当箇所 110〜114行目）：**
```php
'daimons' => $daimons->map(fn ($d) => [
    'id'           => $d->id,
    'daimon_index' => $d->daimon_index,
    'body_html'    => str_replace('src="/n_images/', 'src="' . $assetBase . '/', $d->body_html),
])->values(),
```

**変更後：**
```php
'daimons' => $daimons->map(fn ($d) => [
    'id'           => $d->id,
    'daimon_index' => $d->daimon_index,
    'body_html'    => HtmlStyleInliner::process(
        str_replace('src="/n_images/', 'src="' . $assetBase . '/', $d->body_html)
    ),
])->values(),
```

また、ファイル冒頭の `use` 宣言に以下を追加：

```php
use App\Services\HtmlStyleInliner;
```

---

## Step 3: n_sample.css を public/ から撤去

`public/n_sample.css` を `z_instructions/` 配下に移動してバックアップとして保存。
直接URLアクセスできなくなることで、スタイル定義の漏洩を防ぐ。

```bash
# SunBWorkプロジェクトルートで実行
mv public/n_sample.css z_instructions/n_sample_reference.css
```

> **注意:** `z_instructions/n_sample_reference.css` は参照用バックアップ。
> 誤ってpublicに戻さないこと。

---

## Step 4: NSystemDemoLayout.vue からCSSリンクを削除

`resources/js/layouts/NSystemDemoLayout.vue` の `<Head>` 内にあるCSSリンクを削除する。

**変更前（現在の14〜18行目）：**
```html
<template>
    <Head :title="`${props.title} | N_DB SAMPLE`">
        <link rel="stylesheet" :href="`${basePath}/n_sample.css`" />
    </Head>
```

**変更後：**
```html
<template>
    <Head :title="`${props.title} | N_DB SAMPLE`" />
```

---

## Step 5: layout.blade.php からCSSリンクを削除

`resources/views/n_system/demo/layout.blade.php` の `<head>` 内にあるCSSリンクを削除する。

**変更前（現在の7行目）：**
```html
    <link rel="stylesheet" href="{{ asset('n_sample.css') }}">
```

**変更後：** その行を削除する。

---

## Step 6: npm run build

Vueファイルを変更したため、ビルドが必要。SunBWorkのビルド方法に従って実行すること。

---

## School.vue の変更は不要

`resources/js/Pages/NSystem/School.vue` のscoped stylesを確認済み。

現在のscoped styles（226〜261行目）には：
- `.slide-nav-enter-active` / `.slide-nav-leave-active` — アニメーション
- `:deep(.ndemo-body img)` — img要素セレクタ（クラス名を参照していない）
- `:deep(.ndemo-search-hit)` — 検索ハイライト（これはJSが動的に付与する専用クラス）
- `:deep([id^='daimon-']:target)` — IDセレクタ

**class-based `:deep()` ブロックはひとつもない** ため、School.vueへの変更は不要。

---

## 動作確認

実装後に以下を確認：

1. **問題表示が崩れていない** — 分数、番号ボックス、下線等が正しく表示されるか
2. **ブラウザDevToolsで確認**：
   - Elements パネルで `class="fraction"` 等のクラス属性が存在しない
   - インライン `style="..."` に変換されている
   - `n_sample.css` へのリクエストが発生しない（Networkパネル）
3. **直接URLアクセス** `https://sun-brain.co.jp/members/n_sample.css` が404になる

---

## 注意事項

- SunBWorkの `n-demo` ページにはHTMLビューア機能（出資者向け）は不要。`body_html_raw` は渡さない。
- `HtmlStyleInliner::MAP` のスタイル値はNDBSystemと同一のものを使用すること（両プロジェクトで同じn_sample.cssを使っているため）。
- もし `n_sample.css` に存在するがMAPにないクラスが見つかった場合は、NDBSystemの `app/Services/HtmlStyleInliner.php` を参照してMAPを更新すること。
