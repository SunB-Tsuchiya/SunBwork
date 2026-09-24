<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import OrderReportRecord from '@/Components/MGinbon/OrderReportRecord.vue';

const props = defineProps({
  type: { type: String, required: true },
  project: { type: Object, required: true },
  units: { type: Array, default: () => [] },
});
const records = computed(() => props.units.flatMap((unit) => unit.items.map((item) => ({ unit, item }))));
const selected = ref([]);
watch(records, (rows) => { selected.value = rows.map(({ item }) => item.id); }, { immediate: true });
const selectedRecords = computed(() => records.value.filter(({ item }) => selected.value.includes(item.id)));
const pageSize = computed(() => props.type === 'text_order' ? 8 : (props.type === 'drawing_order' ? 6 : 4));
const printPages = computed(() => {
  const pages = [];
  for (let i = 0; i < selectedRecords.value.length; i += pageSize.value) pages.push(selectedRecords.value.slice(i, i + pageSize.value));
  return pages;
});
const qty = (item, kind) => item.subjects?.reduce((sum, subject) => sum + Number(subject.measurements?.find((row) => row.work_type === kind && row.execution_type === 'subcontracted')?.quantity || 0), 0) || 0;
const grandTotal = (kind) => selectedRecords.value.reduce((sum, { item }) => sum + qty(item, kind), 0);
const title = computed(() => ({ order_form: '作図＆文字発注フォーム', text_order: 'C&C 文字入力発注書', drawing_order: '大連若葉 作図発注書' })[props.type]);
function toggle(id, checked) { selected.value = checked ? [...new Set([...selected.value, id])] : selected.value.filter((value) => value !== id); }
function clearPrintMode() { document.body.classList.remove('mginbon-report-printing'); }
function printSelected() {
  if (!selected.value.length) return;
  document.body.classList.add('mginbon-report-printing');
  window.addEventListener('afterprint', clearPrintMode, { once: true });
  window.print();
}
onBeforeUnmount(clearPrintMode);
</script>

<template>
  <section class="order-layout bg-white">
    <div class="no-print sticky top-[5.5rem] z-30 flex items-center gap-2 border border-gray-400 bg-gray-100 px-3 py-2 text-xs shadow">
      <b>{{ title }}</b><span>選択 {{ selected.length }} / {{ records.length }}媒体</span>
      <button type="button" class="border border-gray-500 bg-white px-3 py-1" @click="selected = records.map(({ item }) => item.id)">すべて選択</button>
      <button type="button" class="border border-gray-500 bg-white px-3 py-1" @click="selected = []">解除</button>
      <button type="button" :disabled="!selected.length" class="ml-auto bg-gray-800 px-4 py-1.5 font-semibold text-white disabled:opacity-40" @click="printSelected">選択したレコードを印刷・PDF</button>
    </div>

    <div class="screen-report">
      <header class="report-header" :class="type === 'drawing_order' ? 'bg-[#ccffff]' : (type === 'order_form' ? 'bg-[#ffffd7]' : 'bg-[#f2f2f2]')">
        <div v-if="type === 'drawing_order'" class="flex items-center gap-3 whitespace-nowrap text-[12px]"><b>大連若葉御中</b><b>{{ project.year }}年</b><b>中学入試問題集</b><b>発注書</b><b class="bg-blue-800 px-2 text-white">作図</b><span class="ml-auto text-xs">作図/合計 <b>{{ grandTotal('drawing') }}</b>　スキャン/合計 <b>{{ grandTotal('scan') }}</b>　□ 未請求でお願いします。</span></div>
        <div v-else-if="type === 'text_order'" class="flex items-center gap-7"><b>シーアンドシー御中</b><b>{{ project.year }}年</b><b>中学入試問題集</b><b>発注書</b></div>
        <div v-else class="flex items-center gap-5"><b>{{ project.year }}年</b><b>中学入試問題集</b><b>文字＆作図</b><b>外注</b></div>
      </header>
      <OrderReportRecord v-for="{ unit, item } in records" :key="item.id" :type="type" :unit="unit" :item="item" selectable :selected="selected.includes(item.id)" @toggle="toggle(item.id, $event)" />
    </div>

    <div class="print-report">
      <section v-for="(page, pageIndex) in printPages" :key="pageIndex" class="print-sheet">
        <header class="report-header" :class="type === 'drawing_order' ? 'bg-[#ccffff]' : (type === 'order_form' ? 'bg-[#ffffd7]' : 'bg-[#f2f2f2]')">
          <div v-if="type === 'drawing_order'" class="flex items-center gap-3 whitespace-nowrap text-[12px]"><b>大連若葉御中</b><b>{{ project.year }}年</b><b>中学入試問題集</b><b>発注書</b><b class="bg-blue-800 px-2 text-white">作図</b><span class="ml-auto text-[9px]">作図/合計 <b>{{ grandTotal('drawing') }}</b>　スキャン/合計 <b>{{ grandTotal('scan') }}</b>　□ 未請求でお願いします。</span></div>
          <div v-else-if="type === 'text_order'" class="flex items-center gap-7"><b>シーアンドシー御中</b><b>{{ project.year }}年</b><b>中学入試問題集</b><b>発注書</b></div>
          <div v-else class="flex items-center gap-5"><b>{{ project.year }}年</b><b>中学入試問題集</b><b>文字＆作図</b><b>外注</b></div>
        </header>
        <div class="record-column"><OrderReportRecord v-for="{ unit, item } in page" :key="item.id" :type="type" :unit="unit" :item="item" /></div>
        <footer class="report-footer"><strong>▰ SUN BRAIN</strong><span>株式会社サン・ブレーン　情報出版部　担当：鈴木和人</span><span>Tel:03-3823-3164　e-mail:ka-suzuki@suna.co.jp</span><span class="ml-auto">{{ pageIndex + 1 }}/{{ printPages.length }}</span></footer>
      </section>
    </div>
  </section>
</template>

<style scoped>
.report-header { border-bottom: 2px solid #087a78; padding: .55rem 1rem; font-size: 17px; }
.screen-report { width: 56rem; }
.print-report { display: none; }
.report-footer { display: flex; align-items: center; gap: .75rem; border-top: 1px solid #999; padding-top: 2mm; font-size: 9px; }
.report-footer strong { font-size: 18px; color: #1746b0; }
@media print {
  .screen-report { display: none !important; }
  .print-report { display: block !important; }
  .admin-only { display: none !important; }
  .print-sheet { box-sizing: border-box; display: flex; width: 210mm; height: 297mm; flex-direction: column; overflow: hidden; padding: 8mm 10mm 7mm; break-after: page; background: white; }
  .print-sheet:last-child { break-after: auto; }
  .print-sheet .report-header { flex: none; }
  .record-column { flex: none; }
  .report-footer { margin-top: auto; flex: none; }
  @page { size: A4 portrait; margin: 0; }
}
</style>

<style>
@media print {
  body.mginbon-report-printing { margin: 0 !important; background: white !important; }
  body.mginbon-report-printing .no-print { display: none !important; }
  body.mginbon-report-printing .mginbon-ledger-root { min-height: 0 !important; padding: 0 !important; background: white !important; }
  body.mginbon-report-printing .mginbon-ledger-root > :not(.space-y-3) { display: none !important; }
  body.mginbon-report-printing .mginbon-ledger-root .space-y-3 > :not(.order-layout) { display: none !important; }
  body.mginbon-report-printing .mginbon-ledger-root .order-layout { margin: 0 !important; }
}
</style>
