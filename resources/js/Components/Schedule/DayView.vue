<script setup>
import { ref, computed, inject, onMounted, onUnmounted, nextTick } from 'vue';
import useToasts from '@/Composables/useToasts';
import { evColor } from '@/Composables/useEventTypeColors';

const props = defineProps({
    date:            { type: String, required: true },
    events:          { type: Array,  default: () => [] },
    reservations:    { type: Array,  default: () => [] },
    overlayUsers:    { type: Array,  default: () => [] },
    rooms:           { type: Array,  default: () => [] },
    worktypes:       { type: Array,  default: () => [] },
    dailyWorktypes:  { type: Array,  default: () => [] },
    defaultWorktype: { type: Object, default: null },
});

const emit = defineEmits(['create', 'update', 'event-click', 'room-create', 'room-click', 'room-update']);

const authUser  = inject('authUser', null);
const scrollEl  = inject('calendarScrollEl', null);

// ── 予約不可メッセージ（Toast） ───────────────────────────────
const { showToast } = useToasts();
function showUnavailableMsg(col) {
    const from = col.available_from?.slice(0, 5) ?? '';
    const to   = col.available_to?.slice(0, 5) ?? '';
    showToast(`「${col.label}」の予約可能時間は ${from}〜${to} です`, 'warning', 4000);
}

// ── 定数 ──────────────────────────────────────────────────────
const START_HOUR  = 7;
const END_HOUR    = 23;
const HOUR_H      = 64;   // px/hour
const SNAP        = 15;   // 分スナップ
const TOTAL_H     = (END_HOUR - START_HOUR) * HOUR_H;
const COL_W       = 168;  // px/column（展開時）
const COL_W_CLOSED = 36;  // px/column（折りたたみ時）
const TIME_W      = 44;   // px（時刻列幅）
const HEADER_H    = 48;   // px（カラムヘッダー高さ）

const hours = computed(() =>
    Array.from({ length: END_HOUR - START_HOUR }, (_, i) => START_HOUR + i)
);

function isToday() {
    return props.date === new Date().toLocaleDateString('sv-SE');
}

const todayWorktype = computed(() => {
    const hit = props.dailyWorktypes.find(dw => dw.date === props.date);
    const id  = hit?.worktype_id ?? props.defaultWorktype?.id ?? null;
    return id ? (props.worktypes.find(wt => wt.id === id) ?? null) : props.defaultWorktype ?? null;
});

// ── カラム定義（順序: own → rooms → overlayUsers）─────────────
const baseColumns = computed(() => [
    { type: 'own',  id: 'own', label: '自分の予定', color: '#3b82f6', sublabel: '' },
    ...props.rooms.map(r => ({
        type: 'room', id: r.id, label: r.name, color: r.color ?? '#9ca3af', sublabel: '会議室',
        available_from: r.available_from, available_to: r.available_to,
    })),
    ...props.overlayUsers.map(u => ({
        type: 'user', id: u.id, label: u.name, color: '#6b7280', sublabel: '',
    })),
]);

// ── カラム並び替え（localStorage 永続化） ──────────────────────
const STORAGE_KEY_ORDER   = `sched_col_order_${props.date}`;
const STORAGE_KEY_CLOSED  = 'sched_col_closed';

function loadOrder() {
    try { return JSON.parse(localStorage.getItem(STORAGE_KEY_ORDER) ?? 'null'); } catch { return null; }
}
function loadClosed() {
    try { return new Set(JSON.parse(localStorage.getItem(STORAGE_KEY_CLOSED) ?? '[]')); } catch { return new Set(); }
}

const colOrderKeys = ref(loadOrder());  // null = デフォルト順
const closedCols   = ref(loadClosed()); // Set<string> of `${type}-${id}`

function colKey(col) { return `${col.type}-${col.id}`; }

const columns = computed(() => {
    const base = baseColumns.value;
    if (!colOrderKeys.value) return base;
    const map = Object.fromEntries(base.map(c => [colKey(c), c]));
    // own は必ず先頭
    const ordered = colOrderKeys.value
        .filter(k => k !== 'own-own' && map[k])
        .map(k => map[k]);
    const rest = base.filter(c => c.type !== 'own' && !colOrderKeys.value.includes(colKey(c)));
    return [map['own-own'], ...ordered, ...rest].filter(Boolean);
});

function saveClosed() {
    localStorage.setItem(STORAGE_KEY_CLOSED, JSON.stringify([...closedCols.value]));
}
function saveOrder() {
    localStorage.setItem(STORAGE_KEY_ORDER, JSON.stringify(columns.value.map(colKey)));
}

function toggleCollapse(col) {
    const k = colKey(col);
    if (closedCols.value.has(k)) { closedCols.value.delete(k); }
    else { closedCols.value.add(k); }
    closedCols.value = new Set(closedCols.value); // trigger reactivity
    saveClosed();
}
function isClosed(col) { return closedCols.value.has(colKey(col)); }

// ── カラムドラッグ並び替え ─────────────────────────────────────
const dragColKey   = ref(null);
const dragOverKey  = ref(null);

function onColDragStart(col, e) {
    if (col.type === 'own') { e.preventDefault(); return; }
    dragColKey.value = colKey(col);
    e.dataTransfer.effectAllowed = 'move';
}
function onColDragOver(col, e) {
    if (col.type === 'own' || !dragColKey.value) return;
    e.preventDefault();
    dragOverKey.value = colKey(col);
}
function onColDrop(col) {
    if (!dragColKey.value || col.type === 'own') return;
    const keys   = columns.value.map(colKey);
    const fromIdx = keys.indexOf(dragColKey.value);
    const toIdx   = keys.indexOf(colKey(col));
    if (fromIdx < 0 || toIdx < 0 || fromIdx === toIdx) return;
    const newKeys = [...keys];
    newKeys.splice(fromIdx, 1);
    newKeys.splice(toIdx, 0, dragColKey.value);
    colOrderKeys.value = newKeys;
    saveOrder();
    dragColKey.value  = null;
    dragOverKey.value = null;
}
function onColDragEnd() {
    dragColKey.value  = null;
    dragOverKey.value = null;
}

// ── イベント振り分け ───────────────────────────────────────────
function localMin(isoStr) {
    const d = new Date(isoStr);
    return d.getHours() * 60 + d.getMinutes();
}

function eventsForCol(col) {
    return props.events.filter(ev => {
        if (new Date(ev.starts_at).toLocaleDateString('sv-SE') !== props.date) return false;
        if (col.type === 'own')  return ev.is_own === true || ev.as_attendee === true;
        // as_attendee イベントは own カラムのみ表示（overlay カラムに重複させない）
        if (col.type === 'user') return !ev.is_own && !ev.as_attendee && String(ev.user_id) === String(col.id);
        return false;
    });
}

function reservationsForRoom(roomId) {
    return props.reservations.filter(r =>
        String(r.meeting_room_id) === String(roomId) &&
        new Date(r.starts_at).toLocaleDateString('sv-SE') === props.date
    );
}

// ── 予約可能時間帯の計算 ───────────────────────────────────────
function unavailableStyle(col) {
    if (col.type !== 'room') return [];
    const from = col.available_from;  // "HH:MM:SS" or null
    const to   = col.available_to;
    if (!from || !to) return [];

    const fromMin = parseInt(from.slice(0, 2)) * 60 + parseInt(from.slice(3, 5));
    const toMin   = parseInt(to.slice(0, 2))   * 60 + parseInt(to.slice(3, 5));

    const blocks = [];
    if (fromMin > START_HOUR * 60) {
        blocks.push({
            top:    0,
            height: (fromMin - START_HOUR * 60) * (HOUR_H / 60),
        });
    }
    if (toMin < END_HOUR * 60) {
        blocks.push({
            top:    (toMin - START_HOUR * 60) * (HOUR_H / 60),
            height: (END_HOUR * 60 - toMin) * (HOUR_H / 60),
        });
    }
    return blocks;
}

function isInUnavailable(roomCol, clickMin) {
    const from = roomCol.available_from;
    const to   = roomCol.available_to;
    if (!from || !to) return false;
    const fromMin = parseInt(from.slice(0, 2)) * 60 + parseInt(from.slice(3, 5));
    const toMin   = parseInt(to.slice(0, 2))   * 60 + parseInt(to.slice(3, 5));
    return clickMin < fromMin || clickMin >= toMin;
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

function fmtTime(isoStr) {
    return new Date(isoStr).toLocaleTimeString('ja-JP', {
        hour: '2-digit', minute: '2-digit', hour12: false,
    });
}

// ── 現在時刻ライン ────────────────────────────────────────────
function nowTop() {
    const now = new Date();
    const m = now.getHours() * 60 + now.getMinutes() - START_HOUR * 60;
    if (m < 0 || m > (END_HOUR - START_HOUR) * 60) return null;
    return `${m * (HOUR_H / 60)}px`;
}

// ── 自分カラム: ドラッグ選択・移動・リサイズ ──────────────────
const ownGridRef = ref(null);
const selecting  = ref(null);
const dragging   = ref(null);

function setOwnRef(el) { ownGridRef.value = el ?? null; }

function yToMin(clientY, el) {
    const target = el ?? ownGridRef.value;
    if (!target) return START_HOUR * 60;
    const rect = target.getBoundingClientRect();
    const raw  = Math.round(((clientY - rect.top) / (HOUR_H / 60)) / SNAP) * SNAP + START_HOUR * 60;
    return Math.max(START_HOUR * 60, Math.min(END_HOUR * 60, raw));
}

function onGridMousedown(e) {
    if (e.button !== 0 || dragging.value) return;
    e.preventDefault();
    const min = yToMin(e.clientY);
    selecting.value = { startMin: min, currentMin: min, type: 'own' };
}

function onEventMousedown(ev, type, e) {
    if (!ev.is_own || e.button !== 0) return;
    if (ev.room_reservation_id) return;  // 会議室予約イベントはドラッグ移動不可
    e.preventDefault();
    e.stopPropagation();
    const startMin = localMin(ev.starts_at);
    const endMin   = localMin(ev.ends_at);
    dragging.value = {
        type, ev: { ...ev }, startMin, endMin,
        offsetMin: type === 'move' ? yToMin(e.clientY) - startMin : 0,
    };
}

// ── 会議室カラム: ドラッグ選択 ────────────────────────────────
const roomGridRefs = ref({});  // roomId -> el
const roomSelecting = ref(null); // { roomId, col, startMin, currentMin }

function setRoomRef(roomId, el) {
    if (el) roomGridRefs.value[roomId] = el;
    else delete roomGridRefs.value[roomId];
}

function onRoomMousedown(e, col) {
    if (e.button !== 0) return;
    const el   = roomGridRefs.value[col.id];
    if (!el) return;
    const rect = el.getBoundingClientRect();
    const raw  = Math.round(((e.clientY - rect.top) / (HOUR_H / 60)) / SNAP) * SNAP + START_HOUR * 60;
    const sMin = Math.max(START_HOUR * 60, Math.min(END_HOUR * 60, raw));
    if (isInUnavailable(col, sMin)) { showUnavailableMsg(col); return; }
    e.preventDefault();
    roomSelecting.value = { roomId: col.id, col, startMin: sMin, currentMin: sMin };
}

function roomSelStyle(roomId) {
    if (!roomSelecting.value || roomSelecting.value.roomId !== roomId) return null;
    const { startMin, currentMin } = roomSelecting.value;
    const top    = (Math.min(startMin, currentMin) - START_HOUR * 60) * (HOUR_H / 60);
    const height = Math.abs(currentMin - startMin) * (HOUR_H / 60);
    return height > 2 ? { top: `${top}px`, height: `${height}px` } : null;
}

// ── 会議室予約ドラッグ/リサイズ ──────────────────────────────
const roomDragging = ref(null); // { res, type, startMin, endMin, offsetMin, roomId }

function canEditRoomRes(res) {
    const u = authUser?.value ?? authUser;
    if (!u) return false;
    if (u.user_role === 'superadmin' || u.user_role === 'admin') return true;
    return String(res.user_id) === String(u.id);
}

function onReservationMousedown(res, type, e) {
    if (e.button !== 0 || !canEditRoomRes(res)) return;
    e.preventDefault();
    e.stopPropagation();
    const el       = roomGridRefs.value[res.meeting_room_id];
    const startMin = localMin(res.starts_at);
    const endMin   = localMin(res.ends_at);
    roomDragging.value = {
        res: { ...res }, type,
        startMin, endMin,
        offsetMin: type === 'move' ? yToMin(e.clientY, el) - startMin : 0,
        roomId: res.meeting_room_id,
    };
}

function roomDragPreviewStyle(res) {
    if (!roomDragging.value || roomDragging.value.res.id !== res.id) return null;
    const { startMin, endMin } = roomDragging.value;
    const roomColor = props.rooms.find(rm => String(rm.id) === String(res.meeting_room_id))?.color ?? '#9ca3af';
    return {
        top:         `${(startMin - START_HOUR * 60) * (HOUR_H / 60)}px`,
        height:      `${Math.max(18, (endMin - startMin) * (HOUR_H / 60))}px`,
        background:  roomColor,
        color:       '#fff',
        borderColor: roomColor,
        opacity:     '0.9',
    };
}

// ── 共通 mousemove / mouseup ──────────────────────────────────
function onMousemove(e) {
    if (roomDragging.value) {
        const d   = roomDragging.value;
        const el  = roomGridRefs.value[d.roomId];
        const min = yToMin(e.clientY, el);
        const dur = d.endMin - d.startMin;
        if (d.type === 'move') {
            const start = Math.max(START_HOUR * 60, Math.min(END_HOUR * 60 - dur, min - d.offsetMin));
            roomDragging.value = { ...d, startMin: start, endMin: start + dur };
        } else if (d.type === 'resize-top') {
            roomDragging.value = { ...d, startMin: Math.min(min, d.endMin - SNAP) };
        } else {
            roomDragging.value = { ...d, endMin: Math.max(min, d.startMin + SNAP) };
        }
        return;
    }
    if (roomSelecting.value) {
        const el  = roomGridRefs.value[roomSelecting.value.roomId];
        const min = yToMin(e.clientY, el);
        roomSelecting.value = { ...roomSelecting.value, currentMin: min };
        return;
    }
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
    if (roomDragging.value) {
        const { startMin, endMin, res } = roomDragging.value;
        const pad  = (n) => String(n).padStart(2, '0');
        const toHm = (m) => `${pad(Math.floor(m / 60))}:${pad(m % 60)}`;
        emit('room-update', {
            id:        res.id,
            starts_at: `${props.date} ${toHm(startMin)}:00`,
            ends_at:   `${props.date} ${toHm(endMin)}:00`,
        });
        roomDragging.value = null;
        return;
    }
    if (roomSelecting.value) {
        const { startMin, currentMin, roomId, col } = roomSelecting.value;
        const sMin = Math.min(startMin, currentMin);
        const eMin = Math.max(startMin, currentMin);
        if (eMin - sMin >= SNAP) {
            emit('room-create', { date: props.date, startMin: sMin, endMin: eMin, roomId });
        } else {
            // ドラッグ距離が小さい場合はクリック扱い（1h デフォルト）
            if (isInUnavailable(col, sMin)) {
                showUnavailableMsg(col);
            } else {
                emit('room-create', { date: props.date, startMin: sMin, endMin: Math.min(END_HOUR * 60, sMin + 60), roomId });
            }
        }
        roomSelecting.value = null;
        return;
    }
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

onMounted(async () => {
    window.addEventListener('mousemove', onMousemove);
    window.addEventListener('mouseup',   onMouseup);

    // レイアウト確定後に現在時刻付近へスクロール
    await nextTick();
    requestAnimationFrame(() => {
        const container = scrollEl?.value;
        if (!container) return;

        const now = new Date();
        const ymd = d => `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
        const targetMin = (props.date === ymd(now))
            ? now.getHours() * 60 + now.getMinutes()
            : 8 * 60;

        // CalendarShell p-3(12px) + DayView ヘッダー(HEADER_H)
        const gridTop = 12 + HEADER_H;
        container.scrollTop = Math.max(0, gridTop + (targetMin - START_HOUR * 60) * (HOUR_H / 60) - 160);
    });
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
    <!-- 多カラム時の縦横スクロールは CalendarShell が担当 -->
    <div class="select-none overflow-clip rounded-lg border border-gray-200 bg-white"
        style="min-height: 520px">

        <div class="flex" :style="{ minWidth: `${TIME_W + columns.reduce((s, c) => s + (isClosed(c) ? COL_W_CLOSED : COL_W), 0)}px` }">

            <!-- ── 時刻列（sticky left）─────────────────────────── -->
            <div class="sticky left-0 z-30 shrink-0 border-r border-gray-200 bg-gray-50"
                :style="{ width: TIME_W + 'px' }">

                <!-- ヘッダー空白 -->
                <div :style="{ height: HEADER_H + 'px' }"
                    class="sticky top-0 z-30 flex flex-col items-end justify-end border-b border-gray-200 bg-gray-50 pb-1 pr-1 gap-0.5">
                    <span class="text-[9px] text-gray-400">
                        {{ new Date(date + 'T00:00:00').toLocaleDateString('ja-JP', { month: 'numeric', day: 'numeric' }) }}
                    </span>
                    <span v-if="todayWorktype" class="text-[9px] font-medium text-indigo-600 leading-none">
                        {{ todayWorktype.name }}
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
                class="flex shrink-0 flex-col border-r border-gray-200 last:border-r-0 transition-all duration-150"
                :class="{ 'opacity-70 ring-2 ring-inset ring-blue-300': dragOverKey === `${col.type}-${col.id}` }"
                :style="{ width: isClosed(col) ? COL_W_CLOSED + 'px' : COL_W + 'px' }"
                :draggable="col.type !== 'own'"
                @dragstart="onColDragStart(col, $event)"
                @dragover="onColDragOver(col, $event)"
                @drop="onColDrop(col)"
                @dragend="onColDragEnd">

                <!-- カラムヘッダー -->
                <div :style="{ height: HEADER_H + 'px', borderBottom: `3px solid ${col.color}` }"
                    class="group sticky top-0 z-20 flex shrink-0 items-center justify-between gap-1 px-1.5 bg-white"
                    :title="isClosed(col) ? col.label : undefined">

                    <template v-if="!isClosed(col)">
                        <!-- ドラッグハンドル（own以外） -->
                        <span v-if="col.type !== 'own'"
                            class="cursor-grab text-gray-300 hover:text-gray-500 text-xs select-none"
                            title="ドラッグで順序変更">⠿</span>
                        <div class="flex min-w-0 flex-col items-center flex-1">
                            <span v-if="col.sublabel" class="text-[9px] text-gray-400">{{ col.sublabel }}</span>
                            <span class="max-w-full truncate text-xs font-semibold text-gray-700">{{ col.label }}</span>
                        </div>
                    </template>
                    <template v-else>
                        <!-- 閉じた状態: 縦書きラベル -->
                        <div class="flex flex-col items-center w-full gap-0.5 py-1">
                            <span v-if="col.sublabel" class="text-[8px] text-gray-400 leading-none">{{ col.sublabel.slice(0,2) }}</span>
                            <span class="text-[9px] font-semibold text-gray-600 leading-none"
                                style="writing-mode: vertical-rl; white-space: nowrap; max-height: 32px; overflow: hidden;">
                                {{ col.label }}
                            </span>
                        </div>
                    </template>

                    <!-- 開閉ボタン -->
                    <button
                        class="shrink-0 rounded p-0.5 text-[10px] text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                        :title="isClosed(col) ? '展開' : '折りたたむ'"
                        @click.stop="toggleCollapse(col)">
                        {{ isClosed(col) ? '▶' : '◀' }}
                    </button>
                </div>

                <!-- グリッド（閉じた状態ではスキップ） -->
                <template v-if="!isClosed(col)">
                    <div
                        :ref="col.type === 'own' ? setOwnRef : undefined"
                        :style="{ height: TOTAL_H + 'px' }"
                        class="relative"
                        :class="[
                            col.type === 'own'  ? 'cursor-crosshair' : '',
                            col.type === 'room' ? 'cursor-crosshair' : '',
                            col.type === 'user' ? 'cursor-default'   : '',
                            col.type === 'own' && isToday() ? 'bg-blue-50/30' : '',
                        ]"
                        @mousedown="col.type === 'own'  ? onGridMousedown($event)
                                  : col.type === 'room' ? onRoomMousedown($event, col)
                                  : undefined">

                        <!-- 時間グリッド線 -->
                        <template v-for="h in hours" :key="h">
                            <div class="pointer-events-none absolute inset-x-0 border-t border-gray-100"
                                :style="{ top: `${(h - START_HOUR) * HOUR_H}px` }" />
                            <div class="pointer-events-none absolute inset-x-0 border-t border-gray-50"
                                :style="{ top: `${(h - START_HOUR) * HOUR_H + HOUR_H / 2}px` }" />
                        </template>

                        <!-- 現在時刻ライン（全カラムに横断） -->
                        <div v-if="isToday() && nowTop() !== null"
                            class="pointer-events-none absolute inset-x-0 z-10 border-t-2 border-red-400"
                            :style="{ top: nowTop() }">
                            <div v-if="ci === 0"
                                class="absolute -left-1 -top-1.5 h-3 w-3 rounded-full bg-red-400" />
                        </div>

                        <!-- 選択プレビュー（自分カラム） -->
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

                            <div v-if="ev.is_own"
                                class="absolute inset-x-0 top-0 h-2 cursor-n-resize"
                                @mousedown.stop="onEventMousedown(ev, 'resize-top', $event)" />
                            <div class="pointer-events-none line-clamp-2 font-semibold leading-tight">{{ ev.title }}</div>
                            <div class="pointer-events-none text-[10px] opacity-80">
                                {{ fmtTime(ev.starts_at) }}–{{ fmtTime(ev.ends_at) }}
                            </div>
                            <div v-if="ev.is_own"
                                class="absolute inset-x-0 bottom-0 h-2 cursor-s-resize"
                                @mousedown.stop="onEventMousedown(ev, 'resize-bot', $event)" />
                        </div>

                        <!-- 会議室カラム -->
                        <template v-if="col.type === 'room'">
                            <!-- 背景色 -->
                            <div class="pointer-events-none absolute inset-0"
                                :style="{ background: col.color ? `${col.color}08` : 'transparent' }" />

                            <!-- 予約不可時間帯（グレー） -->
                            <div v-for="(block, bi) in unavailableStyle(col)" :key="bi"
                                class="pointer-events-none absolute inset-x-0 bg-gray-200/60"
                                :style="{ top: block.top + 'px', height: block.height + 'px' }" />

                            <!-- 会議室グリッド ref（ドラッグ選択用） -->
                            <div class="absolute inset-0 cursor-crosshair"
                                :ref="el => setRoomRef(col.id, el)" />

                            <!-- 選択プレビュー（会議室カラム） -->
                            <div v-if="roomSelStyle(col.id)"
                                class="pointer-events-none absolute inset-x-0.5 rounded border border-indigo-400 bg-indigo-300/40 z-10"
                                :style="roomSelStyle(col.id)" />

                            <!-- 予約ブロック -->
                            <div v-for="res in reservationsForRoom(col.id)" :key="res.id"
                                class="absolute inset-x-0.5 z-20 overflow-hidden rounded border px-1 pt-0.5 text-xs"
                                :class="canEditRoomRes(res) ? 'cursor-grab' : 'cursor-pointer'"
                                :style="[resStyle(res), {
                                    opacity: (roomDragging?.res?.id === res.id && roomDragging?.type === 'move') ? '0.3' : '0.85',
                                }]"
                                @mousedown.stop="canEditRoomRes(res) ? onReservationMousedown(res, 'move', $event) : null"
                                @click.stop="$emit('room-click', res)">
                                <div v-if="canEditRoomRes(res)"
                                    class="absolute inset-x-0 top-0 h-2 cursor-n-resize"
                                    @mousedown.stop="onReservationMousedown(res, 'resize-top', $event)" />
                                <div class="pointer-events-none line-clamp-2 font-semibold leading-tight">{{ res.title }}</div>
                                <div class="pointer-events-none text-[10px] opacity-90">
                                    {{ fmtTime(res.starts_at) }}–{{ fmtTime(res.ends_at) }}
                                </div>
                                <div v-if="res.user?.name" class="pointer-events-none text-[10px] opacity-75 truncate">
                                    {{ res.user.name }}
                                </div>
                                <div v-if="canEditRoomRes(res)"
                                    class="absolute inset-x-0 bottom-0 h-2 cursor-s-resize"
                                    @mousedown.stop="onReservationMousedown(res, 'resize-bot', $event)" />
                            </div>

                            <!-- 予約ドラッグプレビュー -->
                            <template v-for="res in reservationsForRoom(col.id)" :key="`dp-${res.id}`">
                                <div v-if="roomDragPreviewStyle(res)"
                                    class="pointer-events-none absolute inset-x-0.5 z-30 overflow-hidden rounded border px-1 pt-0.5 text-xs shadow-lg ring-2 ring-white"
                                    :style="roomDragPreviewStyle(res)">
                                    <div class="font-semibold leading-tight truncate">{{ res.title }}</div>
                                    <div class="text-[10px] opacity-90">
                                        {{ String(Math.floor(roomDragging.startMin/60)).padStart(2,'0') }}:{{ String(roomDragging.startMin%60).padStart(2,'0') }}–{{ String(Math.floor(roomDragging.endMin/60)).padStart(2,'0') }}:{{ String(roomDragging.endMin%60).padStart(2,'0') }}
                                    </div>
                                </div>
                            </template>
                        </template>

                        <!-- ドラッグプレビュー（自分カラム） -->
                        <div v-if="col.type === 'own' && dragStyle()"
                            class="pointer-events-none absolute inset-x-0.5 overflow-hidden rounded border px-1 pt-0.5 text-xs opacity-90 shadow-lg ring-2 ring-white"
                            :style="dragStyle()">
                            <div class="font-semibold leading-tight">{{ dragging.ev.title }}</div>
                        </div>
                    </div>
                </template>

                <!-- 折りたたみ時のグリッド代替（高さ確保） -->
                <div v-else
                    :style="{ height: TOTAL_H + 'px' }"
                    class="bg-gray-50/50" />
            </div>
        </div>
    </div>
</template>
