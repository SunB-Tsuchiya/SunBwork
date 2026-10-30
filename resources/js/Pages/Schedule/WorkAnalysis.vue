<script setup>
import axios from 'axios';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import useWorkloadAnalysisContext from '@/Composables/useWorkloadAnalysisContext';
const { analysisRoute, activeClass } = useWorkloadAnalysisContext();
import WorkloadAnalysisShell from '@/Components/Schedule/WorkloadAnalysisShell.vue';
import TimelineDiary from '@/Components/TimelineDiary.vue';
import { workCategories, workActivities, activityLabel, workCategoryColors as activityColors, workChipStyle as chipStyle } from '@/Constants/personalWorkActivities';

const props = defineProps({ initialDate: { type: String, default: '' } });
const date = ref(props.initialDate || new Date().toLocaleDateString('sv-SE'));
const queryPeriod = typeof window === 'undefined' ? null : new URLSearchParams(window.location.search).get('period');
const period = ref(['day', 'week', 'month'].includes(queryPeriod) ? queryPeriod : 'day');
const queryScoreVersion = typeof window === 'undefined' ? null : new URLSearchParams(window.location.search).get('score_version');
const scoreVersion = ref(queryScoreVersion === 'trial_time_v2' ? 'trial_time_v2' : 'trial_time_v3');
const weekPickerOpen = ref(false);
const weekPicker = ref(null);
const weekPickerMonth = ref(date.value.slice(0, 7));
const people = ref([]);
const range = ref('');
const loading = ref(true);
const error = ref('');
const pointRule = ref({ points_per_hour: 10, default_coefficient: 1, coefficients: {} });
const includeCreation = ref(true);
const includeOvertime = ref(true);
const includeManagement = ref(true);
const selectedPersonId = ref(null);
const selectedPerson = ref(null);
const personName = ref('');
const personDate = ref(date.value);
const personPeriod = ref(period.value);
const personRange = ref('');
const personDrilled = ref(false);
const personSummary = ref(null);
const personSchedule = ref([]);
const personLoading = ref(false);
const personError = ref('');
const closeButton = ref(null);
const backButton = ref(null);
const personPanel = ref(null);
const personTable = ref(null);
let personSummaryScrollTop = 0;
let personTableScrollTop = 0;
let personSummaryDay = '';
let loadSequence = 0;
let personSequence = 0;

const categoryName = (value) => workCategories.find(([key]) => key === value)?.[1] || 'その他';
const roleColors = {
    shinko: { label: '進行管理', bar: '#f59e0b', chip: '#fef3c7' },
    operator: { label: 'オペレーター', bar: '#3b82f6', chip: '#dbeafe' },
    kousei: { label: '校正', bar: '#ec4899', chip: '#fce7f3' },
    seisaku: { label: '制作', bar: '#8b5cf6', chip: '#ede9fe' },
    jimu: { label: '事務', bar: '#06b6d4', chip: '#cffafe' },
    eigyo: { label: '営業', bar: '#10b981', chip: '#d1fae5' },
    other: { label: 'その他', bar: '#94a3b8', chip: '#f1f5f9' },
};
const roleColor = (code) => roleColors[code] || roleColors.other;
const visibleRoles = computed(() => [...new Set(people.value.map((person) => person.role?.code || 'other'))]
    .map((code) => ({ code, ...roleColor(code) })));
const duration = (minutes) => `${Math.floor(minutes / 60)}時間${minutes % 60 ? `${minutes % 60}分` : ''}`;
const itemName = (item) => ({ quick_work: '簡易予定', my_job: 'マイジョブ', assigned_job: '依頼ジョブ' })[item.kind] || '予定';
const clockFromMinutes = (minutes) => minutes == null ? '—' : `${String(Math.floor(minutes / 60)).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`;
const eventClock = (value) => value ? (value.slice(0, 10) === personDate.value ? value.slice(11, 16) : value.slice(0, 16)) : '—';
const itemStart = (item) => item.source === 'entry' ? clockFromMinutes(item.timeline_start_minute) : eventClock(item.starts_at);
const itemEnd = (item) => item.source === 'entry' ? clockFromMinutes(item.timeline_end_minute) : eventClock(item.ends_at);
const orderedSchedule = computed(() => [...personSchedule.value].sort((a, b) => itemStart(a).localeCompare(itemStart(b), 'ja')));
const personTimelineEvents = computed(() => orderedSchedule.value.map((item) => {
    const start = item.source === 'entry' ? localDateTimeAtMinute(personDate.value, item.timeline_start_minute) : item.starts_at?.replace(' ', 'T');
    const end = item.source === 'entry' ? localDateTimeAtMinute(personDate.value, item.timeline_end_minute) : item.ends_at?.replace(' ', 'T');
    if (!start || !end || new Date(end) <= new Date(start)) return null;
    return {
        id: `${item.source}-${item.id}`,
        title: item.title || itemName(item),
        start,
        end,
        allDay: false,
        color: item._custom_color || '#2563eb',
        textColor: item._custom_text_color || '#fff',
        borderColor: item._custom_border_color || '#fff',
        tooltip: `${itemStart(item)}〜${itemEnd(item)} ${item.title || itemName(item)}`,
        readOnly: true,
    };
}).filter(Boolean));
const personTimelineHours = computed(() => {
    const dayStart = new Date(`${personDate.value}T00:00:00`).getTime();
    const minutes = personTimelineEvents.value.flatMap((event) => [
        Math.max(0, (new Date(event.start).getTime() - dayStart) / 60000),
        Math.min(1440, (new Date(event.end).getTime() - dayStart) / 60000),
    ]);
    return {
        start: minutes.length ? Math.max(0, Math.min(7, Math.floor(Math.min(...minutes) / 60))) : 7,
        end: minutes.length ? Math.min(24, Math.max(19, Math.ceil(Math.max(...minutes) / 60))) : 19,
    };
});
function localDateTimeAtMinute(day, minute) {
    if (minute === null || minute === undefined || !Number.isFinite(Number(minute))) return null;
    const value = new Date(`${day}T00:00:00`);
    if (Number.isNaN(value.getTime())) return null;
    value.setMinutes(Number(minute));
    return `${value.getFullYear()}-${String(value.getMonth() + 1).padStart(2, '0')}-${String(value.getDate()).padStart(2, '0')}T${String(value.getHours()).padStart(2, '0')}:${String(value.getMinutes()).padStart(2, '0')}:00`;
}
const totalRecords = computed(() => people.value.reduce((sum, person) => sum + Number(person.records || 0), 0));
const totalMinutes = computed(() => people.value.reduce((sum, person) => sum + Number(person.minutes || 0), 0));
const visiblePoints = (person) => Number(person.base_points || 0)
    + (includeOvertime.value ? Number(person.overtime_points || 0) : 0)
    + (includeCreation.value ? Number(person.creation_points || 0) : 0)
    + (includeManagement.value ? Number(person.management_points || 0) : 0);
const totalPoints = computed(() => people.value.reduce((sum, person) => sum + visiblePoints(person), 0));
const pointRole = ref('all');
const pointPeople = computed(() => people.value.filter((person) =>
    pointRole.value === 'all' || (person.role?.code || 'other') === pointRole.value));
const totalCreatedProjects = computed(() => people.value.reduce((sum, person) => sum + Number(person.created_projects || 0), 0));
const totalUnrecordedOvertime = computed(() => people.value.reduce((sum, person) => sum + Number(person.unrecorded_overtime_minutes || 0), 0));
const recordedPeople = computed(() => people.value.filter((person) => Number(person.records || 0) > 0).length);
const maxPersonMinutes = computed(() => Math.max(1, ...people.value.map((person) => Number(person.minutes || 0))));
const pointRows = computed(() => {
    const rows = [...pointPeople.value].sort((a, b) => visiblePoints(b) - visiblePoints(a) || a.name.localeCompare(b.name, 'ja'));
    const max = Math.max(1, ...rows.map((row) => visiblePoints(row)));
    return rows.map((row) => ({ ...row, width: `${(visiblePoints(row) / max) * 100}%` }));
});
const pointLabel = (value) => Number(value || 0).toFixed(1);
const comparisonCategory = ref('all');
const comparisonRole = ref('all');
const timeSort = ref('minutes');
const timeGroups = [
    ['job', 'ジョブ作業', '#6366f1'], ['quick', '簡易作業', '#ec4899'],
    ['meeting', '会議・訪問', '#10b981'], ['other', 'その他の業務予定', '#64748b'],
    ['overtime', '認定残業', '#fbbf24'],
];
const timeSortOptions = [['minutes', '総時間'], ...timeGroups.map(([key, label]) => [key, label])];
const timeSortLabel = computed(() => timeSortOptions.find(([key]) => key === timeSort.value)?.[1] || '総時間');
const timeRows = computed(() => [...people.value]
    .map((person) => ({ ...person, sortMinutes: Number(timeSort.value === 'minutes'
        ? person.minutes : person.time_breakdown_minutes?.[timeSort.value]) || 0 }))
    .filter((person) => person.sortMinutes > 0)
    .sort((a, b) => b.sortMinutes - a.sortMinutes || a.name.localeCompare(b.name, 'ja'))
    .map((person) => ({
        ...person,
        segments: timeGroups.map(([key, label, color]) => ({
            key, label, color, minutes: Number(person.time_breakdown_minutes?.[key] || 0),
            width: `${Number(person.time_breakdown_minutes?.[key] || 0) / maxPersonMinutes.value * 100}%`,
        })).filter((segment) => segment.minutes > 0),
    })));
const timeMissingCount = computed(() => people.value.length - timeRows.value.length);
const comparisonCategories = computed(() => workCategories.filter(([code]) => people.value.some((person) =>
    (person.activities || []).some((activity) => activity.work_category === code && Number(activity.minutes || 0) > 0))));
const comparisonPeople = computed(() => people.value.filter((person) =>
    comparisonRole.value === 'all' || (person.role?.code || 'other') === comparisonRole.value));
const comparisonRows = computed(() => {
    const rows = comparisonPeople.value.map((person) => {
        const categories = Object.entries((person.activities || []).reduce((totals, activity) => {
            totals[activity.work_category] = (totals[activity.work_category] || 0) + Number(activity.minutes || 0);
            return totals;
        }, {})).filter(([category]) => comparisonCategory.value === 'all' || category === comparisonCategory.value);
        const minutes = categories.reduce((sum, [, value]) => sum + value, 0);
        return { ...person, comparisonMinutes: minutes, comparisonCategories: categories };
    }).filter((person) => person.comparisonMinutes > 0)
        .sort((a, b) => b.comparisonMinutes - a.comparisonMinutes || a.name.localeCompare(b.name, 'ja'));
    const max = Math.max(1, ...rows.map((row) => row.comparisonMinutes));
    return rows.map((row) => ({ ...row, segments: row.comparisonCategories.map(([category, minutes]) => ({
        category, minutes, width: `${(minutes / max) * 100}%`,
    })) }));
});
const comparisonMissingCount = computed(() => comparisonPeople.value.length - comparisonRows.value.length);
const categorySegments = (person) => Object.entries((person.activities || []).reduce((totals, activity) => {
    totals[activity.work_category] = (totals[activity.work_category] || 0) + Number(activity.minutes || 0);
    return totals;
}, {})).map(([category, minutes]) => ({ category, minutes, width: `${(minutes / maxPersonMinutes.value) * 100}%` }));
watch(comparisonCategories, (options) => {
    if (comparisonCategory.value !== 'all' && !options.some(([code]) => code === comparisonCategory.value)) comparisonCategory.value = 'all';
});
watch(visibleRoles, (roles) => {
    if (pointRole.value !== 'all' && !roles.some((role) => role.code === pointRole.value)) pointRole.value = 'all';
});
const rangeLabel = computed(() => range.value || date.value);
const selectedWeekRange = computed(() => {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(date.value)) return '';
    const [year, month, day] = date.value.split('-').map(Number);
    const sunday = new Date(Date.UTC(year, month - 1, day));
    sunday.setUTCDate(sunday.getUTCDate() - sunday.getUTCDay());
    const saturday = new Date(sunday);
    saturday.setUTCDate(saturday.getUTCDate() + 6);
    const format = (value) => `${value.getUTCFullYear()}-${String(value.getUTCMonth() + 1).padStart(2, '0')}-${String(value.getUTCDate()).padStart(2, '0')}`;
    return `${format(sunday)}（日）～${format(saturday)}（土）`;
});
const weekPickerTitle = computed(() => {
    const [year, month] = weekPickerMonth.value.split('-').map(Number);
    return `${year}年${month}月`;
});
const weekPickerDays = computed(() => {
    const [year, month] = weekPickerMonth.value.split('-').map(Number);
    const first = new Date(Date.UTC(year, month - 1, 1));
    first.setUTCDate(1 - first.getUTCDay());
    return Array.from({ length: 42 }, (_, index) => {
        const day = new Date(first);
        day.setUTCDate(first.getUTCDate() + index);
        const value = `${day.getUTCFullYear()}-${String(day.getUTCMonth() + 1).padStart(2, '0')}-${String(day.getUTCDate()).padStart(2, '0')}`;
        return { value, label: day.getUTCDate(), currentMonth: day.getUTCMonth() === month - 1 };
    });
});
const selectedWeekStart = computed(() => {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(date.value)) return '';
    const [year, month, day] = date.value.split('-').map(Number);
    const sunday = new Date(Date.UTC(year, month - 1, day));
    sunday.setUTCDate(sunday.getUTCDate() - sunday.getUTCDay());
    return `${sunday.getUTCFullYear()}-${String(sunday.getUTCMonth() + 1).padStart(2, '0')}-${String(sunday.getUTCDate()).padStart(2, '0')}`;
});
function moveWeekPickerMonth(offset) {
    const [year, month] = weekPickerMonth.value.split('-').map(Number);
    const next = new Date(Date.UTC(year, month - 1 + offset, 1));
    weekPickerMonth.value = `${next.getUTCFullYear()}-${String(next.getUTCMonth() + 1).padStart(2, '0')}`;
}
function chooseWeek(day) {
    date.value = day;
    weekPickerOpen.value = false;
}
function closeWeekPickerOutside(event) {
    if (weekPickerOpen.value && !weekPicker.value?.contains(event.target)) weekPickerOpen.value = false;
}
function closeWeekPickerOnEscape(event) {
    if (event.key === 'Escape') weekPickerOpen.value = false;
}
const monthDate = computed({
    get: () => date.value.slice(0, 7),
    set: (value) => { if (/^\d{4}-\d{2}$/.test(value)) date.value = `${value}-01`; },
});

async function load() {
    const sequence = ++loadSequence;
    loading.value = true;
    error.value = '';
    range.value = '';
    people.value = [];
    if (!/^\d{4}-\d{2}-\d{2}$/.test(date.value)) {
        error.value = '集計する日・週・月を選んでください。';
        loading.value = false;
        return;
    }
    try {
        const { data } = await axios.get(analysisRoute('analysis'), {
            params: { date: date.value, period: period.value, score_version: scoreVersion.value },
        });
        if (sequence !== loadSequence) return;
        people.value = data.people || [];
        pointRule.value = data.point_rule || pointRule.value;
        range.value = `${data.start} ～ ${data.end}`;
    } catch {
        if (sequence === loadSequence) error.value = '作業実績を読み込めませんでした。';
    } finally {
        if (sequence === loadSequence) loading.value = false;
    }
}

function closePerson() {
    personSequence++;
    selectedPersonId.value = null;
    selectedPerson.value = null;
    personName.value = '';
    personRange.value = '';
    personDrilled.value = false;
    personSummary.value = null;
    personSummaryScrollTop = 0;
    personTableScrollTop = 0;
    personSummaryDay = '';
    personSchedule.value = [];
    personError.value = '';
    personLoading.value = false;
}

async function loadPerson(person, selectedPeriod, selectedDate) {
    const sequence = ++personSequence;
    selectedPersonId.value = person.id;
    personName.value = person.name || personName.value;
    personPeriod.value = selectedPeriod;
    personDate.value = selectedDate;
    personRange.value = selectedDate;
    nextTick(() => (personDrilled.value ? backButton.value : closeButton.value)?.focus());
    selectedPerson.value = null;
    personSchedule.value = [];
    personError.value = '';
    personLoading.value = true;
    try {
        const requests = [axios.get(analysisRoute('analysis'), {
            params: { date: selectedDate, period: selectedPeriod, user_id: person.id, score_version: scoreVersion.value },
        })];
        if (selectedPeriod === 'day') requests.push(axios.get(analysisRoute('person_schedule'), { params: { date: selectedDate, user_id: person.id } }));
        const [analysisResponse, scheduleResponse] = await Promise.all(requests);
        if (sequence !== personSequence) return;
        selectedPerson.value = analysisResponse.data.people?.[0] || null;
        personRange.value = `${analysisResponse.data.start} ～ ${analysisResponse.data.end}`;
        personSchedule.value = scheduleResponse?.data.people?.find((row) => Number(row.id) === Number(person.id))?.items
            ?.filter((item) => !item.private) || [];
        if (!selectedPerson.value) personError.value = 'この担当者の詳細を表示できません。';
    } catch {
        if (sequence === personSequence) personError.value = '詳細を読み込めませんでした。';
    } finally {
        if (sequence === personSequence) personLoading.value = false;
    }
}

function openPerson(person) {
    personDrilled.value = false;
    personSummary.value = null;
    loadPerson(person, period.value, date.value);
}

function openPersonDay(day) {
    if (!selectedPersonId.value || !day?.date) return;
    personSummary.value = {
        person: selectedPerson.value,
        range: personRange.value,
        period: personPeriod.value,
        date: personDate.value,
    };
    personSummaryScrollTop = personPanel.value?.scrollTop || 0;
    personTableScrollTop = personTable.value?.scrollTop || 0;
    personSummaryDay = day.date;
    personDrilled.value = true;
    loadPerson({ id: selectedPersonId.value, name: personName.value }, 'day', day.date);
    nextTick(() => { if (personPanel.value) personPanel.value.scrollTop = 0; });
}

function backToPersonPeriod() {
    if (!selectedPersonId.value || !personSummary.value) return;
    personSequence++;
    selectedPerson.value = personSummary.value.person;
    personRange.value = personSummary.value.range;
    personPeriod.value = personSummary.value.period;
    personDate.value = personSummary.value.date;
    personSchedule.value = [];
    personLoading.value = false;
    personError.value = '';
    personDrilled.value = false;
    personSummary.value = null;
    nextTick(() => {
        if (personPanel.value) personPanel.value.scrollTop = personSummaryScrollTop;
        if (personTable.value) personTable.value.scrollTop = personTableScrollTop;
        const dayButton = [...(personTable.value?.querySelectorAll('button[data-day]') || [])]
            .find((button) => button.dataset.day === personSummaryDay);
        (dayButton || closeButton.value)?.focus({ preventScroll: true });
    });
}

watch([date, period, scoreVersion], () => {
    const url = analysisRoute('results', { date: date.value, period: period.value, score_version: scoreVersion.value });
    window.history.replaceState(window.history.state, '', url);
    closePerson();
    load();
});
onMounted(() => {
    load();
    document.addEventListener('pointerdown', closeWeekPickerOutside);
    document.addEventListener('keydown', closeWeekPickerOnEscape);
});
onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', closeWeekPickerOutside);
    document.removeEventListener('keydown', closeWeekPickerOnEscape);
});
</script>

<template>
    <WorkloadAnalysisShell title="作業実績の内訳" active="results" :date="date" :period="period" :score-version="scoreVersion">

        <section class="rounded-lg bg-white p-4 shadow">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="font-semibold">作業実績の内訳</h3>
                    <p class="mt-1 text-xs text-gray-500">時刻を記録した簡易作業と、過去のジョブ・仕事の会議を集計します。未記録は作業ゼロを意味しません。新しい試算では非公開の業務も内容を伏せて集計します。</p>
                </div>
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <label v-if="period !== 'week'" class="flex items-center gap-2"><span class="whitespace-nowrap">{{ { day: '対象日', month: '対象月' }[period] }}</span>
                        <input v-if="period === 'day'" v-model="date" type="date" aria-label="対象日" class="rounded border px-2 py-1" />
                        <input v-else v-model="monthDate" type="month" aria-label="対象月" class="rounded border px-2 py-1" />
                    </label>
                    <div v-else ref="weekPicker" class="relative flex items-center gap-2">
                        <span class="whitespace-nowrap">対象週</span>
                        <button type="button" class="rounded border bg-white px-3 py-1 text-left" aria-label="対象週を選択" :aria-expanded="weekPickerOpen" @click="weekPickerMonth = date.slice(0, 7); weekPickerOpen = !weekPickerOpen">{{ selectedWeekRange || '週を選択' }} <span aria-hidden="true">▦</span></button>
                        <div v-if="weekPickerOpen" class="absolute right-0 top-full z-30 mt-1 w-72 rounded-lg border border-gray-300 bg-white p-3 shadow-xl" role="dialog" aria-label="対象週を選択">
                            <div class="mb-2 flex items-center justify-between">
                                <button type="button" class="rounded border px-2 py-1" aria-label="前の月" @click="moveWeekPickerMonth(-1)">◀</button>
                                <strong>{{ weekPickerTitle }}</strong>
                                <button type="button" class="rounded border px-2 py-1" aria-label="次の月" @click="moveWeekPickerMonth(1)">▶</button>
                            </div>
                            <div class="grid grid-cols-7 text-center text-xs font-medium text-gray-600">
                                <span v-for="weekday in ['日', '月', '火', '水', '木', '金', '土']" :key="weekday" class="py-1">{{ weekday }}</span>
                            </div>
                            <div v-for="row in 6" :key="row" class="group grid grid-cols-7">
                                <button v-for="day in weekPickerDays.slice((row - 1) * 7, row * 7)" :key="day.value" type="button" class="h-9 border-y text-center text-sm group-hover:bg-blue-50" :class="[day.currentMonth ? 'text-gray-900' : 'text-gray-400', day.value === selectedWeekStart ? 'border-l' : '', weekPickerDays[(row - 1) * 7]?.value === selectedWeekStart ? 'border-blue-600 bg-blue-100' : 'border-transparent', day.value === date ? 'font-bold underline' : '', day.value === weekPickerDays[row * 7 - 1]?.value ? 'border-r' : '']" :aria-label="`${day.value}を含む週を選択`" @click="chooseWeek(day.value)">{{ day.label }}</button>
                            </div>
                            <p class="mt-2 text-xs text-gray-600">日曜から土曜までを1週として選びます。</p>
                        </div>
                    </div>
                    <div class="flex gap-1">
                        <button v-for="[key, label] in [['day', '日'], ['week', '週'], ['month', '月']]" :key="key" type="button" class="rounded border px-3 py-1" :class="period === key ? 'border-blue-600 bg-blue-50 text-blue-700' : 'bg-white'" @click="period = key">{{ label }}</button>
                    </div>
                </div>
            </div>
            <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
                <span class="font-medium text-gray-700">採点方式</span>
                <button v-for="[value, label] in [['trial_time_v3', '標準の試算（V3）'], ['trial_time_v2', '従来の試用点（比較用）']]" :key="value" type="button" class="rounded border px-2 py-1" :class="scoreVersion === value ? activeClass : 'bg-white'" @click="scoreVersion = value">{{ label }}</button>
                <span class="text-gray-600">新しい試算は比較用で、過去の記録を変更しません。</span>
            </div>
            <p v-if="period === 'week' && selectedWeekRange" class="mt-2 text-xs font-medium text-blue-800">対象週：{{ selectedWeekRange }}</p>
            <p v-if="!loading && !error" class="mt-2 text-xs text-gray-500">集計期間：{{ rangeLabel }}</p>
            <p v-if="error" class="mt-2 text-sm text-red-700" role="alert">{{ error }}</p>
            <div v-if="loading" class="mt-4 flex items-center gap-3 rounded border border-blue-200 bg-blue-50 p-4 text-sm font-medium text-blue-800" role="status" aria-live="polite">
                <span class="h-5 w-5 animate-spin rounded-full border-2 border-blue-200 border-t-blue-700" aria-hidden="true"></span>
                選択した期間の作業実績を集計中…
            </div>
            <div v-show="!loading && !error">
            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <div class="rounded border bg-slate-50 p-3"><div class="text-xs text-gray-600">時間記録のある人</div><div class="text-xl font-semibold">{{ recordedPeople }} / {{ people.length }}人</div></div>
                <div class="rounded border bg-slate-50 p-3"><div class="text-xs text-gray-600">記録件数</div><div class="text-xl font-semibold">{{ totalRecords }}件</div></div>
                <div class="rounded border bg-slate-50 p-3"><div class="text-xs text-gray-600">記録された作業時間</div><div class="text-xl font-semibold">{{ duration(totalMinutes) }}</div></div>
                <div class="rounded border bg-slate-50 p-3"><div class="text-xs text-gray-600">案件登録</div><div class="text-xl font-semibold">{{ totalCreatedProjects }}件</div></div>
                <div class="rounded border bg-slate-50 p-3"><div class="text-xs text-gray-600">作業記録のない残業</div><div class="text-xl font-semibold">{{ duration(totalUnrecordedOvertime) }}</div></div>
            </div>
            <p class="mt-2 text-xs text-gray-500">簡易作業は開始・終了時刻から集計します。進み・作業種類の記入は任意です。過去のジョブ予定・仕事の会議も時間に含め、重複時間と昼休憩を除きます。ページ数は点数や順位に使いません。</p>
            <div v-if="!error" class="mt-3 flex flex-wrap gap-x-3 gap-y-1 text-xs text-gray-600" aria-label="担当ごとのグラフ色">
                <span v-for="role in visibleRoles" :key="role.code" class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-sm" :style="{ backgroundColor: role.bar }"></span>{{ role.label }}</span>
            </div>
            <div class="mt-4 grid items-start gap-4 lg:grid-cols-2">
            <div v-if="!error" class="min-w-0 rounded-lg border border-amber-200 bg-amber-50/40 p-4">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h4 class="font-semibold">暫定ポイント順位 <span class="ml-1 text-xs font-normal text-amber-800">試用・係数は調整可能</span></h4>
                    <span class="text-sm text-gray-600">対象全員の記録合計 {{ pointLabel(totalPoints) }}点</span>
                </div>
                <label class="mt-2 inline-flex items-center gap-2 text-sm text-gray-700">順位の対象
                    <select v-model="pointRole" class="rounded border border-gray-300 bg-white px-2 py-1 text-sm">
                        <option value="all">総合（全員）</option>
                        <option v-for="role in visibleRoles" :key="role.code" :value="role.code">{{ role.label }}</option>
                    </select>
                </label>
                <p class="mt-1 text-xs text-gray-600">総合を基本に、担当区分ごとの順位も確認できます。順位は記録された活動の目安で、人事評価を確定するものではありません。</p>
                <p v-if="scoreVersion === 'trial_time_v3'" class="mt-1 text-xs text-gray-600">記録時間×10点/時×種類係数に、1日2時間までの認定残業の追加点を加えます。案件登録・案件管理の自動加点はありません。登録件数と管理案件数は参考情報です。</p>
                <p v-else class="mt-1 text-xs text-gray-600">記録時間×10点/時×種類係数に、記録と重なる残業の追加点、案件登録1件{{ pointRule.creation_points }}点、直近14日にチーム作業が動いた管理案件の暫定点を加えます。通常作成・複製・一括取込は同点。過去の登録者は移行時のリーダーから推定しています。</p>
                <div class="mt-2 flex flex-wrap gap-3 text-xs"><label><input v-model="includeOvertime" type="checkbox" class="mr-1 rounded" />残業追加点</label><label v-if="scoreVersion !== 'trial_time_v3'"><input v-model="includeCreation" type="checkbox" class="mr-1 rounded" />案件登録点</label><label v-if="scoreVersion !== 'trial_time_v3'"><input v-model="includeManagement" type="checkbox" class="mr-1 rounded" />案件管理点</label></div>
                <p class="mt-1 text-xs text-gray-600">チェックした点だけで合計と順位を再計算します。保存済みの記録は変わりません。</p>
                <details class="mt-2 text-xs text-gray-600">
                    <summary class="cursor-pointer text-amber-900">仮係数を見る</summary>
                    <div class="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        <div><strong>作業種類未指定</strong><div>標準 ×{{ pointRule.default_coefficient }}</div></div>
                        <div v-for="[category, label] in workCategories" :key="category">
                            <strong>{{ label }}</strong>
                            <div v-for="[code, name] in workActivities[category]" :key="code">{{ name }} ×{{ pointRule.coefficients?.[code] ?? pointRule.default_coefficient }}</div>
                        </div>
                    </div>
                </details>
                <div class="mt-3 space-y-2">
                    <div v-for="(row, index) in pointRows" :key="row.id" class="grid grid-cols-[1.5rem_minmax(5rem,8rem)_minmax(2rem,1fr)_8rem] items-center gap-2 text-sm">
                        <span class="text-right tabular-nums text-gray-500">{{ index + 1 }}</span>
                        <button type="button" class="truncate text-left font-medium text-blue-800 underline underline-offset-2" :title="`${row.name}の詳細を表示`" @click="openPerson(row)"><span class="mr-1 inline-block h-2 w-2 rounded-sm" :style="{ backgroundColor: roleColor(row.role?.code).bar }"></span>{{ row.name }}</button>
                        <div class="h-5 overflow-hidden rounded bg-white"><div class="h-full rounded" :style="{ width: row.width, backgroundColor: roleColor(row.role?.code).bar }"></div></div>
                        <span class="whitespace-nowrap text-right tabular-nums"><strong>{{ pointLabel(visiblePoints(row)) }}点</strong></span>
                    </div>
                </div>
            </div>
            <div class="min-w-0 space-y-3">
            <div class="rounded-lg border p-4">
                <div class="flex flex-wrap items-center gap-2">
                    <h4 class="mr-auto font-semibold">認定された記録時間の内訳</h4>
                    <select v-model="timeSort" aria-label="記録時間の並び順" class="rounded border px-2 py-1 text-sm">
                        <option v-for="[key, label] in timeSortOptions" :key="key" :value="key">{{ label }}が多い順</option>
                    </select>
                </div>
                <p class="mt-1 text-xs text-gray-500">重複と昼休憩を除いた時間です。棒の長さは総時間、右端は「{{ timeSortLabel }}」の時間です。認定残業は作業の種類より優先して黄橙色で示し、総時間には一度だけ含めます。選択した時間がない{{ timeMissingCount }}人は省略しています。記録時間は勤務態度や成果を示すものではありません。</p>
                <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs text-gray-600" aria-label="時間内訳の色">
                    <span v-for="[key, label, color] in timeGroups" :key="key" class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-sm" :style="{ backgroundColor: color }"></span>{{ label }}</span>
                </div>
                <div class="mt-3 space-y-2">
                    <div v-for="(row, index) in timeRows" :key="row.id" class="rounded border border-gray-100 px-2 py-1.5 text-sm">
                        <div class="grid grid-cols-[1.5rem_minmax(5rem,8rem)_minmax(2rem,1fr)_6.5rem] items-center gap-2">
                            <span class="text-right tabular-nums text-gray-500">{{ index + 1 }}</span>
                            <button type="button" class="truncate text-left font-medium text-blue-800 underline underline-offset-2" :title="`${row.name}の詳細を表示`" @click="openPerson(row)">{{ row.name }}</button>
                            <div class="flex h-5 overflow-hidden rounded bg-gray-100"><div v-for="segment in row.segments" :key="segment.key" class="h-full" :style="{ width: segment.width, backgroundColor: segment.color }" :title="`${segment.label} ${duration(segment.minutes)}`"></div></div>
                            <strong class="text-right tabular-nums">{{ duration(row.sortMinutes) }}</strong>
                        </div>
                        <div class="mt-1 flex flex-wrap justify-end gap-x-3 gap-y-0.5 text-xs text-gray-600">
                            <span v-if="timeSort !== 'minutes'">総時間 {{ duration(row.minutes) }}</span>
                            <span v-for="segment in row.segments" :key="segment.key"><span class="mr-1 inline-block h-2 w-2 rounded-sm" :style="{ backgroundColor: segment.color }"></span>{{ segment.label }} {{ duration(segment.minutes) }}</span>
                        </div>
                    </div>
                    <p v-if="!timeRows.length" class="py-3 text-center text-sm text-gray-500">この条件の認定時間はありません。</p>
                </div>
            </div>
            <details class="rounded-lg border p-4">
                <summary class="cursor-pointer font-semibold">作業区分で比較する</summary>
                <div class="flex flex-wrap items-center gap-2">
                    <h4 class="mr-auto font-semibold">作業区分別の記録時間</h4>
                    <select v-model="comparisonRole" aria-label="比較する担当" class="rounded border px-2 py-1 text-sm">
                        <option value="all">全担当</option>
                        <option v-for="role in visibleRoles" :key="role.code" :value="role.code">{{ role.label }}</option>
                    </select>
                    <select v-model="comparisonCategory" aria-label="比較する作業区分" class="rounded border px-2 py-1 text-sm">
                        <option value="all">全作業区分</option>
                        <option v-for="[code, label] in comparisonCategories" :key="code" :value="code">{{ label }}</option>
                    </select>
                </div>
                <p class="mt-1 text-xs text-gray-500">全作業区分では記録した作業内容を色分けし、担当を選ぶと同じ担当内で比較します。記録時間のない{{ comparisonMissingCount }}人は省略しています。未記録は無作業を意味しません。</p>
                <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs text-gray-600" aria-label="作業区分の色">
                    <span v-for="[code, label] in comparisonCategories" :key="code" class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-sm" :style="{ backgroundColor: activityColors[code]?.border || activityColors.other.border }"></span>{{ label }}</span>
                </div>
                <div class="mt-3 space-y-2">
                    <div v-for="(row, index) in comparisonRows" :key="row.id" class="grid grid-cols-[1.5rem_minmax(5rem,8rem)_minmax(2rem,1fr)_5.5rem] items-center gap-2 text-sm">
                        <span class="text-right tabular-nums text-gray-500">{{ index + 1 }}</span>
                        <button type="button" class="truncate text-left font-medium text-blue-800 underline underline-offset-2" :title="`${row.name}の詳細を表示`" @click="openPerson(row)"><span class="mr-1 inline-block h-2 w-2 rounded-sm" :style="{ backgroundColor: roleColor(row.role?.code).bar }"></span>{{ row.name }}</button>
                        <div class="flex h-5 overflow-hidden rounded bg-gray-100"><div v-for="segment in row.segments" :key="segment.category" class="h-full" :style="{ width: segment.width, backgroundColor: activityColors[segment.category]?.border || activityColors.other.border }" :title="`${categoryName(segment.category)} ${duration(segment.minutes)}`"></div></div>
                        <span class="text-right tabular-nums">{{ duration(row.comparisonMinutes) }}</span>
                    </div>
                    <p v-if="!comparisonRows.length" class="py-3 text-center text-sm text-gray-500">この条件の記録時間はありません。</p>
                </div>
            </details>
            </div>
            </div>
            <div v-if="!error" class="mt-5">
                <p class="mb-2 text-xs text-gray-500">人別の記録時間は、期間内で最も長い人を棒の全幅として比較します。色は作業区分の内訳です。記録日は認定された仕事時間がある日、勤怠日は出退勤時刻がある日です。どちらも有休や所定勤務日数を判定した値ではありません。表示順はユーザー一覧の任意順です。</p>
                <div class="grid items-start gap-3 lg:grid-cols-2">
                <div v-for="person in people" :key="person.id" class="min-w-0 space-y-2 rounded-lg border border-gray-200 p-3 text-sm">
                    <div>
                        <button type="button" class="font-semibold text-blue-800 underline underline-offset-2" :aria-label="`${person.name}の詳細を表示`" @click="openPerson(person)">{{ person.name }}</button>
                        <span class="ml-2 rounded px-1.5 py-0.5 text-xs" :style="{ backgroundColor: roleColor(person.role?.code).chip, color: roleColor(person.role?.code).bar }">{{ person.role?.label || 'その他' }}</span>
                        <p class="text-xs text-gray-500">記録日 {{ person.recorded_days || 0 }}日／勤怠日 {{ person.attendance_days || 0 }}日・{{ person.records || 0 }}件・{{ duration(person.minutes) }}／作業 {{ pointLabel(person.work_points) }}点・会議／訪問 {{ pointLabel(person.meeting_points) }}点・残業 {{ pointLabel(person.overtime_points) }}点・案件登録 {{ pointLabel(person.creation_points) }}点（{{ person.created_projects }}件<span v-if="person.legacy_created_projects">、うち推定{{ person.legacy_created_projects }}件</span>）・案件管理 {{ pointLabel(person.management_points) }}点（{{ person.active_managed_projects }}件）</p>
                    </div>
                    <div v-if="person.activities.length" class="space-y-2">
                        <div class="flex h-3 overflow-hidden rounded bg-gray-100" :aria-label="`${person.name}の作業種類別記録時間`">
                            <div v-for="segment in categorySegments(person)" :key="segment.category" :style="{ width: segment.width, backgroundColor: activityColors[segment.category]?.border || activityColors.other.border }" :title="`${categoryName(segment.category)} ${duration(segment.minutes)}`"></div>
                        </div>
                        <div class="flex flex-wrap gap-1">
                        <span v-for="activity in person.activities" :key="`${activity.work_category}-${activity.activity_type}`" class="rounded border px-2 py-1 text-xs" :style="chipStyle(activityColors[activity.work_category] || activityColors.other)">
                            {{ categoryName(activity.work_category) }}／{{ activity.activity_type ? activityLabel(activity.activity_type, activity.work_category) : '旧記録' }}：{{ duration(activity.minutes) }}・{{ pointLabel(activity.points) }}点（×{{ activity.coefficient }}）
                            <span v-for="(quantity, unit) in activity.quantities" :key="unit" class="ml-1">・{{ quantity }}{{ unit }}</span>
                        </span>
                        </div>
                    </div>
                </div>
                </div>
            </div>
            </div>
        </section>
        <Teleport to="body">
            <div v-if="selectedPersonId !== null" class="fixed inset-0 z-[2200] flex items-center justify-center bg-black/50 p-3 sm:p-6" @click.self="closePerson" @keydown.esc="closePerson">
                <div ref="personPanel" role="dialog" aria-modal="true" aria-label="担当者の作業詳細" tabindex="-1" class="max-h-[92vh] w-full max-w-5xl overflow-y-auto rounded-xl bg-white p-4 shadow-xl sm:p-6">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold">{{ personName || '担当者' }}の作業詳細</h2>
                            <p class="text-sm text-gray-600">{{ personRange }}・{{ { day: '日', week: '週', month: '月' }[personPeriod] }}別</p>
                            <button v-if="personDrilled" ref="backButton" type="button" class="mt-1 text-sm font-medium text-blue-800 underline underline-offset-2" @click="backToPersonPeriod">{{ personSummary?.period === 'week' ? '週' : '月' }}の集計に戻る</button>
                        </div>
                        <button ref="closeButton" type="button" class="rounded border px-3 py-1 text-sm hover:bg-gray-50" @click="closePerson">閉じる</button>
                    </div>
                    <p v-if="personLoading" class="mt-5 text-sm text-gray-600">読み込み中...</p>
                    <p v-else-if="personError" class="mt-5 text-sm text-red-700" role="alert">{{ personError }}</p>
                    <template v-else-if="selectedPerson">
                        <div class="mt-4 grid gap-2 text-sm sm:grid-cols-2 lg:grid-cols-5">
                            <div class="rounded border bg-slate-50 p-3">記録件数 <strong class="ml-2">{{ selectedPerson.records }}件</strong></div>
                            <div class="rounded border bg-slate-50 p-3">集計された作業時間 <strong class="ml-2">{{ duration(selectedPerson.minutes) }}</strong></div>
                            <div class="rounded border bg-slate-50 p-3">作業点 <strong class="ml-2">{{ pointLabel(selectedPerson.work_points) }}点</strong></div>
                            <div class="rounded border bg-slate-50 p-3">会議・訪問点 <strong class="ml-2">{{ pointLabel(selectedPerson.meeting_points) }}点</strong></div>
                            <div class="rounded border bg-slate-50 p-3">基本点合計 <strong class="ml-2">{{ pointLabel(selectedPerson.base_points) }}点</strong></div>
                        </div>
                        <p class="mt-2 text-xs text-gray-600">作業点は会議・訪問以外、会議・訪問点には打合せ・業務外出・訪問商談を含みます。集計時間は昼休憩と重複時間を除きます。予定表にある時間の単純な合計とは異なる場合があります。暫定点は正式な人事評価ではありません。</p>
                        <div v-if="personPeriod === 'day' && personTimelineEvents.length" class="mt-3" aria-label="当日の予定タイムテーブル">
                            <h3 class="mb-1 text-sm font-semibold">当日の予定</h3>
                            <TimelineDiary :date="personDate" :events="personTimelineEvents" :start-hour="personTimelineHours.start" :end-hour="personTimelineHours.end" :editable="false" compact />
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2 text-xs">
                            <span v-for="[source, label] in [['quick', '簡易予定'], ['my_job', 'マイジョブ'], ['assigned_job', '依頼ジョブ'], ['meeting', '会議・外出'], ['scheduled_work', '作業予定']]" :key="source" class="rounded border bg-gray-50 px-2 py-1">{{ label }} {{ selectedPerson.source_counts?.[source] || 0 }}件</span>
                        </div>
                        <div v-if="selectedPerson.activities?.length" class="mt-3 flex flex-wrap gap-2 text-xs">
                            <span v-for="activity in selectedPerson.activities" :key="`${activity.work_category}-${activity.activity_type}`" class="rounded border px-2 py-1" :style="chipStyle(activityColors[activity.work_category] || activityColors.other)">
                                {{ categoryName(activity.work_category) }}／{{ activity.activity_type ? activityLabel(activity.activity_type, activity.work_category) : '旧記録' }}：{{ duration(activity.minutes) }}・{{ activity.records }}件
                            </span>
                        </div>
                        <div v-if="personPeriod === 'day'" class="mt-5">
                            <h3 class="font-semibold">その日の時刻表と内容</h3>
                            <p class="mt-1 text-xs text-gray-600">集計対象外の予定も含む予定表の表示です。非公開の内容は表示しません。新しい試算では、非公開の業務も集計値に含むため、表示できる予定の合計とは一致しない場合があります。</p>
                            <p v-if="!orderedSchedule.length" class="mt-3 rounded border p-3 text-sm text-gray-600">表示できる予定はありません。</p>
                            <div v-else class="mt-3 space-y-2">
                                <div v-for="item in orderedSchedule" :key="`${item.source}-${item.id}`" class="grid gap-2 rounded border p-3 text-sm sm:grid-cols-[8rem_minmax(0,1fr)]">
                                    <div class="font-medium tabular-nums">{{ itemStart(item) }}〜{{ itemEnd(item) }}</div>
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2"><strong>{{ item.title }}</strong><span class="rounded bg-pink-50 px-1.5 py-0.5 text-xs text-pink-800">{{ itemName(item) }}</span></div>
                                        <p v-if="item.project_name" class="mt-1 text-xs text-gray-600">案件：{{ item.project_name }}</p>
                                        <p class="mt-1 text-xs text-gray-600">{{ categoryName(item.work_category) }}／{{ item.activity_type ? activityLabel(item.activity_type, item.work_category) : '作業種類未指定' }}<span v-if="item.actual_quantity !== null && item.actual_quantity !== undefined">・{{ item.actual_quantity }}{{ item.actual_unit || '' }}</span></p>
                                        <p v-if="item.result_note" class="mt-1 whitespace-pre-wrap text-xs text-gray-700">進み・結果：{{ item.result_note }}</p>
                                        <p v-else-if="item.kind === 'quick_work'" class="mt-1 text-xs text-gray-500">進み・結果：未入力</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="mt-5">
                            <h3 class="font-semibold">日ごとの集計</h3>
                            <p class="mt-1 text-xs text-gray-600">期間内の日別値です。日をまたぐ予定は各日に表示されるため、日別件数の合計と期間の記録件数が異なる場合があります。</p>
                            <div ref="personTable" class="mt-3 max-h-96 overflow-y-auto rounded border">
                                <table class="w-full text-left text-sm">
                                    <thead class="sticky top-0 bg-gray-50 text-xs text-gray-600"><tr><th class="px-3 py-2">日付</th><th class="px-3 py-2 text-right">予定数</th><th class="px-3 py-2 text-right">集計時間</th><th class="px-3 py-2 text-right">作業点</th><th class="px-3 py-2 text-right">会議・訪問点</th><th class="px-3 py-2 text-right">基本点合計</th></tr></thead>
                                    <tbody><tr v-for="day in selectedPerson.daily || []" :key="day.date" class="border-t"><td class="px-3 py-2"><button type="button" class="font-medium text-blue-800 underline underline-offset-2 hover:text-blue-600" :data-day="day.date" :aria-label="`${day.date}の作業詳細を表示`" @click="openPersonDay(day)">{{ day.date }}</button></td><td class="px-3 py-2 text-right">{{ day.records }}件</td><td class="px-3 py-2 text-right">{{ duration(day.minutes) }}</td><td class="px-3 py-2 text-right">{{ pointLabel(day.work_points) }}点</td><td class="px-3 py-2 text-right">{{ pointLabel(day.meeting_points) }}点</td><td class="px-3 py-2 text-right">{{ pointLabel(day.base_points) }}点</td></tr></tbody>
                                </table>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </Teleport>
    </WorkloadAnalysisShell>
</template>
