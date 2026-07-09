<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { evColor } from '@/Composables/useEventTypeColors';

const props = defineProps({
    date:            { type: String,  required: true },
    events:          { type: Array,   default: () => [] },
    worktypes:       { type: Array,   default: () => [] },
    dailyWorktypes:  { type: Array,   default: () => [] },
    defaultWorktype: { type: Object,  default: null },
    dailyBreaks:     { type: Array,   default: () => [] },
    defaultBreak:    { type: Object,  default: () => ({ start: '12:00', end: '13:00' }) },
    hasDiary:        { type: Boolean, default: false },
});

const emit = defineEmits(['create', 'update', 'event-click', 'diary-click']);

const START_HOUR = 7;
const END_HOUR   = 22;
const HOUR_H     = 64;
const SNAP       = 15;
const TOTAL_H    = (END_HOUR - START_HOUR) * HOUR_H;
const DAYS_JA    = ['日', '月', '火', '水', '木', '金', '土'];

const hours = Array.from({ length: END_HOUR - START_HOUR }, (_, i) => START_HOUR + i);

// ── 日付情報 ───────────────────────────────────────────────────
const todayStr  = new Date().toLocaleDateString('sv-SE');
const isToday   = computed(() => props.date === todayStr);
const dateObj   = computed(() => new Date(props.date + 'T00:00:00'));
const dateLabel = computed(() => {
    const d = dateObj.value;
    return `${d.getFullYear()}年${d.getMonth() + 1}月${d.getDate()}日（${DAYS_JA[d.getDay()]}）`;
});

// ── 勤務形態 ──────────────────────────────────────────────────
const todayWorktype = computed(() => {
    const hit = props.dailyWorktypes.find(dw => dw.date === props.date);
    const id  = hit?.worktype_id ?? props.defaultWorktype?.id ?? null;
    return id ? (props.worktypes.find(wt => wt.id === id) ?? null) : (props.defaultWorktype ?? null);
});

// ── 休憩時間 ──────────────────────────────────────────────────
function parseHM(timeStr) {
    if (!timeStr) return null;
    const [h, m] = timeStr.split(':');
    return parseInt(h) * 60 + parseInt(m);
}

const todayBreak    = computed(() => props.dailyBreaks?.find(b => b.date === props.date) ?? props.defaultBreak ?? null);
const breakStartMin = computed(() => parseHM(todayBreak.value?.start));
const breakEndMin   = computed(() => parseHM(todayBreak.value?.end));
const breakLabel    = computed(() => {
    const b = todayBreak.value;
    return (b?.start && b?.end) ? `${b.start.substring(0, 5)} 〜 ${b.end.substring(0, 5)}` : null;
});

// ── タイムライン イベント ─────────────────────────────────────
function localMin(isoStr) {
    const d = new Date(isoStr);
    return d.getHours() * 60 + d.getMinutes();
}

function eventDuration(ev) {
    return Math.max(0, new Date(ev.ends_at).getTime() - new Date(ev.starts_at).getTime());
}

const dayEvents = computed(() =>
    props.events
        .filter(ev => new Date(ev.starts_at).toLocaleDateString('sv-SE') === props.date)
        .sort((a, b) => eventDuration(b) - eventDuration(a))
);

function evTop(ev)    { return Math.max(0, localMin(ev.starts_at) - START_HOUR * 60) * (HOUR_H / 60); }
function evHeight(ev) { return Math.max(18, (localMin(ev.ends_at) - localMin(ev.starts_at)) * (HOUR_H / 60)); }
function fmtTime(isoStr) {
    return new Date(isoStr).toLocaleTimeString('ja-JP', { hour: '2-digit', minute: '2-digit', hour12: false });
}

// ── 休憩オーバーレイ ──────────────────────────────────────────
const breakOverlayStyle = computed(() => {
    const s = breakStartMin.value;
    const e = breakEndMin.value;
    if (s == null || e == null || e <= s) return null;
    return {
        top:    `${(s - START_HOUR * 60) * (HOUR_H / 60)}px`,
        height: `${(e - s) * (HOUR_H / 60)}px`,
    };
});

// ── 現在時刻ライン ─────────────────────────────────────────────
const nowMin = ref(new Date().getHours() * 60 + new Date().getMinutes());
let ticker;
onMounted(() => { ticker = setInterval(() => { nowMin.value = new Date().getHours() * 60 + new Date().getMinutes(); }, 60000); });
onUnmounted(() => { clearInterval(ticker); });

const nowStyle = computed(() => {
    if (!isToday.value) return null;
    const top = (nowMin.value - START_HOUR * 60) * (HOUR_H / 60);
    return (top >= 0 && top <= TOTAL_H) ? { top: `${top}px` } : null;
});

// ── スクロール ────────────────────────────────────────────────
const timelineRef = ref(null);

function scrollToTime() {
    if (!timelineRef.value || timelineRef.value.scrollHeight <= timelineRef.value.clientHeight) return false;
    const now = new Date();
    const targetMin = isToday.value ? now.getHours() * 60 + now.getMinutes() : 8 * 60;
    timelineRef.value.scrollTop = Math.max(0, (targetMin - START_HOUR * 60) * (HOUR_H / 60) - 160);
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

// ── ドラッグ ──────────────────────────────────────────────────
const gridRef  = ref(null);
const selecting = ref(null);
const dragging  = ref(null);

function yToMin(clientY) {
    if (!gridRef.value) return START_HOUR * 60;
    const rect = gridRef.value.getBoundingClientRect();
    const raw  = Math.round(((clientY - rect.top) / (HOUR_H / 60)) / SNAP) * SNAP + START_HOUR * 60;
    return Math.max(START_HOUR * 60, Math.min(END_HOUR * 60, raw));
}

function onGridMousedown(e) {
    if (e.button !== 0 || dragging.value) return;
    e.preventDefault();
    const min = yToMin(e.clientY);
    selecting.value = { startMin: min, currentMin: min };
}

function onEventMousedown(ev, type, e) {
    if (!ev.is_own) return;
    if (e.button !== 0) return;
    e.preventDefault();
    e.stopPropagation();
    const origStart = localMin(ev.starts_at);
    const origEnd   = localMin(ev.ends_at);
    dragging.value = {
        type, ev: { ...ev },
        startMin:  origStart,
        endMin:    origEnd,
        origStart, origEnd,
        offsetMin: type === 'move' ? yToMin(e.clientY) - origStart : 0,
    };
}

function onMousemove(e) {
    if (selecting.value) {
        selecting.value = { ...selecting.value, currentMin: yToMin(e.clientY) };
        return;
    }
    if (!dragging.value) return;
    const d = dragging.value;
    if (d.type === 'move') {
        const dur   = d.endMin - d.startMin;
        const start = Math.max(START_HOUR * 60, Math.min(END_HOUR * 60 - dur, yToMin(e.clientY) - d.offsetMin));
        dragging.value = { ...d, startMin: start, endMin: start + dur };
    } else if (d.type === 'resize-top') {
        dragging.value = { ...d, startMin: Math.min(yToMin(e.clientY), d.endMin - SNAP) };
    } else {
        dragging.value = { ...d, endMin: Math.max(yToMin(e.clientY), d.startMin + SNAP) };
    }
}

function onMouseup() {
    if (selecting.value) {
        const { startMin, currentMin } = selecting.value;
        const sMin = Math.min(startMin, currentMin);
        const eMin = Math.max(startMin, currentMin);
        if (eMin - sMin >= SNAP) {
            emit('create', { date: props.date, startMin: sMin, endMin: eMin });
        } else {
            emit('create', { date: props.date, startMin: sMin, endMin: Math.min(sMin + 60, END_HOUR * 60) });
        }
        selecting.value = null;
        return;
    }
    if (dragging.value) {
        const { startMin, endMin, origStart, origEnd, ev } = dragging.value;
        // 実際に移動・リサイズがあった場合のみ update を emit
        if (startMin !== origStart || endMin !== origEnd) {
            const pad  = n => String(n).padStart(2, '0');
            const toHm = m => `${pad(Math.floor(m / 60))}:${pad(m % 60)}`;
            emit('update', { id: ev.id, starts_at: `${props.date} ${toHm(startMin)}:00`, ends_at: `${props.date} ${toHm(endMin)}:00` });
        }
        dragging.value = null;
    }
}

onMounted(async () => {
    window.addEventListener('mousemove', onMousemove);
    window.addEventListener('mouseup',   onMouseup);
    scheduleScrollToTime();
});
onUnmounted(() => {
    window.removeEventListener('mousemove', onMousemove);
    window.removeEventListener('mouseup',   onMouseup);
});

watch(() => props.date, scheduleScrollToTime);

// ── スタイル計算 ──────────────────────────────────────────────
const selStyle = computed(() => {
    if (!selecting.value) return null;
    const { startMin, currentMin } = selecting.value;
    const top    = (Math.min(startMin, currentMin) - START_HOUR * 60) * (HOUR_H / 60);
    const height = Math.abs(currentMin - startMin) * (HOUR_H / 60);
    return height > 2 ? { top: `${top}px`, height: `${height}px` } : null;
});

function evStyle(ev) {
    const isDragging = dragging.value?.ev?.id === ev.id && dragging.value.type === 'move';
    return {
        top:         `${evTop(ev)}px`,
        height:      `${evHeight(ev)}px`,
        zIndex:      Math.min(900, Math.max(1, Math.round(86_400_000 / Math.max(1, eventDuration(ev))))),
        background:  evColor(ev).bg,
        color:       evColor(ev).text,
        borderColor: evColor(ev).border,
        opacity:     isDragging ? '0.3' : ev.completed ? '0.45' : '1',
    };
}

const dragStyle = computed(() => {
    if (!dragging.value) return null;
    const { startMin, endMin, ev } = dragging.value;
    return {
        top:         `${(startMin - START_HOUR * 60) * (HOUR_H / 60)}px`,
        height:      `${(endMin - startMin) * (HOUR_H / 60)}px`,
        zIndex:      1100,
        background:  evColor(ev).bg,
        color:       evColor(ev).text,
        borderColor: evColor(ev).border,
    };
});
</script>

<template>
    <div class="flex h-full min-h-0 overflow-hidden rounded-lg border border-gray-200 bg-white select-none">

        <!-- ── 左: タイムライン ──────────────────────────────── -->
        <div ref="timelineRef" class="flex min-h-0 flex-col flex-1 min-w-0 overflow-y-auto">
            <!-- ヘッダー -->
            <div class="sticky top-0 z-20 flex shrink-0 border-b border-gray-200 bg-white">
                <div style="width: 44px" class="shrink-0 bg-gray-50 border-r border-gray-200" />
                <div class="flex-1 flex items-center justify-center py-2 bg-white"
                    :class="isToday ? 'bg-blue-50' : ''">
                    <span class="text-sm font-semibold text-gray-700">{{ dateLabel }}</span>
                    <span v-if="isToday" class="ml-2 rounded-full bg-blue-600 px-2 py-0.5 text-xs text-white font-medium">今日</span>
                </div>
            </div>

            <!-- グリッド本体 -->
            <div class="flex flex-1">
                <!-- 時刻ラベル -->
                <div class="shrink-0 bg-gray-50 border-r border-gray-200" style="width: 44px">
                    <div :style="{ height: TOTAL_H + 'px' }" class="relative">
                        <div v-for="h in hours" :key="h"
                            class="absolute right-1 text-[10px] text-gray-400 leading-none"
                            :style="{ top: `${(h - START_HOUR) * HOUR_H - 6}px` }">
                            {{ String(h).padStart(2, '0') }}:00
                        </div>
                    </div>
                </div>

                <!-- イベントグリッド -->
                <div ref="gridRef"
                    :style="{ height: TOTAL_H + 'px' }"
                    class="relative flex-1 cursor-crosshair"
                    :class="isToday ? 'bg-blue-50/20' : ''"
                    @mousedown="onGridMousedown">

                    <!-- 時間グリッド線 -->
                    <div v-for="h in hours" :key="h"
                        class="pointer-events-none absolute inset-x-0 border-t border-gray-100"
                        :style="{ top: `${(h - START_HOUR) * HOUR_H}px` }" />
                    <div v-for="h in hours" :key="'hh' + h"
                        class="pointer-events-none absolute inset-x-0 border-t border-gray-50"
                        :style="{ top: `${(h - START_HOUR) * HOUR_H + HOUR_H / 2}px` }" />

                    <!-- 休憩オーバーレイ -->
                    <div v-if="breakOverlayStyle"
                        class="pointer-events-none absolute inset-x-0 bg-amber-50/70 border-y border-amber-100"
                        :style="breakOverlayStyle">
                        <span class="absolute right-1 top-0.5 text-[9px] font-medium text-amber-500">休憩</span>
                    </div>

                    <!-- 選択プレビュー -->
                    <div v-if="selStyle"
                        class="pointer-events-none absolute inset-x-0.5 rounded bg-blue-300/40 border border-blue-400"
                        :style="selStyle" />

                    <!-- イベント -->
                    <div v-for="ev in dayEvents" :key="ev.id"
                        class="absolute inset-x-1.5 overflow-hidden rounded border px-1.5 pt-0.5 text-xs"
                        :class="ev.is_own ? 'cursor-grab' : 'cursor-default'"
                        :style="evStyle(ev)"
                        @mousedown.stop="onEventMousedown(ev, 'move', $event)"
                        @click.stop="emit('event-click', ev)">
                        <div v-if="ev.is_own" class="absolute inset-x-0 top-0 h-2 cursor-n-resize"
                            @mousedown.stop="onEventMousedown(ev, 'resize-top', $event)" />
                        <div class="pointer-events-none font-semibold leading-tight line-clamp-2">{{ ev.title }}</div>
                        <div class="pointer-events-none text-[10px] opacity-80">{{ fmtTime(ev.starts_at) }}</div>
                        <div v-if="ev.is_own" class="absolute inset-x-0 bottom-0 h-2 cursor-s-resize"
                            @mousedown.stop="onEventMousedown(ev, 'resize-bot', $event)" />
                    </div>

                    <!-- ドラッグ中プレビュー -->
                    <div v-if="dragStyle"
                        class="pointer-events-none absolute inset-x-1.5 overflow-hidden rounded border px-1.5 pt-0.5 text-xs opacity-90 shadow-lg ring-2 ring-white"
                        :style="dragStyle">
                        <div class="font-semibold leading-tight">{{ dragging.ev.title }}</div>
                    </div>

                    <!-- 現在時刻ライン -->
                    <div v-if="nowStyle" class="pointer-events-none absolute inset-x-0"
                        :style="{ ...nowStyle, zIndex: 1000 }">
                        <div class="absolute -left-0.5 -top-[5px] h-2.5 w-2.5 rounded-full bg-red-500" />
                        <div class="absolute inset-x-2 h-px bg-red-400" />
                    </div>
                </div>
            </div>
        </div>

        <!-- ── 右: サマリーパネル ─────────────────────────────── -->
        <div class="w-2/5 shrink-0 border-l border-gray-200 bg-gray-50 flex flex-col overflow-y-auto">

            <!-- 勤務形態 -->
            <div class="px-3 py-2.5 border-b border-gray-200">
                <div class="mb-1 text-[10px] font-semibold uppercase tracking-wide text-gray-400">勤務形態</div>
                <div v-if="todayWorktype" class="text-sm font-semibold text-gray-700">{{ todayWorktype.name }}</div>
                <div v-else class="text-sm text-gray-400">設定なし</div>
                <div v-if="todayWorktype?.start_time && todayWorktype?.end_time"
                    class="mt-0.5 text-xs text-gray-500">
                    {{ todayWorktype.start_time.substring(0, 5) }} 〜 {{ todayWorktype.end_time.substring(0, 5) }}
                </div>
            </div>

            <!-- 休憩 -->
            <div class="px-3 py-2.5 border-b border-gray-200">
                <div class="mb-1 text-[10px] font-semibold uppercase tracking-wide text-gray-400">休憩</div>
                <div v-if="breakLabel" class="text-sm text-gray-700">{{ breakLabel }}</div>
                <div v-else class="text-sm text-gray-400">設定なし</div>
            </div>

            <!-- 日報 -->
            <div class="px-3 py-2.5 border-b border-gray-200">
                <div class="mb-1 text-[10px] font-semibold uppercase tracking-wide text-gray-400">日報</div>
                <div class="mb-1.5">
                    <span v-if="hasDiary" class="text-sm font-medium text-emerald-600">✓ 記入済み</span>
                    <span v-else class="text-sm text-gray-400">未記入</span>
                </div>
                <button
                    class="w-full rounded border py-1 text-xs font-medium transition-colors"
                    :class="hasDiary
                        ? 'border-gray-200 bg-gray-100 text-gray-600 hover:bg-gray-200'
                        : 'border-orange-200 bg-orange-50 text-orange-700 hover:bg-orange-100'"
                    @click="emit('diary-click', date)">
                    {{ hasDiary ? '日報を見る' : '日報を書く' }}
                </button>
            </div>

            <!-- 本日の予定一覧 -->
            <div v-if="dayEvents.length" class="px-3 py-2.5 border-b border-gray-200">
                <div class="mb-1.5 text-[10px] font-semibold uppercase tracking-wide text-gray-400">
                    予定 ({{ dayEvents.length }}件)
                </div>
                <div v-for="ev in dayEvents" :key="ev.id"
                    class="mb-1 flex cursor-pointer items-start gap-1.5 rounded px-1.5 py-1 text-xs hover:bg-gray-100"
                    @click="emit('event-click', ev)">
                    <span class="mt-0.5 h-2 w-2 shrink-0 rounded-full"
                        :style="{ background: evColor(ev).bg }" />
                    <span class="leading-tight text-gray-700">
                        <span class="text-[10px] text-gray-400">{{ fmtTime(ev.starts_at) }}</span>
                        <br>{{ ev.title }}
                    </span>
                </div>
            </div>
            <div v-else class="px-3 py-2.5 border-b border-gray-200">
                <div class="text-[10px] font-semibold uppercase tracking-wide text-gray-400 mb-1">予定</div>
                <div class="text-xs text-gray-400">なし</div>
            </div>

            <!-- クイックアクション -->
            <div class="mt-auto px-3 py-3 space-y-1.5">
                <button
                    class="flex w-full items-center justify-center gap-1.5 rounded border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-medium text-emerald-800 hover:bg-emerald-100 transition-colors"
                    @click="emit('create', { date, startMin: null, endMin: null })">
                    <span>📅</span> 予定を追加
                </button>
            </div>
        </div>
    </div>
</template>
