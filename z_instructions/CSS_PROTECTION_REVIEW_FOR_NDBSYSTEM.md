# HtmlStyleInliner コードレビュー結果と修正報告

作成日: 2026-06-25  
対象: SunBWork の `app/Services/HtmlStyleInliner.php`（NDBSystem も同一ファイルのため要確認）

---

## 経緯

SunBWork に CSS 隠蔽機能（`HtmlStyleInliner`）を実装後、AI コードレビュー（8アングル × 検証フェーズ）を実施した。  
NDBSystem の `HtmlStyleInliner.php` は SunBWork と同一実装のため、**同じバグが存在する可能性がある。**  
本ドキュメントでは発見したバグと SunBWork での修正内容を示す。NDBSystem 側での対応判断に使用すること。

---

## 発見バグと修正内容

### 🔴 Bug 1 — `preg_replace_callback` null 戻り値で PHP 8.2 TypeError クラッシュ

**深刻度:** Critical（ページ 500 エラー）  
**判定:** CONFIRMED

**問題:**  
`preg_replace_callback` は PCRE の上限（backtrack_limit / recursion_limit）超過時に `null` を返す。  
修正前のコードは戻り値を `null` チェックせずに次の `preg_replace_callback` の引数に渡していた。  
PHP 8.2（このプロジェクトの要件は `^8.2`）では `array|string` 型引数に `null` を渡すと **TypeError** が発生し、  
`daimons->map()` 全体が例外で止まりページが 500 クラッシュする。

**修正前:**
```php
public static function process(string $html): string
{
    // ① 戻り値が null になりうる
    $html = preg_replace_callback('/<[a-zA-Z][^>]*>/', [self::class, 'convertTag'], $html);

    // ② null を渡すと PHP 8.2 で TypeError
    $html = preg_replace_callback('/<td(\s[^>]*)?>/', function (array $m): string { ... }, $html);

    return $html;
}
```

**修正後:**
```php
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
```

また後述 Bug 3 の修正で `<td>` パスを単一パスに統合したため、第2の `preg_replace_callback` 自体を削除。

---

### 🟠 Bug 2 — `mergeStyle` でバックリファレンス展開による出力破壊

**深刻度:** High（セキュリティ / データ破壊）  
**判定:** PLAUSIBLE（実 IDML データでは `$1` は稀だが、コードレベルの脆弱性として確実に存在）

**問題:**  
`mergeStyle` が `preg_replace` の replacement 文字列に DB 由来の `$combined`（既存 style 属性値 ＋ 新スタイル）を  
エスケープなしで直接渡していた。  
`$combined` が `$1`、`${1}`、`\1` 等を含む場合、PHP の `preg_replace` がそれをバックリファレンスとして展開し  
style 属性値が破損する。

**修正前:**
```php
private static function mergeStyle(string $tag, string $newStyle): string
{
    if (preg_match('/\bstyle="([^"]*)"/', $tag, $sm)) {
        $combined = rtrim($sm[1], ';') . ';' . $newStyle;
        // $combined 内の $1 等がバックリファレンスとして展開される
        return preg_replace('/\bstyle="[^"]*"/', 'style="' . $combined . '"', $tag);
    }
    return preg_replace('/(\s*\/?>)$/', ' style="' . $newStyle . '"$1', $tag);
}
```

**修正後:**
```php
private static function mergeStyle(string $tag, string $newStyle): string
{
    if (preg_match('/\bstyle="([^"]*)"/', $tag, $sm)) {
        $combined = rtrim($sm[1], ';') . ';' . $newStyle;
        // preg_replace_callback を使い、クロージャ内で literal 文字列を返す
        // → $combined の内容がバックリファレンスとして解釈されない
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
```

**動作確認済み:**  
`<span style="color:$1" class="q_dai">` → `<span style="color:$1;clear:both;...">` と `$1` が展開されず保持される。

---

### 🟠 Bug 3 — `<td>` に二重 `preg_replace_callback` で padding が2回適用される可能性

**深刻度:** Medium（潜在バグ）  
**判定:** PLAUSIBLE

**問題:**  
修正前は2パス構成だった。

- Pass 1 (`<[a-zA-Z][^>]*>`) が `<td class="someClass">` を変換し `style="...padding:0 2px..."` を付与
- Pass 2 (`<td(\s[^>]*)?>`) が同じ `<td>` に再度 `mergeStyle` を実行し `padding:3px 6px;min-width:2em` を追記
- 結果: 同一 style 属性に `padding` が2値存在し、後者（Pass 2）が優先されてクラス由来の padding を上書き

現在 `numerator`/`denominator` は `<span>` で実装されているため発現しないが、  
MAP に `padding` を持つクラスが `<td>` に付与されると即座に顕在化する。

**修正:** `<td>` パディング処理を `convertTag` に統合し、**単一パス**で解決。

```php
private static function convertTag(array $m): string
{
    $tag  = $m[0];
    $isTd = (bool) preg_match('/^<td[\s>\/]/i', $tag);

    if (!preg_match('/\bclass="([^"]*)"/', $tag, $cm)) {
        // class なし td → padding だけ付与
        return $isTd ? self::mergeStyle($tag, self::TD_STYLE) : $tag;
    }

    // ... class 変換 ...

    // td の場合は padding をクラス由来スタイルと同一 mergeStyle 呼び出しで合成
    if ($isTd) {
        $styles[] = self::TD_STYLE;
    }

    return self::mergeStyle($tag, implode(';', $styles));
}
```

これにより:
- `mergeStyle` は1回だけ呼ばれる → `padding` の二重付与なし
- Pass 2 の `preg_replace_callback` 自体が不要になり削除

---

### 🟡 Bug 4 — `img-placeholder` クラスが MAP に未登録

**深刻度:** Medium（潜在バグ）  
**判定:** CONFIRMED（n_sample_reference.css に定義あり、MAP に欠落）

**問題:**  
`n_sample.css` に `.img-placeholder`（InDesign 画像欠損時のプレースホルダー）が定義されているが、  
MAP に存在しなかった。`convertTag` は MAP ヒットがゼロでも `class` 属性を削除するため、  
`body_html` に `class="img-placeholder"` が出現するとクラスが削除されスタイルも付与されず、  
プレースホルダーが不可視になり画像欠損の判別が不能になる。

**修正:** MAP に追加。

```php
'img-placeholder' => 'display:inline-block;margin:4px 2px;padding:2px 6px;border:1px dashed #aaa;color:#888;font-size:.8em;background:#fafafa',
```

---

## 修正しなかった事項と理由

### right-figure の float について

レビューで「CSS は `float:right;width:30%` なのに MAP は `text-align:right` で乖離している」と指摘された。  
これは **意図的な乖離**。  
SunBWork の `School.vue` および NDBSystem の `Show.vue` の `:deep(.ndemo-body .right-figure)` が  
どちらも `text-align:right;margin:.75rem 0` を使用しており、  
Vue コンポーネント側で意図的に float を廃止した仕様。MAP はその Vue 側の値を使用している。

### square / inline-box の CSS 値との差異

同様に意図的。`square` の navy ボーダー（`#1a3a6b`）、`inline-box` の flex レイアウト等は  
Vue `:deep()` 側のビジュアルオーバーライド値を採用したもの。原 CSS とは異なるが正しい。

### q_dai::after（clearfix）、ruby、rt、子孫セレクタ等の欠落

**インライン style では表現不可能**なため修正対象外。  
`::after` 疑似要素、`.q_dai > h3` 等の子孫セレクタ、`ruby { ruby-align:center }` 等の要素セレクタは  
inline style に変換する手段がない。現状維持とし、既知の制限としてコメントに記載した。

---

## NDBSystem で確認すべき事項

NDBSystem の `app/Services/HtmlStyleInliner.php` が SunBWork と同一実装であれば、  
以下の4点を同様に修正する必要がある。

| # | 確認箇所 | 修正が必要な条件 |
|---|---|---|
| 1 | `process()` の `preg_replace_callback` 戻り値 | `null` チェックがなければ修正必須 |
| 2 | `mergeStyle()` の `preg_replace` | replacement に `$combined` を直接渡していれば修正必須 |
| 3 | `process()` の2パス構成 | Pass 2 の `<td>` regex が別途あれば単一パスに統合 |
| 4 | MAP に `img-placeholder` があるか | なければ追加 |

---

## SunBWork での修正後ファイル（最終版）

修正済みファイルは `/home/w229/SunBwork/app/Services/HtmlStyleInliner.php` を参照。  
このファイルをそのまま NDBSystem に上書きしても動作するが、  
NDBSystem 側に追加した MAP エントリや設計上の差異がある場合は個別に確認すること。
