<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';

const props = defineProps({
    active: { type: String, default: '' },
});

// Prepress カラー: green-700 / green-800
const tab = (key) => [
    'rounded-md px-3 py-2 text-sm font-medium',
    props.active === key
        ? 'bg-green-100 text-green-800'
        : 'border border-green-700 text-green-700 hover:bg-green-50 hover:text-green-900',
];

function tryRoute(name) {
    try { return route(name); } catch { return null; }
}

const tabs = computed(() => [
    { key: 'dashboard', href: tryRoute('prepress.dashboard'), label: 'ダッシュボード' },
    { key: 'board', href: tryRoute('prepress.board'), label: '伝票ボード' },
    { key: 'tickets', href: tryRoute('prepress.tickets.index'), label: '伝票一覧' },
].filter(t => t.href));

function onMobileSelect(e) {
    const href = e.target.value;
    if (href) router.get(href);
}
</script>

<template>
    <div class="mb-6">
        <!-- モバイル: ドロップダウン -->
        <div class="sm:hidden">
            <select
                @change="onMobileSelect"
                class="w-full rounded-md border border-green-700 bg-white px-3 py-2 text-sm text-green-800 shadow-sm focus:border-green-800 focus:outline-none focus:ring-1 focus:ring-green-800"
            >
                <option value="">— ページを選択 —</option>
                <option
                    v-for="t in tabs"
                    :key="t.key"
                    :value="t.href"
                    :selected="active === t.key"
                >{{ t.label }}</option>
            </select>
        </div>

        <!-- デスクトップ: タブ -->
        <nav class="hidden sm:flex flex-wrap gap-2" aria-label="Tabs">
            <Link :href="route('prepress.dashboard')" :class="tab('dashboard')">
                ダッシュボード
            </Link>
            <Link :href="route('prepress.board')" :class="tab('board')">
                伝票ボード
            </Link>
            <Link :href="route('prepress.tickets.index')" :class="tab('tickets')">
                伝票一覧
            </Link>
        </nav>
    </div>
</template>
