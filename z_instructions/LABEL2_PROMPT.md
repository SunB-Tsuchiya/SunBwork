# LABEL2_PROMPT.md — 宛先ラベルPDF生成ツール Phase 2 新セッション開始プロンプト

最終更新: 2026-06-07

---

## このファイルの使い方

新しいClaudeセッションでPhase 2の実装を続けるときは、このファイルの内容をそのままプロンプトとして貼り付けてください。

---

## プロンプト本文

---

SunBWorkプロジェクト（Laravel11 + Vue3 + Inertia.js）で宛先ラベルPDF生成ツールのPhase 2を実装します。

### 必ず最初に読むファイル

1. `/home/tchirosb/SunBWork/CLAUDE.md` — プロジェクト基本ルール
2. `/home/tchirosb/SunBWork/z_instructions/LABEL_PLAN2.md` — Phase 2 詳細設計
3. `/home/tchirosb/SunBWork/z_instructions/LABEL_MANAGER2.md` — 進捗管理・作業ログ

### Phase 2 の目的

ハードコードされたマスタデータ（教室名・ルートコード・テスト名・科目・内容）をDBに移行し、ブラウザから担当者が編集できるようにする。

### 実装対象ファイル

- `database/migrations/` — 新規4ファイル
- `app/Models/` — 新規4ファイル（LabelSchoolMaster, LabelTestName, LabelSubject, LabelItemType）
- `app/Http/Controllers/LabelMasterController.php` — 新規
- `database/seeders/LabelMasterSeeder.php` — 新規
- `routes/web.php` — ルート追加
- `resources/js/Components/Scripts/LabelGenerator.vue` — DB連携・マスタ管理UI追加

### 初期データファイル（Seeder で使用）

- `Shimizu_Seihan/school_master_draft.csv` — 教室170件（code,display_name,area,route,stop,notes）
- `Shimizu_Seihan/filemakerファイル_forClaude/テスト名.txt`
- `Shimizu_Seihan/filemakerファイル_forClaude/科目.txt`
- `Shimizu_Seihan/filemakerファイル_forClaude/内容.txt`

### 既存の LabelGenerator.vue の状態（Phase 1 完成済み）

- `CODE_DISPLAY_NAMES` — 教室表示名オーバーライド（DBに置き換え予定）
- `DEFAULT_ROUTE_MAP` — ルートコードマップ（DBに置き換え予定）
- `SPECIAL_ENTRY_KEYWORDS` / `SPECIAL_SORT` / `SPECIAL_SORT の $tokai/$julius/$yobi` — 特殊コード（DBに追加）
- `PRESETS` — プリセット定義（Phase 2 では変更しない）

### AS コード重複の対処

- DB では `AS_1`（渋谷校）/ `AS_2`（表参道校）として別レコード
- Excel パース時の `${code}_${rowIdx}` ロジックの修正も必要
  - 現状: `schools[code] ? \`${code}_${r}\` : code` で別キーを生成
  - Phase 2: DB の code と突合できるよう `AS_1` / `AS_2` に統一する

### 注意事項

- Artisan は必ずコンテナ内: `docker compose exec laravel bash -lc "php artisan ..."`
- さくら本番への migrate は別途 SSH で実行（DEPLOY_SAKURA.md 参照）
- マスタ管理UIの権限: 全ログインユーザーが編集可（変更があれば PLAN2 を確認）

---
