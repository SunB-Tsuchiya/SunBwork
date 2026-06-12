<script setup>
import { computed } from 'vue';

const props = defineProps({
    year:   { type: Number, required: true },
    month:  { type: Number, required: true },  // 1-12
    events: { type: Array,  default: () => [] },
});

const emit = defineEmits(['date-click', 'event-click']);

const DAYS = ['日', '月', '火', '水', '木', '金', '土'];

const weeks = computed(() => {
    const firstDay = new Date(props.year, props.month - 1, 1);
    const lastDay  = new Date(props.year, props.month,     0);
    const startDow = firstDay.getDay(); // 0=日曜

    const cells = [];
    // 先月の空白セル
    for (let i = 0; i < startDow; i++) cells.push(null);
    // 当月セル
    for (let d = 1; d <= lastDay.getDate(); d++) {
        cells.push(new Date(props.year, props.month - 1, d));
    }
    // 末尾を7の倍数に
    while (cells.length % 7 !== 0) cells.push(null);

    const result = [];
    for (let i = 0; i < cells.length; i += 7) result.push(cells.slice(i, i + 7));
    return result;
});

function dateStr(d) {
    if (!d) return '';
    return d.toLocaleDateString('sv-SE');
}

function eventsOnDate(d) {
    if (!d) return [];
    const ds = dateStr(d);
    return props.events.filter(e => {
        const s = new Date(e.starts_at).toLocaleDateString('sv-SE');
        return s === ds;
    }).slice(0, 3);
}

function isToday(d) {
    if (!d) return false;
    return dateStr(d) === new Date().toLocaleDateString('sv-SE');
}

function eventColor(e) {
    if (!e.is_own) return 'bg-gray-200 text-gray-600';
    const colors = ['bg-blue-500', 'bg-green-500', 'bg-purple-500', 'bg-orange-500'];
    return colors[(e.id ?? 0) % colors.length] + ' text-white';
}
</script>

<template>
    <div class="select-none overflow-hidden rounded-lg border border-gray-200">
        <!-- 曜日ヘッダー -->
        <div class="grid grid-cols-7 border-b border-gray-200 bg-gray-50">
            <div v-for="(day, i) in DAYS" :key="i"
                class="py-2 text-center text-xs font-medium"
                :class="i === 0 ? 'text-red-500' : i === 6 ? 'text-blue-500' : 'text-gray-600'">
                {{ day }}
            </div>
        </div>

        <!-- 日付グリッド -->
        <div v-for="(week, wi) in weeks" :key="wi" class="grid grid-cols-7">
            <div v-for="(day, di) in week" :key="di"
                class="min-h-24 border-b border-r border-gray-100 p-1 last:border-r-0"
                :class="[
                    day ? 'cursor-pointer hover:bg-blue-50' : 'bg-gray-50',
                    isToday(day) ? 'bg-blue-50' : '',
                ]"
                @click="day && $emit('date-click', dateStr(day))">
                <!-- 日付番号 -->
                <div v-if="day" class="mb-1 flex h-6 w-6 items-center justify-center text-xs font-medium"
                    :class="[
                        isToday(day) ? 'rounded-full bg-blue-600 text-white' : '',
                        di === 0 ? 'text-red-500' : di === 6 ? 'text-blue-500' : 'text-gray-700',
                        isToday(day) ? '!text-white' : '',
                    ]">
                    {{ day.getDate() }}
                </div>

                <!-- イベント -->
                <div v-for="ev in eventsOnDate(day)" :key="ev.id"
                    class="mb-0.5 cursor-pointer truncate rounded px-1 text-xs"
                    :class="eventColor(ev)"
                    @click.stop="$emit('event-click', ev)">
                    {{ ev.title }}
                </div>
            </div>
        </div>
    </div>
</template>
