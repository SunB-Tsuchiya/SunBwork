<script setup>
import { ref, computed, watch, inject, onMounted } from 'vue';
import axios from 'axios';
import MonthView              from './MonthView.vue';
import WeekView               from './WeekView.vue';
import DayView                from './DayView.vue';
import EventModal             from './EventModal.vue';
import EventDetailModal       from './EventDetailModal.vue';
import RoomReservationModal   from './RoomReservationModal.vue';
import OverlayPanel           from './OverlayPanel.vue';
import MiniCalendar           from './MiniCalendar.vue';
import NotificationPanel      from './NotificationPanel.vue';

const props = defineProps({
    initialDate:        { type: String, default: '' },
    eventItemTypes:     { type: Array,  default: () => [] },
    meetingDefinitions: { type: Array,  default: () => [] },
    initialOverlays:    { type: Array,  default: () => [] },
    rooms:              { type: Array,  default: () => [] },
    companies:          { type: Array,  default: () => [] },
    departments:        { type: Array,  default: () => [] },
});

const CSRF     = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
const authUser = inject('authUser', null);

// ── ビューモード・基準日 ──────────────────────────────────────
const STORAGE_KEY_VIEW_MODE = 'schedule_view_mode';
const viewMode    = ref(localStorage.getItem(STORAGE_KEY_VIEW_MODE) || 'week');
watch(viewMode, (v) => localStorage.setItem(STORAGE_KEY_VIEW_MODE, v));
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
    // 会議室予約に紐づくイベントは予約モーダルで開く
    if (ev.room_reservation_id) {
        const reservation = reservations.value.find(r => String(r.id) === String(ev.room_reservation_id));
        if (reservation) { openRoomEdit(reservation); return; }
    }
    detailTarget.value = ev;
    showDetail.value   = true;
}

// ── 保存・削除 ─────────────────────────────────────────────────
function onSaved()   { editTarget.value = null; loadEvents(); }
function onDeleted() { editTarget.value = null; loadEvents(); }

function onModalClose() {
    loadEvents();
    editTarget.value = null;
    showCreate.value = false;
}

// ── ドラッグ移動・リサイズ ────────────────────────────────────
async function onUpdate({ id, starts_at, ends_at }) {
    // 楽観的更新（UI即反映）
    const idx = events.value.findIndex(e => e.id === id);
    if (idx >= 0) {
        // 会議室予約に紐づくイベントはドラッグ移動不可
        if (events.value[idx].room_reservation_id) return;
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

// ── 会議室予約ドラッグ更新 ────────────────────────────────────
async function onRoomUpdate({ id, starts_at, ends_at }) {
    const idx = reservations.value.findIndex(r => r.id === id);
    if (idx < 0) return;
    const orig = { ...reservations.value[idx] };
    reservations.value[idx] = { ...orig, starts_at, ends_at };
    try {
        await axios.put(
            route('schedule.room-reservations.update', { reservation: id }),
            { starts_at, ends_at },
            { headers: { 'X-CSRF-TOKEN': CSRF() } }
        );
        loadEvents();
    } catch (e) {
        reservations.value[idx] = orig;
        alert('会議室予約の更新に失敗しました');
    }
}

// ── 月ビューのイベントクリック ────────────────────────────────
function onDateClick(date) {
    currentDate.value = date;
    viewMode.value    = 'day';
}

// ── 会議室予約モーダル ────────────────────────────────────────
const showRoomModal        = ref(false);
const roomModalTarget      = ref(null); // 既存予約 or null（新規）
const roomModalDefaults    = ref({ date: '', startMin: null, endMin: null, roomId: null });
const roomModalReadOnly    = ref(false);
const roomModalLinkEventId = ref(null); // 既存イベントへのリンク用
const roomModalPreset      = ref(null); // { title, attendees, typeId } — EventDetailModal から引き継ぎ

function canEditReservation(reservation) {
    const u = authUser?.value ?? authUser;
    if (!u) return false;
    if (u.user_role === 'superadmin' || u.user_role === 'admin') return true;
    return String(reservation.user_id) === String(u.id);
}

function openRoomCreate({ date, startMin = null, endMin = null, roomId = null } = {}) {
    roomModalTarget.value   = null;
    roomModalReadOnly.value = false;
    roomModalDefaults.value = { date: date || currentDate.value, startMin, endMin, roomId };
    showRoomModal.value     = true;
}

function openRoomEdit(reservation) {
    roomModalTarget.value  = reservation;
    roomModalReadOnly.value = !canEditReservation(reservation);
    showRoomModal.value    = true;
}

function onRoomSaved()   { roomModalLinkEventId.value = null; roomModalPreset.value = null; loadEvents(); }
function onRoomDeleted() { roomModalLinkEventId.value = null; roomModalPreset.value = null; loadEvents(); }

function onRoomModalClose() {
    showRoomModal.value        = false;
    roomModalLinkEventId.value = null;
    roomModalPreset.value      = null;
}

// 既存予定から会議室予約を後から追加（EventDetailModal の「会議室を予約」ボタン）
function openRoomReserveForEvent(ev) {
    showDetail.value            = false;
    roomModalTarget.value       = null;
    roomModalReadOnly.value     = false;
    roomModalLinkEventId.value  = ev.id;
    const s = new Date(ev.starts_at);
    const e = new Date(ev.ends_at);
    const u = authUser?.value ?? authUser;
    const others = (ev.attendees ?? [])
        .filter(a => String(a.user?.id ?? a.user_id) !== String(u?.id))
        .map(a => ({ id: a.user?.id ?? a.user_id, name: a.user?.name ?? '' }));
    roomModalDefaults.value = {
        date:     s.toLocaleDateString('sv-SE'),
        startMin: s.getHours() * 60 + s.getMinutes(),
        endMin:   e.getHours() * 60 + e.getMinutes(),
        roomId:   null,
    };
    roomModalPreset.value = {
        title:       ev.title,
        attendees:   others,
        typeId:      ev.event_item_type_id ?? null,
        destination: ev.destination ?? '',
        notes:       ev.body ?? '',
    };
    showRoomModal.value = true;
}

// ミニカレンダーから日付を選択したとき → 日ビューで表示
function onMiniCalSelect(d) {
    currentDate.value = d;
    viewMode.value    = 'day';
}

// ── 今日の会議室予約（サイドバー） ───────────────────────────
const todayStr         = new Date().toLocaleDateString('sv-SE');
const todayReservations = ref([]);

async function loadTodayReservations() {
    try {
        const res = await axios.get(route('schedule.events.range'), {
            params: { start: todayStr, end: todayStr },
        });
        const uid = String((authUser?.value ?? authUser)?.id ?? '');
        todayReservations.value = (res.data.reservations ?? [])
            .filter(r => {
                if (String(r.user_id) === uid) return true;
                return (r.event?.attendees ?? []).some(a => String(a.user_id) === uid);
            })
            .sort((a, b) => (a.starts_at > b.starts_at ? 1 : -1));
    } catch (e) {
        // サイドバーなので静かに失敗
    }
}

onMounted(loadTodayReservations);

function onTodayResClick(reservation) {
    currentDate.value = todayStr;
    viewMode.value    = 'day';
    openRoomEdit(reservation);
}

function fmtSidebarTime(isoStr) {
    return new Date(isoStr).toLocaleTimeString('ja-JP', { hour: '2-digit', minute: '2-digit', hour12: false });
}

// 月ビュー: 会議室予約表示トグル（localStorage 保存・デフォルト OFF）
const STORAGE_KEY_MONTH_ROOMS = 'schedule_month_show_rooms';
const showMonthRooms = ref(localStorage.getItem(STORAGE_KEY_MONTH_ROOMS) === 'true');
watch(showMonthRooms, (v) => localStorage.setItem(STORAGE_KEY_MONTH_ROOMS, String(v)));
</script>

<template>
    <!-- 2ペインレイアウト: 左サイドバー + 右メイン -->
    <div class="flex" style="min-height: calc(100vh - 150px)">

        <!-- ── 左サイドバー ──────────────────────────────────────── -->
        <div class="w-52 shrink-0 border-r border-gray-200 bg-gray-50 flex flex-col overflow-y-auto">
            <!-- ミニカレンダー -->
            <MiniCalendar :date="currentDate" @select="onMiniCalSelect" />

            <!-- 今日の会議室予約 -->
            <div v-if="todayReservations.length > 0" class="px-2 pt-2 pb-1">
                <div class="mb-1 text-xs font-semibold text-gray-500 uppercase tracking-wide">今日の会議室</div>
                <button
                    v-for="r in todayReservations"
                    :key="r.id"
                    class="mb-1 w-full text-left rounded-md px-2 py-1 text-xs hover:bg-gray-100 transition-colors"
                    :style="{ borderLeft: `3px solid ${r.meeting_room?.color ?? '#9ca3af'}` }"
                    @click="onTodayResClick(r)">
                    <div class="font-medium text-gray-800 truncate">{{ r.title }}</div>
                    <div class="text-gray-500">
                        {{ fmtSidebarTime(r.starts_at) }}–{{ fmtSidebarTime(r.ends_at) }}
                        <span class="ml-1 text-gray-400">{{ r.meeting_room?.name }}</span>
                    </div>
                </button>
            </div>

            <!-- 区切り -->
            <div class="mx-3 my-1 border-t border-gray-200" />

            <!-- オーバーレイパネル -->
            <div class="px-1 py-1 flex-1">
                <OverlayPanel
                    :overlays="overlays"
                    :companies="companies"
                    :departments="departments"
                    @add="onOverlayAdd"
                    @remove="onOverlayRemove" />
            </div>
        </div>

        <!-- ── 右メインエリア ─────────────────────────────────────── -->
        <div class="flex flex-1 min-w-0 flex-col">
            <!-- ツールバー -->
            <div class="flex flex-wrap items-center gap-2 border-b border-gray-200 bg-white px-4 py-2">
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

                <!-- month / week / day タブ -->
                <div class="flex overflow-hidden rounded border border-gray-300">
                    <button v-for="m in ['month', 'week', 'day']" :key="m"
                        class="px-3 py-1.5 text-sm font-medium transition-colors"
                        :class="viewMode === m
                            ? 'bg-gray-700 text-white'
                            : 'bg-white text-gray-700 hover:bg-gray-50'"
                        @click="viewMode = m">
                        {{ { month: '月', week: '週', day: '日' }[m] }}
                    </button>
                </div>

                <!-- 月ビュー: 会議室予約表示トグル -->
                <label v-if="viewMode === 'month' && rooms.length"
                    class="flex cursor-pointer items-center gap-1.5 text-xs text-gray-600 select-none">
                    <input type="checkbox" v-model="showMonthRooms"
                        class="h-3.5 w-3.5 rounded border-gray-300 text-blue-600" />
                    会議室予約を表示
                </label>

                <!-- 通知パネル -->
                <NotificationPanel />

                <!-- 予定追加ボタン -->
                <button
                    class="rounded bg-blue-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-700"
                    @click="openCreate()">
                    + 予定を追加
                </button>

                <!-- 会議室予約ボタン -->
                <button
                    v-if="rooms.length"
                    class="rounded border border-gray-300 bg-white px-4 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
                    @click="openRoomCreate()">
                    🏢 会議室予約
                </button>
            </div>

            <!-- ローディング -->
            <div v-if="loading" class="flex-1 py-12 text-center text-sm text-gray-400">読み込み中…</div>

            <!-- カレンダー本体 -->
            <div v-else class="flex-1 overflow-auto p-3">
                <MonthView v-if="viewMode === 'month'"
                    :year="viewYear" :month="viewMonth" :events="events"
                    :reservations="showMonthRooms ? reservations : []"
                    :rooms="showMonthRooms ? rooms : []"
                    @date-click="onDateClick"
                    @event-click="openDetail"
                    @room-click="openRoomEdit" />

                <WeekView v-else-if="viewMode === 'week'"
                    :start-date="weekStart" :events="events"
                    :reservations="reservations" :rooms="rooms"
                    @create="openCreate"
                    @update="onUpdate"
                    @event-click="openDetail"
                    @room-click="openRoomEdit" />

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
                    @room-click="openRoomEdit"
                    @room-update="onRoomUpdate" />
            </div>
        </div>
    </div>

    <!-- モーダル（Teleport to body なので位置は影響なし） -->
    <EventModal
        :show="showCreate"
        :event="editTarget"
        :default-date="createDef.date"
        :default-start-min="createDef.startMin"
        :default-end-min="createDef.endMin"
        :event-item-types="eventItemTypes"
        :meeting-definitions="meetingDefinitions"
        :rooms="rooms"
        :companies="companies"
        :departments="departments"
        @close="onModalClose"
        @saved="onSaved"
        @deleted="onDeleted" />

    <EventDetailModal
        :show="showDetail"
        :event="detailTarget"
        @close="showDetail = false"
        @edit="openEdit"
        @open-room-reserve="openRoomReserveForEvent"
        @responded="() => { showDetail = false; loadEvents(); }" />

    <RoomReservationModal
        :show="showRoomModal"
        :reservation="roomModalTarget"
        :rooms="rooms"
        :event-item-types="eventItemTypes"
        :meeting-definitions="meetingDefinitions"
        :default-date="roomModalDefaults.date"
        :default-start-min="roomModalDefaults.startMin"
        :default-end-min="roomModalDefaults.endMin"
        :default-room-id="roomModalDefaults.roomId"
        :default-title="roomModalPreset?.title ?? ''"
        :default-attendees="roomModalPreset?.attendees ?? []"
        :default-type-id="roomModalPreset?.typeId ?? null"
        :default-destination="roomModalPreset?.destination ?? ''"
        :default-notes="roomModalPreset?.notes ?? ''"
        :link-event-id="roomModalLinkEventId"
        :read-only="roomModalReadOnly"
        :companies="companies"
        :departments="departments"
        @close="onRoomModalClose"
        @saved="onRoomSaved"
        @deleted="onRoomDeleted" />
</template>
