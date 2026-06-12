<script setup>
import { ref } from 'vue';

const props = defineProps({
    card:    { type: Object, required: true },
    columns: { type: Array, default: () => [] },
});

const emit = defineEmits(['move', 'delete']);

const showDetail = ref(false);

const CARD_COLORS = {
    indigo: 'border-l-indigo-400',
    blue:   'border-l-blue-400',
    teal:   'border-l-teal-500',
    green:  'border-l-green-500',
    yellow: 'border-l-yellow-400',
    orange: 'border-l-orange-400',
    red:    'border-l-red-400',
    pink:   'border-l-pink-400',
    purple: 'border-l-purple-400',
    gray:   'border-l-gray-400',
};

function cardLeftBorder() {
    const c = props.card.card_color;
    return c && CARD_COLORS[c] ? CARD_COLORS[c] : 'border-l-gray-200';
}
</script>

<template>
    <div
        class="group rounded border border-gray-200 bg-white p-3 shadow-sm border-l-4 transition-colors"
        :class="cardLeftBorder()"
    >
        <div class="flex items-start justify-between gap-2">
            <span class="text-sm font-medium text-gray-800">{{ card.title }}</span>
            <div class="flex shrink-0 gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                <button type="button" class="text-xs text-gray-400 hover:text-gray-600" @click.stop="showDetail = !showDetail">
                    詳細
                </button>
            </div>
        </div>

        <p v-if="card.description && showDetail" class="mt-2 whitespace-pre-wrap text-xs text-gray-500">
            {{ card.description }}
        </p>

        <div v-if="showDetail && columns.length > 1" class="mt-2 flex items-center gap-1">
            <span class="text-xs text-gray-400">移動: </span>
            <button
                v-for="col in columns.filter(c => c.id !== card.team_board_column_id)"
                :key="col.id"
                type="button"
                class="rounded border border-gray-200 px-2 py-0.5 text-xs text-gray-600 hover:bg-gray-100"
                @click="emit('move', card, col.id)"
            >→ {{ col.name }}</button>
            <button
                type="button"
                class="ml-auto rounded border border-red-200 px-2 py-0.5 text-xs text-red-500 hover:bg-red-50"
                @click="emit('delete', card)"
            >削除</button>
        </div>
    </div>
</template>
