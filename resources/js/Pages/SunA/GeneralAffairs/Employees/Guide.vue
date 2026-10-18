<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import ClerkNavigationTabs from '@/Components/Tabs/ClerkNavigationTabs.vue';
import { Link } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

defineProps({
    html: { type: String, default: '' },
    updatedAt: { type: String, default: null },
});
</script>

<template>
    <AppLayout title="社員情報 ガイド">
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('clerk.general_affairs.employees.index')" class="whitespace-nowrap rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300">
                    ← 社員情報に戻る
                </Link>
                <h2 class="text-base font-semibold text-gray-800 sm:text-xl">ガイド</h2>
            </div>
        </template>
        <template #tabs>
            <ClerkNavigationTabs active="employees" />
        </template>

        <div class="rounded bg-white p-6 shadow">
            <p v-if="updatedAt" class="mb-4 text-right text-xs text-gray-500">最終更新: {{ updatedAt }}</p>
            <!-- 元データは Markdown 1 ファイル（サーバー側で HTML を除去して描画） -->
            <div class="staff-guide prose prose-sm max-w-none sm:prose-base" v-html="html"></div>
        </div>
    </AppLayout>
</template>

<style scoped>
.staff-guide :deep(table) { display: block; overflow-x: auto; }
.staff-guide :deep(th),
.staff-guide :deep(td) { padding: 0.4rem 0.75rem; border: 1px solid #e5e7eb; vertical-align: top; }
.staff-guide :deep(th) { background: #f5f3ff; color: #4c1d95; white-space: nowrap; }
.staff-guide :deep(h2) { margin-top: 2rem; padding-left: 0.6rem; border-left: 4px solid #7c3aed; }
</style>
