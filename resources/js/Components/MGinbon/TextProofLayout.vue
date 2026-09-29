<script setup>
import axios from 'axios';
import { computed, reactive } from 'vue';
import LedgerCellEditor from '@/Components/MGinbon/LedgerCellEditor.vue';

const props = defineProps({
  project: { type: Object, required: true },
  units: { type: Array, default: () => [] },
  actorOptions: { type: Object, default: () => ({ users: [], subcontractors: [] }) },
  projectLinked: { type: Boolean, default: false },
});

const saving = reactive({});
const subjectColumns = [
  { code: 'japanese', name: '国語' },
  { code: 'math', name: '算数' },
  { code: 'social', name: '社会' },
  { code: 'science', name: '理科' },
];
const proofRows = [
  { type: 'date', code: 'manuscript_received_on', label: '入稿日', tone: 'date' },
  { type: 'stage', code: 'initial_text_proof', label: '初校・校正', tone: 'actor' },
  { type: 'date', code: 'initial_text_proof_started_on', label: '校正発注', tone: 'date' },
  { type: 'date', code: 'initial_text_proof_completed_on', label: '校正納品', tone: 'date' },
  { type: 'stage', code: 'reproof_scan_check', label: '再校校正1', tone: 'actor' },
  { type: 'date', code: 'reproof_scan_check_started_on', label: '校正発注', tone: 'date' },
  { type: 'date', code: 'reproof_scan_check_completed_on', label: '校正納品', tone: 'date' },
  { type: 'stage', code: 'reproof_text_proof', label: '再校校正2', tone: 'actor' },
  { type: 'date', code: 'reproof_text_proof_started_on', label: '校正発注', tone: 'date' },
  { type: 'date', code: 'reproof_text_proof_completed_on', label: '校正納品', tone: 'date' },
  { type: 'stage', code: 'third_proof', label: '三校・校正', tone: 'actor' },
  { type: 'stage', code: 'fourth_proof', label: '四校・校正', tone: 'actor' },
];
const pageRows = [
  { code: 'problem', label: '問題頁数' },
  { code: 'answer', label: '解答頁数' },
  { code: 'trend', label: '傾対頁数' },
  { code: 'explanation', label: '解説頁数' },
];

const preparedUnits = computed(() => props.units.map((unit) => ({
  ...unit,
  proofItem: unit.items.find((item) => item.media_name === '問題') ?? unit.items[0] ?? null,
})));

function subject(item, code) {
  return item?.subjects?.find((row) => row.code === code) ?? null;
}
function task(subjectRow, code) {
  return subjectRow?.stages?.find((row) => row.code === code) ?? null;
}
function pageKey(unit, pageType, subjectCode) {
  return `${unit.id}:${pageType}:${subjectCode}`;
}
function errorMessage(error) {
  const errors = error?.response?.data?.errors;
  if (errors) return Object.values(errors).flat()[0] ?? '保存できませんでした。';
  return error?.response?.data?.message ?? '保存できませんでした。';
}
async function savePageCount(unit, pageType, subjectCode, event) {
  const item = unit.proofItem;
  if (!item) return;
  const key = pageKey(unit, pageType, subjectCode);
  const raw = event.target.value;
  const pageCount = raw === '' ? null : Number(raw);
  saving[key] = true;
  try {
    const response = await axios.patch(route('coordinator.mginbon.items.page_count.update', { item: item.id }), {
      subject_code: subjectCode,
      page_type: pageType,
      page_count: pageCount,
    });
    unit.page_counts[`${pageType}:${subjectCode}`] = response.data.page_count;
    window.dispatchEvent(new CustomEvent('toast:show', { detail: { type: 'success', message: 'ページ数を保存しました。' } }));
  } catch (error) {
    event.target.value = unit.page_counts?.[`${pageType}:${subjectCode}`] ?? '';
    window.dispatchEvent(new CustomEvent('toast:show', { detail: { type: 'error', message: errorMessage(error) } }));
  } finally {
    saving[key] = false;
  }
}
function cellSaved(item, subjectRow, payload) {
  item.updated_at = payload.updated_at;
  if (payload.kind === 'date') {
    item.subjects.filter((row) => (payload.subjectIds ?? [subjectRow.id]).includes(row.id))
      .forEach((row) => { row.dates[payload.code] = payload.date; });
    return;
  }
  item.subjects.filter((row) => (payload.subjectIds ?? [subjectRow.id]).includes(row.id)).forEach((row) => {
    const stage = task(row, payload.code);
    if (stage) Object.assign(stage, { actor: payload.actor, target: payload.target, assignment_id: payload.assignment_id, status: payload.status, planned: payload.planned });
  });
}
</script>

<template>
  <section class="min-w-[74rem] bg-white text-[11px] text-gray-900">
    <header class="border-b border-[#54aaa8] bg-[#fbffd7] px-3 py-2 text-center">
      <h1 class="text-xl font-semibold">{{ project.year }}年　中学入試問題集　文字校正</h1>
    </header>

    <article v-for="unit in preparedUnits" :key="unit.id" class="grid grid-cols-[21rem_1fr_22rem] border-b-2 border-gray-700">
      <section class="border-r border-gray-500 p-1">
        <div class="grid grid-cols-5 bg-[#ccffff] text-center text-[10px]">
          <span>媒体分類</span><span>みくにコード</span><span>日能研コード</span><span>日能研分類</span><span>学校分類</span>
        </div>
        <div class="grid grid-cols-5 text-center">
          <span class="border border-gray-400 bg-gray-600 py-1 text-white">{{ unit.proofItem?.media_name ?? '' }}</span>
          <span class="border border-gray-400 py-1">{{ unit.mikuni_code }}</span>
          <span class="border border-gray-400 py-1">{{ unit.n_code }}</span>
          <span class="border border-gray-400 py-1">{{ unit.n_category }}</span>
          <span class="border border-gray-400 py-1">{{ unit.school_category }}</span>
        </div>
        <div class="bg-[#66eeee] px-2 py-2 text-center text-lg font-semibold">{{ unit.display_name }}</div>
        <dl class="mt-2 grid grid-cols-[5rem_1fr] gap-y-1">
          <dt class="text-right">科目</dt><dd class="bg-[#ccffcc] px-1">{{ unit.proofItem?.subjects?.map((row) => row.name).join('・') }}</dd>
          <dt class="text-right">銀本掲載</dt><dd class="bg-[#ccffcc] px-1">{{ unit.proofItem?.publication_status || '○' }}</dd>
          <dt class="text-right">問題原本入稿</dt><dd class="bg-[#ccffcc] px-1">{{ unit.proofItem?.shared_dates?.original_received_on || '' }}</dd>
        </dl>
        <div class="relative mt-2 min-h-24 whitespace-pre-wrap border-t border-dotted border-gray-400 px-1 py-2 text-gray-700">{{ unit.proofItem?.note || '' }}</div>
      </section>

      <section class="min-w-0 overflow-x-auto border-r border-gray-500">
        <table class="w-full min-w-[35rem] table-fixed border-collapse">
          <thead><tr class="bg-[#ccffff] text-center"><th class="w-20 border-b border-r border-white"></th><th v-for="column in subjectColumns" :key="column.code" class="border-b border-r border-white py-1">{{ column.name }}</th></tr></thead>
          <tbody>
            <tr v-for="row in proofRows" :key="row.code" class="h-6">
              <th class="border-b border-r border-gray-300 bg-white px-1 text-right font-medium">{{ row.label }}</th>
              <td v-for="column in subjectColumns" :key="column.code" class="border-b border-r border-white p-0 text-center" :class="row.type === 'stage' ? 'bg-[#e7c187]' : 'bg-white'">
                <LedgerCellEditor v-if="subject(unit.proofItem, column.code)" :item="unit.proofItem" :subject="subject(unit.proofItem, column.code)" :column="row" :task="task(subject(unit.proofItem, column.code), row.code)" :actor-options="actorOptions" :project-linked="projectLinked" @saved="cellSaved(unit.proofItem, subject(unit.proofItem, column.code), $event)" />
              </td>
            </tr>
          </tbody>
        </table>
      </section>

      <section class="self-start p-2">
        <table class="w-full table-fixed border-separate border-spacing-0 overflow-hidden rounded-lg border border-gray-500 bg-[#d8efff] text-center">
          <thead><tr><th class="w-16 bg-white"></th><th v-for="column in subjectColumns" :key="column.code" class="border-b border-l border-white py-1 font-medium">{{ column.name }}</th></tr></thead>
          <tbody>
            <tr v-for="pageRow in pageRows" :key="pageRow.code">
              <th class="border-t border-gray-300 bg-white pr-1 text-right font-medium">{{ pageRow.label }}</th>
              <td v-for="column in subjectColumns" :key="column.code" class="h-8 border-l border-t border-white p-0.5">
                <input type="number" min="1" max="999" step="1"
                  :value="unit.page_counts?.[`${pageRow.code}:${column.code}`] ?? ''"
                  :disabled="!projectLinked || !unit.proofItem || saving[pageKey(unit, pageRow.code, column.code)]"
                  class="h-9 w-full border border-blue-400 bg-white px-1 text-center text-base font-semibold disabled:bg-white disabled:text-gray-400"
                  @change="savePageCount(unit, pageRow.code, column.code, $event)" />
              </td>
            </tr>
          </tbody>
        </table>
      </section>
    </article>
  </section>
</template>
