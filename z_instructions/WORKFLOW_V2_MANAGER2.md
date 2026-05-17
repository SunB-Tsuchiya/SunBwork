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
| P2-01 | WorkflowSheetProofController: index() 実装 | ⬜ 未着手 | シート一覧・空き数集計 |
| P2-02 | WorkflowSheetProofController: show() 実装 | ⬜ 未着手 | proof_v2 セルのみ抽出 |
| P2-03 | routes/web.php: proof-coordinator グループに2ルート追加 | ⬜ 未着手 | |

### Phase B: フロントエンド

| # | タスク | 状態 | 備考 |
|---|--------|------|------|
| P2-04 | ProofCoordinator/WorkflowSheets/Index.vue 新規作成 | ⬜ 未着手 | シート一覧 |
| P2-05 | ProofCoordinator/WorkflowSheets/Show.vue 新規作成 | ⬜ 未着手 | 校正割り当てビュー（proof_v2 セルのみ） |
| P2-06 | ProofCoordinatorNavigationTabs.vue: タブ追加 | ⬜ 未着手 | 「管理シート（校正）」 |

### Phase C: 権限調整

| # | タスク | 状態 | 備考 |
|---|--------|------|------|
| P2-07 | coordinator アサインフォームへの proof_coordinator アクセス権限調整 | ⬜ 未着手 | PLAN2.md「未決事項#1」参照 |

### Phase D: ビルド

| # | タスク | 状態 | 備考 |
|---|--------|------|------|
| P2-08 | npm run build | ⬜ 未着手 | |
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
