<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    sheet:      { type: Object, required: true },
    rows:       { type: Array,  default: () => [] },
    cells:      { type: Array,  default: () => [] },
    stages:     { type: Array,  default: () => [] },
    projectJob: { type: Object, required: true },
});

const localRows  = ref(props.rows.map(r => ({ ...r })));
const localCells = ref(props.cells.map(c => ({ ...c })));

const columnConfig = computed(() => props.sheet.column_config ?? []);

const topLevelRows = computed(() => localRows.value.filter(r => !r.parent_id));
const childrenOf   = computed(() => {
    const map = {};
    localRows.value.forEach(r => {
        if (r.parent_id) {
            if (!map[r.parent_id]) map[r.parent_id] = [];
            map[r.parent_id].push(r);
        }
    });
    return map;
});

function getCell(rowId, stageKey) {
    return localCells.value.find(c => c.row_id === rowId && c.stage_key === stageKey) ?? {};
}

function stageName(stageId) {
    return props.stages.find(s => s.id === stageId)?.name ?? '';
}

const printDate = computed(() => {
    const d = new Date();
    return `${d.getFullYear()}/${String(d.getMonth() + 1).padStart(2, '0')}/${String(d.getDate()).padStart(2, '0')}`;
});

function fmtMin(m) {
    if (!m) return '';
    const h = Math.floor(m / 60), mn = m % 60;
    return h > 0 ? `${h}h${mn > 0 ? mn + 'm' : ''}` : `${mn}m`;
}

function cellDisplay(cell, col) {
    if (!cell || !cell.id) return '';
    const t = col.type;
    if (t === 'worker' || t === 'coordinator') {
        return cell.value_user_name ?? cell.value_subcontractor_name ?? '';
    }
    if (t === 'proof_v2') return cell.proof_assignment_title ?? cell.value_user_name ?? '';
    if (t === 'schedlink') return cell.schedule_name ?? '';
    if (t === 'date') return cell.value_date ?? '';
    if (t === 'bool') return cell.value_bool ? '✓' : '';
    return cell.value_text ?? '';
}

function cellCompleted(cell) {
    return !!(cell?.completed_at);
}
</script>

<template>
    <div class="min-h-screen bg-white">
        <!-- 操作パネル（印刷非表示） -->
        <div class="no-print bg-gray-100 border-b px-4 py-2 flex items-center gap-3">
            <span class="text-sm text-gray-600">印刷プレビュー — 管理シート</span>
            <button
                type="button"
                class="rounded bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700"
                @click="window.print()"
            >印刷を実行</button>
        </div>

        <div class="px-6 py-4">
            <!-- ヘッダー -->
            <div class="mb-4 border-b border-gray-300 pb-3">
                <div class="flex flex-wrap items-baseline gap-x-3 gap-y-0.5">
                    <span v-if="projectJob.client_name" class="text-sm font-medium text-gray-700">{{ projectJob.client_name }}</span>
                    <span v-if="projectJob.client_name" class="text-gray-400 text-sm">/</span>
                    <span class="text-base font-semibold text-gray-900">{{ projectJob.title }}</span>
                </div>
                <h1 class="mt-1 text-lg font-bold text-gray-800">{{ sheet.name }}</h1>
                <p class="mt-0.5 text-xs text-gray-400">印刷日: {{ printDate }}</p>
            </div>

            <!-- テーブル -->
            <div v-if="!columnConfig.length" class="py-8 text-center text-gray-400">列が定義されていません。</div>
            <table v-else class="w-full border-collapse border border-gray-400 text-xs">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="border border-gray-400 px-2 py-1 text-left font-semibold" style="min-width:120px;">項目 / 行</th>
                        <th
                            v-for="col in columnConfig"
                            :key="col.key"
                            class="border border-gray-400 px-2 py-1 text-center font-semibold"
                            style="min-width:100px;"
                        >{{ col.label }}</th>
                        <th class="border border-gray-400 px-2 py-1 text-center font-semibold" style="width:50px;">完了</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="row in topLevelRows" :key="row.id">
                        <!-- グループ見出し -->
                        <tr class="bg-gray-50">
                            <td :colspan="columnConfig.length + 2" class="border border-gray-400 px-2 py-1 font-semibold text-gray-600">
                                {{ row.label }}
                                <span v-if="row.stage_id" class="ml-2 text-xs font-normal text-gray-400">[{{ stageName(row.stage_id) }}]</span>
                            </td>
                        </tr>

                        <!-- 子行 -->
                        <tr
                            v-for="child in (childrenOf[row.id] ?? [])"
                            :key="child.id"
                        >
                            <td class="border border-gray-400 py-1 pl-4 pr-2">{{ child.label }}</td>
                            <td
                                v-for="col in columnConfig"
                                :key="col.key"
                                class="border border-gray-400 px-2 py-1 text-center"
                                :class="cellCompleted(getCell(child.id, col.key)) ? 'bg-green-50' : ''"
                            >{{ cellDisplay(getCell(child.id, col.key), col) }}</td>
                            <td class="border border-gray-400 px-2 py-1 text-center">
                                <span v-if="getCell(child.id, columnConfig[0]?.key)?.completed_at">✓</span>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
</template>

<style>
@media print {
    .no-print { display: none !important; }
    @page { margin: 15mm; }
}
</style>
