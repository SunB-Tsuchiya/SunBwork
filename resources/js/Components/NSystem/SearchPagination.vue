<script setup>
defineProps({
    pagination: { type: Object, required: true },
    disabled: { type: Boolean, default: false },
});

defineEmits(['change']);
</script>

<template>
    <nav v-if="pagination.last_page > 1" class="flex items-center justify-center gap-3" aria-label="検索結果ページ">
        <button
            type="button"
            class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40"
            :disabled="disabled || pagination.current_page <= 1"
            @click="$emit('change', pagination.current_page - 1)"
        >
            前へ
        </button>
        <span class="text-sm text-gray-600">{{ pagination.current_page }} / {{ pagination.last_page }}ページ</span>
        <button
            type="button"
            class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40"
            :disabled="disabled || pagination.current_page >= pagination.last_page"
            @click="$emit('change', pagination.current_page + 1)"
        >
            次へ
        </button>
    </nav>
</template>
