<script setup>
import { computed, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import axios from 'axios';

const props = defineProps({
    departmentLabels: { type: Object, default: () => ({}) },
    enabledDepartmentKeys: { type: Array, default: () => [] },
});

const departmentKey = ref(props.enabledDepartmentKeys[0] ?? 'planning');
const sourceType = ref('monthly');
const sourceYear = ref(new Date().getFullYear());
const sourceMonth = ref(new Date().getMonth() + 1);

const fileInput = ref(null);
const selectedFile = ref(null);
const fileName = ref('');

const submitting = ref(false);
const result = ref(null);
const submitError = ref('');

const selectFile = () => fileInput.value?.click();
const onFileChange = (e) => {
    const file = e.target.files[0];
    if (file) {
        selectedFile.value = file;
        fileName.value = file.name;
        result.value = null;
        submitError.value = '';
    }
};

const canSubmit = computed(() => {
    if (!selectedFile.value) return false;
    if (!sourceYear.value) return false;
    if (sourceType.value === 'monthly' && !sourceMonth.value) return false;
    return true;
});

const submit = async () => {
    if (!canSubmit.value || submitting.value) return;

    submitting.value = true;
    result.value = null;
    submitError.value = '';

    const formData = new FormData();
    formData.append('file', selectedFile.value);
    formData.append('department_key', departmentKey.value);
    formData.append('source_type', sourceType.value);
    formData.append('source_year', sourceYear.value);
    if (sourceType.value === 'monthly') {
        formData.append('source_month', sourceMonth.value);
    }

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const response = await axios.post(route('sales_analysis.import.preview'), formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
                'X-CSRF-TOKEN': csrfToken,
            },
        });
        result.value = response.data;
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
</script>

<template>
    <AppLayout title="売上Excel取込">
        <template #header>
            <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">売上Excel取込</h2>
        </template>

        <div class="rounded bg-white px-4 py-6 sm:p-6 shadow">
            <div class="mb-8">
                <h3 class="mb-3 text-sm font-semibold text-gray-900">命名規則</h3>
                <div class="rounded-lg bg-gray-50 p-4 text-sm text-gray-700">
                    <p>年次ファイル例: <span class="font-mono">企画_2026年.xlsx</span>（1シートに1〜12月分の明細）</p>
                    <p class="mt-1">月次ファイル例: <span class="font-mono">企画_2026年09月.xlsx</span></p>
                    <p class="mt-2 text-xs text-gray-500">
                        ファイル名は確認補助です。対象部署・期間はExcel内部のタイトル・明細から検証します。
                    </p>
                </div>
            </div>

            <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700">対象部署</label>
                    <select v-model="departmentKey" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option v-for="key in enabledDepartmentKeys" :key="key" :value="key">
                            {{ departmentLabels[key] }}
                        </option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">種別</label>
                    <select v-model="sourceType" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="monthly">月次</option>
                        <option value="annual">年次</option>
                    </select>
                </div>
                <div class="flex gap-2">
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700">対象年</label>
                        <input
                            v-model.number="sourceYear"
                            type="number"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </div>
                    <div v-if="sourceType === 'monthly'" class="flex-1">
                        <label class="block text-sm font-medium text-gray-700">対象月</label>
                        <input
                            v-model.number="sourceMonth"
                            type="number"
                            min="1"
                            max="12"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </div>
                </div>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700">Excelファイル（.xlsx）</label>
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

            <div class="mb-6">
                <button
                    type="button"
                    :disabled="!canSubmit || submitting"
                    class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-bold text-white hover:bg-indigo-700 disabled:opacity-40"
                    @click="submit"
                >
                    {{ submitting ? '検証中...' : '検証してプレビュー' }}
                </button>
            </div>

            <p v-if="submitError" class="mb-6 rounded bg-red-50 p-3 text-sm text-red-700">{{ submitError }}</p>

            <div v-if="result" class="rounded-lg border p-4" :class="result.valid ? 'border-green-300 bg-green-50' : 'border-red-300 bg-red-50'">
                <p class="text-sm font-semibold" :class="result.valid ? 'text-green-800' : 'text-red-800'">
                    {{ result.valid ? '検証に成功しました' : '検証エラーがあります（確定できません）' }}
                </p>

                <div v-if="result.summary" class="mt-3 grid grid-cols-2 gap-3 text-sm sm:grid-cols-3">
                    <div><span class="text-gray-500">受注件数:</span> {{ result.summary.order_count }}</div>
                    <div><span class="text-gray-500">明細件数:</span> {{ result.summary.detail_count }}</div>
                    <div><span class="text-gray-500">合計金額:</span> ¥{{ Number(result.summary.total_amount).toLocaleString() }}</div>
                </div>

                <div v-if="result.warnings?.length" class="mt-3">
                    <p class="text-xs font-semibold text-amber-700">警告</p>
                    <ul class="mt-1 list-disc pl-5 text-xs text-amber-700">
                        <li v-for="(w, i) in result.warnings" :key="i">{{ w }}</li>
                    </ul>
                </div>

                <div v-if="result.errors?.length" class="mt-3">
                    <p class="text-xs font-semibold text-red-700">エラー</p>
                    <ul class="mt-1 max-h-64 list-disc overflow-y-auto pl-5 text-xs text-red-700">
                        <li v-for="(err, i) in result.errors" :key="i">{{ err }}</li>
                    </ul>
                </div>

                <div class="mt-4">
                    <button
                        type="button"
                        disabled
                        class="inline-flex items-center rounded-md bg-gray-300 px-4 py-2 text-sm font-bold text-gray-500"
                        title="確定機能は後続のPhaseで実装します"
                    >
                        確定（準備中）
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
