<template>
    <AppLayout title="ジョブ編集">
        <div class="mx-auto max-w-2xl rounded bg-white p-6 shadow">
            <h1 class="mb-4 text-2xl font-bold">ジョブ編集（ユーザー）</h1>
            <div>
                <AssignmentFormUser
                    :projectJob="projectJob"
                    :members="members"
                    :assignments="assignments"
                    :editMode="true"
                    :defaultUserId="defaultUserId"
                    :user-clients="userClients"
                    :user-projects="userProjects"
                    :event="event"
                />
                <div class="mt-6 border-t pt-4">
                    <h2 class="text-lg font-semibold">紐づくイベント</h2>
                    <div v-if="event">
                        <div class="mt-2 text-sm text-gray-700">タイトル: {{ event.title }}</div>
                        <div class="mt-1 text-sm text-gray-700">開始: {{ event.start }}</div>
                        <div class="mt-1 text-sm text-gray-700">終了: {{ event.end }}</div>
                        <div class="mt-2">
                            <inertia-link :href="route('events.edit', event.id)" class="text-blue-600">イベント編集へ</inertia-link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import AssignmentFormUser from '@/Pages/Coordinator/ProjectJobs/JobAssign/AssignmentForm_user.vue';
import { usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { route } from 'ziggy-js';

const page = usePage();
const assignment = page.props.projectJobAssignment || null;
const projectJob = ref(
    assignment && assignment.project_job ? assignment.project_job : assignment && assignment.projectJob ? assignment.projectJob : null,
);
const members = page.props.members || [];
const assignments = assignment ? [assignment] : [];
const userClients = page.props.userClients || [];
const userProjects = page.props.userProjects || [];
const defaultUserId = page.props.auth && page.props.auth.user ? page.props.auth.user.id : null;
const event = page.props.event || null;

// no inline editor here; AssignmentForm_user handles event editing when provided :event
</script>

<style scoped></style>
