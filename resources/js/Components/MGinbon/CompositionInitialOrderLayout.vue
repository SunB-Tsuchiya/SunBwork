<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import CompositionInitialOrderRecord from '@/Components/MGinbon/CompositionInitialOrderRecord.vue';

const props = defineProps({
  project: { type: Object, required: true },
  units: { type: Array, default: () => [] },
});
const records = computed(() => props.units.flatMap((unit) => unit.items.map((item) => ({ unit, item }))));
const selected = ref([]);
watch(records, (rows) => { selected.value = rows.map(({ item }) => item.id); }, { immediate: true });
const selectedRecords = computed(() => records.value.filter(({ item }) => selected.value.includes(item.id)));
const printPages = computed(() => {
  const pages = [];
  for (let index = 0; index < selectedRecords.value.length; index += 7) pages.push(selectedRecords.value.slice(index, index + 7));
  return pages;
});
function toggle(id, checked) {
  selected.value = checked ? [...new Set([...selected.value, id])] : selected.value.filter((value) => value !== id);
}
function clearPrintMode() { document.body.classList.remove('mginbon-composition-order-printing'); }
function printSelected() {
  if (!selected.value.length) return;
  document.body.classList.add('mginbon-composition-order-printing');
  window.addEventListener('afterprint', clearPrintMode, { once: true });
  window.print();
}
onBeforeUnmount(clearPrintMode);
</script>

<template>
  <section class="composition-order-layout bg-white">
    <Teleport defer to="#mginbon-report-controls">
      <div class="flex w-full items-center gap-2 px-3 py-1.5 text-xs">
        <b>組版外注_初校 御中</b><span>選択 {{ selected.length }} / {{ records.length }}媒体</span>
        <button type="button" class="border border-gray-500 bg-white px-3 py-1" @click="selected = records.map(({ item }) => item.id)">すべて選択</button>
        <button type="button" class="border border-gray-500 bg-white px-3 py-1" @click="selected = []">解除</button>
        <button type="button" :disabled="!selected.length" class="ml-auto bg-gray-800 px-4 py-1.5 font-semibold text-white disabled:opacity-40" @click="printSelected">選択した発注書を印刷・PDF</button>
      </div>
    </Teleport>

    <div class="screen-composition-order w-[60rem]">
      <header class="border-b-2 border-cyan-700 bg-cyan-100 px-4 py-2 text-center text-lg font-semibold">{{ project.year }}年　中学入試問題集　初校組　発注書</header>
      <CompositionInitialOrderRecord v-for="{ unit, item } in records" :key="item.id" :unit="unit" :item="item" selectable :selected="selected.includes(item.id)" @toggle="toggle(item.id, $event)" />
    </div>

    <div class="print-composition-order">
      <section v-for="(page, pageIndex) in printPages" :key="pageIndex" class="composition-print-sheet">
        <header class="border-b-2 border-cyan-700 bg-cyan-100 px-4 py-2 text-center text-lg font-semibold">{{ project.year }}年　中学入試問題集　初校組　発注書</header>
        <CompositionInitialOrderRecord v-for="{ unit, item } in page" :key="item.id" :unit="unit" :item="item" />
        <footer class="mt-auto flex border-t border-gray-500 pt-2 text-[9px]"><b>株式会社サン・ブレーン</b><span class="ml-auto">{{ pageIndex + 1 }}/{{ printPages.length }}</span></footer>
      </section>
    </div>
  </section>
</template>

<style scoped>
.print-composition-order { display: none; }
@media print {
  .screen-composition-order { display: none !important; }
  .print-composition-order { display: block !important; }
  .composition-print-sheet { box-sizing: border-box; display: flex; width: 210mm; height: 296mm; flex-direction: column; overflow: hidden; padding: 8mm 12mm; break-after: page; page-break-after: always; background: white; }
  .composition-print-sheet:last-child { break-after: auto; }
  @page { size: A4 portrait; margin: 0; }
}
</style>

<style>
@media print {
  body.mginbon-composition-order-printing { margin: 0 !important; background: white !important; }
  body.mginbon-composition-order-printing .no-print { display: none !important; }
  body.mginbon-composition-order-printing .mginbon-ledger-root { min-height: 0 !important; padding: 0 !important; background: white !important; }
  body.mginbon-composition-order-printing .mginbon-ledger-root > .space-y-3 > :not(.composition-order-layout) { display: none !important; }
}
</style>
