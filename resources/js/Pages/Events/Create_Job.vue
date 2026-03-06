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
const prefillEvent = ref(page.props.prefillEvent || null);
</script>

<template>
    <AppLayout title="ジョブイベント作成">
        <div class="mx-auto max-w-3xl rounded bg-white p-6 shadow">
            <h1 class="text-2xl font-bold">ジョブイベント作成</h1>
            <p class="mb-6 text-sm text-gray-600">プロジェクトジョブに紐づくイベントを登録します。</p>
            <div class="rounded border border-gray-100 p-4 shadow-sm">
                <h2 class="mb-4 text-lg font-medium text-gray-700">割当内容</h2>
                <AssignmentFormUser
                    :projectJob="projectJob"
                    :members="members"
                    :assignments="assignments"
                    :event="prefillEvent"
                    :editMode="true"
                    :defaultUserId="defaultUserId"
                    :user-clients="userClients"
                    :user-projects="userProjects"
                />
            </div>
        </div>
    </AppLayout>
</template>
