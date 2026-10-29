<script setup>
import { computed } from 'vue';
import { useSalesChart } from '@/Composables/useSalesChart';

// 経費の比較条件（Phase24）。部署シートと部署比較で共通。最終実績月の表示、比較年度の選択、
// 比較期間（期の途中の年度だけ: 同期間／通年）を出す。値は親が持つ（v-model）。

const props = defineProps({
    // 部署シート／部署比較のAPI応答（actual_months, months, is_partial, compare_period）
    data: { type: Object, required: true },
    fiscalYear: { type: Number, required: true },
    fiscalYears: { type: Array, default: () => [] },
    compareYear: { type: Number, default: null },
    comparePeriod: { type: String, default: null },
});
const emit = defineEmits(['update:compareYear', 'update:comparePeriod']);

const { fiscalPeriodLabel } = useSalesChart();

// 比較年度の選択肢（基準年度以外の取込済み年度。前年度は既定の項目として別に出す）
const options = computed(() => props.fiscalYears.filter((y) => y !== props.fiscalYear && y !== props.fiscalYear - 1));
// 「◯月まで実績」表示用の月名（最終実績月）
const actualMonthLabel = computed(() => (props.data.actual_months > 0 ? `${props.data.months[props.data.actual_months - 1].calendar_month}月` : ''));
</script>

<template>
    <div class="mb-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs">
        <span class="rounded bg-gray-100 px-2 py-1 text-gray-600">{{ actualMonthLabel }}まで実績{{ data.is_partial ? '（期の途中）' : '' }}</span>
        <label class="flex items-center gap-1 text-gray-700">
            比較年度
            <select
                :value="compareYear ?? ''"
                class="rounded-md border-gray-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                @change="emit('update:compareYear', $event.target.value ? Number($event.target.value) : null)"
            >
                <option value="">前年度（{{ fiscalPeriodLabel(fiscalYear - 1) }}）</option>
                <option v-for="fy in options" :key="fy" :value="fy">{{ fiscalPeriodLabel(fy) }}</option>
            </select>
        </label>
        <div v-if="data.is_partial" class="flex items-center gap-1">
            <span class="text-gray-700">比較期間</span>
            <button
                type="button"
                class="rounded px-2 py-1"
                :class="data.compare_period === 'ytd' ? 'bg-gray-700 text-white' : 'border border-gray-300 text-gray-600 hover:bg-gray-100'"
                @click="emit('update:comparePeriod', 'ytd')"
            >同期間（4月〜{{ actualMonthLabel }}）</button>
            <button
                type="button"
                class="rounded px-2 py-1"
                :class="data.compare_period === 'full' ? 'bg-gray-700 text-white' : 'border border-gray-300 text-gray-600 hover:bg-gray-100'"
                @click="emit('update:comparePeriod', 'full')"
            >通年</button>
        </div>
        <span v-if="data.is_partial && data.compare_period === 'ytd'" class="text-gray-400">期の途中のため、比較年度も同じ月までの累計で比べています</span>
        <slot />
    </div>
</template>
