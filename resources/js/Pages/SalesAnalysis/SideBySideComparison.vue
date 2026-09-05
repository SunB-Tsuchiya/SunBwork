<script setup>
import { computed, ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import axios from 'axios';
import SalesAnalysisNavigationTabs from '@/Components/SalesAnalysis/SalesAnalysisNavigationTabs.vue';

const props = defineProps({
    routePrefix: { type: String, required: true },
    departmentLabels: { type: Object, default: () => ({}) },
    enabledDepartmentKeys: { type: Array, default: () => [] },
    hasCompanySelected: { type: Boolean, default: true },
});

// 売上分析ルートは superadmin/admin/clerk の各ロールグループ内に複製登録されている
const rn = (name) => `${props.routePrefix}.sales_analysis.${name}`;

const monthLabels = ['1月', '2月', '3月', '4月', '5月', '6月', '7月', '8月', '9月', '10月', '11月', '12月'];
const nowYear = new Date().getFullYear();
const nowMonth = new Date().getMonth() + 1;

const departmentKey = ref(props.enabledDepartmentKeys[0] ?? 'planning');
const consolidateClients = ref(false);
const mode = ref('year'); // 'year' | 'month'

const yearA = ref(nowYear - 1);
const yearB = ref(nowYear);
const monthYearA = ref(nowYear - 1);
const monthA = ref(nowMonth);
const monthYearB = ref(nowYear);
const monthB = ref(nowMonth);

const loading = ref(false);
const errorMessage = ref('');
const summary = ref(null);

const yen = (v) => (v === null || v === undefined ? '—' : `¥${Number(v).toLocaleString()}`);
const pct = (v) => (v === null || v === undefined ? '比較データなし' : `${v > 0 ? '+' : ''}${v}%`);
const pctClass = (v) => {
    if (v === null || v === undefined) return 'text-gray-400';
    return v > 0 ? 'text-blue-600' : v < 0 ? 'text-red-600' : 'text-gray-500';
};

const useSameMonthLastYear = () => {
    monthYearA.value = monthYearB.value - 1;
    monthA.value = monthB.value;
};

const fetchSummary = async () => {
    if (!props.hasCompanySelected) return;

    loading.value = true;
    errorMessage.value = '';

    const periodA = mode.value === 'year'
        ? { type: 'year', year: yearA.value }
        : { type: 'month', year: monthYearA.value, month: monthA.value };
    const periodB = mode.value === 'year'
        ? { type: 'year', year: yearB.value }
        : { type: 'month', year: monthYearB.value, month: monthB.value };

    const params = {
        department_key: departmentKey.value,
        consolidate_clients: consolidateClients.value ? 1 : 0,
        'period_a[type]': periodA.type,
        'period_a[year]': periodA.year,
        'period_b[type]': periodB.type,
        'period_b[year]': periodB.year,
    };
    if (periodA.type === 'month') params['period_a[month]'] = periodA.month;
    if (periodB.type === 'month') params['period_b[month]'] = periodB.month;

    try {
        const response = await axios.get(route(rn('api.side_by_side_comparison')), { params });
        summary.value = response.data;
    } catch (e) {
        errorMessage.value = 'データの取得に失敗しました。';
        summary.value = null;
    } finally {
        loading.value = false;
    }
};

const periodLengthMismatch = computed(() => {
    if (!summary.value) return false;
    const a = summary.value.period_a;
    const b = summary.value.period_b;
    return a.registered_month_count !== b.registered_month_count;
});

watch([departmentKey, consolidateClients, mode, yearA, yearB, monthYearA, monthA, monthYearB, monthB], fetchSummary);
fetchSummary();
</script>

<template>
    <AppLayout title="左右比較">
        <template #header>
            <div class="flex items-center gap-3">
                <Link
                    :href="route(rn('dashboard'))"
                    class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300 whitespace-nowrap"
                >← データ登録状況</Link>
                <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">左右比較</h2>
            </div>
        </template>
        <template #tabs>
            <SalesAnalysisNavigationTabs :route-prefix="routePrefix" active="side_by_side" />
        </template>

        <div v-if="!hasCompanySelected" class="rounded bg-white p-6 shadow">
            <p class="text-sm text-gray-500">会社が選択されていません。画面右上の会社切替から対象の会社を選択してください。</p>
        </div>

        <div v-else class="space-y-6">
            <!-- フィルタ -->
            <div class="rounded bg-white p-4 shadow">
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-500">部署</label>
                        <select v-model="departmentKey" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                            <option v-for="key in enabledDepartmentKeys" :key="key" :value="key">{{ departmentLabels[key] }}</option>
                            <option value="all">全部署合計</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500">比較モード</label>
                        <div class="mt-1 flex gap-2">
                            <button
                                type="button"
                                class="flex-1 rounded-md border px-2 py-1.5 text-sm"
                                :class="mode === 'year' ? 'border-indigo-600 bg-indigo-50 text-indigo-700 font-semibold' : 'border-gray-300 text-gray-600 hover:bg-gray-50'"
                                @click="mode = 'year'"
                            >年対年</button>
                            <button
                                type="button"
                                class="flex-1 rounded-md border px-2 py-1.5 text-sm"
                                :class="mode === 'month' ? 'border-indigo-600 bg-indigo-50 text-indigo-700 font-semibold' : 'border-gray-300 text-gray-600 hover:bg-gray-50'"
                                @click="mode = 'month'"
                            >月対月</button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500">得意先統合</label>
                        <div class="mt-1 flex gap-2">
                            <button
                                type="button"
                                class="flex-1 rounded-md border px-2 py-1.5 text-sm"
                                :class="!consolidateClients ? 'border-indigo-600 bg-indigo-50 text-indigo-700 font-semibold' : 'border-gray-300 text-gray-600 hover:bg-gray-50'"
                                @click="consolidateClients = false"
                            >OFF</button>
                            <button
                                type="button"
                                class="flex-1 rounded-md border px-2 py-1.5 text-sm"
                                :class="consolidateClients ? 'border-indigo-600 bg-indigo-50 text-indigo-700 font-semibold' : 'border-gray-300 text-gray-600 hover:bg-gray-50'"
                                @click="consolidateClients = true"
                            >ON</button>
                        </div>
                    </div>
                </div>

                <div class="mt-4 border-t border-gray-100 pt-4">
                    <div v-if="mode === 'year'" class="flex flex-wrap items-center gap-3">
                        <span class="text-xs font-semibold text-gray-500">A</span>
                        <input v-model.number="yearA" type="number" class="w-24 rounded-md border-gray-300 text-sm shadow-sm" />年
                        <span class="text-gray-400">対</span>
                        <span class="text-xs font-semibold text-gray-500">B</span>
                        <input v-model.number="yearB" type="number" class="w-24 rounded-md border-gray-300 text-sm shadow-sm" />年
                    </div>
                    <div v-else class="flex flex-wrap items-center gap-3">
                        <span class="text-xs font-semibold text-gray-500">A</span>
                        <input v-model.number="monthYearA" type="number" class="w-24 rounded-md border-gray-300 text-sm shadow-sm" />年
                        <select v-model.number="monthA" class="rounded-md border-gray-300 text-sm shadow-sm">
                            <option v-for="(label, idx) in monthLabels" :key="idx" :value="idx + 1">{{ label }}</option>
                        </select>
                        <span class="text-gray-400">対</span>
                        <span class="text-xs font-semibold text-gray-500">B</span>
                        <input v-model.number="monthYearB" type="number" class="w-24 rounded-md border-gray-300 text-sm shadow-sm" />年
                        <select v-model.number="monthB" class="rounded-md border-gray-300 text-sm shadow-sm">
                            <option v-for="(label, idx) in monthLabels" :key="idx" :value="idx + 1">{{ label }}</option>
                        </select>
                        <button type="button" class="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs text-gray-600 hover:bg-gray-50" @click="useSameMonthLastYear">
                            同月前年にする
                        </button>
                    </div>
                </div>
            </div>

            <p v-if="errorMessage" class="rounded bg-red-50 p-3 text-sm text-red-700">{{ errorMessage }}</p>
            <p v-if="loading" class="text-sm text-gray-500">読み込み中...</p>

            <template v-if="summary">
                <!-- KPI -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="rounded bg-white p-4 shadow">
                        <p class="text-xs text-gray-500">A: {{ summary.period_a.label }}</p>
                        <p class="mt-1 text-xl font-bold text-gray-900">{{ yen(summary.period_a.amount) }}</p>
                        <p class="text-xs text-gray-400">
                            {{ summary.period_a.order_count ?? '—' }}件 / 平均{{ yen(summary.period_a.avg_order_amount) }}
                            <span v-if="summary.period_a.type === 'year'">（{{ summary.period_a.registered_month_count }}/{{ summary.period_a.total_month_count }}ヶ月）</span>
                        </p>
                    </div>
                    <div class="rounded bg-white p-4 shadow">
                        <p class="text-xs text-gray-500">B: {{ summary.period_b.label }}</p>
                        <p class="mt-1 text-xl font-bold text-gray-900">{{ yen(summary.period_b.amount) }}</p>
                        <p class="text-xs text-gray-400">
                            {{ summary.period_b.order_count ?? '—' }}件 / 平均{{ yen(summary.period_b.avg_order_amount) }}
                            <span v-if="summary.period_b.type === 'year'">（{{ summary.period_b.registered_month_count }}/{{ summary.period_b.total_month_count }}ヶ月）</span>
                        </p>
                    </div>
                    <div class="rounded bg-white p-4 shadow">
                        <p class="text-xs text-gray-500">差額（B − A）</p>
                        <p class="mt-1 text-xl font-bold" :class="pctClass(summary.diff.rate)">
                            {{ summary.diff.amount !== null ? yen(summary.diff.amount) : '—' }}
                            <span class="text-sm">{{ summary.diff.rate !== null ? `(${pct(summary.diff.rate)})` : '' }}</span>
                        </p>
                        <p class="text-xs text-gray-400">受注 {{ summary.diff.order_count !== null ? (summary.diff.order_count > 0 ? '+' : '') + summary.diff.order_count : '—' }}件</p>
                    </div>
                </div>

                <p v-if="periodLengthMismatch" class="rounded bg-amber-50 p-3 text-xs text-amber-700">
                    A・Bで登録済み月数が異なります（A: {{ summary.period_a.registered_month_count }}/{{ summary.period_a.total_month_count }}ヶ月、
                    B: {{ summary.period_b.registered_month_count }}/{{ summary.period_b.total_month_count }}ヶ月）。
                    それぞれの登録済み実績をそのまま合算した差額です。
                </p>

                <!-- 得意先別 -->
                <div class="rounded bg-white p-4 shadow">
                    <h3 class="mb-3 text-sm font-semibold text-gray-900">得意先別（上位15社＋その他、Bの金額降順）</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-xs">
                            <thead>
                                <tr class="text-left text-gray-500">
                                    <th class="py-1">得意先</th>
                                    <th class="py-1 text-right">Aの金額</th>
                                    <th class="py-1 text-right">Bの金額</th>
                                    <th class="py-1 text-right">差額</th>
                                    <th class="py-1 text-right">増減率</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="c in summary.clients.rows" :key="c.client_name" class="border-t">
                                    <td class="py-1">{{ c.client_name }}</td>
                                    <td class="py-1 text-right">{{ yen(c.amount_a) }}</td>
                                    <td class="py-1 text-right">{{ yen(c.amount_b) }}</td>
                                    <td class="py-1 text-right" :class="pctClass(c.rate)">{{ yen(c.diff) }}</td>
                                    <td class="py-1 text-right" :class="pctClass(c.rate)">{{ c.rate !== null ? pct(c.rate) : (c.amount_a === 0 ? '新規' : '—') }}</td>
                                </tr>
                                <tr v-if="summary.clients.others_amount_a > 0 || summary.clients.others_amount_b > 0" class="border-t text-gray-400">
                                    <td class="py-1">その他（{{ summary.clients.all_count - summary.clients.rows.length }}社）</td>
                                    <td class="py-1 text-right">{{ yen(summary.clients.others_amount_a) }}</td>
                                    <td class="py-1 text-right">{{ yen(summary.clients.others_amount_b) }}</td>
                                    <td class="py-1 text-right">{{ yen(summary.clients.others_amount_b - summary.clients.others_amount_a) }}</td>
                                    <td class="py-1"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <div class="rounded bg-white p-4 shadow">
                        <h3 class="mb-3 text-sm font-semibold text-gray-900">分類別</h3>
                        <table class="min-w-full text-xs">
                            <thead>
                                <tr class="text-left text-gray-500">
                                    <th class="py-1">分類</th>
                                    <th class="py-1 text-right">A</th>
                                    <th class="py-1 text-right">B</th>
                                    <th class="py-1 text-right">増減率</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in summary.categories" :key="row.label" class="border-t">
                                    <td class="py-1">{{ row.label }}</td>
                                    <td class="py-1 text-right">{{ yen(row.amount_a) }}</td>
                                    <td class="py-1 text-right">{{ yen(row.amount_b) }}</td>
                                    <td class="py-1 text-right" :class="pctClass(row.rate)">{{ row.rate !== null ? pct(row.rate) : '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="rounded bg-white p-4 shadow">
                        <h3 class="mb-3 text-sm font-semibold text-gray-900">項目別</h3>
                        <table class="min-w-full text-xs">
                            <thead>
                                <tr class="text-left text-gray-500">
                                    <th class="py-1">項目</th>
                                    <th class="py-1 text-right">A</th>
                                    <th class="py-1 text-right">B</th>
                                    <th class="py-1 text-right">増減率</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in summary.items" :key="row.label" class="border-t">
                                    <td class="py-1">{{ row.label }}</td>
                                    <td class="py-1 text-right">{{ yen(row.amount_a) }}</td>
                                    <td class="py-1 text-right">{{ yen(row.amount_b) }}</td>
                                    <td class="py-1 text-right" :class="pctClass(row.rate)">{{ row.rate !== null ? pct(row.rate) : '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
