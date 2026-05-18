# WORKFLOW_V2_MANAGER2.md — 校正管理者向け管理シート校正割り当てビュー 進捗管理

作成日: 2026-05-17
関連: WORKFLOW_V2_PLAN2.md / WORKFLOW_V22_PROMPT.md

---

## 作業フロー

```
Phase A（バックエンド）→ Phase B（フロントエンド）→ Phase C（権限調整）→ Phase D（ビルド）
```

---

## 進捗一覧テーブル

### Phase A: バックエンド

| # | タスク | 状態 | 備考 |
|---|--------|------|------|
| P2-01 | WorkflowSheetProofController: index() 実装 | ✅ 完了 | シート一覧・空き数集計 |
| P2-02 | WorkflowSheetProofController: show() + assignPage() + assignStore() 実装 | ✅ 完了 | proof_v2 セルのみ抽出・専用アサインルート |
| P2-03 | routes/web.php: proof-coordinator グループに4ルート追加 | ✅ 完了 | index/show/assign_page/assign_store |

### Phase B: フロントエンド

| # | タスク | 状態 | 備考 |
|---|--------|------|------|
| P2-04 | ProofCoordinator/WorkflowSheets/Index.vue 新規作成 | ✅ 完了 | シート一覧 |
| P2-05 | ProofCoordinator/WorkflowSheets/Show.vue 新規作成 | ✅ 完了 | 校正割り当てビュー（proof_v2 セルのみ） |
| P2-05b | ProofCoordinator/WorkflowSheets/Assign.vue 新規作成 | ✅ 完了 | AssignmentForm.vue を流用した専用アサインページ |
| P2-06 | ProofCoordinatorNavigationTabs.vue: タブ追加 | ✅ 完了 | 「管理シート（校正）」 |

### Phase C: 権限調整

| # | タスク | 状態 | 備考 |
|---|--------|------|------|
| P2-07 | coordinator アサインフォームへの proof_coordinator アクセス権限調整 | ✅ 完了 | 専用ルート（proof_coordinator.workflow_sheets.assign_store）を新設。coordinator ミドルウェア変更不要 |

### Phase D: ビルド

| # | タスク | 状態 | 備考 |
|---|--------|------|------|
| P2-08 | npm run build | ✅ 完了 | エラーなし |
| P2-09 | 動作確認（ローカル） | ⬜ 未着手 | proof_coordinator ユーザーでログイン確認 |

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
| 1 | coordinator アサインフォームへの proof_coordinator アクセス権限 | ⚠️ 未確定 | |
| 2 | WorkflowSheet が複数 defaultRow を持つ場合の対応 | ⚠️ 未確定 | |
| 3 | inbox からのリンク実装 | ⬜ 後続 | |

---

## 作業ログ

| 日付 | 作業者 | 内容 |
|------|--------|------|
| 2026-05-17 | Claude | PLAN2 / MANAGER2 / PROMPT 作成・設計確定 |
| 2026-05-18 | Claude | Phase A〜D 全実装・ビルド完了。専用アサインルートで coordinator ミドルウェア変更なし |
