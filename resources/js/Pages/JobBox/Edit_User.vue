<template>
    <AppLayout title="ジョブ編集">
        <template #header>
            <div class="flex items-center gap-3">
                <button @click="goBack"
                    class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 whitespace-nowrap hover:bg-gray-300"
                >← 戻る</button>
                <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">ジョブ編集</h2>
            </div>
        </template>

        <div class="mx-auto max-w-2xl rounded bg-white px-4 py-6 sm:p-6 shadow">
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

        <!-- 予定（イベント）単体の削除 -->
        <div v-if="event && event.id" class="mx-auto mt-3 max-w-2xl flex justify-end">
            <button @click="deleteEvent"
                class="rounded bg-red-50 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-100"
            >この予定を削除</button>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import AssignmentFormUser from '@/Pages/Coordinator/ProjectJobs/JobAssign/AssignmentForm.vue';
import { router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { route } from 'ziggy-js';

function goBack() { window.history.back(); }

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

function deleteEvent() {
    if (!confirm('この予定を削除しますか？この操作は取り消せません。')) return;
    const returnTo = (() => {
        try {
            return new URLSearchParams(window.location.search).get('return_to') || null;
        } catch { return null; }
    })();
    router.delete(route('events.destroy', { event: event.id }), {
        data: returnTo ? { return_to: returnTo } : {},
        onError: () => alert('削除に失敗しました。'),
    });
}
</script>

<style scoped></style>

