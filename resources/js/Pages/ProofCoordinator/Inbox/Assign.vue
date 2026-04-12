<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import ProofCoordinatorNavigationTabs from '@/Components/Tabs/ProofCoordinatorNavigationTabs.vue';
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
    proofRequest:      { type: Object, required: true },
    projectJob:        { type: Object, default: null },
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
});

const storeUrl = route('proof_coordinator.inbox.assign_store', { proofRequest: props.proofRequest.id });
</script>

<template>
    <AppLayout title="校正員の割り当て">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-800">校正員の割り当て</h2>
                <Link :href="route('proof_coordinator.inbox')" class="text-sm text-gray-500 hover:text-gray-700">
                    ← 受信ボックスに戻る
                </Link>
            </div>
        </template>

        <template #tabs>
            <ProofCoordinatorNavigationTabs active="inbox" />
        </template>

        <div class="space-y-4">

            <!-- 依頼情報（読み取り専用） -->
            <div class="rounded border border-pink-100 bg-pink-50 p-4 text-sm">
                <p class="mb-1 font-semibold text-pink-700">校正依頼情報</p>
                <dl class="grid grid-cols-2 gap-x-6 gap-y-1 text-gray-700 sm:grid-cols-4">
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
                    <div v-if="proofRequest.note">
                        <dt class="text-xs font-medium text-gray-500">備考</dt>
                        <dd class="truncate">{{ proofRequest.note }}</dd>
                    </div>
                </dl>
            </div>

            <!-- ジョブ割り当てフォーム -->
            <div class="rounded bg-white p-6 shadow">
                <p class="mb-4 text-sm text-gray-500">
                    ※ 担当者を選択してください。選択できるのは校正チームのメンバーまたは校正担当者です。<br>
                    ※ 作業詳細（種別・サイズ・ステージ等）は依頼者のジョブから引き継いでいます。必要に応じて修正してください。
                </p>

                <!-- types/sizes/stages/statuses/difficulties/companies は $page.props から AssignmentForm が直接読む -->
                <AssignmentForm
                    mode="coordinator"
                    :projectJob="projectJob"
                    :members="members"
                    :assignments="assignments"
                    :editMode="true"
                    :hide-status="true"
                    :storeOverrideUrl="storeUrl"
                    :show-work-slots="true"
                />
            </div>

        </div>
    </AppLayout>
</template>
