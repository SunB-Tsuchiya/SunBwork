# WORKFLOW_V22_PROMPT.md — 新セッション開始用プロンプト

このファイルをそのままメッセージに貼り付けて新セッションを開始してください。

---

## プロジェクト引き継ぎ

SunBWork（Laravel 11 + Vue 3 + Inertia.js）の管理シート機能の継続実装です。

---

## 前セッションで完了した修正（実装済み・ビルド済み）

以下は **既に修正・動作確認済み** です。再実装不要。

### 1. handleCellUpdate — worker 型セル保存バグ修正
`resources/js/Pages/Coordinator/WorkflowSheets/Show.vue`
- worker型セルの値が API に送信されない問題を修正済み

### 2. handleWorkerJobRegister — coordinator フォームへの正しい遷移
同 Show.vue
- 別ユーザーを選択した場合、coordinator アサインフォームへ正しく遷移するようになった

### 3. getLeafPath() — item_label を含むタイトル生成
同 Show.vue
- 縦積みレイアウトで item_label がある行のジョブタイトルが正しく生成されるようになった
- 例: `〇〇書籍 本文8_学校B初校_初校_組版`

### 4. JobBoxController::global() — whereNotExists バグ修正
`app/Http/Controllers/ProjectJobs/JobBoxController.php`
- 後発 coordinator ジョブが非表示になっていた問題を修正済み

---

## 次に実装する機能

### 概要：校正管理者向け 管理シート校正割り当てビュー

**設計書:** `z_instructions/WORKFLOW_V2_PLAN2.md`
**進捗管理:** `z_instructions/WORKFLOW_V2_MANAGER2.md`

### 背景

管理シートの `proof_v2` 型セルは校正管理者（ProofCoordinator）が担当者をアサインする。
従来は組版1件 ↔ 校正1件の1:1だったが、ダブルチェック（校正・校正２）のように
1:多数が必要になった。

校正管理者は管理シートの校正セルを直接見て、セルごとに担当者をアサインする。

### 新規画面

| URL | 内容 |
|-----|------|
| `GET /proof-coordinator/workflow-sheets` | 管理シート一覧（校正管理者向け） |
| `GET /proof-coordinator/workflow-sheets/{sheet}` | 校正割り当てビュー |

### 校正割り当てビューの表示仕様

`column_config` から `type === 'proof_v2'` の列のみ抽出して表示:

```
┌──────────────────┬────────────────────┬─────────────────────┐
│ 項目              │ 校正                │ 校正２               │
├──────────────────┼────────────────────┼─────────────────────┤
│ 初校              │ 矢山次郎（登録済）  │ [+ 担当者]           │
│ 学校B初校         │ [+ 担当者]          │ [+ 担当者]           │
└──────────────────┴────────────────────┴─────────────────────┘
```

- 「項目」= `item_label`（あれば）+ `stage.label`
- `[+ 担当者]` → coordinator アサインフォームへ遷移
- アサイン済みセルは担当者名 + 登録済バッジ + 詳細リンク

### タイトル自動生成

アサインフォームに渡す `title` パラメータ:
- `[projectJob.title, ...getLeafPath(colKey)].join('_')`
- `getLeafPath()` は item_label 対応済み（前セッション修正）
- 例: `〇〇書籍 本文8_学校B初校_初校_校正`

### アサインフォームへの遷移

既存の coordinator アサインフォーム（`AssignmentForm.vue`）を流用:
```js
router.visit(route('coordinator.project_jobs.assignments.create', {
    projectJob: projectJob.id,
    title: jobTitle,
    project_job_id: projectJob.id,
    user_id: null,  // フォーム上で選択
    _workflow_sheet_id: sheet.id,
    _row_id: rowId,
    _col_key: colKey,
    stage_id: stageId,
}));
```

**権限問題:** coordinator アサインフォームは `coordinator` ミドルウェアが必要。
proof_coordinator ユーザーがアクセスできるよう調整が必要（PLAN2 未決事項#1参照）。

---

## 実装タスク一覧（MANAGER2 と対応）

| # | ファイル | 内容 |
|---|---------|------|
| P2-01 | `app/Http/Controllers/ProofCoordinator/WorkflowSheetProofController.php` | 新規：index / show |
| P2-02 | `resources/js/Pages/ProofCoordinator/WorkflowSheets/Index.vue` | 新規：シート一覧 |
| P2-03 | `resources/js/Pages/ProofCoordinator/WorkflowSheets/Show.vue` | 新規：校正割り当てビュー |
| P2-04 | `routes/web.php` | proof-coordinator グループに2ルート追加 |
| P2-05 | `resources/js/Components/Tabs/ProofCoordinatorNavigationTabs.vue` | タブ追加 |
| P2-06 | coordinator アサインフォームへのアクセス権限調整 | 要確認・要実装 |

---

## 読むべきファイル（セッション開始時）

1. `z_instructions/WORKFLOW_V2_PLAN2.md` — 詳細設計
2. `z_instructions/WORKFLOW_V2_MANAGER2.md` — 進捗確認
3. `app/Http/Controllers/ProofCoordinator/ProofRequestController.php` — 既存 proof coordinator コントローラー参照
4. `routes/web.php` 853〜870行目 — proof-coordinator ルートグループ確認
5. `resources/js/Pages/Coordinator/WorkflowSheets/Show.vue` — 管理シート Show.vue（流用元）
6. `resources/js/Components/Tabs/ProofCoordinatorNavigationTabs.vue` — 既存ナビタブ確認

---

## 重要ルール（必ず守ること）

- Artisan は必ずコンテナ内: `docker compose exec laravel bash -lc "php artisan ..."`
- Vue/JS 変更後は必ず `npm run build`（プロジェクトルートで）
- 設計確認後に実装開始。不明点は1つずつ質問
- ナビゲーションは必ず `route()` を使う（パスのハードコード禁止）
- ToastUnified は AppLayout 内にグローバル配置済み。各ページで重複させない
- AppLayout のデフォルトスロットに `<div class="rounded bg-white p-6 shadow">` を入れる

---

## 環境情報

- ローカル: `http://localhost:8000`
- Docker: `docker compose exec laravel bash -lc "..."`
- ビルド: `npm run build`（`/home/tchirosb/SunBWork` で実行）
