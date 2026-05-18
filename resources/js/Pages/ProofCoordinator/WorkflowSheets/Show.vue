<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import ProofCoordinatorNavigationTabs from '@/Components/Tabs/ProofCoordinatorNavigationTabs.vue';

const props = defineProps({
    sheet:             { type: Object, required: true },
    projectJob:        { type: Object, required: true },
    defaultRowId:      { type: Number, required: true },
    tableRows:         { type: Array,  default: () => [] },
    cells:             { type: Array,  default: () => [] },
    members:           { type: Array,  default: () => [] },
    types:             { type: Array,  default: () => [] },
    sizes:             { type: Array,  default: () => [] },
    stages:            { type: Array,  default: () => [] },
    statuses:          { type: Array,  default: () => [] },
    difficulties:      { type: Array,  default: () => [] },
    user_role:         { type: String, default: '' },
    user_company_id:   { type: [Number, String], default: null },
    user_department_id:{ type: [Number, String], default: null },
    proofRequestId:    { type: Number, default: null },
    proofRequestData:  { type: Object, default: null },
    targetProofKeys:   { type: Array,  default: null },
});

// 全行の proof_cols から一意の列ラベルを順序付きで収集
const proofColumnLabels = computed(() => {
    const seen = new Set();
    const result = [];
    for (const row of props.tableRows) {
        for (const col of row.proof_cols) {
            if (!seen.has(col.label)) {
                seen.add(col.label);
                result.push(col.label);
            }
        }
    }
    return result;
});

// (row, colLabel) に対応するセルを取得
function getCellForRowCol(row, colLabel) {
    const col = row.proof_cols.find(c => c.label === colLabel);
    if (!col) return null;
    return props.cells.find(c => c.row_id === row.row_id && c.stage_key === col.key) ?? null;
}

// (row, colLabel) に対応する col.key を取得
function getColKey(row, colLabel) {
    return row.proof_cols.find(c => c.label === colLabel)?.key ?? null;
}

// column_config を再帰走査して leafKey のラベルパスを返す
function getLeafPath(leafKey, nodes = props.sheet.column_config, path = []) {
    for (const node of nodes) {
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

// stage_id を leafKey から取得
function findStageIdForLeaf(leafKey, nodes = props.sheet.column_config) {
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

// proofRequestId に対応するセルのステージキーを特定
const highlightedStageKey = computed(() => {
    if (!props.proofRequestId) return null;
    const cell = props.cells.find(c => c.proof_request_id === props.proofRequestId);
    return cell?.stage_key ?? null;
});

function isCellHighlighted(row, colLabel) {
    if (!highlightedStageKey.value) return false;
    const col = row.proof_cols.find(c => c.label === colLabel);
    return col?.key === highlightedStageKey.value;
}

// proof_request_id 指定時: targetProofKeys に含まれる列のみ操作可能
function isTargetCell(row, colLabel) {
    if (!props.targetProofKeys) return true;
    const col = row.proof_cols.find(c => c.label === colLabel);
    if (!col) return false;
    return props.targetProofKeys.includes(col.key);
}

function handleAssign(row, colLabel) {
    if (!isTargetCell(row, colLabel)) return;
    const colKey = getColKey(row, colLabel);
    if (!colKey) return;

    const leafPath = getLeafPath(colKey) ?? [];
    const jobTitle = [props.projectJob.title, ...leafPath].filter(Boolean).join('_');
    const stageId  = findStageIdForLeaf(colKey);
    const cell     = getCellForRowCol(row, colLabel);

    const params = {
        sheet:   props.sheet.id,
        title:   jobTitle,
        row_id:  row.row_id,
        col_key: colKey,
    };
    if (stageId) params.stage_id = stageId;
    if (cell?.proof_request_id) {
        params.proof_request_id = cell.proof_request_id;
    } else if (props.proofRequestId) {
        params.proof_request_id = props.proofRequestId;
    }

    router.visit(route('proof_coordinator.workflow_sheets.assign_page', params));
}
</script>

<template>
    <AppLayout :title="`管理シート（校正）: ${sheet.name}`">
        <template #header>
            <div class="flex items-center gap-3">
                <Link
                    :href="route('proof_coordinator.inbox')"
                    class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300 whitespace-nowrap"
                >← 受信箱に戻る</Link>
                <div>
                    <h2 class="text-lg font-semibold text-gray-800">{{ sheet.name }}</h2>
                    <p class="text-sm text-gray-500">{{ projectJob.client_name }} / {{ projectJob.title }}</p>
                </div>
            </div>
        </template>
        <template #tabs>
            <ProofCoordinatorNavigationTabs active="inbox" />
        </template>

        <!-- 校正依頼パネル（proof_request_id 指定時） -->
        <div v-if="proofRequestData" class="mb-4 rounded border border-pink-200 bg-pink-50 p-4 text-sm">
            <div class="mb-1 font-semibold text-pink-700">校正依頼: {{ proofRequestData.title }}</div>
            <div class="flex flex-wrap gap-4 text-gray-600">
                <span v-if="proofRequestData.deadline">締切: <strong>{{ proofRequestData.deadline }}</strong></span>
                <span v-if="proofRequestData.requester_name">依頼者: <strong>{{ proofRequestData.requester_name }}</strong></span>
                <span v-if="proofRequestData.note">備考: {{ proofRequestData.note }}</span>
            </div>
        </div>

        <div class="rounded bg-white p-6 shadow">
            <div v-if="tableRows.length === 0" class="py-8 text-center text-sm text-gray-400">
                このシートに校正（proof_v2）列がありません
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full border-collapse text-sm">
                    <thead>
                        <tr class="bg-red-50 text-left text-xs font-medium text-red-700">
                            <th class="border border-gray-200 px-3 py-2 min-w-[120px]">項目</th>
                            <th
                                v-for="label in proofColumnLabels"
                                :key="label"
                                class="border border-gray-200 px-3 py-2 min-w-[160px]"
                            >{{ label }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(row, ri) in tableRows"
                            :key="ri"
                            class="hover:bg-red-50/30"
                        >
                            <!-- 項目列 -->
                            <td class="border border-gray-200 px-3 py-2 font-medium text-gray-800 bg-gray-50">
                                <span v-if="row.item_label" class="block text-xs text-gray-500">{{ row.item_label }}</span>
                                {{ row.stage_label }}
                            </td>

                            <!-- 各校正列 -->
                            <td
                                v-for="label in proofColumnLabels"
                                :key="label"
                                class="border border-gray-200 px-3 py-2 transition-colors"
                                :class="{ 'bg-pink-50 ring-1 ring-inset ring-pink-300': isCellHighlighted(row, label) }"
                            >
                                <!-- 依頼バッジ -->
                                <div v-if="getCellForRowCol(row, label)?.proof_request_id" class="mb-1">
                                    <span class="inline-flex items-center rounded-full bg-pink-100 px-2 py-0.5 text-xs font-bold text-pink-700">依頼中</span>
                                </div>

                                <!-- アサイン済み -->
                                <template v-if="getCellForRowCol(row, label)?.proof_assignment_id">
                                    <div class="flex flex-col gap-1">
                                        <div class="flex items-center gap-2">
                                            <span class="font-medium text-gray-800">
                                                {{ getCellForRowCol(row, label).proof_assignment_user_name ?? '—' }}
                                            </span>
                                            <span
                                                v-if="getCellForRowCol(row, label).proof_assignment_completed"
                                                class="rounded-full bg-green-100 px-1.5 py-0.5 text-xs font-bold text-green-700"
                                            >完了</span>
                                            <span
                                                v-else
                                                class="rounded-full bg-blue-100 px-1.5 py-0.5 text-xs font-bold text-blue-700"
                                            >登録済</span>
                                        </div>
                                        <p class="text-xs text-gray-500 truncate max-w-[200px]" :title="getCellForRowCol(row, label).proof_assignment_title">
                                            {{ getCellForRowCol(row, label).proof_assignment_title }}
                                        </p>
                                        <button
                                            class="mt-1 self-start rounded border border-gray-300 px-2 py-0.5 text-xs text-gray-600 hover:bg-gray-100"
                                            @click="handleAssign(row, label)"
                                        >+ 追加アサイン</button>
                                    </div>
                                </template>

                                <!-- 未アサイン -->
                                <template v-else>
                                    <!-- 対象列が存在しない行 -->
                                    <span
                                        v-if="!getColKey(row, label)"
                                        class="text-xs text-gray-300"
                                    >—</span>
                                    <!-- 対象外セル（グレーアウト） -->
                                    <span
                                        v-else-if="!isTargetCell(row, label)"
                                        class="text-xs text-gray-300 cursor-default select-none"
                                    >—</span>
                                    <!-- アサイン可能 -->
                                    <button
                                        v-else
                                        class="rounded border border-dashed border-red-300 px-3 py-1 text-xs text-red-500 hover:border-red-500 hover:bg-red-50 transition-colors"
                                        :class="{ 'border-pink-400 text-pink-600 hover:bg-pink-50': getCellForRowCol(row, label)?.proof_request_id }"
                                        @click="handleAssign(row, label)"
                                    >+ 担当者</button>
                                </template>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
