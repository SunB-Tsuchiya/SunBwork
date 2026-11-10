<script setup>
import { onMounted, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import ReportShell from '@/Components/SunA/Sales/ReportShell.vue';
import PeriodBar from '@/Components/SunA/Sales/PeriodBar.vue';
import { useSalesChart } from '@/Composables/useSalesChart';
import { useReportExport } from '@/Components/SunA/Sales/useReportExport.js';

// サンエー印刷 売上レポートの注目ポイント（SUNASALES_PLAN1.md §20）。
// 大きく増えた／減った得意先（前年との差額の上位10社）・新規・休眠（直近の登録月から数か月、売上が止まっている得意先）。
// 増減・新規は年次分析と同じ集計。休眠は年の選択に関わらず、直近の登録月が基準。
const props = defineProps({
    departmentOptions: { type: Array, default: () => [] },
    years: { type: Array, default: () => [] },
    initialYear: { type: Number, default: null },
    dormantOptions: { type: Array, default: () => [3, 6, 12] },
});

const { yen, pct, pctClass } = useSalesChart();
const { exporting, exportError, exportExcel } = useReportExport();
const query = new URLSearchParams(window.location.search);
const qYear = Number(query.get('year'));
const qDept = query.get('dept');
const qDormant = Number(query.get('dormant'));

const deptKey = ref(props.departmentOptions.some((o) => o.key === qDept) ? qDept : 'all');
const year = ref(props.years.includes(qYear) ? qYear : props.initialYear);
const dormantMonths = ref(props.dormantOptions.includes(qDormant) ? qDormant : 6);
const loading = ref(false);
const errorMessage = ref('');
const summary = ref(null);

const fetchSummary = async () => {
    loading.value = true;
    errorMessage.value = '';
    try {
        const res = await axios.get(route('suna.sales.report.api.attention.summary'), { params: { year: year.value, dept: deptKey.value, dormant_months: dormantMonths.value } });
        summary.value = res.data;
    } catch (e) {
        errorMessage.value = 'データの取得に失敗しました。';
        summary.value = null;
    } finally {
        loading.value = false;
    }
};

const goLatest = () => { if (props.initialYear) year.value = props.initialYear; };
const syncQueryString = () => {
    const url = new URL(window.location.href);
    url.searchParams.set('dept', deptKey.value);
    url.searchParams.set('year', String(year.value));
    url.searchParams.set('dormant', String(dormantMonths.value));
    window.history.replaceState(null, '', url);
};
watch([deptKey, year, dormantMonths], () => { syncQueryString(); fetchSummary(); });
onMounted(fetchSummary);

const goToClient = (key) => router.get(route('suna.sales.report.client'), { client: key });
</script>

<template>
    <ReportShell title="注目ポイント" active="attention">
        <template #actions>
            <span v-if="exportError" class="max-w-xs text-xs text-red-600">{{ exportError }}</span>
            <button type="button" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-bold text-gray-700 hover:bg-gray-50 disabled:opacity-50" :disabled="exporting || !summary" @click="exportExcel(route('suna.sales.report.export.attention'), { year, dept: deptKey, dormant_months: dormantMonths })">Excel出力</button>
        </template>
        <div v-if="!years.length" class="rounded bg-white p-6 shadow">
            <p class="text-sm text-gray-500">まだ売上データが取り込まれていません。</p>
            <Link :href="route('suna.sales.report.index')" class="mt-3 inline-block text-sm text-indigo-600 hover:underline">データ登録状況へ</Link>
        </div>

        <div v-else class="space-y-4">
            <PeriodBar
                v-model:dept-key="deptKey"
                v-model:year="year"
                mode="year"
                :department-options="departmentOptions"
                :years="years"
                :loading="loading"
                @go-latest="goLatest"
            />

            <p v-if="errorMessage" class="rounded bg-red-50 p-3 text-sm text-red-700">{{ errorMessage }}</p>
            <p v-if="loading" class="text-sm text-gray-500">読み込み中...</p>

            <p v-if="summary && !summary.has_data" class="rounded bg-white p-6 text-sm text-gray-500 shadow">{{ year }}年は、売上が登録されていません。</p>

            <template v-if="summary && summary.has_data">
                <div class="rounded bg-indigo-50 p-3 text-xs text-indigo-800">
                    <template v-if="summary.comparison_available">{{ year }}年の{{ summary.period.label }}を、{{ summary.prior.year }}年の同じ月と比べています。増加・減少は、前年との差額が大きい順の上位10社です。</template>
                    <template v-else>前年のデータがないため、増加・減少・新規は出せません（休眠は下に表示します）。</template>
                </div>

                <div v-if="summary.comparison_available" class="grid gap-4 lg:grid-cols-2">
                    <div class="rounded bg-white p-4 shadow">
                        <h3 class="mb-2 text-sm font-semibold text-gray-900">大きく増えた得意先 <span class="text-xs font-normal text-gray-400">上位10社（該当 {{ summary.increase.total_count }}社）</span></h3>
                        <p v-if="!summary.increase.rows.length" class="text-xs text-gray-400">該当する得意先はありません。</p>
                        <table v-else class="min-w-full text-xs tabular-nums">
                            <thead>
                                <tr class="text-left text-gray-500">
                                    <th class="py-1">得意先</th>
                                    <th class="py-1 text-right">売上</th>
                                    <th class="py-1 text-right">前年同期間</th>
                                    <th class="py-1 text-right">差額</th>
                                    <th class="py-1 text-right">増減率</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="r in summary.increase.rows" :key="r.client_key" class="border-t">
                                    <td class="py-1"><button type="button" class="text-left text-indigo-700 hover:underline" @click="goToClient(r.client_key)">{{ r.label }}</button></td>
                                    <td class="py-1 text-right">{{ yen(r.amount) }}</td>
                                    <td class="py-1 text-right">{{ yen(r.prior_amount) }}</td>
                                    <td class="py-1 text-right" :class="pctClass(r.rate)">{{ yen(r.diff) }}</td>
                                    <td class="py-1 text-right" :class="pctClass(r.rate)">{{ r.rate !== null ? pct(r.rate) : '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="rounded bg-white p-4 shadow">
                        <h3 class="mb-2 text-sm font-semibold text-gray-900">大きく減った得意先 <span class="text-xs font-normal text-gray-400">上位10社（該当 {{ summary.decrease.total_count }}社）</span></h3>
                        <p v-if="!summary.decrease.rows.length" class="text-xs text-gray-400">該当する得意先はありません。</p>
                        <table v-else class="min-w-full text-xs tabular-nums">
                            <thead>
                                <tr class="text-left text-gray-500">
                                    <th class="py-1">得意先</th>
                                    <th class="py-1 text-right">売上</th>
                                    <th class="py-1 text-right">前年同期間</th>
                                    <th class="py-1 text-right">差額</th>
                                    <th class="py-1 text-right">増減率</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="r in summary.decrease.rows" :key="r.client_key" class="border-t">
                                    <td class="py-1"><button type="button" class="text-left text-indigo-700 hover:underline" @click="goToClient(r.client_key)">{{ r.label }}</button></td>
                                    <td class="py-1 text-right">{{ yen(r.amount) }}</td>
                                    <td class="py-1 text-right">{{ yen(r.prior_amount) }}</td>
                                    <td class="py-1 text-right" :class="pctClass(r.rate)">{{ yen(r.diff) }}</td>
                                    <td class="py-1 text-right" :class="pctClass(r.rate)">{{ r.rate !== null ? pct(r.rate) : '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-if="summary.comparison_available" class="rounded bg-white p-4 shadow">
                    <h3 class="mb-2 text-sm font-semibold text-gray-900">新規の得意先 <span class="text-xs font-normal text-gray-400">前年に売上がなく、今年は売上がある得意先（{{ summary.new.total_count }}社<template v-if="summary.new.total_count > summary.new.rows.length">・売上の大きい順に{{ summary.new.rows.length }}社を表示。全件はExcel出力で</template>）</span></h3>
                    <p v-if="!summary.new.rows.length" class="text-xs text-gray-400">該当する得意先はありません。</p>
                    <table v-else class="min-w-full text-xs tabular-nums">
                        <thead>
                            <tr class="text-left text-gray-500">
                                <th class="py-1">得意先</th>
                                <th class="py-1 text-right">今年の売上</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="r in summary.new.rows" :key="r.client_key" class="border-t">
                                <td class="py-1"><button type="button" class="text-left text-indigo-700 hover:underline" @click="goToClient(r.client_key)">{{ r.label }}</button></td>
                                <td class="py-1 text-right">{{ yen(r.amount) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="rounded bg-white p-4 shadow">
                    <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold text-gray-900">休眠の得意先 <span v-if="summary.dormant.available" class="text-xs font-normal text-gray-400">（{{ summary.dormant.total_count }}社<template v-if="summary.dormant.total_count > summary.dormant.rows.length">・前の12か月の売上が大きい順に{{ summary.dormant.rows.length }}社を表示。全件はExcel出力で</template>）</span></h3>
                        <div class="flex items-center gap-2 text-xs text-gray-500">
                            売上が止まっている期間:
                            <div class="flex rounded border text-sm">
                                <button v-for="n in dormantOptions" :key="n" type="button" class="px-3 py-1" :class="dormantMonths === n ? 'bg-indigo-600 text-white' : 'text-gray-600'" @click="dormantMonths = n">{{ n }}か月</button>
                            </div>
                        </div>
                    </div>
                    <p v-if="!summary.dormant.available" class="rounded bg-amber-50 p-3 text-xs text-amber-800">{{ summary.dormant.message }}</p>
                    <template v-else>
                        <p class="mb-2 text-xs text-gray-500">
                            直近の登録月（{{ summary.dormant.latest_label }}）から{{ summary.dormant.months }}か月（{{ summary.dormant.silent_label }}）売上がなく、その前の12か月（{{ summary.dormant.prior_label }}）には売上があった得意先です。
                            印刷の仕事は季節の単発（カレンダー・あいさつ状など）があるので、12か月に切り替えて見ることもできます。部門は、前の12か月に計上された部門で絞っています。
                        </p>
                        <p v-if="!summary.dormant.rows.length" class="text-xs text-gray-400">該当する得意先はありません。</p>
                        <table v-else class="min-w-full text-xs tabular-nums">
                            <thead>
                                <tr class="text-left text-gray-500">
                                    <th class="py-1">得意先</th>
                                    <th class="py-1 text-right">前の12か月の売上</th>
                                    <th class="py-1 text-right">最後に売上があった月</th>
                                    <th class="py-1 text-right">売上のない期間</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="r in summary.dormant.rows" :key="r.client_key" class="border-t">
                                    <td class="py-1"><button type="button" class="text-left text-indigo-700 hover:underline" @click="goToClient(r.client_key)">{{ r.label }}</button></td>
                                    <td class="py-1 text-right">{{ yen(r.prior_amount) }}</td>
                                    <td class="py-1 text-right">{{ r.last_label }}</td>
                                    <td class="py-1 text-right">{{ r.silent_months }}か月</td>
                                </tr>
                            </tbody>
                        </table>
                    </template>
                </div>
            </template>
        </div>
    </ReportShell>
</template>
