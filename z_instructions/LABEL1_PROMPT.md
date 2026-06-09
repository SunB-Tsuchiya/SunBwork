# LABEL1_PROMPT.md — 宛先ラベル生成ツール 新セッション開始用プロンプト

最終更新: 2026-06-06（Excel構成パターン解析後）

---

## このファイルの使い方

新しいClaudeセッションでラベル生成ツールの実装を続けるときは、  
このファイルの内容をそのままプロンプトとして貼り付けてください。

---

## プロンプト本文

---

SunBWorkプロジェクト（Laravel11 + Vue3 + Inertia.js）に、  
清水製版向け「宛先ラベルPDF自動生成ツール」を /scripts セクションに追加しています。

### 背景

日能研テストの発送段ボールに貼る宛先ラベルを、  
現在はPageMakerで手作業で作っているものを自動化します。  
解析済みのルールに従ってExcel → PDF変換を行うツールです。

### 必ず最初に読むファイル

1. `/home/tchirosb/SunBWork/CLAUDE.md` — プロジェクト基本ルール
2. `/home/tchirosb/SunBWork/z_instructions/LABEL_PLAN1.md` — 詳細仕様（§9〜12が最新の設計）
3. `/home/tchirosb/SunBWork/z_instructions/LABEL_MANAGER1.md` — 進捗管理・解析ログ・確認事項

### 実装対象ファイル

- `resources/js/Components/Scripts/LabelGenerator.vue`（実装済み・要改修）

### 現在の状態と問題

Phase1初版（2026-06-06）は実装済みだが以下の問題がある:

1. **複数実施日のシートが衝突する** — 同じExcelに3/30と4/4のシートが入っており、同じ `sheetKey`（`summer_main_di` 等）に分類されるため後者がスキップされる
2. **ファイル名規則が違う** — 現在: `①_解説_テスト名.pdf` → 目標: `0330春期特別3年①.pdf`
3. **「原本」シートの扱い** — パターンBでは「原本」シートが実際のデータ。フォールバック順序が不足

### 解決方針（PLAN1 §9〜12に詳細）

**データ構造変更:**
```
sheetsData = {
  '__common': { main: {...} },           // 日付なしシート
  '0330': { summer_main_di: {...}, ... }, // 3/30のシート
  '0404': { summer_main_di: {...}, ... }, // 4/4のシート
}
```

**ファイル名規則:**
```
MMDD{略称}{学年}{アイテム番号}.pdf
例: 0330春期特別3年①.pdf
```

**generatePDFs の出力ループ:**
```
detectedDates × gradeOptions × preset.items → 1ファイル（0件ならskip）
```

### 参照ファイル（実ファイルで検証済み）

```
Shimizu_Seihan/2026/20260330_0404春期特別テスト3-5年/s1_0330・0404春期3-5年.xls
  → パターンA（日付別シート）の代表例。12シート。
Shimizu_Seihan/2026/20260307_08学習力育成テスト新4-6年/s1_0307_08学習力新4-6年_再作成.xls
  → パターンB（学年別日付）の代表例。日付なし5シート。
```

### 既存の実装の概要（LabelGenerator.vue）

- `sheetsData` ref — `{ sheetKey: schools }` 形式（→ `{ dateCode: { sheetKey: schools } }` に変更要）
- `detectSheetKey(sheetName)` — シート名からsheetKeyを分類（ほぼ正しい）
- `parseWorkbook(wb)` — ワークブック全体をパース（日付グループ化を追加要）
- `buildLabels()` — presetsとsheetsDataからラベル配列を生成（引数に dateCode, grade を追加要）
- `generatePDFs()` — jsPDFでPDF出力（日付×学年×アイテムのループに変更要）
- `drawLabel(ctx, label)` — Canvasにラベルを描画（変更なし）

---
