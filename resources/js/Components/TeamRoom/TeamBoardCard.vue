<script setup>
import { ref } from 'vue';

const props = defineProps({
    card:    { type: Object, required: true },
    columns: { type: Array, default: () => [] },
});

const emit = defineEmits(['move', 'delete', 'color-change']);

const showDetail = ref(false);

const CARD_COLORS = {
    indigo: { swatch: 'bg-indigo-400',  border: 'border-l-indigo-400', bg: 'bg-indigo-50'  },
    blue:   { swatch: 'bg-blue-400',    border: 'border-l-blue-400',   bg: 'bg-blue-50'    },
    teal:   { swatch: 'bg-teal-500',    border: 'border-l-teal-500',   bg: 'bg-teal-50'    },
    green:  { swatch: 'bg-green-500',   border: 'border-l-green-500',  bg: 'bg-green-50'   },
    yellow: { swatch: 'bg-yellow-400',  border: 'border-l-yellow-400', bg: 'bg-yellow-50'  },
    orange: { swatch: 'bg-orange-400',  border: 'border-l-orange-400', bg: 'bg-orange-50'  },
    red:    { swatch: 'bg-red-400',     border: 'border-l-red-400',    bg: 'bg-red-50'     },
    pink:   { swatch: 'bg-pink-400',    border: 'border-l-pink-400',   bg: 'bg-pink-50'    },
    purple: { swatch: 'bg-purple-400',  border: 'border-l-purple-400', bg: 'bg-purple-50'  },
    gray:   { swatch: 'bg-gray-400',    border: 'border-l-gray-400',   bg: 'bg-gray-100'   },
};

function cardStyle() {
    const c = props.card.card_color;
    return c && CARD_COLORS[c] ? CARD_COLORS[c] : null;
}
</script>

<template>
    <div
        class="group rounded border border-gray-200 bg-white p-3 shadow-sm border-l-4 transition-colors"
        :class="cardStyle() ? [cardStyle().border, cardStyle().bg] : 'border-l-gray-200'"
    >
        <div class="flex items-start justify-between gap-2">
            <span class="text-sm font-medium text-gray-800">{{ card.title }}</span>
            <div class="flex shrink-0 items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                <!-- 色スウォッチ -->
                <button
                    v-for="(cfg, key) in CARD_COLORS"
                    :key="key"
                    type="button"
                    :title="key"
                    :class="[
                        cfg.swatch,
                        'h-3.5 w-3.5 rounded-full border transition-transform hover:scale-125',
                        (card.card_color ?? 'indigo') === key ? 'border-gray-700 scale-110' : 'border-white',
                    ]"
                    @click.stop="emit('color-change', card, key)"
                />
                <button type="button" class="ml-1 text-xs text-gray-400 hover:text-gray-600" @click.stop="showDetail = !showDetail">
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
