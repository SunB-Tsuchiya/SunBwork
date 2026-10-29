<script setup>
import { computed, ref } from 'vue';
import { useSalesChart } from '@/Composables/useSalesChart';

// 経費メモの一覧（Phase24-5）。部署シートの上に置き、この年度のメモを見渡す。行をクリックすると、
// 表の該当行へ移動してメモの窓を開く。前年度のメモは別枠（参考表示）。

const props = defineProps({
    notes: { type: Array, default: () => [] },
    previousNotes: { type: Array, default: () => [] },
    fiscalYear: { type: Number, required: true },
});
const emit = defineEmits(['open']);

const { fiscalPeriodLabel } = useSalesChart();
const open = ref(false);
const showPrevious = ref(false);

const dateText = (iso) => (iso ? new Date(iso).toLocaleDateString('ja-JP') : '');
const targetText = (n) => {
    if (n.category === null) return '部署全体';
    return n.vendor_name ? `${n.category} › ${n.vendor_name}` : `${n.category}（科目）`;
};
const total = computed(() => props.notes.length);
</script>

<template>
    <div v-if="notes.length || previousNotes.length" class="mb-4 rounded border border-gray-200 bg-white">
        <button type="button" class="flex w-full items-center justify-between px-3 py-2 text-left" @click="open = !open">
            <span class="text-sm font-semibold text-gray-900">
                <span class="mr-1 inline-block w-3 text-gray-400">{{ open ? '▾' : '▸' }}</span>メモ一覧
                <span class="ml-2 text-xs font-normal text-gray-500">{{ fiscalPeriodLabel(fiscalYear) }} {{ total }}件<template v-if="previousNotes.length">・前年度 {{ previousNotes.length }}件</template></span>
            </span>
        </button>

        <div v-if="open" class="border-t px-3 pb-3 pt-2 text-xs">
            <p v-if="!notes.length" class="text-gray-400">この年度のメモはまだありません。科目や支払先の行の📝から追加できます。</p>
            <ul class="divide-y">
                <li
                    v-for="n in notes"
                    :key="n.id"
                    class="cursor-pointer px-1 py-1.5 hover:bg-indigo-50"
                    @click="emit('open', { category: n.category, vendor: n.vendor_name })"
                >
                    <span class="block text-[11px] text-gray-500">
                        {{ targetText(n) }}<template v-if="n.calendar_month">・{{ n.calendar_month }}月</template>・{{ n.author_name }}・{{ dateText(n.created_at) }}
                    </span>
                    <span class="block whitespace-pre-wrap break-words text-gray-800">{{ n.body }}</span>
                </li>
            </ul>

            <template v-if="previousNotes.length">
                <button type="button" class="mt-2 text-[11px] text-indigo-600 hover:underline" @click="showPrevious = !showPrevious">
                    {{ showPrevious ? '前年度のメモを閉じる' : `${fiscalPeriodLabel(fiscalYear - 1)}のメモを見る（${previousNotes.length}件）` }}
                </button>
                <ul v-if="showPrevious" class="mt-1 divide-y rounded bg-gray-50 px-2">
                    <li v-for="n in previousNotes" :key="n.id" class="py-1.5">
                        <span class="block text-[11px] text-gray-500">{{ targetText(n) }}<template v-if="n.calendar_month">・{{ n.calendar_month }}月</template>・{{ n.author_name }}・{{ dateText(n.created_at) }}</span>
                        <span class="block whitespace-pre-wrap break-words text-gray-700">{{ n.body }}</span>
                    </li>
                </ul>
            </template>
        </div>
    </div>
</template>
