<template>
    <div class="rounded border-l-4 p-4 shadow-sm" :style="{ borderLeftColor: schemeConfig.primary }">
        <h3 class="flex items-center gap-2 font-medium">
            <span class="inline-block h-3 w-3 rounded-full" :style="{ backgroundColor: schemeConfig.primary }"></span>
            {{ title }}
        </h3>

        <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <canvas ref="chartRef" style="max-height: 240px"></canvas>
            </div>

            <div>
                <div class="flex gap-4">
                    <div class="flex-1">
                        <h4 class="font-medium">詳細<span class="text-xs text-gray-500"> （　）内は係数 </span></h4>
                        <table class="mt-2 w-full table-fixed text-sm">
                            <thead>
                                <tr>
                                    <th v-for="(lbl, idx) in labels" :key="idx" class="text-left">
                                        {{ lbl }}
                                        <span class="text-xs text-gray-500"
                                            >(
                                            {{
                                                Number(props.coefficients[idx] ?? 1)
                                                    .toFixed(2)
                                                    .replace(/\.00$/, '')
                                            }}
                                            )</span
                                        >
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td v-for="(d, idx) in data" :key="idx" class="text-left">{{ d }}</td>
                                </tr>
                                <tr v-if="points && points.length">
                                    <td v-for="(p, idx) in points" :key="'p-' + idx" class="text-left text-xs text-gray-600">
                                        {{ Number(p).toFixed(1) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-4 rounded border p-3" :style="{ backgroundColor: schemeConfig.bg }">
                    <div class="text-xs text-gray-500">合計（生）：{{ formattedPoints }}</div>
                    <template v-if="props.percentile !== null && props.percentile !== undefined">
                        <div class="mt-1 text-sm font-bold" :style="{ color: schemeConfig.dark }">
                            パーセンタイル：{{ Number(props.percentile).toFixed(1) }} / 100
                        </div>
                        <div v-if="props.rank && props.group_count" class="text-sm font-semibold" :style="{ color: schemeConfig.dark }">
                            順位：{{ props.rank }}位 / {{ props.group_count }}人中
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import Chart from 'chart.js/auto';
import { computed, onMounted, ref, watch } from 'vue';

// カテゴリごとのカラースキーム定義
const SCHEMES = {
    blue: {
        primary: '#2563EB',
        dark:    '#1E40AF',
        bg:      '#EFF6FF',
        shades:  ['#1E3A8A', '#1D4ED8', '#2563EB', '#3B82F6', '#60A5FA', '#93C5FD', '#BFDBFE'],
    },
    green: {
        primary: '#16A34A',
        dark:    '#166534',
        bg:      '#F0FDF4',
        shades:  ['#14532D', '#166534', '#15803D', '#16A34A', '#22C55E', '#4ADE80', '#86EFAC'],
    },
    amber: {
        primary: '#D97706',
        dark:    '#92400E',
        bg:      '#FFFBEB',
        shades:  ['#78350F', '#92400E', '#B45309', '#D97706', '#F59E0B', '#FCD34D', '#FDE68A'],
    },
    rose: {
        primary: '#E11D48',
        dark:    '#9F1239',
        bg:      '#FFF1F2',
        shades:  ['#881337', '#9F1239', '#BE123C', '#E11D48', '#F43F5E', '#FB7185', '#FDA4AF'],
    },
    purple: {
        primary: '#7C3AED',
        dark:    '#4C1D95',
        bg:      '#F5F3FF',
        shades:  ['#2E1065', '#4C1D95', '#6D28D9', '#7C3AED', '#8B5CF6', '#A78BFA', '#C4B5FD'],
    },
    indigo: {
        primary: '#4F46E5',
        dark:    '#3730A3',
        bg:      '#EEF2FF',
        shades:  ['#1E1B4B', '#3730A3', '#4338CA', '#4F46E5', '#6366F1', '#818CF8', '#A5B4FC'],
    },
};

const props = defineProps({
    title:        { type: String,  default: '分析' },
    labels:       { type: Array,   default: () => [] },
    data:         { type: Array,   default: () => [] },
    coefficients: { type: Array,   default: () => [] },
    total_points: { type: Number,  default: null },
    points:       { type: Array,   default: () => [] },
    scheme:       { type: String,  default: 'indigo' },
    percentile:   { type: Number,  default: null },
    rank:         { type: Number,  default: null },
    group_count:  { type: Number,  default: null },
});

const schemeConfig = computed(() => SCHEMES[props.scheme] ?? SCHEMES.indigo);

const chartRef = ref(null);
let chartInstance = null;

const renderChart = () => {
    if (!chartRef.value) return;
    const ctx = chartRef.value.getContext('2d');
    if (chartInstance) chartInstance.destroy();

    const isEmpty = !props.data.length || props.data.every((v) => !Number(v));
    const chartLabels = isEmpty ? ['データなし'] : props.labels;
    const chartData   = isEmpty ? [1] : props.data;
    const chartBg     = isEmpty
        ? ['#E5E7EB']
        : schemeConfig.value.shades.slice(0, props.data.length);

    chartInstance = new Chart(ctx, {
        type: 'pie',
        data: {
            labels: chartLabels,
            datasets: [{ data: chartData, backgroundColor: chartBg, borderWidth: 1, borderColor: '#fff' }],
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, padding: 10 } },
                tooltip: { enabled: !isEmpty },
            },
        },
    });
};

onMounted(() => renderChart());
watch([() => props.labels, () => props.data, () => props.scheme], () => renderChart(), { deep: true });

const formattedPoints = computed(() => {
    if (props.total_points !== null && props.total_points !== undefined) return Number(props.total_points).toFixed(1);
    let total = 0;
    for (let i = 0; i < props.data.length; i++) {
        const amt   = Number(props.data[i] || 0);
        const coeff = Number(props.coefficients[i] || 1.0);
        total += amt * coeff;
    }
    return (Math.round(total * 10) / 10).toFixed(1);
});
</script>
<style scoped></style>
