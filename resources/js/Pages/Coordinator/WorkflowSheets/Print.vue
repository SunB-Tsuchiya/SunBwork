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

// デフォルト行の row_id（_default ラベルまたは最初の行）
const defaultRowId = computed(() =>
    localRows.value.find(r => r.label === '_default')?.id ?? localRows.value[0]?.id ?? null
);

// ── 縦積みレイアウト判定 ──────────────────────────────────────────
const stageRows = computed(() =>
    columnConfig.value.filter(n => n.type === 'stage' && n.children?.length > 0)
);
const useVerticalLayout = computed(() => stageRows.value.length > 0);

// 列ヘッダー: 先頭ステージの子ノードを基準にする
const verticalColumns = computed(() =>
    stageRows.value.length ? stageRows.value[0].children : []
);

// 非縦積み（フラット）時のリーフ列
function getLeaves(nodes) {
    const result = [];
    (nodes ?? []).forEach(n => {
        if (!n.children?.length) result.push(n);
        else result.push(...getLeaves(n.children));
    });
    return result;
}
const flatLeafCols = computed(() => getLeaves(columnConfig.value));

// ── セル参照 ─────────────────────────────────────────────────────
function getCellByStageKey(stageKey) {
    if (!stageKey || defaultRowId.value === null) return {};
    return localCells.value.find(c => c.row_id === defaultRowId.value && c.stage_key === stageKey) ?? {};
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
    const rounded = Math.round(m / 10) * 10;
    const h = Math.floor(rounded / 60), mn = rounded % 60;
    if (h > 0 && mn > 0) return `${h}H${mn}m`;
    if (h > 0) return `${h}H`;
    return `${mn}m`;
}

function cellDisplay(cell, col) {
    if (!cell || !cell.id) return '';
    const t = col?.type;
    if (t === 'worker' || t === 'coordinator') {
        const name = cell.value_user_name ?? cell.value_subcontractor_name ?? '';
        const mins = (cell.work_minutes ?? 0) + (cell.proof_work_minutes ?? 0);
        const parts = [name];
        if (cell.assignment_completed || cell.completed_at) parts.push('✓');
        if (mins) parts.push(fmtMin(mins));
        return parts.filter(Boolean).join(' ');
    }
    if (t === 'proof_v2' || t === 'proof_user') {
        const name = cell.proof_assignment_title ?? cell.value_user_name ?? '';
        const mins = cell.proof_work_minutes ?? 0;
        const parts = [name];
        if (cell.proof_assignment_completed || cell.completed_at) parts.push('✓');
        if (mins) parts.push(fmtMin(mins));
        return parts.filter(Boolean).join(' ');
    }
    if (t === 'schedlink') return cell.schedule_name ?? '';
    if (t === 'date') return cell.value_date ?? '';
    if (t === 'bool') return cell.value_bool ? '✓' : '';
    return cell.value_text ?? '';
}

function cellCompleted(cell) {
    return !!(cell?.completed_at || cell?.assignment_completed || cell?.proof_assignment_completed);
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

            <!-- テーブル（縦積みレイアウト） -->
            <template v-if="useVerticalLayout">
                <div v-if="!stageRows.length" class="py-8 text-center text-gray-400">列が定義されていません。</div>
                <table v-else class="w-full border-collapse border border-gray-400 text-xs">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="border border-gray-400 px-2 py-1 text-left font-semibold" style="min-width:100px;">ステージ</th>
                            <th
                                v-for="col in verticalColumns"
                                :key="col.key"
                                class="border border-gray-400 px-2 py-1 text-center font-semibold"
                                style="min-width:90px;"
                            >{{ col.label }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="stage in stageRows" :key="stage.key">
                            <td class="border border-gray-400 px-2 py-1 font-semibold text-gray-700 bg-gray-50">
                                {{ stage.item_label ? `${stage.item_label} / ${stage.label}` : stage.label }}
                            </td>
                            <td
                                v-for="(colTemplate, ci) in verticalColumns"
                                :key="colTemplate.key"
                                class="border border-gray-400 px-2 py-1 text-center"
                                :class="cellCompleted(getCellByStageKey(stage.children[ci]?.key)) ? 'bg-green-50' : ''"
                            >
                                {{ cellDisplay(getCellByStageKey(stage.children[ci]?.key), stage.children[ci]) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </template>

            <!-- テーブル（フラットレイアウト） -->
            <template v-else>
                <div v-if="!flatLeafCols.length" class="py-8 text-center text-gray-400">列が定義されていません。</div>
                <table v-else class="w-full border-collapse border border-gray-400 text-xs">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="border border-gray-400 px-2 py-1 text-left font-semibold" style="min-width:80px;">行</th>
                            <th
                                v-for="col in flatLeafCols"
                                :key="col.key"
                                class="border border-gray-400 px-2 py-1 text-center font-semibold"
                                style="min-width:90px;"
                            >{{ col.label }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="border border-gray-400 px-2 py-1 text-gray-500 bg-gray-50">—</td>
                            <td
                                v-for="col in flatLeafCols"
                                :key="col.key"
                                class="border border-gray-400 px-2 py-1 text-center"
                                :class="cellCompleted(getCellByStageKey(col.key)) ? 'bg-green-50' : ''"
                            >
                                {{ cellDisplay(getCellByStageKey(col.key), col) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </template>
        </div>
    </div>
</template>

<style>
@media print {
    .no-print { display: none !important; }
    @page { margin: 15mm; }
}
</style>
