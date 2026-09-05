<script setup>
import { computed } from 'vue';

// 売上分析・月次系画面の共通期間ナビゲーター（REVIEW3 13.1節、2026-09-04 Phase 12。
// 2026-09-04 Phase 13でgranularity='year'（年次分析等）、
// granularity='month-cyclic'（同月比較等、年を持たず1〜12月だけを巡回する画面）にも対応）。
// 部署・会社統合・年（月）の状態はv-modelで親に持たせ、このコンポーネントは表示と
// 前後移動・境界越え（12月→翌年1月等）・未登録月の案内だけを担当する。

const props = defineProps({
    departmentKey: { type: String, required: true },
    year: { type: Number, default: null }, // granularity='month-cyclic'のときは未使用
    month: { type: Number, default: 1 }, // granularity='year'のときは未使用
    consolidateClients: { type: Boolean, default: false },
    departmentLabels: { type: Object, default: () => ({}) },
    enabledDepartmentKeys: { type: Array, default: () => [] },
    allowAllDepartments: { type: Boolean, default: false },
    granularity: { type: String, default: 'month' }, // 'month' | 'year' | 'month-cyclic'
    // granularity='year'のときの「◯年」表示の接尾辞。期別分析では「年度」を指定する
    yearLabel: { type: String, default: '年' },
    // granularity='month'のときのみ使用: { has_data, nearest_before, nearest_after } | null
    periodStatus: { type: Object, default: null },
    loading: { type: Boolean, default: false },
});

const emit = defineEmits([
    'update:departmentKey',
    'update:year',
    'update:month',
    'update:consolidateClients',
    'go-latest',
    'go-registered',
]);

const monthLabels = ['1月', '2月', '3月', '4月', '5月', '6月', '7月', '8月', '9月', '10月', '11月', '12月'];
const isYearMode = computed(() => props.granularity === 'year');
const isMonthCyclic = computed(() => props.granularity === 'month-cyclic');

const goPrevMonth = () => {
    if (props.month <= 1) {
        if (!isMonthCyclic.value) emit('update:year', props.year - 1);
        emit('update:month', 12);
    } else {
        emit('update:month', props.month - 1);
    }
};

const goNextMonth = () => {
    if (props.month >= 12) {
        if (!isMonthCyclic.value) emit('update:year', props.year + 1);
        emit('update:month', 1);
    } else {
        emit('update:month', props.month + 1);
    }
};

const showUnregisteredNotice = computed(() => props.granularity === 'month' && props.periodStatus && props.periodStatus.has_data === false);
</script>

<template>
    <div class="rounded bg-white p-4 shadow">
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-500">部署</label>
                <select
                    :value="departmentKey"
                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm"
                    @change="emit('update:departmentKey', $event.target.value)"
                >
                    <option v-for="key in enabledDepartmentKeys" :key="key" :value="key">{{ departmentLabels[key] }}</option>
                    <option v-if="allowAllDepartments" value="all">全部署合計</option>
                </select>
            </div>
            <div>
                <label
                    class="block text-xs font-medium text-gray-500"
                    title="表記ゆれのある得意先名（例: 「株式会社◯◯」と「◯◯(株)」）を1社として集計します。統合ルールは「得意先統合設定」画面で登録した候補にもとづきます"
                >得意先統合<span class="text-gray-400">（表記ゆれの名寄せ）</span></label>
                <div class="mt-1 flex gap-2">
                    <button
                        type="button"
                        class="rounded-md border px-2 py-1.5 text-sm"
                        :class="!consolidateClients ? 'border-indigo-600 bg-indigo-50 text-indigo-700 font-semibold' : 'border-gray-300 text-gray-600 hover:bg-gray-50'"
                        @click="emit('update:consolidateClients', false)"
                    >OFF</button>
                    <button
                        type="button"
                        class="rounded-md border px-2 py-1.5 text-sm"
                        :class="consolidateClients ? 'border-indigo-600 bg-indigo-50 text-indigo-700 font-semibold' : 'border-gray-300 text-gray-600 hover:bg-gray-50'"
                        @click="emit('update:consolidateClients', true)"
                    >ON</button>
                </div>
            </div>

            <div v-if="isYearMode" class="flex items-center gap-1">
                <button
                    type="button"
                    :disabled="loading"
                    class="rounded-md border border-gray-300 bg-white px-2 py-1.5 text-sm text-gray-600 hover:bg-gray-50 disabled:opacity-50"
                    @click="emit('update:year', year - 1)"
                >← 前{{ yearLabel }}</button>
                <span class="px-2 text-sm font-bold text-gray-900">{{ year }}{{ yearLabel }}</span>
                <button
                    type="button"
                    :disabled="loading"
                    class="rounded-md border border-gray-300 bg-white px-2 py-1.5 text-sm text-gray-600 hover:bg-gray-50 disabled:opacity-50"
                    @click="emit('update:year', year + 1)"
                >翌{{ yearLabel }} →</button>
                <button
                    type="button"
                    :disabled="loading"
                    class="ml-1 rounded-md border border-indigo-300 bg-indigo-50 px-2 py-1.5 text-sm text-indigo-700 hover:bg-indigo-100 disabled:opacity-50"
                    @click="emit('go-latest')"
                >最新{{ yearLabel }}</button>
            </div>

            <div v-else class="flex items-center gap-1">
                <button
                    type="button"
                    :disabled="loading"
                    class="rounded-md border border-gray-300 bg-white px-2 py-1.5 text-sm text-gray-600 hover:bg-gray-50 disabled:opacity-50"
                    @click="goPrevMonth"
                >← 前月</button>
                <span v-if="!isMonthCyclic" class="px-1 text-sm font-bold text-gray-900">{{ year }}年</span>
                <select
                    :value="month"
                    class="rounded-md border-gray-300 text-sm shadow-sm"
                    @change="emit('update:month', Number($event.target.value))"
                >
                    <option v-for="(label, idx) in monthLabels" :key="idx" :value="idx + 1">{{ label }}</option>
                </select>
                <button
                    type="button"
                    :disabled="loading"
                    class="rounded-md border border-gray-300 bg-white px-2 py-1.5 text-sm text-gray-600 hover:bg-gray-50 disabled:opacity-50"
                    @click="goNextMonth"
                >次月 →</button>
                <button
                    type="button"
                    :disabled="loading"
                    class="ml-1 rounded-md border border-indigo-300 bg-indigo-50 px-2 py-1.5 text-sm text-indigo-700 hover:bg-indigo-100 disabled:opacity-50"
                    @click="emit('go-latest')"
                >最新登録月</button>
            </div>

            <!-- 各画面固有の追加操作（例: 品名検索ボタン）を同じ枠内に置くためのスロット -->
            <div v-if="$slots.extra" class="flex items-center gap-1">
                <slot name="extra" />
            </div>
        </div>

        <p v-if="showUnregisteredNotice" class="mt-3 rounded bg-amber-50 p-2 text-xs text-amber-700">
            {{ year }}年{{ month }}月は未登録です。
            <button
                v-if="periodStatus.nearest_before"
                type="button"
                class="font-semibold underline"
                @click="emit('go-registered', periodStatus.nearest_before)"
            >← {{ periodStatus.nearest_before.year }}年{{ periodStatus.nearest_before.month }}月へ</button>
            <span v-if="periodStatus.nearest_before && periodStatus.nearest_after"> ／ </span>
            <button
                v-if="periodStatus.nearest_after"
                type="button"
                class="font-semibold underline"
                @click="emit('go-registered', periodStatus.nearest_after)"
            >{{ periodStatus.nearest_after.year }}年{{ periodStatus.nearest_after.month }}月へ →</button>
        </p>
    </div>
</template>
