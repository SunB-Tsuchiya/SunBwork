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
