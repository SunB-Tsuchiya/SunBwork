<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import axios from 'axios';
import ReportShell from '@/Components/SunA/Sales/ReportShell.vue';

// 売上レポート（サンエー印刷）の取込。流れ: ① Excelを選ぶ → ② 内容と差分を確認（まだ取り込まない）→ ③ 取り込む。
// 取込のルール: 得意先×年の行単位で追加・更新する（ファイルに無い行は残す）。空欄の月は「未登録」。
// 「この年はファイルの内容で入れ替える」を選んだ年だけは、ファイルに無い得意先の行も削除する。取込ファイルは保存しない。
defineProps({
    maxUploadMb: { type: Number, default: 20 },
});

const fileInput = ref(null);
const selectedFile = ref(null);
const uploading = ref(false);
const confirming = ref(false);
const error = ref('');
const preview = ref(null);
const replaceYears = ref([]);
const result = ref(null);

const kindFilter = ref('all');
const nameFilter = ref('');

const csrfHeaders = () => ({ 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '', Accept: 'application/json' });

const selectFile = () => fileInput.value?.click();
const onFileChange = (e) => {
    selectedFile.value = e.target.files[0] ?? null;
    error.value = '';
};

const reset = () => {
    selectedFile.value = null;
    preview.value = null;
    replaceYears.value = [];
    result.value = null;
    error.value = '';
    if (fileInput.value) fileInput.value.value = '';
};

const messageOf = (e, fallback) => e.response?.data?.message ?? (e.response?.data?.errors ? Object.values(e.response.data.errors).flat()[0] : null) ?? fallback;

// ② 確認する（アップロード → 解析 → 差分プレビュー。まだデータは変わらない）
const runPreview = async () => {
    if (!selectedFile.value || uploading.value) return;
    uploading.value = true;
    error.value = '';
    result.value = null;
    try {
        const form = new FormData();
        form.append('file', selectedFile.value);
        const response = await axios.post(route('suna.sales.report.api.import.preview'), form, { headers: csrfHeaders() });
        preview.value = response.data;
        replaceYears.value = [];
    } catch (e) {
        preview.value = null;
        error.value = messageOf(e, '確認に失敗しました。');
    } finally {
        uploading.value = false;
    }
};

// 「入れ替える年」を変えたら、プレビューし直す（再アップロードは不要）
const refreshPreview = async () => {
    if (!preview.value) return;
    error.value = '';
    try {
        const response = await axios.post(route('suna.sales.report.api.import.repreview'), { token: preview.value.token, replace_years: replaceYears.value }, { headers: csrfHeaders() });
        preview.value = response.data;
    } catch (e) {
        error.value = messageOf(e, 'プレビューの更新に失敗しました。');
    }
};

const confirmImport = async () => {
    if (!preview.value?.can_confirm || confirming.value) return;
    confirming.value = true;
    error.value = '';
    try {
        const response = await axios.post(route('suna.sales.report.api.import.confirm'), { token: preview.value.token, replace_years: replaceYears.value }, { headers: csrfHeaders() });
        result.value = response.data;
        preview.value = null;
        selectedFile.value = null;
        if (fileInput.value) fileInput.value.value = '';
    } catch (e) {
        error.value = messageOf(e, '取込に失敗しました。');
    } finally {
        confirming.value = false;
    }
};

const cancelPreview = async () => {
    if (preview.value) {
        try { await axios.post(route('suna.sales.report.api.import.discard'), { token: preview.value.token }, { headers: csrfHeaders() }); } catch (e) { /* 期限切れでも画面は戻す */ }
    }
    reset();
};

// 差分の表示
const KIND_LABELS = { added: '追加', changed: '変更', removed: '削除' };
const KIND_CLASS = { added: 'bg-blue-100 text-blue-700', changed: 'bg-amber-100 text-amber-700', removed: 'bg-red-100 text-red-700' };
const FIELD_LABELS = { amount: '売上金額', order_count: '受注件数', department: '担当部門' };
const yen = (v) => (v === null || v === undefined || v === '' ? '—' : `¥${Number(v).toLocaleString()}`);
const deptText = (key) => {
    if (!key) return '—';
    const [d, s] = String(key).split('/');
    const labels = preview.value?.department_labels ?? {};
    return s ? `${labels[d] ?? d} ${labels[s] ?? s}` : (labels[d] ?? d);
};
const valueText = (row, which) => {
    const v = which === 'old' ? row.old : row.new;
    if (row.field === 'amount') return yen(v);
    if (row.field === 'department') return deptText(v);
    return v ?? '—';
};
const filteredDetails = computed(() => (preview.value?.details ?? []).filter((d) =>
    (kindFilter.value === 'all' || d.kind === kindFilter.value)
    && (nameFilter.value.trim() === '' || d.client_name.includes(nameFilter.value.trim()))));
</script>

<template>
    <ReportShell title="Excel取込" active="import">
        <div class="space-y-4">
            <!-- 案内 -->
            <div class="rounded bg-white p-4 shadow">
                <p class="text-sm text-gray-700">営業部の「得意先別」売上Excel（得意先×年度の行、月ごとの売上金額・受注件数、担当部門）を、そのまま取り込みます。</p>
                <ul class="mt-2 list-disc space-y-0.5 pl-5 text-xs text-gray-500">
                    <li>先に「確認」を行い、追加・変更・削除される内容を見てから、取り込みます（確認の段階では、データは変わりません）。</li>
                    <li>得意先×年（暦年。1月〜12月が1期）の行ごとに、追加・更新します。ファイルに無い得意先・年のデータは、そのまま残ります（2023年より前を補完する、一部だけのファイルにも使えます）。</li>
                    <li>金額・件数がどちらも空欄の月は「未登録」として扱います（0円とは別です）。ファイルで空欄になった月は、取り込むと、データからも消えます。</li>
                    <li>取込に使ったExcelファイルは、保存しません（確認・取込のあとに削除されます）。</li>
                </ul>
            </div>

            <!-- ① ファイル選択 -->
            <div v-if="!result" class="rounded bg-white p-4 shadow">
                <p class="mb-2 text-sm font-medium text-gray-700">① Excelファイル（.xlsx、{{ maxUploadMb }}MBまで）を選ぶ</p>
                <div class="flex flex-wrap items-center gap-3">
                    <input ref="fileInput" type="file" accept=".xlsx" class="hidden" @change="onFileChange" />
                    <button type="button" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold text-gray-700 shadow-sm hover:bg-gray-50" @click="selectFile">📁 ファイルを選択</button>
                    <span v-if="selectedFile" class="text-sm text-gray-600">{{ selectedFile.name }}</span>
                    <span v-else class="text-sm text-gray-400">ファイルが選択されていません</span>
                    <button
                        type="button"
                        :disabled="!selectedFile || uploading"
                        class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-bold text-white hover:bg-indigo-700 disabled:opacity-40"
                        @click="runPreview"
                    >{{ uploading ? '確認中...' : '② 内容を確認する（まだ取り込みません）' }}</button>
                </div>
                <p v-if="error" class="mt-3 rounded bg-red-50 p-3 text-sm text-red-700">{{ error }}</p>
            </div>

            <!-- ② プレビュー -->
            <div v-if="preview" class="space-y-4">
                <div class="rounded bg-white p-4 shadow">
                    <div class="mb-2 flex flex-wrap items-baseline justify-between gap-2">
                        <h2 class="text-sm font-semibold text-gray-900">確認：{{ preview.original_filename }}</h2>
                        <p class="text-xs text-gray-500">得意先×年の行 {{ preview.row_count.toLocaleString() }}行（{{ preview.year_min }}年〜{{ preview.year_max }}年）</p>
                    </div>

                    <div v-if="preview.errors.length" class="mb-3 rounded border border-red-200 bg-red-50 p-3">
                        <p class="text-sm font-semibold text-red-700">取り込めない内容があります（修正したExcelで、もう一度確認してください）</p>
                        <ul class="mt-1 list-disc space-y-0.5 pl-5 text-xs text-red-700">
                            <li v-for="(m, i) in preview.errors" :key="i">{{ m }}</li>
                        </ul>
                    </div>
                    <div v-if="preview.warnings.length" class="mb-3 rounded border border-amber-200 bg-amber-50 p-3">
                        <p class="text-sm font-semibold text-amber-800">注意（取り込みはできます）</p>
                        <ul class="mt-1 list-disc space-y-0.5 pl-5 text-xs text-amber-800">
                            <li v-for="(m, i) in preview.warnings" :key="i">{{ m }}</li>
                        </ul>
                    </div>
                    <p v-if="preview.duplicate_file" class="mb-3 rounded bg-gray-100 p-2 text-xs text-gray-600">同じ内容のファイルを、以前に取り込んだことがあります。変更がなければ、取り込んでもデータは変わりません。</p>

                    <template v-if="preview.summary">
                        <div class="grid grid-cols-2 gap-2 text-xs sm:grid-cols-4 lg:grid-cols-8">
                            <div class="rounded bg-blue-50 p-2"><p class="text-blue-700">追加（月）</p><p class="text-base font-bold text-blue-900">{{ preview.summary.months_added.toLocaleString() }}</p></div>
                            <div class="rounded bg-amber-50 p-2"><p class="text-amber-700">変更（月）</p><p class="text-base font-bold text-amber-900">{{ preview.summary.months_changed.toLocaleString() }}</p></div>
                            <div class="rounded bg-red-50 p-2"><p class="text-red-700">削除（月）</p><p class="text-base font-bold text-red-900">{{ preview.summary.months_removed.toLocaleString() }}</p></div>
                            <div class="rounded bg-gray-50 p-2"><p class="text-gray-600">変更なし（月）</p><p class="text-base font-bold text-gray-800">{{ preview.summary.months_unchanged.toLocaleString() }}</p></div>
                            <div class="rounded bg-gray-50 p-2"><p class="text-gray-600">新しい得意先×年</p><p class="text-base font-bold text-gray-800">{{ preview.summary.client_years_new.toLocaleString() }}</p></div>
                            <div class="rounded bg-gray-50 p-2"><p class="text-gray-600">更新される行</p><p class="text-base font-bold text-gray-800">{{ preview.summary.client_years_updated.toLocaleString() }}</p></div>
                            <div class="rounded bg-gray-50 p-2"><p class="text-gray-600">変更なしの行</p><p class="text-base font-bold text-gray-800">{{ preview.summary.client_years_unchanged.toLocaleString() }}</p></div>
                            <div class="rounded bg-gray-50 p-2"><p class="text-gray-600">担当部門の変更</p><p class="text-base font-bold text-gray-800">{{ preview.summary.department_changes.toLocaleString() }}</p></div>
                        </div>
                    </template>
                </div>

                <!-- この年は入れ替える -->
                <div v-if="preview.summary && preview.years.length" class="rounded bg-white p-4 shadow">
                    <h3 class="text-sm font-semibold text-gray-900">この年は、ファイルの内容で入れ替える（任意）</h3>
                    <p class="mb-2 text-[11px] text-gray-500">
                        チェックした年は、ファイルに無い得意先の行も、データから削除されます（ファイルに無い得意先を消したいときだけ使います）。
                        チェックしなければ、ファイルに無い行は、そのまま残ります。
                    </p>
                    <div class="flex flex-wrap gap-3">
                        <label v-for="y in preview.years" :key="y" class="flex items-center gap-1 text-sm text-gray-700">
                            <input v-model="replaceYears" type="checkbox" :value="y" class="rounded border-gray-300" @change="$nextTick(refreshPreview)" />
                            {{ y }}年
                        </label>
                    </div>
                    <p v-if="preview.summary.client_years_removed" class="mt-2 text-xs font-medium text-red-700">
                        入れ替えにより、{{ preview.summary.client_years_removed.toLocaleString() }}件の得意先×年の行（{{ preview.summary.months_removed.toLocaleString() }}か月分を含む）が削除されます。
                    </p>
                </div>

                <!-- 差分の詳細 -->
                <div v-if="preview.summary" class="rounded bg-white p-4 shadow">
                    <div class="mb-2 flex flex-wrap items-center gap-2">
                        <h3 class="text-sm font-semibold text-gray-900">変更の詳細</h3>
                        <div class="flex gap-1 text-xs">
                            <button v-for="k in ['all', 'added', 'changed', 'removed']" :key="k" type="button" class="rounded px-2 py-1" :class="kindFilter === k ? 'bg-gray-700 text-white' : 'border border-gray-300 text-gray-600 hover:bg-gray-100'" @click="kindFilter = k">{{ k === 'all' ? 'すべて' : KIND_LABELS[k] }}</button>
                        </div>
                        <input v-model="nameFilter" type="search" placeholder="得意先名で絞り込み" class="ml-auto w-48 rounded-md border-gray-300 text-xs shadow-sm" />
                    </div>
                    <p v-if="!preview.details_total" class="text-xs text-gray-500">前のデータとの違いはありません。</p>
                    <template v-else>
                        <p class="mb-1 text-[11px] text-gray-400">
                            金額の変化が大きい順に表示します{{ preview.details_total > preview.details.length ? `（全${preview.details_total.toLocaleString()}件のうち、先頭${preview.details.length.toLocaleString()}件）` : `（全${preview.details_total.toLocaleString()}件）` }}。
                        </p>
                        <div class="max-h-[50vh] overflow-auto rounded border">
                            <table class="min-w-full text-xs tabular-nums">
                                <thead class="sticky top-0 bg-gray-100 text-gray-600">
                                    <tr>
                                        <th class="px-2 py-1.5 text-left">得意先</th>
                                        <th class="px-2 py-1.5 text-right">年</th>
                                        <th class="px-2 py-1.5 text-right">月</th>
                                        <th class="px-2 py-1.5 text-left">種別</th>
                                        <th class="px-2 py-1.5 text-left">項目</th>
                                        <th class="px-2 py-1.5 text-right">変更前</th>
                                        <th class="px-2 py-1.5 text-right">変更後</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(d, i) in filteredDetails" :key="i" class="border-t">
                                        <td class="px-2 py-1">{{ d.client_name }}</td>
                                        <td class="px-2 py-1 text-right">{{ d.year }}</td>
                                        <td class="px-2 py-1 text-right">{{ d.month ? `${d.month}月` : '（年）' }}</td>
                                        <td class="px-2 py-1"><span class="rounded px-1 text-[10px]" :class="KIND_CLASS[d.kind]">{{ KIND_LABELS[d.kind] }}</span></td>
                                        <td class="px-2 py-1 text-gray-500">{{ FIELD_LABELS[d.field] }}</td>
                                        <td class="px-2 py-1 text-right text-gray-500">{{ valueText(d, 'old') }}</td>
                                        <td class="px-2 py-1 text-right">{{ valueText(d, 'new') }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </template>
                </div>

                <!-- ③ 確定 -->
                <div class="flex flex-wrap items-center gap-3 rounded bg-white p-4 shadow">
                    <button
                        type="button"
                        :disabled="!preview.can_confirm || confirming"
                        class="rounded-md bg-indigo-600 px-5 py-2 text-sm font-bold text-white hover:bg-indigo-700 disabled:opacity-40"
                        @click="confirmImport"
                    >{{ confirming ? '取込中...' : '③ この内容で取り込む' }}</button>
                    <button type="button" class="text-sm text-gray-500 hover:text-gray-700" @click="cancelPreview">やめる（取り込まない）</button>
                    <p v-if="!preview.can_confirm" class="text-xs text-red-600">取り込めない内容があるため、取り込めません。</p>
                    <p v-if="error" class="w-full rounded bg-red-50 p-2 text-xs text-red-700">{{ error }}</p>
                </div>
            </div>

            <!-- 完了 -->
            <div v-if="result" class="rounded-lg border border-green-300 bg-green-50 p-4">
                <p class="text-sm font-semibold text-green-800">取込を完了しました（{{ result.original_filename }}）</p>
                <div class="mt-2 grid grid-cols-2 gap-2 text-sm sm:grid-cols-5">
                    <div><span class="text-gray-500">得意先×年の行:</span> {{ result.row_count.toLocaleString() }}</div>
                    <div><span class="text-gray-500">追加:</span> {{ result.added_count.toLocaleString() }}</div>
                    <div><span class="text-gray-500">変更:</span> {{ result.changed_count.toLocaleString() }}</div>
                    <div><span class="text-gray-500">削除:</span> {{ result.removed_count.toLocaleString() }}</div>
                    <div><span class="text-gray-500">変更なし:</span> {{ result.unchanged_count.toLocaleString() }}</div>
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    <Link :href="route('suna.sales.report.index')" class="rounded-md bg-green-600 px-4 py-2 text-sm font-bold text-white hover:bg-green-700">データ登録状況で確認する</Link>
                    <Link :href="route('suna.sales.report.history.index')" class="rounded-md border border-green-600 bg-white px-4 py-2 text-sm font-bold text-green-700 hover:bg-green-50">取込履歴を見る</Link>
                    <button type="button" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" @click="reset">続けて別のファイルを取り込む</button>
                </div>
            </div>
        </div>
    </ReportShell>
</template>
