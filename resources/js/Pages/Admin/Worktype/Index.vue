<script setup>
import AdminNavigationTabs from '@/Components/Tabs/AdminNavigationTabs.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    worktypes:     { type: Array,   default: () => [] },
    groups:        { type: Array,   default: null },
    is_superadmin: { type: Boolean, default: false },
    company_name:  { type: String,  default: null },
});

function formatTime(t) {
    if (!t) return '—';
    return t.substring(0, 5);
}
</script>

<template>
    <AppLayout title="勤務形態設定">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">勤務形態設定</h2>
                <Link
                    v-if="!is_superadmin"
                    :href="route('admin.worktypes.edit')"
                    class="rounded bg-red-600 px-4 py-2 text-sm font-bold text-white hover:bg-red-700"
                >
                    編集する
                </Link>
            </div>
        </template>
        <template #tabs>
            <AdminNavigationTabs active="worktypes" />
        </template>

        <!-- SuperAdmin: 全会社分を表示 -->
        <template v-if="is_superadmin">
            <div v-for="group in groups" :key="group.company_id" class="mb-6 rounded bg-white p-6 shadow">
                <div class="mb-4 flex items-center justify-between">
                    <h1 class="text-xl font-semibold">
                        勤務形態設定
                        <span class="ml-2 text-base font-normal text-gray-500">{{ group.company_name }}</span>
                    </h1>
                    <Link
                        :href="route('admin.worktypes.edit', { company_id: group.company_id })"
                        class="rounded bg-red-600 px-4 py-2 text-sm font-bold text-white hover:bg-red-700"
                    >
                        編集する
                    </Link>
                </div>
                <template v-if="group.worktypes.length">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="w-16 px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">順序</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">名称</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">始業時間</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">終業時間</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr v-for="wt in group.worktypes" :key="wt.id" class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm text-gray-500">{{ wt.sort_order }}</td>
                                <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ wt.name }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ formatTime(wt.start_time) }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ formatTime(wt.end_time) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </template>
                <p v-else class="text-sm text-gray-500">登録なし</p>
            </div>
        </template>

        <!-- 通常 Admin: 自社のみ -->
        <template v-else>
            <div class="rounded bg-white p-6 shadow">
                <h1 class="mb-4 text-xl font-semibold">
                    勤務形態設定
                    <span v-if="company_name" class="ml-2 text-base font-normal text-gray-500">{{ company_name }}</span>
                </h1>
                <template v-if="worktypes.length">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="w-16 px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">順序</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">名称</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">始業時間</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">終業時間</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr v-for="wt in worktypes" :key="wt.id" class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm text-gray-500">{{ wt.sort_order }}</td>
                                <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ wt.name }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ formatTime(wt.start_time) }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ formatTime(wt.end_time) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </template>
                <p v-else class="text-sm text-gray-500">登録なし</p>
            </div>
        </template>
    </AppLayout>
</template>
