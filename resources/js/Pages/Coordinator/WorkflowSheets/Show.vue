<template>
    <AppLayout :title="`工程シート: ${sheet.name}`">
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

        <div class="rounded bg-white p-6 shadow">
            <!-- ── ヘッダー ────────────────────────────────────────── -->
            <div class="mb-4 flex flex-wrap items-center gap-3">
                <h1 class="text-xl font-bold text-gray-900">{{ sheet.name }}</h1>
                <span class="rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-medium text-indigo-700">工程シート</span>
                <div class="ml-auto flex items-center gap-2">
                    <button v-if="canEdit && !editMode" type="button" class="rounded border border-indigo-300 px-3 py-1.5 text-sm font-medium text-indigo-600 hover:bg-indigo-50" @click="editMode = true">行を編集</button>
                    <button v-if="editMode" type="button" class="rounded border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50" @click="editMode = false">完了</button>
                    <button v-if="canEdit && editMode" type="button" class="rounded border border-indigo-300 px-3 py-1.5 text-sm text-indigo-600 hover:bg-indigo-50" @click="showAddRowModal = true">+ 行を追加</button>
                    <button v-if="canEdit && editMode" type="button" class="rounded border border-gray-300 px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50" @click="openImportModal">項目リストから追加</button>
                    <button v-if="canEdit && editMode" type="button" class="rounded border border-orange-300 px-3 py-1.5 text-sm text-orange-600 hover:bg-orange-50" @click="showStageEditor = true">ステージ設定</button>
                </div>
            </div>

            <!-- ── テーブル ───────────────────────────────────────── -->
            <div class="overflow-x-auto">
                <table class="min-w-full border-collapse border border-gray-300 text-sm">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="border border-gray-300 px-3 py-2 text-left text-xs font-semibold text-gray-600 whitespace-nowrap" style="min-width: 160px;">項目</th>
                            <th v-for="stage in stages" :key="stage.key"
                                class="border border-gray-300 px-3 py-2 text-center text-xs font-semibold whitespace-nowrap"
                                :class="stage.type === 'coordinator' ? 'bg-green-50 text-green-700' : (stage.type === 'joblink' ? 'bg-orange-50 text-orange-700' : 'bg-indigo-50 text-indigo-700')"
                                style="min-width: 200px;">
                                {{ stage.label }}
                                <span v-if="stage.type === 'coordinator'" class="ml-1 text-xs font-normal opacity-60">(進行)</span>
                                <span v-if="stage.type === 'joblink'" class="ml-1 text-xs font-normal opacity-60">(連動)</span>
                            </th>
                            <th class="border border-gray-300 px-3 py-2 text-right text-xs font-semibold text-gray-600 whitespace-nowrap">合計</th>
                            <th v-if="editMode" class="border border-gray-300 px-2 py-2 text-xs text-gray-400">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="row in topLevelRows" :key="row.id">
                            <!-- グループ親行（子を持つ） -->
                            <tr v-if="hasChildren(row.id)" class="bg-gray-50">
                                <td :colspan="stages.length + (editMode ? 3 : 2)" class="border border-gray-300 px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    <template v-if="editMode && editingRowId === row.id">
                                        <input v-model="editingRowLabel" type="text" class="w-full max-w-xs rounded border border-indigo-300 px-2 py-1 text-sm" @blur="saveRowLabel(row)" @keyup.enter="saveRowLabel(row)" />
                                    </template>
                                    <span v-else :class="editMode ? 'cursor-pointer hover:text-indigo-600' : ''" @click="editMode && startEditRow(row)">{{ row.label }}</span>
                                    <button v-if="editMode" type="button" class="ml-3 text-xs text-red-400 hover:text-red-600" @click="confirmDeleteRow(row)">✕</button>
                                </td>
                            </tr>
                            <!-- 子行 -->
                            <template v-if="hasChildren(row.id)">
                                <tr v-for="child in childrenOf[row.id]" :key="child.id" class="hover:bg-gray-50">
                                    <td class="border border-gray-300 py-0 pl-6 pr-2 font-medium text-gray-800">
                                        <template v-if="editMode && editingRowId === child.id">
                                            <input v-model="editingRowLabel" type="text" class="w-full rounded border border-indigo-300 px-2 py-1 text-sm" @blur="saveRowLabel(child)" @keyup.enter="saveRowLabel(child)" />
                                        </template>
                                        <span v-else class="block py-1.5" :class="editMode ? 'cursor-pointer hover:text-indigo-600' : ''" @click="editMode && startEditRow(child)">{{ child.label }}</span>
                                    </td>
                                    <td v-for="stage in stages" :key="stage.key" class="border border-gray-300 p-0" :class="stage.type === 'coordinator' ? 'bg-green-50/30' : (stage.type === 'joblink' ? 'bg-orange-50/30' : '')">
                                        <WorkflowCellEditor :cell="getCell(child.id, stage.key)" :stage="stage" :workerUsers="stage.type === 'coordinator' ? coordinatorUsers : workerUsers" :canEdit="canEdit" :isCoordinator="true" :linkedCell="getLinkedCell(child.id, stage)"
                                            @register="(d) => handleRegister(child.id, stage.key, d)"
                                            @complete="(d) => handleComplete(child.id, stage.key, d)"
                                            @unregister="(d) => handleUnregister(child.id, stage.key, d)" />
                                    </td>
                                    <td class="border border-gray-300 px-3 py-2 text-right text-sm font-semibold text-gray-700">{{ formatMinutes(rowTotal(child.id)) }}</td>
                                    <td v-if="editMode" class="border border-gray-300 px-2 py-1 text-center">
                                        <button type="button" class="text-xs text-red-500 hover:text-red-700" @click="confirmDeleteRow(child)">削除</button>
                                    </td>
                                </tr>
                            </template>
                            <!-- 通常行（子なし） -->
                            <template v-else>
                                <tr class="hover:bg-gray-50">
                                    <td class="border border-gray-300 px-3 py-0 font-medium text-gray-800">
                                        <template v-if="editMode && editingRowId === row.id">
                                            <input v-model="editingRowLabel" type="text" class="w-full rounded border border-indigo-300 px-2 py-1 text-sm" @blur="saveRowLabel(row)" @keyup.enter="saveRowLabel(row)" />
                                        </template>
                                        <span v-else class="block py-1.5" :class="editMode ? 'cursor-pointer hover:text-indigo-600' : ''" @click="editMode && startEditRow(row)">{{ row.label }}</span>
                                    </td>
                                    <td v-for="stage in stages" :key="stage.key" class="border border-gray-300 p-0" :class="stage.type === 'coordinator' ? 'bg-green-50/30' : (stage.type === 'joblink' ? 'bg-orange-50/30' : '')">
                                        <WorkflowCellEditor :cell="getCell(row.id, stage.key)" :stage="stage" :workerUsers="stage.type === 'coordinator' ? coordinatorUsers : workerUsers" :canEdit="canEdit" :isCoordinator="true" :linkedCell="getLinkedCell(row.id, stage)"
                                            @register="(d) => handleRegister(row.id, stage.key, d)"
                                            @complete="(d) => handleComplete(row.id, stage.key, d)"
                                            @unregister="(d) => handleUnregister(row.id, stage.key, d)" />
                                    </td>
                                    <td class="border border-gray-300 px-3 py-2 text-right text-sm font-semibold text-gray-700">{{ formatMinutes(rowTotal(row.id)) }}</td>
                                    <td v-if="editMode" class="border border-gray-300 px-2 py-1 text-center">
                                        <button type="button" class="text-xs text-red-500 hover:text-red-700" @click="confirmDeleteRow(row)">削除</button>
                                    </td>
                                </tr>
                            </template>
                        </template>

                        <!-- 集計行 -->
                        <tr v-if="localRows.length > 0" class="bg-gray-100 font-semibold">
                            <td class="border border-gray-300 px-3 py-2 text-xs text-gray-600">小計</td>
                            <td v-for="stage in stages" :key="stage.key" class="border border-gray-300 px-3 py-2 text-right text-sm text-gray-700">{{ formatMinutes(stageTotal(stage.key)) }}</td>
                            <td class="border border-gray-300 px-3 py-2 text-right text-sm text-indigo-700">{{ formatMinutes(grandTotal) }}</td>
                            <td v-if="editMode" class="border border-gray-300"></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-if="localRows.length === 0" class="mt-4 text-sm text-gray-400">行がありません。「行を追加」または「項目リストから追加」してください。</p>
        </div>

        <!-- ── 行追加モーダル ──────────────────────────────────────── -->
        <div v-if="showAddRowModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="showAddRowModal = false">
            <div class="w-full max-w-sm rounded-lg bg-white p-6 shadow-xl">
                <h3 class="mb-4 text-lg font-semibold text-gray-800">行を追加</h3>
                <div class="mb-3">
                    <label class="mb-1 block text-sm font-medium text-gray-700">項目名 <span class="text-red-500">*</span></label>
                    <input v-model="newRowLabel" type="text" class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none" placeholder="例：序章初校作成" @keyup.enter="submitAddRow" />
                </div>
                <div class="mb-4">
                    <label class="mb-1 block text-sm font-medium text-gray-700">グループ（任意）</label>
                    <select v-model="newRowParentId" class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none">
                        <option :value="null">— なし（トップレベル） —</option>
                        <option v-for="r in topLevelRows" :key="r.id" :value="r.id">{{ r.label }}</option>
                    </select>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" class="rounded border border-gray-300 px-4 py-1.5 text-sm text-gray-600" @click="showAddRowModal = false">キャンセル</button>
                    <button type="button" class="rounded bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50" :disabled="!newRowLabel.trim()" @click="submitAddRow">追加</button>
                </div>
            </div>
        </div>

        <!-- ── 項目リストインポートモーダル ──────────────────────── -->
        <div v-if="showImportModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="showImportModal = false">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                <h3 class="mb-4 text-lg font-semibold text-gray-800">項目リストから追加</h3>
                <p v-if="itemEntries.length === 0" class="text-sm text-gray-400">この案件に項目リストが登録されていません。</p>
                <div v-else class="mb-4 max-h-80 overflow-y-auto rounded border border-gray-200">
                    <label v-for="entry in itemEntries" :key="entry.id" class="flex cursor-pointer items-center gap-3 px-4 py-2 hover:bg-indigo-50">
                        <input type="checkbox" :value="entry.id" v-model="importSelectedIds" class="h-4 w-4 rounded border-gray-300" />
                        <span class="text-sm text-gray-800">{{ entry.name }}</span>
                    </label>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" class="rounded border border-gray-300 px-4 py-1.5 text-sm text-gray-600" @click="showImportModal = false">キャンセル</button>
                    <button type="button" class="rounded bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50" :disabled="importSelectedIds.length === 0" @click="submitImport">追加（{{ importSelectedIds.length }}件）</button>
                </div>
            </div>
        </div>

        <!-- ── ステージ設定モーダル ───────────────────────────────── -->
        <div v-if="showStageEditor" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="showStageEditor = false">
            <div class="w-full max-w-lg rounded-lg bg-white p-6 shadow-xl">
                <h3 class="mb-4 text-lg font-semibold text-gray-800">ステージ設定</h3>
                <div class="mb-4 space-y-2">
                    <div v-for="(stage, idx) in editableStages" :key="idx" class="rounded border border-gray-200 bg-gray-50 px-3 py-2">
                        <div class="flex items-center gap-2">
                            <span class="w-6 text-xs text-gray-400">{{ idx + 1 }}</span>
                            <input v-model="stage.label" type="text" class="flex-1 rounded border border-gray-300 px-2 py-1 text-sm focus:outline-none" placeholder="ステージ名" />
                            <input v-model="stage.key" type="text" class="w-24 rounded border border-gray-300 px-2 py-1 text-sm text-gray-500 focus:outline-none" placeholder="key" />
                            <select v-model="stage.type" class="rounded border border-gray-300 px-2 py-1 text-sm" @change="onStageTypeChange(stage)">
                                <option value="worker">worker</option>
                                <option value="coordinator">coordinator</option>
                                <option value="joblink">joblink（連動）</option>
                            </select>
                            <button type="button" class="text-xs text-red-400 hover:text-red-600" @click="removeStage(idx)">✕</button>
                        </div>
                        <div v-if="stage.type === 'joblink'" class="mt-1.5 flex items-center gap-2 pl-7">
                            <span class="text-xs text-orange-600">連動元:</span>
                            <select v-model="stage.linked_stage_key" class="rounded border border-orange-200 px-2 py-0.5 text-xs focus:outline-none">
                                <option value="">— workerステージを選択 —</option>
                                <option v-for="s in editableStages.filter(s2 => s2.type === 'worker' && s2 !== stage)" :key="s.key" :value="s.key">{{ s.label || s.key }}</option>
                            </select>
                        </div>
                    </div>
                </div>
                <button type="button" class="mb-4 rounded border border-dashed border-gray-300 px-3 py-1.5 text-sm text-gray-500 hover:bg-gray-50" @click="addStage">+ ステージを追加</button>
                <div class="flex justify-end gap-3">
                    <button type="button" class="rounded border border-gray-300 px-4 py-1.5 text-sm text-gray-600" @click="showStageEditor = false">キャンセル</button>
                    <button type="button" class="rounded bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700" @click="saveStageConfig">保存</button>
                </div>
            </div>
        </div>

    </AppLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import WorkflowCellEditor from '@/Components/WorkflowCellEditor.vue';
import axios from 'axios';

const props = defineProps({
    sheet:            { type: Object, required: true },
    rows:             { type: Array,  default: () => [] },
    cells:            { type: Array,  default: () => [] },
    workerUsers:      { type: Array,  default: () => [] },
    coordinatorUsers: { type: Array,  default: () => [] },
    itemEntries:      { type: Array,  default: () => [] },
    templates:        { type: Array,  default: () => [] },
    projectJob:       { type: Object, required: true },
    canEdit:          { type: Boolean, default: false },
});

const localRows  = ref([...props.rows]);
const localCells = ref([...props.cells]);
const editMode   = ref(false);

const stages = computed(() => props.sheet.stage_config?.stages ?? []);

// ── 行グループ化 ──────────────────────────────────────────────────────────
const topLevelRows = computed(() => localRows.value.filter((r) => !r.parent_id));

const childrenOf = computed(() => {
    const map = {};
    for (const r of localRows.value) {
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

// ── セル取得 ──────────────────────────────────────────────────────────────
function getCell(rowId, stageKey) {
    return localCells.value.find((c) => c.row_id === rowId && c.stage_key === stageKey) ?? null;
}

function getLinkedCell(rowId, stage) {
    if (stage.type !== 'joblink' || !stage.linked_stage_key) return null;
    return getCell(rowId, stage.linked_stage_key);
}

// ── 集計 ──────────────────────────────────────────────────────────────────
function rowTotal(rowId) {
    return localCells.value.filter((c) => c.row_id === rowId).reduce((s, c) => s + (c.work_minutes ?? 0), 0);
}

function stageTotal(stageKey) {
    return localCells.value.filter((c) => c.stage_key === stageKey).reduce((s, c) => s + (c.work_minutes ?? 0), 0);
}

const grandTotal = computed(() => localCells.value.reduce((s, c) => s + (c.work_minutes ?? 0), 0));

function formatMinutes(mins) {
    if (!mins) return '—';
    const h = Math.floor(mins / 60);
    const m = mins % 60;
    return h > 0 ? `${h}h${m > 0 ? m + 'm' : ''}` : `${m}m`;
}

// ── セル更新ヘルパー ──────────────────────────────────────────────────────
function upsertCell(updated) {
    const idx = localCells.value.findIndex(
        (c) => c.row_id === updated.row_id && c.stage_key === updated.stage_key
    );
    if (idx >= 0) {
        localCells.value[idx] = updated;
    } else {
        localCells.value.push(updated);
    }
}

// ── 担当者登録 ────────────────────────────────────────────────────────────
async function handleRegister(rowId, stageKey, { user_id }) {
    try {
        const res = await axios.post(
            route('coordinator.workflow_sheets.cells.register', { sheet: props.sheet.id }),
            { row_id: rowId, stage_key: stageKey, user_id }
        );
        upsertCell(res.data.cell);
    } catch (e) {
        alert('登録に失敗しました');
    }
}

// ── 完了トグル ────────────────────────────────────────────────────────────
async function handleComplete(rowId, stageKey, { cell_id }) {
    if (!cell_id) return;
    try {
        const res = await axios.post(
            route('coordinator.workflow_cells.complete', { cell: cell_id })
        );
        const target = getCell(rowId, stageKey);
        if (target) {
            target.completed_at = res.data.completed_at;
            target.work_minutes = res.data.work_minutes;
        }
    } catch (e) {
        alert('更新に失敗しました');
    }
}

// ── 登録解除 ──────────────────────────────────────────────────────────────
async function handleUnregister(rowId, stageKey, { cell_id }) {
    if (!cell_id) return;
    try {
        const res = await axios.post(
            route('coordinator.workflow_cells.unregister', { cell: cell_id })
        );
        upsertCell(res.data.cell);
    } catch (e) {
        alert('解除に失敗しました');
    }
}

// ── 行編集 ────────────────────────────────────────────────────────────────
const editingRowId    = ref(null);
const editingRowLabel = ref('');

function startEditRow(row) {
    editingRowId.value    = row.id;
    editingRowLabel.value = row.label;
}

async function saveRowLabel(row) {
    if (!editingRowLabel.value.trim() || editingRowLabel.value === row.label) {
        editingRowId.value = null;
        return;
    }
    try {
        await axios.put(
            route('coordinator.workflow_sheets.rows.update', { sheet: props.sheet.id, row: row.id }),
            { label: editingRowLabel.value.trim() }
        );
        const target = localRows.value.find((r) => r.id === row.id);
        if (target) target.label = editingRowLabel.value.trim();
    } catch (e) {
        alert('更新に失敗しました');
    } finally {
        editingRowId.value = null;
    }
}

// ── 行追加 ────────────────────────────────────────────────────────────────
const showAddRowModal = ref(false);
const newRowLabel     = ref('');
const newRowParentId  = ref(null);

async function submitAddRow() {
    if (!newRowLabel.value.trim()) return;
    try {
        const res = await axios.post(
            route('coordinator.workflow_sheets.rows.store', { sheet: props.sheet.id }),
            { label: newRowLabel.value.trim(), parent_id: newRowParentId.value ?? null }
        );
        localRows.value.push(res.data.row);
        newRowLabel.value     = '';
        newRowParentId.value  = null;
        showAddRowModal.value = false;
    } catch (e) {
        alert('追加に失敗しました');
    }
}

// ── 行削除 ────────────────────────────────────────────────────────────────
async function confirmDeleteRow(row) {
    if (!confirm(`「${row.label}」を削除しますか？（セルデータも削除されます）`)) return;
    try {
        await axios.delete(
            route('coordinator.workflow_sheets.rows.destroy', { sheet: props.sheet.id, row: row.id })
        );
        localRows.value  = localRows.value.filter((r) => r.id !== row.id);
        localCells.value = localCells.value.filter((c) => c.row_id !== row.id);
    } catch (e) {
        alert('削除に失敗しました');
    }
}

// ── 項目リストインポート ──────────────────────────────────────────────────
const showImportModal   = ref(false);
const importSelectedIds = ref([]);

function openImportModal() {
    importSelectedIds.value = [];
    showImportModal.value   = true;
}

async function submitImport() {
    if (!importSelectedIds.value.length) return;
    try {
        const res = await axios.post(
            route('coordinator.workflow_sheets.rows.import', { sheet: props.sheet.id }),
            { entry_ids: importSelectedIds.value }
        );
        localRows.value.push(...res.data.rows);
        showImportModal.value = false;
    } catch (e) {
        alert('インポートに失敗しました');
    }
}

// ── ステージ設定 ──────────────────────────────────────────────────────────
const showStageEditor = ref(false);
const editableStages  = ref([]);

function addStage() {
    editableStages.value.push({ key: `stage${Date.now()}`, label: '', type: 'worker', linked_stage_key: null });
}

function onStageTypeChange(stage) {
    if (stage.type !== 'joblink') {
        stage.linked_stage_key = null;
    }
}

function removeStage(idx) {
    editableStages.value.splice(idx, 1);
}

async function saveStageConfig() {
    if (editableStages.value.some((s) => !s.key || !s.label)) {
        alert('key とラベルは必須です');
        return;
    }
    try {
        await axios.put(
            route('coordinator.workflow_sheets.update', { sheet: props.sheet.id }),
            { stage_config: { stages: editableStages.value } }
        );
        showStageEditor.value = false;
        router.reload({ only: ['sheet'] });
    } catch (e) {
        alert('保存に失敗しました');
    }
}

watch(showStageEditor, (open) => {
    if (open) editableStages.value = stages.value.map((s) => ({ ...s }));
});
</script>
