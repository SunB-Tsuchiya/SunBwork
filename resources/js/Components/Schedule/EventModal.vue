<script setup>
import { ref, watch, computed, inject, onMounted } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import AttendeeSelector from './AttendeeSelector.vue';
import useToasts from '@/Composables/useToasts';

// 15分刻みの時刻オプション (00:00〜23:45)
const timeOptions = [];
for (let h = 0; h < 24; h++) {
    for (let m = 0; m < 60; m += 15) {
        timeOptions.push(`${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`);
    }
}

const props = defineProps({
    show:               { type: Boolean, default: false },
    event:              { type: Object,  default: null },
    defaultDate:        { type: String,  default: '' },
    defaultStartMin:    { type: Number,  default: null },
    defaultEndMin:      { type: Number,  default: null },
    eventItemTypes:     { type: Array,   default: () => [] },
    meetingDefinitions: { type: Array,   default: () => [] },
    rooms:              { type: Array,   default: () => [] },
    companies:          { type: Array,   default: () => [] },
    departments:        { type: Array,   default: () => [] },
    existingEvents:     { type: Array,   default: () => [] }, // 個人ジョブ予定との重複チェック用
});

const emit = defineEmits(['close', 'saved', 'deleted']);

const authUser = inject('authUser', null);
const CSRFTOKEN = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
const { showToast } = useToasts();

// ── 種別・会議定義 ─────────────────────────────────────────────
const selectedTypeId      = ref(null);
const selectedMeetingId   = ref(null);
const titleManuallyEdited = ref(false);

const selectedSlug      = computed(() => props.eventItemTypes.find(t => t.id === selectedTypeId.value)?.slug ?? null);
const showMeetingSelect  = computed(() => selectedSlug.value === 'conference');
const showDestination    = computed(() => ['customer_visit', 'meeting_client', 'client_visit'].includes(selectedSlug.value));

// ── クライアント候補 ──────────────────────────────────────────
const clients = ref([]);

onMounted(async () => {
    try {
        const res = await axios.get(route('schedule.clients.index'));
        clients.value = res.data;
    } catch {
        // クライアント候補が取得できなくても動作継続
    }
});

const recurrenceLabel = { weekly: '毎週', biweekly: '隔週', monthly: '毎月', custom_dates: 'カレンダー指定' };
const dayLabel = ['日', '月', '火', '水', '木', '金', '土'];

// ── フォーム ───────────────────────────────────────────────────
const form = ref({
    title:                 '',
    date:                  '',
    startTime:             '',
    endTime:               '',
    event_item_type_id:    null,
    meeting_definition_id: null,
    destination:           '',
    body:                  '',
    visibility:            'company',
    is_company_event:      true,
});
const errors        = ref({});
const loading       = ref(false);
const formAttendees = ref([]);

// スペース区切り JST 文字列を ISO 形式に正規化（Safari 対応）
function normDateStr(s) { return s ? s.replace(' ', 'T') : s; }

// ── 自分のジョブ予定との重複チェック ──────────────────────────
const selfConflicts = computed(() => {
    const { date, startTime, endTime } = form.value;
    if (!date || !startTime || !endTime || startTime >= endTime) return [];
    const newStart = new Date(`${date}T${startTime}:00`).getTime();
    const newEnd   = new Date(`${date}T${endTime}:00`).getTime();
    return props.existingEvents.filter(ev => {
        if (props.event && ev.id === props.event.id) return false;
        if (!ev.starts_at || !ev.ends_at) return false;
        const evStart = new Date(normDateStr(ev.starts_at)).getTime();
        const evEnd   = new Date(normDateStr(ev.ends_at)).getTime();
        return evStart < newEnd && evEnd > newStart;
    });
});

// ── 参加者競合チェック ─────────────────────────────────────────
const conflictWarnings = ref([]);
let conflictTimer = null;

function fmtConflictTime(isoStr) {
    const d = new Date(normDateStr(isoStr));
    return d.toLocaleTimeString('ja-JP', { hour: '2-digit', minute: '2-digit', hour12: false });
}

async function checkConflicts() {
    const userIds = formAttendees.value.map(a => a.id).filter(Boolean);
    const { date, startTime, endTime } = form.value;
    if (!userIds.length || !date || !startTime || !endTime || startTime >= endTime) {
        conflictWarnings.value = [];
        return;
    }
    try {
        const res = await axios.get(route('schedule.events.conflicts'), {
            params: {
                starts_at:        `${date} ${startTime}:00`,
                ends_at:          `${date} ${endTime}:00`,
                user_ids:         userIds,
                ...(props.event ? { exclude_event_id: props.event.id } : {}),
            },
        });
        conflictWarnings.value = res.data;
    } catch {
        conflictWarnings.value = [];
    }
}

function scheduleConflictCheck() {
    clearTimeout(conflictTimer);
    conflictTimer = setTimeout(checkConflicts, 500);
}

watch([formAttendees, () => form.value.date, () => form.value.startTime, () => form.value.endTime],
    scheduleConflictCheck, { deep: true });

function pad(n) { return String(n).padStart(2, '0'); }

function minToTime(min) {
    const snapped = Math.round(min / 15) * 15;
    return `${pad(Math.floor(snapped / 60))}:${pad(snapped % 60)}`;
}

function toDateParts(d) {
    return {
        date: `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`,
        time: `${pad(d.getHours())}:${pad(d.getMinutes())}`,
    };
}

// ── 次回開催日計算（CreateInternalEvent.vue から移植） ─────────
function calcNextDate(dayOfWeek, sh, sm) {
    const now   = new Date();
    const today = new Date(); today.setHours(0,0,0,0);
    const todayDow = today.getDay();
    let diff = dayOfWeek - todayDow;
    if (diff === 0) {
        const ms = new Date(today); ms.setHours(parseInt(sh), parseInt(sm), 0, 0);
        if (now >= ms) diff = 7;
    } else if (diff < 0) diff += 7;
    const next = new Date(today.getTime() + diff * 86400000);
    return `${next.getFullYear()}-${pad(next.getMonth()+1)}-${pad(next.getDate())}`;
}

function calcNextMonthlyDate(dayOfWeek, weekOfMonth, sh, sm) {
    const now   = new Date();
    const today = new Date(); today.setHours(0,0,0,0);
    for (let offset = 0; offset <= 24; offset++) {
        const first = new Date(today.getFullYear(), today.getMonth() + offset, 1);
        let diff = dayOfWeek - first.getDay();
        if (diff < 0) diff += 7;
        const candidate = new Date(first.getFullYear(), first.getMonth(), 1 + diff + (weekOfMonth - 1) * 7);
        if (candidate.getMonth() !== first.getMonth()) continue;
        if (candidate > today) {
            return `${candidate.getFullYear()}-${pad(candidate.getMonth()+1)}-${pad(candidate.getDate())}`;
        } else if (candidate.getTime() === today.getTime()) {
            const ms = new Date(today); ms.setHours(parseInt(sh), parseInt(sm), 0, 0);
            if (now < ms) return `${candidate.getFullYear()}-${pad(candidate.getMonth()+1)}-${pad(candidate.getDate())}`;
        }
    }
    return '';
}

function calcNextCustomDate(customDates, sh, sm) {
    if (!Array.isArray(customDates) || customDates.length === 0) return '';
    const sorted = [...customDates].sort((a, b) => a.localeCompare(b));
    const today = new Date().toLocaleDateString('sv-SE');
    const now = new Date();

    for (const dateValue of sorted) {
        if (dateValue > today) return dateValue;
        if (dateValue === today) {
            const ms = new Date();
            ms.setHours(parseInt(sh, 10), parseInt(sm, 10), 0, 0);
            if (now < ms) return dateValue;
        }
    }

    return sorted[0] ?? '';
}

// ── show 変化時にフォーム初期化 ───────────────────────────────
watch(() => props.show, (v) => {
    if (!v) {
        conflictWarnings.value = [];
        clearTimeout(conflictTimer);
        return;
    }
    errors.value = {};
    titleManuallyEdited.value = false;

    if (props.event) {
        const s = toDateParts(new Date(props.event.starts_at));
        const e = toDateParts(new Date(props.event.ends_at));
        selectedTypeId.value    = props.event.event_item_type_id ?? null;
        selectedMeetingId.value = props.event.meeting_definition_id ?? null;
        form.value = {
            title:                 props.event.title ?? '',
            date:                  s.date,
            startTime:             s.time,
            endTime:               e.time,
            event_item_type_id:    props.event.event_item_type_id ?? null,
            meeting_definition_id: props.event.meeting_definition_id ?? null,
            destination:           props.event.destination ?? '',
            body:                  props.event.body ?? '',
            visibility:            props.event.visibility ?? 'company',
            is_company_event:      props.event.is_company_event ?? true,
        };
    } else {
        const date     = props.defaultDate || new Date().toLocaleDateString('sv-SE');
        const startMin = props.defaultStartMin ?? 9 * 60;
        const endMin   = props.defaultEndMin   ?? (startMin + 60);
        selectedTypeId.value    = null;
        selectedMeetingId.value = null;
        selectedRoomId.value    = null;
        form.value = {
            title:                 '',
            date,
            startTime:             minToTime(startMin),
            endTime:               minToTime(endMin),
            event_item_type_id:    selectedTypeId.value,
            meeting_definition_id: null,
            destination:           '',
            body:                  '',
            visibility:            'company',
            is_company_event:      true,
        };
        formAttendees.value = [];
    }
});

// 種別変更 → event_item_type_id 同期・非会議時は会議定義をリセット
watch(selectedTypeId, (v) => {
    form.value.event_item_type_id = v;
    if (selectedSlug.value !== 'conference') {
        selectedMeetingId.value = null;
        form.value.meeting_definition_id = null;
        if (!titleManuallyEdited.value) {
            form.value.title = props.eventItemTypes.find(t => t.id === v)?.name ?? '';
        }
        titleManuallyEdited.value = false;
    }
    if (!showDestination.value) {
        form.value.destination = '';
    }
});

// 会議定義選択 → タイトル・時刻・日付・参加者 自動入力
watch(selectedMeetingId, (id) => {
    form.value.meeting_definition_id = id;
    if (!id) return;
    const meeting = props.meetingDefinitions.find(m => m.id === id);
    if (!meeting) return;

    if (!titleManuallyEdited.value) form.value.title = meeting.title;
    form.value.body = meeting.description ?? '';

    const sh = String(meeting.start_time).split(':')[0].padStart(2, '0');
    const sm = String(meeting.start_time).split(':')[1].padStart(2, '0');
    const eh = String(meeting.end_time).split(':')[0].padStart(2, '0');
    const em = String(meeting.end_time).split(':')[1].padStart(2, '0');
    form.value.startTime = `${sh}:${sm}`;
    form.value.endTime   = `${eh}:${em}`;

    if (meeting.recurrence === 'custom_dates') {
        form.value.date = calcNextCustomDate(meeting.custom_dates, sh, sm);
    } else if (meeting.recurrence === 'monthly' && meeting.week_of_month) {
        form.value.date = calcNextMonthlyDate(meeting.day_of_week, meeting.week_of_month, sh, sm);
    } else {
        form.value.date = calcNextDate(meeting.day_of_week, sh, sm);
    }

    // 新規作成時のみ参加者を自動入力
    if (!props.event) {
        const selfId = authUser?.value?.id ?? authUser?.id;
        formAttendees.value = meeting.members
            .filter(m => m.id !== selfId)
            .map(m => ({ id: m.id, name: m.name }));
    }
});

// ── 編集モードの参加者 ─────────────────────────────────────────
const editAttendees = computed(() => {
    if (!props.event?.attendees) return [];
    return props.event.attendees.map(a => ({
        id:   a.user_id ?? a.user?.id,
        name: a.user?.name ?? '',
    })).filter(a => a.id);
});

// ── 重複確認ダイアログ（新規作成時のみ） ────────────────────
// CreateInternalEvent.vue と同様に events.index?date= を fetch してから confirm() を表示する。
// 実際の interruption_minutes 更新はサーバーサイド（recalcInterruptionMinutes）が行う。
async function confirmOverlap(date, newStart, newEnd) {
    const newDuration = newEnd - newStart;
    let events;
    try {
        const evUrl = route('events.index') + `?date=${encodeURIComponent(date)}`;
        const res = await fetch(evUrl, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (!res.ok) return true; // fetch 失敗時は素通し
        events = await res.json();
    } catch {
        return true;
    }

    const overlapping = events.filter(ev => {
        const evStart = new Date(normDateStr(ev.start ?? ev.starts_at)).getTime();
        const evEnd   = new Date(normDateStr(ev.end   ?? ev.ends_at)).getTime();
        return evStart < newEnd && evEnd > newStart;
    });
    if (!overlapping.length) return true;

    const lines = overlapping.map(ev => {
        const evStart    = new Date(normDateStr(ev.start ?? ev.starts_at)).getTime();
        const evEnd      = new Date(normDateStr(ev.end   ?? ev.ends_at)).getTime();
        const evDuration = evEnd - evStart;
        const overlapStart = Math.max(newStart, evStart);
        const overlapEnd   = Math.min(newEnd, evEnd);
        const overlapMins  = Math.max(0, Math.round((overlapEnd - overlapStart) / 60000));
        const sStr = new Date(evStart).toLocaleTimeString('ja-JP', { hour: '2-digit', minute: '2-digit', hour12: false });
        const eStr = new Date(evEnd).toLocaleTimeString('ja-JP',   { hour: '2-digit', minute: '2-digit', hour12: false });
        if (newDuration >= evDuration) {
            return `「${ev.title}」(${sStr}〜${eStr}) → ${overlapMins}分間重複（今回の会議から差し引き）`;
        } else {
            return `「${ev.title}」(${sStr}〜${eStr}) → ${overlapMins}分間重複（既存の予定から差し引き）`;
        }
    });

    const msg = `以下の予定と時間が重複しています。登録しますか？\n\n${lines.join('\n')}\n\n【OK】を押すと、時間の長い方の予定から重複時間が差し引かれます。`;
    return confirm(msg);
}

// ── 送信 ──────────────────────────────────────────────────────
async function submit() {
    if (form.value.startTime >= form.value.endTime) {
        errors.value = { startTime: ['終了時刻は開始時刻より後に設定してください'] };
        return;
    }
    // 参加者のスケジュール競合は上部バナーで警告のみ表示し、保存はブロックしない

    // 新規作成時のみ: ジョブ予定との重複を確認ダイアログで知らせる
    if (!props.event) {
        const { date, startTime, endTime } = form.value;
        const newStart = new Date(`${date}T${startTime}:00`).getTime();
        const newEnd   = new Date(`${date}T${endTime}:00`).getTime();
        const ok = await confirmOverlap(date, newStart, newEnd);
        if (!ok) return;
    }

    loading.value = true;
    errors.value  = {};

    const { date, startTime, endTime, ...rest } = form.value;
    const payload = {
        ...rest,
        starts_at: `${date} ${startTime}:00`,
        ends_at:   `${date} ${endTime}:00`,
    };

    try {
        let res;
        if (props.event) {
            res = await axios.put(route('schedule.events.update', { event: props.event.id }), payload, {
                headers: { 'X-CSRF-TOKEN': CSRFTOKEN() },
            });
        } else {
            payload.attendee_ids = formAttendees.value.map(a => a.id);
            res = await axios.post(route('schedule.events.store'), payload, {
                headers: { 'X-CSRF-TOKEN': CSRFTOKEN() },
            });

            // タイムテーブルで会議室が選択されていれば、イベントに紐づけて予約作成
            if (selectedRoomId.value) {
                try {
                    await axios.post(
                        route('schedule.room-reservations.store', { room: selectedRoomId.value }),
                        {
                            title:              form.value.title,
                            starts_at:          `${form.value.date} ${form.value.startTime}:00`,
                            ends_at:            `${form.value.date} ${form.value.endTime}:00`,
                            notes:              form.value.body || null,
                            event_item_type_id: form.value.event_item_type_id || null,
                            destination:        showDestination.value ? (form.value.destination || null) : null,
                            self_included:      true,
                            // attendee_ids は送らない — EventController が既に正しく設定済み
                            // 送ると filterAttendeesByPermission でフィルタされ参加者が削除される
                            link_event_id:      res.data.id,
                        },
                        { headers: { 'X-CSRF-TOKEN': CSRFTOKEN() } }
                    );
                } catch (roomErr) {
                    // 補償処理: 作成済みイベントを削除してロールバック
                    try {
                        await axios.delete(
                            route('schedule.events.destroy', { event: res.data.id }),
                            { headers: { 'X-CSRF-TOKEN': CSRFTOKEN() } }
                        );
                    } catch {}
                    const msg = roomErr.response?.data?.message ?? '会議室予約に失敗しました。時間帯や会議室を確認してください。';
                    errors.value = { _general: msg };
                    return;
                }
            }
        }
        emit('saved', res.data);
        emit('close');

        // 未登録クライアント → 登録案内トースト（Admin 以上のみ）
        const dest = form.value.destination?.trim();
        if (dest && showDestination.value) {
            const known = clients.value.some(c => c.name === dest);
            const role  = authUser?.value?.user_role ?? authUser?.user_role;
            if (!known && ['admin', 'superadmin', 'leader', 'clerk', 'coordinator'].includes(role)) {
                showToast(
                    `「${dest}」はクライアント未登録です`,
                    'info',
                    0,
                    { label: 'クライアント登録へ', handler: () => router.get(route('clients.create')) }
                );
            }
        }
    } catch (e) {
        if (e.response?.status === 422) {
            errors.value = e.response.data.errors ?? {};
        }
    } finally {
        loading.value = false;
    }
}

async function deleteEvent() {
    if (!confirm('この予定を削除しますか？')) return;
    loading.value = true;
    try {
        await axios.delete(route('schedule.events.destroy', { event: props.event.id }), {
            headers: { 'X-CSRF-TOKEN': CSRFTOKEN() },
        });
        emit('deleted', props.event.id);
        emit('close');
    } finally {
        loading.value = false;
    }
}

const isEdit = computed(() => !!props.event);

// ── 会議室タイムライン ─────────────────────────────────────────
const TL_START = 8 * 60;   // 8:00
const TL_END   = 20 * 60;  // 20:00
const TL_DUR   = TL_END - TL_START;
const tlHours  = Array.from({ length: 13 }, (_, i) => 8 + i);

// 外出・顧客訪問以外の種別が選択されていれば会議室タイムラインを表示（新規作成のみ）
const NO_ROOM_SLUGS = ['outing', 'customer_visit'];
const showRoomPicker = computed(() =>
    selectedSlug.value !== null
    && !NO_ROOM_SLUGS.includes(selectedSlug.value)
    && !isEdit.value
    && props.rooms.length > 0
);

const loadingRooms    = ref(false);
const dayReservations = ref([]);
const selectedRoomId  = ref(null);  // タイムテーブルで選択した会議室 ID
const activeRoomId    = ref(null);  // 現在ドラッグ中または最後に操作した会議室

// タイムライン計算ユーティリティ
function timeStrToMinutes(t) {
    if (!t) return 0;
    const [h, m] = t.split(':').map(Number);
    return h * 60 + m;
}

function isoToJstMinutes(isoStr) {
    const d = new Date(new Date(isoStr).toLocaleString('ja-JP', { timeZone: 'Asia/Tokyo' }));
    return d.getHours() * 60 + d.getMinutes();
}

function tlLeft(minutes) {
    return `${Math.max(0, (minutes - TL_START) / TL_DUR * 100).toFixed(2)}%`;
}

function tlWidth(startMin, endMin) {
    return `${Math.max(0, (endMin - startMin) / TL_DUR * 100).toFixed(2)}%`;
}

function tlXToMin(el, clientX) {
    const rect = el.getBoundingClientRect();
    const x    = Math.max(0, Math.min(clientX - rect.left, rect.width));
    const raw  = TL_START + (x / rect.width) * TL_DUR;
    return Math.max(TL_START, Math.min(TL_END, Math.round(raw / 15) * 15));
}

// 各会議室の全予約と競合チェック
const roomAvailability = computed(() => {
    const start = timeStrToMinutes(form.value.startTime || '00:00');
    const end   = timeStrToMinutes(form.value.endTime   || '00:00');
    return props.rooms.map(room => {
        const reservations = dayReservations.value.filter(r => r.meeting_room_id === room.id);
        const conflicts = reservations.filter(r => {
            const rs = isoToJstMinutes(r.starts_at);
            const re = isoToJstMinutes(r.ends_at);
            return rs < end && re > start;
        });
        // 予約可能時間チェック: 開始時刻のみ対象（終了が超えていても開始が範囲内なら許可）
        let withinHours = true;
        if (room.available_from && room.available_to) {
            const fromMin = timeStrToMinutes(room.available_from);
            const toMin   = timeStrToMinutes(room.available_to);
            withinHours = start >= fromMin && start < toMin;
        }
        return { room, reservations, conflicts, available: start < end && conflicts.length === 0 && withinHours };
    });
});

// 会議タイプに切り替わったら自動フェッチ
watch(showRoomPicker, (v) => { if (v) fetchDayReservations(); });
watch(() => form.value.date, (v) => {
    selectedRoomId.value = null;  // 日付変更時は選択リセット
    if (showRoomPicker.value && v) fetchDayReservations();
});

async function fetchDayReservations() {
    if (!form.value.date) return;
    loadingRooms.value = true;
    try {
        const res = await axios.get(route('schedule.events.range'), {
            params: { start: form.value.date, end: form.value.date },
        });
        dayReservations.value = res.data.reservations ?? [];
    } catch {
        dayReservations.value = [];
    } finally {
        loadingRooms.value = false;
    }
}

// タイムライン ドラッグ → ドラッグした会議室のみハイライト・form 時刻をリアルタイム更新
function onTrackMousedown(roomId, event) {
    activeRoomId.value = roomId;
    const el = event.currentTarget;
    let anchorMin = tlXToMin(el, event.clientX);
    form.value.startTime = minToTime(anchorMin);
    form.value.endTime   = minToTime(Math.min(TL_END, anchorMin + 60));

    const onMove = (e) => {
        const curMin = tlXToMin(el, e.clientX);
        const s   = Math.min(anchorMin, curMin);
        const end = Math.max(anchorMin, curMin);
        if (end > s) {
            form.value.startTime = minToTime(s);
            form.value.endTime   = minToTime(end);
        }
    };
    const onUp = () => {
        document.removeEventListener('mousemove', onMove);
        document.removeEventListener('mouseup', onUp);
    };
    document.addEventListener('mousemove', onMove);
    document.addEventListener('mouseup', onUp);
}

function openRoomReserve(roomId) {
    // モーダルを開かず、選択状態をトグル（EventModal 保存時に一緒に予約する）
    selectedRoomId.value = selectedRoomId.value === roomId ? null : roomId;
    activeRoomId.value   = roomId;
}

function pad2(n) { return String(n).padStart(2, '0'); }
function fmtResTime(isoStr) {
    const d = new Date(new Date(isoStr).toLocaleString('ja-JP', { timeZone: 'Asia/Tokyo' }));
    return `${pad2(d.getHours())}:${pad2(d.getMinutes())}`;
}
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center">
            <div class="absolute inset-0 bg-black/40" @click="$emit('close')" />
            <div class="relative z-10 w-full max-w-lg rounded-xl bg-white p-6 shadow-2xl overflow-y-auto" style="max-height: 90vh">
                <h2 class="mb-4 text-lg font-semibold text-gray-800">
                    {{ isEdit ? '予定を編集' : '予定を作成' }}
                </h2>

                <form class="space-y-4" @submit.prevent="submit">
                    <p v-if="errors._general" class="rounded bg-red-50 px-3 py-2 text-sm text-red-600">{{ errors._general }}</p>

                    <!-- 種別（セレクター） -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700">種別</label>
                        <select v-model="selectedTypeId"
                            class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                            <option :value="null">— 選択なし —</option>
                            <option v-for="t in eventItemTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                        </select>
                    </div>

                    <!-- 会議定義ピッカー（会議選択時のみ） -->
                    <div v-if="showMeetingSelect && meetingDefinitions.length">
                        <label class="block text-sm font-medium text-gray-700">会議種類</label>
                        <select v-model="selectedMeetingId"
                            class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                            <option :value="null">— 選択なし —</option>
                            <option v-for="m in meetingDefinitions" :key="m.id" :value="m.id">
                                {{ m.title }}（{{ recurrenceLabel[m.recurrence] }}{{ m.recurrence === 'custom_dates' ? `・${m.custom_dates?.length ?? 0}日選択` : `・${dayLabel[m.day_of_week]}曜` }}）
                            </option>
                        </select>
                    </div>

                    <!-- 取引先名（顧客訪問・打ち合わせ顧客のみ） -->
                    <div v-if="showDestination">
                        <label class="block text-sm font-medium text-gray-700">取引先名</label>
                        <input v-model="form.destination"
                            type="text"
                            list="schedule-clients-list"
                            placeholder="取引先名（自由入力可）"
                            class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                        <datalist id="schedule-clients-list">
                            <option v-for="c in clients" :key="c.id" :value="c.name" />
                        </datalist>
                        <p v-if="errors.destination" class="mt-1 text-xs text-red-500">{{ errors.destination[0] }}</p>
                    </div>

                    <!-- タイトル -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700">タイトル <span class="text-red-500">*</span></label>
                        <input v-model="form.title" type="text" required
                            class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
                            @input="titleManuallyEdited = true" />
                        <p v-if="errors.title" class="mt-1 text-xs text-red-500">{{ errors.title[0] }}</p>
                    </div>

                    <!-- 日付 -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700">日付 <span class="text-red-500">*</span></label>
                        <input v-model="form.date" type="date" required
                            class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                    </div>

                    <!-- 開始・終了時刻 -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">開始時刻 <span class="text-red-500">*</span></label>
                            <select v-model="form.startTime" required
                                class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                                <option v-for="t in timeOptions" :key="t" :value="t">{{ t }}</option>
                            </select>
                            <p v-if="errors.startTime" class="mt-1 text-xs text-red-500">{{ errors.startTime[0] }}</p>
                            <p v-if="errors.starts_at" class="mt-1 text-xs text-red-500">{{ errors.starts_at[0] }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">終了時刻 <span class="text-red-500">*</span></label>
                            <select v-model="form.endTime" required
                                class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                                <option v-for="t in timeOptions" :key="t" :value="t">{{ t }}</option>
                            </select>
                        </div>
                    </div>

                    <!-- 公開範囲 -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700">公開範囲</label>
                        <select v-model="form.visibility"
                            class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                            <option value="private">非公開（自分のみ）</option>
                            <option value="company">社内公開</option>
                            <option value="group">グループ公開</option>
                            <option value="public">全体公開</option>
                        </select>
                    </div>

                    <!-- メモ -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700">メモ</label>
                        <textarea v-model="form.body" rows="2"
                            class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                    </div>

                    <!-- 参加者 -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">参加者</label>
                        <!-- 自分は必ず参加者（新規作成時のみ表示） -->
                        <div v-if="!isEdit" class="mb-1.5 flex flex-wrap gap-1.5">
                            <span class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800">
                                自分（{{ authUser?.name ?? authUser?.value?.name }}）
                            </span>
                        </div>
                        <AttendeeSelector
                            v-if="isEdit"
                            :event-id="event.id"
                            :attendees="editAttendees"
                            :self-id="authUser?.id ?? null"
                            :companies="companies"
                            :departments="departments"
                        />
                        <AttendeeSelector
                            v-else
                            :event-id="null"
                            :attendees="formAttendees"
                            :self-id="authUser?.id ?? null"
                            :companies="companies"
                            :departments="departments"
                            @change="v => formAttendees = v"
                        />
                    </div>

                    <!-- 自分のジョブ予定との時間重複警告（保存は可能） -->
                    <div v-if="selfConflicts.length"
                        class="rounded-md border border-orange-300 bg-orange-50 px-3 py-2 text-sm text-orange-800">
                        <div class="mb-1 font-medium">⚠ この時間帯に別の予定があります</div>
                        <ul class="space-y-0.5 text-xs">
                            <li v-for="ev in selfConflicts" :key="ev.id">
                                「{{ ev.title }}」（{{ fmtConflictTime(ev.starts_at) }}〜{{ fmtConflictTime(ev.ends_at) }}）
                            </li>
                        </ul>
                    </div>

                    <!-- 参加者スケジュール競合警告 -->
                    <div v-if="conflictWarnings.length"
                        class="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                        <div class="mb-1 font-medium">⚠ 参加者のスケジュール競合</div>
                        <ul class="space-y-0.5 text-xs">
                            <li v-for="w in conflictWarnings" :key="w.user_id">
                                <span class="font-medium">{{ w.user_name }}</span>：
                                <span v-for="(ev, i) in w.events" :key="i">
                                    {{ fmtConflictTime(ev.starts_at) }}〜{{ fmtConflictTime(ev.ends_at) }}「{{ ev.title }}」<span v-if="i < w.events.length - 1">、</span>
                                </span>
                                に別の予定が入っています
                            </li>
                        </ul>
                    </div>

                    <!-- 会議室タイムライン（会議タイプ自動表示） -->
                    <div v-if="showRoomPicker">
                        <label class="block text-sm font-medium text-gray-700 mb-1">会議室</label>
                        <p class="mb-2 text-[11px] text-gray-400">タイムラインをドラッグして時間を選び、空いている会議室の「予約」をクリック</p>
                        <div class="rounded-lg border border-gray-200 overflow-hidden text-xs select-none">
                            <!-- 時間軸ヘッダー -->
                            <div class="flex border-b border-gray-200 bg-gray-50">
                                <div class="w-14 flex-shrink-0 border-r border-gray-200"></div>
                                <div class="relative flex-1 h-5">
                                    <span v-for="h in tlHours" :key="h"
                                          class="absolute -translate-x-1/2 top-0 leading-5 text-[10px] text-gray-400"
                                          :style="{ left: tlLeft(h * 60) }">{{ h }}</span>
                                </div>
                                <div class="w-10 flex-shrink-0 border-l border-gray-200"></div>
                            </div>
                            <!-- 読み込み中 -->
                            <div v-if="loadingRooms" class="py-3 text-center text-gray-400">読み込み中…</div>
                            <!-- 会議室行 -->
                            <div v-for="ra in roomAvailability" :key="ra.room.id"
                                 class="flex items-center border-b border-gray-100 last:border-b-0">
                                <!-- 部屋名（色帯 + ラベル） -->
                                <div class="w-14 flex-shrink-0 flex items-stretch border-r border-gray-200">
                                    <div class="w-1.5 flex-shrink-0 self-stretch"
                                         :style="{ backgroundColor: ra.room.color || '#9ca3af' }"></div>
                                    <span class="px-1 py-2 text-[11px] font-medium text-gray-700 truncate leading-tight"
                                          :title="ra.room.name">{{ ra.room.name }}</span>
                                </div>
                                <!-- トラック -->
                                <div class="relative flex-1 h-8 bg-white cursor-crosshair"
                                     :title="`${ra.room.name} — ドラッグして時間を選択`"
                                     @mousedown.prevent="onTrackMousedown(ra.room.id, $event)">
                                    <!-- グリッド線 -->
                                    <div v-for="h in tlHours" :key="h"
                                         class="absolute top-0 bottom-0 border-l border-gray-100 pointer-events-none"
                                         :style="{ left: tlLeft(h * 60) }"></div>
                                    <!-- 既存予約 -->
                                    <div v-for="res in ra.reservations" :key="res.id"
                                         class="absolute top-1 bottom-1 rounded pointer-events-none flex items-center overflow-hidden"
                                         :title="`${fmtResTime(res.starts_at)}〜${fmtResTime(res.ends_at)} ${res.title}`"
                                         :style="{
                                             left: tlLeft(isoToJstMinutes(res.starts_at)),
                                             width: tlWidth(isoToJstMinutes(res.starts_at), isoToJstMinutes(res.ends_at)),
                                             backgroundColor: ra.room.color || '#6b7280',
                                         }">
                                        <span class="px-1 text-[9px] text-white truncate leading-none">{{ res.title }}</span>
                                    </div>
                                    <!-- 選択ハイライト（この会議室をドラッグ操作したときのみ） -->
                                    <div v-if="activeRoomId === ra.room.id && form.startTime && form.endTime && form.startTime < form.endTime"
                                         class="absolute top-0 bottom-0 bg-blue-400/25 border-x-2 border-blue-500 pointer-events-none"
                                         :style="{
                                             left: tlLeft(timeStrToMinutes(form.startTime)),
                                             width: tlWidth(timeStrToMinutes(form.startTime), timeStrToMinutes(form.endTime)),
                                         }"></div>
                                </div>
                                <!-- 選択ボタン / 状態 -->
                                <div class="w-10 flex-shrink-0 flex justify-center border-l border-gray-200 py-1">
                                    <button v-if="form.startTime < form.endTime && ra.available" type="button"
                                            :class="selectedRoomId === ra.room.id
                                                ? 'rounded bg-green-600 px-1.5 py-0.5 text-[10px] font-medium text-white hover:bg-green-700'
                                                : 'rounded bg-blue-600 px-1.5 py-0.5 text-[10px] font-medium text-white hover:bg-blue-700'"
                                            @click="openRoomReserve(ra.room.id)">
                                        {{ selectedRoomId === ra.room.id ? '✓' : '選択' }}
                                    </button>
                                    <span v-else-if="form.startTime < form.endTime && !ra.available"
                                          class="text-red-400 text-[11px]">×</span>
                                </div>
                            </div>
                        </div>

                        <!-- 選択中の会議室チップ -->
                        <div v-if="selectedRoomId" class="mt-2 flex items-center gap-2 rounded-md bg-green-50 border border-green-200 px-3 py-1.5 text-sm text-green-800">
                            <span class="font-medium">{{ rooms.find(r => r.id === selectedRoomId)?.name }}</span>
                            <span class="text-green-600">{{ form.startTime }}〜{{ form.endTime }} を予約します</span>
                            <button type="button" class="ml-auto text-green-400 hover:text-green-700 text-base leading-none" @click="selectedRoomId = null">✕</button>
                        </div>
                    </div>

                    <!-- ボタン -->
                    <div class="flex justify-between pt-2">
                        <button v-if="isEdit" type="button"
                            class="rounded-md bg-red-50 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-100"
                            :disabled="loading"
                            @click="deleteEvent">削除</button>
                        <div v-else />
                        <div class="flex gap-2">
                            <button type="button"
                                class="rounded-md border border-gray-300 px-4 py-2 text-sm hover:bg-gray-50"
                                @click="$emit('close')">キャンセル</button>
                            <button type="submit"
                                class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60"
                                :disabled="loading">
                                {{ isEdit ? '更新' : '作成' }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>

</template>
