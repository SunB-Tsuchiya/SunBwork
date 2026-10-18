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
    <AppLayout title="年末調整 設計ガイド">
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('clerk.general_affairs.nencho.index')" class="whitespace-nowrap rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300">
                    ← 年末調整に戻る
                </Link>
                <h2 class="text-base font-semibold text-gray-800 sm:text-xl">設計ガイド</h2>
            </div>
        </template>
        <template #tabs>
            <ClerkNavigationTabs active="applications" />
        </template>

        <div class="rounded bg-white p-6 shadow">
            <p v-if="updatedAt" class="mb-4 text-right text-xs text-gray-500">最終更新: {{ updatedAt }}</p>
            <!-- 元データは Markdown 1 ファイル（サーバー側で HTML を除去して描画） -->
            <div class="nencho-guide prose prose-sm max-w-none sm:prose-base" v-html="html"></div>
        </div>
    </AppLayout>
</template>

<style scoped>
.nencho-guide :deep(table) { display: block; overflow-x: auto; }
.nencho-guide :deep(th),
.nencho-guide :deep(td) { padding: 0.4rem 0.75rem; border: 1px solid #e5e7eb; vertical-align: top; }
.nencho-guide :deep(th) { background: #f5f3ff; color: #4c1d95; white-space: nowrap; }
.nencho-guide :deep(h2) { margin-top: 2rem; padding-left: 0.6rem; border-left: 4px solid #7c3aed; }
</style>
