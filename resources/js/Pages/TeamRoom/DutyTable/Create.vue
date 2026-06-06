<script setup>
import { ref, computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

const props = defineProps({
    team:        { type: Object, required: true },
    previewHtml: { type: String, default: null },
    formData:    { type: Object, default: () => ({ title: '', description: '' }) },
    error:       { type: String, default: null },
});

// ── プレビュー送信フォーム（ファイルあり）────────────────────
const previewForm = useForm({
    title:       props.formData.title ?? '',
    description: props.formData.description ?? '',
    file:        null,
});

function submitPreview() {
    previewForm.post(route('team-rooms.duty-tables.preview', { team: props.team.id }), {
        forceFormData: true,
    });
}

// ── 保存フォーム（HTMLのみ送信）──────────────────────────────
const saveForm = useForm({
    title:        props.formData.title ?? '',
    description:  props.formData.description ?? '',
    html_content: props.previewHtml ?? '',
});

function submitSave() {
    saveForm.post(route('team-rooms.duty-tables.store', { team: props.team.id }));
}

// ファイル選択
const fileInput = ref(null);
function onFileChange(e) {
    previewForm.file = e.target.files[0] ?? null;
}

const hasPreview = computed(() => !!props.previewHtml);
</script>

<template>
    <AppLayout :title="`${team.name} — 係・当番表の登録`">
        <template #header>
            <div class="flex items-center gap-3">
                <Link
                    :href="route('team-rooms.show', { team: team.id, tab: 'duty' })"
                    class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300 whitespace-nowrap"
                >← 係・当番に戻る</Link>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">係・当番表の登録 — {{ team.name }}</h2>
            </div>
        </template>

        <div class="rounded bg-white p-6 shadow space-y-6">

            <!-- ── ファイル読み込みフォーム ── -->
            <form @submit.prevent="submitPreview" enctype="multipart/form-data">
                <h3 class="mb-4 text-base font-semibold text-gray-800 border-b pb-2">ファイル読み込み</h3>

                <!-- タイトル -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        表のタイトル <span class="text-red-500">*</span>
                    </label>
                    <input
                        v-model="previewForm.title"
                        type="text"
                        class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none"
                        placeholder="例：6月 係・当番表"
                        required
                    />
                    <p v-if="previewForm.errors.title" class="mt-1 text-xs text-red-600">{{ previewForm.errors.title }}</p>
                </div>

                <!-- 説明 -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">説明（任意）</label>
                    <textarea
                        v-model="previewForm.description"
                        rows="2"
                        class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none"
                        placeholder="補足説明など"
                    ></textarea>
                </div>

                <!-- ファイル選択 -->
                <div class="mb-5">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        ファイル <span class="text-red-500">*</span>
                        <span class="ml-2 text-xs font-normal text-gray-400">CSV（Shift-JIS可）/ Excel（.xlsx .xls）</span>
                    </label>
                    <input
                        ref="fileInput"
                        type="file"
                        accept=".csv,.txt,.xlsx,.xls,.xlsm,.ods"
                        class="block w-full text-sm text-gray-600 file:mr-4 file:rounded file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100"
                        @change="onFileChange"
                    />
                    <p v-if="previewForm.errors.file" class="mt-1 text-xs text-red-600">{{ previewForm.errors.file }}</p>
                </div>

                <!-- エラー -->
                <div v-if="error" class="mb-4 rounded border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                    <p class="font-medium mb-1">⚠ ファイルを読み込めませんでした</p>
                    <p>{{ error }}</p>
                    <p class="mt-1 text-xs">Excel または CSV の形式・文字コードを確認して再度アップロードしてください。</p>
                </div>

                <button
                    type="submit"
                    :disabled="previewForm.processing || !previewForm.file"
                    class="rounded bg-indigo-600 px-5 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                >
                    {{ previewForm.processing ? '読み込み中…' : 'プレビュー確認' }}
                </button>
            </form>

            <!-- ── プレビュー ── -->
            <div v-if="hasPreview">
                <h3 class="mb-3 text-base font-semibold text-gray-800 border-b pb-2">プレビュー</h3>

                <!-- テーブル表示 -->
                <div class="overflow-x-auto rounded border border-gray-200 p-2 duty-table-preview" v-html="previewHtml"></div>

                <p class="mt-2 text-xs text-gray-500">
                    表示内容を確認し、問題なければ「確定して保存」を押してください。修正が必要な場合はファイルを編集してから再度アップロードしてください。
                </p>

                <!-- 保存フォーム -->
                <form @submit.prevent="submitSave" class="mt-4">
                    <input type="hidden" v-model="saveForm.title" />
                    <input type="hidden" v-model="saveForm.description" />
                    <input type="hidden" v-model="saveForm.html_content" />
                    <button
                        type="submit"
                        :disabled="saveForm.processing"
                        class="rounded bg-green-600 px-5 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50"
                    >
                        {{ saveForm.processing ? '保存中…' : '確定して保存' }}
                    </button>
                </form>
            </div>

        </div>
    </AppLayout>
</template>

<style scoped>
/* プレビューテーブルのスタイル */
:deep(.duty-table-preview table) {
    border-collapse: collapse;
    width: 100%;
    font-size: 0.85rem;
}
:deep(.duty-table-preview th),
:deep(.duty-table-preview td) {
    border: 1px solid #d1d5db;
    padding: 6px 10px;
    text-align: left;
    white-space: nowrap;
}
:deep(.duty-table-preview th) {
    background-color: #f3f4f6;
    font-weight: 600;
}
:deep(.duty-table-preview tr:nth-child(even) td) {
    background-color: #f9fafb;
}
</style>
