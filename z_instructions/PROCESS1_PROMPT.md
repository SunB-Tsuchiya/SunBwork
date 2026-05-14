# PROCESS1_PROMPT.md — 工程シート・項目リスト 新セッション開始プロンプト

このファイルをそのまま新セッションの冒頭に貼り付けてください。

---

## セッション開始プロンプト（ここからコピー）

---

工程シート・項目リスト機能の実装を続けます。

作業前に必ず以下を読んでください:
- `z_instructions/PROCESS_PLAN1.md` — 詳細仕様・DB設計・タスク一覧
- `z_instructions/PROCESS_MANAGER1.md` — 進捗状況・未決事項

### プロジェクト概要
Laravel 11 / Vue 3 / Inertia.js / Vite / Tailwind CSS の社内管理SPA。
- Artisan: `docker compose exec laravel bash -lc "php artisan ..."`
- ビルド: `npm run build`（プロジェクトルートで実行）
- ルート: すべて `routes/web.php`（api.php 不使用）

### 機能概要

**① 項目リスト**
- 案件（ProjectJob）ごとに作業項目名を登録するシンプルなリスト
- テーブル: `project_item_entries`（id, project_job_id, name, sort_order）
- ProjectJob詳細ページに「項目リスト」タブとして追加
- AssignmentForm.vue・台割フォーム・マイジョブ作成フォームのジョブ名入力にオートコンプリート候補として提供

**② 工程シート（WorkflowSheet）**
- 進行表（ProgressSheet）に似た構造だが、専用の固定ステージ（進行・組版・校正・校正２など）を持つ
- 「項目リスト × ステージ」のマトリクス表。担当者・作業日・作業時間を記録
- テーブル: `workflow_templates` / `workflow_sheets` / `workflow_rows` / `workflow_cells`
- ステージ構成はJSONで可変（デフォルト4ステージ。校正が減ることもある）
- ステージ型: `coordinator`（進行 → Coordinator/Leader/Clerkのみ担当可） / `worker`（組版・校正など → 全ユーザー）
- 1案件に複数枚・ステージ構成テンプレートあり・案件複製時に行データも複製
- ProjectJob詳細ページに「工程シート」タブとして追加
- ステージ別小計・行合計・シート合計を表示
- PDF/印刷は Phase 8（フォーマット設計は後で）

### DB テーブル（新規5テーブル）
```
workflow_templates   id, name, stage_config(JSON), created_by
project_item_entries id, project_job_id, name, sort_order
workflow_sheets      id, project_job_id, template_id, name, stage_config(JSON), sort_order, created_by
workflow_rows        id, sheet_id, label, sort_order, item_entry_id(nullable)
workflow_cells       id, row_id, stage_key, assigned_user_id, assignment_id, work_date, work_minutes, completed_at, cell_note, cell_note_user_id
```

### stage_config JSON例
```json
{
  "stages": [
    { "key": "shinko",  "label": "進行",  "type": "coordinator" },
    { "key": "kumihan", "label": "組版",  "type": "worker" },
    { "key": "kosei",   "label": "校正",  "type": "worker" },
    { "key": "kosei2",  "label": "校正２","type": "worker" }
  ]
}
```

### 主要な既存ファイル（参考）
- 進行表モデル: `app/Models/ProgressSheet.php` / `ProgressRow.php` / `ProgressCell.php`
- 案件複製: `app/Http/Controllers/Coordinator/ProjectJobController.php` の `clone()` メソッド
- ProjectJob詳細タブ: `resources/js/Pages/Coordinator/ProjectJobs/Show.vue`（現在6タブ）
- 進行表コントローラー: `app/Http/Controllers/Coordinator/ProgressSheetController.php`

### 現在の進捗
PROCESS_MANAGER1.md を確認してください。

### 今日やるべきタスク
PROCESS_MANAGER1.md の進捗テーブルを確認し、次の未着手タスクから実装を開始してください。
実装前に必ず関連コードを読み、設計方針を示してから着手してください。

---
（ここまでコピー）
