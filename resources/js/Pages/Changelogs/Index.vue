<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

const props = defineProps({
    changelogs: { type: Array, required: true },
});

function formatDate(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    return `${d.getFullYear()}年${d.getMonth() + 1}月${d.getDate()}日`;
}
</script>

<template>
    <AppLayout title="更新ログ">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">更新ログ</h2>
        </template>

        <div class="rounded bg-white p-6 shadow">
            <p class="mb-6 text-sm text-gray-500">
                サイトの機能追加・不具合修正の履歴です。新しい順に表示しています。
            </p>

            <div class="space-y-4">
                <Link
                    v-for="log in changelogs"
                    :key="log.id"
                    :href="route('changelogs.show', { changelog: log.id })"
                    class="block rounded-lg border border-gray-200 p-5 transition hover:border-indigo-300 hover:bg-indigo-50 hover:shadow-sm"
                >
                    <div class="mb-1 flex items-center gap-3">
                        <span class="text-xs text-gray-400">{{ formatDate(log.released_at) }}</span>
                    </div>
                    <h3 class="mb-2 text-base font-semibold text-gray-800">{{ log.title }}</h3>
                    <p class="text-sm leading-relaxed text-gray-600">{{ log.summary }}</p>
                    <span class="mt-3 inline-block text-xs font-medium text-indigo-600">詳細を見る →</span>
                </Link>
            </div>

            <p v-if="changelogs.length === 0" class="py-12 text-center text-sm text-gray-400">
                更新ログはまだありません。
            </p>
        </div>
    </AppLayout>
</template>
