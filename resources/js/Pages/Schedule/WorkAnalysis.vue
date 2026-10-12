<script setup>
import axios from 'axios';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { route } from 'ziggy-js';
import WorkloadAnalysisShell from '@/Components/Schedule/WorkloadAnalysisShell.vue';
import TimelineDiary from '@/Components/TimelineDiary.vue';
import { workCategories, workActivities, activityLabel } from '@/Constants/personalWorkActivities';

const props = defineProps({ initialDate: { type: String, default: '' } });
const date = ref(props.initialDate || new Date().toLocaleDateString('sv-SE'));
const period = ref('day');
const people = ref([]);
const range = ref('');
const loading = ref(false);
const error = ref('');
const pointRule = ref({ points_per_hour: 10, default_coefficient: 1, coefficients: {} });
const includeCreation = ref(true);
const includeOvertime = ref(true);
const includeManagement = ref(true);
const selectedPersonId = ref(null);
const selectedPerson = ref(null);
const personSchedule = ref([]);
const personLoading = ref(false);
const personError = ref('');
const closeButton = ref(null);
let loadSequence = 0;
let personSequence = 0;

const categoryName = (value) => workCategories.find(([key]) => key === value)?.[1] || 'その他';
const activityColors = {
    shinko: { bg: '#fef3c7', text: '#78350f', border: '#fcd34d' },
    eigyo: { bg: '#d1fae5', text: '#065f46', border: '#6ee7b7' },
    dtp: { bg: '#dbeafe', text: '#1e40af', border: '#93c5fd' },
    kousei: { bg: '#fce7f3', text: '#9d174d', border: '#f9a8d4' },
    jimu: { bg: '#cffafe', text: '#155e75', border: '#67e8f9' },
    other: { bg: '#ede9fe', text: '#5b21b6', border: '#c4b5fd' },
};
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
const chipStyle = (color) => ({ backgroundColor: color.bg, color: color.text, borderColor: color.border });
const duration = (minutes) => `${Math.floor(minutes / 60)}時間${minutes % 60 ? `${minutes % 60}分` : ''}`;
const itemName = (item) => ({ quick_work: '簡易予定', my_job: 'マイジョブ', assigned_job: '依頼ジョブ' })[item.kind] || '予定';
const clockFromMinutes = (minutes) => minutes == null ? '—' : `${String(Math.floor(minutes / 60)).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`;
const eventClock = (value) => value ? (value.slice(0, 10) === date.value ? value.slice(11, 16) : value.slice(0, 16)) : '—';
const itemStart = (item) => item.source === 'entry' ? clockFromMinutes(item.timeline_start_minute) : eventClock(item.starts_at);
const itemEnd = (item) => item.source === 'entry' ? clockFromMinutes(item.timeline_end_minute) : eventClock(item.ends_at);
const orderedSchedule = computed(() => [...personSchedule.value].sort((a, b) => itemStart(a).localeCompare(itemStart(b), 'ja')));
const personTimelineEvents = computed(() => orderedSchedule.value.map((item) => {
    const start = item.source === 'entry' ? localDateTimeAtMinute(date.value, item.timeline_start_minute) : item.starts_at?.replace(' ', 'T');
    const end = item.source === 'entry' ? localDateTimeAtMinute(date.value, item.timeline_end_minute) : item.ends_at?.replace(' ', 'T');
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
    const dayStart = new Date(`${date.value}T00:00:00`).getTime();
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
const totalCreatedProjects = computed(() => people.value.reduce((sum, person) => sum + Number(person.created_projects || 0), 0));
const totalUnrecordedOvertime = computed(() => people.value.reduce((sum, person) => sum + Number(person.unrecorded_overtime_minutes || 0), 0));
const recordedPeople = computed(() => people.value.filter((person) => Number(person.records || 0) > 0).length);
const maxPersonMinutes = computed(() => Math.max(1, ...people.value.map((person) => Number(person.minutes || 0))));
const pointRows = computed(() => {
    const rows = [...people.value].sort((a, b) => visiblePoints(b) - visiblePoints(a) || a.name.localeCompare(b.name, 'ja'));
    const max = Math.max(1, ...rows.map((row) => visiblePoints(row)));
    return rows.map((row) => ({ ...row, width: `${(visiblePoints(row) / max) * 100}%` }));
});
const pointLabel = (value) => Number(value || 0).toFixed(1);
const selectedActivityKey = ref('');
const activityKey = (activity) => `${activity.work_category}|${activity.activity_type || 'legacy'}`;
const activityOptions = computed(() => {
    const options = new Map();
    for (const person of people.value) {
        for (const activity of person.activities || []) {
            const key = activityKey(activity);
            if (!options.has(key)) options.set(key, {
                key, category: activity.work_category,
                label: `${categoryName(activity.work_category)}／${activity.activity_type ? activityLabel(activity.activity_type, activity.work_category) : '旧記録'}`,
            });
        }
    }
    return [...options.values()];
});
const selectedActivity = computed(() => activityOptions.value.find((option) => option.key === selectedActivityKey.value) || activityOptions.value[0]);
const rankingRows = computed(() => {
    if (!selectedActivity.value) return [];
    const rows = people.value.map((person) => {
        const activity = (person.activities || []).find((row) => activityKey(row) === selectedActivity.value.key);
        return { id: person.id, name: person.name, role: person.role, minutes: Number(activity?.minutes || 0),
            records: Number(activity?.records || 0), quantities: activity?.quantities || {},
            value: Number(activity?.minutes || 0) };
    }).sort((a, b) => b.value - a.value || a.name.localeCompare(b.name, 'ja'));
    const max = Math.max(1, ...rows.map((row) => row.value));
    return rows.map((row) => ({ ...row, width: `${(row.value / max) * 100}%` }));
});
const categorySegments = (person) => Object.entries((person.activities || []).reduce((totals, activity) => {
    totals[activity.work_category] = (totals[activity.work_category] || 0) + Number(activity.minutes || 0);
    return totals;
}, {})).map(([category, minutes]) => ({ category, minutes, width: `${(minutes / maxPersonMinutes.value) * 100}%` }));
watch(activityOptions, (options) => {
    if (!options.some((option) => option.key === selectedActivityKey.value)) selectedActivityKey.value = options[0]?.key || '';
});
const rangeLabel = computed(() => range.value || date.value);

async function load() {
    const sequence = ++loadSequence;
    loading.value = true;
    error.value = '';
    try {
        const { data } = await axios.get(route('schedule.department_work.analysis'), {
            params: { date: date.value, period: period.value },
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
    personSchedule.value = [];
    personError.value = '';
    personLoading.value = false;
}

async function openPerson(person) {
    const sequence = ++personSequence;
    selectedPersonId.value = person.id;
    nextTick(() => closeButton.value?.focus());
    selectedPerson.value = null;
    personSchedule.value = [];
    personError.value = '';
    personLoading.value = true;
    try {
        const requests = [axios.get(route('schedule.department_work.analysis'), {
            params: { date: date.value, period: period.value, user_id: person.id },
        })];
        if (period.value === 'day') requests.push(axios.get(route('schedule.department_work.index'), { params: { date: date.value, user_id: person.id } }));
        const [analysisResponse, scheduleResponse] = await Promise.all(requests);
        if (sequence !== personSequence) return;
        selectedPerson.value = analysisResponse.data.people?.[0] || null;
        personSchedule.value = scheduleResponse?.data.people?.find((row) => Number(row.id) === Number(person.id))?.items
            ?.filter((item) => !item.private) || [];
        if (!selectedPerson.value) personError.value = 'この担当者の詳細を表示できません。';
    } catch {
        if (sequence === personSequence) personError.value = '詳細を読み込めませんでした。';
    } finally {
        if (sequence === personSequence) personLoading.value = false;
    }
}

watch([date, period], () => { closePerson(); load(); });
onMounted(load);
</script>

<template>
    <WorkloadAnalysisShell title="作業実績の内訳" active="results" :date="date">

        <section class="rounded-lg bg-white p-4 shadow">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="font-semibold">作業実績の内訳</h3>
                    <p class="mt-1 text-xs text-gray-500">時刻を記録した簡易作業と、過去のジョブ・仕事の会議を集計します。未記録は作業ゼロを意味しません。非公開の作業は本人以外の内訳に含めません。</p>
                </div>
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <input v-model="date" type="date" aria-label="集計日" class="rounded border px-2 py-1" />
                    <div class="flex gap-1">
                        <button v-for="[key, label] in [['day', '日'], ['week', '週'], ['month', '月']]" :key="key" type="button" class="rounded border px-3 py-1" :class="period === key ? 'border-blue-600 bg-blue-50 text-blue-700' : 'bg-white'" @click="period = key">{{ label }}</button>
                    </div>
                </div>
            </div>
            <p class="mt-2 text-xs text-gray-500">対象：{{ rangeLabel }} <span v-if="loading">読み込み中...</span></p>
            <p v-if="error" class="mt-2 text-sm text-red-700" role="alert">{{ error }}</p>
            <div v-else class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
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
                    <span class="text-sm text-gray-600">部署の記録合計 {{ pointLabel(totalPoints) }}点</span>
                </div>
                <p class="mt-1 text-xs text-gray-600">記録時間×10点/時×種類係数に、記録と重なる残業の追加点、案件登録1件{{ pointRule.creation_points }}点、直近14日にチーム作業が動いた管理案件の暫定点を加えます。通常作成・複製・一括取込は同点。過去の登録者は移行時のリーダーから推定しています。</p>
                <div class="mt-2 flex flex-wrap gap-3 text-xs"><label><input v-model="includeOvertime" type="checkbox" class="mr-1 rounded" />残業追加点</label><label><input v-model="includeCreation" type="checkbox" class="mr-1 rounded" />案件登録点</label><label><input v-model="includeManagement" type="checkbox" class="mr-1 rounded" />案件管理点</label></div>
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
                    <div v-for="(row, index) in pointRows" :key="row.id" class="grid grid-cols-[1.5rem_minmax(5rem,8rem)_minmax(2rem,1fr)_4rem] items-center gap-2 text-sm">
                        <span class="text-right tabular-nums text-gray-500">{{ index + 1 }}</span>
                        <button type="button" class="truncate text-left font-medium text-blue-800 underline underline-offset-2" :title="`${row.name}の詳細を表示`" @click="openPerson(row)"><span class="mr-1 inline-block h-2 w-2 rounded-sm" :style="{ backgroundColor: roleColor(row.role?.code).bar }"></span>{{ row.name }}</button>
                        <div class="h-5 overflow-hidden rounded bg-white"><div class="h-full rounded" :style="{ width: row.width, backgroundColor: roleColor(row.role?.code).bar }"></div></div>
                        <span class="text-right font-semibold tabular-nums" :title="`適用中：作業 ${pointLabel(row.base_points)}／残業 ${pointLabel(includeOvertime ? row.overtime_points : 0)}／登録 ${pointLabel(includeCreation ? row.creation_points : 0)}／管理 ${pointLabel(includeManagement ? row.management_points : 0)}`">{{ pointLabel(visiblePoints(row)) }}点</span>
                    </div>
                </div>
            </div>
            <div v-if="selectedActivity" class="min-w-0 rounded-lg border p-4">
                <div class="flex flex-wrap items-center gap-2">
                    <h4 class="mr-auto font-semibold">同じ作業種類の記録量を比較</h4>
                    <select v-model="selectedActivityKey" aria-label="比較する作業種類" class="rounded border px-2 py-1 text-sm">
                        <option v-for="option in activityOptions" :key="option.key" :value="option.key">{{ option.label }}</option>
                    </select>
                </div>
                <p class="mt-1 text-xs text-gray-500">同じ作業種類の記録時間を比較します。未記録は0表示ですが、作業をしていないという意味ではありません。</p>
                <div class="mt-3 space-y-2">
                    <div v-for="(row, index) in rankingRows" :key="row.id" class="grid grid-cols-[1.5rem_minmax(5rem,8rem)_minmax(2rem,1fr)_5.5rem] items-center gap-2 text-sm">
                        <span class="text-right tabular-nums text-gray-500">{{ index + 1 }}</span>
                        <button type="button" class="truncate text-left font-medium text-blue-800 underline underline-offset-2" :title="`${row.name}の詳細を表示`" @click="openPerson(row)"><span class="mr-1 inline-block h-2 w-2 rounded-sm" :style="{ backgroundColor: roleColor(row.role?.code).bar }"></span>{{ row.name }}</button>
                        <div class="h-5 overflow-hidden rounded bg-gray-100"><div class="h-full rounded" :style="{ width: row.width, backgroundColor: roleColor(row.role?.code).bar }"></div></div>
                        <span class="text-right tabular-nums">{{ duration(row.value) }}<small class="ml-1 text-gray-500">{{ row.records }}件</small></span>
                    </div>
                </div>
            </div>
            </div>
            <div v-if="!error" class="mt-5">
                <p class="mb-2 text-xs text-gray-500">人別の記録時間は、期間内で最も長い人を棒の全幅として比較します。色は作業区分の内訳です。表示順はユーザー一覧の任意順です。</p>
                <div class="grid items-start gap-3 lg:grid-cols-2">
                <div v-for="person in people" :key="person.id" class="min-w-0 space-y-2 rounded-lg border border-gray-200 p-3 text-sm">
                    <div>
                        <button type="button" class="font-semibold text-blue-800 underline underline-offset-2" :aria-label="`${person.name}の詳細を表示`" @click="openPerson(person)">{{ person.name }}</button>
                        <span class="ml-2 rounded px-1.5 py-0.5 text-xs" :style="{ backgroundColor: roleColor(person.role?.code).chip, color: roleColor(person.role?.code).bar }">{{ person.role?.label || 'その他' }}</span>
                        <p class="text-xs text-gray-500">{{ person.recorded_days ? `${person.recorded_days}日・${person.records || 0}件・${duration(person.minutes)}` : '公開範囲内の認定時間なし' }}／作業 {{ pointLabel(person.base_points) }}点・残業 {{ pointLabel(person.overtime_points) }}点・案件登録 {{ pointLabel(person.creation_points) }}点（{{ person.created_projects }}件<span v-if="person.legacy_created_projects">、うち推定{{ person.legacy_created_projects }}件</span>）・案件管理 {{ pointLabel(person.management_points) }}点（{{ person.active_managed_projects }}件）</p>
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
        </section>
        <Teleport to="body">
            <div v-if="selectedPersonId !== null" class="fixed inset-0 z-[2200] flex items-center justify-center bg-black/50 p-3 sm:p-6" @click.self="closePerson" @keydown.esc="closePerson">
                <div role="dialog" aria-modal="true" aria-label="担当者の作業詳細" tabindex="-1" class="max-h-[92vh] w-full max-w-5xl overflow-y-auto rounded-xl bg-white p-4 shadow-xl sm:p-6">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold">{{ selectedPerson?.name || '担当者' }}の作業詳細</h2>
                            <p class="text-sm text-gray-600">{{ rangeLabel }}・{{ { day: '日', week: '週', month: '月' }[period] }}別</p>
                        </div>
                        <button ref="closeButton" type="button" class="rounded border px-3 py-1 text-sm hover:bg-gray-50" @click="closePerson">閉じる</button>
                    </div>
                    <p v-if="personLoading" class="mt-5 text-sm text-gray-600">読み込み中...</p>
                    <p v-else-if="personError" class="mt-5 text-sm text-red-700" role="alert">{{ personError }}</p>
                    <template v-else-if="selectedPerson">
                        <div class="mt-4 grid gap-2 text-sm sm:grid-cols-3">
                            <div class="rounded border bg-slate-50 p-3">記録件数 <strong class="ml-2">{{ selectedPerson.records }}件</strong></div>
                            <div class="rounded border bg-slate-50 p-3">集計された作業時間 <strong class="ml-2">{{ duration(selectedPerson.minutes) }}</strong></div>
                            <div class="rounded border bg-slate-50 p-3">暫定作業点 <strong class="ml-2">{{ pointLabel(selectedPerson.base_points) }}点</strong></div>
                        </div>
                        <p class="mt-2 text-xs text-gray-600">集計時間は昼休憩と重複時間を除きます。予定表にある時間の単純な合計とは異なる場合があります。暫定点は正式な人事評価ではありません。</p>
                        <div v-if="period === 'day' && personTimelineEvents.length" class="mt-3" aria-label="当日の予定タイムテーブル">
                            <h3 class="mb-1 text-sm font-semibold">当日の予定</h3>
                            <TimelineDiary :date="date" :events="personTimelineEvents" :start-hour="personTimelineHours.start" :end-hour="personTimelineHours.end" :editable="false" compact />
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2 text-xs">
                            <span v-for="[source, label] in [['quick', '簡易予定'], ['my_job', 'マイジョブ'], ['assigned_job', '依頼ジョブ'], ['meeting', '会議・外出']]" :key="source" class="rounded border bg-gray-50 px-2 py-1">{{ label }} {{ selectedPerson.source_counts?.[source] || 0 }}件</span>
                        </div>
                        <div v-if="selectedPerson.activities?.length" class="mt-3 flex flex-wrap gap-2 text-xs">
                            <span v-for="activity in selectedPerson.activities" :key="`${activity.work_category}-${activity.activity_type}`" class="rounded border px-2 py-1" :style="chipStyle(activityColors[activity.work_category] || activityColors.other)">
                                {{ categoryName(activity.work_category) }}／{{ activity.activity_type ? activityLabel(activity.activity_type, activity.work_category) : '旧記録' }}：{{ duration(activity.minutes) }}・{{ activity.records }}件
                            </span>
                        </div>
                        <div v-if="period === 'day'" class="mt-5">
                            <h3 class="font-semibold">その日の時刻表と内容</h3>
                            <p class="mt-1 text-xs text-gray-600">集計対象外の予定も含む予定表の表示です。非公開の内容は表示しません。</p>
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
                            <div class="mt-3 max-h-96 overflow-y-auto rounded border">
                                <table class="w-full text-left text-sm">
                                    <thead class="sticky top-0 bg-gray-50 text-xs text-gray-600"><tr><th class="px-3 py-2">日付</th><th class="px-3 py-2 text-right">予定数</th><th class="px-3 py-2 text-right">集計時間</th><th class="px-3 py-2 text-right">暫定作業点</th></tr></thead>
                                    <tbody><tr v-for="day in selectedPerson.daily || []" :key="day.date" class="border-t"><td class="px-3 py-2">{{ day.date }}</td><td class="px-3 py-2 text-right">{{ day.records }}件</td><td class="px-3 py-2 text-right">{{ duration(day.minutes) }}</td><td class="px-3 py-2 text-right">{{ pointLabel(day.base_points) }}点</td></tr></tbody>
                                </table>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </Teleport>
    </WorkloadAnalysisShell>
</template>
