<script setup>
// サンエー印刷 売上レポートの分析画面に共通の、期間・部門の選択バー。
// サン・ブレーンの PeriodNavigator は「得意先統合（名寄せ）」など SB 固有の項目を常に出すため、SunA 専用に別に持つ（SB のものは変更しない）。
// 年次分析 = 部門＋年（mode='year'）、同月比較 = 部門＋月（mode='month'。年は巡回しない）。部門は「全部門／各部／課・直轄」。
defineProps({
    departmentOptions: { type: Array, default: () => [] }, // [{ key, label }]
    deptKey: { type: String, default: 'all' },
    mode: { type: String, default: 'year' }, // 'year' | 'month'
    year: { type: Number, default: null },
    years: { type: Array, default: () => [] }, // データのある年（昇順）
    month: { type: Number, default: 1 },
    monthsWithData: { type: Array, default: () => [] }, // 登録のある月（月の選択肢の目印）
    loading: { type: Boolean, default: false },
});

const emit = defineEmits(['update:deptKey', 'update:year', 'update:month', 'go-latest']);
const monthLabels = ['1月', '2月', '3月', '4月', '5月', '6月', '7月', '8月', '9月', '10月', '11月', '12月'];
</script>

<template>
    <div class="rounded bg-white p-4 shadow">
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-500">部門</label>
                <select
                    :value="deptKey"
                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm"
                    @change="emit('update:deptKey', $event.target.value)"
                >
                    <option v-for="o in departmentOptions" :key="o.key" :value="o.key">{{ o.label }}</option>
                </select>
            </div>

            <div v-if="mode === 'year'" class="flex items-center gap-1">
                <button
                    type="button"
                    :disabled="loading || !years.includes(year - 1)"
                    class="rounded-md border border-gray-300 bg-white px-2 py-1.5 text-sm text-gray-600 hover:bg-gray-50 disabled:opacity-40"
                    @click="emit('update:year', year - 1)"
                >← 前年</button>
                <select
                    :value="year"
                    class="rounded-md border-gray-300 text-sm font-bold shadow-sm"
                    @change="emit('update:year', Number($event.target.value))"
                >
                    <option v-for="y in years" :key="y" :value="y">{{ y }}年</option>
                </select>
                <button
                    type="button"
                    :disabled="loading || !years.includes(year + 1)"
                    class="rounded-md border border-gray-300 bg-white px-2 py-1.5 text-sm text-gray-600 hover:bg-gray-50 disabled:opacity-40"
                    @click="emit('update:year', year + 1)"
                >翌年 →</button>
                <button
                    type="button"
                    :disabled="loading"
                    class="ml-1 rounded-md border border-indigo-300 bg-indigo-50 px-2 py-1.5 text-sm text-indigo-700 hover:bg-indigo-100 disabled:opacity-50"
                    @click="emit('go-latest')"
                >最新年</button>
            </div>

            <div v-else class="flex items-center gap-1">
                <button
                    type="button"
                    :disabled="loading"
                    class="rounded-md border border-gray-300 bg-white px-2 py-1.5 text-sm text-gray-600 hover:bg-gray-50 disabled:opacity-50"
                    @click="emit('update:month', month <= 1 ? 12 : month - 1)"
                >← 前月</button>
                <select
                    :value="month"
                    class="rounded-md border-gray-300 text-sm font-bold shadow-sm"
                    @change="emit('update:month', Number($event.target.value))"
                >
                    <option v-for="(label, idx) in monthLabels" :key="idx" :value="idx + 1">{{ label }}{{ monthsWithData.includes(idx + 1) ? '' : '（登録なし）' }}</option>
                </select>
                <button
                    type="button"
                    :disabled="loading"
                    class="rounded-md border border-gray-300 bg-white px-2 py-1.5 text-sm text-gray-600 hover:bg-gray-50 disabled:opacity-50"
                    @click="emit('update:month', month >= 12 ? 1 : month + 1)"
                >翌月 →</button>
                <button
                    type="button"
                    :disabled="loading"
                    class="ml-1 rounded-md border border-indigo-300 bg-indigo-50 px-2 py-1.5 text-sm text-indigo-700 hover:bg-indigo-100 disabled:opacity-50"
                    @click="emit('go-latest')"
                >最終実績月</button>
            </div>
            <slot name="extra" />
        </div>
    </div>
</template>
