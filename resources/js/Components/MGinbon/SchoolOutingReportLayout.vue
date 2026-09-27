<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import SchoolOutingReportSheet from '@/Components/MGinbon/SchoolOutingReportSheet.vue';

const props = defineProps({
  project: { type: Object, required: true },
  units: { type: Array, default: () => [] },
  actorOptions: { type: Object, default: () => ({ users: [], subcontractors: [] }) },
  projectLinked: { type: Boolean, default: false },
});

const records = computed(() => props.units.flatMap((unit) => unit.items.map((item) => ({ unit, item }))));
const selected = ref([]);
const includeProgress = ref(false);
const subjectMode = ref('all');
const answerSelections = reactive({});
const subjectOrder = ['japanese', 'math', 'social', 'science'];
watch(records, (rows) => {
  selected.value = rows.map(({ item }) => item.id);
  rows.forEach(({ item }) => {
    if (!(item.id in answerSelections)) answerSelections[item.id] = '';
  });
}, { immediate: true });
const selectedRecords = computed(() => records.value.filter(({ item }) => selected.value.includes(item.id)));
const expandSheets = (rows) => rows.flatMap((record) => subjectMode.value === 'single'
  ? subjectOrder.map((subjectCode) => ({ ...record, subjectCode }))
  : [{ ...record, subjectCode: null }]);
const screenSheets = computed(() => expandSheets(records.value));
const printSheets = computed(() => expandSheets(selectedRecords.value));
const printSheetCount = computed(() => selected.value.length * (subjectMode.value === 'single' ? subjectOrder.length : 1));
const toggle = (id, checked) => {
  selected.value = checked ? [...new Set([...selected.value, id])] : selected.value.filter((value) => value !== id);
};
function dateSaved(item, payload) {
  item.updated_at = payload.updated_at;
  item.subjects.filter((subject) => payload.subjectIds.includes(subject.id)).forEach((subject) => {
    subject.dates ||= {};
    subject.dates[payload.code] = payload.date;
  });
}
function actorSaved(item, payload) {
  item.updated_at = payload.updated_at;
  item.subjects.filter((subject) => payload.subjectIds.includes(subject.id)).forEach((subject) => {
    const stage = subject.stages?.find((row) => row.code === payload.code);
    if (stage) Object.assign(stage, {
      actor: payload.actor,
      target: payload.target,
      assignment_id: payload.assignment_id,
      status: payload.status,
      planned: payload.planned,
    });
  });
}
function clearPrintMode() { document.body.classList.remove('mginbon-school-outing-printing'); }
function printSelected() {
  if (!selected.value.length) return;
  document.body.classList.add('mginbon-school-outing-printing');
  window.addEventListener('afterprint', clearPrintMode, { once: true });
  window.print();
}
onBeforeUnmount(clearPrintMode);
</script>

<template>
  <section class="school-outing-layout">
    <Teleport defer to="#mginbon-report-controls">
      <div class="flex w-full items-center gap-3 px-3 py-1.5 text-xs">
        <b>出校表</b><span>選択 {{ selected.length }} / {{ records.length }}媒体（{{ printSheetCount }}枚）</span>
        <label class="flex items-center gap-1"><input v-model="includeProgress" type="radio" :value="false" />空欄で印刷</label>
        <label class="flex items-center gap-1"><input v-model="includeProgress" type="radio" :value="true" />現在値を反映</label>
        <span class="ml-2 flex overflow-hidden border border-gray-500">
          <button type="button" class="px-3 py-1" :class="subjectMode === 'all' ? 'bg-gray-700 text-white' : 'bg-white'" @click="subjectMode = 'all'">全教科</button>
          <button type="button" class="border-l border-gray-500 px-3 py-1" :class="subjectMode === 'single' ? 'bg-gray-700 text-white' : 'bg-white'" @click="subjectMode = 'single'">単教科</button>
        </span>
        <button type="button" class="border border-gray-500 bg-white px-3 py-1" @click="selected = records.map(({ item }) => item.id)">すべて選択</button>
        <button type="button" class="border border-gray-500 bg-white px-3 py-1" @click="selected = []">解除</button>
        <button type="button" :disabled="!selected.length" class="ml-auto bg-gray-800 px-4 py-1.5 font-semibold text-white disabled:opacity-40" @click="printSelected">選択した出校表を印刷・PDF</button>
      </div>
    </Teleport>

    <div class="screen-outing space-y-4">
      <SchoolOutingReportSheet
        v-for="({ unit, item, subjectCode }, index) in screenSheets"
        :key="`${item.id}-${subjectCode || 'all'}`"
        :project="project"
        :unit="unit"
        :item="item"
        :include-progress="includeProgress"
        :focus-subject="subjectCode"
        :answer-presence="answerSelections[item.id]"
        answer-selectable
        date-editable
        actor-editable
        :actor-options="actorOptions"
        :project-linked="projectLinked"
        selectable
        :selected="selected.includes(item.id)"
        @toggle="toggle(item.id, $event)"
        @update:answer-presence="answerSelections[item.id] = $event"
        @date-saved="dateSaved(item, $event)"
        @actor-saved="actorSaved(item, $event)"
      />
    </div>

    <div class="print-outing">
      <SchoolOutingReportSheet
        v-for="{ unit, item, subjectCode } in printSheets"
        :key="`${item.id}-${subjectCode || 'all'}`"
        :project="project"
        :unit="unit"
        :item="item"
        :include-progress="includeProgress"
        :focus-subject="subjectCode"
        :answer-presence="answerSelections[item.id]"
        :actor-options="actorOptions"
        :project-linked="projectLinked"
      />
    </div>
  </section>
</template>

<style scoped>
.screen-outing { width: 210mm; }
.print-outing { display: none; }
@media print {
  .screen-outing { display: none !important; }
  .print-outing { display: block !important; }
}
</style>

<style>
@media print {
  body.mginbon-school-outing-printing { margin: 0 !important; background: white !important; }
  body.mginbon-school-outing-printing .no-print { display: none !important; }
  body.mginbon-school-outing-printing .mginbon-ledger-root { min-height: 0 !important; padding: 0 !important; background: white !important; }
  body.mginbon-school-outing-printing .mginbon-ledger-root > .space-y-3 > :not(.school-outing-layout) { display: none !important; }
  @page { size: A4 portrait; margin: 0; }
}
</style>
