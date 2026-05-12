<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import UserNavigationTabs from '@/Components/Tabs/UserNavigationTabs.vue';
import AssignmentForm from '@/Pages/Coordinator/ProjectJobs/JobAssign/AssignmentForm.vue';
import { Link } from '@inertiajs/vue3';

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
    proofRequest:        { type: Object, required: true },
    assignment:          { type: Object, default: null },
    projectJob:          { type: Object, default: null },
    members:             { type: Array,  default: () => [] },
    assignments_data:    { type: Array,  default: () => [] },
    existingSlots:       { type: Array,  default: () => [] },
    types:               { type: Array,  default: () => [] },
    sizes:               { type: Array,  default: () => [] },
    stages:              { type: Array,  default: () => [] },
    statuses:            { type: Array,  default: () => [] },
    difficulties:        { type: Array,  default: () => [] },
    companies:           { type: Array,  default: () => [] },
    user_role:           { type: String, default: '' },
    user_company_id:     { type: [Number, String], default: null },
    user_department_id:  { type: [Number, String], default: null },
});

const updateUrl = route('user.proof_jobs.set', { proofRequest: props.proofRequest.id });
</script>

<template>
    <AppLayout title="校正をセット">
        <template #header>
            <div class="flex items-center gap-3">
                <Link
                    :href="route('user.proof_jobs.index')"
                    class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 whitespace-nowrap hover:bg-gray-300"
                >
                    ← 一覧に戻る
                </Link>
                <h2 class="text-xl font-semibold text-gray-800">校正をセット</h2>
            </div>
        </template>
        <template #tabs>
            <UserNavigationTabs active="proof_jobs" />
        </template>

        <div class="space-y-4">

            <!-- 校正依頼情報（読み取り専用） -->
            <div class="rounded border border-pink-100 bg-pink-50 p-4 text-sm">
                <p class="mb-1 font-semibold text-pink-700">校正依頼情報</p>
                <dl class="grid grid-cols-2 gap-x-6 gap-y-1 text-gray-700 sm:grid-cols-3">
                    <div>
                        <dt class="text-xs font-medium text-gray-500">依頼者</dt>
                        <dd>{{ proofRequest.requester?.name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500">関連案件</dt>
                        <dd>{{ proofRequest.project_job?.title ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500">校正締め切り</dt>
                        <dd :class="proofRequest.deadline && new Date(proofRequest.deadline) < new Date() ? 'font-bold text-red-600' : ''">
                            {{ fmtDeadline(proofRequest.deadline) }}
                        </dd>
                    </div>
                    <div v-if="proofRequest.note" class="col-span-2 sm:col-span-3">
                        <dt class="text-xs font-medium text-gray-500">備考</dt>
                        <dd class="whitespace-pre-wrap text-gray-700">{{ proofRequest.note }}</dd>
                    </div>
                </dl>
            </div>

            <!-- ジョブ割り当てフォーム（作業時間スロット付き） -->
            <div class="rounded bg-white px-4 py-6 sm:p-6 shadow">
                <p class="mb-4 text-sm text-gray-500">
                    ※ 作業詳細と作業日・時間を入力して保存してください。
                </p>
                <AssignmentForm
                    mode="coordinator"
                    :projectJob="projectJob"
                    :members="members"
                    :assignments="assignments_data"
                    :editMode="true"
                    :hide-status="true"
                    :save-only="true"
                    :update-override-url="updateUrl"
                    :show-work-slots="true"
                    :initial-work-slots="existingSlots"
                />
            </div>

        </div>
    </AppLayout>
</template>
