<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useJapaneseHolidays } from '@/Composables/useJapaneseHolidays';

const props = defineProps({
    selectableRange: { type: Boolean, default: false },
    year:         { type: Number, required: true },
    month:        { type: Number, required: true },  // 1-12
    events:       { type: Array,  default: () => [] },
    reservations: { type: Array,  default: () => [] },
    rooms:        { type: Array,  default: () => [] },
});

const emit = defineEmits(['date-click', 'event-click', 'room-click', 'range-select']);

const { isHoliday, holidayName, fetchHolidays } = useJapaneseHolidays();
onMounted(fetchHolidays);

const dragStart = ref('');
const dragEnd = ref('');
let suppressClick = false;
function beginRange(day, event) {
    if (!props.selectableRange || !day || event.button !== 0 || event.pointerType === 'touch') return;
    event.preventDefault(); dragStart.value = dateStr(day); dragEnd.value = dragStart.value;
}
function finishRange(day) {
    if (!props.selectableRange || !dragStart.value || !day) return;
    const date = dateStr(day);
    if (date !== dragStart.value) { const sorted = [dragStart.value,date].sort(); suppressClick = true; emit('range-select',{start:sorted[0],end:sorted[1]}); }
    dragStart.value='';dragEnd.value='';
}
function clearRange() { dragStart.value='';dragEnd.value=''; }
function dateKey(day,event) { if(props.selectableRange && day && event.key === 'Enter' && event.target === event.currentTarget){event.preventDefault();emit('date-click',dateStr(day));} }
function clickDate(day) { if(suppressClick){suppressClick=false;return;} if(day)emit('date-click',dateStr(day)); }
onMounted(()=>document.addEventListener('pointerup',clearRange));
onUnmounted(()=>document.removeEventListener('pointerup',clearRange));

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
    }).sort((a, b) => Number(b._source === 'personal-work') - Number(a._source === 'personal-work')).slice(0, 3);
}

function remainingEventsOnDate(d) {
    if (!d) return 0;
    const ds = dateStr(d);
    return Math.max(0, props.events.filter(e => new Date(e.starts_at).toLocaleDateString('sv-SE') === ds).length - 3);
}

function isToday(d) {
    if (!d) return false;
    return dateStr(d) === new Date().toLocaleDateString('sv-SE');
}

function eventColor(e) {
    if (e._custom_color) return e._custom_text_color ? '' : 'text-white';
    if (!e.is_own && !(e.as_attendee && e.my_attendee_status === 'accepted')) {
        return 'bg-gray-200 text-gray-600';
    }
    const colors = ['bg-blue-500', 'bg-green-500', 'bg-purple-500', 'bg-orange-500'];
    return colors[(e.id ?? 0) % colors.length] + ' text-white';
}

function eventStyle(e) {
    const style = e._custom_color ? {
        backgroundColor: e._custom_color,
        ...(e._custom_text_color ? { color: e._custom_text_color } : {}),
        ...(e._custom_border_color ? { borderColor: e._custom_border_color, borderWidth: '1px' } : {}),
    } : {};
    if (e.completed) style.opacity = '0.45';
    return style;
}

function reservationsOnDate(d) {
    if (!d) return [];
    const ds = dateStr(d);
    return props.reservations.filter(r =>
        new Date(r.starts_at).toLocaleDateString('sv-SE') === ds
    );
}

function roomColor(res) {
    const room = props.rooms.find(r => r.id === res.meeting_room_id);
    return room?.color || '#6b7280';
}

function roomName(res) {
    const room = props.rooms.find(r => r.id === res.meeting_room_id);
    return room?.name ?? '会議室';
}

function fmtResTime(isoStr) {
    const d = new Date(isoStr);
    return `${String(d.getHours()).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')}`;
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
                    selectableRange && day && dragStart && dateStr(day) >= [dragStart,dragEnd].sort()[0] && dateStr(day) <= [dragStart,dragEnd].sort()[1] ? 'bg-blue-100' : '',
                ]"
                @pointerdown="beginRange(day,$event)" @pointerenter="dragStart && day && (dragEnd=dateStr(day))" @pointerup="finishRange(day)"
                :tabindex="selectableRange && day ? 0 : undefined" @keydown="dateKey(day,$event)"
                @click="clickDate(day)">
                <!-- 日付番号 -->
                <div v-if="day" class="mb-1 flex h-6 w-6 items-center justify-center text-xs font-medium"
                    :class="[
                        isToday(day) ? 'rounded-full bg-blue-600 text-white' : '',
                        di === 0 || (!isToday(day) && isHoliday(dateStr(day))) ? 'text-red-500' : di === 6 ? 'text-blue-500' : 'text-gray-700',
                        isToday(day) ? '!text-white' : '',
                    ]"
                    :title="holidayName(dateStr(day)) ?? undefined">
                    {{ day.getDate() }}
                </div>

                <!-- イベント -->
                <div v-for="ev in eventsOnDate(day)" :key="ev.id"
                    class="mb-0.5 cursor-pointer truncate rounded px-1 text-xs"
                    :class="eventColor(ev)"
                    :style="eventStyle(ev)"
                    @pointerdown.stop @click.stop="$emit('event-click', ev)">
                    {{ ev.title }}
                </div>
                <div v-if="remainingEventsOnDate(day)" class="text-[10px] text-gray-500">ほか {{ remainingEventsOnDate(day) }} 件</div>

                <!-- 会議室予約 -->
                <div v-for="res in reservationsOnDate(day)" :key="'r' + res.id"
                    class="mb-0.5 cursor-pointer truncate rounded px-1 text-[10px] text-white"
                    :style="{ background: roomColor(res) }"
                    @click.stop="$emit('room-click', res)">
                    🏢 {{ fmtResTime(res.starts_at) }} [{{ roomName(res) }}] {{ res.title }}
                </div>
            </div>
        </div>
    </div>
</template>
