<script setup>
import { Link } from '@inertiajs/vue3';
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
</script>

<template>
    <div class="mb-6">
        <nav class="flex space-x-8" aria-label="Tabs">
            <Link :href="route('coordinator.clients.index')" :class="tab('clients')"> クライアント管理 </Link>
            <Link :href="route('coordinator.subcontractors.index')" :class="tab('subcontractors')"> 外注先管理 </Link>
            <Link :href="getAssignmentsLink()" :class="tab('projects')"> 案件一覧 </Link>
            <Link :href="getJobboxLink()" :class="tab('jobs')"> ジョブ一覧 </Link>
            <Link :href="getCalendarLink()" :class="tab('calendar')"> 案件カレンダー </Link>
            <Link :href="getProgressReportLink()" :class="tab('progress_report')"> 進行レポート </Link>
            <Link :href="getSettingsLink()" :class="tab('settings')"> 設定 </Link>
        </nav>
    </div>
</template>
