<template>
    <AppLayout title="ジョブ作成">
        <div class="mx-auto max-w-2xl rounded bg-white p-6 shadow">
            <h1 class="mb-4 text-2xl font-bold">ジョブ作成２</h1>
            <div>
                <AssignmentFormUser
                    :projectJob="projectJob"
                    :members="members"
                    :assignments="assignments"
                    :editMode="true"
                    :hide-status="true"
                    :default-status-id="1"
                    :defaultUserId="defaultUserId"
                    :user-clients="userClients"
                    :user-projects="userProjects"
                />
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import AssignmentFormUser from '@/Pages/Coordinator/ProjectJobs/JobAssign/AssignmentForm_user.vue';
import { usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

const page = usePage();
const projectJob = ref(page.props.projectJob || null);
const members = page.props.members || [];
const assignments = page.props.assignments || [];
const userClients = page.props.userClients || [];
const userProjects = page.props.userProjects || [];
const defaultUserId = page.props.auth && page.props.auth.user ? page.props.auth.user.id : null;
</script>

<style scoped></style>
