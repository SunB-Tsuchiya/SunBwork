<script setup>
import { computed, ref, nextTick, onMounted } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import ProofCoordinatorNavigationTabs from '@/Components/Tabs/ProofCoordinatorNavigationTabs.vue';

function fmtDeadline(isoStr) {
    if (!isoStr) return '—';
    const fmt = new Intl.DateTimeFormat('ja-JP', {
        timeZone: 'Asia/Tokyo',
        year: 'numeric', month: 'numeric', day: 'numeric',
        hour: '2-digit', minute: '2-digit', hour12: false,
    });
    const p = Object.fromEntries(fmt.formatToParts(new Date(isoStr)).map(({ type, value }) => [type, value]));
    return `${p.year}年${p.month}月${p.day}日 ${p.hour}時${p.minute}分`;
}

const props = defineProps({
    sheet:             { type: Object, required: true },
    projectJob:        { type: Object, required: true },
    rows:              { type: Array,  default: () => [] },
    cells:             { type: Array,  default: () => [] },
    proofColumnDefs:   { type: Array,  default: () => [] },
    members:           { type: Array,  default: () => [] },
    stages:            { type: Array,  default: () => [] },
    difficulties:      { type: Array,  default: () => [] },
    types:             { type: Array,  default: () => [] },
    sizes:             { type: Array,  default: () => [] },
    statuses:          { type: Array,  default: () => [] },
    user_role:         { type: String, default: '' },
    user_company_id:   { type: [Number, String], default: null },
    user_department_id:{ type: [Number, String], default: null },
    proofRequestId:      { type: Number, default: null },
    proofRequestData:    { type: Object, default: null },
    targetRowId:         { type: Number, default: null },
    targetProofColKeys:  { type: Array,  default: null },
});

// 対象行の対象 proof 列かどうか（ハイライト・操作可否に共通使用）
function isCellHighlighted(rowId, colKey) {
    if (!props.targetRowId || !props.targetProofColKeys) return false;
    return rowId === props.targetRowId && props.targetProofColKeys.includes(colKey);
}

// proof_request_id がある場合は同ステージグループの proof 列すべてを対象とする
function isTargetCell(rowId, colKey) {
    if (props.targetRowId === null || props.targetProofColKeys === null) return true;
    return rowId === props.targetRowId && props.targetProofColKeys.includes(colKey);
}

function getCell(rowId, colKey) {
    return props.cells.find(c => c.row_id === rowId && c.col_key === colKey) ?? null;
}

function handleAssign(row, colDef) {
    if (!isTargetCell(row.id, colDef.key)) return;
    const cell = getCell(row.id, colDef.key);
    const params = {
        sheet:   props.sheet.id,
        title:   [props.projectJob.title, row.label, colDef.label].filter(Boolean).join('_'),
        row_id:  row.id,
        col_key: colDef.key,
    };
    if (cell?.proof_request_id) {
        params.proof_request_id = cell.proof_request_id;
    } else if (props.proofRequestId) {
        params.proof_request_id = props.proofRequestId;
    }
    router.visit(route('proof_coordinator.progress_sheets.assign_page', params));
}

// 対象行へのスクロール
const highlightedRowRef = ref(null);

onMounted(() => {
    nextTick(() => {
        if (highlightedRowRef.value) {
            highlightedRowRef.value.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });
});
</script>

<template>
    <AppLayout :title="`進行表（校正）: ${sheet.name}`">
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
                <span v-if="proofRequestData.deadline">
                    締切:
                    <strong :class="new Date(proofRequestData.deadline) < new Date() ? 'text-red-600' : ''">
                        {{ fmtDeadline(proofRequestData.deadline) }}
                    </strong>
                </span>
                <span v-if="proofRequestData.requester_name">依頼者: <strong>{{ proofRequestData.requester_name }}</strong></span>
                <span v-if="proofRequestData.note">備考: {{ proofRequestData.note }}</span>
            </div>
        </div>

        <div class="rounded bg-white p-6 shadow">
            <div v-if="proofColumnDefs.length === 0" class="py-8 text-center text-sm text-gray-400">
                この進行表に校正（proof_user/proof_v2）列がありません
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full border-collapse text-sm">
                    <thead>
                        <tr class="bg-red-50 text-left text-xs font-medium text-red-700">
                            <th class="border border-gray-200 px-3 py-2 min-w-[120px]">行</th>
                            <th
                                v-for="colDef in proofColumnDefs"
                                :key="colDef.key"
                                class="border border-gray-200 px-3 py-2 min-w-[160px]"
                            >{{ colDef.label }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in rows"
                            :key="row.id"
                            class="hover:bg-red-50/30"
                            :ref="(el) => { if (el && proofColumnDefs.some(c => isCellHighlighted(row.id, c.key))) highlightedRowRef = el }"
                        >
                            <!-- 行ラベル -->
                            <td class="border border-gray-200 px-3 py-2 font-medium text-gray-800 bg-gray-50 whitespace-nowrap">
                                {{ row.label }}
                            </td>

                            <!-- 各校正列 -->
                            <td
                                v-for="colDef in proofColumnDefs"
                                :key="colDef.key"
                                class="border border-gray-200 px-3 py-2 transition-colors"
                                :class="{ 'bg-pink-50 ring-1 ring-inset ring-pink-300': isCellHighlighted(row.id, colDef.key) }"
                            >
                                <!-- 依頼バッジ -->
                                <div v-if="getCell(row.id, colDef.key)?.proof_request_id" class="mb-1">
                                    <span class="inline-flex items-center rounded-full bg-pink-100 px-2 py-0.5 text-xs font-bold text-pink-700">依頼中</span>
                                </div>

                                <!-- アサイン済み -->
                                <template v-if="getCell(row.id, colDef.key)?.proof_assignment_id">
                                    <div class="flex flex-col gap-1">
                                        <div class="flex items-center gap-2">
                                            <span class="font-medium text-gray-800">
                                                {{ getCell(row.id, colDef.key).proof_assignment_user_name ?? '—' }}
                                            </span>
                                            <span
                                                v-if="getCell(row.id, colDef.key).proof_assignment_completed"
                                                class="rounded-full bg-green-100 px-1.5 py-0.5 text-xs font-bold text-green-700"
                                            >完了</span>
                                            <span
                                                v-else
                                                class="rounded-full bg-blue-100 px-1.5 py-0.5 text-xs font-bold text-blue-700"
                                            >登録済</span>
                                        </div>
                                        <p class="text-xs text-gray-500 truncate max-w-[200px]" :title="getCell(row.id, colDef.key).proof_assignment_title">
                                            {{ getCell(row.id, colDef.key).proof_assignment_title }}
                                        </p>
                                        <button
                                            v-if="isTargetCell(row.id, colDef.key)"
                                            class="mt-1 self-start rounded border border-gray-300 px-2 py-0.5 text-xs text-gray-600 hover:bg-gray-100"
                                            @click="handleAssign(row, colDef)"
                                        >+ 追加アサイン</button>
                                    </div>
                                </template>

                                <!-- 未アサイン -->
                                <template v-else>
                                    <!-- 対象セル -->
                                    <button
                                        v-if="isTargetCell(row.id, colDef.key)"
                                        class="rounded border border-dashed border-red-300 px-3 py-1 text-xs text-red-500 hover:border-red-500 hover:bg-red-50 transition-colors"
                                        :class="{ 'border-pink-400 text-pink-600 hover:bg-pink-50': getCell(row.id, colDef.key)?.proof_request_id }"
                                        @click="handleAssign(row, colDef)"
                                    >+ 担当者</button>
                                    <!-- 対象外セル（targetCell 指定時） -->
                                    <span v-else class="text-gray-300 text-xs">—</span>
                                </template>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
