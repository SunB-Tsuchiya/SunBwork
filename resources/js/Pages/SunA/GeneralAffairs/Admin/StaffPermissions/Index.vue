<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import AdminNavigationTabs from '@/Components/Tabs/AdminNavigationTabs.vue';
import { Link } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

defineProps({
    staff: { type: Array, default: () => [] },
    abilities: { type: Object, default: () => ({}) },
});

const roleLabels = { clerk: '総務・経理', admin: '管理者', superadmin: 'SuperAdmin' };
const shortLabels = { can_view_employees: '社員情報を見る', can_edit_employees: '編集する', can_view_my_number: 'マイナンバー', can_manage_nencho: '年末調整' };
</script>

<template>
    <AppLayout title="社員情報権限管理">
        <template #header>
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-base font-semibold text-gray-800 sm:text-xl">社員情報権限管理</h2>
                <Link :href="route('admin.general_affairs.my_number_logs.index')" class="inline-flex items-center gap-1 rounded-lg border border-red-200 bg-red-50 px-3 py-1 text-xs font-medium text-red-700 hover:bg-red-100">
                    マイナンバー閲覧記録
                </Link>
            </div>
        </template>
        <template #tabs>
            <AdminNavigationTabs active="staff_permissions" />
        </template>

        <div class="rounded bg-white p-6 shadow">
            <p class="mb-4 text-sm text-gray-600">総務・経理の担当者が、社員情報や年末調整で何をできるかを設定します。権限が設定されていない総務・経理は、何も見られません。管理者は、社員情報（一般項目）と年末調整の管理は常にできますが、<strong>マイナンバーは権限の設定が必要</strong>です。</p>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-3 text-left">氏名</th>
                            <th class="px-3 py-3 text-left">区分</th>
                            <th class="px-3 py-3 text-left">会社</th>
                            <th v-for="(label, key) in shortLabels" :key="key" class="px-3 py-3 text-center">{{ label }}</th>
                            <th class="px-3 py-3 text-right">操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="s in staff" :key="s.id" class="hover:bg-gray-50">
                            <td class="whitespace-nowrap px-3 py-3 font-medium text-gray-800">{{ s.name }}</td>
                            <td class="whitespace-nowrap px-3 py-3">{{ roleLabels[s.role] ?? s.role }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-gray-600">{{ s.company ?? '—' }}</td>
                            <td v-for="(label, key) in shortLabels" :key="key" class="px-3 py-3 text-center">
                                <span v-if="s.permissions[key]" class="text-green-600">●</span>
                                <span v-else-if="s.role !== 'clerk' && key !== 'can_view_my_number'" class="text-xs text-gray-400">常に可</span>
                                <span v-else class="text-gray-300">—</span>
                            </td>
                            <td class="whitespace-nowrap px-3 py-3 text-right">
                                <Link :href="route('admin.general_affairs.staff_permissions.edit', { staff: s.id })" class="text-red-700 hover:underline">設定</Link>
                            </td>
                        </tr>
                        <tr v-if="!staff.length">
                            <td colspan="8" class="px-3 py-8 text-center text-gray-500">設定できる担当者がいません。</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
