<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    leaders:    { type: Array,  default: () => [] },
    indexRoute: { type: String, default: 'admin.leader_permissions.index' },
    editRoute:  { type: String, default: 'admin.leader_permissions.edit' },
});

const permLabels = {
    client_management:      'クライアント管理',
    diary_management:       '日報管理',
    workload_analysis:      '作業量分析',
    workload_setting:       '作業項目設定',
    work_record_management: '勤務時間管理',
};

const permKeys = Object.keys(permLabels);
</script>

<template>
    <AppLayout title="Leader権限管理">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Leader権限管理</h2>
            </div>
        </template>

        <div class="rounded bg-white p-6 shadow">
            <div v-if="leaders.length === 0" class="py-8 text-center text-gray-500">
                Leaderユーザーが登録されていません。
            </div>

            <div v-else class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                名前
                            </th>
                            <th
                                v-for="key in permKeys"
                                :key="key"
                                class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500"
                            >
                                {{ permLabels[key] }}
                            </th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">
                                操作
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr v-for="leader in leaders" :key="leader.id" class="hover:bg-gray-50">
                            <td class="whitespace-nowrap px-4 py-3">
                                <div class="text-sm font-medium text-gray-900">{{ leader.name }}</div>
                                <div class="text-xs text-gray-500">{{ leader.email }}</div>
                            </td>
                            <td
                                v-for="key in permKeys"
                                :key="key"
                                class="px-4 py-3 text-center"
                            >
                                <span
                                    :class="leader[key]
                                        ? 'bg-green-100 text-green-800'
                                        : 'bg-gray-100 text-gray-500'"
                                    class="inline-block rounded-full px-2 py-0.5 text-xs font-semibold"
                                >
                                    {{ leader[key] ? 'ON' : 'OFF' }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-center">
                                <Link
                                    :href="route(editRoute, { leaderuser: leader.id })"
                                    class="rounded bg-orange-600 px-3 py-1 text-xs font-bold text-white hover:bg-orange-700"
                                >
                                    権限設定
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
