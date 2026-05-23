<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';

const props = defineProps({
    active: { type: String, default: '' },
    projectJob: { type: Object, default: null },
});

// Coordinator カラー: green
const tab = (key) => [
    'rounded-md px-3 py-2 text-sm font-medium',
    props.active === key ? 'bg-green-100 text-green-700' : 'border border-green-200 text-green-600 hover:bg-green-50 hover:text-green-800',
];

function getAssignmentsLink() {
    return route('coordinator.project_jobs.index');
}

function getCalendarLink() {
    return route('coordinator.project_jobs.calendar');
}

function getJobboxLink() {
    try {
        return route('coordinator.jobbox');
    } catch (e) {
        return '/coordinator/jobbox';
    }
}

function getProgressSheetListLink() {
    try {
        return route('coordinator.progress_sheet_list.index');
    } catch (e) {
        return '/coordinator/progress-sheet-list';
    }
}

function getWorkflowSheetListLink() {
    try {
        return route('coordinator.workflow_sheet_list.index');
    } catch (e) {
        return '/coordinator/workflow-sheet-list';
    }
}

function getProgressReportLink() {
    try {
        return route('coordinator.progress_report.index');
    } catch (e) {
        return '/coordinator/progress-report';
    }
}

function getSettingsLink() {
    try {
        return route('coordinator.settings.index');
    } catch (e) {
        return '/coordinator/settings';
    }
}

function tryRoute(name) {
    try { return route(name); } catch { return null; }
}

function getGhostUsersLink() {
    try {
        return route('coordinator.ghost_users.index');
    } catch (e) {
        return '/coordinator/ghost-users';
    }
}

const tabs = computed(() => [
    { key: 'dashboard', href: route('coordinator.dashboard'), label: 'ダッシュボード' },
    { key: 'clients', href: route('coordinator.clients.index'), label: 'クライアント管理' },
    { key: 'subcontractors', href: route('coordinator.subcontractors.index'), label: '外注先管理' },
    { key: 'projects', href: getAssignmentsLink(), label: '案件一覧' },
    { key: 'jobs', href: getJobboxLink(), label: 'ジョブ一覧' },
    { key: 'calendar', href: getCalendarLink(), label: '案件カレンダー' },
    { key: 'progress_sheet_list', href: getProgressSheetListLink(), label: '進行表一覧' },
    { key: 'workflow_sheet_list', href: getWorkflowSheetListLink(), label: '管理シート一覧' },
    { key: 'progress_report', href: getProgressReportLink(), label: '進行レポート' },
    { key: 'settings', href: getSettingsLink(), label: '設定' },
    { key: 'ghost_users', href: getGhostUsersLink(), label: 'テストユーザー' },
    { key: 'sales_reps', href: tryRoute('coordinator.sales_reps.index'), label: '営業担当管理' },
].filter(t => t.href));

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
                class="w-full rounded-md border border-green-300 bg-white px-3 py-2 text-sm text-green-700 shadow-sm focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
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
            <Link :href="route('coordinator.dashboard')" :class="tab('dashboard')"> ダッシュボード </Link>
            <Link :href="route('coordinator.clients.index')" :class="tab('clients')"> クライアント管理 </Link>
            <Link :href="route('coordinator.subcontractors.index')" :class="tab('subcontractors')"> 外注先管理 </Link>
            <Link :href="getAssignmentsLink()" :class="tab('projects')"> 案件一覧 </Link>
            <Link :href="getJobboxLink()" :class="tab('jobs')"> ジョブ一覧 </Link>
            <Link :href="getCalendarLink()" :class="tab('calendar')"> 案件カレンダー </Link>
            <Link :href="getProgressSheetListLink()" :class="tab('progress_sheet_list')"> 進行表一覧 </Link>
            <Link :href="getWorkflowSheetListLink()" :class="tab('workflow_sheet_list')"> 管理シート一覧 </Link>
            <Link :href="getProgressReportLink()" :class="tab('progress_report')"> 進行レポート </Link>
            <Link :href="getSettingsLink()" :class="tab('settings')"> 設定 </Link>
            <Link :href="getGhostUsersLink()" :class="tab('ghost_users')"> テストユーザー </Link>
            <Link :href="route('coordinator.sales_reps.index')" :class="tab('sales_reps')"> 営業担当管理 </Link>
        </nav>
    </div>
</template>
