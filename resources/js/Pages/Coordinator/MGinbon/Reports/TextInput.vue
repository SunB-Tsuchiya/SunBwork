<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({ project: { type: Object, required: true }, rows: { type: Array, required: true }, printedAt: { type: String, required: true } });
const pages = computed(() => Array.from({ length: Math.ceil(props.rows.length / 8) }, (_, index) => props.rows.slice(index * 8, index * 8 + 8)));
const shortDate = (value) => value ? value.slice(5).replace('-', '/') : '';
function closePreview() {
  window.close();
  window.setTimeout(() => {
    if (!window.closed) window.location.assign(route('coordinator.mginbon.index', { year: props.project.year, view: 'intake' }));
  }, 150);
}
</script>

<template>
  <Head title="文字入力発注書" />
  <div class="no-print sticky top-0 z-10 flex items-center gap-3 border-b bg-gray-100 px-4 py-2 text-sm"><button type="button" class="rounded bg-gray-800 px-4 py-2 font-semibold text-white" @click="window.print()">印刷・PDF保存</button><button type="button" class="rounded border bg-white px-4 py-2" @click="closePreview">閉じる</button><span>{{ rows.length }}媒体 / {{ pages.length }}ページ</span></div>
  <main class="print-root mx-auto bg-gray-200 py-5">
    <section v-for="(page, pageIndex) in pages" :key="pageIndex" class="print-page mx-auto mb-5 bg-white px-[12mm] py-[10mm] text-[10pt] shadow">
      <header class="mb-3 border-b-2 border-black pb-2"><div class="flex items-end justify-between"><div><div class="text-sm">宛先：____________________________ 御中</div><h1 class="mt-1 text-center text-xl font-bold">文字入力発注書</h1></div><div class="text-right text-xs"><div>{{ project.year }}年　中学入試問題集</div><div>発行 {{ printedAt }}</div><div>{{ pageIndex + 1 }} / {{ pages.length }}</div></div></div></header>
      <article v-for="row in page" :key="row.id" class="record mb-2 border border-black">
        <div class="grid grid-cols-[4.5rem_5rem_1fr_6rem] border-b border-black bg-gray-100 font-semibold"><span class="border-r border-black px-1 py-1">M {{ row.mikuni_code }}</span><span class="border-r border-black px-1 py-1">N {{ row.n_code }}</span><span class="border-r border-black px-1 py-1">{{ row.school_name }}</span><span class="px-1 py-1 text-center">{{ row.media_name }}</span></div>
        <div class="grid grid-cols-[7rem_repeat(4,1fr)]"><div class="border-r border-black px-1 py-1"><div>{{ row.classification }}</div><div class="mt-1 text-[8pt]">{{ row.note }}</div></div><div v-for="subject in row.subjects" :key="subject.name" class="border-r border-black p-1 last:border-r-0"><div class="border-b text-center font-semibold">{{ subject.name }}</div><div class="grid grid-cols-[3rem_1fr] gap-y-0.5 pt-1"><span>発注日</span><span class="border-b border-black text-center">{{ shortDate(subject.ordered_on) }}</span><span>納品日</span><span class="border-b border-black text-center">{{ shortDate(subject.delivered_on) }}</span><span>担当</span><span class="truncate border-b border-black text-center">{{ subject.actor }}</span></div></div></div>
      </article>
    </section>
  </main>
</template>

<style scoped>
.print-page { width: 210mm; min-height: 297mm; }
.record { break-inside: avoid; }
@page { size: A4 portrait; margin: 0; }
@media print {
  .no-print { display: none !important; }
  .print-root { background: white; padding: 0; }
  .print-page { margin: 0; box-shadow: none; break-after: page; }
  .print-page:last-child { break-after: auto; }
}
</style>
