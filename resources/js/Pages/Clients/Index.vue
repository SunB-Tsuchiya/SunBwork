<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
const props = defineProps({ clients: Array });
</script>

<template>
    <AppLayout title="クライアント一覧">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">【進行管理】{{ $page.props.auth.user.name || 'ユーザー' }}さんのページ</h2>
        </template>

        <main class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="bg-white p-6 shadow-xl sm:rounded-lg">
                    <div class="mb-6 flex items-center justify-between">
                        <h1 class="text-2xl font-bold">クライアント一覧</h1>
                        <Link :href="route('leader.clients.create')" class="rounded bg-blue-600 px-4 py-2 font-semibold text-white hover:bg-blue-700"
                            >新規作成</Link
                        >
                    </div>

                    <template v-if="props.clients.length === 0">
                        <p class="py-8 text-gray-500">クライアントはまだ登録されていません</p>
                    </template>

                    <template v-else>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">ID</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">名前</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">詳細</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">独自案件</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">操作</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white">
                                    <tr v-for="client in props.clients" :key="client.id" class="hover:bg-gray-50">
                                        <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-900">{{ client.id }}</td>
                                        <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-900">{{ client.name }}</td>
                                        <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-700">{{ client.detail || client.notes || '' }}</td>
                                        <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-900">{{ client.fromSA ? '○' : '' }}</td>
                                        <td class="whitespace-nowrap px-6 py-4 text-sm">
                                            <Link :href="route('leader.clients.edit', client.id)" class="text-blue-600 hover:text-blue-900"
                                                >編集</Link
                                            >
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </template>
                </div>
            </div>
        </main>
    </AppLayout>
</template>
