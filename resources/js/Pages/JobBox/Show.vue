<template>
    <AppLayout :title="`JobBox - ${projectJob.name}`">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">JobBox — メッセージ表示</h2>
        </template>

        <div class="mx-auto max-w-3xl rounded bg-white p-6 shadow">
            <h1 class="mb-4 text-2xl font-bold">{{ message.subject || 'ジョブ依頼' }}</h1>

            <div class="mb-4 rounded border p-4">
                <div class="mb-2 text-sm text-gray-600">送信者: {{ message.sender?.name || '-' }} / 受信日: {{ message.created_at }}</div>
                <div class="whitespace-pre-wrap">{{ message.body }}</div>
            </div>

            <div class="mt-4">
                <div class="flex items-center gap-2">
                    <Link
                        :href="
                            page.props.auth.user && page.props.auth.user.isCoordinator
                                ? route('coordinator.project_jobs.jobbox.index', { projectJob: projectJob.id })
                                : route('project_jobs.jobbox.index', { projectJob: projectJob.id })
                        "
                        class="rounded bg-gray-200 px-4 py-2"
                        >戻る</Link
                    >
                    <template v-if="page.props.auth.user && !page.props.auth.user.isCoordinator">
                        <button class="rounded bg-green-600 px-4 py-2 text-white" @click.prevent="sendCompletion">完了を返信</button>
                    </template>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
const { projectJob, message } = defineProps({ projectJob: Object, message: Object });
const page = usePage();

function sendCompletion() {
    if (!confirm('このジョブを完了としてコーディネータへ通知しますか？')) return;
    // Attempt to find the assignment id from the message.project_job_assignment
    const assignmentId = message.project_job_assignment?.id || null;
    if (!assignmentId) {
        alert('割り当て情報が見つかりません。');
        return;
    }

    const payload = {
        project_job_assignment_id: assignmentId,
        subject: `【完了報告】 ${projectJob.title || ''}`,
        body: '作業を完了しました。ご確認お願いします。',
    };

    router.post(route('project_jobs.jobbox.reply', { projectJob: projectJob.id }), payload, {
        onSuccess: () => {
            alert('完了報告を送信しました。');
            router.reload();
        },
        onError: (errors) => {
            console.error('sendCompletion error', errors);
            alert('完了報告の送信に失敗しました。');
        },
    });
}
</script>

<style scoped></style>
