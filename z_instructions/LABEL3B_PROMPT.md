# LABEL3B_PROMPT.md — 宛先ラベルPDF生成ツール Phase 3 継続引継ぎプロンプト

作成日: 2026-06-07  
最終更新: 2026-06-09  
対象ファイル: `resources/js/Components/Scripts/LabelGenerator.vue`（約1870行）

新セッション開始時、このファイルを読み込んでから作業に入ること。  
併せて `z_instructions/LABEL_PLAN3.md`（設計書）・`z_instructions/LABEL_MANAGER3.md`（進捗）も参照すること。

---

## プロジェクト概要

清水製版の発送業務で使う宛先ラベルPDFを、Excelの発送部数一覧（s1_*.xls）から  
ブラウザ内で自動生成するツール。日能研テストの教室別・アイテム別・学年別にPDFを出力する。

- ページ: `resources/js/Components/Scripts/LabelGenerator.vue`（単一ファイル）
- ルート: `scripts.show` → `Show.vue` の componentMap に `'LabelGenerator'` で登録済み
- DB: label_school_masters / label_test_names / label_subjects / label_item_types（Phase 2 作成済み）

---

## 実装済み機能サマリー（このセッションまでの全作業）

### Phase 1（基本機能）✅
- Excel（s1_*.xls）をブラウザで読み込み
- 教室コード・部数・学年を抽出
- PRESET（A〜H）のアイテム定義に基づいてラベルデータを生成
- A5横（729×516pt）のPDFを生成（jsPDF + Canvas）
- ファイル名: `MMDD略称学年番号.pdf`

### Phase 2（DBマスタ化）✅
- 教室マスタ・テスト名・科目・内容をDBで管理
- マスタ管理タブ（CRUD UI）を追加
- onMounted で axios取得 → routeMap をDB優先で構築
- テスト名入力に datalist でサジェスト

### Phase 3（アイテム自動検出）✅
- **Excel凡例からアイテムを自動検出**（col 7〜17, row 0〜19 を走査）
- `detectedItems` ref + `activeItems` computed（detectedItems 優先・PRESET フォールバック）
- アイテム編集UI（追加・削除・変更）
- 学年テキスト auto-shrink（角丸枠に収まるよう自動縮小）

### Phase 3.1（実施日の自動取得）✅
- `testDateVal` ref: Excelヘッダーから日付を自動抽出（例: "2026年3月21日"）
- 日付範囲（"月DD・DD日"）形式のテスト名抽出バグ修正
- `detectedDates`（シート名日付）がある場合は実施日フィールドを非表示
- `dateValToMMDD()` でファイル名のMMDDプレフィックスを生成

### Phase 3.2（学年別設定）✅
- `gradeTestNameOverrides` / `gradeDateOverrides` state 追加
- **「学年別設定」パネル**（3列: 印字テスト名 / 実施日 / 印字ラベル名）に統合
  - 空欄 = グローバル設定をそのまま使用
  - 実施日列は `detectedDates.length > 0`（シート名日付あり）の場合は非表示
- `buildLabels()`: 学年ごとのテスト名・実施日を参照するよう変更
- `generatePDFs()`: `datePart` を学年ループ内に移動（学年別実施日 → MMDD生成）
- 実施日が全未設定の場合、出力前に `confirm()` ダイアログを表示

### Phase 3.3（赤枠フィードバック修正）✅ 2026-06-09
- **修正済み**: 入力フィールドの赤枠フィードバックが効かない問題を解決
- 原因: Tailwind JIT が `:class` の動的クラス（`!border-red-400 !bg-red-50`）をパージしていた
- 対処: `:style` インラインスタイルに変更（L1671, L1684, L1704）
  ```html
  :style="excelName && !testNameVal ? 'border-color: #f87171; background-color: #fef2f2;' : ''"
  ```
- 動作確認: Excel読み込み後にフィールドを手動で空にすると赤枠が表示されることを確認済み
- 仕様再確認: 赤枠は「Excel読み込み済み（excelName非空）かつフィールドが空」のときのみ表示

---

## 【重要】未調査の疑問点（次のセッションで確認すること）

### アイテム自動検出が動いていない可能性

2026-06-09 のテストで、`s1_0321-22MFT学習力新4-6年_再作成.xls` を読み込んだところ、  
出力アイテム欄が「**プリセット A: 学習力育成テスト（4〜8科目型）**」を表示していた。

Phase 3 では Excel 凡例からアイテムを自動検出（`detectedItems` に格納）し、  
`detectedItems.length > 0` のときはプリセット表示を隠す仕様のはず。  
しかしこのファイルでプリセットが表示されたということは、**`detectedItems` が空**だったと考えられる。

**次のセッションで確認すべき点:**
1. `extractLegendItems()` がこのファイルで正しく動作しているか
   - ブラウザの DevTools Console で `detectedItems` の値を確認
   - Excel の凡例列位置が col 7〜17 の範囲に実際にあるか確認
2. `parseWorkbook` の legendByNum 収集ロジックがこのファイルに対応しているか
3. シート名 `国算問題原本`・`社理問題原本` に対して `detectSheetKey()` が正しいキーを返しているか

**参考: 対象Excelファイルのパス**  
`/home/tchirosb/SunBWork/Shimizu_Seihan/2026/20260321_22MFT学習力育成テスト新4−6年/s1_0321-22MFT学習力新4-6年_再作成.xls`

---

## 重要な状態（state）一覧

```js
// 主要 ref
const excelName     = ref('');       // 読み込んだExcelのファイル名
const testNameVal   = ref('');       // テスト名（ラベル印字）
const testDateVal   = ref('');       // 実施日（例: "2026年3月21日"）
const shortNameVal  = ref('');       // ファイル名略称（最大8文字程度）
const gradeOptions  = ref([]);       // 検出された学年リスト ['3年','4年','5年','6年']
const detectedDates = ref([]);       // シート名から検出されたMMDDリスト ['0330','0404']
const detectedItems = ref([]);       // Excel凡例から自動検出されたアイテムリスト

// 学年別オーバーライド
const gradeLabelOverrides    = ref({});  // { '3年': 'マイファースト' }
const gradeTestNameOverrides = ref({});  // { '3年': 'マイファーストテスト' }
const gradeDateOverrides     = ref({});  // { '6年': '2026年3月22日' }
```

---

## 日付2パターンの重要な違い

| パターン | 例 | 挙動 |
|---|---|---|
| A: シート名に日付 | 春期テスト | `detectedDates` に自動格納 → 学年別設定の実施日列を**非表示** |
| B: ヘッダーに日付のみ | 学習力育成テスト | `testDateVal` に格納 → 学年別設定の実施日列を**表示** |

パターンBかつ学年ごとに実施日が違う場合（例: 3〜5年=3/21・6年=3/22）:  
→ 学年別設定の「実施日」列で手動入力。空欄の学年は `testDateVal`（グローバル）を使用。

---

## ファイル名と教室コードの確認事項

- ファイル名略称は `parseWorkbook` 内で自動生成される（テスト名から日付・記号を除去して8文字以内）
- Excel ファイル `s1_0321-22MFT学習力新4-6年_再作成.xls` のパスは:  
  `/home/tchirosb/SunBWork/Shimizu_Seihan/2026/20260321_22MFT学習力育成テスト新4−6年/s1_0321-22MFT学習力新4-6年_再作成.xls`

---

## 残作業（優先順）

| 優先 | タスク | 状態 |
|---|---|---|
| 🔴 高 | アイテム自動検出が動いていない疑いを調査・修正 | 未着手（上記「未調査の疑問点」参照）|
| 🟡 中 | 実際のExcelファイルで出力テスト（T-01〜T-08） | 待ち |
| 🟡 中 | テスト結果を踏まえたバグ修正 | 待ち |
| 🟢 低 | さくら本番デプロイ（テスト完了後） | 待ち |

---

## ビルド方法

```bash
# プロジェクトルートで実行
npm run build
```

エラーなし・約21秒で完了が正常。ビルド後はブラウザを `Ctrl+Shift+R` でハードリフレッシュすること。

---

## セッションログ

| 日付 | 内容 |
|---|---|
| 2026-06-07 | Phase 3 / 3.1 / 3.2 全実装完了 |
| 2026-06-09 | 赤枠フィードバックCSSバグ修正（`:class` → `:style` インラインスタイルへ変更）。ビルド完了。 |
| 2026-06-09 | テスト実施: `s1_0321-22MFT学習力新4-6年_再作成.xls` を読み込み、テスト名・実施日・略称の自動取得は正常。ただしアイテムがプリセットAを使用しており、自動検出が機能していない可能性あり。別PCで詳細調査予定。 |
| 2026-06-09 | さくら本番へデプロイ（赤枠修正のみ）。 |
