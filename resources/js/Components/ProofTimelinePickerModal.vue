<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';

const props = defineProps({
    show:           { type: Boolean,          default: false },
    initialUserId:  { type: [Number, String], default: null },
    existingEvents: { type: Array,            default: () => [] },
});

const emit = defineEmits(['close', 'confirmed', 'user-selected']);

// ─────────────────────────────────────────────────────────────────
//  定数
// ─────────────────────────────────────────────────────────────────
const START_HOUR = 8;
const END_HOUR   = 20;
const TOTAL_MINS = (END_HOUR - START_HOUR) * 60;
const MEMBER_W   = 120;
const ROW_H      = 48;
const HEADER_H   = 36;
const SNAP       = 15;

// ─────────────────────────────────────────────────────────────────
//  State
// ─────────────────────────────────────────────────────────────────
const currentDate   = ref(new Date().toISOString().slice(0, 10));
const members       = ref([]);
const schedules     = ref([]);
const contextEvents = ref([]);
const pendingSlots  = ref([]); // { id, date, userId, startMin, endMin }
const editableSlots = ref([]); // 既存イベント（移動・リサイズ可）
const loading       = ref(false);

const timelineAreaRef = ref(null);
const timelineW       = ref(800);
const selecting       = ref(null); // { memberId, startMin, endMin }
const dragging        = ref(null); // { slotId, offset, duration }
const resizing        = ref(null); // { slotId }

let resizeObserver = null;

// ─────────────────────────────────────────────────────────────────
//  Watch: モーダルが開いたらデータ読み込み + ResizeObserver セットアップ
// ─────────────────────────────────────────────────────────────────
watch(() => props.show, (val) => {
    if (val) {
        pendingSlots.value = [];

        // existingEvents → editableSlots に変換
        editableSlots.value = (props.existingEvents ?? []).map(ev => {
            const rawStart = (parseInt(ev.start_hour) - START_HOUR) * 60 + parseInt(ev.start_minute);
            const rawEnd   = (parseInt(ev.end_hour)   - START_HOUR) * 60 + parseInt(ev.end_minute);
            return {
                id:       ev.id,
                date:     ev.date,
                userId:   ev.user_id,
                startMin: Math.max(0, Math.min(TOTAL_MINS, rawStart)),
                endMin:   Math.max(0, Math.min(TOTAL_MINS, rawEnd)),
            };
        });

        // 最初の登録済み日付にジャンプ、なければ今日
        if (editableSlots.value.length > 0) {
            const sorted = [...editableSlots.value.map(s => s.date)].sort();
            currentDate.value = sorted[0];
        } else {
            currentDate.value = new Date().toISOString().slice(0, 10);
        }

        loadData();
        nextTick(setupResizeObserver);
    }
});

// ─────────────────────────────────────────────────────────────────
//  Computed
// ─────────────────────────────────────────────────────────────────
const hours = computed(() =>
    Array.from({ length: END_HOUR - START_HOUR + 1 }, (_, i) => START_HOUR + i)
);
const hoursForLines  = computed(() => hours.value.filter(h => h > START_HOUR));
const hoursFor30Mins = computed(() => hours.value.filter(h => h < END_HOUR));

const displayDate = computed(() => {
    const d = new Date(currentDate.value + 'T00:00:00');
    const days = ['日', '月', '火', '水', '木', '金', '土'];
    return `${d.getFullYear()}年${d.getMonth() + 1}月${d.getDate()}日（${days[d.getDay()]}）`;
});

// ─────────────────────────────────────────────────────────────────
//  日付ナビゲーション
// ─────────────────────────────────────────────────────────────────
function addDays(dateStr, days) {
    const [y, m, d] = dateStr.split('-').map(Number);
    const dt = new Date(y, m - 1, d + days);
    return `${dt.getFullYear()}-${String(dt.getMonth() + 1).padStart(2, '0')}-${String(dt.getDate()).padStart(2, '0')}`;
}

function prevDay()       { currentDate.value = addDays(currentDate.value, -1); loadData(); }
function nextDay()       { currentDate.value = addDays(currentDate.value,  1); loadData(); }
function onDateChange(e) { if (e.target.value) { currentDate.value = e.target.value; loadData(); } }

// ─────────────────────────────────────────────────────────────────
//  データ読み込み
// ─────────────────────────────────────────────────────────────────
async function loadData() {
    loading.value = true;
    try {
        const params = new URLSearchParams({ date: currentDate.value });
        if (props.initialUserId) params.set('user_id', String(props.initialUserId));
        const url = route('proof_coordinator.calendar.picker_data') + '?' + params.toString();
        const res = await fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        });
        if (!res.ok) throw new Error(await res.text());
        const data = await res.json();
        members.value       = data.members;
        schedules.value     = data.schedules;
        contextEvents.value = data.events;
    } catch (e) {
        console.error('picker-data load error', e);
    } finally {
        loading.value = false;
    }
}

// ─────────────────────────────────────────────────────────────────
//  日付変換ユーティリティ
// ─────────────────────────────────────────────────────────────────
function isoToMinutes(isoStr) {
    const d = new Date(isoStr);
    const jstTotalMin = d.getUTCHours() * 60 + d.getUTCMinutes() + 9 * 60;
    return jstTotalMin - START_HOUR * 60;
}

function minsToTimeStr(mins) {
    const total = START_HOUR * 60 + mins;
    return `${String(Math.floor(total / 60)).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`;
}

function snapMins(mins)  { return Math.round(mins / SNAP) * SNAP; }
function clampMins(mins) { return Math.max(0, Math.min(TOTAL_MINS, mins)); }

// ─────────────────────────────────────────────────────────────────
//  マウスX → 分変換
// ─────────────────────────────────────────────────────────────────
function clientXToMins(clientX) {
    if (!timelineAreaRef.value) return 0;
    const rect = timelineAreaRef.value.getBoundingClientRect();
    return clampMins(snapMins((clientX - rect.left) / timelineW.value * TOTAL_MINS));
}

// ─────────────────────────────────────────────────────────────────
//  既存イベント：移動開始
// ─────────────────────────────────────────────────────────────────
function onEditableSlotMouseDown(e, slot) {
    if (e.button !== 0) return;
    e.stopPropagation();
    const clickMins = clientXToMins(e.clientX);
    dragging.value = {
        slotId:   slot.id,
        offset:   clickMins - slot.startMin,
        duration: slot.endMin - slot.startMin,
    };
}

// ─────────────────────────────────────────────────────────────────
//  既存イベント：リサイズ開始（右端ハンドル）
// ─────────────────────────────────────────────────────────────────
function onResizeHandleMouseDown(e, slot) {
    if (e.button !== 0) return;
    e.stopPropagation();
    resizing.value = { slotId: slot.id };
}

// ─────────────────────────────────────────────────────────────────
//  ドラッグ（新規選択）
// ─────────────────────────────────────────────────────────────────
function onTimelineMouseDown(e, member) {
    if (e.button !== 0) return;
    const mins = clientXToMins(e.clientX);
    selecting.value = { memberId: member.id, startMin: mins, endMin: mins };
}

// ─────────────────────────────────────────────────────────────────
//  グローバルマウスイベント
// ─────────────────────────────────────────────────────────────────
function onMouseMove(e) {
    if (dragging.value) {
        const slot = editableSlots.value.find(s => s.id === dragging.value.slotId);
        if (slot) {
            const rawStart = snapMins(clientXToMins(e.clientX) - dragging.value.offset);
            const maxStart = TOTAL_MINS - dragging.value.duration;
            slot.startMin  = Math.max(0, Math.min(maxStart, rawStart));
            slot.endMin    = slot.startMin + dragging.value.duration;
        }
        return;
    }
    if (resizing.value) {
        const slot = editableSlots.value.find(s => s.id === resizing.value.slotId);
        if (slot) {
            const newEnd = clampMins(snapMins(clientXToMins(e.clientX)));
            slot.endMin  = Math.max(slot.startMin + SNAP, newEnd);
        }
        return;
    }
    if (!selecting.value) return;
    selecting.value.endMin = clientXToMins(e.clientX);
}

function onMouseUp() {
    if (dragging.value) {
        dragging.value = null;
        return;
    }
    if (resizing.value) {
        resizing.value = null;
        return;
    }
    if (!selecting.value) return;
    const sel = selecting.value;
    selecting.value = null;

    const s  = Math.min(sel.startMin, sel.endMin);
    const en = Math.max(sel.startMin, sel.endMin);
    if (en - s < SNAP) return;

    pendingSlots.value.push({
        id:       Date.now(),
        date:     currentDate.value,
        userId:   sel.memberId,
        startMin: s,
        endMin:   en,
    });

    emit('user-selected', sel.memberId);
}

// ─────────────────────────────────────────────────────────────────
//  スロット削除
// ─────────────────────────────────────────────────────────────────
function removeSlot(id) {
    pendingSlots.value = pendingSlots.value.filter(s => s.id !== id);
}

// ─────────────────────────────────────────────────────────────────
//  確定
// ─────────────────────────────────────────────────────────────────
function confirm() {
    const newSlots = pendingSlots.value.map(slot => {
        const startTotal = START_HOUR * 60 + slot.startMin;
        const endTotal   = START_HOUR * 60 + slot.endMin;
        return {
            date:        slot.date,
            startHour:   Math.floor(startTotal / 60),
            startMinute: startTotal % 60,
            endHour:     Math.floor(endTotal / 60),
            endMinute:   endTotal % 60,
            userId:      slot.userId,
        };
    });

    const updatedSlots = editableSlots.value.map(slot => {
        const startTotal = START_HOUR * 60 + slot.startMin;
        const endTotal   = START_HOUR * 60 + slot.endMin;
        return {
            id:          slot.id,
            date:        slot.date,
            startHour:   Math.floor(startTotal / 60),
            startMinute: startTotal % 60,
            endHour:     Math.floor(endTotal / 60),
            endMinute:   endTotal % 60,
            userId:      slot.userId,
        };
    });

    emit('confirmed', { newSlots, updatedSlots });
}

// ─────────────────────────────────────────────────────────────────
//  スタイル計算
// ─────────────────────────────────────────────────────────────────
function blockStyle(startMin, endMin) {
    const s = Math.max(0, startMin);
    const e = Math.min(TOTAL_MINS, endMin);
    return {
        left:   (s / TOTAL_MINS * 100) + '%',
        width:  (Math.max(0, e - s) / TOTAL_MINS * 100) + '%',
        top:    '4px',
        height: (ROW_H - 8) + 'px',
    };
}

function selectionStyle(sel) {
    if (!sel) return {};
    return blockStyle(Math.min(sel.startMin, sel.endMin), Math.max(sel.startMin, sel.endMin));
}

// ─────────────────────────────────────────────────────────────────
//  フィルタヘルパー
// ─────────────────────────────────────────────────────────────────
function schedulesForMember(userId) {
    return schedules.value.filter(s => s.user_id === userId);
}

function eventsForMember(userId) {
    return contextEvents.value.filter(e => e.user_id === userId);
}

function pendingSlotsForMember(userId) {
    return pendingSlots.value.filter(s => s.date === currentDate.value && s.userId === userId);
}

function editableSlotsForMember(userId) {
    return editableSlots.value.filter(s => s.date === currentDate.value && s.userId === userId);
}

function memberName(userId) {
    return members.value.find(m => m.id === userId)?.name ?? String(userId);
}

const STATUS_COLORS = {
    pending:     'bg-gray-200 border-gray-400',
    assigned:    'bg-blue-200 border-blue-400',
    in_progress: 'bg-pink-200 border-pink-400',
    completed:   'bg-white border-green-400',
};

// ─────────────────────────────────────────────────────────────────
//  ResizeObserver
// ─────────────────────────────────────────────────────────────────
function setupResizeObserver() {
    if (!timelineAreaRef.value) return;
    resizeObserver?.disconnect();
    resizeObserver = new ResizeObserver(entries => {
        for (const entry of entries) {
            timelineW.value = entry.contentRect.width;
        }
    });
    resizeObserver.observe(timelineAreaRef.value);
    timelineW.value = timelineAreaRef.value.clientWidth;
}

// ─────────────────────────────────────────────────────────────────
//  ライフサイクル
// ─────────────────────────────────────────────────────────────────
onMounted(() => {
    window.addEventListener('mousemove', onMouseMove);
    window.addEventListener('mouseup',  onMouseUp);
    if (props.show) nextTick(setupResizeObserver);
});

onUnmounted(() => {
    window.removeEventListener('mousemove', onMouseMove);
    window.removeEventListener('mouseup',  onMouseUp);
    resizeObserver?.disconnect();
});
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-50 flex flex-col bg-white" style="user-select: none;">

            <!-- ヘッダー -->
            <div class="flex flex-wrap items-center gap-3 border-b border-gray-200 bg-white px-4 py-3 shadow-sm">
                <h3 class="text-base font-semibold text-gray-800">作業時間をカレンダーで選択</h3>

                <!-- 日付ナビ -->
                <div class="flex items-center gap-2">
                    <button @click="prevDay"
                        class="rounded border border-gray-300 px-3 py-1 text-sm hover:bg-gray-50">
                        ◀ 前日
                    </button>
                    <span class="min-w-[180px] text-center text-sm font-semibold text-gray-800">
                        {{ displayDate }}
                    </span>
                    <button @click="nextDay"
                        class="rounded border border-gray-300 px-3 py-1 text-sm hover:bg-gray-50">
                        翌日 ▶
                    </button>
                    <input
                        type="date"
                        :value="currentDate"
                        @change="onDateChange"
                        class="rounded border-gray-300 text-sm"
                    />
                </div>

                <span v-if="loading" class="text-xs text-gray-400">読み込み中...</span>

                <!-- アクションボタン -->
                <div class="ml-auto flex gap-2">
                    <button
                        @click="emit('close')"
                        class="rounded border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                        キャンセル
                    </button>
                    <button
                        @click="confirm"
                        :disabled="pendingSlots.length === 0 && editableSlots.length === 0"
                        class="rounded bg-pink-600 px-4 py-2 text-sm font-medium text-white hover:bg-pink-700 disabled:cursor-not-allowed disabled:opacity-50">
                        確定
                        <span v-if="pendingSlots.length > 0">（新規 {{ pendingSlots.length }}件）</span>
                    </button>
                </div>
            </div>

            <!-- 凡例 -->
            <div class="flex flex-wrap items-center gap-4 border-b bg-gray-50 px-4 py-2 text-xs text-gray-600">
                <span class="flex items-center gap-1">
                    <span class="inline-block h-3 w-5 rounded opacity-70" style="background:#1fb6b3;"></span>予定
                </span>
                <span class="flex items-center gap-1">
                    <span class="inline-block h-3 w-5 rounded opacity-70" style="background:#4F46E5;"></span>独自ジョブ
                </span>
                <span class="flex items-center gap-1">
                    <span class="inline-block h-3 w-5 rounded opacity-70" style="background:#7C3AED;"></span>進行表ジョブ
                </span>
                <span class="flex items-center gap-1">
                    <span class="inline-block h-3 w-5 rounded opacity-70" style="background:#059669;"></span>Coordinator割当
                </span>
                <span class="flex items-center gap-1">
                    <span class="inline-block h-3 w-5 rounded border-2 border-yellow-500 bg-yellow-200"></span>選択中 / 確定済み
                </span>
                <span class="flex items-center gap-1">
                    <span class="inline-block h-3 w-5 rounded border-2 border-rose-500 bg-rose-200"></span>登録済み作業時間（ドラッグ移動・右端リサイズ可）
                </span>
                <span class="ml-auto text-xs text-gray-400">タイムラインをドラッグして時間を選択</span>
            </div>

            <!-- タイムライン -->
            <div class="flex-1 overflow-auto">
                <div class="timeline-wrapper" :style="{ minWidth: (MEMBER_W + 700) + 'px' }">

                    <!-- 時刻ヘッダー -->
                    <div class="sticky top-0 z-10 flex border-b border-gray-200 bg-gray-50"
                         :style="{ height: HEADER_H + 'px' }">
                        <div class="flex-shrink-0 border-r border-gray-200"
                             :style="{ width: MEMBER_W + 'px' }"></div>
                        <div class="relative flex-1" ref="timelineAreaRef">
                            <div v-for="h in hours" :key="h"
                                 class="absolute top-0 flex h-full items-center"
                                 :style="{ left: ((h - START_HOUR) * 60 / TOTAL_MINS * 100) + '%' }">
                                <span class="whitespace-nowrap pl-1 text-xs text-gray-500">{{ h }}:00</span>
                            </div>
                        </div>
                    </div>

                    <!-- メンバー行 -->
                    <div v-for="(member, idx) in members" :key="member.id"
                         class="member-row flex border-b border-gray-100"
                         :style="{ height: ROW_H + 'px' }"
                         :class="idx % 2 === 0 ? 'bg-white' : 'bg-gray-50/50'">

                        <!-- 名前（sticky） -->
                        <div class="sticky left-0 z-10 flex flex-shrink-0 items-center border-r border-gray-200 px-3"
                             :style="{ width: MEMBER_W + 'px' }"
                             :class="idx % 2 === 0 ? 'bg-white' : 'bg-gray-50'">
                            <span class="truncate text-sm font-medium text-gray-700">{{ member.name }}</span>
                        </div>

                        <!-- タイムライン -->
                        <div class="relative flex-1 cursor-crosshair"
                             @mousedown="onTimelineMouseDown($event, member)">

                            <!-- グリッド縦線（整時） -->
                            <div v-for="h in hoursForLines" :key="h"
                                 class="pointer-events-none absolute top-0 h-full w-px bg-gray-100"
                                 :style="{ left: ((h - START_HOUR) * 60 / TOTAL_MINS * 100) + '%' }"></div>
                            <!-- グリッド縦線（30分） -->
                            <div v-for="h in hoursFor30Mins" :key="'h30-' + h"
                                 class="pointer-events-none absolute top-0 h-full w-px bg-gray-100/60"
                                 :style="{ left: (((h - START_HOUR) * 60 + 30) / TOTAL_MINS * 100) + '%' }"></div>

                            <!-- コンテキストイベント（色のみ、背後に表示） -->
                            <div v-for="ev in eventsForMember(member.id)" :key="'e-' + ev.id"
                                 class="pointer-events-none absolute z-[5] rounded opacity-50"
                                 :style="{ ...blockStyle(isoToMinutes(ev.starts_at), isoToMinutes(ev.ends_at)), backgroundColor: ev.color }">
                            </div>

                            <!-- 既存スケジュール（読み取り専用） -->
                            <div v-for="s in schedulesForMember(member.id)" :key="'s-' + s.id"
                                 class="pointer-events-none absolute z-[10] flex items-center overflow-hidden rounded border px-1 text-xs font-medium"
                                 :class="STATUS_COLORS[s.status] ?? STATUS_COLORS.pending"
                                 :style="blockStyle(isoToMinutes(s.starts_at), isoToMinutes(s.ends_at))">
                                <span class="truncate">{{ s.title }}</span>
                            </div>

                            <!-- 登録済み作業時間（移動・リサイズ可） -->
                            <div v-for="slot in editableSlotsForMember(member.id)" :key="'es-' + slot.id"
                                 class="absolute z-[15] flex items-center overflow-hidden rounded border-2 border-rose-500 bg-rose-100 cursor-move select-none"
                                 :class="dragging?.slotId === slot.id || resizing?.slotId === slot.id ? 'opacity-70' : 'opacity-100'"
                                 :style="blockStyle(slot.startMin, slot.endMin)"
                                 @mousedown="onEditableSlotMouseDown($event, slot)">
                                <span class="flex-1 truncate px-1 text-xs font-semibold text-rose-800">
                                    登録済み {{ minsToTimeStr(slot.startMin) }}–{{ minsToTimeStr(slot.endMin) }}
                                </span>
                                <!-- リサイズハンドル（右端） -->
                                <div
                                    class="absolute right-0 top-0 h-full w-2 cursor-col-resize bg-rose-400/60 hover:bg-rose-500"
                                    @mousedown.stop="onResizeHandleMouseDown($event, slot)"
                                ></div>
                            </div>

                            <!-- 確定済み pending slots -->
                            <div v-for="slot in pendingSlotsForMember(member.id)" :key="'p-' + slot.id"
                                 class="pointer-events-none absolute z-[20] flex items-center overflow-hidden rounded border-2 border-yellow-500 bg-yellow-200 opacity-90"
                                 :style="blockStyle(slot.startMin, slot.endMin)">
                                <span class="px-1 text-xs font-semibold text-yellow-900">
                                    {{ minsToTimeStr(slot.startMin) }}–{{ minsToTimeStr(slot.endMin) }}
                                </span>
                            </div>

                            <!-- 選択中プレビュー -->
                            <div v-if="selecting && selecting.memberId === member.id"
                                 class="pointer-events-none absolute z-[30] rounded border-2 border-yellow-500 bg-yellow-100 opacity-80"
                                 :style="selectionStyle(selecting)">
                                <span class="px-1 text-xs text-yellow-800">
                                    {{ minsToTimeStr(Math.min(selecting.startMin, selecting.endMin)) }}–
                                    {{ minsToTimeStr(Math.max(selecting.startMin, selecting.endMin)) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- メンバーなし -->
                    <div v-if="members.length === 0 && !loading"
                         class="flex items-center justify-center py-16 text-gray-400">
                        メンバーが見つかりません。
                    </div>

                </div>
            </div>

            <!-- 選択済みスロット一覧 -->
            <div v-if="pendingSlots.length > 0 || editableSlots.length > 0"
                 class="border-t border-gray-200 bg-gray-50 px-4 py-3">

                <!-- 登録済み（編集中） -->
                <div v-if="editableSlots.length > 0" class="mb-2">
                    <p class="mb-1 text-xs font-medium text-rose-700">
                        登録済み作業時間（{{ editableSlots.length }}件・確定で保存）
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <div v-for="slot in editableSlots" :key="'es-tag-' + slot.id"
                             class="flex items-center gap-1 rounded-full border border-rose-400 bg-rose-50 px-3 py-1 text-xs text-rose-800">
                            <span>{{ slot.date }}&nbsp;{{ minsToTimeStr(slot.startMin) }}–{{ minsToTimeStr(slot.endMin) }}</span>
                            <span class="text-gray-500">（{{ memberName(slot.userId) }}）</span>
                        </div>
                    </div>
                </div>

                <!-- 新規スロット -->
                <div v-if="pendingSlots.length > 0">
                    <p class="mb-1 text-xs font-medium text-gray-600">
                        新規スロット（{{ pendingSlots.length }}件）
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <div v-for="slot in pendingSlots" :key="slot.id"
                             class="flex items-center gap-2 rounded-full bg-yellow-100 px-3 py-1 text-xs text-yellow-900">
                            <span>{{ slot.date }}&nbsp;{{ minsToTimeStr(slot.startMin) }}–{{ minsToTimeStr(slot.endMin) }}</span>
                            <span class="text-gray-500">（{{ memberName(slot.userId) }}）</span>
                            <button @click.stop="removeSlot(slot.id)"
                                    class="ml-1 text-red-400 hover:text-red-600">✕</button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </Teleport>
</template>
