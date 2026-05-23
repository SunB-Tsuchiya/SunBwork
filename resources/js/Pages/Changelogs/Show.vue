<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, usePage } from '@inertiajs/vue3';
import DOMPurify from 'dompurify';
import { computed, ref } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps({
    changelog: { type: Object, required: true },
});

const page = usePage();
const isSuperAdmin = computed(() => page.props.auth?.user?.isSuperAdmin ?? false);
const showDesignFiles = ref(false);

function formatDate(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    return `${d.getFullYear()}年${d.getMonth() + 1}月${d.getDate()}日`;
}

const safeBody = computed(() => DOMPurify.sanitize(props.changelog.body ?? ''));
</script>

<template>
    <AppLayout :title="changelog.title">
        <template #header>
            <div class="flex items-center gap-3">
                <Link
                    :href="route('changelogs.index')"
                    class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300 whitespace-nowrap"
                >← 一覧に戻る</Link>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">更新ログ</h2>
            </div>
        </template>

        <div class="rounded bg-white p-6 shadow">
            <!-- ヘッダー -->
            <div class="mb-6 border-b pb-4">
                <p class="mb-1 text-xs text-gray-400">{{ formatDate(changelog.released_at) }}</p>
                <h1 class="text-2xl font-bold text-gray-800">{{ changelog.title }}</h1>
                <p class="mt-2 text-sm leading-relaxed text-gray-600">{{ changelog.summary }}</p>
            </div>

            <!-- 本文 -->
            <div class="cl-body prose prose-sm max-w-none" v-html="safeBody" />

            <!-- 設計ファイル（SuperAdmin のみ） -->
            <div v-if="isSuperAdmin && changelog.design_files && changelog.design_files.length > 0" class="mt-8 border-t pt-6">
                <button
                    class="flex items-center gap-2 text-xs text-gray-400 hover:text-gray-600"
                    @click="showDesignFiles = !showDesignFiles"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 00-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 01-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 00-3.375-3.375h-1.5a1.125 1.125 0 01-1.125-1.125v-1.5a3.375 3.375 0 00-3.375-3.375H9.75" />
                    </svg>
                    関連設計ファイル（管理者・Claude参照用）
                    <span class="ml-1">{{ showDesignFiles ? '▲' : '▼' }}</span>
                </button>

                <div v-if="showDesignFiles" class="mt-3 rounded border border-gray-100 bg-gray-50 p-4">
                    <p class="mb-2 text-xs text-gray-500">
                        以下のファイルが <code>z_instructions/archived/</code> ディレクトリに保管されています。
                    </p>
                    <ul class="space-y-1">
                        <li v-for="file in changelog.design_files" :key="file" class="text-xs font-mono text-gray-600">
                            {{ file }}
                        </li>
                    </ul>
                    <p v-if="changelog.claude_notes" class="mt-3 text-xs text-gray-500 italic">
                        {{ changelog.claude_notes }}
                    </p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* cl-body セクション見出し */
.cl-body :deep(section) {
    margin-bottom: 1.5rem;
}
.cl-body :deep(h3) {
    font-size: 1rem;
    font-weight: 600;
    color: #374151;
    margin-bottom: 0.5rem;
    padding-bottom: 0.25rem;
    border-bottom: 1px solid #e5e7eb;
}
.cl-body :deep(p) {
    font-size: 0.875rem;
    color: #4b5563;
    line-height: 1.6;
    margin-bottom: 0.5rem;
}
.cl-body :deep(ul) {
    list-style-type: disc;
    padding-left: 1.25rem;
}
.cl-body :deep(li) {
    font-size: 0.875rem;
    color: #374151;
    line-height: 1.6;
    margin-bottom: 0.25rem;
}
</style>
