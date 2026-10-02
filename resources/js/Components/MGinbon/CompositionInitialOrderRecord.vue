<script setup>
const props = defineProps({
  unit: { type: Object, required: true },
  item: { type: Object, required: true },
  selectable: { type: Boolean, default: false },
  selected: { type: Boolean, default: true },
});
const emit = defineEmits(['toggle']);
const subjects = [
  { code: 'japanese', name: '国語' },
  { code: 'math', name: '算数' },
  { code: 'social', name: '社会' },
  { code: 'science', name: '理科' },
];
const subject = (code) => props.item.subjects?.find((row) => row.code === code);
const stage = (code, stageCode) => subject(code)?.stages?.find((row) => row.code === stageCode);
const shortDate = (value) => value ? String(value).slice(5, 10).replace('-', '/') : '';
const date = (code, dateCode) => shortDate(subject(code)?.dates?.[dateCode]);
const uniqueValues = (values) => [...new Set(values.filter(Boolean))].join('・');
const combinedDate = (dateCode) => uniqueValues(subjects.map(({ code }) => date(code, dateCode)));
</script>

<template>
  <article class="composition-order-record relative grid w-[60rem] grid-cols-[37rem_23rem] border-b border-gray-500 py-1 text-[11px]">
    <label v-if="selectable" class="no-print absolute left-1 top-2 z-10"><input type="checkbox" :checked="selected" @change="emit('toggle', $event.target.checked)" /></label>
    <section class="composition-order-left pl-6">
      <div class="grid grid-cols-[3.2rem_4.2rem_5.4rem_1fr_5.5rem] leading-6">
        <span class="bg-gray-500 text-center text-white">{{ unit.mikuni_code }}</span>
        <span class="bg-cyan-100 text-center">{{ unit.n_code }}</span>
        <span class="bg-gray-200 text-center">{{ unit.n_category || unit.school_category }}</span>
        <b class="truncate bg-fuchsia-100 px-1 text-center">{{ unit.display_name }}</b>
        <span class="bg-fuchsia-100 text-center">{{ item.media_name }}</span>
      </div>
      <div class="mt-1 grid grid-cols-[4rem_repeat(4,1fr)] text-center leading-5">
        <span></span><b v-for="entry in subjects" :key="entry.code">{{ entry.name }}</b>
        <span class="text-right">初校組</span><b v-for="entry in subjects" :key="'actor-'+entry.code" class="border border-gray-300 bg-[#66eecb]">{{ stage(entry.code, 'initial_operation')?.actor || '' }}</b>
        <span class="text-right">初校発注日</span><span v-for="entry in subjects" :key="'assigned-'+entry.code" class="border border-gray-300">{{ shortDate(stage(entry.code, 'initial_operation')?.assigned_at) }}</span>
        <span class="text-right">納品日</span><span v-for="entry in subjects" :key="'completed-'+entry.code" class="border border-gray-300 bg-gray-200">{{ shortDate(stage(entry.code, 'initial_operation')?.completed_at) }}</span>
      </div>
    </section>
    <section class="composition-order-supplement pl-4">
      <div class="grid grid-cols-[3rem_repeat(4,5rem)] text-center leading-5">
        <b class="col-start-2 col-span-4 bg-gray-200">国語・算数・社会・理科</b>
        <span class="text-right">入稿</span><span class="border border-gray-300 bg-cyan-50">{{ combinedDate('manuscript_received_on') }}</span><span class="col-span-3"></span>
        <span class="text-right">文字</span><span class="border border-gray-300 bg-cyan-50">{{ combinedDate('text_input_completed_on') }}</span><span class="col-span-3"></span>
        <span class="text-right">作図</span><span v-for="entry in subjects" :key="'drawing-'+entry.code" class="border border-gray-300 bg-cyan-50">{{ stage(entry.code, 'drawing')?.actor || '' }}</span>
        <span class="text-right">納品</span><span v-for="entry in subjects" :key="'drawing-date-'+entry.code" class="border border-gray-300 bg-cyan-50">{{ date(entry.code, 'drawing_completed_on') }}</span>
      </div>
    </section>
  </article>
</template>

<style scoped>
@media print {
  .composition-order-record { display: block; width: 100% !important; padding: 1mm 0; }
  .composition-order-left { width: 100%; padding-left: 0; }
  .composition-order-supplement { display: none !important; }
}
</style>
