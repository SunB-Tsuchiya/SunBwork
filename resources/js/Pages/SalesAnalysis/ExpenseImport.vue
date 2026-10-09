<script setup>
import { ref } from 'vue';
import SalesAnalysisShell from '@/Components/SalesAnalysis/SalesAnalysisShell.vue';
import { Link } from '@inertiajs/vue3';
import axios from 'axios';
import { useSalesChart } from '@/Composables/useSalesChart';

const { fiscalPeriodLabel } = useSalesChart();

// 部署別経費表（情報出版/製版/オンデマンド/共有の4シート）の取込画面（Phase 21）。
// 売上Excelと異なり1ファイル＝1会計年度＝全社分のため、プレビュー/確定の2段階は設けず、
// アップロードと同時に検証・確定する単一ステップにしている。

const props = defineProps({
    routePrefix: { type: String, required: true },
    hasCompanySelected: { type: Boolean, default: true },
    supportsExpenses: { type: Boolean, default: false },
});

const rn = (name) => `${props.routePrefix}.sales_analysis.${name}`;

const nowMonth = new Date().getMonth() + 1; // 1-12
const defaultFiscalYear = new Date().getFullYear() - (nowMonth >= 4 ? 0 : 1);

const fiscalYear = ref(defaultFiscalYear);
const fileInput = ref(null);
const selectedFile = ref(null);
const fileName = ref('');
const forceReimport = ref(false);

const submitting = ref(false);
const submitError = ref('');
// 同一内容チェック／タイトル行の年度不一致チェックで拒否された直後だけ「再取込む」チェックボックスを目立たせる
const isOverridableError = ref(false);
const result = ref(null);

const selectFile = () => fileInput.value?.click();

const onFileChange = (e) => {
    const file = e.target.files[0];
    selectedFile.value = file ?? null;
    fileName.value = file?.name ?? '';
    result.value = null;
    submitError.value = '';
    isOverridableError.value = false;
    forceReimport.value = false;
};

const canSubmit = () => !!selectedFile.value && !!fiscalYear.value;

const submit = async () => {
    if (!canSubmit() || submitting.value) return;

    submitting.value = true;
    submitError.value = '';
    isOverridableError.value = false;
    result.value = null;

    const formData = new FormData();
    formData.append('file', selectedFile.value);
    formData.append('fiscal_year', fiscalYear.value);
    if (forceReimport.value) formData.append('force', '1');

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const response = await axios.post(route(rn('expense_import.store')), formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
                'X-CSRF-TOKEN': csrfToken,
            },
        });
        result.value = response.data;
        selectedFile.value = null;
        fileName.value = '';
        forceReimport.value = false;
    } catch (e) {
        const message = e.response?.data?.message ?? '';
        submitError.value = message || '取込処理に失敗しました。';
        isOverridableError.value = message.includes('同一内容のファイルが既に取り込まれています') || message.includes('年度が一致しません');
    } finally {
        submitting.value = false;
    }
};
</script>

<template>
    <SalesAnalysisShell title="部署別経費取込" :route-prefix="routePrefix" active="import_hub">
        <div v-if="!hasCompanySelected" class="rounded bg-white p-6 shadow">
            <p class="text-sm text-gray-500">会社が選択されていません。画面右上の会社切替から対象の会社を選択してください。</p>
        </div>

        <div v-else-if="!supportsExpenses" class="rounded bg-white p-6 shadow">
            <p class="text-sm text-gray-500">この会社では経費取込機能は利用できません。</p>
        </div>

        <div v-else class="rounded bg-white px-4 py-6 sm:p-6 shadow">
            <div class="mb-8 rounded-lg bg-gray-50 p-4 text-sm text-gray-700">
                <p>部署別経費表（情報出版・製版・オンデマンド・共有の4シート）を取り込みます。</p>
                <p class="mt-1 text-xs text-gray-500">
                    1ファイルで1会計年度（4月〜翌3月）・全部署分を取り込みます。同じ会計年度を再度アップロードすると、
                    新しい版に全体が差し替わります（旧版は履歴として残ります）。
                </p>
                <p class="mt-1 text-xs text-gray-500">
                    粗利計算は行いません。期別分析画面に「経費」として参考情報を並べて表示するだけです。
                </p>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700">① 対象会計年度（4月始まり）</label>
                <div class="mt-2 flex items-center gap-2">
                    <input
                        v-model.number="fiscalYear"
                        type="number"
                        class="block w-32 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <span class="text-sm text-gray-500">{{ fiscalPeriodLabel(fiscalYear) }}（{{ fiscalYear }}年4月〜{{ fiscalYear + 1 }}年3月）</span>
                </div>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700">② Excelファイル（.xlsx）を選択</label>
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
            </div>

            <div class="mb-4 rounded-md p-2" :class="isOverridableError ? 'bg-amber-50 ring-1 ring-amber-300' : ''">
                <label class="flex items-start gap-2 text-xs text-gray-600">
                    <input v-model="forceReimport" type="checkbox" class="mt-0.5 rounded border-gray-300" />
                    <span>
                        警告があっても取り込む（①同一内容のファイルの再取込み、②タイトル行の年度と指定した会計年度の不一致、のいずれかで止められた場合に使用。
                        通常は意図した会計年度を確認してからチェックしてください。新しい版として追加され、旧版は履歴として残ります）
                    </span>
                </label>
            </div>

            <div class="mb-6">
                <button
                    type="button"
                    :disabled="!canSubmit() || submitting"
                    class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-bold text-white hover:bg-indigo-700 disabled:opacity-40"
                    @click="submit"
                >
                    {{ submitting ? '取込中...' : '③ 取込を実行する' }}
                </button>
            </div>

            <p v-if="submitError" class="mb-6 rounded bg-red-50 p-3 text-sm text-red-700">{{ submitError }}</p>

            <div v-if="result" class="rounded-lg border border-green-300 bg-green-50 p-4">
                <p class="text-sm font-semibold text-green-800">
                    取込を完了しました（{{ fiscalPeriodLabel(result.fiscal_year) }} 版v{{ result.version }}）
                </p>
                <div class="mt-3 grid grid-cols-2 gap-3 text-sm sm:grid-cols-3">
                    <div><span class="text-gray-500">科目数:</span> {{ result.category_count }}</div>
                    <div><span class="text-gray-500">明細件数:</span> {{ result.line_item_count }}</div>
                    <div><span class="text-gray-500">合計金額:</span> ¥{{ Number(result.total_amount).toLocaleString() }}</div>
                </div>
                <div v-if="result.warnings?.length" class="mt-3">
                    <p class="text-xs font-semibold text-amber-700">警告</p>
                    <ul class="mt-1 list-disc pl-5 text-xs text-amber-700">
                        <li v-for="(w, i) in result.warnings" :key="i">{{ w }}</li>
                    </ul>
                </div>
                <Link :href="route(rn('fiscal_year_analysis'))" class="mt-3 inline-flex items-center rounded-md bg-green-600 px-4 py-2 text-sm font-bold text-white hover:bg-green-700">
                    期別分析で確認する
                </Link>
            </div>
        </div>
    </SalesAnalysisShell>
</template>
