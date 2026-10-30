<script setup>
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import UserWorkHubTabs from './UserWorkHubTabs.vue';

const props = defineProps({
    active: { type: String, default: '' },
});

const isSunbrain = computed(() => usePage().props.auth?.companyType === 'sunbrain');
// 一般会社（サンエー印刷等）はジョブ系タブを出さない。サン・ブレーンは従来どおり全タブ
const isGeneral = computed(() => usePage().props.auth?.companyType === 'general');
const hubEnabled = computed(() => Boolean(usePage().props.userWorkHub?.enabled) && !isGeneral.value);
const hubTab = computed(() => {
    if (hubEnabled.value) {
        try {
            const current = route().current();
            if (current === 'user.project_jobs.jobbox.show') return 'jobbox';
            if (['events.create_job', 'user.project_jobs.assignments.create', 'user.project_jobs.assignments.edit'].includes(current)) return 'myjob';
        } catch { /* Fall back to the page's explicit tab. */ }
    }
    return props.active;
});
const hubActive = computed(() => ['myjob', 'jobbox', 'broad_projects', 'project_jobs', 'proof_status'].includes(hubTab.value));
const activeKey = computed(() => hubEnabled.value && hubActive.value ? 'work_hub' : props.active);
// Reuse the existing server-calculated INFO trial gate; do not open the board to other roles.
const canAccessDepartmentWork = computed(() => usePage().props.auth?.user?.user_role === 'superadmin'
    && Boolean(usePage().props.auth?.canAccessWorkRecordTrial) && !isGeneral.value);
const GENERAL_ALLOWED = ['dashboard', 'calendar', 'diaries', 'team_rooms', 'settings', 'nencho', 'applications'];
// 「年末調整」タブ: 自分が対象者になっている受付中の受付がある人にだけ表示（会社タイプ問わず。SunA/GeneralAffairs）
const nenchoAvailable = computed(() => Boolean(usePage().props.auth?.nenchoAvailable));
const showTab = (key) => !isGeneral.value || GENERAL_ALLOWED.includes(key);

// 「申請」タブ（交通費など）: 申請者管理（Admin が、ユーザーごとにスイッチで決める）で、使える申請が 1 つでもある人にだけ出す
const showApply = computed(() => (usePage().props.auth?.applicationAccess ?? []).length > 0);

// User カラー: blue
const tab = (key) => [
    'rounded-md px-3 py-2 text-sm font-medium',
    activeKey.value === key
        ? 'bg-blue-100 text-blue-700'
        : 'border border-blue-200 text-blue-600 hover:bg-blue-50 hover:text-blue-800',
];

function tryRoute(name, params = {}) {
    try { return route(name, params); } catch { return null; }
}

const tabs = computed(() => [
    { key: 'dashboard', href: tryRoute('user.dashboard'), label: 'ダッシュボード' },
    { key: 'calendar', href: tryRoute('calendar.index'), label: 'カレンダー' },
    { key: 'department_work', href: tryRoute('schedule.index', { tab: 'work' }), label: '部署の作業', condition: canAccessDepartmentWork.value },
    { key: 'work_hub', href: tryRoute('user.myjobbox.index'), label: '仕事・案件', condition: hubEnabled.value },
    { key: 'project_jobs', href: tryRoute('user.project_jobs.index'), label: '案件確認', condition: !hubEnabled.value },
    { key: 'myjob', href: tryRoute('user.myjobbox.index'), label: 'マイジョブBOX', condition: !hubEnabled.value },
    { key: 'jobbox', href: tryRoute('user.jobbox.index'), label: '依頼されたジョブ', condition: !hubEnabled.value },
    { key: 'diaries', href: tryRoute('diaries.index'), label: '日報一覧' },
    { key: 'team_rooms', href: tryRoute('team-rooms.index'), label: 'チームルーム' },
    { key: 'proof_status', href: tryRoute('user.proof.status'), label: '校正状況', condition: isSunbrain.value && !hubEnabled.value },
    { key: 'nencho', href: tryRoute('user.general_affairs.nencho.index'), label: '年末調整', condition: nenchoAvailable.value },
    { key: 'applications', href: tryRoute('user.applications.index'), label: '申請', condition: showApply.value },
    { key: 'settings', href: tryRoute('user.settings.index'), label: '設定' },
].filter(t => t.condition !== false && t.href && showTab(t.key)));

function onMobileSelect(e) {
    const href = e.target.value;
    if (href) router.get(href);
}
</script>

<template>
    <div class="mb-4">
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
                    :selected="activeKey === t.key"
                >{{ t.label }}</option>
            </select>
        </div>

        <!-- デスクトップ: タブ -->
        <nav class="hidden sm:flex flex-wrap gap-2" aria-label="Tabs">
            <Link v-for="t in tabs" :key="t.key" :href="t.href" :class="tab(t.key)" :aria-current="activeKey === t.key ? 'page' : undefined">{{ t.label }}</Link>
        </nav>
        <UserWorkHubTabs v-if="hubEnabled && hubActive" :active="hubTab" class="mt-3" />
    </div>
</template>
