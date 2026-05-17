# WORKFLOW_V2_PLAN1.md — 工程シートV2 + 進行表時間表示 設計書

作成日: 2026-05-14
関連: WORKFLOW_V2_MANAGER1.md / WORKFLOW_V21_PROMPT.md

---

## 概要

### 変更1: 工程シート — worker型セルへの刷新
- 現状: 担当者セレクタ + 日付・時間手動入力 + 保存ボタン
- 変更後: 進行表の `worker` 型と同一フロー
  - 担当者を選択 → 「＋ 登録」クリック → `ProjectJobAssignment` を自動作成 → セルに紐づけ
  - 登録後は担当者名がロック・「完了にする」ボタン
  - 完了後は緑背景・完了日・作業時間（カレンダーイベントから自動算出）

### 変更2: 作業時間の自動算出（工程シート + 進行表）
- **ソース**: `events` テーブル（`TIMESTAMPDIFF(MINUTE, starts_at, ends_at) - interruption_minutes`）
- `project_job_assignment_id` でリレーション
- 両シートの `show()` で一括バッチ集計して `work_minutes` フィールドとして返す
- 工程シートの手動 `work_minutes` / `work_date` 入力UIは廃止

### 変更3: 時間小計・合計表示（両シート）
- 工程シート: 既存の小計行を events 算出値に切り替え（フロントは変更なし）
- 進行表: 新たに「作業時間」合計行を追加（worker/joblink型列のみ集計）

### 変更4: 工程シート — 行グループ化
- `workflow_rows` に `parent_id` カラム追加
- 親行（グループヘッダー）: ラベルのみ表示・セルなし・灰色背景
- 子行: インデント表示・通常のセルあり
- 行追加モーダルに「グループ」チェックボックスと「親項目」セレクタを追加

---

## DB 変更

### workflow_rows: parent_id 追加

```sql
ALTER TABLE workflow_rows
  ADD COLUMN parent_id BIGINT UNSIGNED NULL AFTER sheet_id;
ALTER TABLE workflow_rows
  ADD CONSTRAINT fk_workflow_rows_parent
    FOREIGN KEY (parent_id) REFERENCES workflow_rows(id) ON DELETE SET NULL;
```

---

## 新規 API

| メソッド | URL | ルート名 | 説明 |
|---------|-----|---------|------|
| POST | coordinator/workflow-sheets/{sheet}/cells/register | coordinator.workflow_sheets.cells.register | 担当者登録→Assignment作成 |
| POST | coordinator/workflow-cells/{cell}/complete | coordinator.workflow_cells.complete | 完了トグル（Coordinator用） |
| POST | coordinator/workflow-cells/{cell}/unregister | coordinator.workflow_cells.unregister | 登録解除 |

### WorkflowCellController::register()

**入力:**
```json
{ "row_id": 1, "stage_key": "kumihan", "user_id": 5, "desired_end_date": "2026-05-20" }
```

**処理:**
1. row が sheet に属するか検証
2. stage_key が stage_config に存在するか検証
3. `ProjectJobAssignment::create()` でジョブ作成（sender_id = 認証ユーザー）
4. `WorkflowCell::updateOrCreate(['row_id','stage_key'], [...])` で更新
5. events から work_minutes 算出（新規なので 0）
6. 更新済みセルデータを返す

### WorkflowCellController::complete()
- 完了トグル: `completed_at` あり → null に / なし → `now()` にセット
- リンク先 Assignment の `completed` フラグも同期
- events から `work_minutes` を再算出して返す

### WorkflowCellController::unregister()
- `assignment_id` を null にする（Assignment レコードは削除しない）
- `completed_at` も null にする
- `assigned_user_id` は保持（再選択可能状態）

### 作業時間バッチ算出（WorkflowSheetController & ProgressSheetController で使用）

```php
$assignmentIds = /* cells から assignment_id を収集 */;
$eventMinutes = DB::table('events')
    ->whereIn('project_job_assignment_id', $assignmentIds)
    ->whereNotNull('ends_at')
    ->selectRaw('project_job_assignment_id,
        COALESCE(SUM(TIMESTAMPDIFF(MINUTE, starts_at, ends_at)
            - COALESCE(interruption_minutes, 0)), 0) as total')
    ->groupBy('project_job_assignment_id')
    ->pluck('total', 'project_job_assignment_id')
    ->toArray();
// cell.work_minutes = $eventMinutes[$cell->assignment_id] ?? 0
```

---

## WorkflowCellEditor.vue 刷新仕様

### Props
```
cell:          Object (nullable) — {assigned_user_id, assignment_id, completed_at, work_minutes, cell_note}
stage:         Object {key, label, type}
workerUsers:   Array [{id, name}]
canEdit:       Boolean — Coordinator は全セル / User は自分が担当のセルのみ
isCoordinator: Boolean — register/unregister ボタン表示制御
```

### Emits
```
@register({ user_id })         — Coordinator のみ
@complete({ cell_id? })        — Coordinator + User（完了トグル）
@unregister({ cell_id })       — Coordinator のみ
```

### 表示状態マシン

| 状態 | 条件 | 左エリア | 右エリア |
|------|------|---------|---------|
| `empty` | user_id=null, assignment_id=null | セレクタ(canEditのみ) | ┄ 未登録 ┄ |
| `selected` | ローカル選択済み, assignment_id=null | 選択した名前 | ＋ 登録 / 取消 |
| `registered` | assignment_id あり, completed_at=null | 🔒 名前 (+ 作業時間) | 完了にする / 解除 |
| `completed` | completed_at あり | ✓ 名前 + 完了日 + 作業時間 | ✓ 完了 / 取り消す |

### 色分け

| 状態 | CSS |
|------|-----|
| completed | `bg-green-50` + `border-l-4 border-green-400` |
| registered | デフォルト |
| empty/selected | デフォルト |

---

## ProgressCell.vue 変更（worker型）

左エリアの登録済み/完了済み表示に追加:

```html
<span v-if="cell.work_minutes" class="text-xs text-gray-500">
  作業: {{ formatMinutes(cell.work_minutes) }}
</span>
```

`cell.work_minutes` は `ProgressSheetController::show()` が events から算出して返す。

---

## ProgressSheets/Show.vue 変更

tbody 末尾に「作業時間」集計行を追加:

```
| 作業時間 | — | Xh Ym | — | Xh Ym | 合計: Xh Ym |
```

- worker / joblink 型リーフ列のみ集計（`column_config` を走査して対象列キーを取得）
- Vue `computed` で算出

---

## 変更ファイル一覧

| # | ファイル | 種別 |
|---|---------|------|
| W2-01 | database/migrations/2026_05_14_300001_add_parent_id_to_workflow_rows_table.php | 新規 |
| W2-02 | app/Models/WorkflowRow.php | 修正（parent/childrenリレーション追加） |
| W2-03 | app/Http/Controllers/Coordinator/WorkflowCellController.php | 修正（register/complete/unregister追加） |
| W2-04 | app/Http/Controllers/Coordinator/WorkflowSheetController.php | 修正（work_minutes算出追加） |
| W2-05 | app/Http/Controllers/User/WorkflowCellController.php | 修正（work_minutes を返すよう更新） |
| W2-06 | app/Http/Controllers/User/WorkflowSheetController.php | 修正（work_minutes算出追加） |
| W2-07 | app/Http/Controllers/Coordinator/ProgressSheetController.php | 修正（work_minutes算出追加） |
| W2-08 | routes/web.php | 修正（3ルート追加） |
| W2-09 | resources/js/Components/WorkflowCellEditor.vue | 修正（worker型に刷新） |
| W2-10 | resources/js/Pages/Coordinator/WorkflowSheets/Show.vue | 修正（行グループ化・新セルAPI対応） |
| W2-11 | resources/js/Pages/User/WorkflowSheets/Show.vue | 修正（worker型対応） |
| W2-12 | resources/js/Components/ProgressCell.vue | 修正（work_minutes表示追加） |
| W2-13 | resources/js/Pages/Coordinator/ProgressSheets/Show.vue | 修正（時間集計行追加） |

---

## 未決事項

| # | 項目 | 状態 |
|---|------|------|
| U-01 | 進行表 User版にも時間小計を追加するか | ⚠️ 保留 |
| U-02 | 工程シート行グループのUI配置（行追加モーダルに統合 or 別ボタン） | ⚠️ 保留 |

---

## 管理シート V3 追加仕様（2026-05-17）

### 背景

「工程シート」を「管理シート」に名称変更し、進行管理表と同等の機能（一覧・編集モード・
テンプレート・印刷・共有リンク・全セル型対応）を追加する。

### 確定事項

| 項目 | 決定内容 |
|------|---------|
| 名称 | 「工程シート」→「管理シート」（URLは workflow-sheets のまま） |
| 列構成 | stage_config 廃止 → column_config（進行表と同形式）に完全移行 |
| 既存データ | ローカルテスト用のみ。自動変換（option C） |
| テンプレート | progress_templates を共有 + sheet_type カラムで区別 |
| ステージ | stages テーブルからセレクター / assignment.stage_id に連動 |
| 項目名 | 自由入力 or 案件に紐づく item_entries から選択 |
| ジョブ完了連携 | JobBoxController → WorkflowCell.completed_at 自動更新追加 |

---

### DB 変更

#### workflow_sheets: column_config + share_token 追加

```sql
ALTER TABLE workflow_sheets
  ADD COLUMN column_config JSON NULL AFTER stage_config,
  ADD COLUMN share_token VARCHAR(64) NULL UNIQUE AFTER column_config;
```

**stage_config → column_config 変換ルール（マイグレーション内で実行）:**
- `{ stages: [...] }` のラッパーを外し配列直値にする
- type: `coordinator` → `worker` / `proof_worker` → `proof_v2` / それ以外はそのまま
- key / label は保持

#### workflow_rows: stage_id 追加

```sql
ALTER TABLE workflow_rows
  ADD COLUMN stage_id BIGINT UNSIGNED NULL AFTER item_entry_id,
  ADD CONSTRAINT fk_workflow_rows_stage
    FOREIGN KEY (stage_id) REFERENCES stages(id) ON DELETE SET NULL;
```

グループヘッダー行（parent_id = NULL）のみが stage_id を持つ。

#### coordinator_workflow_sheet_favorites: 新規

```sql
CREATE TABLE coordinator_workflow_sheet_favorites (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  workflow_sheet_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  UNIQUE KEY uq_wf_fav (user_id, workflow_sheet_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (workflow_sheet_id) REFERENCES workflow_sheets(id) ON DELETE CASCADE
);
```

#### progress_templates: sheet_type 追加

```sql
ALTER TABLE progress_templates
  ADD COLUMN sheet_type VARCHAR(32) NULL DEFAULT NULL;
```

値: `'progress'` / `'management'` / NULL（両方対応）

#### workflow_cells: ProgressCell 互換フィールド追加

```sql
ALTER TABLE workflow_cells
  ADD COLUMN cell_type VARCHAR(32) NOT NULL DEFAULT 'worker' AFTER stage_key,
  ADD COLUMN value_text TEXT NULL,
  ADD COLUMN value_date DATE NULL,
  ADD COLUMN value_bool TINYINT(1) NULL,
  ADD COLUMN value_user_id BIGINT UNSIGNED NULL,
  ADD COLUMN value_subcontractor_id BIGINT UNSIGNED NULL,
  ADD COLUMN proof_assignment_id BIGINT UNSIGNED NULL,
  ADD COLUMN schedule_id BIGINT UNSIGNED NULL,
  ADD COLUMN cell_deadline DATE NULL;
```

**既存データ変換（マイグレーション内）:**
```sql
UPDATE workflow_cells SET value_user_id = assigned_user_id WHERE assigned_user_id IS NOT NULL;
```

`assigned_user_id` は後方互換のため残す（新コードは `value_user_id` を使う）。

---

### グループヘッダー行の表示仕様

```
┌──────────────────────────────────────────────────────────────────────┐
│ HEADER: [📝 序章 or (項目リストから選択▼)]  [ステージ: 初校▼]      │
├──────────────────┬─────────────────┬──────────────────────────────────┤
│ [進行 worker]    │ [組版 worker]   │ [校正 proof_v2]                  │
└──────────────────┴─────────────────┴──────────────────────────────────┘
```

- **親行** (parent_id = NULL): グループヘッダー。全列 span、左=項目名、右=stageセレクター
- **子行** (parent_id != NULL): ProgressCell.vue を使い各列のセルを描画

---

### WorkflowCellController::register() 更新仕様

ジョブ登録時、親行の stage_id を assignment に連動:

```php
$parentRow = $row->parent_id
    ? WorkflowRow::find($row->parent_id)
    : $row;

$assignment = ProjectJobAssignment::create([
    'project_job_id'   => $projectJob->id,
    'user_id'          => $validated['user_id'],
    'sender_id'        => $request->user()->id,
    'title'            => $title,
    'assigned'         => true,
    'desired_end_date' => $validated['desired_end_date'] ?? null,
    'stage_id'         => $parentRow?->stage_id,  // ← 追加
]);
```

---

### テンプレート仕様

**登録 (WorkflowSheetController::registerAsTemplate):**
```php
ProgressTemplate::create([
    'name'          => $name,
    'column_config' => $sheet->column_config,
    'sheet_type'    => 'management',
    'created_by'    => $user->id,
    'is_shared'     => false,
]);
```

**一覧取得 (管理シート用):**
```php
ProgressTemplate::where(function ($q) use ($userId) {
    $q->where('is_shared', true)->orWhere('created_by', $userId);
})->where(function ($q) {
    $q->whereNull('sheet_type')->orWhere('sheet_type', 'management');
})->orderByDesc('updated_at')->get(['id', 'name']);
```

---

### JobBoxController 更新

`completeAssignment()` に WorkflowCell 自動完了を追加:

```php
// ProgressCell 更新の直後に追加
try {
    \App\Models\WorkflowCell::where('assignment_id', $assignment->id)
        ->whereNull('completed_at')
        ->update(['completed_at' => now()]);
} catch (\Throwable $__e) {
    // non-fatal
}
```

---

### 新規 API ルート

| メソッド | URL | ルート名 | 説明 |
|---------|-----|---------|------|
| GET | coordinator/workflow-sheet-list | coordinator.workflow_sheet_list.index | 管理シート一覧 |
| GET | coordinator/workflow-sheet-list/create-projects-json | coordinator.workflow_sheet_list.create_projects_json | 新規作成モーダル用案件JSON |
| POST | coordinator/workflow-sheet-list/favorite/{sheet} | coordinator.workflow_sheet_list.favorite | お気に入りトグル |
| POST | coordinator/workflow-sheets/{sheet}/register-template | coordinator.workflow_sheets.register_template | テンプレート登録 |
| POST | coordinator/workflow-sheets/{sheet}/share | coordinator.workflow_sheets.share | 共有トークン発行 |
| DELETE | coordinator/workflow-sheets/{sheet}/share | coordinator.workflow_sheets.unshare | 共有トークン無効化 |
| GET | coordinator/workflow-sheets/{sheet}/print | coordinator.workflow_sheets.print | 印刷ページ |
| GET | shared/workflow-sheets/{token} | shared.workflow_sheets.show | 共有閲覧（認証不要） |

---

### 変更ファイル一覧（V3）

| # | ファイル | 種別 |
|---|---------|------|
| WM3-01 | database/migrations/2026_05_17_100001_add_column_config_to_workflow_sheets.php | 新規 |
| WM3-02 | database/migrations/2026_05_17_100002_add_share_token_to_workflow_sheets.php | 新規 |
| WM3-03 | database/migrations/2026_05_17_100003_add_stage_id_to_workflow_rows.php | 新規 |
| WM3-04 | database/migrations/2026_05_17_100004_create_coordinator_workflow_sheet_favorites_table.php | 新規 |
| WM3-05 | database/migrations/2026_05_17_100005_add_sheet_type_to_progress_templates.php | 新規 |
| WM3-06 | database/migrations/2026_05_17_100006_expand_workflow_cells.php | 新規 |
| WM3-07 | app/Models/WorkflowSheet.php | 修正 |
| WM3-08 | app/Models/WorkflowRow.php | 修正（stage リレーション追加） |
| WM3-09 | app/Models/CoordinatorWorkflowSheetFavorite.php | 新規 |
| WM3-10 | app/Http/Controllers/Coordinator/WorkflowSheetController.php | 修正（show 更新・registerAsTemplate・printView・share/unshare 追加） |
| WM3-11 | app/Http/Controllers/Coordinator/WorkflowCellController.php | 修正（stage_id 連動・全セル型対応） |
| WM3-12 | app/Http/Controllers/Coordinator/WorkflowSheetListController.php | 新規 |
| WM3-13 | app/Http/Controllers/ProjectJobs/JobBoxController.php | 修正（WorkflowCell 自動完了追加） |
| WM3-14 | routes/web.php | 修正（8ルート追加） |
| WM3-15 | resources/js/Components/Tabs/CoordinatorNavigationTabs.vue | 修正（タブ追加） |
| WM3-16 | resources/js/Pages/Coordinator/WorkflowSheetList/Index.vue | 新規 |
| WM3-17 | resources/js/Pages/Coordinator/WorkflowSheets/Show.vue | 修正（大規模・column_config 対応・グループヘッダー行） |
| WM3-18 | resources/js/Components/WorkflowCellEditor.vue | 修正（stage_id 対応） |
| WM3-19 | resources/js/Pages/User/WorkflowSheets/Show.vue | 修正（column_config 対応） |
| WM3-20 | resources/js/Pages/Coordinator/WorkflowSheets/Print.vue | 新規 |
| WM3-21 | resources/js/Pages/Shared/WorkflowSheets/Show.vue | 新規 |
| WM3-22 | npm run build | ビルド |
| WM3-23 | php artisan migrate | 実行 |

---

## 未決事項（V3追加）

| # | 項目 | 状態 |
|---|------|------|
| U-03 | 管理シート行追加モーダル: グループヘッダー行追加と作業行追加のUI分離方法 | ⚠️ 実装後に手直し |
| U-04 | User版管理シートの詳細UI（column_config 対応の全セル型表示） | ⚠️ 実装後に手直し |
| U-05 | workflow_templates テーブルの廃止タイミング（本番デプロイ後に削除） | ⚠️ 保留 |

---

## 当初プランからの変更・追加修正（2026-05-17 セッション記録）

### BUG-01: handleCellUpdate — worker 型セルの保存が機能しない
**ファイル:** `resources/js/Pages/Coordinator/WorkflowSheets/Show.vue`

**問題:** `handleCellUpdate` に `value_type === 'worker'` の処理がなかった。
担当者セレクタで値を選択しても API に送信されず、セルが元に戻っていた。

**修正内容:**
```js
else if (value_type === 'worker') {
    if (subcontractor_id != null) {
        payload.cells[0].value_subcontractor_id = subcontractor_id;
        payload.cells[0].value_user_id          = null;
    } else {
        payload.cells[0].value_user_id          = value;
        payload.cells[0].value_subcontractor_id = null;
    }
}
```

---

### BUG-02: handleWorkerJobRegister — 常に自己アサインルートに遷移していた
**ファイル:** `resources/js/Pages/Coordinator/WorkflowSheets/Show.vue`

**問題:** BUG-01 の影響で担当者が常に null→自分自身と判定され、
`events.create_job`（自己アサイン）ルートへ遷移していた。

**修正内容:**
```js
const isSelf = !userId || String(userId) === String(authUserId.value);
if (isSelf) {
    router.visit(route('events.create_job', params));
} else {
    params.user_id = userId;
    router.visit(route('coordinator.project_jobs.assignments.create', {...}));
}
```

BUG-01 修正後、別ユーザーを選択した場合は coordinator アサインフォームへ正しく遷移するようになった。

---

### BUG-03: getLeafPath() — item_label を無視していたためタイトルが重複
**ファイル:** `resources/js/Pages/Coordinator/WorkflowSheets/Show.vue`

**問題:** `getLeafPath()` が `node.label` のみ参照していた。
縦積みレイアウトで2つのステージが同じ `label: "初校"` を持つ場合、
後続行（item_label: "学校B初校"）の組版ジョブタイトルが
`"〇〇書籍 本文8_初校_組版"` となり、1行目と同一タイトルになっていた。

**修正内容:**
```js
function getLeafPath(leafKey, nodes = localColumnConfig.value, path = []) {
    for (const node of nodes) {
        // item_label がある場合は [item_label, label] の両方をパスに含める
        const nodeLabels = node.item_label ? [node.item_label, node.label] : [node.label];
        const newPath = [...path, ...nodeLabels];
        ...
    }
}
```

**修正後のタイトル例:**
- 初校/組版 → `〇〇書籍 本文8_初校_組版`（変わらず）
- 学校B初校/組版 → `〇〇書籍 本文8_学校B初校_初校_組版`（修正）

---

### BUG-04: JobBoxController::global() — whereNotExists が後発 coordinator ジョブを非表示にしていた
**ファイル:** `app/Http/Controllers/ProjectJobs/JobBoxController.php`

**問題:** `global()` の `whereNotExists` フォールバック条件が
`pja_self.id <> project_job_assignments.id`（異なるID）だったため、
ユーザーが古い coordinator ジョブを自己登録すると、
それ以降に発行された同タイトルの新しい coordinator ジョブまで非表示になっていた。

例:
- assignment 226（coordinator 発信）→ user が自己登録 → assignment 227（supersedes=226）
- assignment 228（coordinator が再発信、同タイトル）→ 227 のタイトル一致で 228 も非表示に

**修正内容:** フォールバック条件を「自己割当が coordinator アサインより新しい場合のみ」に限定

```php
// 修正前
->whereColumn('pja_self.id', '<>', 'project_job_assignments.id')
// 修正後
->whereColumn('pja_self.id', '>', 'project_job_assignments.id')
```

---

### ADD-01: 縦積みレイアウト（stageRows / useVerticalLayout）の追加
**ファイル:** `resources/js/Pages/Coordinator/WorkflowSheets/Show.vue`

`column_config` のトップレベルに `type: 'stage'` ノードが存在する場合、
縦方向（行 = ステージ、列 = 作業工程）に表示する縦積みレイアウトを追加。

```js
const stageRows = computed(() =>
    localColumnConfig.value.filter(n => n.type === 'stage' && n.children?.length > 0)
);
const useVerticalLayout = computed(() => stageRows.value.length > 0);
```

テンプレートに `v-else-if="useVerticalLayout"` ブロックを追加し、
各 stageRow を `<tr>` として表示（item_label + label をヘッダーに、子列をセルに）。
