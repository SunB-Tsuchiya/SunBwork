<script setup>
import { computed, onMounted, ref, watch } from 'vue';
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

const departmentKey = ref(props.enabledDepartmentKeys[0] ?? 'planning');
const loading = ref(false);
const errorMessage = ref('');
const years = ref([]);

// 年度行を開いたときのファイル一覧（year -> files[]）
const expandedYears = ref(new Set());
const filesByYear = ref({});
const filesLoading = ref({});

const monthLabels = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'];

const hasAnyDataAcrossAllDepartments = computed(() => years.value.length > 0);

const fetchStatus = async () => {
    if (!props.hasCompanySelected) return;

    loading.value = true;
    errorMessage.value = '';
    expandedYears.value = new Set();
    filesByYear.value = {};

    try {
        const response = await axios.get(route(rn('api.registration_status')), {
            params: { department_key: departmentKey.value },
        });
        years.value = response.data.years;
    } catch (e) {
        errorMessage.value = 'データの取得に失敗しました。';
        years.value = [];
    } finally {
        loading.value = false;
    }
};

const toggleYear = async (year) => {
    if (expandedYears.value.has(year)) {
        expandedYears.value.delete(year);
        expandedYears.value = new Set(expandedYears.value);
        return;
    }

    expandedYears.value = new Set(expandedYears.value).add(year);

    if (filesByYear.value[year]) return;

    filesLoading.value = { ...filesLoading.value, [year]: true };
    try {
        const response = await axios.get(route(rn('api.registration_status.files')), {
            params: { department_key: departmentKey.value, year },
        });
        filesByYear.value = { ...filesByYear.value, [year]: response.data.files };
    } catch (e) {
        filesByYear.value = { ...filesByYear.value, [year]: [] };
    } finally {
        filesLoading.value = { ...filesLoading.value, [year]: false };
    }
};

const yen = (v) => (v === null || v === undefined ? '—' : `¥${Number(v).toLocaleString()}`);

const cellClass = (m) => {
    if (m.state === 'future') return 'bg-gray-50 text-gray-300';
    if (m.state === 'no_data') return 'bg-gray-200 text-gray-500';
    if (m.state === 'zero') return 'bg-sky-100 text-sky-700';
    return 'bg-green-100 text-green-800'; // has_sales
};

const cellTitle = (m) => {
    if (m.state === 'future') return 'まだ来ていない月';
    if (m.state === 'no_data') return '未登録';
    const parts = [`売上: ${yen(m.amount)}`, `受注件数: ${m.order_count ?? 0}件`];
    if (m.needs_review) parts.push('この月は複数回取込まれています（現在の有効版を確認してください）');
    if (m.has_issue) parts.push(`明細合計と受注金額に差額があります（未配賦額 ${yen(m.issue_amount)}）`);
    return parts.join(' / ');
};

const registrationLabel = (year) => {
    if (year.registered_month_count >= year.total_due_month_count && year.total_due_month_count > 0) {
        return `${year.registered_month_count}/12 登録済み`;
    }
    const missing = year.total_due_month_count - year.registered_month_count;
    return `${year.registered_month_count}/12（${missing}ヶ月欠落）`;
};

const periodLabelForFile = (file) => file.period_label;

watch(departmentKey, fetchStatus);
onMounted(fetchStatus);
</script>

<template>
    <AppLayout title="データ登録状況">
        <template #header>
            <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">売上分析 - データ登録状況</h2>
        </template>
        <template #tabs>
            <SalesAnalysisNavigationTabs :route-prefix="routePrefix" active="dashboard" />
        </template>

        <div class="space-y-4">
            <!-- 部署タブ -->
            <div class="flex gap-2 border-b border-gray-200">
                <button
                    v-for="key in enabledDepartmentKeys"
                    :key="key"
                    type="button"
                    class="border-b-2 px-4 py-2 text-sm font-semibold"
                    :class="departmentKey === key ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                    @click="departmentKey = key"
                >
                    {{ departmentLabels[key] }}
                </button>
            </div>

            <p v-if="errorMessage" class="rounded bg-red-50 p-3 text-sm text-red-700">{{ errorMessage }}</p>
            <p v-if="loading" class="text-sm text-gray-500">読み込み中...</p>

            <div v-else-if="!hasCompanySelected" class="rounded bg-white p-6 shadow">
                <p class="text-sm text-gray-500">会社が選択されていません。画面右上の会社切替から対象の会社を選択してください。</p>
            </div>

            <div v-else-if="!hasAnyDataAcrossAllDepartments" class="rounded bg-white p-6 shadow">
                <p class="text-sm text-gray-500">
                    {{ departmentLabels[departmentKey] }}の取込データはまだありません。まずはExcelを取り込んでください。
                </p>
                <Link :href="route(rn('import.create'))" class="mt-4 inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-bold text-white hover:bg-indigo-700">
                    Excel取込へ
                </Link>
            </div>

            <div v-else class="rounded bg-white shadow">
                <!-- 凡例 -->
                <div class="flex flex-wrap items-center gap-4 border-b border-gray-100 px-4 py-2 text-xs text-gray-500">
                    <span class="flex items-center gap-1"><span class="h-3 w-3 rounded bg-green-100"></span>登録済み・売上あり</span>
                    <span class="flex items-center gap-1"><span class="h-3 w-3 rounded bg-sky-100"></span>登録済み・売上0円</span>
                    <span class="flex items-center gap-1"><span class="h-3 w-3 rounded bg-gray-200"></span>未登録</span>
                    <span class="flex items-center gap-1"><span class="h-3 w-3 rounded bg-gray-50"></span>まだ来ていない月</span>
                    <span class="flex items-center gap-1">⚠<span>複数回取込あり</span></span>
                    <span class="flex items-center gap-1">🔺<span>明細と受注金額に差額あり</span></span>
                </div>

                <div class="divide-y divide-gray-100">
                    <div v-for="year in years" :key="year.year">
                        <div class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex flex-1 flex-col gap-2 sm:flex-row sm:items-center sm:gap-4">
                                <button type="button" class="w-40 shrink-0 text-left" @click="toggleYear(year.year)">
                                    <span class="text-sm font-bold text-gray-900">{{ expandedYears.has(year.year) ? '▼' : '▶' }} {{ year.year }}年</span>
                                    <span class="ml-2 text-xs text-gray-500">{{ registrationLabel(year) }}</span>
                                </button>

                                <div class="grid grid-cols-12 gap-1">
                                    <component
                                        :is="m.state === 'future' ? 'div' : Link"
                                        v-for="m in year.months"
                                        :key="m.month"
                                        :href="m.state === 'future' ? undefined : route(rn('monthly_analysis')) + `?department_key=${departmentKey}&year=${year.year}&month=${m.month}`"
                                        class="relative flex h-8 w-8 items-center justify-center rounded text-[10px] font-semibold"
                                        :class="[cellClass(m), m.state !== 'future' ? 'cursor-pointer hover:ring-2 hover:ring-indigo-400' : '']"
                                        :title="cellTitle(m)"
                                    >
                                        {{ monthLabels[m.month - 1] }}
                                        <span v-if="m.needs_review" class="absolute -right-1 -top-1 text-[10px]">⚠</span>
                                        <span v-if="m.has_issue" class="absolute -bottom-1 -right-1 text-[10px]">🔺</span>
                                    </component>
                                </div>
                            </div>

                            <div class="flex items-center gap-4 text-xs text-gray-600 sm:text-sm">
                                <span>{{ yen(year.total_amount) }}</span>
                                <span>{{ year.order_count }}件</span>
                                <span>ファイル{{ year.file_count }}件</span>
                                <span v-if="year.latest_registration" class="text-gray-400">
                                    最終登録: {{ year.latest_registration.at?.slice(0, 10) }} {{ year.latest_registration.by }}
                                </span>
                                <Link
                                    :href="route(rn('annual_analysis')) + `?department_key=${departmentKey}&year=${year.year}`"
                                    class="rounded-md bg-indigo-600 px-3 py-1 text-xs font-bold text-white hover:bg-indigo-700"
                                >
                                    年次分析へ
                                </Link>
                            </div>
                        </div>

                        <div v-if="expandedYears.has(year.year)" class="bg-gray-50 px-4 py-3">
                            <p v-if="filesLoading[year.year]" class="text-xs text-gray-500">読み込み中...</p>
                            <table v-else-if="filesByYear[year.year]?.length" class="min-w-full text-xs">
                                <thead>
                                    <tr class="text-left text-gray-500">
                                        <th class="py-1">ファイル名</th>
                                        <th class="py-1">対象期間</th>
                                        <th class="py-1">版</th>
                                        <th class="py-1">現在有効</th>
                                        <th class="py-1">登録日時</th>
                                        <th class="py-1">担当</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="file in filesByYear[year.year]" :key="file.sales_import_id" class="border-t border-gray-200">
                                        <td class="py-1">{{ file.original_filename }}</td>
                                        <td class="py-1">{{ periodLabelForFile(file) }}</td>
                                        <td class="py-1">v{{ file.version }}</td>
                                        <td class="py-1">
                                            <span :class="file.is_fully_active ? 'text-green-700' : 'text-amber-600'">
                                                {{ file.active_month_count }}/{{ file.total_month_count }}有効
                                            </span>
                                        </td>
                                        <td class="py-1">{{ file.imported_at?.slice(0, 16).replace('T', ' ') }}</td>
                                        <td class="py-1">{{ file.imported_by }}</td>
                                    </tr>
                                </tbody>
                            </table>
                            <p v-else class="text-xs text-gray-500">ファイルがありません。</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
