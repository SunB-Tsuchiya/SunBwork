<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    sheet:      { type: Object, required: true },
    token:      { type: String, required: true },
    rows:       { type: Array,  default: () => [] },
    cells:      { type: Array,  default: () => [] },
    projectJob: { type: Object, required: true },
});

const columnConfig = computed(() => props.sheet.column_config ?? []);

const topLevelRows = computed(() => props.rows.filter(r => !r.parent_id));
const childrenOf   = computed(() => {
    const map = {};
    props.rows.forEach(r => {
        if (r.parent_id) {
            if (!map[r.parent_id]) map[r.parent_id] = [];
            map[r.parent_id].push(r);
        }
    });
    return map;
});

function getCell(rowId, stageKey) {
    return props.cells.find(c => c.row_id === rowId && c.stage_key === stageKey) ?? {};
}

function colHeaderClass(col) {
    const t = col.type;
    if (t === 'coordinator') return 'bg-green-50 text-green-700';
    if (t === 'proof_v2')    return 'bg-red-50 text-red-700';
    if (t === 'schedlink')   return 'bg-purple-50 text-purple-700';
    return 'bg-indigo-50 text-indigo-700';
}

function cellDisplay(cell, col) {
    if (!cell?.completed_at && !cell?.value_user_name && !cell?.value_text && !cell?.value_date && cell?.value_bool === null) return '';
    const t = col.type;
    if (t === 'worker' || t === 'coordinator') {
        const parts = [cell.value_user_name, cell.value_subcontractor_name].filter(Boolean);
        const name = parts.join(' / ') || '';
        return cell.assignment_completed ? `✓ ${name}` : name;
    }
    if (t === 'proof_v2') return cell.assignment_completed ? '✓ 完了' : (cell.value_user_name ?? '');
    if (t === 'schedlink') {
        const parts = [cell.schedule_name, cell.schedule_end_date].filter(Boolean);
        return parts.join(' ');
    }
    if (t === 'date') return cell.value_date ?? '';
    if (t === 'bool') return cell.value_bool ? '✓' : '';
    return cell.value_text ?? '';
}

function cellBg(cell, col) {
    if (cell?.assignment_completed || cell?.completed_at) return 'bg-green-50/50';
    const t = col.type;
    if (t === 'coordinator') return 'bg-green-50/20';
    if (t === 'proof_v2')    return 'bg-red-50/20';
    if (t === 'schedlink')   return 'bg-purple-50/20';
    return '';
}

const printDate = computed(() => {
    const d = new Date();
    return `${d.getFullYear()}/${String(d.getMonth() + 1).padStart(2, '0')}/${String(d.getDate()).padStart(2, '0')}`;
});
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <!-- ツールバー -->
        <div class="sticky top-0 z-10 bg-white border-b shadow-sm px-4 py-2 flex items-center justify-between no-print">
            <div>
                <span class="text-xs text-gray-400">管理シート（読み取り専用・共有リンク）</span>
            </div>
            <button
                type="button"
                class="rounded bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700"
                @click="window.print()"
            >印刷</button>
        </div>

        <div class="px-4 py-4 max-w-7xl mx-auto">
            <!-- ヘッダー -->
            <div class="mb-4 rounded bg-white p-4 shadow">
                <div class="flex flex-wrap items-baseline gap-x-3 gap-y-0.5">
                    <span v-if="projectJob.client_name" class="text-sm font-medium text-gray-600">{{ projectJob.client_name }}</span>
                    <span v-if="projectJob.client_name" class="text-gray-300 text-sm">/</span>
                    <span class="text-base font-semibold text-gray-900">{{ projectJob.title }}</span>
                </div>
                <h1 class="mt-1 text-xl font-bold text-gray-800">{{ sheet.name }}</h1>
                <p class="mt-0.5 text-xs text-gray-400">{{ printDate }} 時点</p>
            </div>

            <!-- テーブル -->
            <div class="rounded bg-white shadow overflow-x-auto">
                <div v-if="!columnConfig.length" class="py-8 text-center text-sm text-gray-400">列が定義されていません。</div>
                <table v-else class="w-full border-collapse border border-gray-300 text-sm">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="border border-gray-300 px-3 py-2 text-left text-xs font-semibold text-gray-600 whitespace-nowrap" style="min-width:140px;">項目 / 行</th>
                            <th
                                v-for="col in columnConfig"
                                :key="col.key"
                                class="border border-gray-300 px-3 py-2 text-center text-xs font-semibold whitespace-nowrap"
                                :class="colHeaderClass(col)"
                                style="min-width:140px;"
                            >{{ col.label }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="row in topLevelRows" :key="row.id">
                            <!-- グループ見出し -->
                            <tr class="bg-gray-50">
                                <td :colspan="columnConfig.length + 1" class="border border-gray-300 px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    {{ row.label }}
                                </td>
                            </tr>

                            <!-- 子行 -->
                            <tr
                                v-for="child in (childrenOf[row.id] ?? [])"
                                :key="child.id"
                                class="hover:bg-gray-50"
                            >
                                <td class="border border-gray-300 py-1.5 pl-5 pr-2 text-gray-800">{{ child.label }}</td>
                                <td
                                    v-for="col in columnConfig"
                                    :key="col.key"
                                    class="border border-gray-300 px-3 py-1.5 text-center text-sm"
                                    :class="cellBg(getCell(child.id, col.key), col)"
                                >{{ cellDisplay(getCell(child.id, col.key), col) }}</td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<style>
@media print {
    .no-print { display: none !important; }
    .sticky { position: static !important; }
    body { background: white !important; }
    @page { margin: 15mm; }
}
</style>
