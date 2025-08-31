<template>
    <AppLayout :title="`ジョブ割り当て一覧 - ${projectJob.name}`">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">【進行管理】{{ $page.props.auth.user.name || 'ユーザー' }}さんのページ</h2>
        </template>

        <div class="mx-auto max-w-6xl rounded bg-white p-6 shadow">
            <h1 class="mb-4 text-2xl font-bold">ジョブ割り当て一覧：{{ projectJob.name }}</h1>
            <div class="overflow-x-auto">
                <table class="min-w-full border">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="border px-4 py-2">日付</th>
                            <th class="border px-4 py-2">担当</th>
                            <th class="border px-4 py-2">タイトル</th>
                            <th class="border px-4 py-2">希望日</th>
                            <th class="border px-4 py-2">終了希望日 / 時刻</th>
                            <th class="border px-4 py-2">依頼</th>
                            <th class="border px-4 py-2">Status</th>
                            <th class="border px-4 py-2">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="a in assignments" :key="a.id" class="hover:bg-gray-50">
                            <td class="border px-4 py-2">{{ a.created_at ? a.created_at.split('T')[0] : '-' }}</td>
                            <td class="border px-4 py-2">{{ a.user?.name || '-' }}</td>
                            <td class="border px-4 py-2">{{ a.title }}</td>
                            <td class="border px-4 py-2">{{ a.desired_start_date || '-' }}</td>
                            <td class="border px-4 py-2">
                                {{ a.desired_end_date || '-' }}
                                <span v-if="a.desired_time">
                                    {{ formatTime(a.desired_time) }}
                                </span>
                            </td>
                            <td class="border px-4 py-2">
                                <button class="rounded bg-blue-500 px-3 py-1 text-white" @click.prevent="sendRequest(a)">発信</button>
                            </td>
                            <td class="border px-4 py-2">
                                {{ statusText(a) }}
                            </td>
                            <td class="border px-4 py-2">
                                <Link
                                    :href="route('coordinator.project_jobs.assignments.edit', { projectJob: projectJob.id, assignment: a.id })"
                                    class="rounded bg-yellow-500 px-3 py-1 text-white"
                                    >編集</Link
                                >
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                <Link
                    :href="route('coordinator.project_jobs.assignments.create', { projectJob: projectJob.id })"
                    class="rounded bg-blue-600 px-4 py-2 text-white"
                    >新規ジョブ割り当て</Link
                >
                <Link :href="route('coordinator.project_jobs.show', { projectJob: projectJob.id })" class="rounded bg-gray-200 px-4 py-2">戻る</Link>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
const { projectJob, assignments } = defineProps({ projectJob: Object, assignments: Array });

function formatTime(t) {
    if (!t) return '';
    // t may be '09:00:00' or '09:00' or '09:00:00.000000Z'
    const core = String(t).split('.')[0];
    const parts = core.split(':');
    if (parts.length >= 2) return parts[0].padStart(2, '0') + ':' + parts[1].padStart(2, '0');
    return t;
}

function statusText(a) {
    if (!a.assigned) return '未発信';
    if (a.assigned && !a.accepted) return '送信中';
    if (a.accepted) return '受諾';
    return '-';
}

function sendRequest(a) {
    if (!confirm('このジョブを発信しますか？')) return;

    const payload = {
        project_job_id: projectJob.id,
        project_job_assignment_id: a.id,
        to_user_id: a.user_id || a.user?.id,
        message: `割り当ての依頼: ${a.title}`,
    };

    router.post(route('job_requests.store'), payload, {
        onSuccess: () => {
            // optimistic UI update
            a.assigned = true;
            alert('発信しました。受信者に通知されます。');
        },
        onError: (errors) => {
            console.error('sendRequest error', errors);
            alert('発信に失敗しました。詳細はコンソールを確認してください。');
        },
    });
}
</script>

<style scoped></style>
