<script setup>
import { Link } from '@inertiajs/vue3';

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
</script>

<template>
    <div class="mb-6">
        <nav class="flex flex-wrap gap-2" aria-label="Tabs">
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
