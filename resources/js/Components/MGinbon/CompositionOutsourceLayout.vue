<script setup>
import { computed } from 'vue';

const props = defineProps({
  project: { type: Object, required: true },
  units: { type: Array, default: () => [] },
});

const subjectColumns = [
  { code: 'japanese', name: '国語' },
  { code: 'math', name: '算数' },
  { code: 'social', name: '社会' },
  { code: 'science', name: '理科' },
];
const operationRows = [
  { code: 'initial_operation', label: '初校組' },
  { code: 'reproof_operation', label: '再校組' },
  { code: 'third_operation', label: '三校組' },
  { code: 'fourth_operation', label: '四校組', dates: false },
];
const preparedUnits = computed(() => props.units.map((unit) => ({
  ...unit,
  displayItem: unit.items.find((item) => item.media_name === '問題') ?? unit.items[0] ?? null,
})));
const subject = (item, code) => item?.subjects?.find((row) => row.code === code);
const task = (item, subjectCode, stageCode) => subject(item, subjectCode)?.stages?.find((row) => row.code === stageCode);
const actor = (item, subjectCode, stageCode) => task(item, subjectCode, stageCode)?.actor ?? '';
const shortDate = (value) => value ? String(value).slice(5, 10).replace('-', '/') : '';
const taskDate = (item, subjectCode, stageCode, key) => shortDate(task(item, subjectCode, stageCode)?.[key]);
const milestone = (item, subjectCode, code) => shortDate(subject(item, subjectCode)?.dates?.[code]);
</script>

<template>
  <section class="w-[44rem] bg-white text-[12px] text-gray-900">
    <header class="grid grid-cols-[1fr_auto_1fr] items-center border-b border-[#54aaa8] bg-[#fbffd7] px-3 py-2">
      <div></div>
      <h1 class="text-xl font-semibold">{{ project.year }}年　中学入試問題集</h1>
      <b class="justify-self-end rounded-lg border border-green-700 bg-green-100 px-4 py-1 text-lg">組版外注</b>
    </header>

    <article v-for="unit in preparedUnits" :key="unit.id" class="grid grid-cols-[17rem_27rem] border-b-2 border-gray-700">
      <section class="border-r border-gray-500 p-1">
        <div class="grid h-7 grid-cols-4 text-center text-[11px]">
          <b class="border border-gray-400 bg-gray-600 py-1 text-white">{{ unit.displayItem?.media_name || '' }}</b>
          <span class="border border-gray-400 bg-cyan-100 py-1">{{ unit.mikuni_code }}</span>
          <span class="border border-gray-400 py-1">{{ unit.n_code }}</span>
          <span class="border border-gray-400 bg-gray-200 py-1">{{ unit.n_category || unit.school_category }}</span>
        </div>
        <div class="bg-[#ccffff] px-1 py-1.5 text-center text-sm font-semibold">{{ unit.display_name }}</div>
        <dl class="mt-1 grid grid-cols-[4.5rem_1fr] gap-y-px">
          <dt class="text-right">科目</dt><dd class="bg-[#ccffcc] px-1">{{ unit.displayItem?.subjects?.map((row) => row.name).join('・') }}</dd>
          <dt class="text-right">銀本掲載</dt><dd class="bg-[#ccffcc] px-1">{{ unit.displayItem?.publication_status || '' }}</dd>
          <dt class="text-right">問題原本入稿</dt><dd class="bg-[#ccffcc] px-1">{{ shortDate(unit.displayItem?.shared_dates?.original_received_on) }}</dd>
        </dl>
        <div class="mt-1 min-h-20 whitespace-pre-wrap border-t border-dotted border-gray-400 px-1 py-1 text-gray-700">{{ unit.displayItem?.note || '' }}</div>
      </section>

      <section>
        <table class="w-[27rem] table-fixed border-collapse text-center">
          <colgroup><col class="w-28" /><col v-for="column in subjectColumns" :key="column.code" class="w-20" /></colgroup>
          <thead><tr class="h-7 bg-[#ccffff]"><th></th><th v-for="column in subjectColumns" :key="column.code" class="border-l border-white py-1">{{ column.name }}</th></tr></thead>
          <tbody>
            <tr class="h-7"><th class="pr-1 text-right font-medium">入稿日</th><td v-for="column in subjectColumns" :key="column.code" class="border border-gray-300 bg-[#ffccff] py-1">{{ milestone(unit.displayItem, column.code, 'manuscript_received_on') }}</td></tr>
            <template v-for="row in operationRows" :key="row.code">
              <tr class="h-7"><th class="pr-1 text-right font-medium">{{ row.label }}</th><td v-for="column in subjectColumns" :key="column.code" class="border border-white bg-[#66eecb] py-1 font-semibold">{{ actor(unit.displayItem, column.code, row.code) }}</td></tr>
              <tr v-if="row.dates !== false" class="h-7"><th class="pr-1 text-right font-medium">発注日</th><td v-for="column in subjectColumns" :key="column.code" class="border border-gray-300 py-1">{{ taskDate(unit.displayItem, column.code, row.code, 'assigned_at') }}</td></tr>
              <tr v-if="row.dates !== false" class="h-7"><th class="pr-1 text-right font-medium">納品日</th><td v-for="column in subjectColumns" :key="column.code" class="border border-gray-300 bg-gray-300 py-1">{{ taskDate(unit.displayItem, column.code, row.code, 'completed_at') }}</td></tr>
            </template>
          </tbody>
        </table>
      </section>
    </article>
  </section>
</template>
