# 進行表V2 実装 Claude向けプロンプトファイル
作成日: 2026-04-25

---

## このファイルの使い方

新しい Claude セッションを開始するとき、このファイルの内容をそのまま冒頭に貼り付けてください。
または「PROGRESS_SHEET_V2_PROMPT.md を読んで実装を続けてください」と指示してください。

---

## セッション開始時のプロンプト（コピーして使用）

```
あなたはこれからSunBWorkプロジェクトの「進行表V2刷新」作業を行います。

まず以下のファイルを必ず読んでください：
1. `/home/tchirosb/SunBWork/CLAUDE.md`（プロジェクト全体ルール）
2. `/home/tchirosb/SunBWork/z_instructions/REPAIR_MANAGER.md`（修繕全体の進捗管理書）
3. `/home/tchirosb/SunBWork/z_instructions/PROGRESS_SHEET_V2_MANAGER.md`（進行表V2専用の進捗管理書）
4. `/home/tchirosb/SunBWork/z_instructions/PROGRESS_SHEET_V2_DESIGN.md`（詳細設計書）

読み終えたら、PROGRESS_SHEET_V2_MANAGER.md の進捗一覧を確認し、
次に着手すべき作業（V-xx）を提示してください。
作業は REPAIR_MANAGER.md の「作業フロー（5ステップ）」に従って進めてください。

各V-xx作業の完了・進捗状況は必ず PROGRESS_SHEET_V2_MANAGER.md に記録してください：
- 作業完了時: 進捗一覧のステータスを「✅ 完了」に更新し、作業ログに変更ファイルを記録
- ビルド成功・ユーザー確認待ちの場合: ステータスを「🔨 実装中」に更新
- REPAIR_MANAGER.md のフェーズV欄も同様に更新すること
```

---

## 設計サマリー（Claude向け補足）

### プロジェクト背景

- **業種:** 印刷・組版会社向け社内管理システム（Laravel 11 + Vue 3 + Inertia.js）
- **目的:** 進行管理表のセル型を刷新し、担当者管理・ジョブ連携・スケジュール連携を1セルに統合する
- **決定経緯:** 2026-04-25 にユーザーとのQ&Aで全仕様を確定済み

### 最重要ルール（CLAUDE.md より）

1. 作業前に必ず関連コードを読む
2. 設計提示 → ユーザー確認 → 実装の順を守る
3. 質問は1つずつ
4. Vue/JSファイル変更後は `npm run build`
5. Artisan は `docker compose exec laravel bash -lc "php artisan ..."`
6. さくら本番では `route()` 必須・ハードコードパス禁止

### 新セル型の概要

**`worker`型:** 担当者セレクター（左70%）＋ ジョブ登録/詳細/完了（右30%）の統合セル
- ジョブ登録なしでも担当者のみ設定可（外注・非PC対応）
- 締め切り: cell_deadline（手動）> schedule.end_date > assignment.desired_end_date
- 完了後: 「締切: 26/11/03」→「完了: 26/11/05」に変化

**`schedlink`型:** カレンダー項目セレクター（左70%）＋ 完了/詳細（右30%）
- 完了操作で `project_schedules.completed_at` を更新

### DB変更（V-01で実施）

```
progress_cells:
  + schedule_id (FK→project_schedules, nullable)
  + cell_deadline (date, nullable)
  + cell_note (text, nullable)
  + completed_at (timestamp, nullable)

progress_sheets:
  + share_token (string 64, nullable, unique)

project_schedules:
  + completed_at (timestamp, nullable)
```

### 実装順序

V-01 → V-02 → V-03 → V-04 → V-05 → V-06 → V-07 → V-08 → V-09 → V-10 → V-11 → V-12

詳細は `PROGRESS_SHEET_V2_DESIGN.md` の「実装順序」セクションを参照。

### よくある落とし穴（過去の修正から）

- さくら本番に存在しないカラムを `update()` に含めると壊れる → `Arr::pull()` で除去してから保存
- `project_jobs.schedule` カラムはさくらに存在しない（CONSOLIDATED_09参照）
- ProgressCell の `col_key` はシート内でユニークな文字列キー（数値IDではない）
- `column_config` はJSONとしてDBに保存されており、フロントとバックで同じ構造を共有する
- セット方式の列キー命名規則: `round1_kumihan_tanto`（round番号 + 業務 + フィールド）

### 主要ファイルパス

```
app/Models/ProgressCell.php
app/Models/ProgressSheet.php
app/Models/ProgressRow.php
app/Models/ProjectSchedule.php
app/Http/Controllers/Coordinator/ProgressSheetController.php
app/Http/Controllers/Coordinator/ProgressCellController.php
app/Http/Controllers/ProjectJobs/JobBoxController.php
resources/js/Components/ProgressCell.vue
resources/js/Pages/Coordinator/ProgressSheets/Show.vue
resources/js/Pages/Coordinator/ProgressTemplates/Edit.vue
resources/js/Pages/JobBox/Index.vue
routes/web.php
```
