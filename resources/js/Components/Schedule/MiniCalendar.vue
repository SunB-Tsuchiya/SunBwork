<script setup>
import { ref, computed, watch } from 'vue';

const props = defineProps({
    date: { type: String, required: true }, // YYYY-MM-DD (選択中の日付)
});
const emit = defineEmits(['select']);

const today = new Date().toLocaleDateString('sv-SE');

function parseDate(s) { return new Date(s + 'T00:00:00'); }

const viewYear  = ref(parseDate(props.date).getFullYear());
const viewMonth = ref(parseDate(props.date).getMonth()); // 0-indexed

watch(() => props.date, (v) => {
    const d = parseDate(v);
    viewYear.value  = d.getFullYear();
    viewMonth.value = d.getMonth();
});

function prevMonth() {
    if (viewMonth.value === 0) { viewYear.value--; viewMonth.value = 11; }
    else viewMonth.value--;
}
function nextMonth() {
    if (viewMonth.value === 11) { viewYear.value++; viewMonth.value = 0; }
    else viewMonth.value++;
}

const monthLabel = computed(() =>
    `${viewYear.value}年 ${viewMonth.value + 1}月`
);

// カレンダーグリッド（6行×7列、月曜始まり）
const grid = computed(() => {
    const year  = viewYear.value;
    const month = viewMonth.value;
    const first = new Date(year, month, 1);
    // 月曜を0とした開始オフセット (日=0 → 6, 月=1 → 0, ...)
    const offset = (first.getDay() + 6) % 7;
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const cells = [];
    for (let i = 0; i < offset; i++) cells.push(null);
    for (let d = 1; d <= daysInMonth; d++) {
        cells.push(`${year}-${String(month + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`);
    }
    while (cells.length % 7 !== 0) cells.push(null);
    // 6週分に統一
    while (cells.length < 42) cells.push(null);
    const rows = [];
    for (let r = 0; r < 6; r++) rows.push(cells.slice(r * 7, r * 7 + 7));
    return rows;
});

function dayNum(dateStr) { return dateStr ? parseInt(dateStr.slice(8)) : ''; }
function isSelected(d) { return d === props.date; }
function isToday(d)    { return d === today; }
function isWeekend(colIdx) { return colIdx === 5 || colIdx === 6; } // 土=5, 日=6 (月始まり)
</script>

<template>
    <div class="select-none px-2 pt-3 pb-2">
        <!-- ヘッダー: 月ナビ -->
        <div class="mb-1.5 flex items-center justify-between">
            <button type="button"
                class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700"
                @click="prevMonth">‹</button>
            <span class="text-xs font-semibold text-gray-700">{{ monthLabel }}</span>
            <button type="button"
                class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700"
                @click="nextMonth">›</button>
        </div>

        <!-- 曜日ヘッダー -->
        <div class="mb-0.5 grid grid-cols-7 text-center">
            <span v-for="(w, wi) in ['月','火','水','木','金','土','日']" :key="w"
                class="text-[10px] font-medium"
                :class="wi >= 5 ? 'text-blue-400' : 'text-gray-400'">{{ w }}</span>
        </div>

        <!-- 日付グリッド -->
        <div class="space-y-0.5">
            <div v-for="(row, ri) in grid" :key="ri" class="grid grid-cols-7">
                <button
                    v-for="(d, ci) in row" :key="ci"
                    type="button"
                    class="flex h-6 w-full items-center justify-center rounded text-[11px] transition-colors"
                    :class="[
                        !d ? 'pointer-events-none' : 'cursor-pointer',
                        isSelected(d)
                            ? 'bg-blue-600 font-bold text-white'
                            : isToday(d)
                                ? 'bg-blue-100 font-semibold text-blue-700'
                                : d
                                    ? (isWeekend(ci) ? 'text-blue-500 hover:bg-gray-100' : 'text-gray-700 hover:bg-gray-100')
                                    : '',
                    ]"
                    :disabled="!d"
                    @click="d && $emit('select', d)">
                    {{ dayNum(d) }}
                </button>
            </div>
        </div>
    </div>
</template>
