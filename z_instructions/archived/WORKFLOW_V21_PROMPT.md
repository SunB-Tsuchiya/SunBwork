# WORKFLOW_V21_PROMPT.md — 新セッション開始用プロンプト

このファイルをそのままメッセージに貼り付けて新セッションを開始してください。

---

## プロジェクト引き継ぎ

SunBWork（Laravel 11 + Vue 3 + Inertia.js）の工程シートV2と進行表時間表示機能の実装を継続しています。

### 実装対象
1. **工程シート（WorkflowSheet）**: 担当者選択 + 登録ボタン（worker型セル）への刷新
2. **工程シート + 進行表**: カレンダーイベントから作業時間を自動算出して表示
3. **進行表**: 時間小計・合計行の追加
4. **工程シート**: 行グループ化（parent_id）

### 設計書
- `z_instructions/WORKFLOW_V2_PLAN1.md` — 詳細仕様
- `z_instructions/WORKFLOW_V2_MANAGER1.md` — 進捗管理テーブル

### 作業時間の取得元
```sql
-- 1件の assignment に対する作業時間
SELECT COALESCE(SUM(
  TIMESTAMPDIFF(MINUTE, starts_at, ends_at) - COALESCE(interruption_minutes, 0)
), 0)
FROM events
WHERE project_job_assignment_id = ?
AND ends_at IS NOT NULL
```

### 主要ファイル
- `app/Http/Controllers/Coordinator/WorkflowCellController.php` — register/complete/unregister 追加予定
- `app/Http/Controllers/Coordinator/WorkflowSheetController.php` — show() に work_minutes 追加予定
- `resources/js/Components/WorkflowCellEditor.vue` — worker型に刷新予定
- `resources/js/Pages/Coordinator/WorkflowSheets/Show.vue` — 行グループ化対応予定
- `resources/js/Components/ProgressCell.vue` — worker型に work_minutes 小表示追加予定
- `resources/js/Pages/Coordinator/ProgressSheets/Show.vue` — 時間集計行追加予定

### 新規ルート（未追加）
```php
Route::post('workflow-sheets/{sheet}/cells/register', [WorkflowCellController::class, 'register'])
    ->name('workflow_sheets.cells.register');
Route::post('workflow-cells/{cell}/complete', [WorkflowCellController::class, 'complete'])
    ->name('workflow_cells.complete');
Route::post('workflow-cells/{cell}/unregister', [WorkflowCellController::class, 'unregister'])
    ->name('workflow_cells.unregister');
```

### 作業開始手順
1. `z_instructions/WORKFLOW_V2_MANAGER1.md` で進捗確認
2. 未着手タスクから順に実装
3. npm run build は毎回最後に実行
4. Artisan は `docker compose exec laravel bash -lc "php artisan ..."` で実行
