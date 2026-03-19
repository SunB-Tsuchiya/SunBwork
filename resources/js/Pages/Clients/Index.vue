<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({ clients: Array });

const page = usePage();
const routePrefix = computed(() => {
    const role = page.props.auth?.user?.user_role ?? 'leader';
    return ['admin', 'superadmin'].includes(role) ? 'admin' : 'leader';
});
</script>

<template>
    <AppLayout title="クライアント一覧">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">クライアント管理</h2>
        </template>
        <template #headerExtras>
            <Link :href="route(`${routePrefix}.clients.create`)" class="rounded bg-orange-600 px-4 py-2 font-bold text-white hover:bg-orange-700">新規作成</Link>
        </template>

        <div class="rounded bg-white p-6 shadow">
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
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">操作</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white">
                                    <tr v-for="client in props.clients" :key="client.id" class="hover:bg-gray-50">
                                        <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-900">{{ client.id }}</td>
                                        <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-900">{{ client.name }}</td>
                                        <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-700">{{ client.detail || client.notes || '' }}</td>
                                        <td class="whitespace-nowrap px-6 py-4 text-sm">
                                            <Link :href="route(`${routePrefix}.clients.edit`, client.id)" class="text-blue-600 hover:text-blue-900"
                                                >編集</Link
                                            >
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </template>
        </div>
    </AppLayout>
</template>
