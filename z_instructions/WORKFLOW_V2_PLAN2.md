# WORKFLOW_V2_PLAN2.md — 校正管理者向け 管理シート校正割り当てビュー 設計書

作成日: 2026-05-17
関連: WORKFLOW_V2_MANAGER2.md / WORKFLOW_V22_PROMPT.md

---

## 背景・目的

管理シート（WorkflowSheet）の `proof_v2` 型セルは、校正管理者（ProofCoordinator）が
担当者をアサインする。これまでの 1:1（組版1人 ↔ 校正1人）を前提とした設計では、
`proof_v2` 列が2つ以上ある場合（ダブルチェック等）に対応できていなかった。

**本機能の目的:**
- 校正管理者専用の絞り込みビューを `/proof-coordinator/workflow-sheets` に追加
- 管理シート全体は表示せず、「項目名 + proof_v2 セルのみ」に絞って表示
- 校正管理者がセルごとに担当者を直接アサインできるようにする

---

## 業務フロー（確定）

```
[組版担当者/進行管理]                    [校正管理者]
  │
  ├─ ユーザーが「校正依頼」ボタン → proof-coordinator/inbox に通知
  │    または
  └─ 進行管理が管理シートから校正セルを操作
                                           │
                              /proof-coordinator/workflow-sheets を開く
                                           │
                              対象シートを選択
                                           │
                    ┌──────────────────────┴──────────────────────┐
                    │ 項目        │ 校正              │ 校正２       │
                    ├────────────┼───────────────────┼─────────────┤
                    │ 初校        │ [矢山次郎・登録済] │ [+ 担当者]   │
                    │ 学校B初校   │ [+ 担当者]         │ [+ 担当者]   │
                    └────────────┴───────────────────┴─────────────┘
                                           │
                              [+ 担当者] クリック → アサインフォーム
                                           │
                              担当者選択・締め切り設定・送信
                                           │
                    担当者ジョブボックスへ届く + 管理シートセル更新
```

---

## タイトル自動生成ルール

管理シートの `getLeafPath()` 関数（既に修正済み）を使用:

| 行                | セル  | 生成タイトル例                              |
|-------------------|-------|--------------------------------------------|
| 初校（item_label=null）  | 校正  | `案件名_初校_校正`                        |
| 学校B初校（item_label=学校B初校） | 校正  | `案件名_学校B初校_初校_校正`     |
| 学校B初校         | 校正２ | `案件名_学校B初校_初校_校正２`             |

---

## 新規画面仕様

### 1. シート一覧 `/proof-coordinator/workflow-sheets`

- 自分がアクセス可能な管理シートの一覧
- 各シートに「proof_v2 セルの空き状況」を表示（例: 「校正未アサイン 2件」）
- シートをクリック → 詳細ビューへ

### 2. 校正割り当てビュー `/proof-coordinator/workflow-sheets/{sheet}`

**表示内容（column_config から `proof_v2` 型列のみ抽出）:**

| 列名      | 内容                                     |
|-----------|------------------------------------------|
| 項目       | item_label（あれば）+ stage.label        |
| 校正（各proof_v2列） | 担当者名（登録済）or [+ 担当者] ボタン |

**セル状態:**

| 状態     | 表示                                |
|----------|-------------------------------------|
| 未アサイン | `[+ 担当者]` ボタン               |
| アサイン済 | 担当者名 + 登録済バッジ + 詳細リンク |
| 完了      | 担当者名 + 完了バッジ               |

**[+ 担当者] クリック時の遷移:**
- 既存の coordinator アサインフォーム（AssignmentForm.vue）を流用
- 以下のパラメータを QueryString で渡す:
  ```
  title            = 自動生成タイトル（案件名_item_label_stage_col）
  project_job_id   = projectJob.id
  user_id          = （未設定、フォーム上で選択）
  _workflow_sheet_id = sheet.id
  _row_id          = defaultRowId（WorkflowSheetの row.id）
  _col_key         = 対象 proof_v2 セルの key
  stage_id         = 対象 stage の id（あれば）
  ```
- アサインフォームは coordinator ルートを使用
  → `proof_coordinator` ミドルウェアユーザーも coordinator アサインフォームにアクセスできるよう、
    ルート or ミドルウェアを調整する必要がある（実装時に要確認）

---

## 実装ファイル一覧

| # | ファイル | 変更種別 | 内容 |
|---|---------|---------|------|
| P2-01 | `app/Http/Controllers/ProofCoordinator/WorkflowSheetProofController.php` | 新規 | index / show |
| P2-02 | `resources/js/Pages/ProofCoordinator/WorkflowSheets/Index.vue` | 新規 | シート一覧 |
| P2-03 | `resources/js/Pages/ProofCoordinator/WorkflowSheets/Show.vue` | 新規 | 校正割り当てビュー |
| P2-04 | `routes/web.php` | 更新 | proof-coordinator グループに2ルート追加 |
| P2-05 | `resources/js/Components/Tabs/ProofCoordinatorNavigationTabs.vue` | 更新 | 「管理シート（校正）」タブ追加 |
| P2-06 | `app/Http/Middleware/HandleInertiaRequests.php` | 確認 | proof_coordinator ユーザーが必要な props を受け取れているか |

---

## 新規ルート

```php
// proof-coordinator グループ（既存の Route::middleware(['proof_coordinator'])-> ... ）内に追加
Route::get('workflow-sheets', [WorkflowSheetProofController::class, 'index'])
    ->name('workflow_sheets.index');
Route::get('workflow-sheets/{sheet}', [WorkflowSheetProofController::class, 'show'])
    ->name('workflow_sheets.show');
```

---

## WorkflowSheetProofController 仕様

### index()
- 全 WorkflowSheet を取得（または case by case でアクセス制御）
- 各シートの proof_v2 セルのアサイン済み数 / 総数を集計
- Inertia: `ProofCoordinator/WorkflowSheets/Index`

### show()
- WorkflowSheet を load（column_config 含む）
- column_config から proof_v2 型の列のみ抽出
- 対象セル（proof_v2 の stage_key に一致する WorkflowCell）を取得
- ProjectJob 情報（id, title, client_id, client_name）を付与
- workerUsers（アサイン候補者リスト）を付与
- Inertia: `ProofCoordinator/WorkflowSheets/Show`

---

## アサインフォームへのアクセス権限

現状、coordinator アサインフォーム（`coordinator.project_jobs.assignments.create`）は
`coordinator` ミドルウェアが必要。

対応方針（実装時に選択）:
- **案1**: proof_coordinator ミドルウェアも通過できるようルートのミドルウェアを緩和
- **案2**: proof_coordinator 専用のアサインルートを新設
  `proof-coordinator/workflow-sheets/{sheet}/assign` → AssignmentForm.vue を流用

推奨: **案1**（最小変更）。AssignmentForm.vue の `mode` prop は `'coordinator'` のまま使用可。

---

## 未決事項

| # | 項目 | 状態 |
|---|------|------|
| 1 | coordinator アサインフォームへの proof_coordinator アクセス権限方針 | ⚠️ 実装時確認 |
| 2 | WorkflowSheet の defaultRowId（単一行 or 複数行対応） | ⚠️ 実装時確認 |
| 3 | inbox からのリンク（通知 → このビューへの遷移）は現時点では未実装 | ⬜ 後続フェーズ |
