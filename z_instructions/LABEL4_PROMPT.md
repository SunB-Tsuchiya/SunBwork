# LABEL4_PROMPT.md — 宛先ラベルPDF生成ツール Phase 4 引継ぎプロンプト

作成日: 2026-06-10  
対象ファイル: `resources/js/Components/Scripts/LabelGenerator.vue`（約1900行）

新セッション開始時、このファイルを読み込んでから作業に入ること。  
併せて `z_instructions/LABEL_PLAN4.md`（設計書）・`z_instructions/LABEL_MANAGER4.md`（進捗）も参照すること。

---

## プロジェクト概要

清水製版の発送業務で使う宛先ラベルPDFを、ブラウザ内で自動生成するツール。  
日能研テストの教室別・アイテム別・学年別にPDFを出力する。

- ページ: `resources/js/Components/Scripts/LabelGenerator.vue`（単一ファイル）
- ルート: `scripts.show` → `Show.vue` の componentMap に `'LabelGenerator'` で登録済み
- DB: label_school_masters / label_test_names / label_subjects / label_item_types

---

## Phase 4 の目的

Phase 3 では Excel ファイルからアイテムを自動検出しようとしたが、  
処理済み `s1_*.xls` にはアイテム凡例が存在しないことが多く、根本的に無理だった。

**正しいアプローチ:**  
アイテム.pdf（担当者が常に持っている正式資料）を OCR して、  
テスト情報・アイテム一覧・一式フラグを取得する。

---

## 実装済み（Phase 1〜3）

- Excel (s1_*.xls) 読み込み → 教室コード・部数・学年を抽出
- PRESET A〜H（アイテム定義のフォールバック）
- DB マスタ（教室・テスト名・科目・アイテム種別）
- 学年別設定（テスト名・実施日・印字ラベル名のオーバーライド）
- A5横 PDF 出力（jsPDF + Canvas）
- ファイル名: `MMDD略称学年①.pdf` / 一式時は `MMDD略称学年一式.pdf`

---

## Phase 4 追加実装（未完了）

### バックエンド

1. **`app/Services/OcrSpaceService.php`** — `recognizeFullPage($imagePath): string` メソッド追加
   - クロップなし全ページ OCR
   - 言語: `jpn`、OCREngine=2

2. **`app/Services/LabelItemPdfParser.php`**（新規）— OCRテキストを解析
   - テスト行: `/(\d+)\/(\d+)[・〜~]?(\d+)?\/?\d*\s*実施\s*(.+)/u`
   - アイテム行: 丸囲み数字（①②③...）で始まる行
   - maxBox: 解答/解説→100、問題→50、DI答案/封筒→250
   - 一式フラグ: 「一式表記部署あり」を含む行
   - DB候補: label_test_names から similar_text でスコア付き上位3件

3. **`app/Http/Controllers/LabelOcrController.php`**（新規）
   - `POST /label-ocr/analyze`
   - PDF → JPG（PrepressImageService）→ OCR → 解析 → JSON 返却

4. **`routes/web.php`**
   - `label-masters` と同じ middleware グループに追加
   - `Route::post('/label-ocr/analyze', [LabelOcrController::class, 'analyze'])->name('label-ocr.analyze');`

### フロントエンド（LabelGenerator.vue）

#### 新規 state
```js
const ocrStep        = ref('idle');    // 'idle' | 'uploading' | 'confirming' | 'done'
const ocrResult      = ref(null);      // バックエンドからのJSON
const confirmedTests = ref([]);        // 確定済みテスト一覧
const confirmedItems = ref([]);        // 確定済みアイテム（activeItems に優先使用）
const ichishikiFlag  = ref(false);     // 一式フラグ
const itemPdfName    = ref('');        // PDFファイル名（UI表示用）
```

#### 主要変更
- `activeItems` computed: `confirmedItems.length > 0 ? confirmedItems : PRESETS[selectedPreset]?.items ?? []`
- `detectedItems` ref・`extractLegendItems` → 削除（`confirmedItems` に統一）
- `parseWorkbook`: "原本" / "テストサービス"（"TS"）を含むシート名を `visibleSheets` から除外

#### UI 追加
1. Step 0: アイテムPDF ドロップゾーン（Board.vue 伝票登録モーダルと同じ UI）
2. Step 1: OCR確認・編集モーダル
   - テスト一覧（日付・テスト名・DB候補）
   - アイテム一覧（丸番号・テキスト・maxBox）
   - 一式フラグ チェックボックス
   - 「確定」ボタン

---

## 参照ファイル

| ファイル | 役割 |
|---|---|
| `app/Services/OcrSpaceService.php` | 既存 OCR サービス（流用） |
| `app/Services/PrepressImageService.php` | PDF→JPG 変換（流用） |
| `app/Http/Controllers/Prepress/TicketOcrController.php` | 実装パターン参考 |
| `resources/js/Components/Prepress/OcrModal.vue` | OCR確認 UI パターン参考 |
| `resources/js/Pages/Prepress/Board.vue` | ドロップゾーン UI パターン参考 |
| `z_instructions/LABEL_PLAN4.md` | 詳細設計書 |
| `z_instructions/LABEL_MANAGER4.md` | 進捗管理 |
