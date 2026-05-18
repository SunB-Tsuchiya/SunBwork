<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import ProofCoordinatorNavigationTabs from '@/Components/Tabs/ProofCoordinatorNavigationTabs.vue';
import AssignmentForm from '@/Pages/Coordinator/ProjectJobs/JobAssign/AssignmentForm.vue';

const props = defineProps({
    sheet:             { type: Object, required: true },
    projectJob:        { type: Object, default: null },
    rowId:             { type: Number, default: null },
    colKey:            { type: String, default: null },
    members:           { type: Array,  default: () => [] },
    assignments:       { type: Array,  default: () => [] },
    types:             { type: Array,  default: () => [] },
    sizes:             { type: Array,  default: () => [] },
    stages:            { type: Array,  default: () => [] },
    statuses:          { type: Array,  default: () => [] },
    difficulties:      { type: Array,  default: () => [] },
    companies:         { type: Array,  default: () => [] },
    user_role:         { type: String, default: '' },
    user_company_id:   { type: [Number, String], default: null },
    user_department_id:{ type: [Number, String], default: null },
    proofRequest:      { type: Object, default: null },
});

// row_id / col_key / proof_request_id をクエリパラメータとして storeUrl に付加する
const storeUrl = computed(() => {
    const base = route('proof_coordinator.workflow_sheets.assign_store', { sheet: props.sheet.id });
    const qs   = new URLSearchParams();
    if (props.rowId)              qs.append('row_id',           String(props.rowId));
    if (props.colKey)             qs.append('col_key',          props.colKey);
    if (props.proofRequest?.id)   qs.append('proof_request_id', String(props.proofRequest.id));
    const qsStr = qs.toString();
    return qsStr ? `${base}?${qsStr}` : base;
});

const assignmentFormRef = ref(null);
</script>

<template>
    <AppLayout title="校正担当者のアサイン">
        <template #header>
            <div class="flex items-center gap-3">
                <Link
                    :href="route('proof_coordinator.workflow_sheets.show', { sheet: sheet.id })"
                    class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 whitespace-nowrap hover:bg-gray-300"
                >← 管理シートに戻る</Link>
                <div>
                    <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">
                        校正担当者のアサイン
                    </h2>
                    <p class="text-sm text-gray-500">{{ sheet.name }}</p>
                </div>
            </div>
        </template>
        <template #tabs>
            <ProofCoordinatorNavigationTabs active="inbox" />
        </template>

        <div class="mx-auto max-w-3xl space-y-4">
            <!-- 校正依頼情報パネル -->
            <div v-if="proofRequest" class="rounded border border-pink-200 bg-pink-50 px-4 py-3 text-sm">
                <div class="mb-1 font-semibold text-pink-700">校正依頼: {{ proofRequest.title }}</div>
                <div class="flex flex-wrap gap-4 text-gray-600">
                    <span v-if="proofRequest.deadline">締切: <strong>{{ proofRequest.deadline }}</strong></span>
                    <span v-if="proofRequest.note">備考: {{ proofRequest.note }}</span>
                </div>
            </div>

            <div class="rounded bg-white px-4 py-6 sm:p-6 shadow">
                <p class="mb-4 text-sm text-gray-500">
                    校正担当者を選択してください。選択できるのは校正チームのメンバーまたは単発派遣です。
                </p>

                <AssignmentForm
                    ref="assignmentFormRef"
                    mode="coordinator"
                    :projectJob="projectJob"
                    :members="members"
                    :assignments="assignments"
                    :editMode="true"
                    :hide-status="true"
                    :storeOverrideUrl="storeUrl"
                    :backUrl="route('proof_coordinator.workflow_sheets.show', { sheet: sheet.id })"
                />
            </div>
        </div>
    </AppLayout>
</template>
