<script setup>
import axios from 'axios';
import { Link } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import { route } from 'ziggy-js';
import AppLayout from '@/layouts/AppLayout.vue';
import { workCategories, workActivities, activityLabel } from '@/Constants/personalWorkActivities';
import { evColor } from '@/Composables/useEventTypeColors';

const props = defineProps({ initialDate: { type: String, default: '' } });
const date = ref(props.initialDate || new Date().toLocaleDateString('sv-SE'));
const period = ref('day');
const people = ref([]);
const range = ref('');
const loading = ref(false);
const error = ref('');
const pointRule = ref({ points_per_hour: 10, default_coefficient: 1, coefficients: {} });
const plannedPeople = ref([]);
const planLoading = ref(false);
const planError = ref('');
let loadSequence = 0;
let planSequence = 0;

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
const plannedChipStyle = (item) => chipStyle(item.private
    ? { bg: '#f3f4f6', text: '#4b5563', border: '#d1d5db' }
    : evColor(item));
const duration = (minutes) => `${Math.floor(minutes / 60)}時間${minutes % 60 ? `${minutes % 60}分` : ''}`;
const totalRecords = computed(() => people.value.reduce((sum, person) => sum + Number(person.records || 0), 0));
const totalMinutes = computed(() => people.value.reduce((sum, person) => sum + Number(person.minutes || 0), 0));
const totalPoints = computed(() => people.value.reduce((sum, person) => sum + Number(person.points || 0), 0));
const recordedPeople = computed(() => people.value.filter((person) => Number(person.records || 0) > 0).length);
const maxPersonMinutes = computed(() => Math.max(1, ...people.value.map((person) => Number(person.minutes || 0))));
const pointRows = computed(() => {
    const rows = [...people.value].sort((a, b) => Number(b.points || 0) - Number(a.points || 0) || a.name.localeCompare(b.name, 'ja'));
    const max = Math.max(1, ...rows.map((row) => Number(row.points || 0)));
    return rows.map((row) => ({ ...row, width: `${(Number(row.points || 0) / max) * 100}%` }));
});
const pointLabel = (value) => Number(value || 0).toFixed(1);
const selectedActivityKey = ref('');
const rankingMetric = ref('minutes');
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
const quantityUnits = computed(() => [...new Set(people.value.flatMap((person) => {
    const activity = (person.activities || []).find((row) => activityKey(row) === selectedActivity.value?.key);
    return Object.keys(activity?.quantities || {});
}))]);
const selectedMetric = computed(() => rankingMetric.value.startsWith('unit:') && quantityUnits.value.includes(rankingMetric.value.slice(5))
    ? rankingMetric.value : 'minutes');
const rankingRows = computed(() => {
    if (!selectedActivity.value) return [];
    const rows = people.value.map((person) => {
        const activity = (person.activities || []).find((row) => activityKey(row) === selectedActivity.value.key);
        const unit = selectedMetric.value.startsWith('unit:') ? selectedMetric.value.slice(5) : null;
        return { id: person.id, name: person.name, role: person.role, minutes: Number(activity?.minutes || 0),
            records: Number(activity?.records || 0), quantities: activity?.quantities || {},
            value: unit ? Number(activity?.quantities?.[unit] || 0) : Number(activity?.minutes || 0) };
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
watch(selectedActivityKey, () => { rankingMetric.value = 'minutes'; });
const rangeLabel = computed(() => range.value || date.value);
const pendingPlans = (personId) => plannedPeople.value.find((person) => person.id === personId)?.items
    ?.filter((item) => !item.result_note) || [];

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

async function loadPlans() {
    const sequence = ++planSequence;
    plannedPeople.value = [];
    planError.value = '';
    if (period.value !== 'day') return;
    planLoading.value = true;
    try {
        const { data } = await axios.get(route('schedule.department_work.index'), { params: { date: date.value } });
        if (sequence === planSequence) plannedPeople.value = data.people || [];
    } catch {
        if (sequence === planSequence) planError.value = '予定を読み込めませんでした。';
    } finally {
        if (sequence === planSequence) planLoading.value = false;
    }
}

watch([date, period], load);
watch([date, period], loadPlans);
onMounted(() => { load(); loadPlans(); });
</script>

<template>
    <AppLayout title="作業実績の内訳" :fluid="true">
        <template #header><h2 class="text-xl font-semibold leading-tight text-gray-800">予定表</h2></template>

        <div class="mb-3 flex flex-wrap gap-2 border-b border-gray-200">
            <Link :href="route('schedule.index', { date, tab: 'work' })" class="rounded-t px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-100">部署の作業</Link>
            <span class="rounded-t border border-b-white bg-white px-4 py-2 text-sm font-semibold text-blue-700">作業実績の内訳</span>
            <Link :href="route('schedule.index', { date, tab: 'calendar' })" class="rounded-t px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-100">共有予定</Link>
            <Link :href="route('calendar.index')" class="rounded-t px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-100">カレンダー</Link>
        </div>

        <section class="rounded-lg bg-white p-4 shadow">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="font-semibold">作業実績の内訳</h3>
                    <p class="mt-1 text-xs text-gray-500">記録された結果を集計します。未記録は作業ゼロを意味しません。非公開の作業は本人以外の内訳に含めません。</p>
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
            <div v-else class="mt-4 grid gap-3 sm:grid-cols-3">
                <div class="rounded border bg-slate-50 p-3"><div class="text-xs text-gray-600">結果を記録した人</div><div class="text-xl font-semibold">{{ recordedPeople }} / {{ people.length }}人</div></div>
                <div class="rounded border bg-slate-50 p-3"><div class="text-xs text-gray-600">認定実績</div><div class="text-xl font-semibold">{{ totalRecords }}件</div></div>
                <div class="rounded border bg-slate-50 p-3"><div class="text-xs text-gray-600">記録された作業時間</div><div class="text-xl font-semibold">{{ duration(totalMinutes) }}</div></div>
            </div>
            <p class="mt-2 text-xs text-gray-500">予定だけの登録は認定実績0件・暫定0点です。作業種類・結果文・時間が揃った記録だけを集計します。</p>
            <div v-if="!error" class="mt-3 flex flex-wrap gap-x-3 gap-y-1 text-xs text-gray-600" aria-label="担当ごとのグラフ色">
                <span v-for="role in visibleRoles" :key="role.code" class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-sm" :style="{ backgroundColor: role.bar }"></span>{{ role.label }}</span>
            </div>
            <div class="mt-4 grid items-start gap-4 lg:grid-cols-2">
            <div v-if="!error" class="min-w-0 rounded-lg border border-amber-200 bg-amber-50/40 p-4">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h4 class="font-semibold">暫定ポイント順位 <span class="ml-1 text-xs font-normal text-amber-800">試用・係数は調整可能</span></h4>
                    <span class="text-sm text-gray-600">部署の記録合計 {{ pointLabel(totalPoints) }}点</span>
                </div>
                <p class="mt-1 text-xs text-gray-600">各記録の点数 ＝ 記録時間（時間）×10×作業種類の係数。数量や予定だけの時間は加えません。表示範囲内の記録で計算した参考順位です。</p>
                <details class="mt-2 text-xs text-gray-600">
                    <summary class="cursor-pointer text-amber-900">仮係数を見る</summary>
                    <div class="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        <div v-for="[category, label] in workCategories" :key="category">
                            <strong>{{ label }}</strong>
                            <div v-for="[code, name] in workActivities[category]" :key="code">{{ name }} ×{{ pointRule.coefficients?.[code] ?? pointRule.default_coefficient }}</div>
                        </div>
                    </div>
                </details>
                <div class="mt-3 space-y-2">
                    <div v-for="(row, index) in pointRows" :key="row.id" class="grid grid-cols-[1.5rem_minmax(5rem,8rem)_minmax(2rem,1fr)_4rem] items-center gap-2 text-sm">
                        <span class="text-right tabular-nums text-gray-500">{{ index + 1 }}</span>
                        <span class="truncate font-medium" :title="`${row.name}（${row.role?.label || 'その他'}）`"><span class="mr-1 inline-block h-2 w-2 rounded-sm" :style="{ backgroundColor: roleColor(row.role?.code).bar }"></span>{{ row.name }}</span>
                        <div class="h-5 overflow-hidden rounded bg-white"><div class="h-full rounded" :style="{ width: row.width, backgroundColor: roleColor(row.role?.code).bar }"></div></div>
                        <span class="text-right font-semibold tabular-nums">{{ pointLabel(row.points) }}点</span>
                    </div>
                </div>
            </div>
            <div v-if="selectedActivity" class="min-w-0 rounded-lg border p-4">
                <div class="flex flex-wrap items-center gap-2">
                    <h4 class="mr-auto font-semibold">同じ作業種類の記録量を比較</h4>
                    <select v-model="selectedActivityKey" aria-label="比較する作業種類" class="rounded border px-2 py-1 text-sm">
                        <option v-for="option in activityOptions" :key="option.key" :value="option.key">{{ option.label }}</option>
                    </select>
                    <select v-model="rankingMetric" aria-label="比較する指標" class="rounded border px-2 py-1 text-sm">
                        <option value="minutes">記録時間</option>
                        <option v-for="unit in quantityUnits" :key="unit" :value="`unit:${unit}`">数量（{{ unit }}）</option>
                    </select>
                </div>
                <p class="mt-1 text-xs text-gray-500">同じ作業種類・単位だけを比較します。未記録は0表示ですが、作業をしていないという意味ではありません。</p>
                <div class="mt-3 space-y-2">
                    <div v-for="(row, index) in rankingRows" :key="row.id" class="grid grid-cols-[1.5rem_minmax(5rem,8rem)_minmax(2rem,1fr)_5.5rem] items-center gap-2 text-sm">
                        <span class="text-right tabular-nums text-gray-500">{{ index + 1 }}</span>
                        <span class="truncate font-medium" :title="`${row.name}（${row.role?.label || 'その他'}）`"><span class="mr-1 inline-block h-2 w-2 rounded-sm" :style="{ backgroundColor: roleColor(row.role?.code).bar }"></span>{{ row.name }}</span>
                        <div class="h-5 overflow-hidden rounded bg-gray-100"><div class="h-full rounded" :style="{ width: row.width, backgroundColor: roleColor(row.role?.code).bar }"></div></div>
                        <span class="text-right tabular-nums">{{ selectedMetric === 'minutes' ? duration(row.value) : `${row.value}${selectedMetric.slice(5)}` }}<small class="ml-1 text-gray-500">{{ row.records }}件</small></span>
                    </div>
                </div>
            </div>
            </div>
            <div v-if="!error" class="mt-5">
                <p class="mb-2 text-xs text-gray-500">人別の記録時間は、期間内で最も長い人を棒の全幅として比較します。色は作業区分の内訳です。表示順はユーザー一覧の任意順です。</p>
                <div class="grid items-start gap-3 lg:grid-cols-2">
                <div v-for="person in people" :key="person.id" class="min-w-0 space-y-2 rounded-lg border border-gray-200 p-3 text-sm">
                    <div>
                        <strong>{{ person.name }}</strong>
                        <span class="ml-2 rounded px-1.5 py-0.5 text-xs" :style="{ backgroundColor: roleColor(person.role?.code).chip, color: roleColor(person.role?.code).bar }">{{ person.role?.label || 'その他' }}</span>
                        <p class="text-xs text-gray-500">{{ person.recorded_days ? `${person.recorded_days}日・${person.records || 0}件・${duration(person.minutes)}・${pointLabel(person.points)}点` : '公開範囲内の認定実績0件・暫定0点' }}</p>
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
                    <div v-if="period === 'day'" class="text-xs text-gray-600">
                        <span class="font-medium">結果未入力の予定：</span>
                        <span v-if="planLoading">読み込み中...</span>
                        <span v-else-if="planError">{{ planError }}</span>
                        <span v-else-if="!pendingPlans(person.id).length">なし</span>
                        <span v-for="item in pendingPlans(person.id)" :key="`${item.source}-${item.id}`" class="mr-1 inline-block rounded border px-2 py-0.5" :style="plannedChipStyle(item)">{{ item.title }}</span>
                    </div>
                </div>
                </div>
            </div>
        </section>
    </AppLayout>
</template>
