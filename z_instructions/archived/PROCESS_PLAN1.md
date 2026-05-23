# PROCESS_PLAN1.md — 工程シート・項目リスト 詳細設計書

作成日: 2026-05-14  
対象ブランチ: main  
関連ファイル: PROCESS_MANAGER1.md / PROCESS1_PROMPT.md

---

## 1. 概要・目的

進行表（ProgressSheet）は柔軟性が高い反面、作成に手間がかかる。  
これを補う「工程シート」と、入力補助のための「項目リスト」を追加する。

| 機能 | 目的 |
|------|------|
| **項目リスト** | 案件ごとに作業項目名を登録。ジョブ作成・台割入力時のオートコンプリート候補として使う |
| **工程シート** | 項目（行）× ステージ（列グループ）のマトリクス。担当者・作業日・作業時間を記録し、時間集計・PDF出力ができる |

---

## 2. 用語定義

| 用語 | 意味 |
|------|------|
| **項目リスト** | 案件（ProjectJob）ごとに登録する作業項目名の一覧。オートコンプリートの候補源 |
| **工程シート** | 項目×ステージのマトリクス表。会社の工程管理用 |
| **ステージ** | 工程シートの列グループ（進行・組版・校正・校正２など）。JSON設定で可変 |
| **行（WorkflowRow）** | 工程シートの縦の1項目（例：「序章初校作成」） |
| **セル（WorkflowCell）** | 行とステージの交点。担当者・作業日・作業時間・完了フラグを持つ |
| **coordinator型ステージ** | 「進行」などCoordinator/Leader/Clerkしか担当できないステージ |
| **worker型ステージ** | 「組版」「校正」などすべてのユーザーが担当できるステージ |

---

## 3. 設計確認済み仕様

| 項目 | 決定内容 |
|------|----------|
| ステージ構成 | 可変（JSON）。デフォルト4ステージ（進行・組版・校正・校正２）。校正が減ることもある |
| 工程シート枚数 | 1案件に複数枚 |
| 行の作成方法 | C：項目リストからインポート ＋ 個別追加・編集 の両対応 |
| 項目リストのスコープ | B：ProjectJobごとに独立したリスト |
| 進行ステージの担当者 | worker同型。割り当て可能ロールのみCoordinator/Leader/Clerkに制限 |
| テンプレート | あり。ステージ構成をテンプレートとして保存・選択できる |
| 案件複製 | 工程シート（行のみ・セルデータは除外）も複製対象に含める |
| オートコンプリート対象フォーム | AssignmentForm.vue / 台割項目名 / マイジョブ作成フォーム |
| UIの配置 | ProjectJob詳細ページのタブに「項目リスト」「工程シート」を追加 |
| PDF/印刷 | Phase 8（後で詳細設計。ISO基準対応） |

---

## 4. DB設計

### 4-1. project_item_entries（項目リスト）

```sql
CREATE TABLE project_item_entries (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_job_id BIGINT UNSIGNED NOT NULL,
    name          VARCHAR(255) NOT NULL,
    sort_order    INT NOT NULL DEFAULT 0,
    created_at    TIMESTAMP NULL,
    updated_at    TIMESTAMP NULL,
    FOREIGN KEY (project_job_id) REFERENCES project_jobs(id) ON DELETE CASCADE
);
```

| カラム | 説明 |
|--------|------|
| project_job_id | 案件FK |
| name | 項目名（例：「序章初校作成」） |
| sort_order | 表示順 |

### 4-2. workflow_sheets（工程シート本体）

```sql
CREATE TABLE workflow_sheets (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_job_id  BIGINT UNSIGNED NOT NULL,
    template_id     BIGINT UNSIGNED NULL,
    name            VARCHAR(255) NOT NULL,
    stage_config    JSON NOT NULL,
    sort_order      INT NOT NULL DEFAULT 0,
    created_by      BIGINT UNSIGNED NOT NULL,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,
    FOREIGN KEY (project_job_id) REFERENCES project_jobs(id) ON DELETE CASCADE,
    FOREIGN KEY (template_id)    REFERENCES workflow_templates(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by)     REFERENCES users(id)
);
```

#### stage_config JSON仕様

```json
{
  "stages": [
    { "key": "shinko",   "label": "進行",  "type": "coordinator" },
    { "key": "kumihan",  "label": "組版",  "type": "worker" },
    { "key": "kosei",    "label": "校正",  "type": "worker" },
    { "key": "kosei2",   "label": "校正２","type": "worker" }
  ]
}
```

| フィールド | 説明 |
|-----------|------|
| key | ユニークなステージ識別子（英数字） |
| label | 表示名 |
| type | `"coordinator"` → Coordinator/Leader/Clerk のみ担当可能、`"worker"` → 全ユーザー担当可能 |

### 4-3. workflow_rows（行）

```sql
CREATE TABLE workflow_rows (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sheet_id        BIGINT UNSIGNED NOT NULL,
    label           VARCHAR(255) NOT NULL,
    sort_order      INT NOT NULL DEFAULT 0,
    item_entry_id   BIGINT UNSIGNED NULL,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,
    FOREIGN KEY (sheet_id)      REFERENCES workflow_sheets(id) ON DELETE CASCADE,
    FOREIGN KEY (item_entry_id) REFERENCES project_item_entries(id) ON DELETE SET NULL
);
```

| カラム | 説明 |
|--------|------|
| label | 行ラベル（編集可。項目リストからインポートした後に変更しても構わない） |
| item_entry_id | 項目リストエントリとの任意の紐付き（NULLでも可） |

### 4-4. workflow_cells（セル）

```sql
CREATE TABLE workflow_cells (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    row_id              BIGINT UNSIGNED NOT NULL,
    stage_key           VARCHAR(64) NOT NULL,
    assigned_user_id    BIGINT UNSIGNED NULL,
    assignment_id       BIGINT UNSIGNED NULL,
    work_date           DATE NULL,
    work_minutes        INT NULL,
    completed_at        TIMESTAMP NULL,
    cell_note           TEXT NULL,
    cell_note_user_id   BIGINT UNSIGNED NULL,
    created_at          TIMESTAMP NULL,
    updated_at          TIMESTAMP NULL,
    UNIQUE KEY uq_row_stage (row_id, stage_key),
    FOREIGN KEY (row_id)             REFERENCES workflow_rows(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_user_id)   REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (assignment_id)      REFERENCES project_job_assignments(id) ON DELETE SET NULL,
    FOREIGN KEY (cell_note_user_id)  REFERENCES users(id) ON DELETE SET NULL
);
```

| カラム | 説明 |
|--------|------|
| stage_key | ステージ識別子（stage_configのkeyと対応） |
| assigned_user_id | 担当者 |
| assignment_id | ジョブリンク（任意。ProjectJobAssignment FK） |
| work_date | 作業日 |
| work_minutes | 作業時間（分単位） |
| completed_at | 完了日時 |
| cell_note | メモ本文 |
| cell_note_user_id | メモ記入者 |

### 4-5. workflow_templates（ステージ構成テンプレート）

```sql
CREATE TABLE workflow_templates (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(255) NOT NULL,
    stage_config  JSON NOT NULL,
    created_by    BIGINT UNSIGNED NOT NULL,
    created_at    TIMESTAMP NULL,
    updated_at    TIMESTAMP NULL,
    FOREIGN KEY (created_by) REFERENCES users(id)
);
```

---

## 5. 権限設計

| 操作 | Coordinator | Clerk | Leader | User |
|------|:-----------:|:-----:|:------:|:----:|
| 項目リスト CRUD | ○ | ○ | ○ | — |
| 工程シート作成・削除 | ○ | ○ | — | — |
| 工程シート編集（行・ステージ） | ○ | ○ | — | — |
| セル担当者・日時・時間の更新 | ○ | ○ | △自分のみ | △自分のみ |
| セル完了マーク | ○ | ○ | △自分のみ | △自分のみ |
| 工程シート閲覧 | ○ | ○ | ○ | ○（担当セルあり案件） |
| テンプレート CRUD | ○ | ○ | — | — |

coordinator型ステージのセルへの担当者割り当て：**Coordinator / Leader / Clerk のみ選択可能**

---

## 6. API設計（ルート一覧）

すべて `routes/web.php` に追加。

### 6-1. 項目リスト（Coordinator/Clerk/Leader共通）

| Method | URI | Route名 | 説明 |
|--------|-----|---------|------|
| GET | `/coordinator/project_jobs/{job}/item-entries` | `item_entries.index` | 項目リスト一覧（ページ表示用） |
| PUT | `/coordinator/project_jobs/{job}/item-entries` | `item_entries.update` | 項目リスト一括保存 |
| GET | `/coordinator/project_jobs/{job}/item-entries/suggestions` | `item_entries.suggestions` | オートコンプリート用候補JSON返却 |

### 6-2. 工程シート（Coordinator）

| Method | URI | Route名 | 説明 |
|--------|-----|---------|------|
| POST | `/coordinator/project_jobs/{job}/workflow-sheets` | `workflow_sheets.store` | シート新規作成 |
| PUT | `/coordinator/project_jobs/{job}/workflow-sheets/reorder` | `workflow_sheets.reorder` | シート並び替え |
| GET | `/coordinator/workflow-sheets/{sheet}` | `workflow_sheets.show` | シート詳細（Inertia） |
| PUT | `/coordinator/workflow-sheets/{sheet}` | `workflow_sheets.update` | シート名・stage_config更新 |
| DELETE | `/coordinator/workflow-sheets/{sheet}` | `workflow_sheets.destroy` | シート削除 |
| POST | `/coordinator/workflow-sheets/{sheet}/rows` | `workflow_sheets.rows.store` | 行追加 |
| POST | `/coordinator/workflow-sheets/{sheet}/rows/import` | `workflow_sheets.rows.import` | 項目リストから一括インポート |
| PUT | `/coordinator/workflow-sheets/{sheet}/rows/{row}` | `workflow_sheets.rows.update` | 行編集 |
| DELETE | `/coordinator/workflow-sheets/{sheet}/rows/{row}` | `workflow_sheets.rows.destroy` | 行削除 |
| PUT | `/coordinator/workflow-sheets/{sheet}/rows/reorder` | `workflow_sheets.rows.reorder` | 行並び替え |
| PUT | `/coordinator/workflow-sheets/{sheet}/cells` | `workflow_sheets.cells.update` | セル一括更新 |

### 6-3. 工程シート（User）

| Method | URI | Route名 | 説明 |
|--------|-----|---------|------|
| GET | `/user/workflow-sheets/{sheet}` | `user.workflow_sheets.show` | シート閲覧（Inertia） |
| PUT | `/user/workflow-sheets/{sheet}/cells` | `user.workflow_sheets.cells.update` | 自分のセル更新 |
| POST | `/user/workflow-cells/{cell}/complete` | `user.workflow_cells.complete` | セル完了トグル |

### 6-4. テンプレート（Coordinator）

| Method | URI | Route名 | 説明 |
|--------|-----|---------|------|
| GET | `/coordinator/workflow-templates` | `workflow_templates.index` | テンプレート一覧 |
| POST | `/coordinator/workflow-templates` | `workflow_templates.store` | テンプレート作成 |
| PUT | `/coordinator/workflow-templates/{template}` | `workflow_templates.update` | テンプレート更新 |
| DELETE | `/coordinator/workflow-templates/{template}` | `workflow_templates.destroy` | テンプレート削除 |

---

## 7. フェーズ別タスク一覧

### Phase 0: DB マイグレーション

| # | タスク | 優先 |
|---|--------|------|
| W-00a | workflow_templates テーブル作成マイグレーション | 高 |
| W-00b | project_item_entries テーブル作成マイグレーション | 高 |
| W-00c | workflow_sheets テーブル作成マイグレーション | 高 |
| W-00d | workflow_rows テーブル作成マイグレーション | 高 |
| W-00e | workflow_cells テーブル作成マイグレーション | 高 |

### Phase 1: 項目リスト機能

| # | タスク |
|---|--------|
| W-01 | `ProjectItemEntry` モデル作成（リレーション含む） |
| W-02 | `Coordinator/ItemEntryController` 作成（index / update / suggestions） |
| W-03 | ルート追加（項目リスト3本） |
| W-04 | `ProjectJobs/Show.vue` に「項目リスト」タブ追加 |
| W-05 | `ItemListTab.vue` コンポーネント作成（テキストエリア入力 + 一覧表示） |

### Phase 2: オートコンプリート連携

| # | タスク |
|---|--------|
| W-06 | `AssignmentForm.vue` にジョブ名オートコンプリート追加 |
| W-07 | 台割（ProjectJobItem 作成フォーム）の項目名欄にオートコンプリート追加 |
| W-08 | マイジョブ作成フォームにオートコンプリート追加（案件選択後に候補取得） |

### Phase 3: 工程シート基本機能（バックエンド）

| # | タスク |
|---|--------|
| W-09 | `WorkflowSheet` / `WorkflowRow` / `WorkflowCell` モデル作成 |
| W-10 | `Coordinator/WorkflowSheetController` 作成（show / store / update / destroy / reorder） |
| W-11 | `Coordinator/WorkflowRowController` 作成（store / update / destroy / reorder / import） |
| W-12 | `Coordinator/WorkflowCellController` 作成（bulkUpdate） |
| W-13 | `User/WorkflowSheetController` 作成（show） |
| W-14 | `User/WorkflowCellController` 作成（update / complete） |
| W-15 | ルート追加（工程シート・User側） |

### Phase 4: 工程シート UI

| # | タスク |
|---|--------|
| W-16 | `ProjectJobs/Show.vue` に「工程シート」タブ追加 |
| W-17 | `Coordinator/WorkflowSheets/Show.vue` 作成（メイン編集画面） |
| W-18 | `User/WorkflowSheets/Show.vue` 作成（作業者閲覧・セル更新） |

### Phase 5: テンプレート機能

| # | タスク |
|---|--------|
| W-19 | `WorkflowTemplate` モデル作成 |
| W-20 | `Coordinator/WorkflowTemplateController` 作成 |
| W-21 | ルート追加（テンプレート4本） |
| W-22 | `Coordinator/WorkflowTemplates/Index.vue` 作成 |
| W-23 | シート新規作成ダイアログにテンプレート選択 UI 追加 |

### Phase 6: 時間集計表示

| # | タスク |
|---|--------|
| W-24 | ステージ別小計・行合計・シート合計の計算ロジック（Vue computed） |
| W-25 | 集計行を工程シートUIの末尾に追加 |

### Phase 7: 案件複製対応

| # | タスク |
|---|--------|
| W-26 | `ProjectJobController::clone()` に WorkflowSheet 複製処理を追加（行のみ。セルデータは除外） |
| W-27 | 同じく `project_item_entries` の複製を追加 |

### Phase 8: PDF/印刷（後で詳細設計）

| # | タスク |
|---|--------|
| W-28 | 印刷用ビュー / PDF出力（ISO基準フォーマットは別途設計） |

---

## 8. 変更ファイル一覧

### 新規作成

| ファイル | 種別 |
|----------|------|
| `database/migrations/xxxx_create_workflow_templates_table.php` | Migration |
| `database/migrations/xxxx_create_project_item_entries_table.php` | Migration |
| `database/migrations/xxxx_create_workflow_sheets_table.php` | Migration |
| `database/migrations/xxxx_create_workflow_rows_table.php` | Migration |
| `database/migrations/xxxx_create_workflow_cells_table.php` | Migration |
| `app/Models/ProjectItemEntry.php` | Model |
| `app/Models/WorkflowSheet.php` | Model |
| `app/Models/WorkflowRow.php` | Model |
| `app/Models/WorkflowCell.php` | Model |
| `app/Models/WorkflowTemplate.php` | Model |
| `app/Http/Controllers/Coordinator/ItemEntryController.php` | Controller |
| `app/Http/Controllers/Coordinator/WorkflowSheetController.php` | Controller |
| `app/Http/Controllers/Coordinator/WorkflowRowController.php` | Controller |
| `app/Http/Controllers/Coordinator/WorkflowCellController.php` | Controller |
| `app/Http/Controllers/Coordinator/WorkflowTemplateController.php` | Controller |
| `app/Http/Controllers/User/WorkflowSheetController.php` | Controller |
| `app/Http/Controllers/User/WorkflowCellController.php` | Controller |
| `resources/js/Pages/Coordinator/WorkflowSheets/Show.vue` | Vue Page |
| `resources/js/Pages/Coordinator/WorkflowTemplates/Index.vue` | Vue Page |
| `resources/js/Pages/User/WorkflowSheets/Show.vue` | Vue Page |
| `resources/js/Components/ItemListTab.vue` | Vue Component |

### 変更あり

| ファイル | 変更内容 |
|----------|----------|
| `routes/web.php` | 新ルート追加（項目リスト・工程シート・テンプレート） |
| `app/Models/ProjectJob.php` | `hasMany(WorkflowSheet)` / `hasMany(ProjectItemEntry)` リレーション追加 |
| `app/Http/Controllers/Coordinator/ProjectJobController.php` | `clone()` に WorkflowSheet・ProjectItemEntry 複製処理追加 |
| `resources/js/Pages/Coordinator/ProjectJobs/Show.vue` | タブに「項目リスト」「工程シート」追加 |
| `resources/js/Components/AssignmentForm.vue` | ジョブ名フィールドにオートコンプリート追加 |
| マイジョブ作成フォーム（要確認） | オートコンプリート追加 |
| 台割項目名フォーム（要確認） | オートコンプリート追加 |

---

## 9. 未決事項

| # | 項目 | 優先度 |
|---|------|--------|
| U-01 | PDF/印刷フォーマットの詳細設計（用紙・レイアウト・ISO基準内容） | Phase 8 で設計 |
| U-02 | マイジョブ作成フォームの具体的なファイルパス確認（User側のどのVueファイルか） | Phase 2 着手前 |
| U-03 | 台割（ProjectJobItem）の項目名入力フォームの具体的なファイルパス確認 | Phase 2 着手前 |
| U-04 | WorkflowSheet の共有トークン（share_token）機能を進行表と同様に持つか | Phase 3 前に確認 |
| U-05 | Leaderロールが工程シートのセルを自分以外の担当で更新できるか | Phase 3 前に確認 |
