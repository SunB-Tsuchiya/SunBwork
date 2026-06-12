<script setup>
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import MonthView              from './MonthView.vue';
import WeekView               from './WeekView.vue';
import DayView                from './DayView.vue';
import EventModal             from './EventModal.vue';
import EventDetailModal       from './EventDetailModal.vue';
import RoomReservationModal   from './RoomReservationModal.vue';
import OverlayPanel           from './OverlayPanel.vue';

const props = defineProps({
    initialDate:    { type: String, default: '' },
    eventItemTypes: { type: Array,  default: () => [] },
    initialOverlays: { type: Array, default: () => [] }, // 初期オーバーレイ一覧（full object）
    rooms:          { type: Array,  default: () => [] }, // [{id, name, color}]
    companies:      { type: Array,  default: () => [] },
    departments:    { type: Array,  default: () => [] },
});

const CSRF = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

// ── ビューモード・基準日 ──────────────────────────────────────
const viewMode    = ref('week');
const currentDate = ref(props.initialDate || new Date().toLocaleDateString('sv-SE'));

// ── 週の月曜日を計算 ──────────────────────────────────────────
const weekStart = computed(() => {
    const d   = new Date(currentDate.value + 'T00:00:00');
    const dow = d.getDay();                     // 0=日
    const mon = new Date(d);
    mon.setDate(d.getDate() - (dow === 0 ? 6 : dow - 1));  // 月曜に揃える
    return mon.toLocaleDateString('sv-SE');
});

const viewYear  = computed(() => new Date(currentDate.value + 'T00:00:00').getFullYear());
const viewMonth = computed(() => new Date(currentDate.value + 'T00:00:00').getMonth() + 1);

// ── ラベル ────────────────────────────────────────────────────
const viewLabel = computed(() => {
    const d = new Date(currentDate.value + 'T00:00:00');
    if (viewMode.value === 'month') {
        return `${d.getFullYear()}年${d.getMonth() + 1}月`;
    }
    if (viewMode.value === 'week') {
        const mon = new Date(weekStart.value + 'T00:00:00');
        const sun = new Date(mon); sun.setDate(mon.getDate() + 6);
        return `${mon.getFullYear()}年${mon.getMonth()+1}月${mon.getDate()}日 – ${sun.getMonth()+1}月${sun.getDate()}日`;
    }
    const DAYS_JA = ['日', '月', '火', '水', '木', '金', '土'];
    return `${d.getFullYear()}年${d.getMonth()+1}月${d.getDate()}日（${DAYS_JA[d.getDay()]}）`;
});

// ── ナビゲーション ────────────────────────────────────────────
function navigate(dir) {
    const d = new Date(currentDate.value + 'T00:00:00');
    if (viewMode.value === 'month') d.setMonth(d.getMonth() + dir);
    else if (viewMode.value === 'week') d.setDate(d.getDate() + dir * 7);
    else d.setDate(d.getDate() + dir);
    currentDate.value = d.toLocaleDateString('sv-SE');
}

function goToday() {
    currentDate.value = new Date().toLocaleDateString('sv-SE');
}

// ── オーバーレイ状態（reactive） ──────────────────────────────
const overlays = ref([...props.initialOverlays]);

const overlayUsers = computed(() =>
    overlays.value
        .filter(o => o.target_user_id)
        .map(o => ({ id: o.target_user_id, name: o.target_user?.name ?? '' }))
);

function onOverlayAdd(overlay)  { overlays.value.push(overlay); }
function onOverlayRemove(id)    { overlays.value = overlays.value.filter(o => o.id !== id); }

// ── イベントデータ ─────────────────────────────────────────────
const events       = ref([]);
const reservations = ref([]);
const loading      = ref(false);

const loadRange = computed(() => {
    const d = new Date(currentDate.value + 'T00:00:00');
    if (viewMode.value === 'month') {
        return {
            start: new Date(d.getFullYear(), d.getMonth(), 1).toLocaleDateString('sv-SE'),
            end:   new Date(d.getFullYear(), d.getMonth() + 1, 0).toLocaleDateString('sv-SE'),
        };
    }
    if (viewMode.value === 'week') {
        const mon = new Date(weekStart.value + 'T00:00:00');
        const sun = new Date(mon); sun.setDate(mon.getDate() + 6);
        return { start: weekStart.value, end: sun.toLocaleDateString('sv-SE') };
    }
    return { start: currentDate.value, end: currentDate.value };
});

async function loadEvents() {
    loading.value = true;
    try {
        const res = await axios.get(route('schedule.events.range'), {
            params: { start: loadRange.value.start, end: loadRange.value.end },
        });
        events.value       = res.data.events       ?? res.data;
        reservations.value = res.data.reservations ?? [];
    } catch (e) {
        console.error('予定取得失敗', e);
    } finally {
        loading.value = false;
    }
}

watch(loadRange, loadEvents, { immediate: true });
watch(overlays, loadEvents, { deep: true });

// ── モーダル制御 ───────────────────────────────────────────────
const showCreate    = ref(false);
const editTarget    = ref(null);
const showDetail    = ref(false);
const detailTarget  = ref(null);
const createDef     = ref({ date: '', startMin: null, endMin: null });

function openCreate({ date, startMin = null, endMin = null } = {}) {
    createDef.value  = { date: date || currentDate.value, startMin, endMin };
    editTarget.value = null;
    showCreate.value = true;
}

function openEdit(ev) {
    editTarget.value = ev;
    showDetail.value = false;
    showCreate.value = true;
}

function openDetail(ev) {
    detailTarget.value = ev;
    showDetail.value   = true;
}

// ── 保存・削除 ─────────────────────────────────────────────────
function onSaved()   { editTarget.value = null; loadEvents(); }
function onDeleted() { editTarget.value = null; loadEvents(); }

function onModalClose() {
    // 編集モードで閉じた場合は再取得（ライブモードの参加者変更を反映）
    if (editTarget.value) {
        loadEvents();
    }
    editTarget.value = null;
    showCreate.value = false;
}

// ── ドラッグ移動・リサイズ ────────────────────────────────────
async function onUpdate({ id, starts_at, ends_at }) {
    // 楽観的更新（UI即反映）
    const idx = events.value.findIndex(e => e.id === id);
    if (idx >= 0) {
        const orig = { ...events.value[idx] };
        events.value[idx] = { ...orig, starts_at, ends_at };
        try {
            await axios.put(
                route('schedule.events.update', { event: id }),
                { starts_at, ends_at },
                { headers: { 'X-CSRF-TOKEN': CSRF() } }
            );
            // 成功後は再取得で確定
            loadEvents();
        } catch (e) {
            // ロールバック
            events.value[idx] = orig;
            alert('更新に失敗しました');
        }
    }
}

// ── 月ビューのイベントクリック ────────────────────────────────
function onDateClick(date) {
    currentDate.value = date;
    viewMode.value    = 'day';
}

// ── 会議室予約モーダル ────────────────────────────────────────
const showRoomModal      = ref(false);
const roomModalTarget    = ref(null); // 既存予約 or null（新規）
const roomModalDefaults  = ref({ date: '', startMin: null, endMin: null });

function openRoomCreate({ date, startMin = null, endMin = null } = {}) {
    roomModalTarget.value   = null;
    roomModalDefaults.value = { date: date || currentDate.value, startMin, endMin };
    showRoomModal.value     = true;
}

function openRoomEdit(reservation) {
    roomModalTarget.value  = reservation;
    showRoomModal.value    = true;
}

function onRoomSaved()  { loadEvents(); }
function onRoomDeleted() { loadEvents(); }
</script>

<template>
    <div class="flex flex-col gap-3">
        <!-- ツールバー（既存カレンダーと同スタイル） -->
        <div class="flex flex-wrap items-center gap-2">
            <!-- prev / next / today -->
            <div class="flex items-center gap-1">
                <button
                    class="rounded border border-gray-300 bg-white px-2.5 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 active:bg-gray-100"
                    @click="navigate(-1)">‹</button>
                <button
                    class="rounded border border-gray-300 bg-white px-2.5 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 active:bg-gray-100"
                    @click="navigate(1)">›</button>
                <button
                    class="rounded border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
                    @click="goToday">today</button>
            </div>

            <!-- 期間ラベル -->
            <div class="flex-1 text-center text-base font-semibold text-gray-800">
                {{ viewLabel }}
            </div>

            <!-- month / week / day タブ（既存カレンダーのボタン群スタイル） -->
            <div class="flex overflow-hidden rounded border border-gray-300">
                <button v-for="m in ['month', 'week', 'day']" :key="m"
                    class="px-3 py-1.5 text-sm font-medium transition-colors"
                    :class="viewMode === m
                        ? 'bg-gray-700 text-white'
                        : 'bg-white text-gray-700 hover:bg-gray-50'"
                    @click="viewMode = m">
                    {{ { month: 'month', week: 'week', day: 'day' }[m] }}
                </button>
            </div>

            <!-- 予定追加ボタン -->
            <button
                class="rounded bg-blue-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-700"
                @click="openCreate()">
                + 予定を追加
            </button>

            <!-- 会議室予約ボタン（会議室が1件以上ある場合のみ） -->
            <button
                v-if="rooms.length"
                class="rounded border border-gray-300 bg-white px-4 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
                @click="openRoomCreate()">
                🏢 会議室予約
            </button>
        </div>

        <!-- ローディング -->
        <div v-if="loading" class="py-6 text-center text-sm text-gray-400">読み込み中…</div>

        <!-- カレンダー本体 -->
        <template v-else>
            <MonthView v-if="viewMode === 'month'"
                :year="viewYear" :month="viewMonth" :events="events"
                @date-click="onDateClick"
                @event-click="openDetail" />

            <WeekView v-else-if="viewMode === 'week'"
                :start-date="weekStart" :events="events"
                @create="openCreate"
                @update="onUpdate"
                @event-click="openDetail" />

            <DayView v-else
                :date="currentDate"
                :events="events"
                :reservations="reservations"
                :overlay-users="overlayUsers"
                :rooms="rooms"
                @create="openCreate"
                @update="onUpdate"
                @event-click="openDetail"
                @room-create="openRoomCreate"
                @room-click="openRoomEdit" />
        </template>

        <!-- オーバーレイパネル -->
        <OverlayPanel
            :overlays="overlays"
            :companies="companies"
            :departments="departments"
            @add="onOverlayAdd"
            @remove="onOverlayRemove" />

        <!-- モーダル -->
        <EventModal
            :show="showCreate"
            :event="editTarget"
            :default-date="createDef.date"
            :default-start-min="createDef.startMin"
            :default-end-min="createDef.endMin"
            :event-item-types="eventItemTypes"
            :companies="companies"
            :departments="departments"
            @close="onModalClose"
            @saved="onSaved"
            @deleted="onDeleted" />

        <EventDetailModal
            :show="showDetail"
            :event="detailTarget"
            @close="showDetail = false"
            @edit="openEdit" />

        <RoomReservationModal
            :show="showRoomModal"
            :reservation="roomModalTarget"
            :rooms="rooms"
            :default-date="roomModalDefaults.date"
            :default-start-min="roomModalDefaults.startMin"
            :default-end-min="roomModalDefaults.endMin"
            @close="showRoomModal = false"
            @saved="onRoomSaved"
            @deleted="onRoomDeleted" />
    </div>
</template>
