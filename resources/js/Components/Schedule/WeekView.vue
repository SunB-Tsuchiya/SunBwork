<script setup>
import { ref, computed, inject, onMounted, onUnmounted, nextTick, watch } from 'vue';
import { evColor } from '@/Composables/useEventTypeColors';
import { useJapaneseHolidays } from '@/Composables/useJapaneseHolidays';

const props = defineProps({
    startDate:       { type: String,  required: true },
    events:          { type: Array,   default: () => [] },
    reservations:    { type: Array,   default: () => [] },
    rooms:           { type: Array,   default: () => [] },
    worktypes:       { type: Array,   default: () => [] },
    dailyWorktypes:  { type: Array,   default: () => [] },
    defaultWorktype: { type: Object,  default: null },
    clickToCreate:   { type: Boolean, default: false },
});

const emit = defineEmits(['create', 'update', 'event-click', 'room-click']);

// ── 定数 ──────────────────────────────────────────────────────
const START_HOUR  = 7;
const END_HOUR    = 22;
const HOUR_H      = 64;
const SNAP        = 15;
const TOTAL_H     = (END_HOUR - START_HOUR) * HOUR_H;
const HEADER_H    = 40;
const WORKTYPE_H  = 22;  // 日程行の高さ（px）
const DAYS_JA    = ['日', '月', '火', '水', '木', '金', '土'];

const { isHoliday, holidayName, fetchHolidays } = useJapaneseHolidays();

const days = computed(() => {
    const base = new Date(props.startDate + 'T00:00:00');
    return Array.from({ length: 7 }, (_, i) => {
        const d = new Date(base);
        d.setDate(base.getDate() + i);
        return d;
    });
});

const hours = computed(() =>
    Array.from({ length: END_HOUR - START_HOUR }, (_, i) => START_HOUR + i)
);

function dateStr(d) { return d.toLocaleDateString('sv-SE'); }
function isToday(d) { return dateStr(d) === new Date().toLocaleDateString('sv-SE'); }

function worktypeForDay(d) {
    const ds  = dateStr(d);
    const hit = props.dailyWorktypes.find(dw => dw.date === ds);
    const id  = hit?.worktype_id ?? props.defaultWorktype?.id ?? null;
    return id ? (props.worktypes.find(wt => wt.id === id) ?? null) : props.defaultWorktype ?? null;
}

// ── イベント位置計算 ───────────────────────────────────────────
// starts_at は Eloquent datetime cast → UTC ISO 文字列。
// new Date() でパースすると JS の Date になり、getHours()/getMinutes() は
// ブラウザのローカル時刻（日本では JST）を返す。
function localMin(isoStr) {
    const d = new Date(isoStr);
    return d.getHours() * 60 + d.getMinutes();
}

function eventsForDay(d) {
    const ds = dateStr(d);
    return props.events
        .filter(ev => new Date(ev.starts_at).toLocaleDateString('sv-SE') === ds)
        .sort((a, b) => eventDuration(b) - eventDuration(a));
}

function eventDuration(ev) {
    return Math.max(0, new Date(ev.ends_at).getTime() - new Date(ev.starts_at).getTime());
}

function evTop(ev) {
    return Math.max(0, (localMin(ev.starts_at) - START_HOUR * 60)) * (HOUR_H / 60);
}
function evHeight(ev) {
    return Math.max(18, (localMin(ev.ends_at) - localMin(ev.starts_at)) * (HOUR_H / 60));
}

function fmtTime(isoStr) {
    return new Date(isoStr).toLocaleTimeString('ja-JP', { hour: '2-digit', minute: '2-digit', hour12: false });
}

// ── グリッド DOM refs ─────────────────────────────────────────
const gridRefs = ref([]);
const scrollEl = inject('calendarScrollEl', null);
function setGridRef(el, i) { if (el) gridRefs.value[i] = el; }

// ── 現在時刻ライン ────────────────────────────────────────────
const nowMin = ref(new Date().getHours() * 60 + new Date().getMinutes());
let nowTimer = null;

function nowTop() {
    const m = nowMin.value - START_HOUR * 60;
    if (m < 0 || m > (END_HOUR - START_HOUR) * 60) return null;
    return `${m * (HOUR_H / 60)}px`;
}

function scrollToTime() {
    const container = scrollEl?.value;
    if (!container || container.scrollHeight <= container.clientHeight) return false;

    const now       = new Date();
    const today     = now.toLocaleDateString('sv-SE');
    const hasToday  = days.value.some(d => dateStr(d) === today);
    const targetMin = hasToday ? now.getHours() * 60 + now.getMinutes() : 8 * 60;
    const gridTop   = 12 + HEADER_H + WORKTYPE_H;

    container.scrollTop = Math.max(
        0,
        gridTop + (targetMin - START_HOUR * 60) * (HOUR_H / 60) - 160,
    );
    return true;
}

async function scheduleScrollToTime() {
    await nextTick();
    let attempts = 0;
    const tryScroll = () => {
        if (scrollToTime() || attempts++ >= 5) return;
        requestAnimationFrame(tryScroll);
    };
    requestAnimationFrame(tryScroll);
}

function yToMin(colIndex, clientY) {
    const el = gridRefs.value[colIndex];
    if (!el) return START_HOUR * 60;
    const rect = el.getBoundingClientRect();
    const raw  = Math.round(((clientY - rect.top) / (HOUR_H / 60)) / SNAP) * SNAP + START_HOUR * 60;
    return Math.max(START_HOUR * 60, Math.min(END_HOUR * 60, raw));
}

function clientXToCol(clientX) {
    for (let i = 0; i < gridRefs.value.length; i++) {
        const el = gridRefs.value[i];
        if (!el) continue;
        const r = el.getBoundingClientRect();
        if (clientX >= r.left && clientX <= r.right) return i;
    }
    return null;
}

// ── ドラッグ状態 ──────────────────────────────────────────────
const selecting = ref(null);
// { colIndex, startMin, currentMin }

const dragging = ref(null);
// { type: 'move'|'resize-top'|'resize-bot', ev, colIndex, startMin, endMin, offsetMin }

// ── グリッド空白 mousedown → 選択開始 ────────────────────────
function onGridMousedown(colIndex, e) {
    if (e.button !== 0 || dragging.value) return;
    e.preventDefault();
    const min = yToMin(colIndex, e.clientY);
    selecting.value = { colIndex, startMin: min, currentMin: min };
}

// ── イベント mousedown → ドラッグ開始 ────────────────────────
function onEventMousedown(ev, type, e) {
    if (!ev.is_own) return;
    if (e.button !== 0) return;
    e.preventDefault();
    e.stopPropagation();
    const evDate = new Date(ev.starts_at).toLocaleDateString('sv-SE');
    const colIndex = days.value.findIndex(d => dateStr(d) === evDate);
    const startMin = localMin(ev.starts_at);
    const endMin   = localMin(ev.ends_at);
    const clickMin = yToMin(colIndex, e.clientY);
    dragging.value = {
        type, ev: { ...ev }, colIndex,
        startMin, endMin,
        offsetMin: type === 'move' ? clickMin - startMin : 0,
    };
}

// ── グローバル mousemove ───────────────────────────────────────
function onMousemove(e) {
    if (selecting.value) {
        selecting.value = { ...selecting.value, currentMin: yToMin(selecting.value.colIndex, e.clientY) };
        return;
    }
    if (!dragging.value) return;
    const d = dragging.value;
    if (d.type === 'move') {
        const newCol = clientXToCol(e.clientX) ?? d.colIndex;
        const anchor = yToMin(newCol, e.clientY) - d.offsetMin;
        const dur    = d.endMin - d.startMin;
        const start  = Math.max(START_HOUR * 60, Math.min(END_HOUR * 60 - dur, anchor));
        dragging.value = { ...d, colIndex: newCol, startMin: start, endMin: start + dur };
    } else if (d.type === 'resize-top') {
        const m = yToMin(d.colIndex, e.clientY);
        dragging.value = { ...d, startMin: Math.min(m, d.endMin - SNAP) };
    } else {
        const m = yToMin(d.colIndex, e.clientY);
        dragging.value = { ...d, endMin: Math.max(m, d.startMin + SNAP) };
    }
}

// ── グローバル mouseup → 確定 ─────────────────────────────────
function onMouseup() {
    if (selecting.value) {
        const { colIndex, startMin, currentMin } = selecting.value;
        const sMin = Math.min(startMin, currentMin);
        const eMin = Math.max(startMin, currentMin);
        if (eMin - sMin >= SNAP) {
            emit('create', { date: dateStr(days.value[colIndex]), startMin: sMin, endMin: eMin });
        } else if (props.clickToCreate) {
            const endClamped = Math.min(sMin + 60, END_HOUR * 60);
            emit('create', { date: dateStr(days.value[colIndex]), startMin: sMin, endMin: endClamped });
        }
        selecting.value = null;
        return;
    }
    if (dragging.value) {
        const { colIndex, startMin, endMin, ev } = dragging.value;
        const pad  = (n) => String(n).padStart(2, '0');
        const toHm = (m) => `${pad(Math.floor(m / 60))}:${pad(m % 60)}`;
        const date = dateStr(days.value[colIndex]);
        emit('update', {
            id: ev.id,
            starts_at: `${date} ${toHm(startMin)}:00`,
            ends_at:   `${date} ${toHm(endMin)}:00`,
        });
        dragging.value = null;
    }
}

onMounted(async () => {
    fetchHolidays();
    window.addEventListener('mousemove', onMousemove);
    window.addEventListener('mouseup',   onMouseup);

    nowTimer = setInterval(() => {
        nowMin.value = new Date().getHours() * 60 + new Date().getMinutes();
    }, 60_000);

    scheduleScrollToTime();
});
onUnmounted(() => {
    window.removeEventListener('mousemove', onMousemove);
    window.removeEventListener('mouseup',   onMouseup);
    clearInterval(nowTimer);
});

watch(() => props.startDate, scheduleScrollToTime);

// ── 会議室セクション ───────────────────────────────────────────
const showRoomSection = ref(true);

function reservationsForRoomAndDay(roomId, day) {
    const ds = dateStr(day);
    return props.reservations.filter(r =>
        r.meeting_room_id === roomId &&
        new Date(r.starts_at).toLocaleDateString('sv-SE') === ds
    );
}

function fmtResTime(isoStr) {
    const d = new Date(isoStr);
    return `${String(d.getHours()).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')}`;
}

// ── テンプレート用ヘルパー ─────────────────────────────────────
function selStyle(colIndex) {
    if (!selecting.value || selecting.value.colIndex !== colIndex) return null;
    const { startMin, currentMin } = selecting.value;
    const top    = (Math.min(startMin, currentMin) - START_HOUR * 60) * (HOUR_H / 60);
    const height = Math.abs(currentMin - startMin) * (HOUR_H / 60);
    return height > 2 ? { top: `${top}px`, height: `${height}px` } : null;
}

function evStyle(ev) {
    const isDragging = dragging.value?.ev?.id === ev.id && dragging.value.type === 'move';
    return {
        top:         `${evTop(ev)}px`,
        height:      `${evHeight(ev)}px`,
        zIndex:      Math.max(1, Math.round(86_400_000 / Math.max(1, eventDuration(ev)))),
        background:  evColor(ev).bg,
        color:       evColor(ev).text,
        borderColor: evColor(ev).border,
        opacity:     isDragging ? '0.3' : ev.completed ? '0.45' : '1',
    };
}

function dragStyle(colIndex) {
    if (!dragging.value || dragging.value.colIndex !== colIndex) return null;
    const { startMin, endMin, ev } = dragging.value;
    return {
        top:         `${(startMin - START_HOUR * 60) * (HOUR_H / 60)}px`,
        height:      `${(endMin - startMin) * (HOUR_H / 60)}px`,
        zIndex:      1100,
        background:  evColor(ev).bg,
        color:       evColor(ev).text,
        borderColor: evColor(ev).border,
    };
}
</script>

<template>
    <div class="flex overflow-clip rounded-lg border border-gray-200 bg-white select-none" style="min-height: 0">
        <!-- 時刻ラベル列 -->
        <div class="sticky left-0 z-30 shrink-0 bg-gray-50 border-r border-gray-200" style="width: 44px">
            <div :style="{ height: HEADER_H + 'px' }" class="sticky top-0 z-30 border-b border-gray-200 bg-gray-50" />
            <!-- 日程行スペース -->
            <div :style="{ height: WORKTYPE_H + 'px', top: HEADER_H + 'px' }"
                class="sticky z-30 border-b border-gray-200 bg-gray-100" />
            <div :style="{ height: TOTAL_H + 'px' }" class="relative">
                <div v-for="h in hours" :key="h"
                    class="absolute right-1 text-[10px] text-gray-400 leading-none"
                    :style="{ top: `${(h - START_HOUR) * HOUR_H - 6}px` }">
                    {{ String(h).padStart(2, '0') }}:00
                </div>
            </div>
        </div>

        <!-- 日列 × 7 -->
        <div class="flex flex-1">
            <div v-for="(day, di) in days" :key="di"
                class="relative flex flex-col border-r border-gray-200 last:border-r-0"
                style="min-width: 80px; flex: 1">

                <!-- 曜日ヘッダー -->
                <div :style="{ height: HEADER_H + 'px' }"
                    class="sticky top-0 z-20 flex shrink-0 flex-col items-center justify-center border-b border-gray-200"
                    :class="isToday(day) ? 'bg-blue-50' : 'bg-white'"
                    :title="holidayName(dateStr(day)) ?? undefined">
                    <span class="text-[10px] font-semibold"
                        :class="di === 0 || isHoliday(dateStr(day)) ? 'text-red-500' : di === 6 ? 'text-blue-500' : 'text-gray-500'">
                        {{ DAYS_JA[day.getDay()] }}
                    </span>
                    <span class="flex h-5 w-5 items-center justify-center text-xs font-bold leading-none"
                        :class="[
                            isToday(day) ? 'rounded-full bg-blue-600 text-white' : 'text-gray-800',
                            (di === 0 || isHoliday(dateStr(day))) && !isToday(day) ? 'text-red-500' : '',
                            di === 6 && !isHoliday(dateStr(day)) && !isToday(day) ? 'text-blue-500' : '',
                        ]">
                        {{ day.getDate() }}
                    </span>
                </div>

                <!-- 日程行（worktype） -->
                <div :style="{ height: WORKTYPE_H + 'px', top: HEADER_H + 'px' }"
                    class="sticky z-20 flex items-center justify-center border-b border-gray-200 bg-gray-100 px-0.5">
                    <span class="truncate text-[10px] font-medium text-gray-600">
                        {{ worktypeForDay(day)?.name ?? '' }}
                    </span>
                </div>

                <!-- タイムグリッド -->
                <div :ref="el => setGridRef(el, di)"
                    :style="{ height: TOTAL_H + 'px' }"
                    class="relative cursor-crosshair"
                    :class="isToday(day) ? 'bg-blue-50/20' : ''"
                    @mousedown="onGridMousedown(di, $event)">

                    <!-- 時間グリッド線 -->
                    <div v-for="h in hours" :key="h"
                        class="pointer-events-none absolute inset-x-0 border-t border-gray-100"
                        :style="{ top: `${(h - START_HOUR) * HOUR_H}px` }" />
                    <div v-for="h in hours" :key="'h' + h"
                        class="pointer-events-none absolute inset-x-0 border-t border-gray-50"
                        :style="{ top: `${(h - START_HOUR) * HOUR_H + HOUR_H / 2}px` }" />

                    <!-- 現在時刻ライン（今日の列） -->
                    <div v-if="isToday(day) && nowTop() !== null"
                        class="pointer-events-none absolute inset-x-0 border-t-2 border-red-400"
                        :style="{ top: nowTop(), zIndex: 1000 }">
                        <div class="absolute -left-1 -top-1.5 h-3 w-3 rounded-full bg-red-400" />
                    </div>

                    <!-- 選択プレビュー -->
                    <div v-if="selStyle(di)"
                        class="pointer-events-none absolute inset-x-0.5 rounded bg-blue-300/40 border border-blue-400"
                        :style="selStyle(di)" />

                    <!-- イベント -->
                    <div v-for="ev in eventsForDay(day)" :key="ev.id"
                        class="absolute inset-x-0.5 overflow-hidden rounded border px-1 pt-0.5 text-xs"
                        :class="ev.is_own ? 'cursor-grab' : 'cursor-default'"
                        :style="evStyle(ev)"
                        @mousedown.stop="onEventMousedown(ev, 'move', $event)"
                        @click.stop="$emit('event-click', ev)">
                        <!-- リサイズ上端（自分の予定のみ） -->
                        <div v-if="ev.is_own" class="absolute inset-x-0 top-0 h-2 cursor-n-resize"
                            @mousedown.stop="onEventMousedown(ev, 'resize-top', $event)" />
                        <div class="pointer-events-none font-semibold leading-tight line-clamp-2">{{ ev.title }}</div>
                        <div class="pointer-events-none text-[10px] opacity-80">{{ fmtTime(ev.starts_at) }}</div>
                        <!-- リサイズ下端（自分の予定のみ） -->
                        <div v-if="ev.is_own" class="absolute inset-x-0 bottom-0 h-2 cursor-s-resize"
                            @mousedown.stop="onEventMousedown(ev, 'resize-bot', $event)" />
                    </div>

                    <!-- ドラッグ中プレビュー -->
                    <div v-if="dragStyle(di)"
                        class="pointer-events-none absolute inset-x-0.5 overflow-hidden rounded border px-1 pt-0.5 text-xs opacity-90 shadow-lg ring-2 ring-white"
                        :style="dragStyle(di)">
                        <div class="font-semibold leading-tight">{{ dragging.ev.title }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 会議室セクション（rooms が存在するときのみ表示） -->
    <div v-if="rooms.length" class="mt-2 overflow-hidden rounded-lg border border-gray-200 bg-white select-none">
        <!-- トグルヘッダー -->
        <button
            class="flex w-full items-center gap-1 border-b border-gray-200 bg-gray-50 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-100"
            @click="showRoomSection = !showRoomSection">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
            </svg>
            会議室予約
            <svg xmlns="http://www.w3.org/2000/svg" class="ml-auto h-3.5 w-3.5 transition-transform" :class="showRoomSection ? '' : '-rotate-90'" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
            </svg>
        </button>

        <div v-if="showRoomSection">
            <!-- 行: 室 × 列: 曜日 -->
            <div v-for="room in rooms" :key="room.id"
                class="grid border-b border-gray-100 last:border-b-0"
                style="grid-template-columns: 56px repeat(7, 1fr)">
                <!-- 室名ラベル -->
                <div class="flex items-center justify-end border-r border-gray-200 bg-gray-50 px-1.5 py-1 text-[10px] font-medium text-gray-600 leading-tight">
                    {{ room.name }}
                </div>
                <!-- 曜日セル -->
                <div v-for="(day, di) in days" :key="di"
                    class="min-h-8 border-r border-gray-100 p-0.5 last:border-r-0">
                    <div v-for="res in reservationsForRoomAndDay(room.id, day)" :key="res.id"
                        class="mb-0.5 cursor-pointer truncate rounded px-1 py-0.5 text-[10px] text-white leading-tight"
                        :style="{ background: room.color || '#6b7280' }"
                        @click="$emit('room-click', res)">
                        {{ fmtResTime(res.starts_at) }} {{ res.title }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
