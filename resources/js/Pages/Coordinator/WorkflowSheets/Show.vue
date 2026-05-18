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

watch(() => props.sheet.column_config, val => {
    localColumnConfig.value = JSON.parse(JSON.stringify(val ?? []));
});
watch(() => props.cells, val => {
    localCells.value = val.map(c => ({ ...c }));
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
    return localCells.value.filter(c => c.stage_key === stageKey).reduce((s, c) => s + (c.work_minutes ?? 0), 0);
}
const grandTotal = computed(() => localCells.value.reduce((s, c) => s + (c.work_minutes ?? 0), 0));
function fmtMin(m) {
    if (!m) return '—';
    const h = Math.floor(m / 60), mn = m % 60;
    return h > 0 ? `${h}h${mn > 0 ? mn + 'm' : ''}` : `${mn}m`;
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

// ── Job detail ────────────────────────────────────────────────────────────────
function handleWorkerJobDetail({ assignmentId }) {
    if (!assignmentId || !props.projectJob?.id) return;
    router.visit(route('coordinator.project_jobs.assignments.show', {
        projectJob: props.projectJob.id, assignment: assignmentId,
    }));
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

// ── Column config save ─────────────────────────────────────────────────────────
function onColumnChange(updated) {
    localColumnConfig.value = updated.slice();
}

async function saveColumnConfig() {
    try {
        await axios.put(
            route('coordinator.workflow_sheets.update', { sheet: props.sheet.id }),
            { column_config: localColumnConfig.value }
        );
        editMode.value = false;
        router.reload({ only: ['sheet'] });
    } catch (e) {
        alert('保存に失敗しました');
    }
}

// ── Proof request modal ────────────────────────────────────────────────────────
const showProofModal = ref(false);
const proofModalData = ref({ colKey: '', rowId: null, title: '', deadline: '', note: '' });
const proofModalLoading = ref(false);

function handleProofRequestOpen({ rowId, colKey }) {
    const path  = getLeafPath(colKey) ?? [];
    const title = [props.projectJob?.title, ...path].filter(Boolean).join('_');
    proofModalData.value = { colKey, rowId, title, deadline: '', note: '' };
    showProofModal.value = true;
}

async function submitProofRequest() {
    if (!proofModalData.value.deadline) { alert('締切日を入力してください'); return; }
    proofModalLoading.value = true;
    const cell = localCells.value.find(
        c => c.row_id === props.defaultRowId && c.stage_key === proofModalData.value.colKey
    );
    const payload = {
        project_job_id:     props.projectJob?.id ?? null,
        workflow_cell_id:   cell?.id ?? null,
        workflow_sheet_id:  cell ? null : (props.sheet?.id ?? null),
        workflow_stage_key: cell ? null : (proofModalData.value.colKey ?? null),
        title:              proofModalData.value.title,
        deadline:           proofModalData.value.deadline,
        note:               proofModalData.value.note,
    };
    try {
        await axios.post(route('proof_requests.store'), payload);
        if (cell) {
            const idx = localCells.value.findIndex(c => c.id === cell.id);
            if (idx >= 0) localCells.value[idx] = { ...localCells.value[idx], proof_request_pending: true };
        }
        showProofModal.value = false;
    } catch (e) {
        alert('校正依頼の送信に失敗しました');
    } finally {
        proofModalLoading.value = false;
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
            <div class="flex items-center gap-3">
                <Link
                    :href="route('coordinator.project_jobs.show', { projectJob: projectJob.id }) + '?tab=workflow'"
                    class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300 whitespace-nowrap"
                >← 案件に戻る</Link>
                <div>
                    <p class="text-xs text-gray-400">{{ projectJob.client_name }}</p>
                    <h2 class="text-lg font-semibold text-gray-800">{{ projectJob.title }}</h2>
                </div>
            </div>
        </template>

        <template #tabs>
            <CoordinatorNavigationTabs active="workflow_sheet_list" />
        </template>

        <div class="rounded bg-white p-4 shadow">

            <!-- ── ヘッダーアクション ─────────────────────────────────── -->
            <div class="mb-4 flex flex-wrap items-center gap-2">
                <h1 class="mr-2 text-xl font-bold text-gray-900">{{ sheet.name }}</h1>
                <span class="rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-700">管理シート</span>

                <div class="ml-auto flex flex-wrap items-center gap-2">
                    <template v-if="canEdit">
                        <button
                            type="button"
                            @click="editMode = !editMode"
                            class="rounded border px-3 py-1.5 text-sm"
                            :class="editMode
                                ? 'border-gray-300 bg-gray-100 text-gray-700'
                                : 'border-indigo-300 text-indigo-600 hover:bg-indigo-50'"
                        >{{ editMode ? '編集完了' : '列を編集' }}</button>
                        <button v-if="editMode" type="button" class="rounded border border-gray-300 px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50" @click="showTemplateModal = true">テンプレート登録</button>
                        <button v-if="editMode" type="button" class="rounded border border-red-300 px-3 py-1.5 text-sm text-red-600 hover:bg-red-50" @click="confirmDelete">削除</button>
                    </template>
                    <button type="button" class="rounded border border-gray-300 px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50" @click="openPrint">印刷</button>
                    <template v-if="canEdit">
                        <template v-if="!shareToken">
                            <button type="button" class="rounded border border-green-300 px-3 py-1.5 text-sm text-green-600 hover:bg-green-50" :disabled="shareLoading" @click="issueShare">共有URL発行</button>
                        </template>
                        <template v-else>
                            <button type="button" class="rounded border border-blue-300 px-3 py-1.5 text-sm text-blue-600 hover:bg-blue-50" @click="copyShareUrl">URLをコピー</button>
                            <button type="button" class="rounded border border-red-300 px-3 py-1.5 text-sm text-red-600 hover:bg-red-50" @click="revokeShare">共有を解除</button>
                        </template>
                    </template>
                </div>
            </div>

            <!-- ── 編集モード：ColumnTreeEditor ──────────────────────── -->
            <div v-if="editMode && canEdit" class="mb-4">
                <h3 class="mb-2 font-semibold text-gray-700">列・ステージ構成</h3>
                <ColumnTreeEditor
                    :nodes="localColumnConfig"
                    :stages="stages"
                    :item-entries="itemEntries"
                    @change="onColumnChange"
                />
                <div class="mt-4 flex justify-center gap-3">
                    <button type="button" class="rounded border border-gray-300 px-5 py-1.5 text-sm text-gray-600 hover:bg-gray-50" @click="editMode = false">キャンセル</button>
                    <button type="button" class="rounded bg-indigo-600 px-8 py-1.5 text-sm font-medium text-white hover:bg-indigo-700" @click="saveColumnConfig">保存</button>
                </div>
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
                        <label class="mb-1 block text-sm font-medium text-gray-700">締切日 <span class="text-red-500">*</span></label>
                        <input v-model="proofModalData.deadline" type="date" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
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
