<template>
    <AppLayout :title="`管理シート: ${sheet.name}`">
        <template #header>
            <div class="flex flex-col gap-1">
                <div class="flex items-center gap-3">
                    <Link
                        :href="backUrl"
                        class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 whitespace-nowrap hover:bg-gray-300"
                    >← 案件詳細に戻る</Link>
                    <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">管理シート：{{ sheet.name }}</h2>
                </div>
                <div class="flex flex-wrap items-center gap-x-4 gap-y-0.5 text-sm text-gray-600">
                    <span v-if="projectJob.client_name" class="font-medium text-gray-700">{{ projectJob.client_name }}</span>
                    <span v-if="projectJob.client_name && projectJob.title" class="text-gray-400">/</span>
                    <span class="font-medium text-indigo-700">{{ projectJob.title }}</span>
                </div>
            </div>
        </template>

        <div class="rounded bg-white p-4 shadow">

            <div v-if="!leafColumns.length" class="py-12 text-center text-sm text-gray-400">
                列が定義されていません。
            </div>

            <!-- ▼ 縦積みレイアウト（トップレベルに stage ノードがある場合） -->
            <div v-else-if="useVerticalLayout" class="overflow-x-auto">
                <table class="min-w-full border-collapse border border-gray-300 text-sm">
                    <thead>
                        <tr>
                            <th class="border border-gray-300 bg-gray-50 px-3 py-2 text-center text-xs font-semibold text-gray-600 whitespace-nowrap" style="min-width:120px;">
                                項目 / ステージ
                            </th>
                            <th
                                v-for="col in verticalColumns"
                                :key="col.key"
                                class="border border-gray-300 px-3 py-2 text-center text-xs font-semibold whitespace-nowrap"
                                :class="colHeaderClass(col)"
                                style="min-width:200px;"
                            >{{ col.label }}</th>
                            <th class="border border-gray-300 bg-gray-50 px-3 py-2 text-right text-xs font-semibold text-gray-600 whitespace-nowrap">合計</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="stage in stageRows" :key="stage.key" class="hover:bg-gray-50/50">
                            <td class="border border-gray-200 bg-gray-50 px-3 py-2 align-top text-xs font-medium text-gray-700 whitespace-nowrap">
                                <div class="flex flex-col gap-0.5">
                                    <span v-if="stage.item_label" class="rounded bg-blue-100 px-1.5 py-0.5 text-xs font-medium text-blue-700 w-fit">{{ stage.item_label }}</span>
                                    <span class="text-gray-600">{{ stage.label }}</span>
                                    <span class="text-xs text-gray-400 font-normal">{{ getGroupProgress(stage) }}</span>
                                </div>
                            </td>
                            <td
                                v-for="child in stage.children"
                                :key="child.key"
                                class="border border-gray-300 p-0 align-top"
                                :class="colCellBg(child)"
                            >
                                <WorkflowCellEditor
                                    :cell="getCell(defaultRowId, child.key)"
                                    :stage="child"
                                    :workerUsers="child.type === 'coordinator' ? coordinatorUsers : workerUsers"
                                    :canEdit="canEditCell(defaultRowId, child)"
                                    :isCoordinator="false"
                                    :linkedCell="getLinkedCell(defaultRowId, child)"
                                    :canSelfRegister="canSelfRegisterCell(defaultRowId, child)"
                                    :detailUrl="getCellDetailUrl(defaultRowId, child)"
                                    @complete="(d) => handleCellComplete(defaultRowId, child.key, d)"
                                    @register="() => handleCellRegister(defaultRowId, child.key)"
                                />
                            </td>
                            <td class="border border-gray-300 px-3 py-2 text-right text-sm font-semibold text-gray-700 whitespace-nowrap">
                                {{ fmtMin(stageRowTotal(stage)) }}
                            </td>
                        </tr>
                    </tbody>
                    <tfoot v-if="grandTotal > 0">
                        <tr class="bg-gray-100 font-semibold">
                            <td class="border border-gray-300 px-3 py-1.5 text-xs text-gray-500">小計</td>
                            <td
                                v-for="(col, ci) in verticalColumns"
                                :key="col.key"
                                class="border border-gray-300 px-3 py-1.5 text-right text-xs text-gray-700"
                            >{{ fmtMin(colGroupTotal(ci)) }}</td>
                            <td class="border border-gray-300 px-3 py-1.5 text-right text-xs text-indigo-700">{{ fmtMin(grandTotal) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- ▼ 横並びレイアウト（フォールバック） -->
            <div v-else class="overflow-x-auto">
                <table class="min-w-full border-collapse border border-gray-300 text-sm">
                    <thead>
                        <tr v-for="(headerRow, level) in headerRows" :key="level">
                            <th
                                v-if="level === 0"
                                :rowspan="treeDepth"
                                class="border border-gray-300 bg-gray-50 px-3 py-2 text-left text-xs font-semibold text-gray-600"
                                style="min-width:160px;"
                            >項目</th>
                            <th
                                v-for="(cell, idx) in headerRow"
                                :key="idx"
                                :colspan="cell.colspan"
                                class="border border-gray-300 px-3 py-2 text-center text-xs font-semibold whitespace-nowrap"
                                :class="cell.node ? colHeaderClass(cell.node) : 'bg-gray-50'"
                                style="min-width:200px;"
                            >{{ cell.node?.label ?? '' }}</th>
                            <th v-if="level === 0" :rowspan="treeDepth" class="border border-gray-300 bg-gray-50 px-3 py-2 text-right text-xs font-semibold text-gray-600">合計</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="row in topLevelRows" :key="row.id">
                            <tr v-if="hasChildren(row.id)" class="bg-gray-50">
                                <td :colspan="leafColumns.length + 2" class="border border-gray-300 px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ row.label }}</td>
                            </tr>
                            <template v-if="hasChildren(row.id)">
                                <tr v-for="child in childrenOf[row.id]" :key="child.id" class="hover:bg-gray-50">
                                    <td class="border border-gray-300 py-0 pl-6 pr-2 font-medium text-gray-800"><span class="block py-1.5">{{ child.label }}</span></td>
                                    <td v-for="col in leafColumns" :key="col.key" class="border border-gray-300 p-0" :class="colCellBg(col)">
                                        <WorkflowCellEditor
                                            :cell="getCell(child.id, col.key)"
                                            :stage="col"
                                            :workerUsers="col.type === 'coordinator' ? coordinatorUsers : workerUsers"
                                            :canEdit="canEditCell(child.id, col)"
                                            :isCoordinator="false"
                                            :linkedCell="getLinkedCell(child.id, col)"
                                            :canSelfRegister="canSelfRegisterCell(child.id, col)"
                                            :detailUrl="getCellDetailUrl(child.id, col)"
                                            @complete="(d) => handleCellComplete(child.id, col.key, d)"
                                            @register="() => handleCellRegister(child.id, col.key)"
                                        />
                                    </td>
                                    <td class="border border-gray-300 px-3 py-2 text-right text-sm font-semibold text-gray-700">{{ fmtMin(rowTotal(child.id)) }}</td>
                                </tr>
                            </template>
                            <template v-else>
                                <tr class="hover:bg-gray-50">
                                    <td class="border border-gray-300 px-3 py-0 font-medium text-gray-800"><span class="block py-1.5">{{ row.label }}</span></td>
                                    <td v-for="col in leafColumns" :key="col.key" class="border border-gray-300 p-0" :class="colCellBg(col)">
                                        <WorkflowCellEditor
                                            :cell="getCell(row.id, col.key)"
                                            :stage="col"
                                            :workerUsers="col.type === 'coordinator' ? coordinatorUsers : workerUsers"
                                            :canEdit="canEditCell(row.id, col)"
                                            :isCoordinator="false"
                                            :linkedCell="getLinkedCell(row.id, col)"
                                            :canSelfRegister="canSelfRegisterCell(row.id, col)"
                                            :detailUrl="getCellDetailUrl(row.id, col)"
                                            @complete="(d) => handleCellComplete(row.id, col.key, d)"
                                            @register="() => handleCellRegister(row.id, col.key)"
                                        />
                                    </td>
                                    <td class="border border-gray-300 px-3 py-2 text-right text-sm font-semibold text-gray-700">{{ fmtMin(rowTotal(row.id)) }}</td>
                                </tr>
                            </template>
                        </template>
                        <tr v-if="rows.length > 0" class="bg-gray-100 font-semibold">
                            <td class="border border-gray-300 px-3 py-2 text-xs text-gray-600">小計</td>
                            <td v-for="col in leafColumns" :key="col.key" class="border border-gray-300 px-3 py-2 text-right text-sm text-gray-700">{{ fmtMin(colTotal(col.key)) }}</td>
                            <td class="border border-gray-300 px-3 py-2 text-right text-sm text-indigo-700">{{ fmtMin(grandTotal) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p v-if="rows.length === 0 && leafColumns.length" class="mt-4 text-sm text-gray-400">項目がありません。</p>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AppLayout from '@/layouts/AppLayout.vue';
import WorkflowCellEditor from '@/Components/WorkflowCellEditor.vue';
import axios from 'axios';

const props = defineProps({
    sheet:            { type: Object, required: true },
    rows:             { type: Array,  default: () => [] },
    cells:            { type: Array,  default: () => [] },
    workerUsers:      { type: Array,  default: () => [] },
    coordinatorUsers: { type: Array,  default: () => [] },
    authUserId:       { type: Number, required: true },
    projectJob:       { type: Object, required: true },
});

const localCells = ref([...props.cells]);

const backUrl = computed(() => {
    const base = route('user.project_jobs.show', { projectJob: props.projectJob.id });
    const backTab = new URLSearchParams(window.location.search).get('back_tab');
    return backTab ? `${base}?tab=${backTab}` : base;
});

// ── Column config ────────────────────────────────────────────────────────────
const columnConfig = computed(() => props.sheet.column_config ?? []);

// ── Layout helpers ───────────────────────────────────────────────────────────
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

const leafColumns      = computed(() => getLeaves(columnConfig.value));
const treeDepth        = computed(() => getTreeDepth(columnConfig.value));
const headerRows       = computed(() => buildHeaderRows(columnConfig.value, treeDepth.value));

// ── 縦積みレイアウト ──────────────────────────────────────────────────────────
const stageRows        = computed(() => columnConfig.value.filter(n => n.type === 'stage' && n.children?.length > 0));
const useVerticalLayout = computed(() => stageRows.value.length > 0);
const verticalColumns  = computed(() => stageRows.value.length ? stageRows.value[0].children : []);
const defaultRowId     = computed(() => props.rows[0]?.id ?? null);

// ── 行グループ化（横並びレイアウト用） ─────────────────────────────────────────
const topLevelRows = computed(() => props.rows.filter(r => !r.parent_id));

const childrenOf = computed(() => {
    const map = {};
    for (const r of props.rows) {
        if (r.parent_id) {
            if (!map[r.parent_id]) map[r.parent_id] = [];
            map[r.parent_id].push(r);
        }
    }
    return map;
});

function hasChildren(rowId) {
    return !!(childrenOf.value[rowId]?.length);
}

// ── Cell lookup ───────────────────────────────────────────────────────────────
function getCell(rowId, stageKey) {
    return localCells.value.find(c => c.row_id === rowId && c.stage_key === stageKey) ?? null;
}

function getLinkedCell(rowId, stage) {
    if (stage.type !== 'joblink' || !stage.linked_stage_key) return null;
    return getCell(rowId, stage.linked_stage_key);
}

// ── セルアクセス制御 ──────────────────────────────────────────────────────────
function canEditCell(rowId, stage) {
    const cell = getCell(rowId, stage.key);
    if (!cell?.assigned_user_id) return false;
    return cell.assigned_user_id === props.authUserId;
}

function canSelfRegisterCell(rowId, stage) {
    if (stage.type === 'coordinator' || stage.type === 'joblink') return false;
    const cell = getCell(rowId, stage.key);
    return !cell?.assignment_id;
}

function getCellDetailUrl(rowId, stage) {
    const cell = getCell(rowId, stage.key);
    if (!cell?.assignment_id) return null;
    return route('user.myjobbox.show', { assignment: cell.assignment_id });
}

// ── 列ヘッダースタイル ────────────────────────────────────────────────────────
function colHeaderClass(col) {
    if (!col) return 'bg-gray-50 text-gray-500';
    const t = col.type;
    if (t === 'item')        return 'bg-blue-50 text-blue-800';
    if (t === 'stage')       return 'bg-indigo-50 text-indigo-700';
    if (t === 'coordinator') return 'bg-green-50 text-green-700';
    if (t === 'proof_v2')    return 'bg-red-50 text-red-700';
    return 'bg-indigo-50 text-indigo-700';
}

function colCellBg(col) {
    const t = col?.type;
    if (t === 'coordinator') return 'bg-green-50/20';
    if (t === 'proof_v2')    return 'bg-red-50/20';
    return '';
}

// ── グループ進捗 ───────────────────────────────────────────────────────────────
function getGroupProgress(groupNode) {
    const leaves = getLeaves([groupNode]);
    const total  = leaves.length;
    const done   = leaves.filter(leaf => {
        const c = localCells.value.find(c => c.row_id === defaultRowId.value && c.stage_key === leaf.key);
        return c?.completed_at;
    }).length;
    return `${done}/${total}`;
}

// ── 集計 ──────────────────────────────────────────────────────────────────────
function colTotal(stageKey) {
    return localCells.value.filter(c => c.stage_key === stageKey).reduce((s, c) => s + (c.work_minutes ?? 0), 0);
}

function colGroupTotal(ci) {
    return stageRows.value.reduce((sum, stage) => {
        const child = stage.children?.[ci];
        return sum + (child ? colTotal(child.key) : 0);
    }, 0);
}

function stageRowTotal(stageNode) {
    return getLeaves([stageNode]).reduce((s, leaf) => s + colTotal(leaf.key), 0);
}

function rowTotal(rowId) {
    return localCells.value.filter(c => c.row_id === rowId).reduce((s, c) => s + (c.work_minutes ?? 0), 0);
}

const grandTotal = computed(() => localCells.value.reduce((s, c) => s + (c.work_minutes ?? 0), 0));

function fmtMin(m) {
    if (!m) return '—';
    const h = Math.floor(m / 60);
    const mn = m % 60;
    if (h > 0 && mn > 0) return `${h}H${mn}m`;
    if (h > 0) return `${h}H`;
    return `${mn}m`;
}

// ── イベントハンドラ ──────────────────────────────────────────────────────────
async function handleCellComplete(rowId, stageKey, { cell_id }) {
    if (!cell_id) return;
    try {
        const res = await axios.post(route('user.workflow_cells.complete', { cell: cell_id }));
        const cell = localCells.value.find(c => c.row_id === rowId && c.stage_key === stageKey);
        if (cell) {
            cell.completed_at = res.data.completed_at;
            cell.work_minutes = res.data.work_minutes;
        }
    } catch {
        alert('更新に失敗しました');
    }
}

async function handleCellRegister(rowId, stageKey) {
    try {
        const res = await axios.post(
            route('user.workflow_sheets.cells.register', { sheet: props.sheet.id }),
            { row_id: rowId, stage_key: stageKey },
        );
        const newCell = res.data.cell;
        const idx = localCells.value.findIndex(c => c.row_id === rowId && c.stage_key === stageKey);
        if (idx >= 0) {
            localCells.value[idx] = { ...localCells.value[idx], ...newCell };
        } else {
            localCells.value.push(newCell);
        }
    } catch (e) {
        alert(e?.response?.data?.error ?? '登録に失敗しました');
    }
}
</script>
