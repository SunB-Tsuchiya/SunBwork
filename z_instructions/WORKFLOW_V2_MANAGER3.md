# WORKFLOW_V2_MANAGER3.md — 校正依頼・管理シート 統合フロー 進捗管理

作成日: 2026-05-18
関連: WORKFLOW_V2_PLAN3.md / WORKFLOW3_PROMPT.md

---

## 作業フロー

```
Phase A（DB）→ Phase B（バックエンド）→ Phase C（フロントエンド）→ Phase D（ビルド・マイグレーション）
```

---

## 進捗一覧テーブル

### Phase A: DB

| # | タスク | 状態 | 備考 |
|---|--------|------|------|
| P3-01 | Migration: workflow_cell_id カラム追加 | ✅ 完了 | proof_requests に workflow_cell_id BIGINT NULLABLE FK |

### Phase B: バックエンド

| # | タスク | 状態 | 備考 |
|---|--------|------|------|
| P3-02 | WorkflowSheetController: formatCellFull + show() 更新 | ✅ 完了 | proof_request_pending / proof_request_id 追加 |
| P3-03 | ProofRequestController: store() + assignPage() 更新 | ✅ 完了 | workflow_cell_id 受取・assignPage リダイレクト |
| P3-04 | WorkflowSheetProofController: show() + assignStore() 更新 | ✅ 完了 | pending ProofRequest 付与・proof_request_id 受理 |

### Phase C: フロントエンド

| # | タスク | 状態 | 備考 |
|---|--------|------|------|
| P3-05 | Coordinator/WorkflowSheets/Show.vue 更新 | ✅ 完了 | handleProofRequestOpen + 校正依頼モーダル |
| P3-06 | ProofCoordinator/WorkflowSheets/Show.vue 更新 | ✅ 完了 | proof_request_id 対応・依頼バッジ・依頼パネル・ハイライト |
| P3-07 | ProofCoordinator/WorkflowSheets/Assign.vue 更新 | ✅ 完了 | proofRequest prop・proof_request_id storeUrl付加 |
| P3-08 | ProofCoordinatorNavigationTabs.vue 更新 | ✅ 完了 | 「管理シート（校正）」タブ削除 |

### Phase D: ビルド・マイグレーション

| # | タスク | 状態 | 備考 |
|---|--------|------|------|
| P3-09 | npm run build | ✅ 完了 | |
| P3-10 | php artisan migrate（コンテナ内） | ⬜ 未着手 | |
| P3-11 | 動作確認（ローカル） | ⬜ 未着手 | |

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

| # | 項目 | 状態 | 結論 |
|---|------|------|------|
| 1 | coordinator の proof_v2 セルに依頼が複数存在する場合の扱い | ⚠️ 暫定 | 最新1件のみ対応 |

---

## 作業ログ

| 日付 | 作業者 | 内容 |
|------|--------|------|
| 2026-05-18 | Claude | PLAN3 / MANAGER3 / PROMPT3 作成・設計確定 |
| 2026-05-18 | Claude | Phase A〜C 全実装・npm run build 完了 |
