<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import ProofCoordinatorNavigationTabs from '@/Components/Tabs/ProofCoordinatorNavigationTabs.vue';
import AssignmentForm from '@/Pages/Coordinator/ProjectJobs/JobAssign/AssignmentForm.vue';
import ProofTimelinePickerModal from '@/Components/ProofTimelinePickerModal.vue';

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

const storeUrl = computed(() => {
    const base = route('proof_coordinator.progress_sheets.assign_store', { sheet: props.sheet.id });
    const qs   = new URLSearchParams();
    if (props.rowId)              qs.append('row_id',           String(props.rowId));
    if (props.colKey)             qs.append('col_key',          props.colKey);
    if (props.proofRequest?.id)   qs.append('proof_request_id', String(props.proofRequest.id));
    const qsStr = qs.toString();
    return qsStr ? `${base}?${qsStr}` : base;
});

// ─────────────────────────────────────────────────────────────────
//  タイムラインピッカーモーダル
// ─────────────────────────────────────────────────────────────────
const assignmentFormRef  = ref(null);
const showPickerModal    = ref(false);
const pickerInitialUser  = ref(null);

function openPicker() {
    const uid = assignmentFormRef.value?.getSelectedUserId() ?? null;
    pickerInitialUser.value = uid ? Number(uid) : null;
    showPickerModal.value = true;
}

function onPickerUserSelected(userId) {
    assignmentFormRef.value?.setSelectedUser(userId);
}

function onPickerConfirmed({ newSlots }) {
    if (newSlots.length > 0 && !assignmentFormRef.value?.getSelectedUserId()) {
        assignmentFormRef.value?.setSelectedUser(newSlots[0].userId);
    }
    for (const slot of newSlots) {
        assignmentFormRef.value?.addExternalWorkSlot(slot);
    }
    showPickerModal.value = false;
}
</script>

<template>
    <AppLayout title="校正担当者のアサイン">
        <template #header>
            <div class="flex items-center gap-3">
                <Link
                    :href="route('proof_coordinator.progress_sheets.show', { sheet: sheet.id })"
                    class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 whitespace-nowrap hover:bg-gray-300"
                >← 進行表に戻る</Link>
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

            <!-- 校正依頼情報（読み取り専用） -->
            <div v-if="proofRequest" class="rounded border border-pink-100 bg-pink-50 p-4 text-sm">
                <p class="mb-1 font-semibold text-pink-700">校正依頼情報</p>
                <dl class="grid grid-cols-2 gap-x-6 gap-y-1 text-gray-700 sm:grid-cols-3">
                    <div>
                        <dt class="text-xs font-medium text-gray-500">依頼者</dt>
                        <dd>{{ proofRequest.requester_name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500">校正締め切り</dt>
                        <dd :class="proofRequest.deadline && new Date(proofRequest.deadline) < new Date() ? 'font-bold text-red-600' : ''">
                            {{ fmtDeadline(proofRequest.deadline) }}
                        </dd>
                    </div>
                    <div v-if="proofRequest.note">
                        <dt class="text-xs font-medium text-gray-500">備考</dt>
                        <dd class="truncate">{{ proofRequest.note }}</dd>
                    </div>
                </dl>
            </div>

            <!-- ジョブ割り当てフォーム -->
            <div class="rounded bg-white px-4 py-6 sm:p-6 shadow">
                <p class="mb-4 text-sm text-gray-500">
                    ※ 担当者を選択してください。選択できるのは校正チームのメンバーまたは単発派遣です。<br>
                    ※ 作業詳細（種別・サイズ・ステージ等）は依頼者のジョブから引き継いでいます。必要に応じて修正してください。
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
                    :show-work-slots="true"
                    :backUrl="route('proof_coordinator.progress_sheets.show', { sheet: sheet.id })"
                    @open-calendar="openPicker"
                />
            </div>

        </div>

        <!-- タイムラインピッカーモーダル（校正担当者のスケジュール確認） -->
        <ProofTimelinePickerModal
            :show="showPickerModal"
            :initialUserId="pickerInitialUser"
            @close="showPickerModal = false"
            @user-selected="onPickerUserSelected"
            @confirmed="onPickerConfirmed"
        />

    </AppLayout>
</template>
