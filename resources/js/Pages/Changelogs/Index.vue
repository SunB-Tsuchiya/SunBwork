<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { ref, computed } from 'vue';

const props = defineProps({
    changelogs: { type: Array, required: true },
});

const keyword = ref('');

const filtered = computed(() => {
    const q = keyword.value.trim().toLowerCase();
    if (!q) return props.changelogs;
    return props.changelogs.filter(log =>
        (log.title ?? '').toLowerCase().includes(q) ||
        (log.summary ?? '').toLowerCase().includes(q) ||
        (log.version ?? '').toLowerCase().includes(q)
    );
});

function formatDate(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    return `${d.getFullYear()}年${d.getMonth() + 1}月${d.getDate()}日`;
}

function highlight(text, q) {
    if (!q || !text) return text;
    const escaped = q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    return text.replace(new RegExp(`(${escaped})`, 'gi'), '<mark class="bg-yellow-200 rounded px-0.5">$1</mark>');
}
</script>

<template>
    <AppLayout title="更新ログ">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">更新ログ</h2>
        </template>

        <div class="rounded bg-white p-6 shadow">
            <p class="mb-4 text-sm text-gray-500">
                サイトの機能追加・不具合修正の履歴です。新しい順に表示しています。
            </p>

            <div class="relative mb-6">
                <input
                    v-model="keyword"
                    type="text"
                    placeholder="キーワードで検索（タイトル・概要・バージョン）"
                    class="w-full rounded-lg border border-gray-300 py-2 pl-9 pr-4 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300"
                />
                <svg class="absolute left-2.5 top-2.5 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                </svg>
                <button
                    v-if="keyword"
                    @click="keyword = ''"
                    class="absolute right-2.5 top-2.5 text-gray-400 hover:text-gray-600"
                    aria-label="クリア"
                >✕</button>
            </div>

            <p v-if="keyword && filtered.length > 0" class="mb-3 text-xs text-gray-500">
                {{ filtered.length }} 件ヒット
            </p>

            <div class="space-y-4">
                <Link
                    v-for="log in filtered"
                    :key="log.id"
                    :href="route('changelogs.show', { changelog: log.id })"
                    class="block rounded-lg border border-gray-200 p-5 transition hover:border-indigo-300 hover:bg-indigo-50 hover:shadow-sm"
                >
                    <div class="mb-1 flex items-center gap-3">
                        <span class="text-xs text-gray-400">{{ formatDate(log.released_at) }}</span>
                        <span v-if="log.version" class="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-500" v-html="highlight(log.version, keyword.trim())"></span>
                    </div>
                    <h3 class="mb-2 text-base font-semibold text-gray-800" v-html="highlight(log.title, keyword.trim())"></h3>
                    <p class="text-sm leading-relaxed text-gray-600" v-html="highlight(log.summary, keyword.trim())"></p>
                    <span class="mt-3 inline-block text-xs font-medium text-indigo-600">詳細を見る →</span>
                </Link>
            </div>

            <p v-if="filtered.length === 0 && keyword" class="py-12 text-center text-sm text-gray-400">
                「{{ keyword }}」に一致するログは見つかりませんでした。
            </p>
            <p v-else-if="changelogs.length === 0" class="py-12 text-center text-sm text-gray-400">
                更新ログはまだありません。
            </p>
        </div>
    </AppLayout>
</template>
