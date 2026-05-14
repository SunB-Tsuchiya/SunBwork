<template>
    <AppLayout :title="`工程シート: ${sheet.name}`">
        <template #header>
            <div class="flex items-center gap-3">
                <h2 class="text-lg font-semibold text-gray-800">
                    {{ projectJob.title }}
                    <span class="ml-2 text-base font-normal text-gray-400">工程シート</span>
                </h2>
            </div>
        </template>

        <div class="rounded bg-white p-6 shadow">
            <div class="mb-4 flex items-center gap-3">
                <h1 class="text-xl font-bold text-gray-900">{{ sheet.name }}</h1>
                <span class="text-xs text-gray-400">{{ projectJob.client_name }}</span>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full border-collapse border border-gray-300 text-sm">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="border border-gray-300 px-3 py-2 text-left text-xs font-semibold text-gray-600" style="min-width: 160px;">項目</th>
                            <th v-for="stage in stages" :key="stage.key"
                                class="border border-gray-300 px-3 py-2 text-center text-xs font-semibold whitespace-nowrap"
                                :class="stage.type === 'coordinator' ? 'bg-green-50 text-green-700' : (stage.type === 'joblink' ? 'bg-orange-50 text-orange-700' : 'bg-indigo-50 text-indigo-700')"
                                style="min-width: 200px;">
                                {{ stage.label }}
                                <span v-if="stage.type === 'joblink'" class="ml-1 text-xs font-normal opacity-60">(連動)</span>
                            </th>
                            <th class="border border-gray-300 px-3 py-2 text-right text-xs font-semibold text-gray-600">合計</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="row in topLevelRows" :key="row.id">
                            <!-- グループ親行 -->
                            <tr v-if="hasChildren(row.id)" class="bg-gray-50">
                                <td :colspan="stages.length + 2" class="border border-gray-300 px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ row.label }}</td>
                            </tr>
                            <!-- 子行 -->
                            <template v-if="hasChildren(row.id)">
                                <tr v-for="child in childrenOf[row.id]" :key="child.id" class="hover:bg-gray-50">
                                    <td class="border border-gray-300 py-0 pl-6 pr-2 font-medium text-gray-800"><span class="block py-1.5">{{ child.label }}</span></td>
                                    <td v-for="stage in stages" :key="stage.key" class="border border-gray-300 p-0" :class="stage.type === 'coordinator' ? 'bg-green-50/30' : (stage.type === 'joblink' ? 'bg-orange-50/30' : '')">
                                        <WorkflowCellEditor :cell="getCell(child.id, stage.key)" :stage="stage" :workerUsers="stage.type === 'coordinator' ? coordinatorUsers : workerUsers" :canEdit="canEditCell(child.id, stage)" :isCoordinator="false" :linkedCell="getLinkedCell(child.id, stage)"
                                            @complete="(d) => handleCellComplete(child.id, stage.key, d)" />
                                    </td>
                                    <td class="border border-gray-300 px-3 py-2 text-right text-sm font-semibold text-gray-700">{{ formatMinutes(rowTotal(child.id)) }}</td>
                                </tr>
                            </template>
                            <!-- 通常行 -->
                            <template v-else>
                                <tr class="hover:bg-gray-50">
                                    <td class="border border-gray-300 px-3 py-0 font-medium text-gray-800"><span class="block py-1.5">{{ row.label }}</span></td>
                                    <td v-for="stage in stages" :key="stage.key" class="border border-gray-300 p-0" :class="stage.type === 'coordinator' ? 'bg-green-50/30' : (stage.type === 'joblink' ? 'bg-orange-50/30' : '')">
                                        <WorkflowCellEditor :cell="getCell(row.id, stage.key)" :stage="stage" :workerUsers="stage.type === 'coordinator' ? coordinatorUsers : workerUsers" :canEdit="canEditCell(row.id, stage)" :isCoordinator="false" :linkedCell="getLinkedCell(row.id, stage)"
                                            @complete="(d) => handleCellComplete(row.id, stage.key, d)" />
                                    </td>
                                    <td class="border border-gray-300 px-3 py-2 text-right text-sm font-semibold text-gray-700">{{ formatMinutes(rowTotal(row.id)) }}</td>
                                </tr>
                            </template>
                        </template>

                        <tr v-if="rows.length > 0" class="bg-gray-100 font-semibold">
                            <td class="border border-gray-300 px-3 py-2 text-xs text-gray-600">小計</td>
                            <td v-for="stage in stages" :key="stage.key" class="border border-gray-300 px-3 py-2 text-right text-sm text-gray-700">{{ formatMinutes(stageTotal(stage.key)) }}</td>
                            <td class="border border-gray-300 px-3 py-2 text-right text-sm text-indigo-700">{{ formatMinutes(grandTotal) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-if="rows.length === 0" class="mt-4 text-sm text-gray-400">項目がありません。</p>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
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
const stages     = computed(() => props.sheet.stage_config?.stages ?? []);

// ── 行グループ化 ──────────────────────────────────────────────────────────
const topLevelRows = computed(() => props.rows.filter((r) => !r.parent_id));

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

function getCell(rowId, stageKey) {
    return localCells.value.find((c) => c.row_id === rowId && c.stage_key === stageKey) ?? null;
}

function getLinkedCell(rowId, stage) {
    if (stage.type !== 'joblink' || !stage.linked_stage_key) return null;
    return getCell(rowId, stage.linked_stage_key);
}

function canEditCell(rowId, stage) {
    const cell = getCell(rowId, stage.key);
    if (!cell?.assigned_user_id) return false;
    return cell.assigned_user_id === props.authUserId;
}

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

async function handleCellComplete(rowId, stageKey, { cell_id }) {
    if (!cell_id) return;
    try {
        const res = await axios.post(route('user.workflow_cells.complete', { cell: cell_id }));
        const cell = localCells.value.find((c) => c.row_id === rowId && c.stage_key === stageKey);
        if (cell) {
            cell.completed_at = res.data.completed_at;
            cell.work_minutes = res.data.work_minutes;
        }
    } catch (e) {
        alert('更新に失敗しました');
    }
}
</script>
