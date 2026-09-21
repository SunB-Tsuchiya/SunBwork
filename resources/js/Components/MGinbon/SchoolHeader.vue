<script setup>
defineProps({ unit: { type: Object, required: true }, tone: { type: String, default: 'green' } });
</script>

<template>
  <header class="flex flex-wrap items-center gap-x-4 gap-y-2 border-b border-gray-500 px-3 py-2" :class="tone === 'blue' ? 'bg-blue-50' : 'bg-[#f6fad3]'">
    <div class="rounded bg-gray-700 px-3 py-1.5 text-sm font-semibold text-white">M {{ unit.mikuni_code || '共通' }}</div>
    <div class="text-xs text-gray-600">N <span class="font-medium text-gray-900">{{ unit.n_code || '—' }}</span></div>
    <h3 class="min-w-[14rem] flex-1 text-base font-semibold text-gray-900">{{ unit.display_name }}</h3>
    <span class="rounded bg-white px-2 py-1 text-xs text-gray-700">{{ unit.school_category || '分類なし' }}</span>
    <span class="rounded bg-white px-2 py-1 text-xs text-gray-700">{{ unit.n_category || '日能研分類なし' }}</span>
    <slot />
  </header>
</template>
