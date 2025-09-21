<template>
    <AppLayout :title="`割当編集 - ${projectJob ? projectJob.title : ''}`">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">割当編集</h2>
        </template>

        <div class="mx-auto max-w-3xl rounded bg-white p-6 shadow">
            <h1 class="mb-4 text-2xl font-bold">割当を編集</h1>

            <AssignmentForm :projectJob="projectJob" :members="members" :assignments="assignments" :editMode="true" />

            <div class="mt-4">
                <Link
                    :href="route('coordinator.project_jobs.assignments.index', { projectJob: projectJob ? projectJob.id : '' })"
                    class="rounded bg-gray-200 px-4 py-2"
                    >戻る</Link
                >
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import AssignmentForm from '@/Pages/Coordinator/ProjectJobs/JobAssign/AssignmentForm.vue';
import { Link } from '@inertiajs/vue3';

// keep a single defineProps call and expose local bindings for the template
const props = defineProps({ projectJob: Object, members: Array, assignments: Array });
const projectJob = props.projectJob;
const members = props.members || [];
const assignments = props.assignments || [];
</script>

<style scoped></style>
