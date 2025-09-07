<template>
    <AppLayout title="案件一覧">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">【進行管理】{{ $page.props.auth.user.name || 'ユーザー' }}さんのページ</h2>
        </template>
        <div class="mx-auto max-w-4xl rounded bg-white p-6 shadow">
            <div class="mb-6 flex items-center justify-between">
                <h1 class="text-2xl font-bold">案件一覧</h1>
                <Link :href="route('coordinator.project_jobs.create')" class="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700"
                    >新規作成</Link
                >
            </div>
            <div v-if="jobs.length === 0" class="py-8 text-gray-500">登録された案件はありません</div>
            <table v-else class="min-w-full border">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="border px-4 py-2">ID</th>
                        <th class="border px-4 py-2">案件名</th>
                        <th class="border px-4 py-2">クライアント名</th>
                        <th class="border px-4 py-2">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="job in jobs" :key="job.id" class="hover:bg-gray-50">
                        <td class="border px-4 py-2">{{ job.id }}</td>
                        <td class="border px-4 py-2">{{ job.title || job.name }}</td>
                        <td class="border px-4 py-2">{{ job.client?.name || '-' }}</td>
                        <td class="flex gap-2 border px-4 py-2">
                            <Link :href="route('coordinator.project_jobs.show', { projectJob: job.id })" class="rounded bg-gray-200 px-2 py-1"
                                >詳細</Link
                            >
                            <Link :href="route('coordinator.project_jobs.edit', { projectJob: job.id })" class="rounded bg-yellow-200 px-2 py-1"
                                >編集</Link
                            >
                            <button @click="destroy(job.id)" class="rounded bg-red-200 px-2 py-1">削除</button>
                            <button class="rounded bg-green-200 px-2 py-1">完了</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { onMounted } from 'vue';
const props = defineProps({ jobs: Array, registerFlags: Array, jobid: [Number, String] });
const jobs = props.jobs || [];
const registerFlags = props.registerFlags || [];
// jobid（直前登録ID）があればそれを優先、なければ最新ID
const latestJobId = props.jobid || (jobs.length ? jobs[jobs.length - 1].id : null);
const page = usePage();
onMounted(() => {
    if (page.props.reload) {
        location.reload();
        return;
    }
    // 新規登録直後、teammember/schedule未設定なら案内
    if (registerFlags.length && latestJobId) {
        // If both team members and schedule are missing, offer to go to team member create.
        if (registerFlags.includes('teammember') && registerFlags.includes('schedule')) {
            if (confirm('プロジェクト登録が完了しました。続いてメンバーを登録しますか？')) {
                // projectTeamMembers.create doesn't require the projectJob param in this app; the create page reads latest context.
                router.visit(route('coordinator.project_team_members.create'));
            }
        } else if (registerFlags.includes('schedule')) {
            if (confirm('メンバー登録が完了しました。続いてスケジュールを登録しますか？')) {
                // There is no dedicated project_schedules.index route; navigate to the just-created job's show page instead.
                router.visit(route('coordinator.project_jobs.show', { projectJob: latestJobId }));
            }
        }
    }
});
function destroy(id) {
    if (confirm('本当に削除しますか？')) {
        router.delete(route('coordinator.project_jobs.destroy', { projectJob: id }));
    }
}
</script>

<style scoped>
/* 必要に応じてスタイル追加 */
</style>
