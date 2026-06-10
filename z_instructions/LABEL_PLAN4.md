# LABEL_PLAN4.md — 宛先ラベルPDF生成ツール Phase 4 設計書

作成日: 2026-06-10

---

## 概要

Phase 3 で実装した「Excelからアイテムを自動検出する」方式を廃止し、  
**アイテム.pdf OCR フロー**に置き換える。  
アイテム.pdf が実施日・テスト名・アイテム一覧・一式フラグの唯一の正しい情報源。

---

## 背景・理由

- `s1_*.xls`（処理済みExcel）にはアイテム凡例が存在しないことが多い
- `元 Excel` にはある場合もあるが、ファイル名・格納場所が不定
- アイテム.pdf（FAX/スキャン画像含む）は担当者が常に手元に持っている
- → OCR でアイテム.pdf を読んで確認・編集 → 確定 の流れが最も堅牢

---

## Phase 4 UI フロー（新規設計）

```
[Step 0] アイテム.pdf ドロップ/選択
    ↓ アップロード → PrepressImageService (PDF→JPG) → OcrSpaceService (全ページOCR)
[Step 1] OCR確認・編集モーダル
    - 検出されたテスト（日付 + テスト名 + 学年）の一覧
    - 各テスト名に DB候補（label_test_names）を表示、選択 or 手動入力
    - アイテム一覧（①②...）と maxBox（自動推定）を確認・編集
    - 一式フラグ チェックボックス
    ↓ 「確定」ボタン
[Step 2] Excel（s1_*.xls）ドロップ/選択（既存UIを継続）
    - 読み込み後: 「原本」「テストサービス（TS）」含むシートを表示から除外
    - シート表示に OCR確定済みの テスト名 + 実施日 を組み合わせて表示
[Step 3] PDF出力（既存のまま）
```

---

## Section 1: バックエンド

### 1-1. LabelOcrController.php（新規）

```
app/Http/Controllers/LabelOcrController.php
```

**エンドポイント:** `POST /label-ocr/analyze`  
**認証:** `auth:sanctum, config('jetstream.auth_session'), 'verified'`（label-masters と同じ）

**処理フロー:**
1. Request から PDF ファイルを受け取る
2. `PrepressImageService::convertPdfToJpg($path)` で JPG 変換（ページ1のみ）
3. `OcrSpaceService::analyzeImage($jpgPath)` で全ページ OCR（言語: jpn、クロップなし）
4. `LabelItemPdfParser::parse($ocrText)` でテキストを解析
5. label_test_names / label_subjects / label_item_types から候補を返す
6. JSON レスポンス

**レスポンス構造:**
```json
{
  "tests": [
    {
      "date_raw": "3/21",
      "name_raw": "マイファーストテスト",
      "grade_raw": "",
      "matched_test_names": [
        { "id": 3, "name": "マイファーストテスト", "short_name": "MFT", "score": 1.0 }
      ]
    }
  ],
  "items": [
    {
      "num": "①",
      "text_raw": "国算社理 解答",
      "max_box": 100
    }
  ],
  "ichishiki": true,
  "ocr_text": "（元テキスト全文）",
  "image_url": "/storage/tmp/label_ocr_xxx.jpg"
}
```

### 1-2. LabelItemPdfParser.php（新規）

```
app/Services/LabelItemPdfParser.php
```

**解析ルール:**
- テスト行: `/(\d+)\/(\d+)[・〜~]?(\d+)?\/?\d*\s*実施\s*(.+)/u`
  → 例: `"3/21 実施学習力育成テスト新4年"`
  → 例: `"3/21・22 実施マイファーストテスト"`
- アイテム行: 丸囲み数字（①②...）で始まる行
- maxBox: 直前の B5用紙/封筒セクション行から推定（既存の inferMaxBox ロジックと同等）
  - 解答・解説 → 100
  - 問題 → 50
  - DI答案・封筒 → 250
- 一式フラグ: 行に「一式表記部署あり」を含む → true

**DB候補マッチング（ファジー）:**
- label_test_names: `similar_text()` または部分一致スコアで上位3件を返す

---

## Section 2: フロントエンド変更

### 2-1. LabelGenerator.vue 変更点

#### 新規 state

```js
// OCR 関連
const ocrStep         = ref('idle');    // 'idle' | 'uploading' | 'confirming' | 'done'
const ocrResult       = ref(null);      // バックエンドから返ってきたJSON
const ocrImageUrl     = ref('');
const confirmedTests  = ref([]);        // 確定済みテスト一覧
const confirmedItems  = ref([]);        // 確定済みアイテム一覧（detectedItemsを置き換え）
const ichishikiFlag   = ref(false);     // 一式PDF生成フラグ
const itemPdfName     = ref('');        // アイテムPDFのファイル名（UI表示用）
```

#### 変更する computed / function

- `activeItems`: `confirmedItems.length > 0 ? confirmedItems : PRESETS[selectedPreset]?.items ?? []`
- `detectedItems` → 廃止（`confirmedItems` に一本化）
- `extractLegendItems` → 廃止（使わない）
- `parseWorkbook`: "原本" / "テストサービス"（TS） を含むシートを `visibleSheets` から除外

#### 追加 UI コンポーネント（モーダル内）

**Step 0: アイテムPDFドロップゾーン**
- Board.vue 伝票登録モーダルと同じ UI
- ドロップ or 「ファイルを選択」ボタン
- ファイル選択後 → 自動アップロード → spinner 表示

**Step 1: OCR確認モーダル**
- モーダル上部: アイテムPDF OCR結果プレビュー（OCRテキスト）
- テストセクション（繰り返し）:
  - 実施日 input（手動修正可）
  - テスト名 input（手動修正可）+ DB候補ボタン群
  - 学年 input（手動修正可）
- アイテムセクション（繰り返し）:
  - 丸番号 | テキスト input | maxBox select
  - 削除ボタン / 追加ボタン
- 一式フラグ チェックボックス
- 「確定」ボタン → ocrStep = 'done'

---

## Section 3: 既存 Phase 3 機能の取り扱い

| 機能 | Phase 4 での扱い |
|---|---|
| `extractLegendItems` | 削除（OCRに置き換え） |
| `detectedItems` ref | 削除（`confirmedItems` に置き換え） |
| PRESET A〜H | 保持（`confirmedItems` が空の場合のフォールバック）|
| `gradeLabelOverrides` | 保持（学年別印字ラベル名オーバーライド）|
| `gradeTestNameOverrides` | OCR結果で自動設定 → 手動修正可 |
| `gradeDateOverrides` | OCR結果で自動設定 → 手動修正可 |
| `testDateVal` / `testNameVal` | OCR確定後に自動設定（なければ手動入力） |
| `shortNameVal` | DB `short_name` から自動設定 → 手動修正可 |

---

## Section 4: ファイル変更一覧

| ファイル | 変更種別 | 内容 |
|---|---|---|
| `app/Http/Controllers/LabelOcrController.php` | 新規 | OCR解析エンドポイント |
| `app/Services/LabelItemPdfParser.php` | 新規 | OCRテキスト解析・DB候補マッチング |
| `routes/web.php` | 変更 | /label-ocr/analyze ルート追加 |
| `resources/js/Components/Scripts/LabelGenerator.vue` | 変更 | UI全面改修（OCRフロー + シートフィルタ） |

---

## Section 5: 認証・ルート

```php
// web.php
Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])
    ->group(function () {
        // 既存 label-masters ルートの近くに追加
        Route::post('/label-ocr/analyze', [LabelOcrController::class, 'analyze'])
             ->name('label-ocr.analyze');
    });
```

---

## Section 6: OcrSpaceService の利用

`app/Services/OcrSpaceService.php` の既存実装を流用。  
アイテムPDF用は **クロップなし**（全ページ OCR）でコール。

`LabelOcrController` から:
```php
$ocrText = app(OcrSpaceService::class)->recognizeFullPage($jpgPath);
```

`OcrSpaceService` に `recognizeFullPage($imagePath): string` メソッドを追加（または既存 `analyze` の引数でクロップを省略できるか確認）。

---

## 判断メモ

- **maxBoxはアイテムテキストから推定**（解答/解説→100, 問題→50, DI答案/封筒→250）。PDF記載値との不一致があれば OCR確認モーダルで手動修正。
- **OCR精度が低い場合も「ユーザーが確認・修正する」前提**で OK（OcrModal.vue パターンと同じ）。
- **アイテム.pdfが複数ページある場合**: 1ページ目のみ OCR（通常は1枚）。複数テストが1ページに記載される形式なので十分。
- **ロジ.xls フロー**: Phase 4 範囲外。既存のロジ関連コードは変更しない。
