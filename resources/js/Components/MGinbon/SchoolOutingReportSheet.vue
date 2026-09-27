<script setup>
import { computed } from 'vue';
import SchoolOutingDateField from '@/Components/MGinbon/SchoolOutingDateField.vue';
import SchoolOutingActorField from '@/Components/MGinbon/SchoolOutingActorField.vue';

const props = defineProps({
  project: { type: Object, required: true },
  unit: { type: Object, required: true },
  item: { type: Object, required: true },
  includeProgress: { type: Boolean, default: false },
  focusSubject: { type: String, default: null },
  answerPresence: { type: String, default: '' },
  answerSelectable: { type: Boolean, default: false },
  dateEditable: { type: Boolean, default: false },
  actorEditable: { type: Boolean, default: false },
  actorOptions: { type: Object, default: () => ({ users: [], subcontractors: [] }) },
  projectLinked: { type: Boolean, default: false },
  selectable: { type: Boolean, default: false },
  selected: { type: Boolean, default: true },
});
const emit = defineEmits(['toggle', 'update:answerPresence', 'dateSaved', 'actorSaved']);
const subjectOrder = ['japanese', 'math', 'social', 'science'];
const subjectNames = { japanese: '国語', math: '算数', social: '社会', science: '理科' };
const subjects = computed(() => subjectOrder.filter((code) => props.item.subjects?.some((row) => row.code === code)));
const displayedSubjects = computed(() => props.focusSubject ? subjectOrder : subjects.value);
const focusedSubjectRow = computed(() => props.focusSubject
  ? props.item.subjects?.find((subject) => subject.code === props.focusSubject)
  : null);
const rawDateValue = (key) => (props.focusSubject ? focusedSubjectRow.value?.dates?.[key] : null)
    || props.item.shared_dates?.[key]
    || props.item.subjects?.map((subject) => subject.dates?.[key]).find(Boolean)
    || '';
const dateValue = (key) => {
  if (!props.includeProgress) return '';
  const raw = rawDateValue(key);
  return raw ? raw.slice(2).replaceAll('-', '/') : '';
};
const editableSubjectIds = computed(() => props.focusSubject
  ? [focusedSubjectRow.value?.id].filter(Boolean)
  : props.item.subjects?.map((subject) => subject.id) || []);
const displayedActorSubjects = computed(() => props.focusSubject
  ? [focusedSubjectRow.value].filter(Boolean)
  : props.item.subjects || []);
const actorValue = (stageCode) => {
  if (!props.includeProgress) return '';
  const sourceSubjects = props.focusSubject ? [focusedSubjectRow.value].filter(Boolean) : props.item.subjects;
  return [...new Set(sourceSubjects?.flatMap((subject) => subject.stages || [])
    .filter((stage) => stage.code === stageCode).map((stage) => stage.actor).filter(Boolean) || [])].join('／');
};
const noteValue = computed(() => props.includeProgress ? (props.item.note || '') : '');
const stageRows = [
  { cycle: '作図', role: 'OP', stage: 'drawing' },
  { cycle: '初校', role: 'OP', stage: 'initial_operation' },
  { cycle: '', role: '初校チェック', stage: 'initial_check' },
  { cycle: '', hint: '突き合わせ', role: '初校校正', stage: 'initial_text_proof' },
  { cycle: '再校', role: 'OP', stage: 'reproof_operation' },
  { cycle: '', hint: '赤字照合→\n画像確認', role: '再校校正', stage: 'reproof_scan_check' },
  { cycle: '', hint: '突き合わせ', role: '再校校正', stage: 'reproof_text_proof' },
  { cycle: '三校', role: 'OP', stage: 'third_operation' },
  { cycle: '', hint: '赤字照合', role: '三校校正', stage: 'third_proof' },
  { cycle: '四校', role: 'OP', stage: 'fourth_operation' },
  { cycle: '', hint: '赤字照合', role: '四校校正', stage: 'fourth_proof' },
  { cycle: '五校', role: 'OP', stage: 'fifth_operation' },
  { cycle: '', hint: '赤字照合', role: '五校校正', stage: null },
];
const dateAfterStage = {
  initial_check: [{ label: '初校出', code: 'initial_shared_on' }],
  reproof_text_proof: [{ label: '再校出', code: 'reproof_shared_on' }],
  third_proof: [{
    label: '三校出', code: 'third_shared_on',
    secondary: { label: '三校戻', code: 'third_returned_on' },
  }],
  fourth_proof: [{ label: '四校出', code: 'fourth_shared_on' }],
};
const timeline = stageRows.flatMap((row) => [
  { type: 'stage', ...row },
  ...(dateAfterStage[row.stage] || []).map((date) => ({ type: 'date', ...date })),
]);
</script>

<template>
  <article class="outing-sheet relative bg-white text-black">
    <label v-if="selectable" class="no-print absolute -left-7 top-2"><input type="checkbox" :checked="selected" @change="emit('toggle', $event.target.checked)" /></label>

    <header class="grid grid-cols-[1fr_1.55fr_.72fr_.72fr] overflow-hidden rounded-xl border border-gray-500 text-[28px] leading-[44px]">
      <b class="bg-gray-300 text-center">{{ project.year }}年</b>
      <b class="bg-gray-400 text-center">中学入試問題集</b>
      <b class="bg-black text-center text-white">{{ item.media_name }}</b>
      <span class="flex items-center justify-center bg-white px-1 text-center text-lg font-bold">
        <select v-if="answerSelectable" :value="answerPresence" class="no-print h-8 w-full border-gray-400 py-0 pl-2 pr-6 text-sm font-normal" @change="emit('update:answerPresence', $event.target.value)"><option value="">空欄</option><option value="解答あり">解答あり</option><option value="解答なし">解答なし</option></select>
        <span v-else>{{ answerPresence }}</span>
      </span>
    </header>

    <section class="mt-1 rounded-xl bg-[#c6f1ff] px-3 py-2.5">
      <div class="grid grid-cols-[5rem_5rem_7rem_1fr] gap-3 text-center text-[13px] font-medium leading-tight">
        <div><span>みくにコード</span><b class="mt-1 block bg-white py-1 text-lg font-normal leading-6">{{ unit.mikuni_code }}</b></div>
        <div><span>日能研コード</span><b class="mt-1 block bg-white py-1 text-lg font-normal leading-6">{{ unit.n_code }}</b></div>
        <div><span>日能研分類</span><b class="mt-1 block bg-white py-1 text-lg font-normal leading-6">{{ unit.n_category || unit.school_category }}</b></div>
        <div><span>学校分類</span><b class="mt-1 block bg-white py-1 text-lg font-normal leading-6">{{ unit.school_category }}</b></div>
      </div>
      <h1 class="mt-2 bg-white px-3 py-2 text-center text-[27px] font-extrabold leading-9">{{ unit.display_name }}<span v-if="unit.exam_session">（{{ unit.exam_session }}）</span></h1>
      <div class="mt-1.5 grid grid-cols-[3rem_1fr_5rem_5rem] items-center text-base font-medium"><span>科目</span><span class="flex gap-3 bg-white px-2 py-1"><span v-for="code in displayedSubjects" :key="code" :class="focusSubject && code !== focusSubject ? 'text-gray-300' : 'font-bold text-black'">{{ subjectNames[code] }}</span></span><span class="text-right">銀本掲載</span><span class="bg-white px-2 py-0.5 text-center text-[28px] font-bold leading-8">{{ item.publication_status || '○' }}</span></div>
    </section>

    <section class="mt-2 grid grid-cols-[8rem_10rem_minmax(0,1fr)] gap-3 text-sm font-medium">
      <div class="min-h-14 bg-[#dcefd1] px-2 py-1"><b class="text-xs font-normal">入稿日</b><SchoolOutingDateField compact :item="item" code="manuscript_received_on" :value="rawDateValue('manuscript_received_on')" :subject-ids="editableSubjectIds" :include-progress="includeProgress" :editable="dateEditable" @saved="emit('dateSaved', $event)" /></div>
      <div class="flex min-w-0 items-start gap-2 px-1 py-1"><b class="shrink-0 whitespace-nowrap pt-1 text-xs font-normal">文字UP</b><span class="h-7 min-w-0 flex-1 border border-gray-400 bg-gray-200"><SchoolOutingDateField compact :item="item" code="text_input_completed_on" :value="rawDateValue('text_input_completed_on')" :subject-ids="editableSubjectIds" :include-progress="includeProgress" :editable="dateEditable" @saved="emit('dateSaved', $event)" /></span></div>
      <div class="ml-1 min-h-14 min-w-0 whitespace-pre-wrap border border-gray-400 p-1.5 text-xs">{{ noteValue }}</div>
    </section>

    <section class="mt-4 space-y-1">
      <div v-for="(entry, index) in timeline" :key="`${entry.type}-${entry.stage || entry.code}-${index}`">
        <div v-if="entry.type === 'date'" class="relative h-9">
          <div class="absolute left-0 top-1/2 z-10 h-10 w-28 -translate-y-1/2 bg-[#dcefd1] px-2 py-0.5 text-[11px] font-normal leading-3"><span class="block">{{ entry.label }}</span><SchoolOutingDateField compact :item="item" :code="entry.code" :value="rawDateValue(entry.code)" :subject-ids="editableSubjectIds" :include-progress="includeProgress" :editable="dateEditable" @saved="emit('dateSaved', $event)" /></div>
          <span class="absolute left-28 right-0 top-1/2 block border-t border-gray-500"></span>
          <div v-if="entry.secondary" class="absolute left-0 top-[calc(50%+1.25rem)] z-10 h-10 w-28 bg-[#dcefd1] px-2 py-0.5 text-[11px] font-normal leading-3"><span class="block">{{ entry.secondary.label }}</span><SchoolOutingDateField compact :item="item" :code="entry.secondary.code" :value="rawDateValue(entry.secondary.code)" :subject-ids="editableSubjectIds" :include-progress="includeProgress" :editable="dateEditable" @saved="emit('dateSaved', $event)" /></div>
        </div>
        <div v-else class="grid min-h-9 grid-cols-[7rem_5.2rem_6.6rem_1fr] items-stretch gap-x-1 text-[14px] font-medium">
          <div></div>
          <div v-if="entry.cycle" class="flex items-center justify-center bg-black px-2 text-base font-bold text-white">{{ entry.cycle }}</div>
          <div v-else class="flex items-center justify-end whitespace-pre-line text-right leading-tight">{{ entry.hint || '' }}</div>
          <b class="flex items-center whitespace-nowrap bg-gray-500 px-2 text-[13px] font-semibold text-white">{{ entry.role }}</b>
          <span class="grid grid-cols-[1fr_7.5rem] items-center border border-gray-500 bg-white">
            <SchoolOutingActorField :item="item" :subjects="displayedActorSubjects" :stage-code="entry.stage" :actor-options="actorOptions" :include-progress="includeProgress" :editable="actorEditable" :project-linked="projectLinked" @saved="emit('actorSaved', $event)" />
            <span class="whitespace-nowrap px-1 text-center text-[11px] font-semibold">作業時間（　　　）</span>
          </span>
        </div>
      </div>
    </section>

    <div class="ml-auto mt-5 grid h-14 w-44 grid-cols-[3rem_1fr] items-center rounded-xl border border-black px-3 text-base font-medium"><span>校了</span><span class="text-center text-xl">{{ dateValue('completed_on') || '／' }}</span></div>
  </article>
</template>

<style scoped>
.outing-sheet { box-sizing: border-box; width: 210mm; min-height: 285mm; padding: 8mm 10mm; font-family: Arial, 'Noto Sans JP', sans-serif; }
@media screen {
  .outing-sheet { border: 1px solid #aaa; box-shadow: 0 2px 8px rgb(0 0 0 / 15%); }
}
@media print {
  .outing-sheet { height: 296mm; min-height: 296mm; overflow: hidden; break-after: page; page-break-after: always; }
  .outing-sheet:last-child { break-after: auto; page-break-after: auto; }
}
</style>
