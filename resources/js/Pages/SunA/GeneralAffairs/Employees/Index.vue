<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import ClerkNavigationTabs from '@/Components/Tabs/ClerkNavigationTabs.vue';
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps({
    companies: { type: Array, default: () => [] },
    selectedCompanyId: { type: Number, required: true },
    users: { type: Array, default: () => [] },
    employmentLabels: { type: Object, default: () => ({}) },
    abilities: { type: Object, default: () => ({}) },
});

const q = ref('');
const filtered = computed(() => {
    const word = q.value.trim();
    return word ? props.users.filter((u) => `${u.name}${u.name_kana ?? ''}${u.employee_number ?? ''}${u.department ?? ''}`.includes(word)) : props.users;
});
</script>

<template>
    <AppLayout title="社員情報">
        <template #header>
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-base font-semibold text-gray-800 sm:text-xl">社員情報</h2>
                <Link :href="route('clerk.general_affairs.employees.guide')" class="inline-flex items-center gap-1 rounded-lg border border-purple-200 bg-purple-50 px-3 py-1 text-xs font-medium text-purple-700 hover:bg-purple-100">
                    📖 ガイド
                </Link>
            </div>
        </template>
        <template #tabs>
            <ClerkNavigationTabs active="employees" />
        </template>

        <div class="rounded bg-white p-6 shadow">
            <p class="mb-4 text-sm text-gray-600">総務が管理する社員の情報（住所・最寄り駅・マイナンバーなど）です。氏名・部署・雇用区分は、管理者の「ユーザー管理」の情報を表示しています。</p>

            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <nav class="flex flex-wrap gap-2" aria-label="会社">
                    <Link
                        v-for="c in companies"
                        :key="c.id"
                        :href="route('clerk.general_affairs.employees.index', { company: c.id })"
                        :class="['rounded-md px-3 py-2 text-sm font-medium', c.id === selectedCompanyId ? 'bg-purple-100 text-purple-700' : 'border border-purple-200 text-purple-600 hover:bg-purple-50']"
                    >{{ c.name }}</Link>
                </nav>
                <input v-model="q" type="search" placeholder="氏名・フリガナ・社員番号・部署で絞り込み" class="w-72 max-w-full rounded border-gray-300 text-sm focus:border-purple-500 focus:ring-purple-500" />
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-3 text-left">氏名</th>
                            <th class="px-3 py-3 text-left">フリガナ</th>
                            <th class="px-3 py-3 text-left">社員番号</th>
                            <th class="px-3 py-3 text-left">部署</th>
                            <th class="px-3 py-3 text-left">雇用区分</th>
                            <th class="px-3 py-3 text-left">社員情報</th>
                            <th v-if="abilities.viewMyNumber" class="px-3 py-3 text-left">マイナンバー</th>
                            <th class="px-3 py-3 text-right">操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="u in filtered" :key="u.id" class="hover:bg-gray-50">
                            <td class="whitespace-nowrap px-3 py-3 font-medium text-gray-800">{{ u.name }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-gray-600">{{ u.name_kana ?? '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-gray-600">{{ u.employee_number ?? '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-3">{{ u.department ?? '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-3">{{ employmentLabels[u.employment_type] ?? u.employment_type }}</td>
                            <td class="whitespace-nowrap px-3 py-3">
                                <span class="rounded-full px-2 py-1 text-xs font-medium" :class="u.registered ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'">{{ u.registered ? '登録済み' : '未登録' }}</span>
                            </td>
                            <td v-if="abilities.viewMyNumber" class="whitespace-nowrap px-3 py-3">
                                <span class="rounded-full px-2 py-1 text-xs font-medium" :class="u.has_my_number ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'">{{ u.has_my_number ? '登録済み' : '未登録' }}</span>
                            </td>
                            <td class="whitespace-nowrap px-3 py-3 text-right">
                                <Link :href="route('clerk.general_affairs.employees.edit', { user: u.id })" class="text-purple-700 hover:underline">{{ abilities.editEmployees ? '編集' : '表示' }}</Link>
                            </td>
                        </tr>
                        <tr v-if="!filtered.length">
                            <td colspan="8" class="px-3 py-8 text-center text-gray-500">該当する社員がいません。</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
