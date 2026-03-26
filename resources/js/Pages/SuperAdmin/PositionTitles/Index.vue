<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    adminTitles:  { type: Array, default: () => [] },
    leaderTitles: { type: Array, default: () => [] },
});
</script>

<template>
    <AppLayout title="役職称号管理">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">役職称号管理</h2>
                <Link
                    :href="route('superadmin.position_titles.edit')"
                    class="rounded bg-yellow-600 px-4 py-2 text-sm font-medium text-white hover:bg-yellow-700"
                >
                    編集
                </Link>
            </div>
        </template>

        <div class="rounded bg-white p-6 shadow">
            <div class="mx-auto w-full md:w-1/2">
            <!-- Admin 用称号 -->
            <div class="mb-8">
                <h3 class="mb-3 text-base font-semibold text-gray-700">Admin 用（社長・役員クラス）</h3>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">称号</th>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">ソート順</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr v-for="title in adminTitles" :key="title.id" class="hover:bg-gray-50">
                            <td class="px-4 py-2 text-sm text-gray-900">{{ title.name }}</td>
                            <td class="px-4 py-2 text-sm text-gray-500">{{ title.sort_order }}</td>
                        </tr>
                        <tr v-if="adminTitles.length === 0">
                            <td colspan="2" class="px-4 py-4 text-center text-sm text-gray-500">データがありません</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Leader 用称号 -->
            <div>
                <h3 class="mb-3 text-base font-semibold text-gray-700">Leader 用（部長・課長クラス）</h3>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">称号</th>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">ソート順</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr v-for="title in leaderTitles" :key="title.id" class="hover:bg-gray-50">
                            <td class="px-4 py-2 text-sm text-gray-900">{{ title.name }}</td>
                            <td class="px-4 py-2 text-sm text-gray-500">{{ title.sort_order }}</td>
                        </tr>
                        <tr v-if="leaderTitles.length === 0">
                            <td colspan="2" class="px-4 py-4 text-center text-sm text-gray-500">データがありません</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            </div><!-- /w-full md:w-1/2 -->
        </div>
    </AppLayout>
</template>
