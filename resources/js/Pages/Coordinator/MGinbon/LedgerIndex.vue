<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import SchoolHeader from '@/Components/MGinbon/SchoolHeader.vue';
import IntakeCheckBlock from '@/Components/MGinbon/IntakeCheckBlock.vue';
import LedgerCellEditor from '@/Components/MGinbon/LedgerCellEditor.vue';
import ToastUnified from '@/Components/ToastUnified.vue';

const props = defineProps({
  project: { type: Object, required: true }, projects: { type: Array, default: () => [] }, projectJobs: { type: Array, default: () => [] },
  units: { type: Object, required: true }, summary: { type: Object, required: true }, mediaOptions: { type: Array, default: () => [] },
  subjects: { type: Array, default: () => [] }, filters: { type: Object, required: true },
  actorOptions: { type: Object, default: () => ({ users: [], subcontractors: [] }) },
});
const form = reactive({ ...props.filters });
const projectLinkForm = useForm({ project_job_id: props.project.project_job_id ?? '' });
const toolbarVisible = ref(true);
let lastScrollY = 0;
function handleWindowScroll() {
  const currentY = Math.max(0, window.scrollY);
  if (currentY < 24 || currentY < lastScrollY - 6) toolbarVisible.value = true;
  else if (currentY > lastScrollY + 6 && currentY > 110) toolbarVisible.value = false;
  lastScrollY = currentY;
}
onMounted(() => {
  lastScrollY = window.scrollY;
  window.addEventListener('scroll', handleWindowScroll, { passive: true });
});
onBeforeUnmount(() => window.removeEventListener('scroll', handleWindowScroll));
const ledgerColumns = [
  ['date', 'manuscript_received_on', '入稿', '入稿', 'intake'], ['date', 'manuscript_due_on', '入稿指定', '入稿', 'intake'],
  ['date', 'text_input_completed_on', '文字UP', '入稿', 'prepress'], ['date', 'drawing_completed_on', '作図UP', '入稿', 'prepress'],
  ['stage', 'initial_operation', '初校組', '初校', 'operation'], ['stage', 'initial_check', 'チェック', '初校', 'operation'],
  ['date', 'initial_shared_on', '初校出', '初校', 'output'], ['stage', 'initial_text_proof', '初校校正', '初校', 'proof'],
  ['date', 'initial_text_proof_started_on', '校正入', '初校', 'proof'], ['date', 'initial_text_proof_completed_on', '校正UP', '初校', 'proof'],
  ['shared', 'original_received_on', '原本入稿', '入稿', 'scan'], ['shared', 'original_scan_completed_on', 'スキャンUP', '入稿', 'scan'],
  ['date', 'initial_returned_on', '初校戻り', '初校', 'intake'],
  ['stage', 'reproof_operation', '初校修正', '再校', 'operation'], ['stage', 'reproof_scan_check', '校正①', '再校', 'proof'],
  ['date', 'reproof_scan_check_started_on', '校正入', '再校', 'proof'], ['date', 'reproof_scan_check_completed_on', '校正UP', '再校', 'proof'],
  ['date', 'reproof_shared_on', '再校出', '再校', 'output'], ['stage', 'reproof_text_proof', '校正②', '再校', 'proof'],
  ['date', 'reproof_text_proof_started_on', '校正入', '再校', 'proof'], ['date', 'reproof_text_proof_completed_on', '校正UP', '再校', 'proof'],
  ['date', 'reproof_returned_on', '再校戻り', '再校', 'intake'],
  ['stage', 'third_operation', '三校修正', '三校以降', 'operation'], ['stage', 'third_proof', '校正', '三校以降', 'proof'],
  ['date', 'third_shared_on', '三校出', '三校以降', 'output'], ['date', 'third_returned_on', '三校戻り', '三校以降', 'intake'],
  ['stage', 'client_return_operation', 'みくに戻りOP', '三校以降', 'operation'], ['stage', 'fourth_operation', '四校修正', '三校以降', 'operation'],
  ['stage', 'fourth_proof', '校正', '三校以降', 'proof'], ['date', 'fourth_shared_on', '四校出', '三校以降', 'output'],
  ['date', 'fourth_returned_on', '四校戻り', '三校以降', 'intake'], ['stage', 'fifth_operation', '五校修正', '三校以降', 'operation'],
  ['date', 'fifth_shared_on', '五校出', '三校以降', 'output'], ['date', 'completed_on', '校了', '三校以降', 'complete'],
].map(([type, code, label, group, tone]) => ({ type, code, label, group, tone }));
const reproofSharedIndex = ledgerColumns.findIndex((column) => column.code === 'reproof_shared_on');
const ledgerBands = [
  { key: 'first', columns: ledgerColumns.slice(0, reproofSharedIndex) },
  { key: 'second', columns: ledgerColumns.slice(reproofSharedIndex) },
];
const displayedRange = computed(() => props.units.total ? `${props.units.from}～${props.units.to}校 / ${props.units.total}校` : '0校');
const search = () => router.get(route('coordinator.mginbon.index'), { ...form }, { preserveState: true, preserveScroll: true, replace: true });
function setView(view) { form.view = view; search(); }
function resetFilters() { Object.assign(form, { search: '', media: '', subject: '', status: 'all', per_page: 10 }); search(); }
function saveProjectLink() { projectLinkForm.put(route('coordinator.mginbon.projects.project_link', { project: props.project.id }), { preserveScroll: true }); }
function createProjectLink() { if (window.confirm(`${props.project.year}年の銀本専用案件を新規作成して接続しますか？`)) router.post(route('coordinator.mginbon.projects.project_link.create', { project: props.project.id }), {}, { preserveScroll: true }); }
const task = (subject, code) => subject.stages?.find((row) => row.code === code);
const columnClass = (tone) => ({ intake: 'bg-[#ffccff]', prepress: 'bg-[#fff0cb]', scan: 'bg-[#dadada]', operation: 'bg-[#66ffcc]', output: 'bg-[#fffb83]', proof: 'bg-white', complete: 'bg-[#ccffcc]' })[tone] ?? 'bg-white';
const paginationLabel = (label) => label.replace('&laquo; Previous', '前へ').replace('Next &raquo;', '次へ');
function cellSaved(item, subject, payload) {
  item.updated_at = payload.updated_at;
  if (payload.kind === 'date') {
    if (payload.subjectId === null) item.shared_dates[payload.code] = payload.date;
    else subject.dates[payload.code] = payload.date;
    return;
  }
  const row = task(subject, payload.code);
  if (row) Object.assign(row, { actor: payload.actor, target: payload.target, assignment_id: payload.assignment_id, status: payload.status, planned: payload.planned });
}
</script>

<template>
  <Head title="銀本進行" />
  <div class="min-h-screen w-full bg-gray-100 p-2 sm:p-3">
    <ToastUnified />
    <div class="space-y-3">
      <section class="sticky top-0 z-50 -mx-2 -mt-2 border-b border-gray-500 bg-white shadow-sm transition-transform duration-200 sm:-mx-3 sm:-mt-3" :class="toolbarVisible ? 'translate-y-0' : '-translate-y-full'">
        <div class="overflow-x-auto">
          <div class="min-w-[72rem] text-xs text-gray-800">
            <div class="flex h-12 items-stretch border-b border-gray-300">
              <div class="flex items-center border-r border-gray-300 px-1">
                <button type="button" class="h-8 w-8 border border-gray-300 bg-white text-2xl leading-none hover:bg-gray-100" title="前のページ" @click="window.history.back()">‹</button>
                <button type="button" class="ml-1 h-8 w-8 border border-gray-500 bg-white text-2xl leading-none hover:bg-gray-100" title="次のページ" @click="window.history.forward()">›</button>
              </div>
              <div class="flex min-w-40 items-center gap-2 border-r border-gray-300 px-3">
                <span class="flex h-7 w-7 items-center justify-center rounded-full border-4 border-gray-300 bg-white"></span>
                <div><div><b>{{ units.from || 0 }}–{{ units.to || 0 }}</b> / {{ units.total }}校</div><div class="text-[10px]">{{ project.year }}年 中学入試問題集</div></div>
              </div>
              <button type="button" class="min-w-24 border-r border-gray-300 px-3 hover:bg-gray-100" @click="resetFilters"><span class="block text-lg">▣</span>すべてを表示</button>
              <Link :href="route('coordinator.mginbon.import_preview')" class="min-w-20 border-r border-gray-300 px-3 py-1 text-center hover:bg-gray-100"><span class="block text-lg">＋</span>取込確認</Link>
              <button v-if="!project.project_job_id" type="button" class="min-w-20 border-r border-gray-300 px-3 hover:bg-gray-100" @click="createProjectLink"><span class="block text-lg">■</span>案件作成</button>
              <Link :href="route('coordinator.dashboard')" class="min-w-24 border-r border-gray-300 px-3 py-1 text-center hover:bg-gray-100"><span class="block text-lg">↗</span>通常サイト</Link>
              <form class="ml-auto flex items-center gap-1 px-2" @submit.prevent="search"><span class="text-lg">⌕</span><input v-model="form.search" type="search" placeholder="学校名・コードを検索" class="h-7 w-60 border-gray-400 px-2 py-1 text-xs" /><button class="h-7 border border-gray-400 bg-white px-3 hover:bg-gray-100">検索</button></form>
            </div>
            <div class="flex h-8 items-center gap-3 border-b border-gray-400 bg-gray-50 px-1">
              <label class="flex items-center gap-1">レイアウト:<select v-model="form.view" class="h-6 w-44 border-gray-400 py-0 pl-2 pr-7 text-xs" @change="search"><option value="list">LIST</option><option value="intake">入稿チェック</option></select></label>
              <span class="flex items-center gap-1">表示方法の切り替え:<button type="button" class="border border-gray-400 bg-gray-600 px-2 py-0.5 text-white">▤</button><button type="button" class="border border-gray-400 bg-white px-2 py-0.5">▦</button><button type="button" class="border border-gray-400 bg-white px-2 py-0.5">▦</button></span>
              <span class="border border-gray-400 bg-white px-3 py-0.5">プレビュー</span>
              <span class="ml-auto font-semibold">{{ displayedRange }}（全{{ summary.total }}媒体）</span>
            </div>
            <form class="flex h-8 items-center gap-2 bg-[#aeb2b5] px-1" @submit.prevent="search">
              <select v-model.number="form.year" class="h-6 w-28 border-gray-400 py-0 pl-2 pr-7 text-xs"><option v-for="p in projects" :key="p.id" :value="p.year">{{ p.year }}年</option></select>
              <select v-model="form.media" class="h-6 w-36 border-gray-400 py-0 pl-2 pr-7 text-xs"><option value="">媒体: すべて</option><option v-for="m in mediaOptions" :key="m">{{ m }}</option></select>
              <select v-model="form.subject" class="h-6 w-32 border-gray-400 py-0 pl-2 pr-7 text-xs"><option value="">教科: すべて</option><option v-for="s in subjects" :key="s.code" :value="s.code">{{ s.name }}</option></select>
              <select v-model="form.status" class="h-6 w-32 border-gray-400 py-0 pl-2 pr-7 text-xs"><option value="all">状態: すべて</option><option value="draft">下書き</option><option value="review_required">要確認</option></select>
              <select v-model.number="form.per_page" class="h-6 w-28 border-gray-400 py-0 pl-2 pr-7 text-xs"><option :value="10">10校／頁</option><option :value="25">25校／頁</option><option :value="50">50校／頁</option></select>
              <button class="h-6 border border-gray-500 bg-white px-4 hover:bg-gray-100">絞り込む</button>
              <label class="ml-auto flex items-center gap-1 text-white">連携案件:<select v-model="projectLinkForm.project_job_id" class="h-6 w-72 border-gray-400 py-0 pl-2 pr-7 text-xs text-gray-800"><option value="">未接続</option><option v-for="job in projectJobs" :key="job.id" :value="job.id">{{ job.jobcode ? `${job.jobcode} / ` : '' }}{{ job.title }}</option></select></label>
              <button type="button" class="h-6 border border-gray-600 bg-gray-700 px-3 text-white" @click="saveProjectLink">保存</button>
            </form>
          </div>
        </div>
      </section>
      <section v-if="units.data.length" class="border-t border-gray-700 bg-white">
        <article v-for="unit in units.data" :key="unit.id" class="border-b border-gray-700">
          <template v-if="form.view === 'intake'">
            <SchoolHeader :unit="unit"><span v-if="unit.review_status === 'review_required'" class="bg-orange-100 px-2 py-1 text-xs text-orange-800">要確認</span></SchoolHeader>
            <IntakeCheckBlock v-for="item in unit.items" :key="item.id" :item="item" />
          </template>
          <template v-else>
            <section v-for="item in unit.items" :key="item.id" class="grid border-b-2 border-gray-700 last:border-b-0 lg:grid-cols-[17rem_minmax(0,1fr)]">
              <div class="bg-white p-1 text-xs leading-snug">
                <div class="grid h-6 grid-cols-4 items-center bg-[#ccffff] text-center text-[11px]">
                  <span>みくにコード</span><span>日能研コード</span><span>分類</span><span>媒体</span>
                </div>
                <div class="grid h-6 grid-cols-4 gap-0.5 text-center text-xs">
                  <span class="border border-gray-400 bg-gray-500 py-1 text-white">{{ unit.mikuni_code || '' }}</span>
                  <span class="border border-gray-400 bg-white py-1">{{ unit.n_code || '' }}</span>
                  <span class="border border-gray-400 bg-white py-1">{{ unit.n_category || unit.school_category || '' }}</span>
                  <span class="border border-gray-400 bg-white py-1">{{ item.media_name }}</span>
                </div>
                <div class="bg-[#f6fad3] px-1 py-1.5 text-sm font-semibold">{{ unit.display_name }}</div>
                <div class="grid h-7 grid-cols-[1fr_4.25rem_2.75rem] items-center gap-0.5">
                  <span class="bg-[#ccffcc] px-1 py-1">{{ item.subjects.map((subject) => subject.name).join('・') }}</span>
                  <span class="bg-[#ccffcc] px-1 py-1 text-center">{{ item.publication_status || '○' }}</span>
                  <Link :href="route('coordinator.mginbon.items.show', { item: item.id })" class="bg-gray-200 px-1 py-1 text-center hover:bg-gray-300">編集</Link>
                </div>
                <div class="relative mt-1 min-h-[7.25rem] bg-white px-0.5 py-1 text-gray-700">
                  <div class="absolute inset-x-0 top-7 border-t border-dotted border-gray-400"></div>
                  <div class="absolute inset-x-0 top-14 border-t border-dotted border-gray-400"></div>
                  <div class="absolute inset-x-0 top-[5.25rem] border-t border-dotted border-gray-400"></div>
                  <span class="relative whitespace-pre-wrap">{{ item.note || '' }}</span>
                </div>
              </div>
              <div class="min-w-0 overflow-x-auto bg-white">
                <table v-for="band in ledgerBands" :key="band.key" class="w-[65rem] min-w-[65rem] table-fixed border-collapse border-b border-gray-600 text-[11px] leading-tight last:border-b-0">
                  <thead><tr class="h-6 bg-[#ccffff] text-center">
                    <th class="w-11 px-1 py-1"></th>
                    <th v-for="column in band.columns" :key="column.code" class="border-r border-white px-1 py-1 font-medium">{{ column.label }}</th>
                  </tr></thead>
                  <tbody><tr v-for="subject in item.subjects" :key="subject.id" class="border-b border-white text-center last:border-b-0">
                    <th class="h-6 w-11 bg-white px-1 py-1 text-right text-xs font-semibold">{{ subject.name }}</th>
                    <td v-for="column in band.columns" :key="column.code" class="h-6 border-r border-white p-0 font-medium" :class="columnClass(column.tone)">
                      <LedgerCellEditor :item="item" :subject="subject" :column="column" :task="task(subject, column.code)" :actor-options="actorOptions" :project-linked="Boolean(project.project_job_id)" @saved="cellSaved(item, subject, $event)" />
                    </td>
                  </tr></tbody>
                </table>
              </div>
            </section>
          </template>
        </article>
      </section>
      <div v-else class="rounded bg-white p-8 text-center text-sm text-gray-500 shadow">条件に一致する学校はありません。</div>
      <nav v-if="units.links.length > 3" class="flex flex-wrap justify-center gap-1"><template v-for="link in units.links" :key="`${link.label}-${link.url}`"><Link v-if="link.url" :href="link.url" preserve-scroll class="rounded border px-3 py-1.5 text-sm" :class="link.active ? 'border-green-600 bg-green-600 text-white' : 'bg-white'">{{ paginationLabel(link.label) }}</Link><span v-else class="rounded border bg-gray-50 px-3 py-1.5 text-sm text-gray-400">{{ paginationLabel(link.label) }}</span></template></nav>
    </div>
  </div>
</template>
