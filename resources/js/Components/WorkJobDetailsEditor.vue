<script setup>
import { ref } from 'vue';
import axios from 'axios';
import { route } from 'ziggy-js';
import { useFileAnalyzer } from '@/composables/useFileAnalyzer';
import FileInfoDisplay from '@/Components/FileInfoDisplay.vue';
import WorkFileInfoHelp from '@/Components/WorkFileInfoHelp.vue';

const props = defineProps({ work: { type: Object, required: true } });
const emit = defineEmits(['saved', 'cancel']);
const note = ref(props.work.detail_note || '');
const detailsVersion = props.work.details_version || 0;
const saving = ref(false);
const error = ref('');
const analyzer = useFileAnalyzer();
const { results, analyzing, rejectedFiles } = analyzer;
results.value = (props.work.file_info?.files || []).map(file => ({ ...file, analyzing: false }));
async function selected(event) {
    const files = Array.from(event.target.files || []);
    if (files.length > 50) {
        error.value = '一度に読み取れるファイルは50件までです。';
    } else if (results.value.length + files.length > 200) {
        error.value = '作業ファイル情報は200件以内で登録してください。';
    } else {
        error.value = '';
        try { await analyzer.analyzeFiles(files); }
        catch { analyzing.value = false; error.value = 'ファイル情報を読み取れませんでした。ファイルを確認してください。'; }
    }
    event.target.value = '';
}
async function save() {
    if (saving.value || analyzing.value) return;
    saving.value = true; error.value = '';
    try {
        await axios.put(route('user.myjobbox.entries.details', { entry: props.work.id }), {
            details_version: detailsVersion,
            detail_note: note.value,
            file_info: analyzer.buildFileInfo(),
        });
        emit('saved');
    } catch (err) {
        error.value = Object.values(err.response?.data?.errors || {}).flat().join(' ') || err.response?.data?.message || '詳細情報を保存できませんでした。';
    } finally { saving.value = false; }
}
</script>

<template>
    <section class="rounded-lg border border-blue-200 bg-white p-5 shadow-sm" aria-labelledby="work-details-heading">
        <h3 id="work-details-heading" class="mb-3 font-semibold text-gray-900">詳細入力</h3>
        <label class="mb-4 block text-sm text-gray-700">作業の説明（任意）
            <textarea v-model="note" :disabled="saving" maxlength="5000" rows="3" class="mt-1 block w-full rounded border-gray-300 text-sm" />
        </label>
        <h4 class="mb-2 text-sm font-semibold">作業ファイル情報</h4>
        <WorkFileInfoHelp />
        <label class="inline-block cursor-pointer rounded border border-blue-200 bg-blue-50 px-3 py-2 text-sm font-medium text-blue-700">
            {{ analyzing ? '読み取り中…' : '対象ファイルを選択' }}
            <input type="file" multiple :disabled="saving || analyzing" class="sr-only" @change="selected" />
        </label>
        <p class="mt-2 text-xs text-gray-500">一度に50件、合計200件まで。保存済みの一覧に追加します。情報を置き換える場合は、先に一覧から取り除いてください。</p>
        <ul v-if="rejectedFiles.length" class="mt-2 text-xs text-orange-800"><li v-for="(file, index) in rejectedFiles" :key="index">{{ file.name }}：{{ file.reason }}</li></ul>
        <div v-if="results.length" class="mt-3 space-y-3">
            <FileInfoDisplay :file-info="analyzer.buildFileInfo()" />
            <ul class="space-y-1 text-xs"><li v-for="(file, index) in results" :key="index" class="flex items-center justify-between gap-3"><span class="break-all">{{ file.name }}</span><button type="button" :disabled="saving || analyzing" class="shrink-0 text-red-700 underline disabled:opacity-50" @click="results.splice(index, 1)">一覧から取り除く</button></li></ul>
        </div>
        <p v-if="error" role="alert" class="mt-3 text-sm text-red-700">{{ error }}</p>
        <div class="mt-4 flex justify-end gap-2">
            <button type="button" :disabled="saving || analyzing" class="rounded border px-3 py-2 text-sm disabled:opacity-50" @click="emit('cancel')">キャンセル</button>
            <button type="button" :disabled="saving || analyzing" class="rounded bg-blue-600 px-3 py-2 text-sm text-white disabled:opacity-50" @click="save">{{ saving ? '保存中…' : '詳細を保存' }}</button>
        </div>
    </section>
</template>
