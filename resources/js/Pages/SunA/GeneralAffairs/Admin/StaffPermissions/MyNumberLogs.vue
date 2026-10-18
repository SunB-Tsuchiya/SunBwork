<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import AdminNavigationTabs from '@/Components/Tabs/AdminNavigationTabs.vue';
import { Link } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

defineProps({
    logs: { type: Object, required: true },
    actions: { type: Object, default: () => ({}) },
});
</script>

<template>
    <AppLayout title="マイナンバー閲覧記録">
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('admin.general_affairs.staff_permissions.index')" class="whitespace-nowrap rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300">
                    ← 権限管理に戻る
                </Link>
                <h2 class="text-base font-semibold text-gray-800 sm:text-xl">マイナンバー閲覧記録</h2>
            </div>
        </template>
        <template #tabs>
            <AdminNavigationTabs active="staff_permissions" />
        </template>

        <div class="rounded bg-white p-6 shadow">
            <p class="mb-4 text-sm text-gray-600">マイナンバーの全文表示・登録・変更・削除の記録です（新しい順）。</p>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-3 text-left">日時</th>
                            <th class="px-3 py-3 text-left">操作した人</th>
                            <th class="px-3 py-3 text-left">対象の社員</th>
                            <th class="px-3 py-3 text-left">操作</th>
                            <th class="px-3 py-3 text-left">IP</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="l in logs.data" :key="l.id" class="hover:bg-gray-50">
                            <td class="whitespace-nowrap px-3 py-3">{{ l.accessed_at }}</td>
                            <td class="whitespace-nowrap px-3 py-3 font-medium text-gray-800">{{ l.viewer_name }}</td>
                            <td class="whitespace-nowrap px-3 py-3">{{ l.employee_name }}</td>
                            <td class="whitespace-nowrap px-3 py-3">{{ actions[l.action] ?? l.action }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-gray-500">{{ l.ip_address ?? '—' }}</td>
                        </tr>
                        <tr v-if="!logs.data.length">
                            <td colspan="5" class="px-3 py-8 text-center text-gray-500">記録はまだありません。</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="logs.links && logs.last_page > 1" class="mt-4 flex flex-wrap gap-1">
                <template v-for="(link, i) in logs.links" :key="i">
                    <Link v-if="link.url" :href="link.url" class="rounded border px-3 py-1 text-xs" :class="link.active ? 'border-red-300 bg-red-50 text-red-700' : 'border-gray-300 text-gray-600 hover:bg-gray-50'" v-html="link.label" />
                    <span v-else class="rounded border border-gray-200 px-3 py-1 text-xs text-gray-400" v-html="link.label" />
                </template>
            </div>
        </div>
    </AppLayout>
</template>
