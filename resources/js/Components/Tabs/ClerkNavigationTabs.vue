<script setup>
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    active: { type: String, default: '' },
});

// Clerk カラー: purple
const tab = (key) => [
    'rounded-md px-3 py-2 text-sm font-medium',
    props.active === key
        ? 'bg-purple-100 text-purple-700'
        : 'border border-purple-200 text-purple-600 hover:bg-purple-50 hover:text-purple-800',
];
</script>

<template>
    <div class="mb-6">
        <nav class="flex flex-wrap gap-2" aria-label="Tabs">
            <!-- Clerk 専用タブはここに追加予定 -->
        </nav>
    </div>
</template>
