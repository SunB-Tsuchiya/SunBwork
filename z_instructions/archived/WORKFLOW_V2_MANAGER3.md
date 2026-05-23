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

### Phase P4: 進行表（ProgressSheet）からの依頼リダイレクト

| # | タスク | 状態 | 備考 |
|---|--------|------|------|
| P4-01 | ProofRequestController::assignPage(): proof_cell_id リダイレクト追加 | ✅ 完了 | |
| P4-02 | ProgressSheetProofController 新規作成 | ✅ 完了 | show / assignPage / assignStore |
| P4-03 | routes/web.php: progress-sheets 3ルート追加 | ✅ 完了 | |
| P4-04 | ProofCoordinator/ProgressSheets/Show.vue 新規作成 | ✅ 完了 | |
| P4-05 | ProofCoordinator/ProgressSheets/Assign.vue 新規作成 | ✅ 完了 | |

### Phase D: ビルド・マイグレーション

| # | タスク | 状態 | 備考 |
|---|--------|------|------|
| P3-09 | npm run build（P3+P4） | ✅ 完了 | |
| P3-10 | php artisan migrate（コンテナ内） | ✅ 完了 | workflow_cell_id追加済み |
| P3-11 | 動作確認（ローカル） | ✅ 完了 | ユーザー確認により全フロー OK |

### Phase BF: バグ修正（2026-05-18 動作確認後）

| # | タスク | 状態 | 備考 |
|---|--------|------|------|
| BF-01 | workflow_cell_id が null のまま保存 → firstOrCreate + fallback redirect | ✅ 完了 | ProofRequestController::store()/assignPage() + Coordinator/Show.vue |
| BF-02 | 全行に `+ 担当者` ボタン表示 → targetProofKeys で対象校正セルを絞り込み | ✅ 完了 | WorkflowSheetProofController::show() + ProofCo/Show.vue |
| BF-03 | WorkflowSheets/Assign.vue が一般フォームになっていた → 校正専用フォームに復元 | ✅ 完了 | Assign.vue 再実装 + SavesProofWorkSlots trait 新規追加 |
| BF-04 | ユーザー校正ジョブ画面でクライアント空欄 | ✅ 完了 | ProofJobController eager load (.client) + _client_id 追加 |

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
| 2026-05-18 | Claude | P4（進行表リダイレクト対応）実装完了・PLAN3 設計追記 |
| 2026-05-18 | Claude | BF-01〜04 バグ修正完了・動作確認 OK（ユーザー確認済み） |
