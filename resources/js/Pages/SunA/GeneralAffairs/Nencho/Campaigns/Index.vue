<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import ClerkNavigationTabs from '@/Components/Tabs/ClerkNavigationTabs.vue';
import { Link } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

defineProps({
    companies: { type: Array, default: () => [] },
    selectedCompanyId: { type: Number, required: true },
    campaigns: { type: Array, default: () => [] },
    statuses: { type: Object, default: () => ({}) },
});
</script>

<template>
    <AppLayout title="年末調整の受付">
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('clerk.general_affairs.nencho.index')" class="whitespace-nowrap rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300">
                    ← 年末調整に戻る
                </Link>
                <h2 class="text-base font-semibold text-gray-800 sm:text-xl">受付</h2>
            </div>
        </template>
        <template #tabs>
            <ClerkNavigationTabs active="applications" />
        </template>

        <div class="rounded bg-white p-6 shadow">
            <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-gray-600">会社ごと・年度ごとの受付です。受付を作ると、社員・契約社員が対象者として自動で登録されます。</p>
                <Link :href="route('clerk.general_affairs.nencho.campaigns.create', { company: selectedCompanyId })" class="rounded bg-purple-600 px-4 py-2 text-sm font-medium text-white hover:bg-purple-700">
                    受付を作成
                </Link>
            </div>

            <nav class="mb-4 flex flex-wrap gap-2" aria-label="会社">
                <Link
                    v-for="c in companies"
                    :key="c.id"
                    :href="route('clerk.general_affairs.nencho.campaigns.index', { company: c.id })"
                    :class="['rounded-md px-3 py-2 text-sm font-medium', c.id === selectedCompanyId ? 'bg-purple-100 text-purple-700' : 'border border-purple-200 text-purple-600 hover:bg-purple-50']"
                >{{ c.name }}</Link>
            </nav>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-3 text-left">年度</th>
                            <th class="px-3 py-3 text-left">タイトル</th>
                            <th class="px-3 py-3 text-left">受付期間</th>
                            <th class="px-3 py-3 text-left">状態</th>
                            <th class="px-3 py-3 text-right">対象者</th>
                            <th class="px-3 py-3 text-right">操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="c in campaigns" :key="c.id" class="hover:bg-gray-50">
                            <td class="whitespace-nowrap px-3 py-3">{{ c.fiscal_year }}年分</td>
                            <td class="px-3 py-3 font-medium text-gray-800">{{ c.title }}</td>
                            <td class="whitespace-nowrap px-3 py-3">{{ c.opens_on }} ～ {{ c.due_on }}</td>
                            <td class="whitespace-nowrap px-3 py-3">
                                <span class="rounded-full px-2 py-1 text-xs font-medium" :class="c.status === 'open' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'">{{ statuses[c.status] ?? c.status }}</span>
                            </td>
                            <td class="whitespace-nowrap px-3 py-3 text-right">{{ c.targets_count - c.excluded_count }}名<span v-if="c.excluded_count" class="ml-1 text-xs text-gray-500">（除外{{ c.excluded_count }}）</span></td>
                            <td class="whitespace-nowrap px-3 py-3 text-right">
                                <Link :href="route('clerk.general_affairs.nencho.campaigns.show', { campaign: c.id })" class="mr-3 text-purple-700 hover:underline">詳細</Link>
                                <Link :href="route('clerk.general_affairs.nencho.campaigns.edit', { campaign: c.id })" class="text-purple-700 hover:underline">編集</Link>
                            </td>
                        </tr>
                        <tr v-if="!campaigns.length">
                            <td colspan="6" class="px-3 py-8 text-center text-gray-500">この会社の受付はまだありません。「受付を作成」から作成してください。</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
