# WORKFLOW_V2_MANAGER1.md — 工程シートV2 + 進行表時間表示 進捗管理

作成日: 2026-05-14
関連: WORKFLOW_V2_PLAN1.md / WORKFLOW_V21_PROMPT.md

---

## 作業フロー

```
Phase A（DB）→ Phase B（バックエンド工程シート）→ Phase C（バックエンド進行表）
→ Phase D（フロントエンド工程シート）→ Phase E（フロントエンド進行表）→ Phase F（ビルド）
```

---

## 進捗一覧テーブル

### Phase A: DB マイグレーション

| # | タスク | 状態 | 備考 |
|---|--------|------|------|
| W2-01 | workflow_rows に parent_id カラム追加 | ✅ 完了 | ON DELETE SET NULL |

### Phase B: バックエンド — 工程シート

| # | タスク | 状態 | 備考 |
|---|--------|------|------|
| W2-02 | WorkflowRow モデル: parent/children リレーション追加 | ✅ 完了 | |
| W2-03 | WorkflowCellController: register/complete/unregister 追加 | ✅ 完了 | |
| W2-04 | WorkflowSheetController::show(): work_minutes をeventsから算出 | ✅ 完了 | バッチ集計 |
| W2-05 | User/WorkflowCellController: work_minutes を返すよう更新 | ✅ 完了 | |
| W2-06 | User/WorkflowSheetController::show(): work_minutes 算出追加 | ✅ 完了 | |
| W2-08 | routes/web.php: 3ルート追加 | ✅ 完了 | |

### Phase C: バックエンド — 進行表

| # | タスク | 状態 | 備考 |
|---|--------|------|------|
| W2-07 | ProgressSheetController::show(): work_minutes 算出追加 | ✅ 完了 | バッチ集計 |

### Phase D: フロントエンド — 工程シート

| # | タスク | 状態 | 備考 |
|---|--------|------|------|
| W2-09 | WorkflowCellEditor.vue: worker型に刷新 | ✅ 完了 | register/complete/unregister emit |
| W2-10 | Coordinator/WorkflowSheets/Show.vue: 行グループ化・新APIイベント対応 | ✅ 完了 | |
| W2-11 | User/WorkflowSheets/Show.vue: worker型対応 | ✅ 完了 | |

### Phase E: フロントエンド — 進行表

| # | タスク | 状態 | 備考 |
|---|--------|------|------|
| W2-12 | ProgressCell.vue: worker型に work_minutes 小表示追加 | ✅ 完了 | |
| W2-13 | Coordinator/ProgressSheets/Show.vue: 時間集計行追加 | ✅ 完了 | worker/joblink型のみ集計 |

### Phase F: ビルド

| # | タスク | 状態 | 備考 |
|---|--------|------|------|
| W2-14 | npm run build | ✅ 完了 | |
| W2-15 | php artisan migrate | ✅ 完了 | workflow_rows.parent_id 追加 |

---

## 状態凡例

| 記号 | 意味 |
|------|------|
| ⬜ 未着手 | まだ作業していない |
| 🔵 作業中 | 現在進行中 |
| ✅ 完了 | 実装・テスト済み |
| ⚠️ 保留 | 仕様未確定・依存待ち |
| ❌ 差し戻し | 問題あり・修正必要 |

---

## 未決事項トラッカー

| # | 項目 | 状態 |
|---|------|------|
| U-01 | 進行表 User版にも時間小計を追加するか | ⚠️ 保留 |
| U-02 | 工程シート行グループのUI（行追加モーダル統合 or 別ボタン） | ⚠️ 保留 |
| U-03 | 管理シート行追加モーダル: グループヘッダー行追加と作業行追加のUI | ⚠️ 実装後に手直し |
| U-04 | User版管理シートの詳細UI（column_config 対応の全セル型表示） | ⚠️ 実装後に手直し |
| U-05 | workflow_templates テーブルの廃止タイミング | ⚠️ 保留 |

---

## 作業ログ

| 日付 | 内容 |
|------|------|
| 2026-05-14 | 設計確認。PLAN/MANAGER/PROMPT 3ファイル作成。実装着手 |
| 2026-05-14 | W2-01〜W2-15 全タスク完了。npm run build + php artisan migrate 完了 |
| 2026-05-17 | V3仕様確定。PLAN1/MANAGER1 に追記。実装着手 |

---

## V3 進捗一覧テーブル

### Phase A: DB マイグレーション

| # | タスク | 状態 | 備考 |
|---|--------|------|------|
| WM3-01 | workflow_sheets に column_config 追加・stage_config 自動変換 | ⬜ 未着手 | type変換含む |
| WM3-02 | workflow_sheets に share_token 追加 | ⬜ 未着手 | |
| WM3-03 | workflow_rows に stage_id 追加 | ⬜ 未着手 | FK→stages |
| WM3-04 | coordinator_workflow_sheet_favorites テーブル作成 | ⬜ 未着手 | |
| WM3-05 | progress_templates に sheet_type 追加 | ⬜ 未着手 | |
| WM3-06 | workflow_cells 拡張（ProgressCell互換フィールド） | ⬜ 未着手 | value_user_id移行含む |

### Phase B: バックエンド — モデル

| # | タスク | 状態 | 備考 |
|---|--------|------|------|
| WM3-07 | WorkflowSheet モデル更新 | ⬜ 未着手 | column_config/share_token/favorites |
| WM3-08 | WorkflowRow モデル更新 | ⬜ 未着手 | stage リレーション |
| WM3-09 | CoordinatorWorkflowSheetFavorite モデル新規 | ⬜ 未着手 | |

### Phase C: バックエンド — コントローラー・ルート

| # | タスク | 状態 | 備考 |
|---|--------|------|------|
| WM3-10 | WorkflowSheetController 更新 | ⬜ 未着手 | show/registerAsTemplate/printView/share/unshare |
| WM3-11 | WorkflowCellController 更新 | ⬜ 未着手 | stage_id連動・全セル型対応 |
| WM3-12 | WorkflowSheetListController 新規 | ⬜ 未着手 | ProgressSheetListController流用 |
| WM3-13 | JobBoxController 更新 | ⬜ 未着手 | WorkflowCell自動完了追加 |
| WM3-14 | routes/web.php 更新 | ⬜ 未着手 | 8ルート追加 |

### Phase D: フロントエンド — 一覧

| # | タスク | 状態 | 備考 |
|---|--------|------|------|
| WM3-15 | CoordinatorNavigationTabs.vue 更新 | ⬜ 未着手 | タブ追加 |
| WM3-16 | Coordinator/WorkflowSheetList/Index.vue 新規 | ⬜ 未着手 | ProgressSheetList流用 |

### Phase E: フロントエンド — 管理シート詳細

| # | タスク | 状態 | 備考 |
|---|--------|------|------|
| WM3-17 | Coordinator/WorkflowSheets/Show.vue 大規模更新 | ⬜ 未着手 | column_config対応・グループヘッダー行・ProgressCell.vue使用 |
| WM3-18 | WorkflowCellEditor.vue 更新 | ⬜ 未着手 | stage_id連動 |
| WM3-19 | User/WorkflowSheets/Show.vue 更新 | ⬜ 未着手 | column_config対応 |

### Phase F: フロントエンド — 印刷・共有

| # | タスク | 状態 | 備考 |
|---|--------|------|------|
| WM3-20 | Coordinator/WorkflowSheets/Print.vue 新規 | ⬜ 未着手 | ProgressSheets/Print.vue流用 |
| WM3-21 | Shared/WorkflowSheets/Show.vue 新規 | ⬜ 未着手 | 共有閲覧ページ |

### Phase G: ビルド・マイグレーション

| # | タスク | 状態 | 備考 |
|---|--------|------|------|
| WM3-22 | npm run build | ⬜ 未着手 | |
| WM3-23 | php artisan migrate | ⬜ 未着手 | |

---

## セッション引き継ぎメモ

新セッションを開始する際は **WORKFLOW_V21_PROMPT.md** をそのまま貼り付ける。
作業前に必ずこのファイルで進捗を確認し、完了したタスクを ✅ に更新すること。
