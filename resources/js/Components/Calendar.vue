<template>
    <div class="calendar-container">
        <div class="mb-4 flex flex-wrap gap-2">
            <button @click="openEventModal" class="rounded bg-blue-600 px-4 py-2 text-white">予定作成</button>
            <button @click="goToJobCreate" class="rounded bg-indigo-600 px-4 py-2 text-white">ジョブ作成</button>
            <button @click="goToDiaryCreate" class="rounded bg-orange-500 px-4 py-2 text-white">{{ props.diaryLabel }}作成</button>
            <button @click="openScheduleModal" class="rounded border border-gray-400 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">日程設定</button>
        </div>
        <FullCalendar ref="fullCalendarRef" :options="calendarOptions" :events="events" />
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
                    <button @click="goToJobCreate" class="rounded bg-blue-600 px-4 py-2 text-white">ジョブ作成</button>

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

        <!-- 週間日程設定モーダル -->
        <div v-if="showScheduleModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
            <div class="w-full max-w-sm rounded-lg bg-white p-6 shadow-lg">
                <h2 class="mb-4 text-lg font-bold">週間日程設定</h2>
                <p class="mb-3 text-xs text-gray-500">空白はデフォルト設定を使用します。</p>
                <table class="w-full text-sm">
                    <tbody>
                        <!-- 全日一括変更 -->
                        <tr class="border-b-2 border-gray-300 bg-gray-50">
                            <td class="py-2 pr-3 font-bold text-gray-800 w-24">全日</td>
                            <td class="py-2">
                                <select
                                    :value="null"
                                    @change="(e) => { const v = e.target.value ? Number(e.target.value) : null; weekDays.forEach(d => d.worktype_id = v); e.target.value = ''; }"
                                    class="w-full rounded border-gray-300 text-sm focus:border-blue-400 focus:ring-blue-400"
                                >
                                    <option value="">— 一括選択 —</option>
                                    <option :value="null">— デフォルト —</option>
                                    <option v-for="wt in worktypes" :key="wt.id" :value="wt.id">
                                        {{ wt.name }}
                                        <template v-if="wt.start_time"> ({{ wt.start_time.substring(0, 5) }}〜)</template>
                                    </option>
                                </select>
                            </td>
                        </tr>
                        <!-- 曜日ごと -->
                        <tr v-for="day in weekDays" :key="day.date" class="border-b last:border-0">
                            <td class="py-2 pr-3 font-medium text-gray-700 w-24">{{ day.label }}</td>
                            <td class="py-2">
                                <select
                                    v-model="day.worktype_id"
                                    class="w-full rounded border-gray-300 text-sm focus:border-blue-400 focus:ring-blue-400"
                                >
                                    <option :value="null">— デフォルト —</option>
                                    <option v-for="wt in worktypes" :key="wt.id" :value="wt.id">
                                        {{ wt.name }}
                                        <template v-if="wt.start_time"> ({{ wt.start_time.substring(0, 5) }}〜)</template>
                                    </option>
                                </select>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div class="mt-5 flex justify-end gap-3">
                    <button @click="showScheduleModal = false" class="rounded bg-gray-200 px-4 py-2 text-sm">キャンセル</button>
                    <button @click="saveWeekSchedule" :disabled="savingSchedule" class="rounded bg-blue-600 px-4 py-2 text-sm text-white disabled:opacity-50">
                        {{ savingSchedule ? '保存中…' : '保存' }}
                    </button>
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
import { computed, nextTick, onMounted, ref, watch } from 'vue';
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
    jobs: {
        type: Array,
        default: () => [],
    },
    initialView: {
        type: String,
        default: 'timeGridWeek',
    },
    defaultWorktype: {
        type: Object,
        default: null,
    },
    worktypes: {
        type: Array,
        default: () => [],
    },
    dailyWorktypes: {
        type: Array,
        default: () => [],
    },
});

// 日ごとの勤務形態マップ { 'YYYY-MM-DD': worktype_id }（ローカル更新可能）
const localDailyWorktypes = ref([...(props.dailyWorktypes ?? [])]);

const dailyWorktypeMap = computed(() => {
    const map = {};
    localDailyWorktypes.value.forEach((d) => {
        if (d.date) map[d.date] = d.worktype_id;
    });
    return map;
});

// 指定日の有効な勤務形態を返す（日次設定 > デフォルト）
function getWorktypeForDate(dateStr) {
    const wid = dailyWorktypeMap.value[dateStr];
    if (wid) {
        const wt = props.worktypes.find((w) => w.id === wid);
        if (wt) return wt;
    }
    return props.defaultWorktype ?? null;
}

// 夜勤判定: デフォルト勤務形態の start_time が 16:00 以降なら夜勤
const isNightShift = computed(() => {
    if (!props.defaultWorktype?.start_time) return false;
    const h = parseInt(props.defaultWorktype.start_time.substring(0, 2), 10);
    return h >= 16;
});

// スロット表示範囲
// 夜勤: 16:00 〜 30:00 (翌 06:00)、通常: 07:00 〜 24:00
const slotMinTime = computed(() => (isNightShift.value ? '16:00:00' : '07:00:00'));
const slotMaxTime = computed(() => (isNightShift.value ? '30:00:00' : '24:00:00'));

// 初期スクロール位置: 現在時刻の 1 時間前
// 夜勤かつ深夜 0〜6 時は FullCalendar の 24+h 表記に合わせる
const scrollTime = computed(() => {
    const now = new Date();
    let h = now.getHours();
    const m = now.getMinutes();
    if (isNightShift.value && h < 6) {
        h += 24; // 深夜帯は 24xx 表記
    }
    const scrollH = Math.max(isNightShift.value ? 16 : 7, h - 1);
    return `${String(scrollH).padStart(2, '0')}:${String(m).padStart(2, '0')}:00`;
});

// ---- 表示中の日付範囲（datesSet で更新） ----
const viewStart = ref(null); // Date
const viewEnd = ref(null);   // Date（exclusive）

// ---- 始業前グレー背景イベント ----
const backgroundEvents = computed(() => {
    if (!viewStart.value) return [];
    const results = [];
    const cur = new Date(viewStart.value);
    const end = viewEnd.value ? new Date(viewEnd.value) : new Date(viewStart.value);
    end.setDate(end.getDate() + 1);
    const slotMin = isNightShift.value ? 16 : 7;

    while (cur < end) {
        const dateStr =
            cur.getFullYear() +
            '-' + String(cur.getMonth() + 1).padStart(2, '0') +
            '-' + String(cur.getDate()).padStart(2, '0');
        const wt = getWorktypeForDate(dateStr);
        if (wt?.start_time) {
            const [sh, sm] = wt.start_time.split(':').map(Number);
            const startMins = sh * 60 + sm;
            if (startMins > slotMin * 60) {
                results.push({
                    start: `${dateStr}T${String(slotMin).padStart(2, '0')}:00:00`,
                    end: `${dateStr}T${wt.start_time.substring(0, 5)}:00`,
                    display: 'background',
                    color: 'rgba(0,0,0,0.08)',
                });
            }
        }
        cur.setDate(cur.getDate() + 1);
    }
    return results;
});

// ---- 週間日程設定モーダル ----
const showScheduleModal = ref(false);
const weekDays = ref([]);    // [{ date, label, worktype_id }]
const savingSchedule = ref(false);
const DAY_NAMES = ['月', '火', '水', '木', '金', '土', '日'];

function getMondayOfWeek(d) {
    const day = d.getDay(); // 0=Sun
    const diff = day === 0 ? -6 : 1 - day;
    const mon = new Date(d);
    mon.setDate(d.getDate() + diff);
    return mon;
}

function openScheduleModal() {
    const refDate = viewStart.value
        ? new Date(viewStart.value)
        : new Date(selectedDate.value);
    const monday = getMondayOfWeek(refDate);

    const days = [];
    for (let i = 0; i < 7; i++) {
        const d = new Date(monday);
        d.setDate(monday.getDate() + i);
        const dateStr =
            d.getFullYear() +
            '-' + String(d.getMonth() + 1).padStart(2, '0') +
            '-' + String(d.getDate()).padStart(2, '0');
        days.push({
            date: dateStr,
            label: `${d.getMonth() + 1}/${d.getDate()}(${DAY_NAMES[i]})`,
            worktype_id: dailyWorktypeMap.value[dateStr] ?? props.defaultWorktype?.id ?? null,
        });
    }
    weekDays.value = days;
    showScheduleModal.value = true;
}

async function saveWeekSchedule() {
    savingSchedule.value = true;
    try {
        await axios.post('/user/daily-worktypes', { days: weekDays.value });
        // ローカル状態を更新
        weekDays.value.forEach((day) => {
            const idx = localDailyWorktypes.value.findIndex((d) => d.date === day.date);
            if (!day.worktype_id) {
                if (idx >= 0) localDailyWorktypes.value.splice(idx, 1);
            } else if (idx >= 0) {
                localDailyWorktypes.value.splice(idx, 1, { date: day.date, worktype_id: day.worktype_id });
            } else {
                localDailyWorktypes.value.push({ date: day.date, worktype_id: day.worktype_id });
            }
        });
        showScheduleModal.value = false;
    } catch {
        alert('保存に失敗しました');
    } finally {
        savingSchedule.value = false;
    }
}

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

// clicked/dragged time range (used by select modal to prefill create)
const clickedStartHour = ref(null);
const clickedStartMinute = ref(null);
const clickedEndHour = ref(null);
const clickedEndMinute = ref(null);

// カレンダーで選択中の日付（初期値は今日）
const today = new Date();
const yyyy = today.getFullYear();
const mm = String(today.getMonth() + 1).padStart(2, '0');
const dd = String(today.getDate()).padStart(2, '0');
const selectedDate = ref(`${yyyy}-${mm}-${dd}`);
// If the page was opened with a ?date=YYYY-MM-DD query (or Inertia supplied it in the URL),
// prefer that as the initial selected date so links like "予定を編集" focus the correct day.
try {
    const params = new URLSearchParams(window.location.search);
    const qd = params.get('date');
    if (qd && typeof qd === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(qd)) {
        selectedDate.value = qd;
    }
} catch (e) {
    // ignore malformed URL or environments without window
}
// personal-only calendar: no schedule scoping
const selectedScheduleId = ref(null);

const startHourSelectRef = ref(null);
const endHourSelectRef = ref(null);
// Reference to FullCalendar component so we can programmatically navigate to dates
const fullCalendarRef = ref(null);

function goToAssignedJobs() {
    try {
        // Prefer named Ziggy route for JobBox index if available
        if (typeof route === 'function' && route().has && route().has('coordinator.jobbox')) {
            try {
                router.get(route('coordinator.jobbox'));
                return;
            } catch (e) {
                // fallthrough to fallback
            }
        }
    } catch (err) {
        // ignore
    }
    // fallback literal path
    router.get('/jobbox');
}

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

function buildDateTimeParams() {
    const params = { date: selectedDate.value };
    if (clickedStartHour.value !== null && clickedStartMinute.value !== null) {
        const hh = String(clickedStartHour.value).padStart(2, '0');
        const mm = String(clickedStartMinute.value).padStart(2, '0');
        params.startHour = hh;
        params.startMinute = mm;
        if (clickedEndHour.value !== null && clickedEndMinute.value !== null) {
            params.endHour = String(clickedEndHour.value).padStart(2, '0');
            params.endMinute = String(clickedEndMinute.value).padStart(2, '0');
        } else {
            params.endHour = String((Number(hh) + 1) % 24).padStart(2, '0');
            params.endMinute = mm;
        }
    }
    return params;
}

function openEventModal() {
    const params = buildDateTimeParams();
    // 選択中の日付をセットしてEvents/Create.vueへ遷移
    try {
        const current = window.location.pathname + window.location.search + window.location.hash;
        router.get(route('events.create', { ...params, return_to: current }));
        return;
    } catch (e) {
        router.get(route('events.create', params));
    }
}

function openEventModalFromSelect() {
    const params = buildDateTimeParams();
    try {
        const current = window.location.pathname + window.location.search + window.location.hash;
        router.get(route('events.create', { ...params, return_to: current }));
    } catch (e) {
        router.get(route('events.create', params));
    }
    showSelectModal.value = false;
    clickedStartHour.value = null;
    clickedStartMinute.value = null;
    clickedEndHour.value = null;
    clickedEndMinute.value = null;
}

function goToDiaryCreateFromSelect() {
    try {
        const current = window.location.pathname + window.location.search + window.location.hash;
        router.get(route('diaries.create', { date: selectedDate.value, return_to: current }));
    } catch (e) {
        router.get(route('diaries.create', { date: selectedDate.value }));
    }
    showSelectModal.value = false;
}

// 日付クリック時の遷移処理を削除

function goToDiaryCreate() {
    // 選択中の日付で作成画面へ遷移
    try {
        const current = window.location.pathname + window.location.search + window.location.hash;
        router.get(route('diaries.create', { date: selectedDate.value, return_to: current }));
    } catch (e) {
        router.get(route('diaries.create', { date: selectedDate.value }));
    }
}

function goToJobCreate() {
    showSelectModal.value = false;
    try {
        const params = buildDateTimeParams();
        try {
            router.get(route('events.create_job', params));
            return;
        } catch (e) {
            // fallback: open the generic event create page
            openEventModal();
            return;
        }
    } catch (e) {
        // fallback to existing events.create
        openEventModal();
    }
}

function handleDateSelect(selectionInfo) {
    // カレンダーで日付選択時に選択日を保持
    const dateStr = selectionInfo.startStr.split('T')[0];
    selectedDate.value = dateStr;
    selectedScheduleId.value = null;

    // ドラッグで時間範囲が選択されたとき start/end を抽出
    try {
        const startObj = selectionInfo.start;
        const endObj = selectionInfo.end;
        if (startObj && (startObj.getHours() !== 0 || selectionInfo.startStr.includes('T'))) {
            clickedStartHour.value = startObj.getHours();
            clickedStartMinute.value = startObj.getMinutes() < 30 ? 0 : 30;
        } else {
            clickedStartHour.value = null;
            clickedStartMinute.value = null;
        }
        if (endObj && (endObj.getHours() !== 0 || selectionInfo.endStr.includes('T'))) {
            clickedEndHour.value = endObj.getHours();
            clickedEndMinute.value = endObj.getMinutes() < 30 ? 0 : 30;
        } else {
            clickedEndHour.value = null;
            clickedEndMinute.value = null;
        }
    } catch (e) {
        clickedStartHour.value = null;
        clickedStartMinute.value = null;
        clickedEndHour.value = null;
        clickedEndMinute.value = null;
    }

    showSelectModal.value = true;
}

// Handle clicking a time slot or date cell. Snap minutes to 00 or 30 and open select modal.
function handleTimeSlotClick(info) {
    try {
        // info may be a Date or an object depending on FullCalendar hook
        let dateObj = null;
        if (info && info.date)
            dateObj = info.date; // dateClick provides { date, ... }
        else if (info && info.start)
            dateObj = info.start; // select provides start
        else if (info instanceof Date) dateObj = info;
        if (!dateObj) return;
        // convert to local YYYY-MM-DD and hours/minutes (avoid toISOString which shifts to UTC)
        const y = dateObj.getFullYear();
        const mo = String(dateObj.getMonth() + 1).padStart(2, '0');
        const da = String(dateObj.getDate()).padStart(2, '0');
        const dateOnly = `${y}-${mo}-${da}`;
        const h = dateObj.getHours();
        const m = dateObj.getMinutes();
        const snappedM = m < 30 ? 0 : 30;
        selectedDate.value = dateOnly;
        clickedStartHour.value = h;
        clickedStartMinute.value = snappedM;
        showSelectModal.value = true;
    } catch (e) {
        // ignore
    }
}

// project schedule flows removed from personal calendar

// 日報がある日をイベントとして表示（タイトルは●アイコン）
// Merge diaries, events, and assigned jobs into FullCalendar events
const baseEvents = ref([
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
        // If title starts with completion prefix, use dark yellow color
        const isCompleted = typeof event.title === 'string' && event.title.indexOf('【完了】') === 0;

        // Determine linkage id coming from server (canonical assignment id)
        const pjAssignmentId = event.extendedProps?.project_job_assignment_id ?? event.project_job_assignment_id ?? null;

        // If linkage id is not present, treat as a 'personal unlinked' event — use a distinctive color
        if (!pjAssignmentId) {
            return {
                title: event.title,
                start: event.start,
                end: event.end ?? undefined,
                allDay: event.allDay ?? false,
                // distinct color for user's own unlinked events. Default to a teal color that's different
                // from assignment-status colors (which are likely red/orange/blue). Use a muted teal: #1fb6b3
                color: event.color ?? '#1fb6b3',
                event_id: event.id,
                schedule_id: event.extendedProps?.schedule_id ?? event.schedule_id ?? undefined,
                description: event.description ?? event.extendedProps?.description ?? '',
            };
        }

        // default coloring path
        return {
            title: event.title,
            start: event.start,
            end: event.end ?? undefined,
            allDay: event.allDay ?? false,
            color: isCompleted ? '#b58900' : (event.color ?? `rgba(37,99,235,${alpha})`),
            event_id: event.id,
            schedule_id: event.extendedProps?.schedule_id ?? event.schedule_id ?? undefined,
            description: event.description ?? event.extendedProps?.description ?? '',
        };
    }),
    // Assigned jobs display removed per UX request: do not include props.jobs in calendar events
]);

// 通常イベント + 始業前背景イベントを結合
const allEvents = computed(() => [...baseEvents.value, ...backgroundEvents.value]);

// debug logs removed after investigation

const calendarOptions = computed(() => ({
    plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
    // choose initial view: if all events are all-day, use month grid so they are visible
    initialView: props.initialView,
    // initialDate allows FullCalendar to open on a specific day (YYYY-MM-DD)
    initialDate: selectedDate.value,
    events: allEvents.value,
    datesSet: function (info) {
        viewStart.value = info.start;
        viewEnd.value = info.end;
    },
    locale: 'ja',
    headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek,timeGridDay',
    },
    selectable: true,
    dateClick: handleTimeSlotClick,
    slotMinTime: slotMinTime.value,
    slotMaxTime: slotMaxTime.value,
    scrollTime: scrollTime.value,
    scrollTimeReset: false,
    firstDay: 1,
    weekText: '\u9031',
    dayHeaderFormat: { weekday: 'short' },
    // add just after dayHeaderFormat
    // dayHeaderContent: 月/日を表示。月ビューでは日付は表示しない。
    dayHeaderContent: function (arg) {
        // arg.date は Date、arg.text はロケールに沿った曜ラベル（例: "月"）
        const viewType = arg.view && arg.view.type ? String(arg.view.type) : '';
        const d = arg.date;
        const month = d ? d.getMonth() + 1 : '';
        const day = d ? d.getDate() : '';
        const md = month && day ? `${month}/${day}` : '';
        const weekdayText = arg.text || '';

        // 勤務形態名を取得（dayGridMonth では表示しない）
        let wtHtml = '';
        if (viewType !== 'dayGridMonth' && d) {
            const dateStr =
                d.getFullYear() +
                '-' + String(d.getMonth() + 1).padStart(2, '0') +
                '-' + String(d.getDate()).padStart(2, '0');
            const wt = getWorktypeForDate(dateStr);
            if (wt?.name) {
                wtHtml = `<div class="fc-day-worktype">${wt.name}</div>`;
            }
        }

        // 月表示（dayGridMonth）のときは日付 (md) を表示しない
        if (viewType === 'dayGridMonth') {
            return { html: `<div class="fc-day-header-bottom">${weekdayText}</div>` };
        }

        // それ以外のビューでは「12/1」(上段) + 曜日(下段) + 勤務形態(下段) を表示
        return {
            html: `<div class="fc-day-header-top">${md}</div><div class="fc-day-header-bottom">${weekdayText}</div>${wtHtml}`,
        };
    },
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
                // eventResize payload log removed
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
                // eventResize error log suppressed (keep error thrown)
                if (e.response && e.response.data) {
                    alert('予定の更新に失敗しました');
                    // API error detail log suppressed
                } else {
                    alert('予定の更新に失敗しました');
                }
                info.revert(); // 失敗時は元に戻す
            }
        } else {
            info.revert(); // キャンセル時は元に戻す
        }
    },
    eventClick: async function (info) {
        try {
            // If the clicked event is an all-day event, prefer navigating to the corresponding show page
            if (info.event.allDay) {
                if (info.event.extendedProps.diary_id) {
                    try {
                        router.get(route('diaries.show', { diary: info.event.extendedProps.diary_id }));
                    } catch (e) {
                        // Ziggy route may not be available in some contexts - fallback to a safe URL
                        window.location.href = `/diaries/${info.event.extendedProps.diary_id}`;
                    }
                    return;
                }

                if (info.event.extendedProps.event_id || info.event.extendedProps.id || info.event.id) {
                    // derive a best-effort event id from multiple possible locations
                    const evId =
                        info.event.extendedProps.event_id ||
                        info.event.extendedProps.id ||
                        info.event.id ||
                        (info.event._def && info.event._def.publicId) ||
                        null;
                    if (evId) {
                        // debug: print the derived id and fallback URL so developer can inspect in browser console
                        console.debug('Calendar: navigating to event show', {
                            evId,
                            fallback: `/events/${evId}`,
                            extendedProps: info.event.extendedProps,
                        });
                        try {
                            router.get(route('events.show', { event: evId }));
                        } catch (e) {
                            window.location.href = `/events/${evId}`;
                        }
                        return;
                    }
                }

                // Assigned job items: if the event has an explicit event id candidate, prefer navigating to that
                // (this covers cases where an assigned-job entry actually points to an event). Otherwise,
                // attempt existence probe on /events/:job_id and fall back to assigned-jobs.
                if (info.event.extendedProps.job_id) {
                    const jid = info.event.extendedProps.job_id;
                    // Check for explicit event id fields that may be present on the event
                    const explicitEvId =
                        (info.event.extendedProps && (info.event.extendedProps.event_id || info.event.extendedProps.id)) ||
                        info.event.id ||
                        (info.event._def && info.event._def.publicId) ||
                        null;
                    if (explicitEvId) {
                        try {
                            // Prefer navigating to the explicit event id
                            router.get(route('events.show', { event: explicitEvId }));
                        } catch (e) {
                            window.location.href = `/events/${explicitEvId}`;
                        }
                        return;
                    }
                    try {
                        // Quick existence probe: try HEAD first, then GET as a fallback if HEAD isn't supported.
                        let exists = false;
                        try {
                            const headResp = await fetch(`/events/${jid}`, { method: 'HEAD', credentials: 'same-origin' });
                            exists = headResp.ok;
                        } catch (headErr) {
                            exists = false;
                        }

                        if (!exists) {
                            try {
                                const getResp = await fetch(`/events/${jid}`, { method: 'GET', credentials: 'same-origin' });
                                exists = getResp.ok;
                            } catch (getErr) {
                                exists = false;
                            }
                        }

                        if (exists) {
                            try {
                                router.get(route('events.show', { event: jid }));
                            } catch (e) {
                                window.location.href = `/events/${jid}`;
                            }
                        } else {
                            try {
                                router.get(route('user.assigned-jobs.show', { assigned_job: jid }));
                            } catch (e) {
                                try {
                                    router.get(route('assigned-jobs.show', { id: jid }));
                                } catch (e2) {
                                    window.location.href = `/assigned-jobs/${jid}`;
                                }
                            }
                        }
                    } catch (outerErr) {
                        // On any probe/navigation error, fallback to assigned-jobs
                        try {
                            router.get(route('assigned-jobs.show', { id: jid }));
                        } catch (e) {
                            window.location.href = `/assigned-jobs/${jid}`;
                        }
                    }
                    return;
                }
            }

            // 日報ラベルクリック時のみ遷移（既存の挙動を保持）
            if (info.event.extendedProps.diary_id) {
                router.get(route('diaries.show', { diary: info.event.extendedProps.diary_id }));
            }
            // 予定ラベルクリック時はShow.vueへ遷移
            if ((info.event.extendedProps && (info.event.extendedProps.event_id || info.event.extendedProps.id)) || info.event.id) {
                const evId =
                    (info.event.extendedProps && (info.event.extendedProps.event_id || info.event.extendedProps.id)) ||
                    info.event.id ||
                    (info.event._def && info.event._def.publicId) ||
                    null;
                if (evId) {
                    // console.debug('Calendar: navigating to event show (non-allDay)', {
                    //     evId,
                    //     fallback: `/events/${evId}`,
                    //     extendedProps: info.event.extendedProps,
                    // });
                    try {
                        router.get(route('events.show', { event: evId }));
                    } catch (e) {
                        window.location.href = `/events/${evId}`;
                    }
                }
            }
            // project schedule clicks not handled by personal calendar
        } catch (err) {
            // swallow errors to avoid breaking the calendar UI
        }
    },
    select: handleDateSelect,
}));

// When selectedDate changes (including initial value set from query param), navigate FullCalendar to it.
watch(
    selectedDate,
    async (newDate) => {
        try {
            // Wait for calendar to mount
            await nextTick();
            const fc = fullCalendarRef.value && fullCalendarRef.value.getApi ? fullCalendarRef.value : null;
            if (fc && typeof fc.getApi === 'function') {
                const api = fc.getApi();
                if (api && typeof api.gotoDate === 'function') {
                    api.gotoDate(newDate);
                }
            }
        } catch (e) {
            // swallow errors to avoid breaking UI
            console.debug('Calendar: gotoDate failed', e);
        }
    },
    { immediate: true },
);

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
    // send start/end debug suppressed
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
/* 月表示時に曜日のみを中央寄せ表示する場合の補正 */
.fc .fc-col-header-cell .fc-day-header-bottom {
    display: block;
    text-align: center;
    /* 既存のスタイルと衝突しないように調整 */
}

/* 通常（非月ビュー）で日付（上段）をやや強調 */
.fc .fc-col-header-cell .fc-day-header-top {
    font-size: 0.95rem;
    font-weight: 700;
    line-height: 1;
    padding-bottom: 0.06rem;
    color: rgba(15, 23, 42, 0.95);
}
.fc .fc-col-header-cell .fc-day-header-bottom {
    font-size: 0.75rem;
    font-weight: 600;
    color: rgba(15, 23, 42, 0.7);
}
/* 勤務形態名（ヘッダー内） */
.fc .fc-col-header-cell .fc-day-worktype {
    font-size: 0.68rem;
    font-weight: 500;
    color: rgba(37, 99, 235, 0.85);
    line-height: 1.2;
    padding-top: 0.1rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
</style>
