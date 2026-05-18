# WORKFLOW_V2_PLAN3.md — 校正依頼・管理シート 統合設計書

作成日: 2026-05-18
関連: WORKFLOW_V2_MANAGER3.md / WORKFLOW3_PROMPT.md

---

## 背景・目的

- `proof-coordinator/inbox`（校正依頼受信）と `proof-coordinator/workflow-sheets`（管理シート）が独立しており、校正管理者が混乱する
- coordinator が管理シートの proof_v2 セルから直接 ProofRequest を作成できるようにする
- inbox の依頼を受理・配置する際、管理シートの該当セルを起点として操作できるようにする

---

## 全体フロー（確定）

```
【coordinatorが管理シートから校正依頼を送る】
Coordinator/WorkflowSheets/Show.vue
  proof_v2 セル の「📋 校正管理へ依頼」ドロップダウン選択
    → handleProofRequestOpen モーダル表示（締切・備考入力）
    → POST /proof-requests（workflow_cell_id 含む）
    → ProofRequest 作成（pending）
    → セルが「依頼中」状態に更新
    → proof_coordinator の inbox に通知

【proof_coordinator が受理・配置する】
inbox 一覧 → 依頼をクリック
  ├─ workflow_cell_id あり
  │    → ProofRequestController::assignPage()
  │      redirect → proof_coordinator.workflow_sheets.show?proof_request_id=X
  │    → Show.vue: 該当セルをハイライト + 依頼パネル表示
  │    → [配置する] → Assign.vue（proof_request_id 付き）
  │    → assignStore: 受理(ProofRequest→in_progress) + WorkflowCell 更新
  │    → inbox に戻る
  │
  └─ workflow_cell_id なし（旧来の依頼）
       → 既存 Inbox/Assign.vue（変更なし）

【coordinator が管理シートから直接アサイン（チーム外メンバー）】
Coordinator/WorkflowSheets/Show.vue（変更なし）
  proof_v2 セル → 既存の worker-job-register フロー
```

---

## DB設計

### proof_requests テーブルへの追加

```sql
ALTER TABLE proof_requests
  ADD COLUMN workflow_cell_id BIGINT UNSIGNED NULL AFTER proof_cell_id,
  ADD FOREIGN KEY (workflow_cell_id) REFERENCES workflow_cells(id) ON DELETE SET NULL;
```

### WorkflowCell データの追加フィールド（controller 側集計）

`formatCellFull()` の返却値に以下を追加（DB カラム追加なし）:
- `proof_request_pending` boolean — pending な ProofRequest があるか
- `proof_request_id` int|null — そのID（複数の場合は最新1件）

---

## 実装ファイル一覧

| # | ファイル | 種別 | 内容 |
|---|---------|------|------|
| P3-01 | `database/migrations/2026_05_18_add_workflow_cell_id_to_proof_requests.php` | 新規 | workflow_cell_id カラム追加 |
| P3-02 | `app/Http/Controllers/Coordinator/WorkflowSheetController.php` | 更新 | show(): ProofRequest 集計 → formatCellFull に proof_request_pending / proof_request_id 追加 |
| P3-03 | `app/Http/Controllers/ProofCoordinator/ProofRequestController.php` | 更新 | store(): workflow_cell_id を受取・保存。assignPage(): workflow_cell_id があれば Show.vue へ redirect |
| P3-04 | `app/Http/Controllers/ProofCoordinator/WorkflowSheetProofController.php` | 更新 | show(): 各セルに pending ProofRequest 付与。assignStore(): proof_request_id を受取り ProofRequest 受理 |
| P3-05 | `resources/js/Pages/Coordinator/WorkflowSheets/Show.vue` | 更新 | handleProofRequestOpen + 校正依頼モーダル追加 |
| P3-06 | `resources/js/Pages/ProofCoordinator/WorkflowSheets/Show.vue` | 更新 | proof_request_id クエリ対応・依頼バッジ・依頼パネル・ハイライト |
| P3-07 | `resources/js/Pages/ProofCoordinator/WorkflowSheets/Assign.vue` | 更新 | proofRequest prop・context 表示・proof_request_id を storeUrl に付加 |
| P3-08 | `resources/js/Components/Tabs/ProofCoordinatorNavigationTabs.vue` | 更新 | 「管理シート（校正）」タブ削除 |

---

## 各ファイルの詳細仕様

### P3-01: Migration

```php
Schema::table('proof_requests', function (Blueprint $table) {
    $table->unsignedBigInteger('workflow_cell_id')->nullable()->after('proof_cell_id');
    $table->foreign('workflow_cell_id')->references('id')->on('workflow_cells')->nullOnDelete();
});
```

---

### P3-02: WorkflowSheetController::show() 変更

`$rawCells` 取得後、以下を追加:

```php
// proof_requests と照合（workflow_cell_id を使って pending 状態を取得）
$cellIds = $rawCells->pluck('id')->filter()->toArray();
$pendingProofRequests = [];
if (!empty($cellIds)) {
    $prs = \App\Models\ProofRequest::whereIn('workflow_cell_id', $cellIds)
        ->where('status', 'pending')
        ->get(['id', 'workflow_cell_id']);
    foreach ($prs as $pr) {
        $pendingProofRequests[$pr->workflow_cell_id] = $pr->id;
    }
}
```

`formatCellFull()` に引数 `array $pendingProofRequests` を追加し、返却値に:
```php
'proof_request_pending' => isset($pendingProofRequests[$c->id]),
'proof_request_id'      => $pendingProofRequests[$c->id] ?? null,
```

---

### P3-03: ProofRequestController 変更

**store():**
```php
$data = $request->validate([
    // 既存バリデーション...
    'workflow_cell_id' => ['nullable', 'exists:workflow_cells,id'],
]);
// $data に含めて ProofRequest::create()
```

**assignPage():**
```php
// workflow_cell_id が設定されている場合は WorkflowSheet に遷移
if ($proofRequest->workflow_cell_id) {
    $wCell = \App\Models\WorkflowCell::find($proofRequest->workflow_cell_id);
    $row   = $wCell ? \App\Models\WorkflowRow::find($wCell->row_id) : null;
    $sheet = $row ? \App\Models\WorkflowSheet::find($row->sheet_id) : null;
    if ($sheet) {
        return redirect()->route('proof_coordinator.workflow_sheets.show', [
            'sheet'           => $sheet->id,
            'proof_request_id'=> $proofRequest->id,
        ]);
    }
}
// workflow_cell_id なし → 既存フォールバック
```

---

### P3-04: WorkflowSheetProofController 変更

**show():** 各セルに pending ProofRequest を付与（P3-02 と同様の集計ロジック）

**assignStore():**
```php
$proofRequestId = $request->query('proof_request_id');
// ...（既存の assignment 作成・WorkflowCell 更新の後）
if ($proofRequestId) {
    \App\Models\ProofRequest::where('id', $proofRequestId)
        ->where('status', 'pending')
        ->update([
            'status'               => 'in_progress',
            'proof_coordinator_id' => $senderUser->id,
            'proofreader_id'       => $isDispatcher ? null : $assigneeUserId,
        ]);
}
```

---

### P3-05: Coordinator/WorkflowSheets/Show.vue 変更

既存の `handleProofRequestOpen` ハンドラ追加（ProgressCell の `@proof-request-open` イベントを受信）:

```js
const showProofModal    = ref(false);
const proofModalData    = ref({ colKey: '', rowId: null, title: '', deadline: '', note: '' });

function handleProofRequestOpen({ rowId, colKey }) {
    const path  = getLeafPath(colKey) ?? [];
    const title = [props.projectJob.title, ...path].filter(Boolean).join('_');
    proofModalData.value = { colKey, rowId, title, deadline: '', note: '' };
    showProofModal.value = true;
}

async function submitProofRequest() {
    // WorkflowCell.id を特定（stage_key = colKey の cell.id）
    const cell = localCells.value.find(
        c => c.row_id === props.defaultRowId && c.stage_key === proofModalData.value.colKey
    );
    const payload = {
        project_job_id:   props.projectJob.id,
        workflow_cell_id: cell?.id ?? null,
        title:            proofModalData.value.title,
        deadline:         proofModalData.value.deadline,
        note:             proofModalData.value.note,
    };
    try {
        await axios.post(route('proof_requests.store'), payload);
        // ローカル状態を更新（依頼中バッジ表示）
        if (cell) {
            const idx = localCells.value.findIndex(c => c.id === cell.id);
            if (idx >= 0) localCells.value[idx].proof_request_pending = true;
        }
        showProofModal.value = false;
    } catch (e) {
        alert('校正依頼の送信に失敗しました');
    }
}
```

ProgressCell に `@proof-request-open="handleProofRequestOpen"` を追加（縦積み・横並び両方）。

モーダル HTML を追加（Teleport to="body"）。

---

### P3-06: ProofCoordinator/WorkflowSheets/Show.vue 変更

**新 props:**
```js
const props = defineProps({
    // 既存...
    proofRequestId: { type: Number, default: null },  // URL クエリから
    pendingRequests: { type: Object, default: () => ({}) }, // { [stage_key]: { id, title, deadline, note } }
});
```

**ハイライト:** `proofRequestId` が指定された場合、対応する `stage_key` を持つセルにハイライト CSS を適用

**依頼パネル:** ハイライトされたセルの上部に ProofRequest 情報（タイトル・締切・備考）を表示

**handleAssign 変更:**
```js
function handleAssign(row, colLabel, proofReqId = null) {
    // 既存のパラメータに加え
    if (proofReqId) params.proof_request_id = proofReqId;
    router.visit(route('proof_coordinator.workflow_sheets.assign_page', params));
}
```

---

### P3-07: Assign.vue 変更

```js
const props = defineProps({
    // 既存...
    proofRequest: { type: Object, default: null }, // proof_request_id から取得した ProofRequest
});

const storeUrl = computed(() => {
    const base = route('proof_coordinator.workflow_sheets.assign_store', { sheet: props.sheet.id });
    const qs   = new URLSearchParams();
    if (props.rowId)              qs.append('row_id',           String(props.rowId));
    if (props.colKey)             qs.append('col_key',          props.colKey);
    if (props.proofRequest?.id)   qs.append('proof_request_id', String(props.proofRequest.id));
    const qsStr = qs.toString();
    return qsStr ? `${base}?${qsStr}` : base;
});
```

ProofRequest がある場合、フォーム上部に依頼情報パネルを表示（Inbox/Assign.vue の依頼情報欄と同形式）。

---

### P3-08: ProofCoordinatorNavigationTabs.vue

「管理シート（校正）」タブ定義を削除（desktop の Link タグと mobile の option タグの両方）。

---

## 未決事項

| # | 項目 | 状態 |
|---|------|------|
| 1 | proof_request_id を assignPage 経由で Assign.vue に渡す際、WorkflowSheetProofController::assignPage() でも ProofRequest を render に含めるか | 実装時確認 |
| 2 | coordinator の proof_v2 セルに証明依頼が複数存在する場合の扱い（最新1件のみ対応） | ⚠️ 暫定最新1件 |
