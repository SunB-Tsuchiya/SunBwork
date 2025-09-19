<script setup>
import { Link } from '@inertiajs/vue3';
const props = defineProps({
    active: { type: String, default: '' },
    // optional project context to build assignments link
    projectJob: { type: Object, default: null },
});

function getAssignmentsLink() {
    try {
        if (props.projectJob && props.projectJob.id) {
            return route('coordinator.project_jobs.assignments.index', { projectJob: props.projectJob.id });
        }
    } catch (e) {
        // fallthrough
    }
    return route('coordinator.project_jobs.index');
}
function getJobboxLink() {
    try {
        if (props.projectJob && props.projectJob.id) {
            // coordinator-specific JobBox for a project
            return route('coordinator.project_jobs.jobbox.index', { projectJob: props.projectJob.id });
        }
    } catch (e) {
        // fallthrough
    }
    try {
        // try global (non-coordinator) jobbox named route
        return route('project_jobs.jobbox.index');
    } catch (e) {
        // final fallback to literal path used in AppLayout
        return '/jobbox';
    }
}
</script>

<template>
    <div class="mb-6">
        <nav class="flex space-x-8" aria-label="Tabs">
            <Link
                :href="getAssignmentsLink()"
                :class="[
                    'rounded-md px-3 py-2 text-sm font-medium',
                    props.active === 'projects'
                        ? 'bg-green-100 text-green-700'
                        : 'border border-gray-200 text-gray-600 hover:bg-green-50 hover:text-gray-800',
                ]"
            >
                案件一覧
            </Link>

            <Link
                :href="getJobboxLink()"
                :class="[
                    'rounded-md px-3 py-2 text-sm font-medium',
                    props.active === 'jobs'
                        ? 'bg-green-100 text-green-700'
                        : 'border border-gray-200 text-gray-600 hover:bg-green-50 hover:text-gray-800',
                ]"
            >
                ジョブ一覧
            </Link>

            <Link :href="route('profile.show')" class="rounded-md px-3 py-2 text-sm font-medium text-gray-600 hover:text-gray-800">
                プロフィール編集
            </Link>
        </nav>
    </div>
</template>
