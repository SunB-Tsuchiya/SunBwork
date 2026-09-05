<script setup>
import { computed, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import axios from 'axios';
import SalesAnalysisNavigationTabs from '@/Components/SalesAnalysis/SalesAnalysisNavigationTabs.vue';

const props = defineProps({
    routePrefix: { type: String, required: true },
    departmentLabels: { type: Object, default: () => ({}) },
    enabledDepartmentKeys: { type: Array, default: () => [] },
});

// 売上分析ルートは superadmin/admin/clerk の各ロールグループ内に複製登録されている
const rn = (name) => `${props.routePrefix}.sales_analysis.${name}`;

const departmentKey = ref(props.enabledDepartmentKeys[0] ?? 'planning');
const sourceType = ref('monthly');
// 年月はファイルのタイトル行から自動入力する運用のため、既定値は持たせない
const sourceYear = ref(null);
const sourceMonth = ref(null);
const sourceMonthEnd = ref(null);

const fileInput = ref(null);
const selectedFile = ref(null);
const fileName = ref('');
const inspectedDepartmentLabel = ref('');
const inspectError = ref('');

const submitting = ref(false);
const confirming = ref(false);
const result = ref(null);
const submitError = ref('');
const confirmError = ref('');
const confirmedImport = ref(null);

// エラーのある受注を個別に除外して再検証するための状態
const excludedOrderNumbers = ref([]);
const checkedInvalidOrders = ref([]);

const selectFile = () => fileInput.value?.click();

const resolveDepartmentKey = (label) => {
    const entry = Object.entries(props.departmentLabels).find(([, l]) => l === label);
    return entry ? entry[0] : null;
};

const escapeRegExp = (s) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

// ファイル名（例: 企画_2026年08月.xlsx / 企画_2026年.xlsx / 企画_2026年01-06月.xlsx）から
// 対象部署・種別・年・月を解析する。Excel内部のタイトル行は部署間で記載が不統一なため
// 自動入力の情報源には使わない（取込時の整合性チェックではサーバー側で引き続き使用する）。
const parseFileName = (name) => {
    const base = name.replace(/\.xlsx$/i, '');
    const labels = Object.values(props.departmentLabels);
    if (labels.length === 0) return null;

    const labelPattern = [...labels].sort((a, b) => b.length - a.length).map(escapeRegExp).join('|');
    const re = new RegExp(`^(${labelPattern})_(\\d{4})年(?:(\\d{1,2})-(\\d{1,2})月|(\\d{1,2})月)?$`);
    const m = base.match(re);
    if (!m) return null;

    const [, label, year, rangeStart, rangeEnd, singleMonth] = m;
    if (rangeStart && rangeEnd) {
        return { label, year: Number(year), sourceType: 'range', month: Number(rangeStart), monthEnd: Number(rangeEnd) };
    }
    if (singleMonth) {
        return { label, year: Number(year), sourceType: 'monthly', month: Number(singleMonth), monthEnd: null };
    }
    return { label, year: Number(year), sourceType: 'annual', month: null, monthEnd: null };
};

// ファイル選択直後にファイル名を解析し、対象部署・種別・年月フォームへ自動反映する。
// フォーム自体もファイル選択後に表示することで、「先に手入力しないといけない」誤解を防ぐ。
const onFileChange = (e) => {
    const file = e.target.files[0];
    if (!file) return;

    selectedFile.value = file;
    fileName.value = file.name;
    result.value = null;
    submitError.value = '';
    inspectError.value = '';
    inspectedDepartmentLabel.value = '';
    sourceYear.value = null;
    sourceMonth.value = null;
    sourceMonthEnd.value = null;
    excludedOrderNumbers.value = [];
    checkedInvalidOrders.value = [];

    const parsed = parseFileName(file.name);
    if (parsed) {
        sourceType.value = parsed.sourceType;
        sourceYear.value = parsed.year;
        sourceMonth.value = parsed.month;
        sourceMonthEnd.value = parsed.monthEnd ?? parsed.month;
        inspectedDepartmentLabel.value = parsed.label;

        const key = resolveDepartmentKey(parsed.label);
        if (key && props.enabledDepartmentKeys.includes(key)) {
            departmentKey.value = key;
        }
    } else {
        inspectError.value = 'ファイル名から対象部署・年月を自動読取できませんでした。命名規則に沿ったファイル名に変更するか、下記の項目を入力してください。';
    }
};

const canSubmit = computed(() => {
    if (!selectedFile.value) return false;
    if (!sourceYear.value) return false;
    if ((sourceType.value === 'monthly' || sourceType.value === 'range') && !sourceMonth.value) return false;
    if (sourceType.value === 'range' && (!sourceMonthEnd.value || sourceMonthEnd.value < sourceMonth.value)) return false;
    return true;
});

const submit = async () => {
    if (!canSubmit.value || submitting.value) return;

    submitting.value = true;
    result.value = null;
    submitError.value = '';
    confirmError.value = '';
    confirmedImport.value = null;

    const formData = new FormData();
    formData.append('file', selectedFile.value);
    formData.append('department_key', departmentKey.value);
    formData.append('source_type', sourceType.value);
    formData.append('source_year', sourceYear.value);
    if (sourceType.value === 'monthly' || sourceType.value === 'range') {
        formData.append('source_month', sourceMonth.value);
    }
    if (sourceType.value === 'range') {
        formData.append('source_month_end', sourceMonthEnd.value);
    }
    excludedOrderNumbers.value.forEach((orderNumber) => {
        formData.append('excluded_order_numbers[]', orderNumber);
    });

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const response = await axios.post(route(rn('import.preview')), formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
                'X-CSRF-TOKEN': csrfToken,
            },
        });
        result.value = response.data;
        checkedInvalidOrders.value = [];
    } catch (e) {
        if (e.response?.status === 422 && e.response.data?.errors) {
            const messages = Object.values(e.response.data.errors).flat();
            submitError.value = messages.join(' / ');
        } else {
            submitError.value = '検証処理に失敗しました。';
        }
    } finally {
        submitting.value = false;
    }
};

// エラーのある受注のうちチェックしたものを除外リストへ加え、同じファイルで再検証する
const retryExcludingChecked = async () => {
    if (checkedInvalidOrders.value.length === 0) return;

    excludedOrderNumbers.value = [...new Set([...excludedOrderNumbers.value, ...checkedInvalidOrders.value])];
    await submit();
};

const confirmImport = async () => {
    if (!result.value?.preview_token || confirming.value) return;

    confirming.value = true;
    confirmError.value = '';

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const response = await axios.post(
            route(rn('import.store')),
            { preview_token: result.value.preview_token },
            { headers: { 'X-CSRF-TOKEN': csrfToken } }
        );
        confirmedImport.value = response.data;
        result.value = null;
    } catch (e) {
        confirmError.value = e.response?.data?.message ?? '確定処理に失敗しました。';
    } finally {
        confirming.value = false;
    }
};
</script>

<template>
    <AppLayout title="売上Excel取込">
        <template #header>
            <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">売上Excel取込</h2>
        </template>
        <template #tabs>
            <SalesAnalysisNavigationTabs :route-prefix="routePrefix" active="import" />
        </template>

        <div class="rounded bg-white px-4 py-6 sm:p-6 shadow">
            <div class="mb-8">
                <h3 class="mb-3 text-sm font-semibold text-gray-900">ファイル名の例</h3>
                <div class="rounded-lg bg-gray-50 p-4 text-sm text-gray-700">
                    <p>月次: <span class="font-mono">企画_2026年08月.xlsx</span>（1ヶ月分の明細）</p>
                    <p class="mt-1">年次: <span class="font-mono">企画_2026年.xlsx</span>（1シートに1〜12月分の明細）</p>
                    <p class="mt-1">範囲指定（半期など）: <span class="font-mono">企画_2026年01-06月.xlsx</span>（1シートに複数月分の明細）</p>
                    <p class="mt-3 text-xs text-gray-500">
                        対象部署・対象年月は、<strong>このファイル名から自動入力</strong>されます。上記の命名規則に沿ったファイル名にしてください
                        （Excel内部のタイトル行は部署・担当者により記載が不統一なため、自動入力には使用しません。取込時の内容整合性チェックには引き続き使用します）。
                    </p>
                </div>
            </div>

            <!-- ファイル選択（常に最初に表示） -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700">① Excelファイル（.xlsx）を選択</label>
                <div class="mt-2 flex items-center gap-4">
                    <input ref="fileInput" type="file" accept=".xlsx" class="hidden" @change="onFileChange" />
                    <button
                        type="button"
                        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm hover:bg-gray-50"
                        @click="selectFile"
                    >
                        📁 ファイルを選択
                    </button>
                    <span v-if="fileName" class="text-sm text-gray-600">{{ fileName }}</span>
                    <span v-else class="text-sm text-gray-500">ファイルが選択されていません</span>
                </div>
                <p v-if="inspectedDepartmentLabel" class="mt-2 text-xs text-green-700">
                    ファイル名から対象部署・年月を自動入力しました（判定部署: {{ inspectedDepartmentLabel }}）。② の内容をご確認ください。
                </p>
                <p v-else-if="inspectError" class="mt-2 text-xs text-amber-700">{{ inspectError }}</p>
            </div>

            <!-- ファイル選択後に自動入力された内容を表示・確認 -->
            <div v-if="selectedFile" class="mb-6">
                <label class="block text-sm font-medium text-gray-700">② 内容の確認（自動入力されています。必要であれば修正してください）</label>
                <div class="mt-2 grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <label class="block text-xs text-gray-500">対象部署</label>
                        <select v-model="departmentKey" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option v-for="key in enabledDepartmentKeys" :key="key" :value="key">
                                {{ departmentLabels[key] }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">種別</label>
                        <select v-model="sourceType" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="monthly">月次</option>
                            <option value="annual">年次</option>
                            <option value="range">範囲指定（半期など）</option>
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <div class="flex-1">
                            <label class="block text-xs text-gray-500">対象年</label>
                            <input
                                v-model.number="sourceYear"
                                type="number"
                                placeholder="未取得"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                        <div v-if="sourceType === 'monthly'" class="flex-1">
                            <label class="block text-xs text-gray-500">対象月</label>
                            <input
                                v-model.number="sourceMonth"
                                type="number"
                                min="1"
                                max="12"
                                placeholder="未取得"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                        <div v-if="sourceType === 'range'" class="flex-1">
                            <label class="block text-xs text-gray-500">開始月</label>
                            <input
                                v-model.number="sourceMonth"
                                type="number"
                                min="1"
                                max="12"
                                placeholder="未取得"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                        <div v-if="sourceType === 'range'" class="flex-1">
                            <label class="block text-xs text-gray-500">終了月</label>
                            <input
                                v-model.number="sourceMonthEnd"
                                type="number"
                                min="1"
                                max="12"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="selectedFile" class="mb-6">
                <button
                    type="button"
                    :disabled="!canSubmit || submitting"
                    class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-bold text-white hover:bg-indigo-700 disabled:opacity-40"
                    @click="submit"
                >
                    {{ submitting ? '検証中...' : '③ 検証してプレビュー' }}
                </button>
            </div>

            <p v-if="submitError" class="mb-6 rounded bg-red-50 p-3 text-sm text-red-700">{{ submitError }}</p>

            <div v-if="confirmedImport" class="mb-6 rounded-lg border border-green-300 bg-green-50 p-4">
                <p class="text-sm font-semibold text-green-800">取込を確定しました（{{ departmentLabels[confirmedImport.department_key] }} 版 v{{ confirmedImport.version }}）</p>
                <Link :href="route(rn('dashboard'))" class="mt-3 inline-flex items-center rounded-md bg-green-600 px-4 py-2 text-sm font-bold text-white hover:bg-green-700">
                    売上分析ダッシュボードで確認する
                </Link>
            </div>
            <p v-if="confirmError" class="mb-6 rounded bg-red-50 p-3 text-sm text-red-700">{{ confirmError }}</p>

            <div v-if="result" class="rounded-lg border p-4" :class="result.valid ? 'border-green-300 bg-green-50' : 'border-red-300 bg-red-50'">
                <p class="text-sm font-semibold" :class="result.valid ? 'text-green-800' : 'text-red-800'">
                    {{ result.valid ? '検証に成功しました' : '検証エラーがあります（確定できません）' }}
                </p>

                <div v-if="result.summary" class="mt-3 grid grid-cols-2 gap-3 text-sm sm:grid-cols-3">
                    <div><span class="text-gray-500">受注件数:</span> {{ result.summary.order_count }}</div>
                    <div><span class="text-gray-500">明細件数:</span> {{ result.summary.detail_count }}</div>
                    <div><span class="text-gray-500">合計金額:</span> ¥{{ Number(result.summary.total_amount).toLocaleString() }}</div>
                    <div v-if="Number(result.summary.total_unallocated_amount) !== 0" class="text-amber-700">
                        <span class="text-gray-500">未配賦額合計:</span> ¥{{ Number(result.summary.total_unallocated_amount).toLocaleString() }}
                    </div>
                </div>

                <div v-if="result.diff?.length" class="mt-4">
                    <p class="text-xs font-semibold text-gray-700">既存版との差分</p>
                    <div v-for="d in result.diff" :key="`${d.year}-${d.month}`" class="mt-2 rounded border border-gray-200 bg-white p-3 text-xs">
                        <p class="font-semibold text-gray-700">{{ d.year }}年{{ d.month }}月</p>
                        <p v-if="!d.has_existing" class="mt-1 text-gray-500">既存版はありません（新規取込）</p>
                        <div v-else class="mt-1 grid grid-cols-2 gap-1 text-gray-600 sm:grid-cols-3">
                            <span>既存版: v{{ d.existing_version }}</span>
                            <span>金額差: ¥{{ Number(d.amount_diff).toLocaleString() }}</span>
                            <span>追加/削除/変更: {{ d.added_order_count }} / {{ d.removed_order_count }} / {{ d.changed_order_count }}</span>
                        </div>
                    </div>
                </div>

                <div v-if="result.warnings?.length" class="mt-3">
                    <p class="text-xs font-semibold text-amber-700">警告</p>
                    <ul class="mt-1 list-disc pl-5 text-xs text-amber-700">
                        <li v-for="(w, i) in result.warnings" :key="i">{{ w }}</li>
                    </ul>
                </div>

                <div v-if="result.errors?.length" class="mt-3">
                    <p class="text-xs font-semibold text-red-700">エラー（ファイル全体を確定できません。Excelを修正してください）</p>
                    <ul class="mt-1 max-h-64 list-disc overflow-y-auto pl-5 text-xs text-red-700">
                        <li v-for="(err, i) in result.errors" :key="i">{{ err }}</li>
                    </ul>
                </div>

                <div v-if="result.invalid_orders?.length" class="mt-3 rounded border border-red-200 bg-white p-3">
                    <p class="text-xs font-semibold text-red-700">
                        エラーのある受注（{{ result.invalid_orders.length }}件）— チェックして除外すると、この受注だけ取込対象外（未保存）にして残りを確定できます
                    </p>
                    <ul class="mt-2 space-y-2">
                        <li v-for="o in result.invalid_orders" :key="o.order_number" class="text-xs text-gray-700">
                            <label class="flex items-start gap-2">
                                <input
                                    type="checkbox"
                                    :value="o.order_number"
                                    v-model="checkedInvalidOrders"
                                    class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                <span>
                                    <span class="font-semibold">受注No {{ o.order_number }}</span>
                                    <ul class="mt-0.5 list-disc pl-5 text-red-700">
                                        <li v-for="(err, i) in o.errors" :key="i">{{ err }}</li>
                                    </ul>
                                </span>
                            </label>
                        </li>
                    </ul>
                    <button
                        type="button"
                        :disabled="checkedInvalidOrders.length === 0 || submitting"
                        class="mt-3 inline-flex items-center rounded-md border border-red-300 bg-white px-3 py-1.5 text-xs font-semibold text-red-700 shadow-sm hover:bg-red-50 disabled:opacity-40"
                        @click="retryExcludingChecked"
                    >
                        {{ submitting ? '再検証中...' : `選択した${checkedInvalidOrders.length}件を除外して再検証` }}
                    </button>
                </div>

                <div v-if="result.excluded_orders?.length" class="mt-3 rounded border border-gray-200 bg-gray-50 p-3 text-xs text-gray-600">
                    <p class="font-semibold text-gray-700">指定により除外した受注No（{{ result.excluded_orders.length }}件・取込対象外）</p>
                    <p class="mt-1">{{ result.excluded_orders.join('、') }}</p>
                </div>

                <div class="mt-4">
                    <button
                        type="button"
                        :disabled="!result.valid || !result.preview_token || confirming"
                        class="inline-flex items-center rounded-md bg-green-600 px-4 py-2 text-sm font-bold text-white hover:bg-green-700 disabled:opacity-40"
                        @click="confirmImport"
                    >
                        {{ confirming ? '確定中...' : 'この内容で確定する' }}
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
