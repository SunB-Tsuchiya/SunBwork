<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import SuperAdminNavigationTabs from '@/Components/Tabs/SuperAdminNavigationTabs.vue';
import { Link, router } from '@inertiajs/vue3';

defineProps({
    groups: { type: Array, required: true },
});

function confirmDelete(id) {
    if (confirm('このグループを削除してもよろしいですか？')) {
        router.delete(route('super-admin.company-groups.destroy', id));
    }
}
</script>

<template>
    <AppLayout title="グループ会社設定">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">グループ会社設定</h2>
                <Link
                    :href="route('super-admin.company-groups.create')"
                    class="rounded bg-yellow-600 px-4 py-2 text-sm font-bold text-white hover:bg-yellow-700"
                >
                    新規グループ登録
                </Link>
            </div>
        </template>

        <template #tabs>
            <SuperAdminNavigationTabs active="company_groups" />
        </template>

        <div class="rounded bg-white px-4 py-6 sm:p-6 shadow">
            <div v-if="groups.length === 0" class="py-8 text-center text-gray-500">
                グループが登録されていません。
            </div>

            <div v-else class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">グループ名</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">識別キー</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">所属会社</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">状態</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr v-for="group in groups" :key="group.id" class="hover:bg-gray-50">
                            <td class="whitespace-nowrap px-4 py-3">
                                <div class="text-sm font-medium text-gray-900">{{ group.name }}</div>
                                <div v-if="group.description" class="text-xs text-gray-400 mt-0.5">{{ group.description }}</div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm font-mono text-gray-600">{{ group.group_key }}</td>
                            <td class="px-4 py-3">
                                <div v-if="group.companies?.length" class="flex flex-wrap gap-1">
                                    <span
                                        v-for="c in group.companies"
                                        :key="c.id"
                                        class="inline-block rounded-full bg-yellow-100 px-2 py-0.5 text-xs font-medium text-yellow-800"
                                    >{{ c.name }}</span>
                                </div>
                                <span v-else class="text-xs text-gray-400">未設定</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-center">
                                <span
                                    :class="group.active
                                        ? 'bg-green-100 text-green-700'
                                        : 'bg-gray-100 text-gray-500'"
                                    class="inline-block rounded-full px-2 py-0.5 text-xs font-medium"
                                >{{ group.active ? '有効' : '無効' }}</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-center">
                                <Link
                                    :href="route('super-admin.company-groups.edit', group.id)"
                                    class="mr-2 rounded bg-yellow-600 px-3 py-1 text-xs font-bold text-white hover:bg-yellow-700"
                                >編集</Link>
                                <button
                                    type="button"
                                    class="rounded bg-red-600 px-3 py-1 text-xs font-bold text-white hover:bg-red-700"
                                    @click="confirmDelete(group.id)"
                                >削除</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
