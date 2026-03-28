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

function getJobboxLink() {
    try {
        if (props.projectJob && props.projectJob.id) {
            return route('coordinator.project_jobs.jobbox.index', { projectJob: props.projectJob.id });
        }
    } catch (e) {
        // fallthrough
    }
    try {
        return route('coordinator.jobbox');
    } catch (e) {
        return '/jobbox';
    }
}
</script>

<template>
    <div class="mb-6">
        <nav class="flex space-x-8" aria-label="Tabs">
            <Link :href="getAssignmentsLink()" :class="tab('projects')"> 案件一覧 </Link>
            <Link :href="getJobboxLink()" :class="tab('jobs')"> ジョブ一覧 </Link>
        </nav>
    </div>
</template>
