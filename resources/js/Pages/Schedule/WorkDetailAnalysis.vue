<script setup>
import axios from 'axios';
import { computed, onMounted, ref, watch } from 'vue';
import { route } from 'ziggy-js';
import WorkloadAnalysisShell from '@/Components/Schedule/WorkloadAnalysisShell.vue';
import { workCategories, activityLabel } from '@/Constants/personalWorkActivities';

const props = defineProps({ initialDate: { type: String, default: '' } });
const month = ref((props.initialDate || new Date().toLocaleDateString('sv-SE')).slice(0, 7));
const period = ref('month');
const role = ref('all');
const category = ref('all');
const source = ref('all');
const people = ref([]);
const excludedCount = ref(0);
const range = ref('');
const loading = ref(false);
const error = ref('');
const includeOvertime = ref(true);
const includeCreation = ref(true);
const includeManagement = ref(true);
const sortBy = ref('units');
let sequence = 0;

const roleLabels = {
    shinko: '進行管理', operator: 'オペレーター', kousei: '校正',
    seisaku: '制作', jimu: '事務', eigyo: '営業', other: 'その他',
};
const roleColors = {
    shinko: '#f59e0b', operator: '#3b82f6', kousei: '#ec4899',
    seisaku: '#8b5cf6', jimu: '#06b6d4', eigyo: '#10b981', other: '#94a3b8',
};
const sourceLabels = [
    ['quick', '簡易実績'], ['my_job', 'マイジョブ'],
    ['assigned_job', '依頼ジョブ'], ['meeting', '会議・外出'],
];
const categoryLabel = (code) => workCategories.find(([key]) => key === code)?.[1] || 'その他';
const number = (value) => Number(value || 0).toLocaleString('ja-JP');
const decimal = (value) => Number(value || 0).toFixed(1);
const duration = (minutes) => `${Math.floor(Number(minutes || 0) / 60)}時間${Number(minutes || 0) % 60 ? `${Number(minutes) % 60}分` : ''}`;
const visiblePoints = (person) => Number(person.base_points || 0)
    + (includeOvertime.value ? Number(person.overtime_points || 0) : 0)
    + (includeCreation.value ? Number(person.creation_points || 0) : 0)
    + (includeManagement.value ? Number(person.management_points || 0) : 0);
const appliedPoint = (person, key, enabled) => decimal(enabled ? person[key] : 0);
const roles = computed(() => [...new Set(people.value.map((person) => person.role?.code || 'other'))]
    .map((code) => [code, roleLabels[code] || 'その他']));
const filtered = computed(() => people.value.filter((person) =>
    (role.value === 'all' || (person.role?.code || 'other') === role.value)
    && (category.value === 'all' || (person.activities || []).some((activity) => activity.work_category === category.value))
    && (source.value === 'all' || Number(person.source_counts?.[source.value] || 0) > 0)));
const sortValue = (person) => sortBy.value === 'records' ? Number(person.records || 0)
    : (sortBy.value === 'points' ? visiblePoints(person) : Number(person.work_units || 0));
const ranked = computed(() => [...filtered.value]
    .sort((a, b) => sortValue(b) - sortValue(a) || a.name.localeCompare(b.name, 'ja')));
const totals = computed(() => filtered.value.reduce((sum, person) => {
    sum.units += Number(person.work_units || 0);
    sum.records += Number(person.records || 0);
    sum.minutes += Number(person.minutes || 0);
    sum.recorded += Number(person.records || 0) > 0 ? 1 : 0;
    for (const [key] of sourceLabels) sum.sources[key] += Number(person.source_counts?.[key] || 0);
    return sum;
}, { units: 0, records: 0, minutes: 0, recorded: 0,
    sources: { quick: 0, my_job: 0, assigned_job: 0, meeting: 0 } }));

async function load() {
    if (!/^\d{4}-\d{2}$/.test(month.value)) return;
    const current = ++sequence;
    loading.value = true;
    error.value = '';
    try {
        const { data } = await axios.get(route('schedule.department_work.analysis'), {
            params: { date: `${month.value}-01`, period: period.value },
        });
        if (current !== sequence) return;
        people.value = data.people || [];
        excludedCount.value = Number(data.excluded_count || 0);
        range.value = `${data.start} ～ ${data.end}`;
    } catch {
        if (current === sequence) error.value = '作業分析を読み込めませんでした。';
    } finally {
        if (current === sequence) loading.value = false;
    }
}

watch([month, period], load);
watch([includeOvertime, includeCreation, includeManagement], () => { sortBy.value = 'points'; });
onMounted(load);
</script>

<template>
    <WorkloadAnalysisShell title="作業量分析" active="details" :date="`${month}-01`">

        <section class="rounded-lg bg-white p-4 shadow">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="text-lg font-semibold">記録と作業の内訳</h3>
                    <p class="mt-1 text-xs text-gray-600">情報出版の試用画面です。工程候補は同じ案件・種類・日付の記録をまとめた参考値です。完了数の確定値ではありません。</p>
                </div>
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <input v-model="month" type="month" aria-label="対象月" class="rounded border px-2 py-1" />
                    <select v-model="period" aria-label="集計期間" class="rounded border px-2 py-1">
                        <option value="month">1か月</option><option value="quarter">3か月</option>
                    </select>
                    <select v-model="role" aria-label="担当で絞る" class="rounded border px-2 py-1">
                        <option value="all">全担当</option>
                        <option v-for="[code, label] in roles" :key="code" :value="code">{{ label }}</option>
                    </select>
                    <select v-model="category" aria-label="作業区分に該当する人を表示" class="rounded border px-2 py-1">
                        <option value="all">全作業区分</option>
                        <option v-for="[code, label] in workCategories" :key="code" :value="code">{{ label }}</option>
                    </select>
                    <select v-model="source" aria-label="入力元に該当する人を表示" class="rounded border px-2 py-1">
                        <option value="all">全入力元</option>
                        <option v-for="[code, label] in sourceLabels" :key="code" :value="code">{{ label }}</option>
                    </select>
                </div>
            </div>
            <p class="mt-2 text-xs text-gray-500">対象：{{ range || `${month}-01` }} <span v-if="loading">読み込み中...</span></p>
            <p class="mt-1 text-xs text-gray-600">作業区分・入力元は該当する人を表示する条件です。下の件数・時間・点数は、その人の全記録の集計です。</p>
            <p v-if="error" class="mt-3 text-sm text-red-700" role="alert">{{ error }}</p>

            <div v-if="!error" class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <div class="rounded border bg-slate-50 p-3"><div class="text-xs text-gray-600">分析対象</div><strong class="text-xl">{{ filtered.length }}人</strong><p class="text-xs text-gray-500">対象外 {{ excludedCount }}人</p></div>
                <div class="rounded border bg-slate-50 p-3"><div class="text-xs text-gray-600">記録のある人</div><strong class="text-xl">{{ totals.recorded }}人</strong></div>
                <div class="rounded border bg-slate-50 p-3"><div class="text-xs text-gray-600">工程候補</div><strong class="text-xl">{{ number(totals.units) }}</strong><p class="text-xs text-gray-500">入力 {{ number(totals.records) }}件</p></div>
                <div class="rounded border bg-slate-50 p-3"><div class="text-xs text-gray-600">記録時間</div><strong class="text-xl">{{ duration(totals.minutes) }}</strong><p class="text-xs text-gray-500">重複・昼休憩を除外</p></div>
                <div class="rounded border bg-slate-50 p-3"><div class="text-xs text-gray-600">入力元</div><p v-for="[key, label] in sourceLabels" :key="key" class="text-xs">{{ label }} {{ number(totals.sources[key]) }}件</p></div>
            </div>

            <div class="mt-5 rounded border border-amber-200 bg-amber-50/50 p-3 text-sm">
                <div class="font-semibold">並び方と暫定点の比較</div>
                <p class="mt-1 text-xs text-gray-700">工程候補は同じ案件・作業種類・日付などをまとめた参考数、入力件数は記録の数です。どちらも完了数ではありません。暫定点は主に記録時間で決まります。</p>
                <div class="mt-2 flex flex-wrap gap-2 text-xs">
                    <label v-for="[key, label] in [['units', '工程候補が多い順'], ['records', '入力件数が多い順'], ['points', '暫定点が高い順']]" :key="key" class="rounded border px-2 py-1" :class="sortBy === key ? 'border-blue-500 bg-blue-50 text-blue-800' : 'bg-white'">
                        <input v-model="sortBy" type="radio" name="work-detail-sort" :value="key" class="sr-only" />{{ label }}
                    </label>
                </div>
                <p class="mt-1 text-xs text-gray-600">表示順は記録を見るための並び替えで、能力や成果の順位ではありません。</p>
                <div class="mt-2 flex flex-wrap gap-4 text-xs">
                    <label><input v-model="includeOvertime" type="checkbox" class="mr-1 rounded" />残業追加点</label>
                    <label><input v-model="includeCreation" type="checkbox" class="mr-1 rounded" />案件登録点</label>
                    <label><input v-model="includeManagement" type="checkbox" class="mr-1 rounded" />案件管理点</label>
                </div>
                <p class="mt-1 text-xs text-gray-600">切り替えると「暫定点順」で再表示します。下の内訳と合計には、チェックした点だけを反映します。</p>
            </div>

            <div v-if="!error" class="mt-4 overflow-x-auto rounded border">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs text-gray-600">
                        <tr><th class="px-3 py-2">表示順</th><th class="px-3 py-2">担当者</th><th class="px-3 py-2">担当</th><th class="px-3 py-2 text-right">工程候補／入力</th><th class="px-3 py-2 text-right">記録日／勤怠日（参考）</th><th class="px-3 py-2 text-right">記録時間</th><th class="px-3 py-2 text-right">作業点</th><th class="px-3 py-2 text-right">適用中の残業／登録／管理</th><th class="px-3 py-2 text-right">暫定合計</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="(person, index) in ranked" :key="person.id" class="hover:bg-gray-50">
                            <td class="px-3 py-2 text-gray-500">{{ index + 1 }}</td>
                            <td class="px-3 py-2 font-semibold">{{ person.name }}</td>
                            <td class="px-3 py-2"><span class="mr-1 inline-block h-2.5 w-2.5 rounded-sm" :style="{ backgroundColor: roleColors[person.role?.code] || roleColors.other }"></span>{{ person.role?.label || roleLabels.other }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ number(person.work_units) }}／{{ number(person.records) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ number(person.recorded_days) }}／{{ person.attendance_days ? number(person.attendance_days) : '—' }}<span v-if="person.attendance_days" class="ml-1 text-xs text-gray-500">({{ Math.round(person.recorded_days / person.attendance_days * 100) }}%)</span></td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ duration(person.minutes) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ decimal(person.base_points) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums" :title="`元の点数：残業 ${decimal(person.overtime_points)}／登録 ${decimal(person.creation_points)}／管理 ${decimal(person.management_points)}`">{{ appliedPoint(person, 'overtime_points', includeOvertime) }}／{{ appliedPoint(person, 'creation_points', includeCreation) }}／{{ appliedPoint(person, 'management_points', includeManagement) }}</td>
                            <td class="px-3 py-2 text-right font-semibold tabular-nums">{{ decimal(visiblePoints(person)) }}</td>
                        </tr>
                        <tr v-if="!ranked.length"><td colspan="9" class="px-3 py-6 text-center text-gray-500">対象の記録がありません。</td></tr>
                    </tbody>
                </table>
            </div>

            <div v-if="!error" class="mt-5 grid gap-3 lg:grid-cols-2">
                <article v-for="person in ranked" :key="person.id" class="rounded border p-3 text-sm">
                    <div class="flex flex-wrap items-center justify-between gap-2"><h4 class="font-semibold">{{ person.name }}</h4><span class="text-xs text-gray-500">活動管理案件 {{ number(person.active_managed_projects) }}件・登録 {{ number(person.created_projects) }}件</span></div>
                    <div class="mt-2 flex flex-wrap gap-1 text-xs">
                        <span v-for="[key, label] in sourceLabels" :key="key" class="rounded bg-slate-100 px-2 py-1">{{ label }} {{ number(person.source_counts?.[key]) }}件</span>
                    </div>
                    <p v-if="!person.activities?.length" class="mt-2 text-xs text-gray-500">公開範囲内の作業実績なし</p>
                    <div v-else class="mt-2 space-y-1 text-xs">
                        <p v-for="activity in person.activities" :key="`${activity.work_category}-${activity.activity_type}`" class="rounded bg-gray-50 px-2 py-1">
                            {{ categoryLabel(activity.work_category) }}／{{ activityLabel(activity.activity_type, activity.work_category) }}：入力{{ number(activity.records) }}件・{{ duration(activity.minutes) }}
                            <span v-for="(quantity, unit) in activity.quantities" :key="unit">・{{ number(quantity) }}{{ unit }}</span>
                        </p>
                    </div>
                    <p v-if="person.unrecorded_overtime_minutes" class="mt-2 text-xs text-amber-800">記録のない残業 {{ duration(person.unrecorded_overtime_minutes) }}</p>
                </article>
            </div>
        </section>
    </WorkloadAnalysisShell>
</template>
