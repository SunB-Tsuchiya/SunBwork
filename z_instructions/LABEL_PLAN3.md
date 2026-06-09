# LABEL_PLAN3.md — 宛先ラベルPDF生成ツール Phase 3 設計書
# アイテム自動検出・学年ラベルオーバーライド・アイテム編集UI

作成日: 2026-06-07

---

## 1. このフェーズの背景と発見

### 発端
Phase 1/2 では出力アイテム（解答・問題用紙・DI答案等）を **PRESETS** 定数として
コードにハードコードしていた。しかし実テストファイルを分析した結果、
**アイテム情報（①〜⑮の丸数字で始まるセル）がExcelファイル内に直接埋め込まれている**
ことが判明した。

### 発見した凡例カラム位置

| テスト種別 | 凡例列 | 例（セル値） |
|---|---|---|
| 学習力育成テスト（国算シート） | col 13 | `①国算社理 解答` / `②国算 問題用紙` / `④国語 ＤI答案用紙` |
| 学習力育成テスト（社理シート） | col 13 | `③社理 問題用紙` / `⑥社会 ＤI答案用紙` |
| 合格力育成テスト（12アイテム） | col 13 | `①国算 解答 総合` 〜 `⑫理科 DI答案用紙 難関` |
| 日能研全国テスト | col 15 | `①国算 解答` 〜 `④算数 DI答案用紙` |
| 公開模試（各シート） | col 9, row 0 | `③４科 問題用紙とDI答案用紙` |

### PRESETシステムの問題点（修正前）
- PRESET A（学習力育成テスト）は8アイテム定義されていたが、
  実際の学習力育成テストは7アイテム（国算社理解答が「合算」で1アイテム）
- 合格力育成テスト（12アイテム）はどのPRESETにも対応していなかった
- マイファーストテスト（3年生向け特別テスト名）は学年ラベルの上書き手段がなかった

---

## 2. 実装した変更（Phase 3）

### 2-1. アイテム自動検出ロジック

**新規関数 3本（LabelGenerator.vue script section）**

```
inferMaxBox(label: string) → number
  凡例テキストから箱分割上限を判定する
  "DI答案|答案用紙" → 250
  "解答|解説"       → 100
  "問題"            → 50
  その他            → 100

parseItemLabel(fullText: string) → { subject: string, itemLabel: string }
  "①国算社理 解答" → { subject: "国算社理", itemLabel: "解答" }
  "④国語 ＤI答案用紙" → { subject: "国語", itemLabel: "DI答案用紙" }
  ロジック:
    1. 丸数字と全角スペースを正規化・除去
    2. 空白区切りでsplit
    3. "解答|解説|問題|答案|DI" に最初に一致する単語の位置で subject / itemLabel を分割

extractLegendItems(rows: array[][]) → LegendItem[]
  行0〜19の col 7〜17 を走査
  ①〜⑮で始まる長さ2以上のセルを収集
  → parseItemLabel + inferMaxBox を適用して配列を返す
```

**parseSheet の変更**
- return に `legendItems: extractLegendItems(rows)` を追加

**parseWorkbook の変更**
- `const legendByNum = {}` を宣言
- 各シートの `legendItems` をループし、同番（①等）は最初のシート優先でマージ
- `sheetKey` は当該シートの `detectSheetKey()` 結果を使用
- ループ後に `detectedItems.value = Object.values(legendByNum).sort(...)` でセット
- `gradeLabelOverrides.value = {}` でリセット（ファイル再読込時）

**activeItems computed（新規）**
```js
const activeItems = computed(() =>
    detectedItems.value.length > 0
        ? detectedItems.value
        : (PRESETS[selectedPreset.value]?.items ?? [])
);
```
`buildLabels` / `generatePDFs` / `labelCountPerItem` すべてが `activeItems` を参照する。

---

### 2-2. テスト名抽出バグ修正

**修正前の問題:**
```
"2026年3月21・22日 学習力育成テスト"
→ mDate2 の regex /\d{4}年(\d+)月(\d+)日/ がマッチしない
  （"21" の直後が "日" ではなく "・" だから）
→ テスト名全体がrawHeaderになってしまい、日付が残る
```

**修正後:**
```js
const mDateRange = rawHeader.match(/\d{4}年\d+月\d+[・〜～\-]\d+日/);
// 上が先にチェックされる（mDate2 より前）
// マッチした場合: 日付範囲全体を除去してテスト名を抽出
```

shortName略称自動生成のregexも同様に拡張:
```js
/\d{4}年\d+月\d+[・〜～\-]\d+日?|\d{4}年\d+月\d+日|\d+月\d+[・〜～\-]\d+日?|\d+月\d+日?/g
```

---

### 2-3. 学年ラベルオーバーライド

**state:**
```js
const gradeLabelOverrides = ref({});
// { '3年': 'マイファースト', '4年': '', ... }
// 空文字はデフォルト（学年名そのまま）を使用
```

**使用箇所:**
- `buildLabels()` 内: `const gradeDisplay = gradeLabelOverrides.value[grade] || grade;`
  → ラベルオブジェクトの `grade` フィールドに `gradeDisplay` を格納
- `generatePDFs()` 内: PDFファイル名に `gradeDisplay` を使用
- `drawLabel()` 内: `label.grade` に既に `gradeDisplay` が入っている（auto-shrinkで対応）

**UI（テンプレート）:**
ステータスブロックの下に学年ラベルオーバーライドパネルを配置。
`gradeOptions` がある場合のみ表示。
`v-model="gradeLabelOverrides[g]"` でバインド。

**マイファーストケースの使い方:**
1. `20260321_22MFT学習力育成テスト新4-6年.xls` を読み込む
2. 学年ラベル変更パネルで「3年 → マイファースト」を入力
3. 出力ファイル名: `0321マイファースト①.pdf` 等

---

### 2-4. 学年テキスト自動縮小

**修正前:** `F(60, '900'); ctx.fillText(label.grade, ...)` — 固定サイズ60

**修正後:**
```js
let gSize = 60;
F(gSize, '900');
const maxGradeW = s(110);  // 枠幅（121px - マージン）
while (gSize > 14 && label.grade && ctx.measureText(label.grade).width > maxGradeW) {
    gSize -= 2;
    F(gSize, '900');
}
ctx.fillText(label.grade || '', s(50), s(311));
```
「マイファースト」（5文字）は概ね 28〜32px になると推定。

---

### 2-5. アイテム編集UI

**state:**
```js
const showItemEditor = ref(false);  // 編集パネル開閉
```

**UI構成:**
- 「出力アイテム」ラベルの右に「▼ 編集する」ボタン
- 開いた状態: `detectedItems` の各行を v-for で表示し、全フィールド編集可能
  - num（丸数字）/ subject（科目）/ itemLabel（内容）/ sheetKey（セレクト）/ maxBox（数値）/ 削除ボタン
- 「+ アイテムを追加」ボタン（`addItemRow()`）
- 閉じた状態: アイテム別教室数プレビューを表示（従来のpresetItemsプレビューと同等）

**注意:** 編集対象は `detectedItems` ref のみ。
PRESET アイテムを編集したい場合は、一度 `detectedItems` に手動でコピーすることが必要。
（PRESETフォールバック時は編集不可 — 必要なら将来対応）

---

### 2-6. ステータスブロックの変更

| 条件 | 表示内容 |
|---|---|
| `detectedItems.length > 0` | 「アイテム: N件（Excel凡例から自動検出）」|
| `detectedItems.length === 0` | 「プリセット: A: 学習力育成テスト（4〜8科目型）」（従来）|

プリセットセレクトは `detectedItems.length === 0` の場合のみ表示。

---

## 3. sheetKey の割り当てロジック

アイテムの `sheetKey` は「その凡例が発見されたシート」の `detectSheetKey()` 結果。

```
学習力育成テスト/国算シート → 'kokusan'
学習力育成テスト/社理シート → 'shashiri'
日能研全国テスト            → 'kokusan_di'
公開模試/四科テストシート   → 'yonka'
公開模試/二科テストシート   → 'nika'
公開模試/国算解答シート     → 'kokusan_kaitou'
公開模試/社理解答シート     → 'shashiri_kaitou'
```

**同番（例: ③）が複数シートに出現した場合:** 最初に発見したシートの sheetKey を採用。
これにより、同番が2シートにまたがる場合でも重複せず1つにまとまる。

**DI答案用紙の sheetKey:**
学習力育成テストの DI答案用紙は国算/社理の本体シートに凡例がある。
例: 「④国語 ＤI答案用紙」が国算シートに記載 → sheetKey = 'kokusan'。
`resolveSheet` の SHEET_FALLBACKS で `kokusan_di → ['kokusan', 'main']` となっているため、
実際には kokusan_di シートが存在する場合はそちらを優先して部数を取得できる。

---

## 4. PRESETフォールバックの位置づけ

PRESET は以下のケースで引き続き使われる:

1. Excelに凡例列がない古いファイル形式
2. 凡例スキャン範囲（col 7〜17, row 0〜19）に凡例が見つからない場合

PRESETキーの自動推定ロジック（`computePresetKey`）は変更なし。
`activeItems` が PRESET を参照するのはフォールバック時のみ。

---

## 5. 変更ファイル一覧

| ファイル | 変更内容 |
|---|---|
| `resources/js/Components/Scripts/LabelGenerator.vue` | 以下の全変更 |
| `z_instructions/LABEL_PLAN3.md` | 本ファイル（新規） |
| `z_instructions/LABEL_MANAGER3.md` | 進捗管理（新規） |
| `z_instructions/LABEL3_PROMPT.md` | セッション引継ぎ用（新規） |

### LabelGenerator.vue 変更箇所（行番号は変更後の概算）

| 変更箇所 | 内容 |
|---|---|
| L53 | `const CIRCLED_NUMS = '①②...⑮'` 追加 |
| L163〜200 | `inferMaxBox` / `parseItemLabel` / `extractLegendItems` 追加 |
| L285〜287 | `detectedItems` / `gradeLabelOverrides` / `showItemEditor` state追加 |
| L582〜597 | `parseSheet` の日付抽出: mDateRange パターン追加 |
| L766 | `parseSheet` return に `legendItems` 追加 |
| L556〜 | `parseWorkbook` に legendByNum 収集・detectedItems セット追加 |
| L628〜 | shortName自動生成のregex拡張 |
| L841〜845 | `buildLabels` を `activeItems` + `gradeDisplay` に変更 |
| L868 | `label.grade: gradeDisplay` に変更 |
| L1052〜 | `generatePDFs` を `activeItems` + `gradeDisplay` に変更 |
| L1002〜1010 | `drawLabel` grade auto-shrink 追加 |
| L1166〜1170 | `activeItems` computed 追加 |
| L1173〜1180 | `canGenerate` に `activeItems.value.length > 0` 追加 |
| L1183〜1196 | `labelCountPerItem` を `activeItems` で算出に変更 |
| L1231〜1235 | `gradeDisplayLabel` / `addItemRow` / `removeItemRow` 追加 |
| Template | ステータスブロック・学年オーバーライドUI・アイテム編集UI・プリセット表示条件変更 |

---

## 7. 実施日（testDateVal）の設計（Phase 3.1 追加）

### 背景
- 単一日テスト（学習力育成テスト等）はシート名に日付がないため `detectedDates = []`
- `buildLabels` で `dateCodeToDisplay('__common') = ''` になり、ラベルの実施日欄が空白
- かつ実施日の手入力フィールドが存在しなかった

### 新規 state
```js
const testDateVal = ref('');  // 例: "2026年3月21日" / "2026年3月21・22日"
```

### データフロー
```
Excelヘッダー（row 0, col 0 or 1）
  "2026年3月21日 学習力育成テスト"
       ↓ parseSheet: mDate2[0] = "2026年3月21日" → testInfo.date
       ↓ parseWorkbook: detectedDate → testDateVal.value = "2026年3月21日"
       ↓ buildLabels: dateCode==='__common' → label.date = testDateVal.value
       ↓ generatePDFs: datePart = dateValToMMDD("2026年3月21日") = "0321"
  ファイル名: 0321学習力育成テ5年①.pdf
```

### 複数日付テストとの共存
| 状況 | detectedDates | label.date | ファイル名プレフィックス |
|---|---|---|---|
| 単一日（学習力育成等） | `[]` | `testDateVal.value` | `dateValToMMDD(testDateVal)` |
| 複数日（前中期・後期） | `['0330','0404']` | `dateCodeToDisplay(dateCode)` | `'0330'` / `'0404'` |

### UI
- 複数日付テスト（`detectedDates.length > 0`）: 実施日入力欄を**非表示**。「○/○、○/○（シート名から自動検出）」と表示のみ。
- 単一日テスト（`detectedDates.length === 0`）: 実施日入力欄を**表示**。Excelヘッダーから自動取得・手入力可。

### ファイル名略称のプレビュー例表示
```js
// 複数日テスト: detectedDates[0] を使用
// 単一日テスト: dateValToMMDD(testDateVal) を使用
```

### 新規ヘルパー
```js
function dateValToMMDD(dateStr) {
    // "2026年3月21日" → "0321"
    // "2026年3月21・22日" → "0321"（最初の日）
    const m = dateStr.match(/(\d+)月(\d+)/);
    return m ? m[1].padStart(2,'0') + m[2].padStart(2,'0') : '';
}
```

---

## 8. 学年別設定（テスト名・実施日・印字ラベル名）の設計（Phase 3.2）

### 背景と発見（2026-06-07 実ファイル調査）

1つのExcelシートに、**学年によってテスト名・実施日が異なる複数テストが同居**する場合がある。

#### 実例: `s1_0321-22MFT学習力新4-6年_再作成.xls`
```
ヘッダー: "2026年3月21・22日　学習力育成テスト・マイファーストテスト"
シート名: 「国算問題原本」「社理問題原本」（日付なし）

列構造:
  row3: コード | 教室名 | ３年生 | ４年生 | ５年生 | ６年生
  ↓
  3年列 → テスト名: マイファーストテスト  / 実施日: 2026年3月21日
  4年列 → テスト名: 学習力育成テスト     / 実施日: 2026年3月21日
  5年列 → テスト名: 学習力育成テスト     / 実施日: 2026年3月21日
  6年列 → テスト名: 学習力育成テスト     / 実施日: 2026年3月22日
```

元のFileMakerでは各シートを個別に開いて名前・日付を手入力していた。
本ツールでは全シート一括処理のため、**学年ごとの設定オーバーライドUI**で対応する。

#### 複数日付パターンの種類（調査済み）
| パターン | 具体例 | シート名に日付あり | 現状対応 |
|---|---|---|---|
| A: シート名に日付あり | 春期テスト `0330_0404` | `「春期3月30日DI」「春期4月4DI」` | ✅ `detectedDates` で自動処理 |
| B: 全学年が同一シート・学年別に実施日が異なる | 学習力育成テスト `0321_22` | シート名に日付なし | ❌ 学年別設定UIで手動入力 |

---

### 新規 state（追加）

```js
const gradeTestNameOverrides = ref({});  // { '3年': 'マイファーストテスト' }
const gradeDateOverrides      = ref({});  // { '6年': '2026年3月22日' }
// 既存: gradeLabelOverrides = ref({})  // { '3年': 'マイファースト' }
```

3つをまとめて「学年別設定」として扱う。リセットタイミング: `processExcelFile` + `parseWorkbook`。

---

### UI設計: 「学年別設定」パネル（既存の「学年ラベル変更」パネルを置き換え）

```
学年別設定  （空欄 = 上部のテスト名/実施日/学年名をそのまま使用）
       印字テスト名                   実施日                 印字ラベル名
3年 → [マイファーストテスト  ]  [2026年3月21日    ]  [マイファースト]
4年 → [                    ]  [                 ]  [             ]
5年 → [                    ]  [                 ]  [             ]
6年 → [                    ]  [2026年3月22日    ]  [             ]
```

- 3列グリッド。空欄の学年はデフォルト（上部グローバル設定）を使用
- 実施日列は `detectedDates.length > 0`（パターンA）の場合は非表示（シート名日付が優先）
- テスト名列・印字ラベル名列は常に表示

---

### buildLabels の変更

```js
// 変更前（Phase 3.1時点）
const gradeDisplay  = gradeLabelOverrides.value[grade] || grade;
label.grade    = gradeDisplay;
label.testName = testNameVal.value;
label.date     = dateCode === '__common' ? testDateVal.value : dateCodeToDisplay(dateCode);

// 変更後（Phase 3.2）
const gradeDisplay  = gradeLabelOverrides.value[grade]    || grade;
const gradeTestName = gradeTestNameOverrides.value[grade] || testNameVal.value;
const gradeDate     = detectedDates.value.length > 0
    ? dateCodeToDisplay(dateCode)   // パターンA: シート日付優先
    : (gradeDateOverrides.value[grade] || testDateVal.value);  // パターンB: 学年別 or グローバル

label.grade    = gradeDisplay;
label.testName = gradeTestName;
label.date     = gradeDate;
```

---

### generatePDFs のファイル名変更

```js
// 変更前: datePart は dateCode に依存（全学年共通）
const datePart = dateCode === '__common' ? dateValToMMDD(testDateVal.value) : dateCode;

// 変更後: パターンBのとき学年別日付からMMDDを抽出
const gradeDate = gradeDateOverrides.value[grade] || testDateVal.value;
const datePart  = dateCode === '__common'
    ? dateValToMMDD(gradeDate)   // 学年ごとに異なる可能性あり
    : dateCode;                  // パターンA: シートMMDD固定

// ファイル名例:
// 3年: 0321マイファースト①.pdf（gradeDate='2026年3月21日', gradeDisplay='マイファースト'）
// 6年: 0322学習力6年①.pdf（gradeDate='2026年3月22日', gradeDisplay='6年'）
```

---

### 視覚フィードバック（未入力フィールドの赤枠表示）

Excel読み込み後に自動取得できなかった必須フィールドを赤枠で強調。

| フィールド | 赤表示の条件 |
|---|---|
| テスト名 | `excelName && !testNameVal` |
| 実施日（単一日テスト） | `excelName && !testDateVal && detectedDates.length === 0` |
| ファイル名略称 | `excelName && !shortNameVal` |

実装: `:class` バインディングで `border-red-400 bg-red-50` を動的に切り替え。

---

### 出力前の確認ダイアログ

`generatePDFs()` の冒頭で空欄チェック → `confirm()` を出す。

```js
// 実施日が空欄のとき（テスト名・略称はcanGenerateで既にdisabled）
if (!testDateVal.value && !Object.values(gradeDateOverrides.value).some(v => v)) {
    // detectedDates もない = 実施日が完全未設定
    if (detectedDates.value.length === 0) {
        const ok = confirm('実施日が未入力です。ラベルの実施日欄が空白になり、ファイル名のMMDDも省略されます。このまま出力しますか？');
        if (!ok) return;
    }
}
```

---

### 変更ファイル一覧（Phase 3.2 追加分）

| 変更箇所 | 内容 |
|---|---|
| state | `gradeTestNameOverrides` / `gradeDateOverrides` 追加 |
| `processExcelFile` | 上記2つをリセット |
| `parseWorkbook` | 上記2つをリセット |
| `buildLabels` | `gradeTestName` / `gradeDate` を学年別設定から取得 |
| `generatePDFs` | `datePart` を学年別日付から生成。確認ダイアログ追加 |
| Template | 「学年別設定」パネルに統合（testName列・date列・label列の3列グリッド） |
| Template | 各入力フィールドの赤枠バインディング追加 |

---

## 9. 未解決・将来課題

| 課題 | 備考 |
|---|---|
| PRESETフォールバック時にアイテムを編集する手段がない | detectedItems が空のときアイテム編集UI非表示 |
| 同一丸番号が3シート以上に出現したときの挙動（全国テスト等） | 最初のシートのみ採用（設計上の意図） |
| 凡例抽出失敗時のユーザー向けエラー表示 | 現状: PRESETに自動フォールバックするだけで通知なし |
| 公開模試の sheetKey 対応（col 9 / row 0 の単セル凡例） | 現状の extractLegendItems は col 7〜17 を走査するため取得できている可能性あり。要実テスト確認 |
| 合格力育成テストの 総合/難関 サフィックス処理 | `①国算 解答 総合` の "総合" が itemLabel に入る。 `resolveSheet` のシート分類が合っているか要確認 |
| ファイル名略称（shortName）の学年別設定 | 3年=マイファースト略称、4-6年=学習力略称のような場合に対応できない。現状は単一略称。将来課題。 |
