<template>
    <AppLayout :title="`JobBox - ${projectJob.name}`">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">JobBox — ジョブ関連メッセージ</h2>
        </template>

        <div class="mx-auto max-w-6xl rounded bg-white p-6 shadow">
            <h1 class="mb-4 text-2xl font-bold">JobBox：{{ projectJob.name }}</h1>
            <div class="mb-4 flex items-center gap-2">
                <input v-model="page.props.q_model" @keyup.enter="search" placeholder="タイトル/詳細/担当で検索" class="w-72 rounded border px-3 py-2 text-sm" />
                <button class="rounded bg-blue-600 px-3 py-2 text-white" @click.prevent="search">検索</button>
                <button class="ml-2 rounded border px-3 py-2" @click.prevent="clearSearch">クリア</button>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full border">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="border px-4 py-2">受信日</th>
                            <th class="border px-4 py-2">タイトル</th>
                            <th class="border px-4 py-2">送信者</th>
                            <th class="border px-4 py-2">ステータス</th>
                            <th class="border px-4 py-2">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="m in messages.data" :key="m.id" class="hover:bg-gray-50">
                            <td class="border px-4 py-2">{{ formatDate(m.created_at) }}</td>
                            <td class="border px-4 py-2">{{ m.subject || m.title || (m.body && m.body.slice(0,80)) }}</td>
                            <td class="border px-4 py-2">{{ m.sender?.name || '-' }}</td>
                            <td class="border px-4 py-2">{{ m.read_at ? '既読' : '未読' }}</td>
                            <td class="border px-4 py-2">
                                <Link :href="route('coordinator.project_jobs.jobbox.show', { projectJob: projectJob.id, message: m.id })" class="rounded bg-blue-600 px-3 py-1 text-white">表示</Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex items-center justify-between">
                <div class="text-sm text-gray-600">全 {{ messages.total }} 件</div>
                <div class="flex items-center space-x-2">
                    <button :disabled="!messages.prev_page_url" @click.prevent="goto(messages.prev_page_url)" class="rounded border px-3 py-1 disabled:opacity-50">前へ</button>
                    <div class="text-sm">{{ messages.current_page }} / {{ messages.last_page }}</div>
                    <button :disabled="!messages.next_page_url" @click.prevent="goto(messages.next_page_url)" class="rounded border px-3 py-1 disabled:opacity-50">次へ</button>
                </div>
            </div>

            <div class="mt-4">
                <Link :href="route('coordinator.project_jobs.show', { projectJob: projectJob.id })" class="rounded bg-gray-200 px-4 py-2">戻る</Link>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
const { projectJob, messages } = defineProps({ projectJob: Object, messages: Object });
const page = usePage();
page.props.q_model = page.props.q || '';

function formatDate(d) {
    if (!d) return '-';
    return String(d).split('T')[0];
}

function goto(url) {
    if (!url) return;
    router.visit(url, { preserveState: false });
}

function search() {
    router.get(route('coordinator.project_jobs.jobbox.index', { projectJob: projectJob.id }), { q: page.props.q_model }, { preserveState: false });
}

function clearSearch() {
    page.props.q_model = '';
    search();
}
</script>

<style scoped></style>
