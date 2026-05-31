<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    noCompanySelected: { type: Boolean, default: false },
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
    project_job_overview:   '案件総覧',
};

const permKeys = Object.keys(permLabels);
</script>

<template>
    <AppLayout title="Leader権限管理">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">Leader権限管理</h2>
            </div>
        </template>

        <!-- SuperAdmin グローバルモード警告 -->
        <div v-if="noCompanySelected" class="rounded border border-yellow-300 bg-yellow-50 p-6 shadow">
            <div class="flex items-start gap-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 h-5 w-5 shrink-0 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>
                <div>
                    <p class="font-semibold text-yellow-800">会社が選択されていません</p>
                    <p class="mt-1 text-sm text-yellow-700">右上の会社コンテキスト切り替えで表示したい会社を選択してから、このページを開いてください。</p>
                </div>
            </div>
        </div>

        <div v-else class="rounded bg-white px-4 py-6 sm:p-6 shadow">
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
