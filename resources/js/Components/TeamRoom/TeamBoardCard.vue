<script setup>
import { ref } from 'vue';

const props = defineProps({
    card:    { type: Object, required: true },
    columns: { type: Array, default: () => [] },
});

const emit = defineEmits(['move', 'delete']);

const showDetail = ref(false);

const CARD_COLORS = {
    indigo: { border: 'border-t-indigo-400', bg: 'bg-indigo-50'  },
    blue:   { border: 'border-t-blue-400',   bg: 'bg-blue-50'    },
    teal:   { border: 'border-t-teal-500',   bg: 'bg-teal-50'    },
    green:  { border: 'border-t-green-500',  bg: 'bg-green-50'   },
    yellow: { border: 'border-t-yellow-400', bg: 'bg-yellow-50'  },
    orange: { border: 'border-t-orange-400', bg: 'bg-orange-50'  },
    red:    { border: 'border-t-red-400',    bg: 'bg-red-50'     },
    pink:   { border: 'border-t-pink-400',   bg: 'bg-pink-50'    },
    purple: { border: 'border-t-purple-400', bg: 'bg-purple-50'  },
    gray:   { border: 'border-t-gray-400',   bg: 'bg-gray-100'   },
};

function cardStyle() {
    const c = props.card.card_color;
    return c && CARD_COLORS[c] ? CARD_COLORS[c] : null;
}
</script>

<template>
    <div
        class="group rounded border border-gray-200 p-3 shadow-sm transition-colors"
        :class="cardStyle()
            ? ['border-t-4', cardStyle().border, cardStyle().bg]
            : 'bg-white'"
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
