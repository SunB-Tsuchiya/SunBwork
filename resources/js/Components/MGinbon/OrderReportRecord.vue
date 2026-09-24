<script setup>
const props = defineProps({
  type: { type: String, required: true },
  unit: { type: Object, required: true },
  item: { type: Object, required: true },
  selectable: { type: Boolean, default: false },
  selected: { type: Boolean, default: true },
});
const emit = defineEmits(['toggle']);
const subjectOrder = ['japanese', 'math', 'social', 'science'];
const names = { japanese: '国語', math: '算数', social: '社会', science: '理科' };
const subject = (code) => props.item.subjects?.find((row) => row.code === code);
const date = (code, key) => subject(code)?.dates?.[key]?.slice(5).replace('-', '/') || '';
const stage = (code, key) => subject(code)?.stages?.find((row) => row.code === key)?.actor || '';
const qty = (code, kind, execution) => subject(code)?.measurements?.find((row) => row.work_type === kind && row.execution_type === execution)?.quantity ?? '';
const total = (kind, execution) => subjectOrder.reduce((sum, code) => sum + (Number(qty(code, kind, execution)) || 0), 0);
</script>

<template>
  <article class="order-record relative border-b-2" :class="[type === 'drawing_order' ? 'border-green-700' : 'border-teal-700', `${type}-record`]">
    <label v-if="selectable" class="no-print absolute left-1 top-1 z-10"><input type="checkbox" :checked="selected" @change="emit('toggle', $event.target.checked)" /></label>
    <div class="record-head grid grid-cols-[3.6rem_4.2rem_6.2rem_1fr_5.8rem] text-[11px] leading-5">
      <span class="bg-gray-500 pl-5 text-white">{{ unit.mikuni_code }}</span>
      <span class="bg-[#ccffff] text-center">{{ unit.n_code }}</span>
      <span class="bg-[#ccffff] text-center">{{ unit.n_category || unit.school_category }}</span>
      <b class="truncate bg-[#ccffff] text-center">{{ unit.display_name }}</b>
      <span class="bg-[#ccffcc] text-center">{{ item.media_name }}</span>
    </div>

    <div v-if="type === 'text_order'" class="text-order-grid grid grid-cols-[2.8rem_repeat(4,1fr)_1rem_repeat(4,3.8rem)] text-[10px] leading-[1.15]">
      <span></span><b v-for="code in subjectOrder" :key="'h'+code" class="text-center">{{ names[code] }}</b><span class="admin-only"></span><b v-for="code in subjectOrder" :key="'r'+code" class="admin-only text-center">{{ names[code] }}</b>
      <span class="text-right">発注日</span><span v-for="code in subjectOrder" :key="'o'+code" class="border border-gray-300 text-center">{{ date(code, 'manuscript_received_on') }}</span><span class="admin-only"></span><span v-for="code in subjectOrder" :key="'i'+code" class="admin-only border border-red-200 bg-[#ffcccc] text-center">{{ date(code, 'manuscript_received_on') }}</span>
      <span class="text-right">納品日</span><span v-for="code in subjectOrder" :key="'d'+code" class="border border-gray-300 bg-[#dddddd] text-center">{{ date(code, 'text_input_completed_on') }}</span><span class="admin-only"></span><span v-for="code in subjectOrder" :key="'a'+code" class="admin-only border border-yellow-200 bg-[#ffffcc] text-center">{{ stage(code, 'text_input') }}</span>
    </div>

    <div v-else class="drawing-order-grid grid grid-cols-[4.8rem_repeat(4,1fr)_2.6rem_1rem_repeat(4,3.8rem)] text-[10px] leading-[1.15]">
      <span></span><b v-for="code in subjectOrder" :key="'h'+code" class="text-center">{{ names[code] }}</b><span></span><span class="admin-only"></span><b v-for="code in subjectOrder" :key="'r'+code" class="admin-only text-center">{{ names[code] }}</b>
      <span class="text-right">スキャン点数</span><span v-for="code in subjectOrder" :key="'s'+code" class="border border-gray-300 bg-[#ffffcc] text-center">{{ qty(code, 'scan', type === 'drawing_order' ? 'subcontracted' : 'internal') }}</span><b class="border-b border-gray-400 text-center">{{ total('scan', type === 'drawing_order' ? 'subcontracted' : 'internal') || '' }}</b><span class="admin-only"></span><span v-for="code in subjectOrder" :key="'i'+code" class="admin-only border border-red-200 bg-[#ffcccc] text-center">{{ date(code, 'manuscript_received_on') }}</span>
      <span class="text-right">作図点数</span><span v-for="code in subjectOrder" :key="'g'+code" class="border border-gray-300 bg-[#ccffff] text-center">{{ qty(code, 'drawing', type === 'drawing_order' ? 'subcontracted' : 'internal') }}</span><b class="border-b border-gray-400 text-center">{{ total('drawing', type === 'drawing_order' ? 'subcontracted' : 'internal') || '' }}</b><span class="admin-only"></span><span v-for="code in subjectOrder" :key="'w'+code" class="admin-only border border-yellow-200 bg-[#ffffcc] text-center">{{ stage(code, 'drawing') }}</span>
      <span class="text-right">発注日</span><span v-for="code in subjectOrder" :key="'o'+code" class="border border-gray-300 text-center">{{ date(code, 'manuscript_received_on') }}</span><span></span><span class="admin-only"></span><span v-for="code in subjectOrder" :key="'x'+code" class="admin-only"></span>
      <span class="text-right">納品日</span><span v-for="code in subjectOrder" :key="'d'+code" class="border border-gray-300 bg-[#dddddd] text-center">{{ date(code, 'drawing_completed_on') }}</span><span></span><span class="admin-only"></span><span v-for="code in subjectOrder" :key="'y'+code" class="admin-only"></span>
    </div>

    <div v-if="type === 'order_form'" class="grid grid-cols-[4.8rem_repeat(4,1fr)_2.6rem] text-[10px] leading-[1.15]">
      <span class="text-right">文字入力</span><span v-for="code in subjectOrder" :key="'t'+code" class="border border-gray-300 bg-[#ffffcc] text-center">{{ stage(code, 'text_input') }}</span><span></span>
      <span class="text-right">社外/scan数</span><span v-for="code in subjectOrder" :key="'es'+code" class="border border-gray-300 bg-[#99ffcc] text-center">{{ qty(code, 'scan', 'subcontracted') }}</span><b class="text-center text-blue-700">{{ total('scan', 'subcontracted') }}</b>
      <span class="text-right">社外/作図数</span><span v-for="code in subjectOrder" :key="'eg'+code" class="border border-gray-300 bg-[#99ffcc] text-center">{{ qty(code, 'drawing', 'subcontracted') }}</span><b class="text-center text-blue-700">{{ total('drawing', 'subcontracted') }}</b>
      <span class="text-right">社内/scan数</span><span v-for="code in subjectOrder" :key="'is'+code" class="border border-gray-300 bg-[#99ccff] text-center">{{ qty(code, 'scan', 'internal') }}</span><b class="text-center text-blue-700">{{ total('scan', 'internal') }}</b>
      <span class="text-right">社内/作図</span><span v-for="code in subjectOrder" :key="'ig'+code" class="border border-gray-300 bg-[#99ccff] text-center">{{ qty(code, 'drawing', 'internal') }}</span><b class="text-center text-blue-700">{{ total('drawing', 'internal') }}</b>
    </div>
  </article>
</template>

<style scoped>
.order-record { padding: 1.5mm 0; break-inside: avoid; }
.text_order-record .record-head, .drawing_order-record .record-head { font-size: 13px; line-height: 28px; }
.text_order-record .text-order-grid { font-size: 13px; line-height: 27px; }
.drawing_order-record .drawing-order-grid { font-size: 12px; line-height: 23px; }
@media print {
  .admin-only { display: none !important; }
  .text-order-grid { grid-template-columns: 2.8rem repeat(4, 1fr) !important; }
  .drawing-order-grid { grid-template-columns: 4.8rem repeat(4, 1fr) 2.6rem !important; }
}
</style>
