<script setup>
import { ref, computed, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import CoordinatorNavigationTabs from '@/Components/Tabs/CoordinatorNavigationTabs.vue';
import ColumnTreeEditor from '@/Components/ColumnTreeEditor.vue';
import ProgressCell from '@/Components/ProgressCell.vue';
import axios from 'axios';

const props = defineProps({
    sheet:            { type: Object,  required: true },
    defaultRowId:     { type: Number,  required: true },
    cells:            { type: Array,   default: () => [] },
    workerUsers:      { type: Array,   default: () => [] },
    coordinatorUsers: { type: Array,   default: () => [] },
    subcontractors:   { type: Array,   default: () => [] },
    itemEntries:      { type: Array,   default: () => [] },
    templates:        { type: Array,   default: () => [] },
    stages:           { type: Array,   default: () => [] },
    projectSchedules: { type: Array,   default: () => [] },
    projectJob:       { type: Object,  required: true },
    canEdit:          { type: Boolean, default: false },
});

const authUserId = computed(() => usePage().props.auth?.user?.id ?? null);

// ── State ──────────────────────────────────────────────────────────────────────
const localCells        = ref(props.cells.map(c => ({ ...c })));
const editMode          = ref(false);
const localColumnConfig = ref(JSON.parse(JSON.stringify(props.sheet.column_config ?? [])));

// 編集モード用：元の値を保存（変更検知用）
let savedEditModeColumnConfig = null;
let savedEditModeSheetName = null;

watch(() => props.sheet.column_config, val => {
    localColumnConfig.value = JSON.parse(JSON.stringify(val ?? []));
});
watch(() => props.cells, val => {
    localCells.value = val.map(c => ({ ...c }));
});
watch(editMode, (newVal) => {
  if (newVal) {
    // 編集モード開始時に現在の値を保存
    savedEditModeColumnConfig = JSON.stringify(localColumnConfig.value);
    savedEditModeSheetName = localSheetName.value;
  }
});

// ── Column tree helpers ────────────────────────────────────────────────────────
function getLeaves(nodes) {
    const result = [];
    (nodes ?? []).forEach(n => {
        if (!n.children?.length) result.push(n);
        else result.push(...getLeaves(n.children));
    });
    return result;
}

function getSpan(node) {
    if (!node.children?.length) return 1;
    return node.children.reduce((s, c) => s + getSpan(c), 0);
}

function getTreeDepth(nodes) {
    if (!nodes?.length) return 0;
    return 1 + Math.max(...nodes.map(n => n.children?.length ? getTreeDepth(n.children) : 0));
}

// 多段ヘッダー用：各レベルの { node, colspan } 配列を構築
function buildHeaderRows(nodes, maxDepth) {
    const rows = Array.from({ length: maxDepth }, () => []);
    function traverse(node, depth) {
        rows[depth].push({ node, colspan: getSpan(node) });
        if (node.children?.length) {
            node.children.forEach(c => traverse(c, depth + 1));
        } else {
            for (let d = depth + 1; d < maxDepth; d++) {
                rows[d].push({ node: null, colspan: 1 });
            }
        }
    }
    nodes.forEach(n => traverse(n, 0));
    return rows;
}

const leafColumns = computed(() => getLeaves(localColumnConfig.value));
const treeDepth   = computed(() => getTreeDepth(localColumnConfig.value));
const headerRows  = computed(() => buildHeaderRows(localColumnConfig.value, treeDepth.value));

// ── 縦積みレイアウト用（トップレベルが stage ノードのとき） ────────────────────
const stageRows = computed(() =>
    localColumnConfig.value.filter(n => n.type === 'stage' && n.children?.length > 0)
);
const useVerticalLayout = computed(() => stageRows.value.length > 0);
// 1列目ヘッダーの「列テンプレート」は先頭ステージの子に基づく
const verticalColumns = computed(() =>
    stageRows.value.length ? stageRows.value[0].children : []
);
// 子インデックス ci の列合計（全ステージ行を縦積み）
function colGroupTotal(ci) {
    return stageRows.value.reduce((sum, stage) => {
        const child = stage.children?.[ci];
        return sum + (child ? colTotal(child.key) : 0);
    }, 0);
}
const verticalGrandTotal = computed(() =>
    verticalColumns.value.reduce((s, _, i) => s + colGroupTotal(i), 0)
);

// ── Column style helpers ───────────────────────────────────────────────────────
function colHeaderClass(col) {
    if (!col) return 'bg-gray-50 text-gray-500';
    const t = col.type;
    if (t === 'item')        return 'bg-blue-50 text-blue-800';
    if (t === 'stage')       return 'bg-indigo-50 text-indigo-700';
    if (t === 'coordinator') return 'bg-green-50 text-green-700';
    if (t === 'proof_v2')    return 'bg-red-50 text-red-700';
    if (t === 'schedlink')   return 'bg-purple-50 text-purple-700';
    return 'bg-gray-100 text-gray-700';
}

function colCellBg(col) {
    const t = col.type;
    if (t === 'coordinator') return 'bg-green-50/20';
    if (t === 'proof_v2')    return 'bg-red-50/20';
    if (t === 'schedlink')   return 'bg-purple-50/20';
    return '';
}

// ── グループ進捗 ───────────────────────────────────────────────────────────────
function getGroupProgress(groupNode) {
    const leaves = getLeaves([groupNode]);
    const total  = leaves.length;
    const done   = leaves.filter(leaf => {
        const c = localCells.value.find(c => c.row_id === props.defaultRowId && c.stage_key === leaf.key);
        return c?.completed_at || c?.assignment_completed;
    }).length;
    return `${done}/${total}`;
}

// ── Cell lookup ───────────────────────────────────────────────────────────────
function getCellForProgressCell(stageKey) {
    const c = localCells.value.find(c => c.row_id === props.defaultRowId && c.stage_key === stageKey) ?? {};
    return { ...c, col_key: stageKey };
}

function upsertCell(updated) {
    const normalized = { ...updated, stage_key: updated.stage_key ?? updated.col_key };
    const idx = localCells.value.findIndex(
        c => c.row_id === props.defaultRowId && c.stage_key === normalized.stage_key
    );
    if (idx >= 0) localCells.value[idx] = { ...localCells.value[idx], ...normalized };
    else localCells.value.push({ ...normalized, row_id: props.defaultRowId });
}

// ── Work minutes ───────────────────────────────────────────────────────────────
function colTotal(stageKey) {
    return localCells.value.filter(c => c.stage_key === stageKey).reduce((s, c) => s + (c.work_minutes ?? 0) + (c.proof_work_minutes ?? 0), 0);
}
const grandTotal = computed(() => localCells.value.reduce((s, c) => s + (c.work_minutes ?? 0) + (c.proof_work_minutes ?? 0), 0));
function fmtMin(m) {
    if (!m) return '—';
    const rounded = Math.round(m / 10) * 10;
    const h = Math.floor(rounded / 60), mn = rounded % 60;
    if (h > 0 && mn > 0) return `${h}H${mn}m`;
    if (h > 0) return `${h}H`;
    return `${mn}m`;
}

// ── Column tree traversal helpers ─────────────────────────────────────────────
function findStageIdForLeaf(leafKey, nodes = localColumnConfig.value) {
    for (const node of nodes) {
        if (node.children?.length) {
            for (const child of node.children) {
                if (child.key === leafKey && node.type === 'stage') {
                    return props.stages.find(s => s.name === node.label)?.id ?? null;
                }
            }
            const r = findStageIdForLeaf(leafKey, node.children);
            if (r !== undefined) return r;
        }
    }
    return null;
}

function getLeafPath(leafKey, nodes = localColumnConfig.value, path = []) {
    for (const node of nodes) {
        // item_label がある場合（縦積みレイアウトの行識別）は item_label + label の両方を含める
        const nodeLabels = node.item_label ? [node.item_label, node.label] : [node.label];
        const newPath = [...path, ...nodeLabels];
        if (node.key === leafKey) return newPath;
        if (node.children?.length) {
            const r = getLeafPath(leafKey, node.children, newPath);
            if (r) return r;
        }
    }
    return null;
}

// ── Cell update ───────────────────────────────────────────────────────────────
async function handleCellUpdate({ col_key, value_type, value, subcontractor_id }) {
    const row_id    = props.defaultRowId;
    const stage_key = col_key;
    const payload   = { cells: [{ row_id, stage_key }] };
    if (value_type === 'bool')          payload.cells[0].value_bool = value;
    else if (value_type === 'date')     payload.cells[0].value_date = value;
    else if (value_type === 'text')     payload.cells[0].value_text = value;
    else if (value_type === 'user')     payload.cells[0].value_user_id = value;
    else if (value_type === 'subcontractor') payload.cells[0].value_subcontractor_id = value;
    else if (value_type === 'worker') {
        if (subcontractor_id != null) {
            payload.cells[0].value_subcontractor_id = subcontractor_id;
            payload.cells[0].value_user_id          = null;
        } else {
            payload.cells[0].value_user_id          = value;
            payload.cells[0].value_subcontractor_id = null;
        }
    }
    try {
        const res = await axios.put(
            route('coordinator.workflow_sheets.cells.update', { sheet: props.sheet.id }),
            payload
        );
        if (res.data.cells?.length) res.data.cells.forEach(c => upsertCell(c));
    } catch (e) {
        console.error('セル更新失敗', e);
    }
}

// ── Worker complete ────────────────────────────────────────────────────────────
async function handleWorkerComplete({ colKey }) {
    const cell = getCellForProgressCell(colKey);
    if (!cell?.id) return;
    try {
        const res = await axios.post(
            route('coordinator.workflow_cells.complete', { cell: cell.id })
        );
        const idx = localCells.value.findIndex(c => c.id === cell.id);
        if (idx >= 0) {
            localCells.value[idx].completed_at  = res.data.completed_at;
            localCells.value[idx].work_minutes  = res.data.work_minutes;
        }
    } catch (e) {
        alert('更新に失敗しました');
    }
}

// ── Job detail modal ──────────────────────────────────────────────────────────
const jobDetailModal = ref({
    open: false, title: '', assigneeName: '', isSubcontractor: false,
    endDate: null, completed: false, assignmentId: null, cellId: null,
    completing: false, unlinking: false,
});

function handleWorkerJobDetail({ assignmentId, colKey }) {
    if (!assignmentId) return;
    const cell = localCells.value.find(c => c.row_id === props.defaultRowId && c.stage_key === colKey);
    let assigneeName = null;
    if (cell?.assignment_subcontractor_id) {
        const sub = props.subcontractors.find(s => s.id === cell.assignment_subcontractor_id);
        assigneeName = sub ? `[外注] ${sub.name}` : null;
    }
    if (!assigneeName && cell?.assignment_user_id) {
        const user = [...props.workerUsers, ...props.coordinatorUsers].find(u => u.id === cell.assignment_user_id);
        assigneeName = user?.name ?? null;
    }
    jobDetailModal.value = {
        open: true,
        title: cell?.assignment_title ?? '(タイトルなし)',
        assigneeName,
        isSubcontractor: !!cell?.assignment_subcontractor_id,
        endDate: cell?.assignment_end_date ?? null,
        completed: !!cell?.assignment_completed,
        assignmentId,
        cellId: cell?.id ?? null,
        completing: false,
        unlinking: false,
    };
}

function openJobAssignmentDetail(assignmentId) {
    if (!assignmentId || !props.projectJob?.id) return;
    router.visit(route('coordinator.project_jobs.assignments.show', {
        projectJob: props.projectJob.id, assignment: assignmentId,
    }));
}

async function callApiCsrf(url) {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const res = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
    });
    if (!res.ok) throw new Error(await res.text());
    return res.json();
}

async function completeJobAssignment() {
    const id = jobDetailModal.value.assignmentId;
    if (!id) return;
    jobDetailModal.value.completing = true;
    try {
        await callApiCsrf(route('coordinator.progress_sheets.assignments.complete', { assignment: id }));
        const idx = localCells.value.findIndex(c => c.id === jobDetailModal.value.cellId);
        if (idx >= 0) localCells.value[idx] = { ...localCells.value[idx], assignment_completed: true };
        jobDetailModal.value.open = false;
    } catch { /* ignore */ }
    finally { jobDetailModal.value.completing = false; }
}

async function uncompleteJobAssignment() {
    const id = jobDetailModal.value.assignmentId;
    if (!id) return;
    jobDetailModal.value.completing = true;
    try {
        await callApiCsrf(route('coordinator.progress_sheets.assignments.uncomplete', { assignment: id }));
        const idx = localCells.value.findIndex(c => c.id === jobDetailModal.value.cellId);
        if (idx >= 0) localCells.value[idx] = { ...localCells.value[idx], assignment_completed: false };
        jobDetailModal.value.open = false;
    } catch { /* ignore */ }
    finally { jobDetailModal.value.completing = false; }
}

async function unregisterJobFromCell() {
    const cellId = jobDetailModal.value.cellId;
    if (!cellId) return;
    if (!confirm('この登録情報を削除しますか？')) return;
    jobDetailModal.value.unlinking = true;
    try {
        await callApiCsrf(route('coordinator.workflow_cells.unregister', { cell: cellId }));
        const idx = localCells.value.findIndex(c => c.id === cellId);
        if (idx >= 0) {
            localCells.value[idx] = {
                ...localCells.value[idx],
                assignment_id: null, assignment_title: null,
                assignment_completed: null, assignment_user_id: null,
                assignment_end_date: null, completed_at: null,
            };
        }
        jobDetailModal.value.open = false;
    } catch {
        alert('削除に失敗しました');
    } finally {
        jobDetailModal.value.unlinking = false;
    }
}

// ── Job registration ──────────────────────────────────────────────────────────
function handleWorkerJobRegister({ colKey, userId }) {
    const stageId  = findStageIdForLeaf(colKey);
    const colPath  = getLeafPath(colKey) ?? [];
    const jobTitle = [props.projectJob.title, ...colPath].filter(Boolean).join('_');

    const params = {
        title:              jobTitle,
        project_job_id:     props.projectJob.id,
        workflow_sheet_id:  props.sheet.id,
        row_id:             props.defaultRowId,
        col_key:            colKey,
    };
    if (stageId)                 params.stage_id = stageId;
    if (props.projectJob.client_id) params.client_id = props.projectJob.client_id;

    const isSelf = !userId || String(userId) === String(authUserId.value);
    if (isSelf) {
        router.visit(route('events.create_job', params));
    } else {
        params.user_id = userId;
        router.visit(route('coordinator.project_jobs.assignments.create', {
            projectJob: props.projectJob.id,
            ...params,
        }));
    }
}

// ── Proof direct complete ─────────────────────────────────────────────────────
async function handleProofDirectComplete({ assignmentId }) {
    if (!assignmentId) return;
    try {
        await axios.post(
            route('coordinator.project_jobs.assignments.complete', {
                projectJob: props.projectJob.id, assignment: assignmentId,
            })
        );
        localCells.value.forEach(c => {
            if (c.proof_assignment_id === assignmentId) c.proof_assignment_completed = true;
        });
    } catch (e) {
        alert('完了処理に失敗しました');
    }
}

// ── Schedlink complete ─────────────────────────────────────────────────────────
async function handleSchedlinkComplete({ colKey }) {
    const cell = getCellForProgressCell(colKey);
    if (!cell?.id) return;
    try {
        await axios.put(
            route('coordinator.workflow_sheets.cells.update', { sheet: props.sheet.id }),
            { cells: [{ row_id: props.defaultRowId, stage_key: colKey, completed_at: new Date().toISOString() }] }
        );
        const idx = localCells.value.findIndex(c => c.id === cell.id);
        if (idx >= 0) localCells.value[idx].completed_at = new Date().toISOString();
    } catch (e) {
        alert('完了処理に失敗しました');
    }
}

// ── Note save ─────────────────────────────────────────────────────────────────
async function handleNoteSave({ colKey, note }) {
    try {
        await axios.put(
            route('coordinator.workflow_sheets.cells.update', { sheet: props.sheet.id }),
            { cells: [{ row_id: props.defaultRowId, stage_key: colKey, cell_note: note }] }
        );
        const c = localCells.value.find(c => c.row_id === props.defaultRowId && c.stage_key === colKey);
        if (c) c.cell_note = note;
    } catch (e) {
        console.error('ノート保存失敗', e);
    }
}

// ── Sheet name ────────────────────────────────────────────────────────────────
const localSheetName = ref(props.sheet.name);

// ── Column config save ─────────────────────────────────────────────────────────
function onColumnChange(updated) {
    localColumnConfig.value = updated.slice();
}

async function saveColumnConfig() {
    if (!localSheetName.value.trim()) { alert('シート名を入力してください'); return; }
    try {
        await axios.put(
            route('coordinator.workflow_sheets.update', { sheet: props.sheet.id }),
            { column_config: localColumnConfig.value, name: localSheetName.value.trim() }
        );
        savedEditModeColumnConfig = JSON.stringify(localColumnConfig.value);
        savedEditModeSheetName = localSheetName.value;
        editMode.value = false;
        router.reload({ only: ['sheet'] });
    } catch (e) {
        alert('保存に失敗しました');
    }
}

function hasChangesInEditMode() {
  const columnConfigChanged = JSON.stringify(localColumnConfig.value) !== savedEditModeColumnConfig;
  const sheetNameChanged = localSheetName.value !== savedEditModeSheetName;
  return columnConfigChanged || sheetNameChanged;
}

async function saveAndExitEditMode() {
    if (!localSheetName.value.trim()) { alert('シート名を入力してください'); return; }
    try {
        await axios.put(
            route('coordinator.workflow_sheets.update', { sheet: props.sheet.id }),
            { column_config: localColumnConfig.value, name: localSheetName.value.trim() }
        );
        editMode.value = false;
        router.reload({ only: ['sheet'] });
    } catch (e) {
        alert('保存に失敗しました');
    }
}

function exitEditModeWithoutSave() {
  if (hasChangesInEditMode()) {
    if (!confirm('変更内容が保存されていません。破棄して戻りますか？')) {
      return;
    }
  }
  // 元の値に戻す
  localColumnConfig.value = JSON.parse(savedEditModeColumnConfig);
  localSheetName.value = savedEditModeSheetName;
  editMode.value = false;
}

// ── Proof request modal ────────────────────────────────────────────────────────
const showProofModal = ref(false);
const proofModalData = ref({ colKey: '', rowId: null, title: '', deadlineDate: '', deadlineHour: 17, deadlineMinute: 30, note: '' });
const proofModalLoading = ref(false);
const proofModalHours = Array.from({ length: 24 }, (_, i) => i);
const proofModalMinutes = [0, 15, 30, 45];

function handleProofRequestOpen({ rowId, colKey }) {
    const path  = getLeafPath(colKey) ?? [];
    const title = [props.projectJob?.title, ...path].filter(Boolean).join('_');
    const today = new Date().toLocaleDateString('sv-SE'); // YYYY-MM-DD
    proofModalData.value = { colKey, rowId, title, deadlineDate: today, deadlineHour: 17, deadlineMinute: 30, note: '' };
    showProofModal.value = true;
}

function submitProofRequest() {
    if (!proofModalData.value.deadlineDate) { alert('締切日を入力してください'); return; }
    proofModalLoading.value = true;
    const h = String(proofModalData.value.deadlineHour).padStart(2, '0');
    const m = String(proofModalData.value.deadlineMinute).padStart(2, '0');
    const deadline = new Date(`${proofModalData.value.deadlineDate}T${h}:${m}:00+09:00`).toISOString();
    const cell = localCells.value.find(
        c => c.row_id === props.defaultRowId && c.stage_key === proofModalData.value.colKey
    );
    const payload = {
        project_job_id:     props.projectJob?.id ?? null,
        workflow_cell_id:   cell?.id ?? null,
        workflow_sheet_id:  cell ? null : (props.sheet?.id ?? null),
        workflow_stage_key: cell ? null : (proofModalData.value.colKey ?? null),
        title:              proofModalData.value.title,
        deadline,
        note:               proofModalData.value.note,
    };
    router.post(route('proof_requests.store'), payload, {
        preserveScroll: true,
        onSuccess: () => { showProofModal.value = false; },
        onError: () => { alert('校正依頼の送信に失敗しました'); },
        onFinish: () => { proofModalLoading.value = false; },
    });
}

// ── Proof request cancel / extend deadline ─────────────────────────────────────
const proofDeadlineModal = ref({ show: false, proofRequestId: null, currentDeadline: '', newDeadline: '', newHour: 17, newMinute: 30, loading: false });
const deadlineHours = Array.from({ length: 24 }, (_, i) => i);
const deadlineMinutes = [0, 15, 30, 45];

async function handleProofRequestCancel({ proofRequestId, cellId }) {
    if (!window.confirm('この校正依頼を削除しますか？')) return;
    try {
        await axios.delete(route('proof_requests.destroy', { proofRequest: proofRequestId }), {
            headers: { Accept: 'application/json' },
        });
        const idx = localCells.value.findIndex(c => c.id === cellId);
        if (idx >= 0) {
            localCells.value[idx] = {
                ...localCells.value[idx],
                proof_request_pending: false,
                proof_request_id: null,
                proof_request_deadline: null,
            };
        }
    } catch (e) {
        alert(e?.response?.data?.message ?? '依頼の削除に失敗しました');
    }
}

function handleProofRequestExtendDeadline({ proofRequestId, currentDeadline }) {
    proofDeadlineModal.value = { show: true, proofRequestId, currentDeadline, newDeadline: currentDeadline ?? '', newHour: 17, newMinute: 30, loading: false };
}

async function submitExtendDeadline() {
    if (!proofDeadlineModal.value.newDeadline) { alert('締切日を入力してください'); return; }
    proofDeadlineModal.value.loading = true;
    try {
        const h = String(proofDeadlineModal.value.newHour).padStart(2, '0');
        const m = String(proofDeadlineModal.value.newMinute).padStart(2, '0');
        const deadline = new Date(`${proofDeadlineModal.value.newDeadline}T${h}:${m}:00+09:00`).toISOString();
        const res = await axios.patch(route('proof_requests.update_deadline', { proofRequest: proofDeadlineModal.value.proofRequestId }), {
            deadline,
        });
        const newDeadline = res.data.deadline;
        localCells.value = localCells.value.map(c =>
            c.proof_request_id === proofDeadlineModal.value.proofRequestId
                ? { ...c, proof_request_deadline: newDeadline }
                : c
        );
        proofDeadlineModal.value.show = false;
    } catch (e) {
        alert('締切の更新に失敗しました');
    } finally {
        proofDeadlineModal.value.loading = false;
    }
}

// ── Template registration ──────────────────────────────────────────────────────
const showTemplateModal  = ref(false);
const templateName       = ref('');
const templateIsShared   = ref(false);
const templateLoading    = ref(false);

async function registerTemplate() {
    if (!templateName.value.trim()) return;
    templateLoading.value = true;
    try {
        await axios.post(
            route('coordinator.workflow_sheets.register_template', { sheet: props.sheet.id }),
            { name: templateName.value.trim(), is_shared: templateIsShared.value }
        );
        showTemplateModal.value = false;
        templateName.value      = '';
    } catch (e) {
        alert('登録に失敗しました');
    } finally {
        templateLoading.value = false;
    }
}

// ── Share ──────────────────────────────────────────────────────────────────────
const shareToken   = ref(props.sheet.share_token ?? null);
const shareUrl     = ref('');
const shareLoading = ref(false);

async function issueShare() {
    shareLoading.value = true;
    try {
        const res = await axios.post(
            route('coordinator.workflow_sheets.share', { sheet: props.sheet.id })
        );
        shareToken.value = res.data.share_token;
        shareUrl.value   = res.data.url;
    } catch (e) {
        alert('共有URLの発行に失敗しました');
    } finally {
        shareLoading.value = false;
    }
}

async function revokeShare() {
    if (!confirm('共有URLを無効化しますか？')) return;
    try {
        await axios.delete(
            route('coordinator.workflow_sheets.unshare', { sheet: props.sheet.id })
        );
        shareToken.value = null;
        shareUrl.value   = '';
    } catch (e) {
        alert('無効化に失敗しました');
    }
}

function copyShareUrl() {
    navigator.clipboard?.writeText(shareUrl.value || window.location.origin + '/shared/workflow-sheets/' + shareToken.value);
}

// ── Delete sheet ───────────────────────────────────────────────────────────────
function confirmDelete() {
    if (!confirm(`「${props.sheet.name}」を削除しますか？`)) return;
    router.delete(route('coordinator.workflow_sheets.destroy', { sheet: props.sheet.id }));
}

// ── Print ──────────────────────────────────────────────────────────────────────
function openPrint() {
    window.open(route('coordinator.workflow_sheets.print', { sheet: props.sheet.id }), '_blank');
}
</script>

<template>
    <AppLayout :title="`管理シート: ${sheet.name}`">
        <template #header>
            <div class="flex flex-col gap-1">
                <div class="flex items-center gap-3">
                    <Link
                        :href="route('coordinator.project_jobs.show', { projectJob: projectJob.id }) + '?tab=workflow'"
                        class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300 whitespace-nowrap"
                    >← 案件に戻る</Link>
                    <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">
                        管理シート：{{ sheet.name }}
                    </h2>
                </div>
                <div class="flex flex-wrap items-center gap-x-4 gap-y-0.5 text-sm text-gray-600">
                    <span v-if="projectJob.client_name" class="font-medium text-gray-700">{{ projectJob.client_name }}</span>
                    <span v-if="projectJob.client_name && projectJob.title" class="text-gray-400">/</span>
                    <span class="font-medium text-indigo-700">{{ projectJob.title }}</span>
                </div>
            </div>
        </template>

        <template #tabs>
            <CoordinatorNavigationTabs active="workflow_sheet_list" />
        </template>

        <div class="rounded bg-white p-4 shadow">

            <!-- ── ツールバー ──────────────────────────────── -->
            <div class="mb-4 flex flex-wrap items-center gap-3">
                <template v-if="canEdit">
                    <template v-if="editMode">
                        <button
                            type="button"
                            class="rounded bg-green-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-green-700"
                            @click="saveAndExitEditMode"
                        >保存して終了</button>
                        <button
                            type="button"
                            class="rounded border border-gray-300 px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50"
                            @click="exitEditModeWithoutSave"
                        >保存しないで戻る</button>
                    </template>
                    <button
                        v-else
                        type="button"
                        class="rounded bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700"
                        @click="editMode = true"
                    >編集モード</button>

                    <button
                        v-if="!editMode"
                        type="button"
                        class="rounded border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50"
                        @click="showTemplateModal = true"
                    >テンプレートとして登録</button>

                    <button
                        type="button"
                        class="rounded border border-red-200 bg-white px-3 py-1.5 text-sm text-red-500 hover:bg-red-50"
                        @click="confirmDelete"
                    >シート削除</button>
                </template>

                <button
                    v-if="!editMode"
                    type="button"
                    class="rounded border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50"
                    @click="openPrint"
                >印刷</button>

                <template v-if="canEdit && !editMode">
                    <button
                        v-if="!shareToken"
                        type="button"
                        class="rounded border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50"
                        :disabled="shareLoading"
                        @click="issueShare"
                    >{{ shareLoading ? '発行中...' : '共有リンクを発行' }}</button>
                    <template v-else>
                        <button
                            type="button"
                            class="rounded border border-blue-300 bg-blue-50 px-3 py-1.5 text-sm font-medium text-blue-700 hover:bg-blue-100"
                            @click="copyShareUrl"
                        >URLをコピー</button>
                        <button
                            type="button"
                            class="rounded border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-500 hover:bg-gray-50"
                            :disabled="shareLoading"
                            @click="revokeShare"
                        >リンクを無効化</button>
                    </template>
                </template>
            </div>

            <!-- ── 編集モード：シート名 + ColumnTreeEditor ──────────── -->
            <div v-if="editMode && canEdit" class="mb-4">
                <div class="mb-4">
                    <label class="mb-1 block text-sm font-medium text-gray-700">シート名</label>
                    <input
                        v-model="localSheetName"
                        type="text"
                        class="w-full max-w-sm rounded border border-gray-300 px-3 py-1.5 text-sm focus:border-indigo-400 focus:outline-none"
                        placeholder="シート名を入力"
                    />
                </div>
                <h3 class="mb-2 font-semibold text-gray-700">列・ステージ構成</h3>
                <ColumnTreeEditor
                    :nodes="localColumnConfig"
                    :stages="stages"
                    :item-entries="itemEntries"
                    @change="onColumnChange"
                />
            </div>

            <!-- ── 通常モード：テーブル ───────────────────────────────── -->
            <div v-else>
                <div v-if="!leafColumns.length" class="py-12 text-center text-sm text-gray-400">
                    列が定義されていません。「列を編集」で列を追加してください。
                </div>

                <!-- ▼ 縦積みレイアウト（トップレベルに stage ノードがある場合） -->
                <div v-else-if="useVerticalLayout" class="overflow-x-auto">
                    <table class="min-w-full border-collapse border border-gray-300 text-sm">
                        <thead>
                            <tr>
                                <!-- 項目列ヘッダー -->
                                <th class="border border-gray-300 bg-gray-50 px-3 py-2 text-center text-xs font-semibold text-gray-600 whitespace-nowrap" style="min-width:120px;">
                                    項目 / ステージ
                                </th>
                                <!-- 先頭ステージの子を列テンプレートとして使用 -->
                                <th
                                    v-for="col in verticalColumns"
                                    :key="col.key"
                                    class="border border-gray-300 px-3 py-2 text-center text-xs font-semibold whitespace-nowrap"
                                    :class="colHeaderClass(col)"
                                    style="min-width:200px;"
                                >{{ col.label }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- 1 ステージノード = 1 行 -->
                            <tr v-for="stage in stageRows" :key="stage.key">
                                <!-- 項目ラベルセル -->
                                <td class="border border-gray-200 bg-gray-50 px-3 py-2 align-top text-xs font-medium text-gray-700 whitespace-nowrap">
                                    <div class="flex flex-col gap-0.5">
                                        <span v-if="stage.item_label" class="rounded bg-blue-100 px-1.5 py-0.5 text-xs font-medium text-blue-700 w-fit">{{ stage.item_label }}</span>
                                        <span class="text-gray-600">{{ stage.label }}</span>
                                        <span class="text-gray-400 font-normal">{{ getGroupProgress(stage) }}</span>
                                    </div>
                                </td>
                                <!-- 各子列のセル（ProgressCell が <td> を描画） -->
                                <ProgressCell
                                    v-for="child in stage.children"
                                    :key="child.key"
                                    :cell="getCellForProgressCell(child.key)"
                                    :colDef="child"
                                    :rowId="defaultRowId"
                                    :canEdit="canEdit"
                                    :users="child.type === 'coordinator' ? coordinatorUsers : workerUsers"
                                    :subcontractors="child.type === 'coordinator' ? [] : subcontractors"
                                    :projectSchedules="projectSchedules"
                                    :authUserId="authUserId"
                                    @update="handleCellUpdate"
                                    @worker-complete="handleWorkerComplete"
                                    @worker-job-register="handleWorkerJobRegister"
                                    @worker-job-detail="handleWorkerJobDetail"
                                    @proof-direct-complete="handleProofDirectComplete"
                                    @proof-request-open="handleProofRequestOpen"
                                    @proof-request-cancel="handleProofRequestCancel"
                                    @proof-request-extend-deadline="handleProofRequestExtendDeadline"
                                    @schedlink-complete="handleSchedlinkComplete"
                                    @note-save="handleNoteSave"
                                />
                            </tr>
                        </tbody>
                        <!-- 列ごとの小計行 -->
                        <tfoot v-if="verticalGrandTotal > 0">
                            <tr class="bg-gray-100 font-semibold">
                                <td class="border border-gray-300 px-3 py-1.5 text-right text-xs text-gray-500">小計</td>
                                <td
                                    v-for="(col, ci) in verticalColumns"
                                    :key="col.key"
                                    class="border border-gray-300 px-3 py-1.5 text-right text-xs text-gray-700"
                                >{{ fmtMin(colGroupTotal(ci)) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- ▼ 横並びレイアウト（フォールバック：ステージノードなし） -->
                <div v-else class="overflow-x-auto">
                    <table class="min-w-full border-collapse border border-gray-300 text-sm">
                        <thead>
                            <tr v-for="(headerRow, level) in headerRows" :key="level">
                                <th
                                    v-for="(cell, idx) in headerRow"
                                    :key="idx"
                                    :colspan="cell.colspan"
                                    class="border border-gray-300 px-3 py-2 text-center text-xs font-semibold whitespace-nowrap"
                                    :class="cell.node ? colHeaderClass(cell.node) : 'bg-gray-50'"
                                    style="min-width:200px;"
                                >
                                    <template v-if="cell.node">
                                        <div class="flex items-center justify-center gap-1.5 flex-wrap">
                                            <span v-if="cell.node.item_label" class="rounded bg-blue-100 px-1.5 py-0.5 text-xs font-medium text-blue-700">{{ cell.node.item_label }}</span>
                                            <span v-if="cell.node.item_label && cell.node.label" class="text-gray-300 text-xs">/</span>
                                            <span>{{ cell.node.label }}</span>
                                        </div>
                                        <div v-if="cell.node.children?.length" class="text-xs font-normal text-gray-400">
                                            {{ getGroupProgress(cell.node) }}
                                        </div>
                                    </template>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <ProgressCell
                                    v-for="col in leafColumns"
                                    :key="col.key"
                                    :cell="getCellForProgressCell(col.key)"
                                    :colDef="col"
                                    :rowId="defaultRowId"
                                    :canEdit="canEdit"
                                    :users="col.type === 'coordinator' ? coordinatorUsers : workerUsers"
                                    :subcontractors="col.type === 'coordinator' ? [] : subcontractors"
                                    :projectSchedules="projectSchedules"
                                    :authUserId="authUserId"
                                    @update="handleCellUpdate"
                                    @worker-complete="handleWorkerComplete"
                                    @worker-job-register="handleWorkerJobRegister"
                                    @worker-job-detail="handleWorkerJobDetail"
                                    @proof-direct-complete="handleProofDirectComplete"
                                    @proof-request-open="handleProofRequestOpen"
                                    @proof-request-cancel="handleProofRequestCancel"
                                    @proof-request-extend-deadline="handleProofRequestExtendDeadline"
                                    @schedlink-complete="handleSchedlinkComplete"
                                    @note-save="handleNoteSave"
                                />
                            </tr>
                        </tbody>
                        <tfoot v-if="grandTotal > 0">
                            <tr class="bg-gray-100 font-semibold">
                                <td v-for="col in leafColumns" :key="col.key" class="border border-gray-300 px-3 py-1.5 text-right text-xs text-gray-700">
                                    {{ fmtMin(colTotal(col.key)) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- ── 校正依頼モーダル ───────────────────────────────────────── -->
        <Teleport to="body">
            <div v-if="showProofModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="showProofModal = false">
                <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                    <h3 class="mb-4 text-lg font-semibold text-gray-800">校正管理へ依頼</h3>
                    <div class="mb-3">
                        <label class="mb-1 block text-sm font-medium text-gray-700">依頼タイトル</label>
                        <input v-model="proofModalData.title" type="text" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                    </div>
                    <div class="mb-3">
                        <label class="mb-1 block text-sm font-medium text-gray-700">締切日時 <span class="text-red-500">*</span></label>
                        <div class="flex items-center gap-2">
                            <input v-model="proofModalData.deadlineDate" type="date" class="flex-1 rounded border border-gray-300 px-2 py-2 text-sm" />
                            <select v-model="proofModalData.deadlineHour" class="rounded border border-gray-300 px-2 py-2 text-sm">
                                <option v-for="h in proofModalHours" :key="h" :value="h">{{ String(h).padStart(2, '0') }}</option>
                            </select>
                            <span class="text-sm text-gray-500">時</span>
                            <select v-model="proofModalData.deadlineMinute" class="rounded border border-gray-300 px-2 py-2 text-sm">
                                <option v-for="min in proofModalMinutes" :key="min" :value="min">{{ String(min).padStart(2, '0') }}</option>
                            </select>
                            <span class="text-sm text-gray-500">分</span>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="mb-1 block text-sm font-medium text-gray-700">備考</label>
                        <textarea v-model="proofModalData.note" rows="3" class="w-full rounded border border-gray-300 px-3 py-2 text-sm"></textarea>
                    </div>
                    <div class="flex justify-end gap-3">
                        <button type="button" class="rounded border border-gray-300 px-4 py-1.5 text-sm text-gray-600" @click="showProofModal = false">キャンセル</button>
                        <button type="button" class="rounded bg-pink-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-pink-700 disabled:opacity-50" :disabled="proofModalLoading" @click="submitProofRequest">依頼する</button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ── 締切延長モーダル ───────────────────────────────────────── -->
        <Teleport to="body">
            <div v-if="proofDeadlineModal.show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="proofDeadlineModal.show = false">
                <div class="w-full max-w-sm rounded-lg bg-white p-6 shadow-xl">
                    <h3 class="mb-4 text-base font-semibold text-gray-800">締切日を延長</h3>
                    <div class="mb-4">
                        <label class="mb-1 block text-sm font-medium text-gray-700">新しい締切日時 <span class="text-red-500">*</span></label>
                        <div class="flex items-center gap-2">
                            <input v-model="proofDeadlineModal.newDeadline" type="date" class="flex-1 rounded border border-gray-300 px-3 py-2 text-sm" />
                            <select v-model="proofDeadlineModal.newHour" class="rounded border border-gray-300 px-2 py-2 text-sm">
                                <option v-for="h in deadlineHours" :key="h" :value="h">{{ String(h).padStart(2, '0') }}</option>
                            </select>
                            <span class="text-sm text-gray-500">時</span>
                            <select v-model="proofDeadlineModal.newMinute" class="rounded border border-gray-300 px-2 py-2 text-sm">
                                <option v-for="min in deadlineMinutes" :key="min" :value="min">{{ String(min).padStart(2, '0') }}</option>
                            </select>
                            <span class="text-sm text-gray-500">分</span>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3">
                        <button type="button" class="rounded border border-gray-300 px-4 py-1.5 text-sm text-gray-600" @click="proofDeadlineModal.show = false">キャンセル</button>
                        <button type="button" class="rounded bg-yellow-500 px-4 py-1.5 text-sm font-medium text-white hover:bg-yellow-600 disabled:opacity-50" :disabled="proofDeadlineModal.loading" @click="submitExtendDeadline">更新する</button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ── ジョブ詳細モーダル ─────────────────────────────────────── -->
        <Teleport to="body">
            <div v-if="jobDetailModal.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="jobDetailModal.open = false">
                <div class="w-full max-w-sm rounded-lg bg-white p-6 shadow-xl">
                    <h3 class="mb-3 text-lg font-semibold text-gray-800">登録済みジョブ</h3>
                    <dl class="space-y-2 text-sm">
                        <div><dt class="text-xs font-medium text-gray-500">タイトル</dt><dd class="text-gray-800">{{ jobDetailModal.title }}</dd></div>
                        <div v-if="jobDetailModal.assigneeName"><dt class="text-xs font-medium text-gray-500">担当者</dt><dd class="text-gray-800">{{ jobDetailModal.assigneeName }}</dd></div>
                        <div v-if="jobDetailModal.endDate"><dt class="text-xs font-medium text-gray-500">期限</dt><dd class="text-gray-800">{{ jobDetailModal.endDate }}</dd></div>
                        <div><dt class="text-xs font-medium text-gray-500">状態</dt><dd><span :class="jobDetailModal.completed ? 'text-yellow-700 font-semibold' : 'text-blue-700'">{{ jobDetailModal.completed ? '✓ 完了' : '未完了' }}</span></dd></div>
                    </dl>
                    <div class="mt-5 flex flex-wrap justify-end gap-2">
                        <button
                            v-if="jobDetailModal.assignmentId && !jobDetailModal.isSubcontractor"
                            type="button"
                            class="rounded bg-blue-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-700"
                            @click="openJobAssignmentDetail(jobDetailModal.assignmentId)"
                        >ジョブ詳細を開く</button>
                        <button
                            v-if="canEdit && jobDetailModal.assignmentId && !jobDetailModal.completed"
                            type="button"
                            class="rounded bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-60"
                            :disabled="jobDetailModal.completing"
                            @click="completeJobAssignment"
                        >{{ jobDetailModal.completing ? '処理中…' : '完了にする' }}</button>
                        <button
                            v-if="canEdit && jobDetailModal.assignmentId && jobDetailModal.completed"
                            type="button"
                            class="rounded bg-orange-500 px-4 py-1.5 text-sm font-medium text-white hover:bg-orange-600 disabled:opacity-60"
                            :disabled="jobDetailModal.completing"
                            @click="uncompleteJobAssignment"
                        >{{ jobDetailModal.completing ? '処理中…' : '未完了に戻す' }}</button>
                        <button
                            v-if="canEdit && jobDetailModal.cellId"
                            type="button"
                            class="rounded bg-red-100 px-4 py-1.5 text-sm font-medium text-red-700 hover:bg-red-200 disabled:opacity-60"
                            :disabled="jobDetailModal.unlinking"
                            @click="unregisterJobFromCell"
                        >{{ jobDetailModal.unlinking ? '処理中…' : '削除する' }}</button>
                        <button
                            type="button"
                            class="rounded border border-gray-300 px-4 py-1.5 text-sm text-gray-600 hover:bg-gray-50"
                            @click="jobDetailModal.open = false"
                        >閉じる</button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ── テンプレート登録モーダル ───────────────────────────────── -->
        <Teleport to="body">
            <div v-if="showTemplateModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="showTemplateModal = false">
                <div class="w-full max-w-sm rounded-lg bg-white p-6 shadow-xl">
                    <h3 class="mb-4 text-lg font-semibold text-gray-800">テンプレートとして登録</h3>
                    <div class="mb-3">
                        <label class="mb-1 block text-sm font-medium text-gray-700">テンプレート名 <span class="text-red-500">*</span></label>
                        <input v-model="templateName" type="text" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" placeholder="テンプレート名" />
                    </div>
                    <label class="mb-4 flex cursor-pointer items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" v-model="templateIsShared" class="h-4 w-4 rounded" />
                        全体公開にする
                    </label>
                    <div class="flex justify-end gap-3">
                        <button type="button" class="rounded border border-gray-300 px-4 py-1.5 text-sm text-gray-600" @click="showTemplateModal = false">キャンセル</button>
                        <button type="button" class="rounded bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50" :disabled="!templateName.trim() || templateLoading" @click="registerTemplate">登録</button>
                    </div>
                </div>
            </div>
        </Teleport>

    </AppLayout>
</template>
