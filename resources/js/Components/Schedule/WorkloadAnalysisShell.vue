<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import ToastUnified from '@/Components/ToastUnified.vue';

const props = defineProps({
    title: { type: String, required: true },
    active: { type: String, required: true },
    date: { type: String, required: true },
});

const scheduleHref = computed(() => route('schedule.index', { date: props.date, tab: 'work' }));
const resultsHref = computed(() => route('schedule.department_work.results', { date: props.date }));
const detailsHref = computed(() => route('schedule.department_work.details', { date: props.date }));
const guideHref = computed(() => route('schedule.department_work.guide', { date: props.date }));
const recordTrialHref = computed(() => route('schedule.work_record_trial.page', { date: props.date }));
const tabClass = (key) => [
    'rounded-t px-4 py-2 text-sm font-semibold',
    props.active === key
        ? 'border border-b-white bg-white text-blue-700'
        : 'text-gray-600 hover:bg-white/70',
];
</script>

<template>
    <Head :title="title" />
    <div class="min-h-screen bg-gray-100">
        <ToastUnified />
        <header class="border-b border-gray-200 bg-white px-3 py-3 shadow-sm sm:px-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <a :href="scheduleHref" class="rounded bg-gray-100 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-200">← 予定表へ</a>
                    <h1 class="text-lg font-semibold text-gray-800">{{ title }}</h1>
                </div>
                <div class="flex items-center gap-3">
                    <Link :href="guideHref" class="rounded border border-blue-200 bg-blue-50 px-3 py-1.5 text-sm font-medium text-blue-800 hover:bg-blue-100" :aria-current="active === 'guide' ? 'page' : undefined">この分析のガイド</Link>
                    <span class="text-xs text-gray-500">情報出版・試用</span>
                </div>
            </div>
        </header>
        <nav class="flex flex-wrap gap-2 border-b border-gray-200 px-3 pt-3 sm:px-4" aria-label="作業分析の画面">
            <Link :href="resultsHref" :class="tabClass('results')">作業実績の内訳・簡易順位</Link>
            <Link :href="detailsHref" :class="tabClass('details')">詳しい作業量分析</Link>
            <Link :href="recordTrialHref" :class="tabClass('record')">作業記録の試用</Link>
            <a :href="route('superadmin.workload_analyzer.archive')" target="_blank" rel="noopener noreferrer" :class="tabClass('archive')">旧方式アーカイブ ↗</a>
        </nav>
        <div class="px-3 py-3 sm:px-4">
            <slot />
        </div>
    </div>
</template>
