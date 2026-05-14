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

---

## 作業ログ

| 日付 | 内容 |
|------|------|
| 2026-05-14 | 設計確認。PLAN/MANAGER/PROMPT 3ファイル作成。実装着手 |
| 2026-05-14 | W2-01〜W2-15 全タスク完了。npm run build + php artisan migrate 完了 |

---

## セッション引き継ぎメモ

新セッションを開始する際は **WORKFLOW_V21_PROMPT.md** をそのまま貼り付ける。
作業前に必ずこのファイルで進捗を確認し、完了したタスクを ✅ に更新すること。
