<template>
    <div class="calendar-container">
        <div class="mb-4 flex gap-4">
            <button @click="openEventModal" class="rounded bg-blue-600 px-4 py-2 text-white">予定作成</button>
            <button @click="goToDiaryCreate" class="rounded bg-orange-500 px-4 py-2 text-white">メモ作成</button>
        </div>
        <FullCalendar :options="calendarOptions" :events="calendarEvents" />

        <!-- 日付クリックは直接メモモーダルを開く（select modal を廃止） -->

        <!-- スケジュールをクリックしたときのアクションモーダル（表示 / メモ作成） -->
        <div v-if="showScheduleActionModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
            <div class="w-full max-w-xs rounded-lg bg-white p-6 text-center shadow-lg">
                <h2 class="mb-4 text-lg font-bold">スケジュール操作</h2>
                <div class="mb-4">選択中のスケジュール ID: {{ selectedScheduleForAction }}</div>
                <div class="flex flex-col gap-3">
                    <button @click="goToScheduleShowFromAction" class="rounded bg-blue-600 px-4 py-2 text-white">スケジュール表示</button>
                    <button @click="openMemoModalFromAction" class="rounded bg-green-600 px-4 py-2 text-white">メモ作成</button>
                    <button @click="showScheduleActionModal = false" class="rounded bg-gray-300 px-4 py-2">キャンセル</button>
                </div>
            </div>
        </div>

        <!-- スケジュール用メモ作成モーダル（日時 + テキストのみ） -->
        <div v-if="showMemoModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-lg">
                <h2 class="mb-4 text-lg font-bold">メモ作成</h2>
                <div v-if="selectedScheduleIdForMemo" class="mb-2 text-sm text-gray-600">スケジュールID: {{ selectedScheduleIdForMemo }}</div>
                <div class="mb-2">
                    <label class="block text-sm font-medium">日付</label>
                    <div class="flex items-center gap-2">
                        <input type="date" v-model="memoDate" class="rounded border p-2" />
                    </div>
                </div>
                <div class="mb-2">
                    <label class="block text-sm font-medium">メモ</label>
                    <textarea v-model="memoBody" class="w-full rounded border p-2" rows="6"></textarea>
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" @click="showMemoModal = false" class="rounded bg-gray-300 px-4 py-2">キャンセル</button>
                    <button type="button" @click="submitScheduleMemo" class="rounded bg-green-600 px-4 py-2 text-white">保存</button>
                </div>
            </div>
        </div>

        <!-- コメント編集モーダル -->
        <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-lg">
                <h2 class="mb-4 text-lg font-bold">メモ編集</h2>
                <div class="mb-2 text-sm text-gray-600">コメントID: {{ editingCommentId }}</div>
                <div class="mb-2">
                    <label class="block text-sm font-medium">日付</label>
                    <input type="date" v-model="editingCommentDate" class="rounded border p-2" />
                </div>
                <div class="mb-2">
                    <label class="block text-sm font-medium">メモ</label>
                    <textarea v-model="editingCommentBody" class="w-full rounded border p-2" rows="6"></textarea>
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" @click="showEditModal = false" class="rounded bg-gray-300 px-4 py-2">キャンセル</button>
                    <button type="button" @click="submitEditComment" class="rounded bg-blue-600 px-4 py-2 text-white">更新</button>
                    <!-- Show delete only when the editing id corresponds to a project memo (server or local) -->
                    <button
                        v-if="(props.memos || []).some((m) => m.id === editingCommentId) || (localMemos || []).some((m) => m.id === editingCommentId)"
                        type="button"
                        @click="deleteEditingMemo"
                        class="rounded bg-red-600 px-4 py-2 text-white"
                    >
                        削除
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import timeGridPlugin from '@fullcalendar/timegrid';
import FullCalendar from '@fullcalendar/vue3';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, nextTick, onMounted, ref } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps({
    diaries: { type: Array, default: () => [] },
    events: { type: Array, default: () => [] },
    comments: { type: Array, default: () => [] },
    memos: { type: Array, default: () => [] },
    project: { type: Object, default: null },
    diaryLabel: { type: String, default: 'メモ' },
});

const showModal = ref(false);
const form = ref({ title: '', description: '', startHour: '09', startMinute: '00', endHour: '10', endMinute: '00', date: '' });
const today = new Date();
const yyyy = today.getFullYear();
const mm = String(today.getMonth() + 1).padStart(2, '0');
const dd = String(today.getDate()).padStart(2, '0');
const selectedDate = ref(`${yyyy}-${mm}-${dd}`);
const selectedScheduleId = ref(null);
// schedule action UI
const showScheduleActionModal = ref(false);
const selectedScheduleForAction = ref(null);
const showMemoModal = ref(false);
const selectedScheduleIdForMemo = ref(null);
const memoBody = ref('');
const memoDate = ref(`${yyyy}-${mm}-${dd}`);

const showEditModal = ref(false);
const editingCommentId = ref(null);
const editingCommentBody = ref('');
const editingCommentDate = ref('');

const startHourSelectRef = ref(null);
const endHourSelectRef = ref(null);
const memoDateRef = ref(null);

onMounted(() => {
    nextTick(() => {
        const now = new Date();
        const currentHour = String(now.getHours()).padStart(2, '0');
        if (startHourSelectRef.value) {
            const idx = Array.from(startHourSelectRef.value.options).findIndex((opt) => opt.value === currentHour);
            if (idx >= 0) startHourSelectRef.value.selectedIndex = idx;
        }
    });
});

function openEventModal() {
    router.get(route('events.create', { date: selectedDate.value }));
}
function openEventModalFromSelect() {
    router.get(route('events.create', { date: selectedDate.value }));
}
function goToDiaryCreate() {
    // トップの「メモ作成」は今日の日付でモーダルを開く
    memoDate.value = `${yyyy}-${mm}-${dd}`;
    selectedScheduleIdForMemo.value = null;
    memoBody.value = '';
    showMemoModal.value = true;
}
function handleDateSelect(selectionInfo) {
    // カレンダーの日付クリックでは即メモモーダルを開く（その日付をセット）
    const dateStr = selectionInfo.startStr.split('T')[0];
    selectedDate.value = dateStr;
    memoDate.value = dateStr;
    selectedScheduleIdForMemo.value = null;
    memoBody.value = '';
    showMemoModal.value = true;
}
function goToScheduleMemoCreate(scheduleId) {
    // open schedule memo creation page as fallback (not modal)
    router.get(route('coordinator.project_schedule_comments.create', { project_schedule: scheduleId }));
}

function focusMemoDate() {
    if (memoDateRef.value) memoDateRef.value.showPicker ? memoDateRef.value.showPicker() : memoDateRef.value.focus();
}

// local memos created client-side to show immediately and merge with server props
const localMemos = ref([]);

const calendarEvents = computed(() => {
    const list = [];
    // scheduled events from props.events
    (props.events ?? []).forEach((event, idx, arr) => {
        let overlapCount = 0;
        if (event.start && event.end) {
            const evStart = new Date(event.start).getTime();
            const evEnd = new Date(event.end).getTime();
            overlapCount = arr.filter((ev, i) => {
                if (i === idx) return false;
                if (!ev.start || !ev.end) return false;
                const s = new Date(ev.start).getTime();
                const e = new Date(ev.end).getTime();
                return evStart < e && evEnd > s;
            }).length;
        }
        const alpha = Math.max(1 - overlapCount * 0.2, 0.2);
        list.push({
            title: event.title,
            start: event.start,
            end: event.end ?? undefined,
            allDay: event.allDay ?? false,
            color: event.color ?? `rgba(37,99,235,${alpha})`,
            event_id: event.id,
            description: event.description ?? event.extendedProps?.description ?? '',
        });
    });

    // comments
    (props.comments ?? []).forEach((c) => {
        if (!c.date) return;
        list.push({
            title: '🗒️',
            start: c.date,
            allDay: true,
            color: '#f59e42',
            extendedProps: { comment_id: c.id, project_schedule_id: c.project_schedule_id, body: c.body },
        });
    });

    // server memos
    (props.memos ?? []).forEach((m) => {
        if (!m.date) return;
        list.push({
            title: '📝',
            start: m.date,
            allDay: true,
            color: '#60a5fa',
            extendedProps: { memo_id: m.id, project_id: m.project_id, body: m.body },
        });
    });

    // local client-created memos (avoid duplicates by id)
    localMemos.value.forEach((m) => {
        if (!m.date) return;
        // don't duplicate if server already returned same memo id
        const exists = list.some((ev) => ev.extendedProps && ev.extendedProps.memo_id === m.id);
        if (!exists) {
            list.push({
                title: '📝',
                start: m.date,
                allDay: true,
                color: '#60a5fa',
                extendedProps: { memo_id: m.id, project_id: m.project_id, body: m.body },
            });
        }
    });

    return list;
});

// Modify calendarOptions to attach eventDidMount for native tooltip
const calendarOptions = computed(() => ({
    plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
    // Use merged calendarEvents (server+comments+memos+local) to decide view
    initialView:
        calendarEvents.value && calendarEvents.value.length > 0 && calendarEvents.value.every((ev) => ev.allDay) ? 'dayGridMonth' : 'timeGridWeek',
    events: calendarEvents.value,
    locale: 'ja',
    headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek' },
    selectable: true,
    slotMinTime: '07:00:00',
    slotMaxTime: '24:00:00',
    firstDay: 1,
    weekText: '\u9031',
    dayHeaderFormat: { weekday: 'short' },
    slotDuration: '00:15:00',
    slotLabelInterval: '00:30:00',
    slotLabelFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
    height: 720,
    editable: true,
    eventDurationEditable: true,
    eventResizableFromStart: true,
    eventResize: async function (info) {
        const newStart = info.event.start;
        const newEnd = info.event.end;
        const startStr = info.event.startStr || (newStart ? newStart.toISOString() : null);
        const endStr = info.event.endStr || (newEnd ? newEnd.toISOString() : null);
        const fmtDateOnly = (iso) => (iso ? String(iso).split('T')[0] : null);
        const displayStart = fmtDateOnly(startStr);
        let displayEndInclusive = null;
        if (endStr) {
            const endDateOnly = fmtDateOnly(endStr);
            if (info.event.allDay) {
                const d = new Date(endDateOnly);
                d.setDate(d.getDate() - 1);
                displayEndInclusive = d.toISOString().split('T')[0];
            } else {
                displayEndInclusive = endDateOnly;
            }
        }
        let confirmMessage = '';
        if (info.event.allDay) {
            confirmMessage = `予定を変更しますか？\n開始: ${displayStart}\n終了: ${displayEndInclusive || displayStart}`;
        } else {
            const startDateObj = new Date(newStart);
            const endDateObj = new Date(newEnd);
            const date = startDateObj.toISOString().slice(0, 10);
            const startHour = String(startDateObj.getHours()).padStart(2, '0');
            const startMinute = String(startDateObj.getMinutes()).padStart(2, '0');
            const endHour = String(endDateObj.getHours()).padStart(2, '0');
            const endMinute = String(endDateObj.getMinutes()).padStart(2, '0');
            confirmMessage = `予定の時間を変更しますか？\n開始: ${date} ${startHour}:${startMinute}\n終了: ${date} ${endHour}:${endMinute}`;
        }
        if (confirm(confirmMessage)) {
            try {
                function stripTags(str) {
                    return str ? str.replace(/<[^>]*>?/gm, '') : '';
                }
                const safeTitle = info.event.title && stripTags(info.event.title).trim() !== '' ? info.event.title : 'タイトル未設定';
                const safeDescription =
                    info.event.extendedProps.description && stripTags(info.event.extendedProps.description).trim() !== ''
                        ? info.event.extendedProps.description
                        : '内容未設定';
                let logPayload = {};
                if (info.event.allDay) {
                    logPayload = {
                        start_date: displayStart,
                        end_date: displayEndInclusive || displayStart,
                        title: safeTitle,
                        description: safeDescription,
                    };
                } else {
                    const startDateObj2 = new Date(newStart);
                    const endDateObj2 = new Date(newEnd);
                    const date2 = startDateObj2.toISOString().slice(0, 10);
                    const startHour2 = String(startDateObj2.getHours()).padStart(2, '0');
                    const startMinute2 = String(startDateObj2.getMinutes()).padStart(2, '0');
                    const endHour2 = String(endDateObj2.getHours()).padStart(2, '0');
                    const endMinute2 = String(endDateObj2.getMinutes()).padStart(2, '0');
                    logPayload = {
                        date: date2,
                        startHour: startHour2,
                        startMinute: startMinute2,
                        endHour: endHour2,
                        endMinute: endMinute2,
                        title: safeTitle,
                        description: safeDescription,
                    };
                }
                console.log('eventResize送信データ', logPayload);
                // Only update generic events for personal calendar
                await axios.put(`/events/${info.event.extendedProps.event_id}/calendar`, {
                    date: displayStart,
                    startHour: newStart ? String(newStart.getHours()).padStart(2, '0') : undefined,
                    startMinute: newStart ? String(newStart.getMinutes()).padStart(2, '0') : undefined,
                    endHour: newEnd ? String(newEnd.getHours()).padStart(2, '0') : undefined,
                    endMinute: newEnd ? String(newEnd.getMinutes()).padStart(2, '0') : undefined,
                });
                alert('予定を更新しました');
            } catch (e) {
                console.log('eventResize error:', e);
                if (e.response && e.response.data) {
                    alert('予定の更新に失敗しました');
                    console.log('API error detail:', e.response.data);
                } else {
                    alert('予定の更新に失敗しました');
                }
                info.revert();
            }
        } else {
            info.revert();
        }
    },
    eventDidMount: function (info) {
        // Attach native tooltip showing comment body when present
        try {
            if (info.event.extendedProps && info.event.extendedProps.body) {
                info.el.setAttribute('title', info.event.extendedProps.body);
                // also set cursor pointer for visual affordance
                info.el.style.cursor = 'pointer';
            }
        } catch (e) {
            // ignore
        }
    },
    eventClick: function (info) {
        // comment click -> open edit modal for comment
        if (info.event.extendedProps.comment_id) {
            // Prevent navigation; open inline modal
            // Find comment data from props.comments
            const cid = info.event.extendedProps.comment_id;
            const comment = (props.comments || []).find((c) => c.id === cid) || {
                id: cid,
                body: info.event.extendedProps.body || '',
                date: info.event.startStr ? info.event.startStr.split('T')[0] : info.event.start,
            };
            openEditModalForComment(comment);
            return;
        }
        // memo click -> open edit modal for project memo
        if (info.event.extendedProps.memo_id) {
            const mid = info.event.extendedProps.memo_id;
            const memo = (props.memos || []).find((m) => m.id === mid) ||
                (localMemos.value || []).find((m) => m.id === mid) || {
                    id: mid,
                    body: info.event.extendedProps.body || '',
                    date: info.event.startStr ? info.event.startStr.split('T')[0] : info.event.start,
                };
            openEditModalForComment({ id: memo.id, body: memo.body, date: memo.date });
            return;
        }
        if (info.event.extendedProps.event_id) {
            router.get(route('events.show', { event: info.event.extendedProps.event_id }));
        }
    },
    select: handleDateSelect,
}));

function goToScheduleShowFromAction() {
    if (!selectedScheduleForAction.value) return;
    showScheduleActionModal.value = false;
    router.get(route('coordinator.project_schedules.show', { project_schedule: selectedScheduleForAction.value }));
}

function openMemoModalFromAction() {
    if (!selectedScheduleForAction.value) return;
    selectedScheduleIdForMemo.value = selectedScheduleForAction.value;
    showScheduleActionModal.value = false;
    showMemoModal.value = true;
}

function openEditModalForComment(comment) {
    editingCommentId.value = comment.id;
    editingCommentBody.value = comment.body || '';
    editingCommentDate.value = comment.date || memoDate.value;
    showEditModal.value = true;
}

async function submitEditComment() {
    if (!editingCommentBody.value || editingCommentBody.value.trim() === '') {
        alert('メモの内容を入力してください');
        return;
    }
    try {
        // Try to call named route via ziggy if available, otherwise fallback to URL
        const url = route('coordinator.project_schedule_comments.update', { comment: editingCommentId.value });
        await axios.put(url, {
            body: editingCommentBody.value,
            metadata: { date: editingCommentDate.value },
        });
        // After successful update, reload page or re-fetch via Inertia to refresh props/events
        // (Avoid mutating undefined local `events` variable)
        router.reload();
        showEditModal.value = false;
        editingCommentId.value = null;
        // Optionally refresh page or keep local state
    } catch (e) {
        console.error('submitEditComment error', e);
        alert('メモの更新に失敗しました');
    }
}

async function deleteEditingMemo() {
    if (!editingCommentId.value) return;
    if (!confirm('このメモを削除しますか？')) return;
    try {
        // call destroy via explicit URL to avoid Ziggy resolving to coordinator-prefixed route
        const url = `/project_memos/${editingCommentId.value}`;
        await axios.delete(url);
        showEditModal.value = false;
        editingCommentId.value = null;
        // refresh to get server-provided memos
        router.reload();
    } catch (e) {
        console.error('deleteEditingMemo error', e);
        alert('メモの削除に失敗しました');
    }
}

async function submitScheduleMemo() {
    // If a schedule id is set, post to the schedule comments store
    if (selectedScheduleIdForMemo.value) {
        if (!memoBody.value || memoBody.value.trim() === '') {
            alert('メモの内容を入力してください');
            return;
        }
        try {
            await axios.post(route('coordinator.project_schedule_comments.store', { project_schedule: selectedScheduleIdForMemo.value }), {
                body: memoBody.value,
                metadata: { date: memoDate.value },
            });
            showMemoModal.value = false;
            memoBody.value = '';
            router.get(route('coordinator.project_schedules.show', { project_schedule: selectedScheduleIdForMemo.value }));
        } catch (e) {
            console.error('submitScheduleMemo error', e);
            alert('メモの保存に失敗しました');
        }
        return;
    }
    // No schedule id: create a project-level memo (date-based note)
    try {
        const payload = {
            project_id: props.project ? props.project.id : null,
            date: memoDate.value,
            body: memoBody.value,
        };
        const resp = await axios.post(route('coordinator.project_memos.store'), payload);
        // update local events with returned memo
        if (resp && resp.data && resp.data.memo) {
            const m = resp.data.memo;
            localMemos.value.push({ id: m.id, project_id: m.project_id, date: m.date, body: m.body });
        }
        showMemoModal.value = false;
        memoBody.value = '';
    } catch (e) {
        console.error('submitScheduleMemo (project memo) error', e);
        alert('メモの保存に失敗しました');
    }
}

const submitEvent = async () => {
    const start = `${form.value.date} ${form.value.startHour}:${form.value.startMinute}:00`;
    const end = `${form.value.date} ${form.value.endHour}:${form.value.endMinute}:00`;
    try {
        await axios.post('/events', { title: form.value.title, description: form.value.description, start, end });
        showModal.value = false;
        // Refresh via Inertia to obtain server-authoritative events
        router.reload();
    } catch (e) {
        if (e.response && e.response.data && e.response.data.errors) {
            const messages = Object.values(e.response.data.errors).flat().join('\n');
            alert('登録に失敗しました:\n' + messages);
        } else {
            alert('登録に失敗しました');
        }
    }
};
</script>

<style scoped>
.calendar-container {
    padding: 1rem;
}

/* FullCalendarの基本スタイル */
.fc {
    font-family: inherit;
}

.fc-button {
    background-color: #3b82f6;
    border-color: #3b82f6;
    color: white;
}

.fc-button:hover {
    background-color: #2563eb;
    border-color: #2563eb;
}

.fc-daygrid-day {
    cursor: pointer;
}

.fc-daygrid-day:hover {
    background-color: #f3f4f6;
}

/* emphasize hour boundaries (every 4 slots when slotDuration is 15min => 60min) */
.fc .fc-timegrid .fc-scrollgrid .fc-timegrid-slot-lane tr:nth-child(4n) td {
    border-top: 4px solid rgba(15, 23, 42, 0.22) !important;
    /* add slight shadow so the separator stands out against white */
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.6);
}

/* emphasize labels at hour marks and make them two-digit bold (e.g. 09:00) */
.fc .fc-timegrid .fc-timegrid-slot-labels tr:nth-child(4n) td {
    border-top: 4px solid rgba(15, 23, 42, 0.28) !important;
    font-weight: 800;
    color: rgba(15, 23, 42, 0.98);
    background-color: rgba(15, 23, 42, 0.03);
    /* keep label visually aligned by adding small padding */
    padding-top: 4px !important;
}

/* give each slot a bit more vertical padding to avoid cramped appearance */
.fc .fc-timegrid .fc-scrollgrid .fc-timegrid-slot-lane td {
    padding-top: 6px;
    padding-bottom: 6px;
}
</style>
