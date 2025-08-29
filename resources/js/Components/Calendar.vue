<template>
    <div class="calendar-container">
        <div class="mb-4 flex gap-4">
            <button @click="openEventModal" class="rounded bg-blue-600 px-4 py-2 text-white">予定作成</button>
            <button @click="goToDiaryCreate" class="rounded bg-orange-500 px-4 py-2 text-white">{{ props.diaryLabel }}作成</button>
        </div>
        <FullCalendar :options="calendarOptions" :events="events" />
        <!-- 予定作成モーダル -->
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-lg">
                <h2 class="mb-4 text-lg font-bold">イベント追加</h2>
                <form @submit.prevent="submitEvent">
                    <!-- ...既存のイベントフォーム... -->
                    <div class="mb-2">
                        <label class="block text-sm font-medium">タイトル</label>
                        <input v-model="form.title" type="text" class="w-full rounded border p-2" required />
                    </div>
                    <div class="mb-2">
                        <label class="block text-sm font-medium">内容</label>
                        <textarea v-model="form.description" class="w-full rounded border p-2" rows="2"></textarea>
                    </div>
                    <div class="mb-2">
                        <label class="block text-sm font-medium">日付</label>
                        <div class="w-full rounded border bg-gray-100 p-2">{{ form.date }}</div>
                    </div>
                    <div class="mb-2">
                        <div class="flex items-center gap-6">
                            <div>
                                <label class="mb-1 block text-sm font-medium">開始時刻</label>
                                <div class="flex gap-2">
                                    <select v-model="form.startHour" class="w-20 rounded border p-1" ref="startHourSelectRef">
                                        <option v-for="h in 24" :key="h" :value="String(h - 1).padStart(2, '0')">
                                            {{ String(h - 1).padStart(2, '0') }}
                                        </option>
                                    </select>
                                    <select v-model="form.startMinute" class="w-20 rounded border p-1">
                                        <option v-for="m in [0, 15, 30, 45]" :key="m" :value="String(m).padStart(2, '0')">
                                            {{ String(m).padStart(2, '0') }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium">終了時刻</label>
                                <div class="flex gap-2">
                                    <select v-model="form.endHour" class="w-20 rounded border p-1" ref="endHourSelectRef">
                                        <option v-for="h in 24" :key="h" :value="String(h - 1).padStart(2, '0')">
                                            {{ String(h - 1).padStart(2, '0') }}
                                        </option>
                                    </select>
                                    <select v-model="form.endMinute" class="w-20 rounded border p-1">
                                        <option v-for="m in [0, 15, 30, 45]" :key="m" :value="String(m).padStart(2, '0')">
                                            {{ String(m).padStart(2, '0') }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 flex justify-end gap-2">
                        <button type="button" @click="showModal = false" class="rounded bg-gray-300 px-4 py-2">キャンセル</button>
                        <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-white">登録</button>
                    </div>
                </form>
            </div>
        </div>
        <!-- 日付クリック時の選択モーダル -->
        <div v-if="showSelectModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
            <div class="w-full max-w-xs rounded-lg bg-white p-6 text-center shadow-lg">
                <h2 class="mb-4 text-lg font-bold">{{ selectedDate }} の操作</h2>
                <div class="flex flex-col gap-4">
                    <button @click="openEventModalFromSelect" class="rounded bg-blue-600 px-4 py-2 text-white">予定作成</button>
                    <button v-if="selectedScheduleId === null" @click="goToDiaryCreateFromSelect" class="rounded bg-orange-500 px-4 py-2 text-white">
                        日報作成
                    </button>
                    <button v-else @click="goToScheduleMemoCreate(selectedScheduleId)" class="rounded bg-green-600 px-4 py-2 text-white">
                        メモ作成
                    </button>
                    <button @click="showSelectModal = false" class="rounded bg-gray-300 px-4 py-2">キャンセル</button>
                </div>
            </div>
        </div>

        <!-- schedule-specific UI removed — this Calendar is personal-only -->
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
    diaries: {
        type: Array,
        default: () => [],
    },
    events: {
        type: Array,
        default: () => [],
    },
    diaryLabel: {
        type: String,
        default: '日報',
    },
});

const showModal = ref(false);
const showSelectModal = ref(false);
const form = ref({
    title: '',
    description: '',
    startHour: '09',
    startMinute: '00',
    endHour: '10',
    endMinute: '00',
    date: '',
});

// カレンダーで選択中の日付（初期値は今日）
const today = new Date();
const yyyy = today.getFullYear();
const mm = String(today.getMonth() + 1).padStart(2, '0');
const dd = String(today.getDate()).padStart(2, '0');
const selectedDate = ref(`${yyyy}-${mm}-${dd}`);
// personal-only calendar: no schedule scoping
const selectedScheduleId = ref(null);

const startHourSelectRef = ref(null);
const endHourSelectRef = ref(null);

onMounted(() => {
    nextTick(() => {
        const now = new Date();
        const currentHour = String(now.getHours()).padStart(2, '0');
        // 開始時刻のselect
        if (startHourSelectRef.value) {
            const idx = Array.from(startHourSelectRef.value.options).findIndex((opt) => opt.value === currentHour);
            if (idx >= 0) startHourSelectRef.value.selectedIndex = idx;
        }
        // 終了時刻のselect（今回はデフォルト10時のまま）
    });
});

function openEventModal() {
    // 選択中の日付をセットしてEvents/Create.vueへ遷移
    router.get(route('events.create', { date: selectedDate.value }));
}

function openEventModalFromSelect() {
    router.get(route('events.create', { date: selectedDate.value }));
    showSelectModal.value = false;
}

function goToDiaryCreateFromSelect() {
    router.get(route('diaries.create', { date: selectedDate.value }));
    showSelectModal.value = false;
}

// 日付クリック時の遷移処理を削除

function goToDiaryCreate() {
    // 選択中の日付で作成画面へ遷移
    router.get(route('diaries.create', { date: selectedDate.value }));
}

function handleDateSelect(selectionInfo) {
    // カレンダーで日付選択時に選択日を保持
    const dateStr = selectionInfo.startStr.split('T')[0];
    selectedDate.value = dateStr;
    // reset schedule id when selecting a bare date - calendar can receive schedule context via props/events
    selectedScheduleId.value = null;
    // 予定・日報作成選択モーダルを表示
    showSelectModal.value = true;
}

// project schedule flows removed from personal calendar

// 日報がある日をイベントとして表示（タイトルは●アイコン）
const events = ref([
    // 日報（オレンジ）
    ...props.diaries.map((diary) => {
        // UTC→JST(+9h)変換
        const d = new Date(diary.date);
        d.setHours(d.getHours() + 9);
        const yyyy = d.getFullYear();
        const mm = String(d.getMonth() + 1).padStart(2, '0');
        const dd = String(d.getDate()).padStart(2, '0');
        return {
            title: `● ${props.diaryLabel}`,
            start: `${yyyy}-${mm}-${dd}`,
            allDay: true,
            color: '#f59e42',
            diary_id: diary.id,
        };
    }),
    // 予定（青）
    ...(props.events ?? []).map((event, idx, arr) => {
        // 重複数をカウント
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
        // 透明度計算（最大0.2まで薄くする）
        const alpha = Math.max(1 - overlapCount * 0.2, 0.2);
        return {
            title: event.title,
            start: event.start,
            end: event.end ?? undefined,
            // respect incoming allDay flag if provided
            allDay: event.allDay ?? false,
            color: event.color ?? `rgba(37,99,235,${alpha})`,
            event_id: event.id,
            // ProjectSchedule mapping: preserve schedule_id if provided
            schedule_id: event.extendedProps?.schedule_id ?? event.schedule_id ?? undefined,
            description: event.description ?? event.extendedProps?.description ?? '',
        };
    }),
]);

console.log('Calendar.vue events for FullCalendar:', events.value);

const calendarOptions = computed(() => ({
    plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
    // choose initial view: if all events are all-day, use month grid so they are visible
    initialView: events.value && events.value.length > 0 && events.value.every((ev) => ev.allDay) ? 'dayGridMonth' : 'timeGridWeek',
    events: events.value,
    locale: 'ja',
    headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek,timeGridDay',
    },
    selectable: true,
    // show time slots starting at 07:00 and use 30-minute intervals for consistent labels
    slotMinTime: '07:00:00',
    slotMaxTime: '24:00:00',
    firstDay: 1,
    weekText: '\u9031',
    dayHeaderFormat: { weekday: 'short' },
    // keep internal grid at 15-minute increments but show labels every 30 minutes
    slotDuration: '00:15:00',
    slotLabelInterval: '00:30:00',
    // force two-digit hour/minute labels (e.g. 09:00, 09:30)
    slotLabelFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
    // ensure the calendar has enough height so time slots aren't cramped
    height: 720,
    editable: true, // イベントのドラッグ・リサイズを有効化
    eventDurationEditable: true,
    eventResizableFromStart: true,
    eventResize: async function (info) {
        const newStart = info.event.start;
        const newEnd = info.event.end;
        // Prefer FullCalendar provided ISO strings to avoid TZ-shift issues
        const startStr = info.event.startStr || (newStart ? newStart.toISOString() : null);
        const endStr = info.event.endStr || (newEnd ? newEnd.toISOString() : null);
        // derive display dates (for allDay, endStr is exclusive -> subtract 1 day)
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
        // For non-allDay show times, otherwise just dates
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
                // バリデーション用: タイトル・説明が空やタグのみの場合はダミー値をセット
                function stripTags(str) {
                    return str ? str.replace(/<[^>]*>?/gm, '') : '';
                }
                const safeTitle = info.event.title && stripTags(info.event.title).trim() !== '' ? info.event.title : 'タイトル未設定';
                const safeDescription =
                    info.event.extendedProps.description && stripTags(info.event.extendedProps.description).trim() !== ''
                        ? info.event.extendedProps.description
                        : '内容未設定';
                // Build a safe log payload depending on allDay or timed event
                let logPayload = {};
                if (info.event.allDay) {
                    logPayload = {
                        start_date: displayStart,
                        end_date: displayEndInclusive || displayStart,
                        title: safeTitle,
                        description: safeDescription,
                    };
                } else {
                    // compute time parts for timed events
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
                // update personal event via events endpoint
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
                info.revert(); // 失敗時は元に戻す
            }
        } else {
            info.revert(); // キャンセル時は元に戻す
        }
    },
    eventClick: function (info) {
        // 日報ラベルクリック時のみ遷移
        if (info.event.extendedProps.diary_id) {
            router.get(route('diaries.show', { diary: info.event.extendedProps.diary_id }));
        }
        // 予定ラベルクリック時はShow.vueへ遷移
        if (info.event.extendedProps.event_id) {
            router.get(route('events.show', { event: info.event.extendedProps.event_id }));
        }
        // project schedule clicks not handled by personal calendar
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

async function submitScheduleMemo() {
    if (!selectedScheduleIdForMemo.value) {
        alert('スケジュールが選択されていません');
        return;
    }
    if (!memoBody.value || memoBody.value.trim() === '') {
        alert('メモの内容を入力してください');
        return;
    }
    try {
        await axios.post(route('coordinator.project_schedule_comments.store', { project_schedule: selectedScheduleIdForMemo.value }), {
            body: memoBody.value,
        });
        showMemoModal.value = false;
        memoBody.value = '';
        // Optional: navigate back to schedule show to reflect new comment
        router.get(route('coordinator.project_schedules.show', { project_schedule: selectedScheduleIdForMemo.value }));
    } catch (e) {
        console.error('submitScheduleMemo error', e);
        alert('メモの保存に失敗しました');
    }
}

const submitEvent = async () => {
    const start = `${form.value.date} ${form.value.startHour}:${form.value.startMinute}:00`;
    const end = `${form.value.date} ${form.value.endHour}:${form.value.endMinute}:00`;
    console.log('送信start:', start, '送信end:', end);
    try {
        await axios.post('/events', {
            title: form.value.title,
            description: form.value.description,
            start,
            end,
        });
        showModal.value = false;
        // カレンダーに即時反映（青色ラベル）
        events.value.push({
            title: form.value.title,
            start,
            end,
            color: '#2563eb', // 青色
            allDay: false,
        });
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
