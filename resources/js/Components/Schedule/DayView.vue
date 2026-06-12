<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    date:         { type: String, required: true },
    events:       { type: Array,  default: () => [] },
    reservations: { type: Array,  default: () => [] }, // [{id, meeting_room_id, ...}]
    overlayUsers: { type: Array,  default: () => [] }, // [{id, name}]
    rooms:        { type: Array,  default: () => [] }, // [{id, name, color}]
});

const emit = defineEmits(['create', 'update', 'event-click', 'room-create', 'room-click']);

// ── 定数 ──────────────────────────────────────────────────────
const START_HOUR = 7;
const END_HOUR   = 23;
const HOUR_H     = 64;   // px/hour
const SNAP       = 15;   // 分スナップ
const TOTAL_H    = (END_HOUR - START_HOUR) * HOUR_H;
const COL_W      = 168;  // px/column
const TIME_W     = 44;   // px（時刻列幅）
const HEADER_H   = 48;   // px（カラムヘッダー高さ）

const hours = computed(() =>
    Array.from({ length: END_HOUR - START_HOUR }, (_, i) => START_HOUR + i)
);

function isToday() {
    return props.date === new Date().toLocaleDateString('sv-SE');
}

// ── カラム定義 ─────────────────────────────────────────────────
// type: 'own' | 'user' | 'room'
const columns = computed(() => [
    { type: 'own',  id: 'own', label: '自分の予定', color: '#3b82f6', sublabel: '' },
    ...props.overlayUsers.map(u => ({
        type: 'user', id: u.id, label: u.name, color: '#6b7280', sublabel: '',
    })),
    ...props.rooms.map(r => ({
        type: 'room', id: r.id, label: r.name, color: r.color ?? '#9ca3af', sublabel: '会議室',
    })),
]);

// ── イベント振り分け ───────────────────────────────────────────
function localMin(isoStr) {
    const d = new Date(isoStr);
    return d.getHours() * 60 + d.getMinutes();
}

function eventsForCol(col) {
    return props.events.filter(ev => {
        if (new Date(ev.starts_at).toLocaleDateString('sv-SE') !== props.date) return false;
        if (col.type === 'own')  return ev.is_own === true;
        if (col.type === 'user') return !ev.is_own && String(ev.user_id) === String(col.id);
        return false;
    });
}

function reservationsForRoom(roomId) {
    return props.reservations.filter(r =>
        String(r.meeting_room_id) === String(roomId) &&
        new Date(r.starts_at).toLocaleDateString('sv-SE') === props.date
    );
}

function resStyle(r) {
    const roomColor = props.rooms.find(rm => String(rm.id) === String(r.meeting_room_id))?.color ?? '#9ca3af';
    const s = localMin(r.starts_at);
    const e = localMin(r.ends_at);
    return {
        top:         `${(s - START_HOUR * 60) * (HOUR_H / 60)}px`,
        height:      `${Math.max(18, (e - s) * (HOUR_H / 60))}px`,
        background:  roomColor,
        color:       '#fff',
        borderColor: roomColor,
        opacity:     '0.85',
    };
}

// ── 会議室グリッドのクリックで予約作成 ───────────────────────
function onRoomGridClick(e, roomId) {
    const el   = e.currentTarget;
    const rect = el.getBoundingClientRect();
    const raw  = Math.round(((e.clientY - rect.top) / (HOUR_H / 60)) / SNAP) * SNAP + START_HOUR * 60;
    const sMin = Math.max(START_HOUR * 60, Math.min(END_HOUR * 60, raw));
    const eMin = Math.min(END_HOUR * 60, sMin + 60);
    emit('room-create', { date: props.date, startMin: sMin, endMin: eMin, roomId });
}

// ── カラー ────────────────────────────────────────────────────
const PALETTE = [
    { bg: '#3b82f6', text: '#fff', border: '#2563eb' },
    { bg: '#10b981', text: '#fff', border: '#059669' },
    { bg: '#8b5cf6', text: '#fff', border: '#7c3aed' },
    { bg: '#f97316', text: '#fff', border: '#ea580c' },
    { bg: '#0ea5e9', text: '#fff', border: '#0284c7' },
];
function evColor(ev) {
    if (!ev.is_own) return { bg: '#e5e7eb', text: '#374151', border: '#d1d5db' };
    return PALETTE[(ev.id ?? 0) % PALETTE.length];
}
function fmtTime(isoStr) {
    return new Date(isoStr).toLocaleTimeString('ja-JP', {
        hour: '2-digit', minute: '2-digit', hour12: false,
    });
}

// ── 現在時刻ライン ────────────────────────────────────────────
function nowTop() {
    const now = new Date();
    const m = now.getHours() * 60 + now.getMinutes() - START_HOUR * 60;
    return `${Math.max(0, m * (HOUR_H / 60))}px`;
}

// ── ドラッグ / 選択（自分カラムのみ） ─────────────────────────
const ownGridRef = ref(null);
const selecting  = ref(null);
const dragging   = ref(null);

function setOwnRef(el) { ownGridRef.value = el ?? null; }

function yToMin(clientY) {
    const el = ownGridRef.value;
    if (!el) return START_HOUR * 60;
    const rect = el.getBoundingClientRect();
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
    if (!ev.is_own || e.button !== 0) return;
    e.preventDefault();
    e.stopPropagation();
    const startMin = localMin(ev.starts_at);
    const endMin   = localMin(ev.ends_at);
    dragging.value = {
        type, ev: { ...ev }, startMin, endMin,
        offsetMin: type === 'move' ? yToMin(e.clientY) - startMin : 0,
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
        }
        selecting.value = null;
        return;
    }
    if (dragging.value) {
        const { startMin, endMin, ev } = dragging.value;
        const pad  = (n) => String(n).padStart(2, '0');
        const toHm = (m) => `${pad(Math.floor(m / 60))}:${pad(m % 60)}`;
        emit('update', {
            id:        ev.id,
            starts_at: `${props.date} ${toHm(startMin)}:00`,
            ends_at:   `${props.date} ${toHm(endMin)}:00`,
        });
        dragging.value = null;
    }
}

onMounted(() => {
    window.addEventListener('mousemove', onMousemove);
    window.addEventListener('mouseup',   onMouseup);
});
onUnmounted(() => {
    window.removeEventListener('mousemove', onMousemove);
    window.removeEventListener('mouseup',   onMouseup);
});

// ── スタイル計算 ───────────────────────────────────────────────
function selStyle() {
    if (!selecting.value) return null;
    const { startMin, currentMin } = selecting.value;
    const top    = (Math.min(startMin, currentMin) - START_HOUR * 60) * (HOUR_H / 60);
    const height = Math.abs(currentMin - startMin) * (HOUR_H / 60);
    return height > 2 ? { top: `${top}px`, height: `${height}px` } : null;
}

function evStyle(ev) {
    const s = localMin(ev.starts_at);
    const e = localMin(ev.ends_at);
    return {
        top:         `${(s - START_HOUR * 60) * (HOUR_H / 60)}px`,
        height:      `${Math.max(18, (e - s) * (HOUR_H / 60))}px`,
        background:  evColor(ev).bg,
        color:       evColor(ev).text,
        borderColor: evColor(ev).border,
    };
}

function dragStyle() {
    if (!dragging.value) return null;
    const { startMin, endMin, ev } = dragging.value;
    return {
        top:         `${(startMin - START_HOUR * 60) * (HOUR_H / 60)}px`,
        height:      `${(endMin - startMin) * (HOUR_H / 60)}px`,
        background:  evColor(ev).bg,
        color:       evColor(ev).text,
        borderColor: evColor(ev).border,
    };
}
</script>

<template>
    <!-- overflow-x-auto で多カラム時に横スクロール -->
    <div class="select-none overflow-x-auto rounded-lg border border-gray-200 bg-white"
        style="min-height: 520px">

        <div class="flex" :style="{ minWidth: `${TIME_W + columns.length * COL_W}px` }">

            <!-- ── 時刻列（sticky left）─────────────────────────── -->
            <div class="sticky left-0 z-20 shrink-0 border-r border-gray-200 bg-gray-50"
                :style="{ width: TIME_W + 'px' }">

                <!-- ヘッダー空白 -->
                <div :style="{ height: HEADER_H + 'px' }"
                    class="flex items-end justify-end border-b border-gray-200 pb-1 pr-1">
                    <span class="text-[9px] text-gray-400">
                        {{ new Date(date + 'T00:00:00').toLocaleDateString('ja-JP', { month: 'numeric', day: 'numeric' }) }}
                    </span>
                </div>

                <!-- 時刻ラベル -->
                <div class="relative" :style="{ height: TOTAL_H + 'px' }">
                    <div v-for="h in hours" :key="h"
                        class="absolute right-1 text-[10px] leading-none text-gray-400"
                        :style="{ top: `${(h - START_HOUR) * HOUR_H - 6}px` }">
                        {{ String(h).padStart(2, '0') }}:00
                    </div>
                </div>
            </div>

            <!-- ── カラム群 ──────────────────────────────────────── -->
            <div v-for="(col, ci) in columns" :key="`${col.type}-${col.id}`"
                class="flex shrink-0 flex-col border-r border-gray-200 last:border-r-0"
                :style="{ width: COL_W + 'px' }">

                <!-- カラムヘッダー -->
                <div :style="{ height: HEADER_H + 'px', borderBottom: `3px solid ${col.color}` }"
                    class="flex shrink-0 flex-col items-center justify-center px-2 bg-white">
                    <span v-if="col.sublabel" class="text-[9px] text-gray-400">{{ col.sublabel }}</span>
                    <span class="max-w-full truncate text-xs font-semibold text-gray-700">{{ col.label }}</span>
                </div>

                <!-- グリッド -->
                <div
                    :ref="col.type === 'own' ? setOwnRef : undefined"
                    :style="{ height: TOTAL_H + 'px' }"
                    class="relative"
                    :class="[
                        col.type === 'own' ? 'cursor-crosshair' : 'cursor-default',
                        col.type === 'own' && isToday() ? 'bg-blue-50/30' : '',
                    ]"
                    @mousedown="col.type === 'own' ? onGridMousedown($event) : undefined">

                    <!-- 時間グリッド線 -->
                    <template v-for="h in hours" :key="h">
                        <div class="pointer-events-none absolute inset-x-0 border-t border-gray-100"
                            :style="{ top: `${(h - START_HOUR) * HOUR_H}px` }" />
                        <div class="pointer-events-none absolute inset-x-0 border-t border-gray-50"
                            :style="{ top: `${(h - START_HOUR) * HOUR_H + HOUR_H / 2}px` }" />
                    </template>

                    <!-- 現在時刻ライン（全カラムに横断） -->
                    <div v-if="isToday()"
                        class="pointer-events-none absolute inset-x-0 z-10 border-t-2 border-red-400"
                        :style="{ top: nowTop() }">
                        <!-- ドットは最初のカラムのみ -->
                        <div v-if="ci === 0"
                            class="absolute -left-1 -top-1.5 h-3 w-3 rounded-full bg-red-400" />
                    </div>

                    <!-- 選択プレビュー（自分カラムのみ） -->
                    <div v-if="col.type === 'own' && selStyle()"
                        class="pointer-events-none absolute inset-x-0.5 rounded border border-blue-400 bg-blue-300/40"
                        :style="selStyle()" />

                    <!-- イベント -->
                    <div v-for="ev in eventsForCol(col)" :key="ev.id"
                        class="absolute inset-x-0.5 overflow-hidden rounded border px-1 pt-0.5 text-xs"
                        :class="ev.is_own ? 'cursor-grab' : 'cursor-pointer'"
                        :style="[evStyle(ev), {
                            opacity: (dragging?.ev?.id === ev.id && dragging?.type === 'move') ? '0.3' : '1',
                        }]"
                        @mousedown.stop="ev.is_own ? onEventMousedown(ev, 'move', $event) : null"
                        @click.stop="$emit('event-click', ev)">

                        <!-- リサイズハンドル（自分イベントのみ） -->
                        <div v-if="ev.is_own"
                            class="absolute inset-x-0 top-0 h-2 cursor-n-resize"
                            @mousedown.stop="onEventMousedown(ev, 'resize-top', $event)" />
                        <div class="pointer-events-none line-clamp-2 font-semibold leading-tight">
                            {{ ev.title }}
                        </div>
                        <div class="pointer-events-none text-[10px] opacity-80">
                            {{ fmtTime(ev.starts_at) }}–{{ fmtTime(ev.ends_at) }}
                        </div>
                        <div v-if="ev.is_own"
                            class="absolute inset-x-0 bottom-0 h-2 cursor-s-resize"
                            @mousedown.stop="onEventMousedown(ev, 'resize-bot', $event)" />
                    </div>

                    <!-- 会議室カラム：予約一覧 + クリック新規作成 -->
                    <template v-if="col.type === 'room'">
                        <!-- 背景色 -->
                        <div class="pointer-events-none absolute inset-0"
                            :style="{ background: col.color ? `${col.color}08` : 'transparent' }" />
                        <!-- クリックで新規予約 -->
                        <div class="absolute inset-0 cursor-pointer"
                            @click="onRoomGridClick($event, col.id)" />
                        <!-- 予約ブロック -->
                        <div v-for="res in reservationsForRoom(col.id)" :key="res.id"
                            class="absolute inset-x-0.5 overflow-hidden rounded border px-1 pt-0.5 text-xs cursor-pointer"
                            :style="resStyle(res)"
                            @click.stop="$emit('room-click', res)">
                            <div class="pointer-events-none line-clamp-2 font-semibold leading-tight">{{ res.title }}</div>
                            <div class="pointer-events-none text-[10px] opacity-90">
                                {{ fmtTime(res.starts_at) }}–{{ fmtTime(res.ends_at) }}
                            </div>
                        </div>
                    </template>

                    <!-- ドラッグプレビュー（自分カラムのみ） -->
                    <div v-if="col.type === 'own' && dragStyle()"
                        class="pointer-events-none absolute inset-x-0.5 overflow-hidden rounded border px-1 pt-0.5 text-xs opacity-90 shadow-lg ring-2 ring-white"
                        :style="dragStyle()">
                        <div class="font-semibold leading-tight">{{ dragging.ev.title }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
