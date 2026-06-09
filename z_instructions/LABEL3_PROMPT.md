# LABEL3_PROMPT.md — 宛先ラベルPDF生成ツール Phase 3 引継ぎプロンプト

新セッション開始時にこのファイルを読み込んでから作業に入ること。

---

## プロジェクト概要（このツールの目的）

清水製版の発送業務で使う宛先ラベルPDFを、Excelの発送部数一覧（s1_*.xls）から
ブラウザ内で自動生成するツール。日能研テストの教室別・アイテム別・学年別にPDFを出力する。

- ページ: `resources/js/Components/Scripts/` 内の `LabelGenerator.vue`（単一ファイル、約1800行）
- ルート: `scripts.show` → `Show.vue` の componentMap に `'LabelGenerator'` で登録済み
- DB: Phase 2 で label_school_masters / label_test_names / label_subjects / label_item_types を作成済み

---

## Phase 1〜3 実装済み機能サマリー

### Phase 1（基本機能）
- Excelファイル（s1_*.xls）をブラウザで読み込み
- 教室コード・部数・学年を抽出
- PRESET（A〜H）のアイテム定義に基づいてラベルデータを生成
- A5横（729×516pt）のPDFを生成（jsPDF + Canvas）
- ファイル名: `MMDD略称学年番号.pdf`

### Phase 2（DBマスタ化）
- 教室マスタ・テスト名・科目・内容をDBで管理
- マスタ管理タブ（CRUD UI）を追加
- onMounted で axios取得 → routeMap をDB優先で構築
- テスト名入力に datalist でサジェスト

### Phase 3（アイテム自動検出）
- **Excel凡例からアイテムを自動検出**
  - `extractLegendItems()`: col 7〜17, row 0〜19 を走査
  - ①〜⑮で始まるセルを検出 → `parseItemLabel()` で subject/itemLabel に分解
  - `inferMaxBox()` で maxBox を自動判定（DI答案=250/解答=100/問題=50）
  - `parseWorkbook()` が全シートから収集し `detectedItems` にセット
- **activeItems computed**: detectedItems があれば優先、なければ PRESET フォールバック
- **学年ラベルオーバーライドUI**: 例) 3年 → マイファースト
- **アイテム編集UI**: 検出結果を手動で追加・削除・変更可能
- **日付範囲テスト名バグ修正**: `月DD・DD日` 形式に対応
- **学年テキスト auto-shrink**: 長い学年名が学年枠をはみ出さないよう自動縮小

---

## 重要なデータ構造

### PRESET（フォールバック用）
```js
PRESETS[key].items = [
  { num: '①', subject: '国算', itemLabel: '解説', maxBox: 100, sheetKey: 'kokusan' },
  ...
]
```
キー: A=学習力育成 / B=全国テスト / C=公開模試 / D=夏期特別 / E=公立中高 / G/H=夏期夏

### detectedItems（Excel凡例から自動生成）
PRESET と同じ形式。`parseWorkbook` 実行後にセットされる。

### sheetsData（日付バケット）
```js
{ '__common': { 'kokusan': {schools}, 'shashiri': {schools}, ... },
  '0330': { 'kokusan': {schools}, ... },
  '0404': { ... } }
```

### school オブジェクト
```js
{ code: 'DL', name: '赤羽', grades: { '3年': 120, '4年': 85 }, rowIdx: 7, colIdx: 0 }
```

### label オブジェクト（drawLabel に渡す）
```js
{ itemKey, itemNum, subject, itemLabel, routeCode, schoolCode, schoolName,
  boxNum, boxTotal, quantity, serial, testName, date, grade,
  _internalCode, _routeOrder, _stopOrder, _areaOrder, _rowIdx, _colIdx }
```

---

## sheetKey の対応表

| sheetKey | 対応シート名パターン |
|---|---|
| `kokusan` | 国算を含む |
| `shashiri` | 社理・社会・理科を含む |
| `kokusan_di` | DI + 国算系 |
| `shashiri_di` | DI + 社理系 |
| `kokusan_kaitou` | 国算解答・国算解説 |
| `shashiri_kaitou` | 社理解答・社理解説 |
| `yonka` | 四科テスト・4科テスト |
| `nika` | 二科テスト・2科テスト |
| `summer_main_di` | DI単独（夏期） |
| `summer_early` | 前中期 |
| `summer_late` | 後期 |
| `main` | その他 |
| `exclude` | テストサービス・TS（除外） |
| `ichishiki` | 一式（Phase2未実装） |

---

## 凡例検出位置（テスト種別別）

| テスト種別 | 凡例列 | 備考 |
|---|---|---|
| 学習力育成テスト | col 13（0始まり） | 国算/社理シートそれぞれに記載 |
| 合格力育成テスト | col 13 | 総合・難関サフィックスあり |
| 日能研全国テスト | col 15 | |
| 公開模試 | col 9, row 0 | 1シート1アイテムの可能性あり |

---

## ラベルレイアウト（A5横 729×516pt）

```
[ルートコード A1等]   [メール便]            [箱番号 1/3]
───────────────────────────────────────────────────────
[教室コード]  [教室名 校]                           [行]
[実施日]                                       [実施]
[学年枠]      [テスト名]         [科目]
 ┌──────┐    学習力育成テスト   国算
 │  5年  │
 └──────┘   [アイテム名: 問題用紙]    [120部]
                                      通番 1
                               (株)サンエー印刷
```

座標原点は左上。スケール SCALE=1（pt単位）。

---

## テスト課題（Phase 3 確認用）

以下を実際のExcelファイルで確認してください。
過去の納品ファイル（PDFまたは印刷物）と比較することで正確性を確認します。

---

### T-01: 学習力育成テスト — アイテム自動検出確認

**使用ファイル:** 2025年/2026年の学習力育成テスト Excel（s1_*.xls）

**手順:**
1. ツール → ラベル生成タブ → Excel を選択
2. ステータスブロックを確認

**期待値:**
- 「アイテム: 7件（Excel凡例から自動検出）」と表示される
- アイテム一覧に以下が並ぶ:
  ```
  ① 国算社理  解答       100部/箱  sheetKey: kokusan
  ② 国算      問題用紙    50部/箱   sheetKey: kokusan
  ③ 社理      問題用紙    50部/箱   sheetKey: shashiri
  ④ 国語      DI答案用紙 250部/箱  sheetKey: kokusan
  ⑤ 算数      DI答案用紙 250部/箱  sheetKey: kokusan
  ⑥ 社会      DI答案用紙 250部/箱  sheetKey: shashiri
  ⑦ 理科      DI答案用紙 250部/箱  sheetKey: shashiri
  ```
- ※ 番号・科目・内容・maxBoxが正確か確認（特に解答と問題用紙のmaxBoxを見る）

**よくある問題:**
- アイテム数が8件の場合 → 国算解答と社理解答が別々に検出されている
  → アイテム編集UIで結合（①を「国算社理 解答」に変更して②を削除）

---

### T-02: 日付範囲テスト名 — 「月DD・DD日」形式

**使用ファイル:** `20260321_22MFT学習力育成テスト新4-6年.xls`（3月21・22日実施）

**手順:**
1. Excelを読み込む
2. ステータスブロックの「テスト名」を確認
3. 「ファイル名略称」フィールドを確認

**期待値:**
- テスト名: `学習力育成テスト`（日付なし）
- 略称: `学習力育成テ`（8文字制限）
- NG例: `3月21・22日学習力育成テスト`（日付が残っている）

---

### T-03: マイファーストテスト — 学年ラベルオーバーライド

**使用ファイル:** 3年生が含まれる学習力育成テスト Excel（3月〜4月の新学年切り替え時期のもの）

**手順:**
1. Excelを読み込む（3年・4年・5年・6年が検出されること）
2. 「学年ラベル変更」パネルの「3年 →」入力欄に「マイファースト」と入力
3. 「プレビュー学年」で「3年」を選択して教室数プレビューを確認
4. 「宛紙出力」を実行

**期待値:**
- 出力ファイルに `マイファースト①.pdf` 等が含まれる（`3年①.pdf` ではない）
- そのPDFを開くと学年枠に「マイファースト」と印字されている
- 「マイファースト」の文字サイズが「3年」「4年」等より小さい（自動縮小）

---

### T-04: 合格力育成テスト — 12アイテム検出

**使用ファイル:** 合格力育成テスト Excel

**手順:**
1. Excelを読み込む
2. アイテム数を確認

**期待値:**
- 「アイテム: 12件（Excel凡例から自動検出）」
- 総合/難関のサフィックスが各アイテムの itemLabel に含まれる
  例: `解答 総合` / `解答 難関` / `問題用紙 総合` ...

---

### T-05: 日能研全国テスト — col 15 の凡例

**使用ファイル:** 日能研全国テスト Excel

**手順:**
1. Excelを読み込む
2. アイテム数と内容を確認

**期待値:**
- 「アイテム: 4件（Excel凡例から自動検出）」
- ①国算 解答 / ②国算 問題用紙 / ③国語 DI答案用紙 / ④算数 DI答案用紙

---

### T-06: 公開模試 — 各シートから4アイテム

**使用ファイル:** 全国公開模試 Excel

**手順:**
1. Excelを読み込む
2. アイテム数と sheetKey を確認

**期待値:**
- 「アイテム: 4件（Excel凡例から自動検出）」
- ①国算解答（sheetKey: kokusan_kaitou）/ ②社理解答（shashiri_kaitou）
  ③4科 問題用紙とDI答案（yonka）/ ④2科 問題用紙とDI答案（nika）
- ※ 公開模試はシートごとに1アイテムの凡例がcol 9にある。
  アイテム内容が正しいか確認

---

### T-07: アイテム手動編集

**手順:**
1. いずれかのExcelを読み込む
2. 「▼ 編集する」をクリックしてアイテム編集UIを開く
3. 任意のアイテムのitemLabelを変更する（例: 「解答」→「解答・解説」）
4. 「▲ 閉じる」でプレビューに戻る
5. プレビューの教室数が更新されているか確認
6. 「+ アイテムを追加」で行を追加 → 削除ボタンで削除

**期待値:**
- 変更内容がリアルタイムでプレビューに反映される
- 追加・削除が正しく動作する

---

### T-08: 過去納品ファイルとの比較（最重要）

**手順:**
1. 過去に実際に納品した実績のあるPDFを用意する
2. 同じExcelファイルで本ツールを使って出力する
3. 以下の項目を1件1件比較する:

| 比較項目 | 確認方法 |
|---|---|
| 教室数（ファイル枚数） | PDFの総ページ数を比較 |
| 教室名 | 先頭10件程度を目視比較 |
| 部数 | 数件をサンプリングして確認 |
| 教室の並び順 | ルート順（A1→I2）が正しいか |
| 箱分割 | 例: 120部→解答=2箱（100+20） |
| ファイル名の形式 | `MMDD略称学年番号.pdf` |

**注意:** 教室名に「校」が付く/付かない判定が過去と異なる場合、
`NO_SUFFIX_KEYWORDS` またはDBの `display_name` を確認すること。

---

## 既知の未実装事項

- 一式シート（Phase 3 以降対応予定）
- パターンB の学年別実施日（手動修正が現状の対処）
- AS コード（渋谷/表参道）の最終確定 → 担当者確認待ち
- PRESETフォールバック時のアイテム編集UI（detectedItemsが空のときは編集不可）

---

## ファイルパス一覧

| ファイル | 用途 |
|---|---|
| `resources/js/Components/Scripts/LabelGenerator.vue` | メインコンポーネント（約1800行） |
| `app/Http/Controllers/LabelMasterController.php` | マスタCRUD API |
| `app/Models/LabelSchoolMaster.php` | 教室マスタModel |
| `app/Models/LabelTestName.php` | テスト名Model |
| `app/Models/LabelSubject.php` | 科目Model |
| `app/Models/LabelItemType.php` | 内容Model |
| `database/seeders/LabelMasterSeeder.php` | 初期データ |
| `Shimizu_Seihan/school_master_draft.csv` | 教室マスタCSV（シードソース） |
| `z_instructions/LABEL_PLAN1.md` | Phase 1 設計書 |
| `z_instructions/LABEL_PLAN2.md` | Phase 2 設計書（DBマスタ化） |
| `z_instructions/LABEL_PLAN3.md` | Phase 3 設計書（アイテム自動検出等）← 重要 |
