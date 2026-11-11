<script setup>
import { onMounted, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import SalesAnalysisShell from '@/Components/SalesAnalysis/SalesAnalysisShell.vue';
import PeriodNavigator from '@/Components/SalesAnalysis/PeriodNavigator.vue';
import { useSalesChart } from '@/Composables/useSalesChart';

// 注目ポイント（SBATTN_PLAN1.md）。大きく増えた／減った得意先（前年との差額の上位10社）・新規・休眠。
// 増減・新規は年次分析と同じ集計（登録済みの月を前年の同じ月と比べる）。休眠は、選んだ部署の最新の登録月から数か月、売上が止まっている得意先。
const props = defineProps({
    routePrefix: { type: String, required: true },
    departmentLabels: { type: Object, default: () => ({}) },
    enabledDepartmentKeys: { type: Array, default: () => [] },
    initialDepartmentKey: { type: String, default: 'planning' },
    initialYear: { type: Number, required: true },
    hasAnyData: { type: Boolean, default: false },
    hasCompanySelected: { type: Boolean, default: true },
    dormantOptions: { type: Array, default: () => [3, 6, 12] },
});

// 売上分析ルートは superadmin/admin/clerk の各ロールグループ内に複製登録されている
const rn = (name) => `${props.routePrefix}.sales_analysis.${name}`;

const { yen, pct, pctClass } = useSalesChart();

const query = new URLSearchParams(window.location.search);
const qDormant = Number(query.get('dormant'));

const departmentKey = ref(props.initialDepartmentKey);
const year = ref(props.initialYear);
const consolidateClients = ref(false);
const dormantMonths = ref(props.dormantOptions.includes(qDormant) ? qDormant : 6);

const loading = ref(false);
const errorMessage = ref('');
const summary = ref(null);

const params = () => ({
    department_key: departmentKey.value,
    year: year.value,
    consolidate_clients: consolidateClients.value ? 1 : 0,
    dormant_months: dormantMonths.value,
});

const fetchSummary = async () => {
    loading.value = true;
    errorMessage.value = '';
    try {
        const res = await axios.get(route(rn('api.attention')), { params: params() });
        summary.value = res.data;
    } catch (e) {
        errorMessage.value = 'データの取得に失敗しました。';
        summary.value = null;
    } finally {
        loading.value = false;
    }
};

const goLatest = async () => {
    try {
        const res = await axios.get(route(rn('api.annual_latest_period')), { params: { department_key: departmentKey.value } });
        if (res.data.latest) year.value = res.data.latest.year;
    } catch (e) {
        // 取得できなければ、そのまま
    }
};

const syncQueryString = () => {
    const url = new URL(window.location.href);
    url.searchParams.set('department_key', departmentKey.value);
    url.searchParams.set('year', String(year.value));
    url.searchParams.set('dormant', String(dormantMonths.value));
    window.history.replaceState(null, '', url);
};

watch([departmentKey, year, consolidateClients, dormantMonths], () => {
    syncQueryString();
    fetchSummary();
});
onMounted(() => {
    if (props.hasAnyData) fetchSummary();
});

// 得意先名を押すと、得意先分析へ（統合ONのときはグループ名がそのまま使える）
const goToClient = (name) => router.get(route(rn('client_analysis')), { department_key: departmentKey.value, client_name: name });
</script>

<template>
    <SalesAnalysisShell title="注目ポイント" :route-prefix="routePrefix" active="attention">
        <template #actions>
            <a
                v-if="hasAnyData"
                :href="route(rn('attention.export'), params())"
                class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-bold text-gray-700 hover:bg-gray-50"
            >Excel出力</a>
        </template>

        <div v-if="!hasAnyData" class="rounded bg-white p-6 shadow">
            <template v-if="!hasCompanySelected">
                <p class="text-sm text-gray-500">会社が選択されていません。画面右上の会社切替から対象の会社を選択してください。</p>
            </template>
            <template v-else>
                <p class="text-sm text-gray-500">まだ取込データがありません。まずはExcelを取り込んでください。</p>
                <Link :href="route(rn('import.create'))" class="mt-4 inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-bold text-white hover:bg-indigo-700">
                    Excel取込へ
                </Link>
            </template>
        </div>

        <div v-else class="space-y-4">
            <PeriodNavigator
                v-model:department-key="departmentKey"
                v-model:year="year"
                v-model:consolidate-clients="consolidateClients"
                granularity="year"
                allow-all-departments
                :department-labels="departmentLabels"
                :enabled-department-keys="enabledDepartmentKeys"
                :loading="loading"
                @go-latest="goLatest"
            />

            <p v-if="errorMessage" class="rounded bg-red-50 p-3 text-sm text-red-700">{{ errorMessage }}</p>
            <p v-if="loading" class="text-sm text-gray-500">読み込み中...</p>

            <p v-if="summary && !summary.has_data" class="rounded bg-white p-6 text-sm text-gray-500 shadow">{{ year }}年は、売上が登録されていません。</p>

            <template v-if="summary && summary.has_data">
                <div class="rounded bg-indigo-50 p-3 text-xs text-indigo-800">
                    <template v-if="summary.comparison_available">{{ year }}年の1〜{{ summary.last_month }}月（登録済みの月）を、{{ summary.prior_year }}年の同じ月と比べています。増加・減少は、前年との差額が大きい順の上位10社です。</template>
                    <template v-else>前年のデータがないため、増加・減少・新規は出せません（休眠は下に表示します）。</template>
                </div>

                <div v-if="summary.comparison_available" class="grid gap-4 lg:grid-cols-2">
                    <div v-for="block in [{ key: 'increase', title: '大きく増えた得意先' }, { key: 'decrease', title: '大きく減った得意先' }]" :key="block.key" class="rounded bg-white p-4 shadow">
                        <h3 class="mb-2 text-sm font-semibold text-gray-900">{{ block.title }} <span class="text-xs font-normal text-gray-400">上位10社（該当 {{ summary[block.key].total_count }}社）</span></h3>
                        <p v-if="!summary[block.key].rows.length" class="text-xs text-gray-400">該当する得意先はありません。</p>
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
                                <tr v-for="r in summary[block.key].rows" :key="r.label" class="border-t">
                                    <td class="py-1"><button type="button" class="text-left text-indigo-700 hover:underline" @click="goToClient(r.label)">{{ r.label }}</button></td>
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
                            <tr v-for="r in summary.new.rows" :key="r.label" class="border-t">
                                <td class="py-1"><button type="button" class="text-left text-indigo-700 hover:underline" @click="goToClient(r.label)">{{ r.label }}</button></td>
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
                            基準月（{{ summary.dormant.latest_label }}）から{{ summary.dormant.months }}か月（{{ summary.dormant.silent_label }}）売上がなく、その前の12か月（{{ summary.dormant.prior_label }}）には売上があった得意先です。
                            選んだ部署の売上だけで判定しています（全部署のときは、どの部署にも売上がない得意先）。印刷の仕事は季節の単発（カレンダー・あいさつ状など）があるので、12か月に切り替えて見ることもできます。
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
                                <tr v-for="r in summary.dormant.rows" :key="r.label" class="border-t">
                                    <td class="py-1"><button type="button" class="text-left text-indigo-700 hover:underline" @click="goToClient(r.label)">{{ r.label }}</button></td>
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
    </SalesAnalysisShell>
</template>
