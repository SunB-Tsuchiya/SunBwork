# WORKFLOW3_PROMPT.md — 校正依頼・管理シート 統合フロー 次セッション開始プロンプト

作成日: 2026-05-18

---

## このプロンプトについて

新しいセッションで WORKFLOW V2 Phase 3 の続きを作業する際に貼り付けてください。

---

## 貼り付けプロンプト

```
WORKFLOW_V2_PLAN3.md と WORKFLOW_V2_MANAGER3.md を確認してください。

P3（校正依頼・管理シート 統合フロー）の実装状況を把握し、残タスクがあれば継続してください。

主な実装内容:
- proof_requests テーブルに workflow_cell_id カラム追加（マイグレーション済み）
- Coordinator の管理シートから proof_coordinator へ依頼できるモーダル追加
- proof_coordinator の inbox → 管理シートへのリダイレクト（workflow_cell_id がある場合）
- 管理シート上でアサインすると ProofRequest を同時に受理（in_progress に更新）
- ProofCoordinatorNavigationTabs から「管理シート（校正）」タブを削除（inbox から遷移する設計）

残タスク（MANAGER3.md を確認）:
- php artisan migrate（コンテナ内で実行）
- 動作確認（proof_coordinator / coordinator ユーザーでログイン確認）
```

---

## 設計サマリー

### フロー

1. **Coordinator が管理シートから依頼する**
   - `Coordinator/WorkflowSheets/Show.vue` の proof_v2 セルドロップダウン「📋 校正管理へ依頼」を選択
   - `handleProofRequestOpen` → モーダル表示（締切・備考入力）
   - `POST /proof-requests`（`workflow_cell_id` 含む）
   - ProofRequest が pending で作成、セルが「依頼中」バッジに更新

2. **proof_coordinator が受理・配置する**
   - inbox 一覧 → 依頼をクリック → `assignPage()`
   - `workflow_cell_id` があれば `proof_coordinator.workflow_sheets.show?proof_request_id=X` にリダイレクト
   - Show.vue: 対応セルをハイライト + 依頼パネル表示
   - `[+ 担当者]` クリック → `Assign.vue`（`proof_request_id` が storeUrl に付加される）
   - assignStore: アサイン作成 + ProofRequest を `in_progress` に更新

### 変更ファイル一覧

| ファイル | 変更内容 |
|---------|---------|
| `database/migrations/2026_05_18_100001_add_workflow_cell_id_to_proof_requests.php` | workflow_cell_id カラム追加 |
| `app/Models/ProofRequest.php` | fillable に workflow_cell_id 追加 |
| `app/Http/Controllers/Coordinator/WorkflowSheetController.php` | formatCellFull + show() |
| `app/Http/Controllers/ProofCoordinator/ProofRequestController.php` | store() + assignPage() |
| `app/Http/Controllers/ProofCoordinator/WorkflowSheetProofController.php` | show() + assignPage() + assignStore() |
| `resources/js/Pages/Coordinator/WorkflowSheets/Show.vue` | handleProofRequestOpen + 校正依頼モーダル |
| `resources/js/Pages/ProofCoordinator/WorkflowSheets/Show.vue` | 依頼バッジ・ハイライト・依頼パネル |
| `resources/js/Pages/ProofCoordinator/WorkflowSheets/Assign.vue` | proofRequest prop・storeUrl |
| `resources/js/Components/Tabs/ProofCoordinatorNavigationTabs.vue` | 管理シートタブ削除 |
