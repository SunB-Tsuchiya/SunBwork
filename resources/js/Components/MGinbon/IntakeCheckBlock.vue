<script setup>
import { Link } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, reactive, ref } from 'vue';
import LedgerCellEditor from '@/Components/MGinbon/LedgerCellEditor.vue';

const props = defineProps({
  item: { type: Object, required: true },
  unit: { type: Object, required: true },
  actorOptions: { type: Object, default: () => ({ users: [], subcontractors: [] }) },
  projectLinked: { type: Boolean, default: false },
});
const subjectOrder = ['japanese', 'math', 'social', 'science'];
const subject = (code) => props.item.subjects?.find((row) => row.code === code);
const qty = (code, type, execution) => subject(code)?.measurements?.find((row) => row.work_type === type && row.execution_type === execution)?.quantity ?? 0;
const headers = { japanese: '国語', math: '算数', social: '社会', science: '理科' };
const intakeColumn = { type: 'date', code: 'manuscript_received_on' };
const originalReceivedColumn = { type: 'shared', code: 'original_received_on' };
const originalScanColumn = { type: 'shared', code: 'original_scan_completed_on' };
const actorColumns = {
  text_input: { type: 'stage', code: 'text_input' },
  drawing: { type: 'stage', code: 'drawing' },
};
const saving = ref(false);
const saved = ref(false);
const error = ref('');
const note = ref(props.item.note || '');
const dirty = ref(false);
const pendingSave = ref(false);
const form = reactive(Object.fromEntries((props.item.subjects ?? []).map((row) => [row.id, {
  'scan:subcontracted': qty(row.code, 'scan', 'subcontracted'),
  'drawing:subcontracted': qty(row.code, 'drawing', 'subcontracted'),
  'scan:internal': qty(row.code, 'scan', 'internal'),
  'drawing:internal': qty(row.code, 'drawing', 'internal'),
}])));
let updatedAt = props.item.updated_at;
const inputValue = (code, key) => form[subject(code)?.id]?.[key] ?? 0;
function setInput(code, key, value) {
  const id = subject(code)?.id;
  if (id) form[id][key] = Math.max(0, Number.parseInt(value || 0, 10) || 0);
  dirty.value = true;
  saved.value = false;
}
function total(key) { return Object.values(form).reduce((sum, row) => sum + (Number(row[key]) || 0), 0); }
const warnings = computed(() => props.item.subjects.flatMap((row) => {
  const messages = [];
  const drawingTask = row.stages?.find((stage) => stage.code === 'drawing');
  const textTask = row.stages?.find((stage) => stage.code === 'text_input');
  const internal = (form[row.id]?.['scan:internal'] || 0) + (form[row.id]?.['drawing:internal'] || 0);
  const subcontracted = (form[row.id]?.['scan:subcontracted'] || 0) + (form[row.id]?.['drawing:subcontracted'] || 0);
  if (!row.dates?.manuscript_received_on) messages.push(`${row.name}: 入稿日なし`);
  if ((internal + subcontracted) > 0 && !drawingTask?.actor) messages.push(`${row.name}: 作図・scan担当なし`);
  if (subcontracted > 0 && drawingTask?.target?.startsWith('user:')) messages.push(`${row.name}: 社外点数／社内担当を確認`);
  if (internal > 0 && drawingTask?.target?.startsWith('subcontractor:')) messages.push(`${row.name}: 社内点数／外注担当を確認`);
  if (row.dates?.text_input_completed_on && !textTask?.actor) messages.push(`${row.name}: 文字入力担当なし`);
  return messages;
}));
async function save() {
  if (saving.value) { pendingSave.value = true; return; }
  saving.value = true; error.value = ''; saved.value = false;
  dirty.value = false;
  try {
    const response = await axios.patch(route('coordinator.mginbon.items.intake_check.update', { item: props.item.id }), {
      updated_at: updatedAt,
      note: note.value || null,
      subjects: Object.entries(form).map(([id, measurements]) => ({ id: Number(id), measurements })),
    });
    updatedAt = response.data.updated_at;
    props.item.updated_at = response.data.updated_at;
    props.item.note = note.value;
    saved.value = true;
  } catch (e) {
    dirty.value = true;
    error.value = e?.response?.data?.errors?.updated_at?.[0] ?? e?.response?.data?.message ?? '保存できませんでした。';
  } finally {
    saving.value = false;
    if (pendingSave.value) {
      pendingSave.value = false;
      if (dirty.value) save();
    }
  }
}
function requestSave() { if (dirty.value) save(); }
function changeNote() { dirty.value = true; saved.value = false; }
function task(code, stage) { return subject(code)?.stages?.find((row) => row.code === stage); }
function cellSaved(code, payload) {
  updatedAt = payload.updated_at;
  props.item.updated_at = payload.updated_at;
  if (payload.kind === 'date' && payload.subjectIds === null) {
    props.item.shared_dates[payload.code] = payload.date;
    return;
  }
  const subjectIds = payload.subjectIds ?? [subject(code)?.id].filter(Boolean);
  if (payload.kind === 'date') {
    props.item.subjects.filter((row) => subjectIds.includes(row.id))
      .forEach((row) => { row.dates[payload.code] = payload.date; });
    return;
  }
  props.item.subjects.filter((row) => subjectIds.includes(row.id)).forEach((row) => {
    const target = row.stages?.find((stage) => stage.code === payload.code);
    if (target) Object.assign(target, { actor: payload.actor, target: payload.target,
      assignment_id: payload.assignment_id, status: payload.status, planned: payload.planned });
  });
}
</script>

<template>
  <section class="grid border-b-2 border-gray-700 bg-white lg:grid-cols-[17rem_minmax(30rem,1fr)]">
    <div class="border-r border-gray-500 bg-white p-1 text-[13px] leading-snug">
      <div class="grid h-7 grid-cols-4 items-center bg-[#ccffff] text-center text-xs"><span>みくにコード</span><span>日能研コード</span><span>分類</span><span>媒体</span></div>
      <div class="grid h-7 grid-cols-4 gap-0.5 text-center"><span class="border border-gray-400 bg-gray-600 py-1 text-white">{{ unit.mikuni_code || '' }}</span><span class="border border-gray-400 bg-white py-1">{{ unit.n_code || '' }}</span><span class="border border-gray-400 bg-white py-1">{{ unit.n_category || unit.school_category || '' }}</span><span class="border border-gray-400 bg-white py-1">{{ item.media_name }}</span></div>
      <div class="bg-[#ccffff] px-1 py-1.5 text-center text-sm font-semibold">{{ unit.display_name }}</div>
      <div class="grid h-7 grid-cols-[1fr_4.5rem_3rem] items-center gap-0.5"><span class="bg-[#ccffcc] px-1 py-1">{{ item.subjects.map((s) => s.name).join('・') }}</span><span class="bg-[#ccffcc] px-1 py-1 text-center">{{ task('japanese', 'initial_operation')?.actor || '初校OP' }}</span><span class="bg-gray-100 px-1 py-1 text-center">{{ item.publication_status || '○' }}</span></div>
      <div class="mt-1 grid min-h-7 grid-cols-[4.25rem_1fr] items-center gap-px bg-[#ccffcc]"><span class="px-1">原本入稿</span><LedgerCellEditor v-if="item.subjects[0]" :item="item" :subject="item.subjects[0]" :column="originalReceivedColumn" @saved="cellSaved(item.subjects[0].code, $event)" /></div>
      <div class="mt-px grid min-h-7 grid-cols-[4.25rem_1fr] items-center gap-px bg-[#ccffcc]"><span class="px-1">スキャンUP</span><LedgerCellEditor v-if="item.subjects[0]" :item="item" :subject="item.subjects[0]" :column="originalScanColumn" @saved="cellSaved(item.subjects[0].code, $event)" /></div>
      <details v-if="warnings.length" class="mt-1 border border-orange-400 bg-orange-50 px-1 py-0.5 text-[10px] text-orange-900"><summary class="cursor-pointer font-semibold">要確認 {{ warnings.length }}件</summary><ul class="mt-0.5 list-disc pl-4"><li v-for="message in warnings" :key="message">{{ message }}</li></ul></details>
      <textarea v-model="note" maxlength="5000" rows="3" class="mt-1 w-full resize-y border border-dotted border-gray-500 bg-white p-1 text-xs text-gray-700 focus:border-green-600 focus:ring-1 focus:ring-green-600" placeholder="詳細メモ" @input="changeNote" @blur="requestSave"></textarea>
      <div class="mt-1 flex flex-wrap items-center gap-2"><button type="button" :disabled="saving || !dirty" class="rounded bg-green-700 px-3 py-1 font-medium text-white disabled:opacity-50" @click="requestSave">{{ saving ? '保存中…' : '今すぐ保存' }}</button><Link :href="route('coordinator.mginbon.items.show', { item: item.id })" class="rounded border border-green-700 bg-white px-2 py-1 font-medium text-green-800">担当・詳細</Link><span v-if="saved" class="font-medium text-green-700">自動保存しました</span></div>
      <p v-if="error" class="mt-1 text-red-600">{{ error }}</p>
    </div>
    <div class="overflow-x-auto">
      <table class="w-[27rem] min-w-[27rem] table-fixed border-collapse text-[13px]">
        <thead><tr class="h-7 bg-[#ccffff]"><th class="w-[6.5rem] border-r border-gray-400 px-1 py-1 text-left"></th><th v-for="code in subjectOrder" :key="code" class="w-[4.5rem] border-r border-gray-400 px-1 py-1">{{ headers[code] }}</th><th class="w-10 px-1 py-1 text-blue-700">合計</th></tr></thead>
        <tbody>
          <tr><th class="border-r border-t border-gray-400 px-2 py-1 text-right">入稿</th><td v-for="code in subjectOrder" :key="code" class="h-7 border-r border-t border-gray-400 bg-[#ffccff] p-0 text-center"><LedgerCellEditor v-if="subject(code)" :item="item" :subject="subject(code)" :column="intakeColumn" @saved="cellSaved(code, $event)" /><span v-else>—</span></td><td></td></tr>
          <tr><th class="border-r border-gray-400 px-2 py-1 text-right">文字入力</th><td v-for="code in subjectOrder" :key="code" class="h-7 border-r border-gray-400 bg-[#ccffff] p-0 text-center"><LedgerCellEditor v-if="subject(code)" :item="item" :subject="subject(code)" :column="actorColumns.text_input" :task="task(code, 'text_input')" :actor-options="actorOptions" :project-linked="projectLinked" @saved="cellSaved(code, $event)" /><span v-else>—</span></td><td></td></tr>
          <tr><th class="border-r border-gray-400 px-2 py-1 text-right">作図・スキャン</th><td v-for="code in subjectOrder" :key="code" class="h-7 border-r border-gray-400 bg-[#e8ebff] p-0 text-center"><LedgerCellEditor v-if="subject(code)" :item="item" :subject="subject(code)" :column="actorColumns.drawing" :task="task(code, 'drawing')" :actor-options="actorOptions" :project-linked="projectLinked" @saved="cellSaved(code, $event)" /><span v-else>—</span></td><td></td></tr>
          <tr v-for="row in [['scan','subcontracted','社外／scan点数'],['drawing','subcontracted','社外／作図点数'],['scan','internal','社内／scan点数'],['drawing','internal','社内／作図点数']]" :key="`${row[0]}-${row[1]}`" class="h-7"><th class="border-r border-gray-400 px-1 py-1 text-right">{{ row[2] }}</th><td v-for="code in subjectOrder" :key="code" class="border-r border-gray-400 bg-[#d1d1d1] p-0.5 text-center"><input v-if="subject(code)" type="number" min="0" max="999999" :value="inputValue(code, `${row[0]}:${row[1]}`)" class="w-full border-0 bg-white/70 px-1 py-0.5 text-center text-[13px] focus:ring-2 focus:ring-green-500" @input="setInput(code, `${row[0]}:${row[1]}`, $event.target.value)" @blur="requestSave" /><span v-else>—</span></td><td class="border-l border-blue-200 bg-blue-50 px-1 py-1 text-center font-bold text-blue-700">{{ total(`${row[0]}:${row[1]}`) || '' }}</td></tr>
        </tbody>
      </table>
    </div>
  </section>
</template>
