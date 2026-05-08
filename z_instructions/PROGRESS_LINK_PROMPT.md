# 進行表×カレンダー連携 引き継ぎプロンプト

## セッション開始時にすること

1. `z_instructions/PROGRESS_LINK_MANAGER.md` を読む（進捗一覧の確認）
2. `z_instructions/PROGRESS_LINK_PLAN.md` を読む（設計書）
3. 現在の進捗を確認し、ユーザーに次の推奨作業を提示して待つ

---

## このタスクの目的

進行表（ProgressSheet）の行・列とカレンダーのスケジュール（ProjectSchedule）を直接紐づけ、
セル完了時に進捗率を自動計算してカレンダーに反映する。

---

## 設計の核心（必ず理解すること）

### データモデル

```
progress_cells
  row_id → ProgressRow（行）
  col_key → column_config のキー（列）
  cell_type: 'worker' | 'schedlink' | 'proof_v2'（完了可能な型）
  completed_at: 完了日時

project_job_items（連携設定マスター）
  type: 'row' | 'column'
  row_id: 行リンク時の ProgressRow.id
  col_key: 列リンク時の column_config キー（親グループキーでもリーフキーでも可）
  linked_schedule_id: ★新規追加カラム★ → ProjectSchedule.id

project_schedules
  progress: 進捗率（0〜100）
  completed_at: 完了日時（NULLなら未完了）
  → カレンダーでグレー表示・バッジ表示に使用
```

### 進捗計算ロジック

**行リンク（type='row'）:**
- 対象行のすべての完了可能セル（worker/schedlink/proof_v2）をカウント
- `progress = done / total * 100`

**列リンク（type='column'）:**
- `col_key` が親グループキーの場合 → その配下のすべてのリーフキーが対象
- `col_key` がリーフキーの場合 → そのキーのみが対象
- 全行 × 対象列のセルを集計

### フックを追加する場所

セルが完了するたびに `ProgressLinkService::recalculate($cell)` を呼ぶ：
- `app/Http/Controllers/Coordinator/ProgressCellController::complete()`
- `app/Http/Controllers/User/ProgressCellController::complete()`

---

## column_config の構造（重要）

```json
[
  {
    "key": "shoko",
    "label": "初校",
    "children": [
      { "key": "shoko_kumihan", "label": "組版", "children": [] },
      { "key": "shoko_kosei",   "label": "校正", "children": [] }
    ]
  },
  {
    "key": "saiko",
    "label": "再校",
    "children": [
      { "key": "saiko_kumihan", "label": "組版", "children": [] },
      { "key": "saiko_kosei",   "label": "校正", "children": [] }
    ]
  }
]
```

- `col_key = "shoko"` でリンクした場合 → "shoko_kumihan" と "shoko_kosei" の両方が集計対象
- `col_key = "shoko_kumihan"` でリンクした場合 → "shoko_kumihan" のみが集計対象

---

## 連携設定UI のユーザー操作フロー

```
案件詳細 → 「連携設定」タブ
  ↓
進行表「〇〇進行表」の連携設定が展開される
  ↓
「編集」ボタン → 各行/列にスケジュールセレクタが表示される
  ↓
行「学校1」→ スケジュール「学校1問題」を選択
列「初校（まとめ）」→ スケジュール「初校作業」を選択
列「初校・組版」→ スケジュール「初校組版完了」を選択（任意）
  ↓
「保存」→ project_job_items.linked_schedule_id に保存される
  ↓
以降、セル完了のたびに自動で進捗再計算 → カレンダーに反映
```

---

## 実装ファイル一覧

| ファイル | 新規/変更 | 内容 |
|---------|---------|------|
| `database/migrations/2026_05_06_000001_add_linked_schedule_id_to_project_job_items.php` | 新規 | linked_schedule_id 追加 |
| `app/Services/ProgressLinkService.php` | 新規 | 進捗再計算サービス |
| `app/Http/Controllers/Coordinator/ProgressCellController.php` | 変更 | complete() にフック追加 |
| `app/Http/Controllers/User/ProgressCellController.php` | 変更 | complete() にフック追加 |
| `app/Http/Controllers/Coordinator/ProjectSchedulesController.php` | 変更 | uncomplete() に progress=0 追加 |
| `app/Http/Controllers/Coordinator/ProgressSheetItemController.php` | 変更 | schedules prop / linked_schedule_id 対応 |
| `resources/js/Components/ProjectJobItemsTab.vue` | 変更 | スケジュールセレクタUIに刷新 |
| `resources/js/Components/ProjectCalendar.vue` | 確認・軽微修正 | progress 表示確認 |

---

## 注意事項

- さくら本番に `php artisan migrate` を必ず実行すること
- ProgressLinkService の計算は O(n) で実行される。セル数が多い場合の性能に注意
- `uncomplete` はスケジュール側から行うが、個別セルの `completed_at` はリセットしない（現行動作を維持）
- `project_schedules.project_job_item_id`（既存カラム）は schedlink セル連携で引き続き使用。削除しないこと
