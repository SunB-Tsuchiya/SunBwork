<script setup>
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import CalendarShell    from '@/Components/Schedule/CalendarShell.vue';
import MonthView        from '@/Components/Schedule/MonthView.vue';
import WeekView         from '@/Components/Schedule/WeekView.vue';
import EventModal       from '@/Components/Schedule/EventModal.vue';
import EventDetailModal from '@/Components/Schedule/EventDetailModal.vue';
import ActionSheet      from '@/Components/Calendar/ActionSheet.vue';
import UserDayView      from '@/Components/Calendar/UserDayView.vue';
import { useCalendarCore } from '@/Components/Schedule/useCalendarCore.js';

const props = defineProps({
    eventItemTypes:     { type: Array,  default: () => [] },
    meetingDefinitions: { type: Array,  default: () => [] },
    rooms:              { type: Array,  default: () => [] },
    companies:          { type: Array,  default: () => [] },
    departments:        { type: Array,  default: () => [] },
    worktypes:          { type: Array,  default: () => [] },
    dailyWorktypes:     { type: Array,  default: () => [] },
    dailyBreaks:        { type: Array,  default: () => [] },
    defaultBreak:       { type: Object, default: () => ({ start: '12:00', end: '13:00' }) },
    defaultWorktype:    { type: Object, default: null },
});

const CSRF = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

// ── ナビゲーション（共通コンポーザブル） ─────────────────────
const { viewMode, currentDate, weekStart, viewYear, viewMonth, viewLabel, navigate, goToday, loadRange } =
    useCalendarCore({ storageKey: 'user_calendar_view_mode' });

// ── イベントデータ ─────────────────────────────────────────────
const companyEvents  = ref([]); // Schedule と共有（is_company_event=true）
const personalEvents = ref([]); // 個人専用（ジョブ・旧カレンダーイベント）
const diaryDates     = ref([]); // 日報マーカー [{ id, date: 'YYYY-MM-DD' }]
const loading        = ref(false);

// 日報マーカーをイベント形式に変換（MonthView 表示用）
const diaryEvents = computed(() =>
    diaryDates.value.map(d => ({
        id:          `diary-${d.id}`,
        title:       '● 日報',
        starts_at:   `${d.date}T08:00:00`,
        ends_at:     `${d.date}T08:15:00`,
        is_own:      true,
        _custom_color: '#f59e42',
        _is_diary:   true,
        _diary_id:   d.id,
        _diary_date: d.date,
    }))
);

// MonthView/WeekView/DayView に渡すイベント統合
const allEvents = computed(() => [
    ...companyEvents.value,
    ...personalEvents.value,
    ...diaryEvents.value,
]);

// WeekView/DayView では日報マーカーを除外（時刻グリッドでは不要）
const timedEvents = computed(() => allEvents.value.filter(e => !e._is_diary));

async function loadEvents() {
    loading.value = true;
    try {
        const [companyRes, personalRes] = await Promise.all([
            axios.get(route('schedule.events.range'), {
                params: { start: loadRange.value.start, end: loadRange.value.end },
            }),
            axios.get(route('calendar.events.range'), {
                params: { start: loadRange.value.start, end: loadRange.value.end },
            }),
        ]);
        companyEvents.value  = companyRes.data.events       ?? companyRes.data;
        personalEvents.value = personalRes.data.events      ?? [];
        diaryDates.value     = personalRes.data.diaries     ?? [];
    } catch (e) {
        console.error('カレンダーイベント取得失敗', e);
    } finally {
        loading.value = false;
    }
}

watch(loadRange, loadEvents, { immediate: true });

// ── ActionSheet ───────────────────────────────────────────────
const showActionSheet = ref(false);
const actionSheetDef  = ref({ date: '', startMin: null, endMin: null });

function openActionSheet({ date, startMin = null, endMin = null } = {}) {
    actionSheetDef.value = { date: date || currentDate.value, startMin, endMin };
    showActionSheet.value = true;
}

function onActionSheetClose() { showActionSheet.value = false; }

function onActionAddEvent() {
    showActionSheet.value = false;
    openCreate(actionSheetDef.value);
}

// minutes → { startHour, startMinute } / { endHour, endMinute } 変換ヘルパー
function minToParams(startMin, endMin) {
    const p = {};
    if (startMin != null) {
        p.startHour   = String(Math.floor(startMin / 60)).padStart(2, '0');
        p.startMinute = String(startMin % 60).padStart(2, '0');
    }
    if (endMin != null) {
        p.endHour   = String(Math.floor(endMin / 60)).padStart(2, '0');
        p.endMinute = String(endMin % 60).padStart(2, '0');
    }
    return p;
}

function onActionMyJob() {
    showActionSheet.value = false;
    const { date, startMin, endMin } = actionSheetDef.value;
    router.get(route('events.create_job', { date, ...minToParams(startMin, endMin) }));
}

function onActionSheetJob() {
    showActionSheet.value = false;
    openJobSheetModal();
}

function onActionDiary() {
    showActionSheet.value = false;
    router.get(route('diaries.create', { date: actionSheetDef.value.date }));
}

// ── Schedule EventModal（案件打合せ・外出 / 社内予定） ─────────
const showCreate   = ref(false);
const editTarget   = ref(null);
const showDetail   = ref(false);
const detailTarget = ref(null);
const createDef    = ref({ date: '', startMin: null, endMin: null });

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
    if (ev._is_diary) {
        goToDiary(ev._diary_date);
        return;
    }
    if (ev._source === 'personal') {
        // ジョブ連動イベント → 種別に応じた詳細ページへ遷移
        if (ev.project_job_assignment_id) {
            try {
                if (ev._is_self_assigned) {
                    // マイジョブ（自己割当）→ MyJobBox 詳細
                    router.get(route('user.myjobbox.show', { assignment: ev.project_job_assignment_id }));
                } else if (ev._project_job_id) {
                    // Coordinator 割当 → その案件の JobBox 一覧
                    router.get(route('user.project_jobs.jobbox.index', { projectJob: ev._project_job_id }));
                }
            } catch {
                // ルート解決失敗時は何もしない
            }
            return;
        }
        // 手動作成イベント → 種別に応じた編集ページへ
        if (ev.id) {
            try {
                const editRoute = ev._event_route === 'internal'
                    ? route('events.internal-event.edit', { event: ev.id })
                    : ev._event_route === 'client'
                        ? route('events.client-event.edit', { event: ev.id })
                        : route('events.edit', { event: ev.id });
                router.get(editRoute);
            } catch {
                // ルート解決失敗時は何もしない
            }
        }
        return;
    }
    detailTarget.value = ev;
    showDetail.value   = true;
}

function onSaved()   { editTarget.value = null; loadEvents(); }
function onDeleted() { editTarget.value = null; loadEvents(); }

function onModalClose() {
    loadEvents();
    editTarget.value = null;
    showCreate.value = false;
}

function onMonthDateClick(date) {
    openActionSheet({ date });
}

// ── ドラッグ更新（会社イベントのみ） ──────────────────────────
async function onUpdate({ id, starts_at, ends_at }) {
    const idx = companyEvents.value.findIndex(e => e.id === id);
    if (idx < 0) return;
    if (companyEvents.value[idx].room_reservation_id) return;
    const orig = { ...companyEvents.value[idx] };
    companyEvents.value[idx] = { ...orig, starts_at, ends_at };
    try {
        await axios.put(
            route('schedule.events.update', { event: id }),
            { starts_at, ends_at },
            { headers: { 'X-CSRF-TOKEN': CSRF() } }
        );
        loadEvents();
    } catch {
        companyEvents.value[idx] = orig;
        alert('更新に失敗しました');
    }
}

// ── 日報 ────────────────────────────────────────────────────
const hasDiaryToday = computed(() =>
    diaryDates.value.some(d => d.date === currentDate.value)
);

function goToDiary(dateStr) {
    const date = dateStr || currentDate.value;
    router.get(route('diaries.create', { date }));
}

// ── ⚙ 設定ドロップダウン ──────────────────────────────────────
const showSettingsMenu = ref(false);
function toggleSettings() { showSettingsMenu.value = !showSettingsMenu.value; }
function closeSettings()  { showSettingsMenu.value = false; }

function openScheduleModalFromMenu() {
    showSettingsMenu.value = false;
    openScheduleModal();
}

function openBreakModalFromMenu() {
    showSettingsMenu.value = false;
    openBreakModal();
}

// 進行表・管理シートモーダル
const showJobSheetModal     = ref(false);
const jobSheetLoading       = ref(false);
const jsClients             = ref([]);
const jsProjects            = ref([]);
const jsSelectedClientId    = ref('');
const jsSelectedProjectId   = ref('');
const jsProgressSheets      = ref([]);
const jsWorkflowSheets      = ref([]);
const jsSelectedProgressSheetId = ref('');
const jsSelectedWorkflowSheetId = ref('');
const jsSheetsLoading       = ref(false);

const jsFilteredProjects = computed(() =>
    jsSelectedClientId.value
        ? jsProjects.value.filter(p => String(p.client_id) === String(jsSelectedClientId.value))
        : []
);

const canGoToProgressSheet = computed(() =>
    jsProgressSheets.value.length > 0 &&
    (jsProgressSheets.value.length === 1 || !!jsSelectedProgressSheetId.value)
);

const canGoToWorkflowSheet = computed(() =>
    jsWorkflowSheets.value.length > 0 &&
    (jsWorkflowSheets.value.length === 1 || !!jsSelectedWorkflowSheetId.value)
);

async function openJobSheetModal() {
    jsSelectedClientId.value = '';
    jsSelectedProjectId.value = '';
    jsProgressSheets.value = [];
    jsWorkflowSheets.value = [];
    jsSelectedProgressSheetId.value = '';
    jsSelectedWorkflowSheetId.value = '';
    showJobSheetModal.value = true;
    if (jsClients.value.length === 0) {
        jobSheetLoading.value = true;
        try {
            const res = await fetch(route('user.project_jobs.json'), {
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': CSRF(), Accept: 'application/json' },
            });
            if (res.ok) {
                const data = await res.json();
                if (data.clients?.length) jsClients.value = data.clients;
                if (data.projects?.length) jsProjects.value = data.projects;
            }
        } catch { /* ignore */ } finally {
            jobSheetLoading.value = false;
        }
    }
}

function onClientChange() {
    jsSelectedProjectId.value = '';
    jsProgressSheets.value = [];
    jsWorkflowSheets.value = [];
    jsSelectedProgressSheetId.value = '';
    jsSelectedWorkflowSheetId.value = '';
}

async function onProjectChange() {
    jsProgressSheets.value = [];
    jsWorkflowSheets.value = [];
    jsSelectedProgressSheetId.value = '';
    jsSelectedWorkflowSheetId.value = '';
    if (!jsSelectedProjectId.value) return;
    jsSheetsLoading.value = true;
    try {
        const res = await fetch(route('user.project_jobs.sheets_json', { projectJob: jsSelectedProjectId.value }), {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
        });
        if (res.ok) {
            const data = await res.json();
            jsProgressSheets.value = data.progress_sheets ?? [];
            jsWorkflowSheets.value = data.workflow_sheets ?? [];
            if (jsProgressSheets.value.length === 1) jsSelectedProgressSheetId.value = String(jsProgressSheets.value[0].id);
            if (jsWorkflowSheets.value.length === 1) jsSelectedWorkflowSheetId.value = String(jsWorkflowSheets.value[0].id);
        }
    } catch { /* ignore */ } finally {
        jsSheetsLoading.value = false;
    }
}

function goToProgressSheet() {
    if (!canGoToProgressSheet.value) return;
    const id = jsProgressSheets.value.length === 1 ? jsProgressSheets.value[0].id : jsSelectedProgressSheetId.value;
    if (!id) return;
    showJobSheetModal.value = false;
    const { date, startMin, endMin } = actionSheetDef.value;
    const params = { sheet: id, ...minToParams(startMin, endMin) };
    if (date) params.date = date;
    router.visit(route('user.progress_sheets.show', params));
}

function goToWorkflowSheet() {
    if (!canGoToWorkflowSheet.value) return;
    const id = jsWorkflowSheets.value.length === 1 ? jsWorkflowSheets.value[0].id : jsSelectedWorkflowSheetId.value;
    if (!id) return;
    showJobSheetModal.value = false;
    router.visit(route('user.workflow_sheets.show', { sheet: id }));
}

// ── 週間日程設定モーダル ──────────────────────────────────────
const showScheduleModal = ref(false);
const weekDays          = ref([]);
const savingSchedule    = ref(false);
const DAY_NAMES = ['月', '火', '水', '木', '金', '土', '日'];

const localDailyWorktypes = ref([...(props.dailyWorktypes ?? [])]);

const dailyWorktypeMap = computed(() => {
    const map = {};
    localDailyWorktypes.value.forEach(d => { if (d.date) map[d.date] = d.worktype_id; });
    return map;
});

function getMondayOfWeek(d) {
    const day = d.getDay();
    const diff = day === 0 ? -6 : 1 - day;
    const mon = new Date(d);
    mon.setDate(d.getDate() + diff);
    return mon;
}

function openScheduleModal() {
    const monday = getMondayOfWeek(new Date(currentDate.value + 'T00:00:00'));
    const days = [];
    for (let i = 0; i < 7; i++) {
        const d = new Date(monday);
        d.setDate(monday.getDate() + i);
        const ds = d.toLocaleDateString('sv-SE');
        days.push({
            date:       ds,
            label:      `${d.getMonth()+1}/${d.getDate()}(${DAY_NAMES[i]})`,
            worktype_id: dailyWorktypeMap.value[ds] ?? props.defaultWorktype?.id ?? null,
        });
    }
    weekDays.value = days;
    showScheduleModal.value = true;
}

async function saveWeekSchedule() {
    savingSchedule.value = true;
    try {
        await axios.post(route('user.daily_worktypes.store'), { days: weekDays.value });
        weekDays.value.forEach(day => {
            const idx = localDailyWorktypes.value.findIndex(d => d.date === day.date);
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

// ── 週間休憩設定モーダル ──────────────────────────────────────
const showBreakModal    = ref(false);
const breakDays         = ref([]);
const savingBreak       = ref(false);
const batchAllEnabled   = ref(false);
const batchStartH       = ref('12');
const batchStartM       = ref('00');
const batchEndH         = ref('13');
const batchEndM         = ref('00');

const localDailyBreaks = ref([...(props.dailyBreaks ?? [])]);

const dailyBreakMap = computed(() => {
    const map = {};
    localDailyBreaks.value.forEach(d => { if (d.date) map[d.date] = { start: d.start, end: d.end }; });
    return map;
});

function parseHM(timeStr) {
    if (!timeStr) return ['12', '00'];
    const [h, m] = timeStr.split(':');
    return [h ?? '12', m ?? '00'];
}

function openBreakModal() {
    const monday  = getMondayOfWeek(new Date(currentDate.value + 'T00:00:00'));
    const defStart = props.defaultBreak?.start ?? '12:00';
    const defEnd   = props.defaultBreak?.end   ?? '13:00';
    const [defSH, defSM] = parseHM(defStart);
    const [defEH, defEM] = parseHM(defEnd);
    const days = [];
    for (let i = 0; i < 7; i++) {
        const d = new Date(monday);
        d.setDate(monday.getDate() + i);
        const ds = d.toLocaleDateString('sv-SE');
        const saved = dailyBreakMap.value[ds];
        const [sh, sm] = saved ? parseHM(saved.start) : [defSH, defSM];
        const [eh, em] = saved ? parseHM(saved.end)   : [defEH, defEM];
        days.push({
            date:    ds,
            label:   `${d.getMonth()+1}/${d.getDate()}(${DAY_NAMES[i]})`,
            enabled: saved !== undefined,
            startH: sh, startM: sm,
            endH:   eh, endM:   em,
        });
    }
    breakDays.value = days;
    batchAllEnabled.value = false;
    showBreakModal.value = true;
}

function applyBatchAllEnabled() { breakDays.value.forEach(d => (d.enabled = batchAllEnabled.value)); }

function applyBatchBreakTime() {
    breakDays.value.forEach(d => {
        d.enabled = true;
        d.startH = batchStartH.value; d.startM = batchStartM.value;
        d.endH   = batchEndH.value;   d.endM   = batchEndM.value;
    });
}

async function saveWeekBreaks() {
    savingBreak.value = true;
    try {
        const days = breakDays.value.map(day => ({
            date:        day.date,
            break_start: day.enabled ? `${day.startH}:${day.startM}` : null,
            break_end:   day.enabled ? `${day.endH}:${day.endM}`     : null,
        }));
        await axios.post(route('user.daily_breaks.store'), { days });
        days.forEach(day => {
            const idx = localDailyBreaks.value.findIndex(d => d.date === day.date);
            if (!day.break_start) {
                if (idx >= 0) localDailyBreaks.value.splice(idx, 1);
            } else if (idx >= 0) {
                localDailyBreaks.value.splice(idx, 1, { date: day.date, start: day.break_start, end: day.break_end });
            } else {
                localDailyBreaks.value.push({ date: day.date, start: day.break_start, end: day.break_end });
            }
        });
        showBreakModal.value = false;
    } catch {
        alert('保存に失敗しました');
    } finally {
        savingBreak.value = false;
    }
}
</script>

<template>
    <CalendarShell
        :current-date="currentDate"
        :view-mode="viewMode"
        :view-label="viewLabel"
        :loading="loading"
        @navigate="navigate"
        @go-today="goToday"
        @view-mode-change="viewMode = $event"
        @mini-cal-select="currentDate = $event; viewMode = 'day'">

        <!-- ── ツールバー追加ボタン ──────────────────────────────── -->
        <template #toolbar-extra>
            <!-- ⚙ 設定ドロップダウン -->
            <div class="relative">
                <button
                    class="flex items-center gap-1.5 rounded border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50"
                    @click="toggleSettings"
                    @blur="() => setTimeout(closeSettings, 150)">
                    ⚙ 設定
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 transition-transform" :class="showSettingsMenu ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                    </svg>
                </button>
                <div v-if="showSettingsMenu"
                    class="absolute right-0 top-full z-30 mt-1 w-36 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-lg">
                    <button class="flex w-full items-center gap-2 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50"
                        @click="openScheduleModalFromMenu">
                        📅 日程設定
                    </button>
                    <button class="flex w-full items-center gap-2 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 border-t border-gray-100"
                        @click="openBreakModalFromMenu">
                        ☕ 休憩設定
                    </button>
                </div>
            </div>
        </template>

        <!-- ── カレンダー本体 ──────────────────────────────────────── -->
        <MonthView v-if="viewMode === 'month'"
            :year="viewYear" :month="viewMonth"
            :events="allEvents"
            @date-click="onMonthDateClick"
            @event-click="openDetail" />

        <WeekView v-else-if="viewMode === 'week'"
            :start-date="weekStart"
            :events="timedEvents"
            :worktypes="worktypes"
            :daily-worktypes="localDailyWorktypes"
            :default-worktype="defaultWorktype"
            :click-to-create="true"
            @create="openActionSheet"
            @update="onUpdate"
            @event-click="openDetail" />

        <UserDayView v-else
            :date="currentDate"
            :events="timedEvents"
            :worktypes="worktypes"
            :daily-worktypes="localDailyWorktypes"
            :default-worktype="defaultWorktype"
            :daily-breaks="localDailyBreaks"
            :default-break="defaultBreak"
            :has-diary="hasDiaryToday"
            @create="openActionSheet"
            @update="onUpdate"
            @event-click="openDetail"
            @diary-click="goToDiary" />

    </CalendarShell>

    <!-- ── Schedule EventModal（案件打合せ・外出 / 社内予定） ──── -->
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
        @open-room-reserve="showDetail = false"
        @responded="() => { showDetail = false; loadEvents(); }" />

    <ActionSheet
        :show="showActionSheet"
        :date="actionSheetDef.date"
        :start-min="actionSheetDef.startMin"
        :end-min="actionSheetDef.endMin"
        @close="onActionSheetClose"
        @add-event="onActionAddEvent"
        @my-job="onActionMyJob"
        @sheet-job="onActionSheetJob"
        @diary="onActionDiary" />

    <!-- ── 進行表・管理シートモーダル ─────────────────────────── -->
    <div
        v-if="showJobSheetModal"
        class="fixed inset-0 z-[60] flex items-center justify-center bg-black bg-opacity-50"
        @click.self="showJobSheetModal = false">
        <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
            <h2 class="mb-4 text-lg font-bold">案件を選択（進行表・管理シートから）</h2>
            <div v-if="jobSheetLoading" class="py-8 text-center text-sm text-gray-500">読み込み中…</div>
            <div v-else>
                <div class="mb-3">
                    <label class="mb-1 block text-sm font-medium text-gray-700">クライアント</label>
                    <select v-model="jsSelectedClientId" @change="onClientChange" class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
                        <option value="">— 選択してください —</option>
                        <option v-for="c in jsClients" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
                    </select>
                </div>
                <div v-if="jsSelectedClientId" class="mb-3">
                    <label class="mb-1 block text-sm font-medium text-gray-700">案件</label>
                    <select v-model="jsSelectedProjectId" @change="onProjectChange" class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
                        <option value="">— 選択してください —</option>
                        <option v-for="p in jsFilteredProjects" :key="p.id" :value="String(p.id)">{{ p.title || p.name }}</option>
                    </select>
                </div>
                <div v-if="jsSelectedProjectId">
                    <div v-if="jsSheetsLoading" class="py-4 text-center text-sm text-gray-400">読み込み中…</div>
                    <template v-else>
                        <div v-if="jsProgressSheets.length > 0" class="mb-3 rounded border border-indigo-200 bg-indigo-50 p-3">
                            <div class="mb-2 text-sm font-semibold text-indigo-700">進行表</div>
                            <div v-if="jsProgressSheets.length === 1" class="mb-2 text-sm text-gray-800">{{ jsProgressSheets[0].name }}</div>
                            <select v-else v-model="jsSelectedProgressSheetId" class="mb-2 w-full rounded border border-gray-300 px-3 py-2 text-sm">
                                <option value="">— 選択してください —</option>
                                <option v-for="s in jsProgressSheets" :key="s.id" :value="String(s.id)">{{ s.name }}</option>
                            </select>
                            <div class="flex justify-end">
                                <button
                                    @click="goToProgressSheet"
                                    :disabled="!canGoToProgressSheet"
                                    :class="canGoToProgressSheet
                                        ? 'rounded bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700'
                                        : 'cursor-not-allowed rounded bg-gray-300 px-4 py-1.5 text-sm font-medium text-gray-500'">開く</button>
                            </div>
                        </div>
                        <div v-if="jsWorkflowSheets.length > 0" class="mb-3 rounded border border-purple-200 bg-purple-50 p-3">
                            <div class="mb-2 text-sm font-semibold text-purple-700">管理シート</div>
                            <div v-if="jsWorkflowSheets.length === 1" class="mb-2 text-sm text-gray-800">{{ jsWorkflowSheets[0].name }}</div>
                            <select v-else v-model="jsSelectedWorkflowSheetId" class="mb-2 w-full rounded border border-gray-300 px-3 py-2 text-sm">
                                <option value="">— 選択してください —</option>
                                <option v-for="s in jsWorkflowSheets" :key="s.id" :value="String(s.id)">{{ s.name }}</option>
                            </select>
                            <div class="flex justify-end">
                                <button
                                    @click="goToWorkflowSheet"
                                    :disabled="!canGoToWorkflowSheet"
                                    :class="canGoToWorkflowSheet
                                        ? 'rounded bg-purple-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-purple-700'
                                        : 'cursor-not-allowed rounded bg-gray-300 px-4 py-1.5 text-sm font-medium text-gray-500'">開く</button>
                            </div>
                        </div>
                        <div v-if="jsProgressSheets.length === 0 && jsWorkflowSheets.length === 0" class="text-sm text-gray-400">
                            進行表・管理シートなし
                        </div>
                    </template>
                </div>
            </div>
            <div class="mt-4 flex justify-end">
                <button @click="showJobSheetModal = false" class="rounded border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">閉じる</button>
            </div>
        </div>
    </div>

    <!-- ── 週間日程設定モーダル ───────────────────────────────── -->
    <div v-if="showScheduleModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
        <div class="w-full max-w-sm rounded-lg bg-white p-6 shadow-lg">
            <h2 class="mb-4 text-lg font-bold">週間日程設定</h2>
            <p class="mb-3 text-xs text-gray-500">空白はデフォルト設定を使用します。</p>
            <table class="w-full text-sm">
                <tbody>
                    <tr class="border-b-2 border-gray-300 bg-gray-50">
                        <td class="w-24 py-2 pr-3 font-bold text-gray-800">全日</td>
                        <td class="py-2">
                            <select :value="null"
                                @change="(e) => { const v = e.target.value ? Number(e.target.value) : null; weekDays.forEach(d => (d.worktype_id = v)); e.target.value = ''; }"
                                class="w-full rounded border-gray-300 text-sm">
                                <option value="">— 一括選択 —</option>
                                <option :value="null">— デフォルト —</option>
                                <option v-for="wt in worktypes" :key="wt.id" :value="wt.id">
                                    {{ wt.name }}<template v-if="wt.start_time"> ({{ wt.start_time.substring(0,5) }}〜)</template>
                                </option>
                            </select>
                        </td>
                    </tr>
                    <tr v-for="day in weekDays" :key="day.date" class="border-b last:border-0">
                        <td class="w-24 py-2 pr-3 font-medium text-gray-700">{{ day.label }}</td>
                        <td class="py-2">
                            <select v-model="day.worktype_id" class="w-full rounded border-gray-300 text-sm">
                                <option :value="null">— デフォルト —</option>
                                <option v-for="wt in worktypes" :key="wt.id" :value="wt.id">
                                    {{ wt.name }}<template v-if="wt.start_time"> ({{ wt.start_time.substring(0,5) }}〜)</template>
                                </option>
                            </select>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div class="mt-5 flex justify-end gap-3">
                <button @click="showScheduleModal = false" class="rounded bg-gray-200 px-4 py-2 text-sm">キャンセル</button>
                <button @click="saveWeekSchedule" :disabled="savingSchedule"
                    class="rounded bg-blue-600 px-4 py-2 text-sm text-white disabled:opacity-50">
                    {{ savingSchedule ? '保存中…' : '保存' }}
                </button>
            </div>
        </div>
    </div>

    <!-- ── 週間休憩設定モーダル ───────────────────────────────── -->
    <div v-if="showBreakModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
        <div class="w-full max-w-lg rounded-lg bg-white p-6 shadow-lg">
            <h2 class="mb-1 text-lg font-bold">週間休憩設定</h2>
            <p class="mb-3 text-xs text-gray-500">チェックを入れた日に休憩時間が適用されます。時間を変更するとチェックが自動で入ります。</p>
            <!-- 一括設定 -->
            <div class="mb-3 rounded border border-gray-200 bg-gray-50 p-3">
                <div class="mb-2 flex items-center gap-2">
                    <input type="checkbox" id="batch-all" v-model="batchAllEnabled" @change="applyBatchAllEnabled"
                        class="h-4 w-4 rounded border-gray-300 text-blue-600" />
                    <label for="batch-all" class="text-sm font-medium text-gray-700">全日有効化</label>
                </div>
                <div class="flex items-center gap-3">
                    <div class="flex gap-1">
                        <input type="text" v-model="batchStartH" placeholder="12" maxlength="2"
                            class="w-12 rounded border border-gray-300 px-2 py-1 text-center text-sm" />
                        <span class="py-1 text-gray-500">:</span>
                        <input type="text" v-model="batchStartM" placeholder="00" maxlength="2"
                            class="w-12 rounded border border-gray-300 px-2 py-1 text-center text-sm" />
                    </div>
                    <span class="text-gray-500">〜</span>
                    <div class="flex gap-1">
                        <input type="text" v-model="batchEndH" placeholder="13" maxlength="2"
                            class="w-12 rounded border border-gray-300 px-2 py-1 text-center text-sm" />
                        <span class="py-1 text-gray-500">:</span>
                        <input type="text" v-model="batchEndM" placeholder="00" maxlength="2"
                            class="w-12 rounded border border-gray-300 px-2 py-1 text-center text-sm" />
                    </div>
                    <button @click="applyBatchBreakTime" class="rounded bg-gray-600 px-3 py-1 text-xs text-white hover:bg-gray-700">一括適用</button>
                </div>
            </div>
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b-2 border-gray-300 bg-gray-100 text-xs text-gray-600">
                        <th class="py-2 pl-1 pr-3 text-left font-medium">日付</th>
                        <th class="py-2 w-10 text-center font-medium">有効</th>
                        <th class="py-2 pr-8 text-left font-medium">開始</th>
                        <th class="py-2 pl-8 text-left font-medium">終了</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="day in breakDays" :key="day.date" class="border-b last:border-0">
                        <td class="w-28 py-2 pr-3 font-medium text-gray-700">{{ day.label }}</td>
                        <td class="py-2 text-center">
                            <input type="checkbox" v-model="day.enabled"
                                class="h-4 w-4 rounded border-gray-300 text-blue-600" />
                        </td>
                        <td class="py-2">
                            <div class="flex gap-1">
                                <input type="text" v-model="day.startH" maxlength="2"
                                    class="w-12 rounded border border-gray-300 px-2 py-1 text-center text-sm"
                                    @input="day.enabled = true" />
                                <span class="py-1 text-gray-500">:</span>
                                <input type="text" v-model="day.startM" maxlength="2"
                                    class="w-12 rounded border border-gray-300 px-2 py-1 text-center text-sm"
                                    @input="day.enabled = true" />
                            </div>
                        </td>
                        <td class="py-2">
                            <div class="flex gap-1">
                                <input type="text" v-model="day.endH" maxlength="2"
                                    class="w-12 rounded border border-gray-300 px-2 py-1 text-center text-sm"
                                    @input="day.enabled = true" />
                                <span class="py-1 text-gray-500">:</span>
                                <input type="text" v-model="day.endM" maxlength="2"
                                    class="w-12 rounded border border-gray-300 px-2 py-1 text-center text-sm"
                                    @input="day.enabled = true" />
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div class="mt-5 flex justify-end gap-3">
                <button @click="showBreakModal = false" class="rounded bg-gray-200 px-4 py-2 text-sm">キャンセル</button>
                <button @click="saveWeekBreaks" :disabled="savingBreak"
                    class="rounded bg-teal-600 px-4 py-2 text-sm text-white disabled:opacity-50">
                    {{ savingBreak ? '保存中…' : '保存' }}
                </button>
            </div>
        </div>
    </div>
</template>
