# LABEL_ALGORITHM.md — 宛先ラベルPDF生成ツール アルゴリズム・ルール集

更新日: 2026-06-12  
担当: Claude Code  
対象: LabelGenerator.vue / LabelGeneratorV2.vue

このファイルはLABELプロジェクトで判明したルール・アルゴリズムを一元管理する生きたドキュメントです。  
新たな発見や修正があれば必ずここに追記すること。

---

## 1. Excelファイル（s1_*.xls）の構造

### 1-1. セルレイアウト（全シート共通・0インデックス）

```
行0 (row 0): ヘッダー（テスト名・実施日・アイテム凡例）
行1〜4:     サブヘッダー（アイテム凡例の続き）
行5〜6:     空または罫線
行7〜末:    教室データ
```

**教室データブロック（行7〜）:**

```
左ブロック（0-indexed）:
  col 0 = 教室コード（例: DL, AS）
  col 1 = 教室名
  col 2 = 3年 の部数
  col 3 = 4年 の部数
  col 4 = 5年 の部数
  col 5 = 6年 の部数

右ブロック（0-indexed）:
  col 6 = 教室コード
  col 7 = 教室名
  col 8 = 3年 の部数
  col 9 = 4年 の部数
  col 10 = 5年 の部数
  col 11 = 6年 の部数
```

**学年列の自動検出:** ヘッダー行（row 0〜7）に「3年」「4年」「5年」「6年」（または「3年生」等）が含まれる列番号を動的検出する。検出できない場合は上記デフォルト（col 2-5, 8-11）にフォールバック。

**教室コード形式:** 半角2文字英大文字（AA, DL, AS ...）または全角→Unicode NFKC正規化で半角化。

**スキップ条件（教室行）:**
- 「小計」「合計」「本部計」「総合計」を含む名前の行
- コードが`[A-Z]{2}`に一致しない行
- 対象学年の部数が0または空白の学校はその学年向けラベルをスキップ

---

### 1-2. アイテム凡例の位置

アイテム番号（①②③...）は通常ヘッダー行（row 0〜19）の**右側（col 6〜18付近）**に記載される。

例（学習力育成テスト, 0-indexed）:
```
row 0, col 13: "①国算社理 解答"
row 1, col 13: "②国算 問題用紙"
row 2, col 13: "④国語 ＤI答案用紙"
```

**抽出ルール:** rows 0〜19, cols 6〜18 を走査し、`[①-⑮]` で始まる2文字以上のセル値を収集。`parseItemLabel()` で科目（subject）と内容（itemLabel）を分離。

**maxBox 判定（`inferMaxBox`）:**
| 内容 | maxBox |
|---|---|
| DI答案・答案用紙 | 250 |
| 解答・解説 | 100 |
| 問題用紙 | 50 |
| その他 | 100 |

---

## 2. シートパターン分類

### 2-1. パターンA: 日付別シート方式（春期テスト型）

- シート名に `M月D日` が含まれる → 日付を自動抽出（extractDateCode）
- 実施日ごとにシートが分かれる
- sheetsData 構造: `{ '__common': {}, '0330': { sheetKey: schools, ... }, '0404': { ... } }`
- V2での対応: detectedDates に抽出された日付が入る → ユーザー入力不要（試験項目欄は自動対応）

### 2-2. パターンB: 学年別日付方式（学習力育成テスト型）

- シート名に日付なし（ヘッダー行のみに記載）
- 全学年が1シートにまとまり、学年ごとに実施日が異なる
- **ユーザーが試験項目欄で学年ごとに実施日を手動入力する必要あり**
- V2での判定: detectedDates が空 → パターンB として警告表示

### 2-3. パターンC: 単一日付・公開模試型

- シート名に日付なし、ヘッダーに一つの日付
- パターンBと同様の扱い

---

## 3. 日付抽出ルール（extractDateCode）

```js
function extractDateCode(sheetName) {
    const n = sheetName.normalize('NFKC');
    // 「日」抜けtypoに対応: 日? で省略可能にする
    // (?=[^月]|$) で次の月 を誤マッチしないようにする
    const m = n.match(/(\d+)月(\d+)日?(?=[^月]|$)/);
    if (!m) return null;
    return m[1].padStart(2, '0') + m[2].padStart(2, '0');
}
```

**対応例:**
| シート名 | 正規表現マッチ | 結果 |
|---|---|---|
| `春期3月30日DI` | `3月30日` → groups:'3','30' | `0330` |
| `春期4月4DI` | `4月4D` → groups:'4','4' | `0404` ← typo対応 |
| `国算問題原本` | マッチなし | null（__commonバケット） |
| `答案用紙3月30日テストサービス` | `3月30日` | `0330` |

**ユーザー入力との紐付け:**
V2では detectedDates に格納された値（例: `['0330', '0404']`）をユーザー入力の試験日（MMDD変換値）と照合する。typoがあっても正規表現で抽出できれば自動紐付け可能。

---

## 4. シートキー分類（detectSheetKey）

優先順位順に評価:

```
1. テストサービス | TS → 'exclude'  ← 除外リスト
2. 一式 → 'ichishiki'               ← 一式PDF対象リスト
3. DI 含む:
   - 国算|国語|算数 → 'kokusan_di'
   - 社理|社会|理科|社答案|理答案 → 'shashiri_di'
   - 前.*中期|前中期 → 'kokusan_di'
   - 後期 → 'shashiri_di'
   - 答案 → 'shashiri_di'
   - その他 → 'summer_main_di'
4. 国算 → 'kokusan'
5. 社理|社会|理科 → 'shashiri'
6. 四科テスト|4科テスト → 'yonka'
7. 二科テスト|2科テスト → 'nika'
8. 国算解答|国算解説 → 'kokusan_kaitou'
9. 社理解答|社理解説 → 'shashiri_kaitou'
10. 公立中高|適性検査 → 'kouritsu'
11. 前.*中期|前中期 → 'summer_early'
12. 後期 → 'summer_late'
13. その他 → 'main'
```

**sheetsData バケット衝突:** 同一バケット・同一キーは最初のシートを保持（社会DI・理科DIは同値のため問題なし）。

---

## 5. 学年別アイテム検出（最重要ルール）

### 5-1. 基本ルール

**「どのアイテムをどの学年向けに生成するか」は、シートごとの分析で決まる。**

アルゴリズム:
```
FOR each data sheet (exclude, ichishiki, 原本 を除く):
    sheet_symbols = header行(row 0-19)から ①②... を収集（生文字列で検索すること）
    grade_with_data = データ行(row 7+)で qty > 0 の学年の集合
    
    IF sheet_symbols ≠ ∅ AND grade_with_data ≠ ∅:
        FOR each grade in grade_with_data:
            gradeItemMap[grade] += sheet_symbols
```

### 5-2. 春期テスト（s1_0330・0404春期3-5年.xls）の例

| シート名 | アイテム記号 | 3年データ | 4年データ | 5年データ |
|---|---|---|---|---|
| 春期3月30日DI | ①② | あり | あり | あり |
| 国算答案用紙3月30日DI | ③④ | あり | あり | あり |
| 社答案用紙3月30日DI関西なし | ⑤ | **なし** | あり | あり |
| 理答案用紙3月30日DI | ⑥ | **なし** | あり | あり |

**結果:**
- 3年: ①②③④のみ（⑤⑥は部数が無記入→スキップ）
- 4年: ①②③④⑤⑥
- 5年: ①②③④⑤⑥

→ 出力PDFも学年ごとに異なるアイテム数になる。

### 5-3. gradeItemMap 構造

```js
const gradeItemMap = {
    '3年': ['①','②','③','④'],
    '4年': ['①','②','③','④','⑤','⑥'],
    '5年': ['①','②','③','④','⑤','⑥'],
}
```

---

## 6. 出力PDF命名規則

```
{MMDD}{略称}{学年}{①}.pdf   ← 個別ラベルPDF
{MMDD}{略称}{学年}一式.pdf  ← 一式PDF（部署分）
```

| 構成要素 | 取得元 |
|---|---|
| MMDD | シート名から抽出（パターンA）またはユーザー入力（パターンB） |
| 略称 | PDFタイトルフィールド（ユーザー入力。試験名の短縮版） |
| 学年 | gradeItemMap のキー / ユーザーが学年ボタンで選択 |
| ① | gradeItemMap[grade] の各アイテム記号 |

**フォルダ名パターン:** `20260330_0404春期特別テスト3-5年`
- 先頭に年度 + MMDD群（`_` 区切り）
- テスト名（試験名フィールド）
- 学年範囲（`3-5年` 等）

---

## 7. 一式PDF と 内部部署等の分離（重要ルール）

### 7-1. 一式の概念

「一式」とは、教室ではなく本部スタッフがアイテム確認・手元保管のために配達する必要部数。  
小ロットをバルクで一回配送し、配送先で仕訳ける。

- **一式シート** (`一式` を含むシート名): 一式宛先ごとの部数を黄色セルで記録
- **DI/原本等のシート**: 一式分は除外され、教室への配達数のみ記録
- 一式の総合計 = 各教室の配達総合計と一致する（別途検証可能）

### 7-2. シート種別と処理の分離（⚠️ 混同禁止）

| シート種別 | 処理関数 | 目的 | 黄色判定 |
|---|---|---|---|
| 一式シート（名前に「一式」含む） | `parseIsshikiRows` | PDF宛先ラベル生成 + データ照会 | **要**（fgColor.rgb=FFFF00） |
| 通常シート（DI・原本等） | `parseExtraLeftRows` | 内部部署等の部数集計 | **不要**（位置ベース検出） |

**⚠️ なぜ分離するか:**
- 黄色セルはファイルによって存在しない場合がある
- 通常シートの合計行や集計行も黄色で着色されることがある（総合計等）
- `.xls` 形式ではインデックスカラーで保存され `fgColor.rgb` が取れないケースがある
- `parseIsshikiRows` を通常シートに適用すると誤検出・誤集計が起きる

### 7-3. `parseIsshikiRows` — 一式シート専用（黄色セルベース）

```js
// 一式シートのみに適用
// 左ブロック: nameCol=2, 学年cols=3-7（gradeMin:2, gradeMax:7）
// 右ブロック: nameCol=8, 学年cols=9-13（gradeMin:8, gradeMax:13）
// fgColor.rgb === 'FFFF00' の行のみ抽出
// 戻り値: [{ name: '総務本部', grades: {'4年':5, ...} }, ...]
```

用途:
1. `symDataRef[bucket]._isshikiDestinations` に格納 → 一式PDF生成時に使用
2. `sheetDataRef` に `type:'isshiki'` エントリとして追加 → データ照会タブ表示

### 7-4. `parseExtraLeftRows` — 通常シート専用（位置ベース）

```js
// 通常シート（DI・原本等）の 内部部署等行 を取得
// 条件: col0/col1 に学校コード(AA等)がない + col2 に名前 + col3-7 に数量
// 「小計・合計・本部計」等はSKIP。parseSchoolRows 取得済み名前は除外。
// 戻り値: [{ name: 'ロジ', grades: {'4年':10} }, ...]
```

取得対象の例: ロジ、西日暮里教務、湘南台教務 等  
→ `sheetDataRef[i].yellowRows` に格納  
→ `dataviewRows` で area='日能研本部' として追加（naibuグループ）

### 7-5. 一式PDF

- ユーザーが「一式」チェックボックスをONにする → 一式PDF生成フラグ
- 一式シート検出: `ichishikiDetected` ref（情報表示用）
- 一式PDF名: `{MMDD}{略称}{学年}一式.pdf`
- 一式対象外（個別ラベルを受け取る）: コバ、向学館ユリウス、関東物流、予備

---

## 8. ラベル並び順

```
1. SS（日能研札幌）          ← 固定で最初
2. 関東ルート順 A1→B1→...→I2 （各ルート内は停車番号順）
3. T*（東海）               ← Excel行番号順
4. K*・L*・J*・M*（関西・京都）← Excel行番号順
5. P*（四国）               ← Excel行番号順（PA徳島は最後固定）
6. R*（九州）               ← Excel行番号順
7. $tokai（東海本部特殊行）   ← 東海T*直後
8. $julius（ユリウス・アトラス）
9. $yobi（予備）
10. PA（徳島）              ← 固定で最後
```

**ソートキー実装（labelSortKey）:**
```js
if (code === 'SS') return -10000;
if (code === 'PA') return 999999;
if (SPECIAL_SORT[code]) return SPECIAL_SORT[code];
if (routeMap[code])     return routeOrder * 1000 + stop;
return 50000 + rowIdx * 2 + colIdx;   // 非関東(基底50000でI2の17999より大)
```

---

## 9. 特殊コード・特殊行

| コード | 教室名 | 備考 |
|---|---|---|
| AS_1 | 渋谷校 | ASコード重複→行インデックスで分離 |
| AS_2 | 表参道校 | 同上 |
| $tokai | 日能研東海本部 | col 2 に「東海本部」を含む特殊行 |
| $julius | ユリウス・アトラス分 | col 2 に「ユリウス」「アトラス」を含む |
| $yobi | 予備 | col 2 に「予備」を含む |
| SS | 日能研札幌 | 関東ルート外、固定で先頭 |
| PA | 徳島 | 固定で末尾 |

---

## 10. 「校」付与ルール

| 条件 | 処理 |
|---|---|
| ルート一覧（A1〜I2）に該当コードあり | 「校」を付加（赤羽 → 赤羽校） |
| NO_SUFFIX_KEYWORDS に一致 | 「校」付加しない |
| 特殊コード（$tokai等） | 「校」付加しない |

**NO_SUFFIX_KEYWORDS:** `コバ, 向学館, 関東物流, NTS, 別館, 関東本部, 職員, 本部, 受付, ロジ, 研究, 調査, 情報, 人材, 業務, 法人, 学力`

**CODE_DISPLAY_NAMES（特別表示名）:**
| コード | 表示 |
|---|---|
| GA | 日能研小田原（デフォルト「小田原校」を上書き） |

---

## 11. 箱分割ルール

部数がmaxBoxを超える場合、複数ページに分割:

```
maxBox=100, qty=125 → 箱1: 100部(1/2), 箱2: 25部(2/2)
maxBox=50,  qty=80  → 箱1: 50部(1/2), 箱2: 30部(2/2)
maxBox=250, qty=200 → 箱1: 200部(1/1) 分割なし
```

ラベル右上に `n / m`（現在箱番号/総箱数）を印字。  
通番は各箱に連番で振る（箱ごとにカウントアップ）。

---

## 12. テスト名・略称の自動生成

ヘッダー行（row 0 の col 0 または col 1）から抽出:

```js
// 日付範囲パターン（"2026年3月21・22日"等）を除去してテスト名を取得
const mDateRange = rawHeader.match(/\d{4}年\d+月\d+[・〜～\-]\d+日/);
const mDate2     = rawHeader.match(/\d{4}年(\d+)月(\d+)日/);
```

**略称（shortName）自動生成:**
```js
const auto = detectedName
    .replace(/\d{4}年\d+月\d+[・〜～\-]\d+日?|\d{4}年\d+月\d+日|\d+月\d+[・〜～\-]\d+日?|\d+月\d+日?/g, '')
    .replace(/部数一覧表|実施|テスト名?/g, '')
    .replace(/[・\s　]+/g, '')
    .trim()
    .slice(0, 8);  // 8文字以内
```

---

## 13. 既知バグ・対処済み事例

| # | 症状 | 原因 | 対処 |
|---|---|---|---|
| 1 | C2〜I2ルートが非関東より後に並ぶ | 非関東基底値10000 < C2〜I2（11001〜17001） | 基底値を50000に変更 |
| 2 | 表参道（AS）が欠落 | 渋谷もASコード → 2校目が grades を上書き | schoolKeyを `${code}_${r}` で別エントリ化 |
| 3 | 小田原が「小田原校」表示 | 元PDF表記は「日能研小田原」 | CODE_DISPLAY_NAMES で上書き |
| 4 | 「4月4DI」の日付が取れない | 「日」が抜けたtypo | `日?` で省略対応、lookahead `(?=[^月]|$)` |
| 5 | テスト名に日付が残る（"21・22日"） | mDate2正規表現が`21・22日`にマッチしない | mDateRange を先に評価 |
| 6 | ①しか検出されない（V2） | セル値に `.normalize('NFKC')` を適用すると ①(U+2460)→1(U+0031) に変換される | 記号スキャンループ内では normalize しない（生文字列 `String(row[c] \|\| '')` で検索）。学年名の全角→半角変換には NFKC を使うが、ITEM_SYMBOLS チェック前には使わないこと |
| 7 | 教室データが0件（V2） | `parseSchoolRows` が `[0, 6]` 列を検索していたが、このExcelは col 0 が空欄・col 1 にコード・col 7 に右ブロックコードの構造だった | `[0, 1, 6, 7]` を全て試し、同一行内の重複を `seenCodes` Set で排除する（V1と同じ） |
| 8 | 内部部署等（ロジ等）が部数集計に入らない | `parseIsshikiRows`（黄色セルベース）を通常シートに適用したが `.xls` のインデックスカラーでは `fgColor.rgb` が未設定 | 通常シートは色判定しない `parseExtraLeftRows`（位置ベース: col2に名前 + col3-7に数量 + コードなし）に切り替え |
| 9 | 関西本部が関西の部数集計に入らない | 境界「関西本部小計」→ area='関西本部' が `SUMMARY_AREA_GROUP` 未登録で 'other'扱い | `SUMMARY_AREA_GROUP` に '関西本部' → 'kansei' を追加（九州本部・日能研関東/関西/九州も同様に追加） |
| 10 | 一式シートがデータ照会タブに表示されない | `parseWorkbook` の一式ブロックが `newSheetList` に追加せず `continue` していた | 一式シートも `newSheetList` に `type:'isshiki'` で追加するよう変更 |

---

## 14. V2 データパイプライン（実装済み）

### 14-1. parseWorkbook の全体フロー

```
for each sheet:
  dc = extractDateCode(sheetName)     // "春期3月30日DI" → "0330"
  gradeCols = detectGradeCols(data)   // { col: '3年', ... }
  sheetSymbols = 生文字列スキャン(cols 6-20)  // ① を NFKC 前に検出
  gradesInSheet = qty > 0 の学年
  gradeItemMap[grade] += sheetSymbols // 学年別アイテムマップ更新

  legendItems = extractLegendItemsV2(data)  // 凡例から {sym, subject, itemLabel, maxBox}
  schools = parseSchoolRows(data, gradeCols) // 教室データ
  symData[dc ?? '__common'][sym] = { schools, subject, itemLabel, maxBox }
```

### 14-2. symDataRef 構造

```js
symDataRef = {
  '0330': {
    '①': { schools: { 'DL': {code:'DL', name:'日能研札幌', grades:{'3年':20,'4年':15}}, ... },
             subject: '国算', itemLabel: '解答', maxBox: 100 },
    '②': { schools: { ... }, subject: '', itemLabel: '問題用紙', maxBox: 50 },
    ...
  },
  '0404': { '①': { ... }, ... },
}
```

### 14-3. generatePdfs の全体フロー

```
for (dateCode, grade, sym) in pdfGroups:
  symEntry = symDataRef[dateCode][sym] or symDataRef['__common'][sym]
  if symEntry:
    labels = buildLabelsFromEntry(symEntry, grade, group)
    // 各 label: { routeCode, schoolName, schoolCode, date, grade, testName,
    //             subject, itemLabel, quantity, boxNum, boxTotal, serial, ... }
    for each label: drawLabel(ctx, label) → JPEG → jsPDF addImage
  else:
    drawNoDataLabel() → 代替1枚
```

### 14-4. extractLegendItemsV2 — NFKC禁止ルール

```js
// rows 0-19, cols 7-17 をスキャン
// ① ② ... を ITEM_SYMBOLS.includes(raw[0]) で検出（NFKC 前）
// 記号以降は normalize('NFKC') して subject / itemLabel に分割
const PATS = ['DI答案','DI','解答','解説','問題','答案'];
// 最初にマッチしたパターンの前が subject、以降が itemLabel
```

### 14-5. parseSchoolRows — 教室データ抽出

- コード列: **[0, 1, 6, 7] を全て試す**（Excelによって col 0 が空欄で col 1 にコードが来るケースがある）
- 名前列: コード列+1
- 同一行内での重複処理: `seenCodes` Set で排除（ASが2行ある場合は `code_r` で別エントリ）
- 部数列: gradeCols で検出した列（コード列からの相対距離 1-6）
- スキップ: 「小計」「合計」等を含む行
- 特殊行: row[2] にキーワード → `$tokai` / `$julius` / `$yobi` コードで追加
- AS重複対策: 同コードが複数行 → `${code}_${rowIndex}` で別エントリ

### 14-6. buildLabelsFromEntry — ラベルオブジェクト構築

- qty > 0 の教室のみ対象
- `needsSchoolSuffix` → 「校」付与（「本部」「ロジ」等はスキップ）
- `splitBoxes(qty, maxBox)` → 複数箱に分割
- `FALLBACK_ROUTE_MAP[code]` → routeCode, stop 取得
- ソート: `labelSortKey` (SS→関東ルート順→非関東行順→PA)
- serial: ソート後に連番

## 15. V2 vs V1 の違い

| 機能 | V1 (LabelGenerator.vue) | V2 (LabelGeneratorV2.vue) |
|---|---|---|
| プリセット | PRESETS定数＋OCR自動検出 | Excelヘッダーから直接検出（extractLegendItemsV2） |
| 学年別アイテム | sheetKey経由で完全対応 | symDataRef（sym×date×grade）で対応 |
| ラベル生成 | 実装済み | 実装済み（drawLabel 同等） |
| ルートマップ | FALLBACK_ROUTE_MAP | label_routes / label_route_stops DBから動的取得（実装済み） |
| 一式PDF内容 | 部分実装 | 未実装（Phase 2予定） |
| 出力順序 | labelSortKey 完全実装 | labelSortKey 移植済み |
| マスタ管理 | なし | モーダル4タブ（試験名・教室・アイテム・社内便） |

---

## 16. V2 マスタ管理システム（実装済み: 2026-06-11）

### 16-1. DB テーブル一覧

| テーブル | 行数 | 役割 |
|---|---|---|
| label_school_masters | 104件 | 教室コード・名前・ルート・停車順 |
| label_test_names | 52件 | テスト名・表示フラグ |
| label_item_types | 35件 | アイテム種別・表示フラグ |
| label_subjects | 0件 | 科目名（未使用） |
| label_routes | 20ルート | 社内便ルート定義（コース1: A1〜I2、コース2: A2〜G土便） |
| label_route_stops | 122件 | 社内便停留所・教室コード・到着時刻・色カテゴリ |

### 16-2. label_routes カラム

```
id, code VARCHAR(10) UNIQUE,  course TINYINT,  area VARCHAR(50),
day1 VARCHAR(20),  day1_start VARCHAR(50),
day2 VARCHAR(20) nullable,  day2_start VARCHAR(50) nullable,
sort_order TINYINT,  timestamps
```

- `course`: 1=コース1（平日午前）, 2=コース2（曜日別）
- `sort_order`: 1=A1, 2=B1, ... 10=G水便, 11=A2, ... 20=G土便

### 16-3. label_route_stops カラム

```
id, route_id FK→label_routes.id CASCADE,
stop_order TINYINT,  school_code VARCHAR(10) nullable FK→label_school_masters.code,
school_name VARCHAR(150),  arrival_time VARCHAR(10),
notes VARCHAR(200),  color_category VARCHAR(20) nullable,
timestamps
UNIQUE(route_id, stop_order)
```

- `stop_order`: 1=目黒（共通先頭）, 2〜= 各停留所
- `color_category`: honbu / kanto / busho / henkou / kakunin / ng

**Excelの色 → color_category マッピング（LabelMasterSeeder::COLOR_MAP）:**

| Excel RGB | カテゴリ | Tailwind クラス | 意味 |
|---|---|---|---|
| FFCC66 | honbu | bg-amber-100 | 本部系教室 |
| 99FF66 | kanto | bg-green-100 | 関東系教室 |
| FFFF00 | busho | bg-yellow-100 | 部署等 |
| FF66FF | henkou | bg-pink-100 | 変更 |
| FF3300 | kakunin | bg-red-100 | 確認 |
| 00B0F0 | ng | bg-sky-100 | NG便 |

### 16-4. label_school_masters.stop_order エンコード

```
stop_order = route.sort_order * 100 + stop.stop_order
```

例: B1（sort_order=2）の5番目停留所 → stop_order = 205  
→ 教室マスタ デフォルトソートは `ISNULL(stop_order), stop_order, code` でルート順を反映

### 16-5. 社内便マスタ Excel 解析（LabelMasterSeeder::seedRoutes）

- ファイル: `z_shimizu_seihan/社内便_ルート一覧_2025.1001～.xlsx`
- **setReadDataOnly は使わない**（セル背景色を読むためスタイルが必要）
- 列マップ: A=[1,2], B=[3,4], C=[5,6], D=[7,8], E=[9,10], F=[11,12], G=[13,14], H=[15,16], I=[17,18], EXTRA=[21,22]
- コース区切り: 「コース1」「コース2」を含む行でセクション分割
- 停留所行: `B-1` 等 (route_letter - stop_num) を `B1` に正規化
- 時刻: Excel 小数 (0〜1) → 分換算 → `HH:MM` フォーマット

### 16-6. 停留所の挿入・削除（insert-at / destroy-shift）

**挿入時の重複キー回避:**  
`INCREMENT` を昇順に実行すると `UNIQUE(route_id, stop_order)` に違反する。
必ず `ORDER BY stop_order DESC` で大きい番号から更新する:

```php
\DB::statement(
    'UPDATE label_route_stops SET stop_order = stop_order + 1, updated_at = ? WHERE route_id = ? AND stop_order >= ? ORDER BY stop_order DESC',
    [now(), $route->id, $pos]
);
```

### 16-7. V2 マスタモーダル構成

4タブ構成。ヘッダーのマスタボタン群でそれぞれ直接開ける。

| タブ | key | 幅 | 特記 |
|---|---|---|---|
| 試験名マスタ | testNames | 92vw / max 960px | チェックボックスで即時表示切替 |
| 教室マスタ | schools | 同上 | ヘッダークリックでソート（開いたらstop_order順リセット） |
| アイテムマスタ | itemTypes | 同上 | チェックボックスで即時表示切替 |
| 社内便マスタ | routes | 98vw / max 1500px | Excelグリッド、セルクリックで編集パネル展開 |

---

## 17. データ照会（sheetDataRef / dataviewRows）

### 17-1. sheetDataRef 構造

```js
sheetDataRef = [
  { name: '春期原本',         grades: ['3年','4年','5年'], schools: {...}, yellowRows: [...] },
  { name: '春期3月30日一式',  grades: ['4年','5年'],       schools: {}, isshikiDests: [...], type:'isshiki' },
  { name: '春期3月30日DI',    grades: ['4年','5年','6年'], schools: {...}, yellowRows: [...] },
  ...
]
```

- テストサービスシート（`/テストサービス|TS宛紙不要|\bTS\b/`）は含まない（PDF生成対象外）
- 一式シートのみ `type:'isshiki'` が付く

### 17-2. dataviewRows の切り替え

- `type !== 'isshiki'`: school rows + yellowRows（area='日能研本部'として追加）
- `type === 'isshiki'`: isshikiDests をそのまま表示（area='一式'）

---

## 18. 部数集計（summaryStructured）

### 18-1. エリアグループマッピング（SUMMARY_AREA_GROUP）

| area（マスタ or 境界検出） | グループ |
|---|---|
| 本部 | honbu |
| 東海本部, 東海本部職員, 東海, 日能研東海 | tokai |
| 日能研本部, 本部職員, 本部部署分 | naibu |
| 関東, 関東スタッフ, 日能研関東 | kanto |
| 関西, 関西本部, 日能研関西 | kansei |
| 九州, 九州本部, 日能研九州 | kyushu |

**境界検出エリア名の発生源**: `parseSchoolRows` の Pass2（`/^(.+?)(?:小計|本部計)$/`）  
例: "日能研関西小計" → area='日能研関西' → kansei グループ

### 18-2. 表示構造（通常シート）

```
本部              [honbu学校群]
日能研東海         [tokai: area='東海本部' 学校群]
東海本部           [tokai: area='日能研東海' $tokai特殊エントリ]
日能研東海 小計    [tokai合計、2種類以上ある場合のみ]
本部+東海 累計     [honbu+tokai、両方ある場合のみ]
内部部署等         [naibu: $julius($30) + $yobi($1001) + ロジ等(yellowRows)]
日能研本部 合計    [honbu+tokai+naibu]
関東              [kanto学校群]
関東 合計
関西              [kansei学校群]
関西 合計
九州              [kyushu学校群]
九州 合計
（other群: 未分類）
─────────────
総計（tfoot）
```

- 一式シート表示時: 宛先ごとの部数リストのみ（階層なし）
- 総合計（summaryTotal）は data行のみ合算（subtotal行は除く）

---

## 19. 未実装事項（今後のフェーズ）

- [ ] 学年ラベルオーバーライド（マイファースト → 新3年 等）
- [ ] 教室表示名のオーバーライド（例: GA → 日能研小田原）
- [ ] V2 と DB の本格連携: ラベル生成時に label_route_stops から routeCode/stop を取得（現状は FALLBACK_ROUTE_MAP 固定）
- [ ] 科目マスタ（label_subjects）の活用

---

*このファイルは実装・テストで新たな発見があるたびに更新すること。*
