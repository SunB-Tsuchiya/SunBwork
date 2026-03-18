<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, usePage } from '@inertiajs/vue3';
const props = defineProps({ settings: Object });
const page = usePage();
const user = page.props.user;
</script>

<template>
    <AppLayout title="AI設定" :user="user">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">AI設定</h2>
        </template>

        <div class="rounded bg-white p-6 shadow">
                        <div class="mb-4 flex items-center justify-between">
                            <h1 class="text-2xl font-bold">AI設定</h1>
                            <Link :href="route('superadmin.ai.create')" class="rounded bg-blue-600 px-3 py-2 text-white">新規作成</Link>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">ID</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Model</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Max tokens</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">更新</th>
                                        <th class="px-6 py-3"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white">
                                    <tr v-for="s in settings.data" :key="s.id" class="hover:bg-gray-50">
                                        <td class="px-6 py-4 text-sm text-gray-900">{{ s.id }}</td>
                                        <td class="px-6 py-4 text-sm text-gray-900">{{ s.model }}</td>
                                        <td class="px-6 py-4 text-sm text-gray-900">{{ s.max_tokens }}</td>
                                        <td class="px-6 py-4 text-sm text-gray-900">{{ s.updated_at }}</td>
                                        <td class="px-6 py-4 text-sm">
                                            <Link :href="route('superadmin.ai.edit', s.id)" class="text-blue-600 hover:text-blue-900">編集</Link>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4 flex justify-end">
                            <nav class="inline-flex -space-x-px" aria-label="Pagination">
                                <ul class="inline-flex items-center space-x-1">
                                    <li v-for="link in settings.links" :key="link.label">
                                        <Link
                                            v-if="link.url"
                                            :href="link.url"
                                            class="rounded border bg-white px-3 py-1 text-sm text-gray-700 hover:bg-gray-50"
                                        >
                                            <span v-html="link.label"></span>
                                        </Link>
                                        <span v-else class="rounded border bg-gray-200 px-3 py-1 text-sm text-gray-600" v-html="link.label"></span>
                                    </li>
                                </ul>
                            </nav>
                        </div>
        </div>
    </AppLayout>
</template>
