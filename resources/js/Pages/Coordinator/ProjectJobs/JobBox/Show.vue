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
                <Link :href="route('coordinator.project_jobs.jobbox.index', { projectJob: projectJob.id })" class="rounded bg-gray-200 px-4 py-2">戻る</Link>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
const { projectJob, message } = defineProps({ projectJob: Object, message: Object });
</script>

<style scoped></style>
