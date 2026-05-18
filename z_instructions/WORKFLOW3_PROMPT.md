# WORKFLOW3_PROMPT.md — 校正依頼・管理シート 統合フロー 次セッション開始プロンプト

作成日: 2026-05-18
最終更新: 2026-05-18（BF-01〜04 バグ修正後）

---

## このプロンプトについて

新しいセッションで WORKFLOW V2 Phase 3 の続きを作業する際に貼り付けてください。

---

## 貼り付けプロンプト

```
WORKFLOW_V2_PLAN3.md と WORKFLOW_V2_MANAGER3.md を確認してください。

P3（校正依頼・管理シート 統合フロー）の実装状況を把握し、残タスクがあれば継続してください。

主な実装内容（P3〜P4）:
- proof_requests テーブルに workflow_cell_id カラム追加（マイグレーション済み）
- Coordinator の管理シートから proof_coordinator へ依頼できるモーダル追加
- proof_coordinator の inbox → 管理シートへのリダイレクト（workflow_cell_id がある場合）
- 管理シート上でアサインすると ProofRequest を同時に受理（in_progress に更新）
- ProofCoordinatorNavigationTabs から「管理シート（校正）」タブを削除（inbox から遷移する設計）
- 進行表（ProgressSheet）からの依頼リダイレクト対応（P4）

バグ修正（BF-01〜04）:
- BF-01: workflow_cell_id が null → WorkflowCell firstOrCreate + project_job_id からの fallback redirect
- BF-02: 全行に「+ 担当者」表示 → targetProofKeys（組版セルと同ステージグループの校正セルのみ表示）
- BF-03: Assign.vue が一般フォーム → 校正専用（ProofTimelinePickerModal）フォームに復元
- BF-04: ユーザー校正ジョブ画面でクライアント空欄 → eager load + _client_id 追加

動作確認: 全フロー OK（ユーザー確認済み）
```

---

## 設計サマリー

### フロー

1. **Coordinator が管理シートから依頼する**
   - `Coordinator/WorkflowSheets/Show.vue` の proof_v2 セルドロップダウン「📋 校正管理へ依頼」を選択
   - `handleProofRequestOpen` → モーダル表示（締切・備考入力）
   - `POST /proof-requests`（`workflow_cell_id` 含む）
   - proof_v2 セルがまだ DB に存在しない場合は `workflow_sheet_id + workflow_stage_key` をペイロードに含め、サーバー側で `firstOrCreate` する
   - ProofRequest が pending で作成、セルが「依頼中」バッジに更新

2. **proof_coordinator が受理・配置する**
   - inbox 一覧 → 依頼をクリック → `assignPage()`
   - リダイレクト優先順位:
     1. `workflow_cell_id` あり → `proof_coordinator.workflow_sheets.show?proof_request_id=X`
     2. `proof_cell_id` あり → 進行表 show にリダイレクト
     3. `project_job_id` から管理シートを逆引き → 管理シート show にリダイレクト
   - Show.vue: 対象セルのみ `[+ 担当者]` ボタン表示（`targetProofKeys` で絞り込み）
   - `[+ 担当者]` クリック → `Assign.vue`（校正専用フォーム＋タイムラインピッカー）
   - assignStore: アサイン作成 + work_slots 保存 + ProofRequest を `in_progress` に更新

### 組版セルと校正セルの関係（BF-02 で実装）

管理シートの列は `column_config` ツリーで定義される。各ステージグループ（親ノード）の直下に複数の列（子ノード）が並ぶ。

```
ステージグループ（例: レイアウト更新_初校）
├── worker 列（組版担当: type="coordinator_v2" 等）
├── 校正 列（type="proof_v2"）
└── 校正２ 列（type="proof_user"）
```

proof_coordinator が受理する際、依頼元の組版 PJA の `WorkflowCell.stage_key` から `findProofKeysForWorkerKey()` でツリーを再帰探索し、**同じ親ノードに属する proof_v2/proof_user 列の key** のみを `targetProofKeys` として取得。それ以外の行・列は `—` 表示にする。

### 変更ファイル一覧

| ファイル | 変更内容 |
|---------|---------|
| `database/migrations/2026_05_18_100001_add_workflow_cell_id_to_proof_requests.php` | workflow_cell_id カラム追加 |
| `app/Models/ProofRequest.php` | fillable に workflow_cell_id 追加 |
| `app/Http/Controllers/Coordinator/WorkflowSheetController.php` | formatCellFull + show() |
| `app/Http/Controllers/ProofCoordinator/ProofRequestController.php` | store()（firstOrCreate）+ assignPage()（fallback redirect） |
| `app/Http/Controllers/ProofCoordinator/WorkflowSheetProofController.php` | show()（targetProofKeys）+ assignPage()（deadline ISO形式・requester_name）+ assignStore()（work_slots） |
| `app/Http/Controllers/Concerns/SavesProofWorkSlots.php` | ★新規: work_slots 保存 trait（ProofRequestController / WorkflowSheetProofController 共用） |
| `app/Http/Controllers/User/ProofJobController.php` | setPage()（eager load .client + _client_id 追加） |
| `resources/js/Pages/Coordinator/WorkflowSheets/Show.vue` | handleProofRequestOpen + 校正依頼モーダル + workflow_sheet_id/stage_key ペイロード追加 |
| `resources/js/Pages/ProofCoordinator/WorkflowSheets/Show.vue` | 依頼バッジ・ハイライト・依頼パネル + targetProofKeys による表示絞り込み |
| `resources/js/Pages/ProofCoordinator/WorkflowSheets/Assign.vue` | ★全面再実装: ProofTimelinePickerModal・show-work-slots・proof_request_id storeUrl付加 |
| `resources/js/Components/Tabs/ProofCoordinatorNavigationTabs.vue` | 管理シートタブ削除 |

### 注意事項

- `WorkflowSheets/Assign.vue`（ProofCoordinator）は**校正専用フォーム**であり、一般の Coordinator ジョブ割り当てフォームと混同しないこと
- `SavesProofWorkSlots` trait は ProofSchedule・PJA（pja101: 自己割当）・Event を生成する校正専用ロジック
- 組版担当の PJA → `supersedes_assignment_id` → coordinator PJA → WorkflowCell → stage_key という連鎖でセルを特定する
- `targetProofKeys` が null の場合は全セル表示（旧来動作）、設定されていれば対象列のみ表示
