<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    scripts: {
        type: Array,
        required: true,
    },
});

const cardStyles = [
    {
        bgFrom: 'from-indigo-50', bgTo: 'to-blue-50', border: 'border-indigo-200',
        badge: 'bg-indigo-100 text-indigo-700', btn: 'bg-indigo-600 hover:bg-indigo-700 text-white',
        iconBg: 'bg-indigo-100', iconColor: 'text-indigo-600',
    },
    {
        bgFrom: 'from-violet-50', bgTo: 'to-purple-50', border: 'border-violet-200',
        badge: 'bg-violet-100 text-violet-700', btn: 'bg-violet-600 hover:bg-violet-700 text-white',
        iconBg: 'bg-violet-100', iconColor: 'text-violet-600',
    },
    {
        bgFrom: 'from-teal-50', bgTo: 'to-emerald-50', border: 'border-teal-200',
        badge: 'bg-teal-100 text-teal-700', btn: 'bg-teal-600 hover:bg-teal-700 text-white',
        iconBg: 'bg-teal-100', iconColor: 'text-teal-600',
    },
];

function styleFor(index) {
    return cardStyles[index % cardStyles.length];
}
</script>

<template>
    <AppLayout title="スクリプト">
        <template #header>
            <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">スクリプトツール</h2>
        </template>

        <div class="rounded-lg bg-white p-8 shadow">
            <!-- ヘッダー -->
            <div class="mb-10 text-center">
                <div class="mb-3 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 7.5l3 2.25-3 2.25m4.5 0h3m-9 8.25h13.5A2.25 2.25 0 0021 18V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v12a2.25 2.25 0 002.25 2.25z" />
                    </svg>
                </div>
                <h1 class="mb-2 text-3xl font-bold text-gray-800">社内スクリプトツール</h1>
                <p class="text-gray-500">業務効率化ツールを選んでください。</p>
            </div>

            <!-- スクリプトカード -->
            <div
                v-if="scripts.length > 0"
                class="grid gap-6"
                :class="{
                    'grid-cols-1': scripts.length === 1,
                    'grid-cols-1 md:grid-cols-2': scripts.length === 2,
                    'grid-cols-1 md:grid-cols-3': scripts.length >= 3,
                }"
            >
                <Link
                    v-for="(script, index) in scripts"
                    :key="script.id"
                    :href="route('scripts.show', { script: script.slug })"
                    class="group block rounded-2xl border-2 bg-gradient-to-br p-6 shadow-sm transition-all duration-200 hover:-translate-y-1 hover:shadow-md"
                    :class="[styleFor(index).bgFrom, styleFor(index).bgTo, styleFor(index).border]"
                >
                    <!-- アイコン + バッジ -->
                    <div class="mb-4 flex items-start justify-between">
                        <div
                            class="flex h-14 w-14 items-center justify-center rounded-xl"
                            :class="styleFor(index).iconBg"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" :class="styleFor(index).iconColor" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 7.5l3 2.25-3 2.25m4.5 0h3m-9 8.25h13.5A2.25 2.25 0 0021 18V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v12a2.25 2.25 0 002.25 2.25z" />
                            </svg>
                        </div>
                        <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="styleFor(index).badge">
                            Tool
                        </span>
                    </div>

                    <!-- タイトル・説明 -->
                    <h2 class="mb-2 text-xl font-bold text-gray-800 group-hover:underline">{{ script.name }}</h2>
                    <p class="mb-6 text-sm leading-relaxed text-gray-500">{{ script.description }}</p>

                    <!-- ボタン -->
                    <div class="flex items-center justify-between">
                        <span class="rounded-lg px-4 py-2 text-sm font-semibold transition-colors" :class="styleFor(index).btn">
                            ツールを開く →
                        </span>
                    </div>
                </Link>
            </div>

            <!-- ツールがない場合 -->
            <div v-else class="py-16 text-center text-gray-400">
                <p>現在利用できるツールはありません。</p>
            </div>

            <p class="mt-10 text-center text-xs text-gray-400">
                表示されるツールは権限に応じて異なります。
            </p>
        </div>
    </AppLayout>
</template>
