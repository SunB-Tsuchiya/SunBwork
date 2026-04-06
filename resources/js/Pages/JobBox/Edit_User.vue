<template>
    <AppLayout title="ジョブ編集">
        <div class="mx-auto max-w-2xl rounded bg-white p-6 shadow">
            <h1 class="mb-4 text-2xl font-bold">ジョブ編集（ユーザー）</h1>
            <div>
                <AssignmentFormUser
                    mode="user"
                    :projectJob="projectJob"
                    :members="members"
                    :assignments="assignments"
                    :editMode="true"
                    :defaultUserId="defaultUserId"
                    :user-clients="userClients"
                    :user-projects="userProjects"
                    :other-client-id="otherClientId"
                    :other-project-id="otherProjectId"
                    :event="event"
                />
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import AssignmentFormUser from '@/Pages/Coordinator/ProjectJobs/JobAssign/AssignmentForm.vue';
import { usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

const page = usePage();
const assignment = page.props.projectJobAssignment || null;
const projectJob = ref(
    assignment && assignment.project_job ? assignment.project_job : assignment && assignment.projectJob ? assignment.projectJob : null,
);
const members = page.props.members || [];
const assignments = assignment ? [assignment] : [];
const userClients = page.props.userClients || [];
const userProjects = page.props.userProjects || [];
const otherClientId = page.props.otherClientId ?? null;
const otherProjectId = page.props.otherProjectId ?? null;
const defaultUserId = page.props.auth && page.props.auth.user ? page.props.auth.user.id : null;
const event = page.props.event || null;
</script>

<style scoped></style>

