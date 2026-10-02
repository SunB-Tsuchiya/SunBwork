<script setup>
import axios from 'axios';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps({ project: { type: Object, required: true } });
const emit = defineEmits(['close', 'completed']);
const milestones = ref([]);
const milestoneCode = ref('initial_shared_on');
const date = ref(new Date().toLocaleDateString('sv-SE'));
const filenames = ref('');
const overwrite = ref(false);
const preview = ref(null);
const previewSignature = ref('');
const loading = ref(false);
const saving = ref(false);
const error = ref('');
const completion = ref(null);
const directoryInput = ref(null);
const fileInput = ref(null);
const fileSelectionMessage = ref('');
const dragging = ref(false);
const signature = computed(() => JSON.stringify([milestoneCode.value, date.value, filenames.value, overwrite.value]));
const canPreview = computed(() => milestoneCode.value && date.value && filenames.value.trim() && !loading.value && !saving.value);
const canStore = computed(() => preview.value?.registrable_count > 0 && previewSignature.value === signature.value && !saving.value);
const statusLabel = {
  ready: '登録可能', overwrite_ready: '上書き可能', same_date: '登録済み', existing_value: '別日付あり',
  invalid_format: '形式エラー', year_mismatch: '年度相違', duplicate: '重複', not_found: '対象なし', ambiguous: '複数候補',
};
const statusClass = (status) => ({
  ready: 'bg-green-100 text-green-900', overwrite_ready: 'bg-amber-100 text-amber-900', same_date: 'bg-blue-50 text-blue-800',
  existing_value: 'bg-orange-100 text-orange-900', invalid_format: 'bg-red-100 text-red-900', year_mismatch: 'bg-red-100 text-red-900',
  duplicate: 'bg-purple-100 text-purple-900', not_found: 'bg-gray-200 text-gray-800', ambiguous: 'bg-red-100 text-red-900',
})[status] || 'bg-gray-100';
const payload = () => ({ project_id: props.project.id, milestone_code: milestoneCode.value, date: date.value, filenames: filenames.value, overwrite: overwrite.value });
const message = (failure) => Object.values(failure?.response?.data?.errors || {}).flat()[0]
  || failure?.response?.data?.message
  || '処理できませんでした。';

watch(signature, () => { completion.value = null; });

function importFiles(fileList) {
  const selected = Array.from(fileList || []);
  const pdfNames = selected.filter((file) => /\.pdf$/i.test(file.name)).map((file) => file.name);
  const current = filenames.value.split(/\r?\n/).map((line) => line.trim()).filter(Boolean);
  const merged = [...new Set([...current, ...pdfNames])];
  const added = merged.length - new Set(current).size;
  const duplicates = pdfNames.length - added;
  filenames.value = merged.join('\n');
  fileSelectionMessage.value = `${selected.length}ファイル中 PDF ${pdfNames.length}件を確認、${added}件を追加${duplicates > 0 ? `（重複 ${duplicates}件は追加せず）` : ''}。`;
}

function selectedFiles(event) {
  importFiles(event.target.files);
  event.target.value = '';
}

function dropped(event) {
  dragging.value = false;
  importFiles(event.dataTransfer?.files);
}

onMounted(async () => {
  try {
    const response = await axios.get(route('coordinator.mginbon.filename_bulk.options'));
    milestones.value = response.data.milestones || [];
  } catch (failure) { error.value = message(failure); }
});

async function runPreview() {
  if (!canPreview.value) return;
  loading.value = true; error.value = ''; completion.value = null;
  try {
    const response = await axios.post(route('coordinator.mginbon.filename_bulk.preview'), payload());
    preview.value = response.data;
    previewSignature.value = signature.value;
  } catch (failure) { error.value = message(failure); preview.value = null; previewSignature.value = ''; }
  finally { loading.value = false; }
}

async function store() {
  if (!canStore.value || !window.confirm(`${preview.value.registrable_count}件に${preview.value.milestone_label}の日付 ${date.value} を登録しますか？`)) return;
  saving.value = true; error.value = '';
  try {
    const response = await axios.post(route('coordinator.mginbon.filename_bulk.store'), payload());
    completion.value = response.data;
    preview.value = null; previewSignature.value = '';
    window.dispatchEvent(new CustomEvent('toast:show', { detail: { type: 'success', message: `${response.data.updated}件を一括登録しました。` } }));
    emit('completed');
  } catch (failure) { error.value = message(failure); }
  finally { saving.value = false; }
}
</script>

<template>
  <section class="no-print border-2 border-indigo-700 bg-white shadow-lg">
    <header class="flex items-center gap-3 border-b border-indigo-300 bg-indigo-50 px-3 py-2">
      <strong class="text-sm text-indigo-950">ファイル名一括登録</strong>
      <span class="text-xs text-indigo-800">Nコード・年度・媒体・教科を解析し、選択した工程日を一括登録します。</span>
      <button type="button" class="ml-auto border border-gray-400 bg-white px-3 py-1 text-xs" @click="emit('close')">閉じる</button>
    </header>

    <div class="grid gap-3 p-3 lg:grid-cols-[18rem_1fr]">
      <div class="space-y-3">
        <label class="block text-xs font-semibold">登録工程
          <select v-model="milestoneCode" class="mt-1 h-9 w-full border-gray-400 py-0 text-sm">
            <option v-for="option in milestones" :key="option.code" :value="option.code">{{ option.label }}</option>
          </select>
        </label>
        <label class="block text-xs font-semibold">登録日
          <input v-model="date" type="date" class="mt-1 h-9 w-full border-gray-400 py-0 text-sm" />
        </label>
        <label class="flex items-center gap-2 border border-amber-300 bg-amber-50 p-2 text-xs text-amber-950">
          <input v-model="overwrite" type="checkbox" />既存の別日付を上書きする
        </label>
        <p class="text-[11px] leading-relaxed text-gray-600">形式: <code>NNNNYYYY__媒体教科.pdf</code><br />例: <code>30812026__AASh.pdf</code></p>
      </div>
      <div class="text-xs font-semibold">
        <div class="flex flex-wrap items-center gap-2">
          <span>ファイル名一覧（1行1件、200件まで）</span>
          <button type="button" class="border border-indigo-600 bg-indigo-50 px-3 py-1 text-indigo-900 hover:bg-indigo-100" @click="directoryInput?.click()">フォルダから取得</button>
          <button type="button" class="border border-gray-500 bg-white px-3 py-1 text-gray-800 hover:bg-gray-100" @click="fileInput?.click()">PDFを複数選択</button>
          <input ref="directoryInput" type="file" accept="application/pdf,.pdf" multiple webkitdirectory directory class="hidden" @change="selectedFiles" />
          <input ref="fileInput" type="file" accept="application/pdf,.pdf" multiple class="hidden" @change="selectedFiles" />
        </div>
        <div class="mt-1 border-2 border-dashed p-2 transition-colors" :class="dragging ? 'border-indigo-600 bg-indigo-50' : 'border-gray-300 bg-gray-50'" @dragenter.prevent="dragging = true" @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false" @drop.prevent="dropped">
          <p class="mb-1 text-center text-[11px] font-normal text-gray-600">PDFをここへドラッグ＆ドロップ、または下の欄へファイル名を貼り付け</p>
          <textarea v-model="filenames" rows="8" class="w-full resize-y border-gray-400 font-mono text-xs font-normal" placeholder="30812026__AASh.pdf"></textarea>
        </div>
        <p v-if="fileSelectionMessage" class="mt-1 bg-blue-50 px-2 py-1 text-[11px] font-normal text-blue-900">{{ fileSelectionMessage }} PDF本体はサーバーへ送信しません。</p>
      </div>
    </div>

    <div class="flex items-center gap-3 border-t bg-gray-50 px-3 py-2">
      <button type="button" :disabled="!canPreview" class="bg-indigo-700 px-5 py-1.5 text-xs font-semibold text-white disabled:opacity-40" @click="runPreview">{{ loading ? '解析中…' : '解析する' }}</button>
      <button type="button" :disabled="!canStore" class="bg-green-700 px-5 py-1.5 text-xs font-semibold text-white disabled:opacity-40" @click="store">{{ saving ? '登録中…' : '登録可能な行を一括登録' }}</button>
      <span v-if="preview" class="text-xs">全{{ preview.rows.length }}件・<strong class="text-green-800">登録可能 {{ preview.registrable_count }}件</strong></span>
      <span v-if="previewSignature && previewSignature !== signature" class="text-xs font-semibold text-amber-700">入力が変更されたため、再解析してください。</span>
    </div>
    <p v-if="error" class="border-t border-red-300 bg-red-50 px-3 py-2 text-xs text-red-800">{{ error }}</p>
    <p v-if="completion" class="border-t border-green-300 bg-green-50 px-3 py-2 text-xs font-semibold text-green-900">{{ completion.updated }}件を登録し、{{ completion.skipped }}件を変更せずスキップしました。</p>

    <div v-if="preview" class="max-h-[28rem] overflow-auto border-t">
      <table class="min-w-full border-collapse text-[11px]">
        <thead class="sticky top-0 z-10 bg-[#ccffff] text-left"><tr><th class="border p-1">#</th><th class="border p-1">ファイル名</th><th class="border p-1">Nコード</th><th class="border p-1">学校名</th><th class="border p-1">媒体</th><th class="border p-1">教科</th><th class="border p-1">現在値</th><th class="border p-1">登録値</th><th class="border p-1">判定</th><th class="border p-1">詳細</th></tr></thead>
        <tbody><tr v-for="row in preview.rows" :key="row.line" class="align-top"><td class="border p-1 text-right">{{ row.line }}</td><td class="border p-1 font-mono">{{ row.filename || row.input }}</td><td class="border p-1">{{ row.n_code || '' }}</td><td class="border p-1">{{ row.school_name || '' }}</td><td class="border p-1">{{ row.media_name || '' }}</td><td class="border p-1">{{ row.subject_name || '' }}</td><td class="border p-1">{{ row.current_date || '' }}</td><td class="border p-1">{{ row.new_date || '' }}</td><td class="border p-1"><span class="inline-block whitespace-nowrap px-2 py-0.5 font-semibold" :class="statusClass(row.status)">{{ statusLabel[row.status] || row.status }}</span></td><td class="border p-1">{{ row.message }}</td></tr></tbody>
      </table>
    </div>
  </section>
</template>
