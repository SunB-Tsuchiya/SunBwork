<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
const page = usePage();
const isProofMember = computed(() => page.props.auth?.isProofMember ?? false);
const props = defineProps({
    active: { type: String, default: '' },
});
// User カラー: blue
const tab = (key) => [
    'rounded-md px-3 py-2 text-sm font-medium',
    props.active === key
        ? 'bg-blue-100 text-blue-700'
        : 'border border-blue-200 text-blue-600 hover:bg-blue-50 hover:text-blue-800',
];
</script>

<template>
    <div class="mb-6">
        <nav class="flex flex-wrap gap-2" aria-label="Tabs">
            <Link :href="route('user.project_jobs.index')" :class="tab('project_jobs')">
                案件確認
            </Link>
            <Link :href="route('user.myjobbox.index')" :class="tab('myjob')">
                マイジョブBOX
            </Link>
            <Link :href="route('user.jobbox.index')" :class="tab('jobbox')">
                依頼されたジョブ
            </Link>
            <Link :href="route('diaries.index')" :class="tab('diaries')">
                日報一覧
            </Link>
            <Link :href="route('calendar.index')" :class="tab('calendar')">
                カレンダー
            </Link>
            <!-- 校正カレンダー: 非表示（後で使用予定） -->
            <!-- <Link :href="route('proof.calendar')" :class="tab('proof_calendar')">
                校正カレンダー
            </Link> -->
            <Link :href="route('user.proof.status')" :class="tab('proof_status')">
                校正状況
            </Link>
            <Link
                v-if="isProofMember"
                :href="route('user.proof_jobs.index')"
                :class="['rounded-md px-3 py-2 text-sm font-medium', active === 'proof_jobs' ? 'bg-pink-100 text-pink-700' : 'border border-pink-200 text-pink-600 hover:bg-pink-50 hover:text-pink-800']"
            >
                校正ジョブ
            </Link>
            <Link :href="route('user.settings.index')" :class="tab('settings')">
                設定
            </Link>
        </nav>
    </div>
</template>
