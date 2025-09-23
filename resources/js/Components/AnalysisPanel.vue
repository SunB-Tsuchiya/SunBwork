<template>
    <div class="rounded border p-4">
        <h3 class="font-medium">{{ title }}</h3>

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

                    <!-- difficulty section removed -->
                </div>

                <div class="mt-4 rounded border bg-gray-50 p-3">
                    <div class="text-sm">合計ポイント：{{ formattedPoints }} ポイント</div>
                    <div class="text-sm text-gray-600">計算: 分量 × 係数（合算後に小数第1位で丸め）</div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import Chart from 'chart.js/auto';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps({
    title: { type: String, default: '分析' },
    labels: { type: Array, default: () => [] },
    data: { type: Array, default: () => [] },
    coefficients: { type: Array, default: () => [] },
    total_points: { type: Number, default: null },
    points: { type: Array, default: () => [] },
});

const chartRef = ref(null);
let chartInstance = null;

const renderChart = () => {
    if (!chartRef.value) return;
    const ctx = chartRef.value.getContext('2d');
    const bg = ['#4F46E5', '#06B6D4', '#F59E0B', '#EF4444', '#10B981', '#8B5CF6', '#FB7185'];
    if (chartInstance) chartInstance.destroy();
    chartInstance = new Chart(ctx, {
        type: 'pie',
        data: {
            labels: props.labels,
            datasets: [{ data: props.data, backgroundColor: bg.slice(0, props.data.length) }],
        },
        options: { responsive: true, plugins: { legend: { position: 'bottom' } } },
    });
};

onMounted(() => renderChart());
watch([() => props.labels, () => props.data], () => renderChart(), { deep: true });

const formattedPoints = computed(() => {
    if (props.total_points !== null && props.total_points !== undefined) return Number(props.total_points).toFixed(1);
    let total = 0;
    for (let i = 0; i < props.data.length; i++) {
        const amt = Number(props.data[i] || 0);
        const coeff = Number(props.coefficients[i] || 1.0);
        total += amt * coeff;
    }
    return (Math.round(total * 10) / 10).toFixed(1);
});

// difficulty-related props and computed removed
</script>
<style scoped></style>
