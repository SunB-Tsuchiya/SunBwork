<script setup>
import { computed } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';

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

function tryRoute(name) {
    try { return route(name); } catch { return null; }
}

const tabs = computed(() => [
    { key: 'dashboard', href: tryRoute('user.dashboard'), label: 'ダッシュボード' },
    { key: 'project_jobs', href: tryRoute('user.project_jobs.index'), label: '案件確認' },
    { key: 'myjob', href: tryRoute('user.myjobbox.index'), label: 'マイジョブBOX' },
    { key: 'jobbox', href: tryRoute('user.jobbox.index'), label: '依頼されたジョブ' },
    { key: 'diaries', href: tryRoute('diaries.index'), label: '日報一覧' },
    { key: 'calendar', href: tryRoute('calendar.index'), label: 'カレンダー' },
    { key: 'proof_status', href: tryRoute('user.proof.status'), label: '校正状況' },
    { key: 'proof_jobs', href: tryRoute('user.proof_jobs.index'), label: '校正ジョブ', condition: isProofMember.value },
    { key: 'settings', href: tryRoute('user.settings.index'), label: '設定' },
].filter(t => t.condition !== false && t.href));

function onMobileSelect(e) {
    const href = e.target.value;
    if (href) router.get(href);
}
</script>

<template>
    <div class="mb-6">
        <!-- モバイル: ドロップダウン -->
        <div class="sm:hidden">
            <select
                @change="onMobileSelect"
                class="w-full rounded-md border border-blue-300 bg-white px-3 py-2 text-sm text-blue-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            >
                <option value="">— ページを選択 —</option>
                <option
                    v-for="t in tabs"
                    :key="t.key"
                    :value="t.href"
                    :selected="active === t.key"
                >{{ t.label }}</option>
            </select>
        </div>

        <!-- デスクトップ: タブ -->
        <nav class="hidden sm:flex flex-wrap gap-2" aria-label="Tabs">
            <Link :href="route('user.dashboard')" :class="tab('dashboard')">
                ダッシュボード
            </Link>
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
            <Link :href="route('user.proof.status')" :class="tab('proof_status')">
                校正状況
            </Link>
            <Link
                v-if="isProofMember"
                :href="route('user.proof_jobs.index')"
                :class="['rounded-md px-3 py-2 text-sm font-medium', props.active === 'proof_jobs' ? 'bg-pink-100 text-pink-700' : 'border border-pink-200 text-pink-600 hover:bg-pink-50 hover:text-pink-800']"
            >
                校正ジョブ
            </Link>
            <Link :href="route('user.settings.index')" :class="tab('settings')">
                設定
            </Link>
        </nav>
    </div>
</template>
