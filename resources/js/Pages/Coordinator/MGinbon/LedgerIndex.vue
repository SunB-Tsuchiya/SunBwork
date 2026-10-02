<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import axios from 'axios';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import CompositionInitialOrderLayout from '@/Components/MGinbon/CompositionInitialOrderLayout.vue';
import CompositionOutsourceLayout from '@/Components/MGinbon/CompositionOutsourceLayout.vue';
import ConvenientSearchMenu from '@/Components/MGinbon/ConvenientSearchMenu.vue';
import FilenameBulkMenu from '@/Components/MGinbon/FilenameBulkMenu.vue';
import IntakeCheckBlock from '@/Components/MGinbon/IntakeCheckBlock.vue';
import LedgerCellEditor from '@/Components/MGinbon/LedgerCellEditor.vue';
import LedgerFindLayout from '@/Components/MGinbon/LedgerFindLayout.vue';
import LedgerTableLayout from '@/Components/MGinbon/LedgerTableLayout.vue';
import OrderReportLayout from '@/Components/MGinbon/OrderReportLayout.vue';
import SchoolOutingReportLayout from '@/Components/MGinbon/SchoolOutingReportLayout.vue';
import TextProofLayout from '@/Components/MGinbon/TextProofLayout.vue';
import ToastUnified from '@/Components/ToastUnified.vue';

const props = defineProps({
  project: { type: Object, required: true }, projects: { type: Array, default: () => [] }, projectJobs: { type: Array, default: () => [] },
  units: { type: Object, required: true }, summary: { type: Object, required: true }, mediaOptions: { type: Array, default: () => [] },
  subjects: { type: Array, default: () => [] }, filters: { type: Object, required: true },
  actorOptions: { type: Object, default: () => ({ users: [], subcontractors: [] }) },
  projectLinkLocked: { type: Boolean, default: false },
  preparation: { type: Object, required: true },
});
const uniqueMediaOptions = computed(() => [...new Set(props.mediaOptions.filter(Boolean))]);
const reportViews = ['order_form', 'text_order', 'drawing_order', 'scan_order', 'school_outing_problem', 'composition_outsource', 'composition_initial_order'];
const pageTitle = computed(() => ({ preparation: '銀本 年度準備', list: '銀本進行', intake: '入稿チェック', text_proof: '文字校正', composition_outsource: '組版外注', composition_initial_order: '組版外注 初校発注書', order_form: '作図＆文字発注フォーム', text_order: 'C&C文字発注', drawing_order: '大連若葉作図発注書', scan_order: '原本スキャン発注書', school_outing_problem: '出稿表' })[form.view] || '銀本進行');
const form = reactive({ ...props.filters });
const projectLinkForm = useForm({ project_job_id: props.project.project_job_id ?? '' });
const toolbarVisible = ref(true);
const displayModeStorageKey = 'mginbon-ledger-display-mode';
const displayModes = ['single', 'list', 'table'];
const displayMode = ref('list');
const singleRecordIndex = ref(0);
const findMode = ref(false);
const activeFindIndex = ref(0);
const emptyFindRequest = () => ({ omit: false, conditions: [] });
const findOperators = [
  { value: '=', label: '=  単語全体が一致（または空白に一致）' },
  { value: '==', label: '== フィールド全体が一致' },
  { separator: true },
  { value: '!', label: '!  重複する値の検索' },
  { separator: true },
  { value: '<', label: '<  小さい' },
  { value: '≤', label: '≤  小さいか等しい' },
  { value: '>', label: '>  大きい' },
  { value: '≥', label: '≥  大きいか等しい' },
  { value: '...', label: '... 範囲' },
  { value: '//', label: '// 現在の日付' },
  { value: '?', label: '?  無効な日付か時刻' },
  { separator: true },
  { value: '@', label: '@  任意の1文字' },
  { value: '#', label: '#  任意の1つの数字' },
  { value: '*', label: '*  任意の文字列' },
  { value: '\\', label: '¥  後続文字をエスケープ' },
  { value: '"', label: '"  フレーズに一致（単語の始めから）' },
  { value: '*"', label: '*" フレーズに一致（どこからでも）' },
  { separator: true },
  { value: '~', label: '~  ゆるやかな検索（日本語のみ）' },
];
const selectedFindOperator = ref('');
const findRequests = ref([emptyFindRequest()]);
const convenientSearchOpen = ref(false);
const filenameBulkOpen = ref(false);
const simulationActive = ref(false);
const showLedgerResults = computed(() => !findMode.value || simulationActive.value);
const previewCount = ref(null);
const previewing = ref(false);
let previewTimer = null;
const serializeFind = (requests = findRequests.value) => JSON.stringify({ version: 2, requests });
const currentFindCriteria = computed(() => ({ version: 2, requests: findRequests.value }));
const parsedExecutedRequests = computed(() => {
  if (!form.find) return [];
  try {
    const decoded = JSON.parse(form.find);
    return Array.isArray(decoded) ? decoded : (decoded?.requests ?? []);
  } catch { return []; }
});
const conditionFieldLabels = { school_name: '学校名', media: '媒体', category: '分類', mikuni_code: 'Mコード', n_code: 'Nコード', publication_status: '掲載', note: '備考', milestone: '工程日付', actor: '担当者', anomaly: '異常' };
const searchConditionChips = computed(() => parsedExecutedRequests.value.map((request, index) => ({
  index,
  label: request.label || request.conditions?.map((condition) => `${conditionFieldLabels[condition.field] || condition.field}:${condition.value}`).join('・') || `条件${index + 1}`,
  omit: Boolean(request.omit),
})));

const recordNumber = ref(props.units.from || 0);
const mediaOrder = ['問題', '解答のみ', '解説解答', '解答用紙', '傾向と対策', '解答'];
const mediaRank = (name) => {
  const index = mediaOrder.indexOf(name);
  return index === -1 ? mediaOrder.length : index;
};
const sortedMediaUnits = computed(() => props.units.data
  .flatMap((unit, unitIndex) => (
    unit.items.length
      ? unit.items.map((item, itemIndex) => ({ ...unit, items: [item], mediaRank: mediaRank(item.media_name), unitIndex, itemIndex }))
      : [{ ...unit, items: [], mediaRank: mediaOrder.length + 1, unitIndex, itemIndex: 0 }]
  ))
  .sort((left, right) => left.mediaRank - right.mediaRank || left.unitIndex - right.unitIndex || left.itemIndex - right.itemIndex));
const listRecords = computed(() => sortedMediaUnits.value.map((unit) => ({ unit, item: unit.items[0] ?? null })));
const singleUnits = computed(() => {
  const record = listRecords.value[singleRecordIndex.value];
  if (!record) return [];
  return [{ ...record.unit, items: record.item ? [record.item] : [] }];
});
const visibleUnits = computed(() => (
  form.view === 'list' && displayMode.value === 'single' ? singleUnits.value : sortedMediaUnits.value
));
watch(listRecords, (records) => {
  if (!records.length) singleRecordIndex.value = 0;
  else singleRecordIndex.value = Math.min(singleRecordIndex.value, records.length - 1);
  recordNumber.value = records.length ? (props.units.from || 1) + singleRecordIndex.value : 0;
});
watch(() => props.units.from, () => {
  singleRecordIndex.value = 0;
  recordNumber.value = props.units.from || 0;
});
watch(findRequests, () => {
  if (convenientSearchOpen.value) schedulePreviewCount();
}, { deep: true });
function setDisplayMode(mode) {
  if (!displayModes.includes(mode)) return;
  displayMode.value = mode;
  if (mode === 'single') singleRecordIndex.value = 0;
  window.localStorage.setItem(displayModeStorageKey, mode);
}
function goToRecord(number) {
  const total = props.units.total || 0;
  if (!total) return;
  const target = Math.min(Math.max(Number(number) || 1, 1), total);
  const page = Math.ceil(target / Number(form.per_page || 25));
  setDisplayMode('single');
  router.get(route('coordinator.mginbon.index'), { ...form, page }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
    onSuccess: () => {
      singleRecordIndex.value = (target - 1) % Number(form.per_page || 25);
      recordNumber.value = target;
    },
  });
}
function moveSingleRecord(offset) {
  const current = (props.units.from || 1) + singleRecordIndex.value;
  goToRecord(current + offset);
}
function beginFind() {
  simulationActive.value = false;
  findRequests.value = [emptyFindRequest()];
  activeFindIndex.value = 0;
  findMode.value = true;
}
function addFindRequest() {
  findRequests.value.push(emptyFindRequest());
  activeFindIndex.value = findRequests.value.length - 1;
}
function deleteFindRequest() {
  if (findRequests.value.length === 1) findRequests.value[0] = emptyFindRequest();
  else findRequests.value.splice(activeFindIndex.value, 1);
  activeFindIndex.value = Math.min(activeFindIndex.value, findRequests.value.length - 1);
}
function setFindRequestOmit(omit) {
  if (findRequests.value[activeFindIndex.value]) findRequests.value[activeFindIndex.value].omit = omit;
}
function insertFindOperator() {
  if (!selectedFindOperator.value) return;
  window.dispatchEvent(new CustomEvent('mginbon-find-insert-operator', { detail: selectedFindOperator.value }));
  selectedFindOperator.value = '';
}
function applyConvenientSearch({ mode, request }) {
  simulationActive.value = false;
  const next = structuredClone(request);
  const hasDraftConditions = findRequests.value.some((entry) => entry.conditions?.length);
  if (mode === 'refine' && !hasDraftConditions && parsedExecutedRequests.value.length) {
    findRequests.value = structuredClone(parsedExecutedRequests.value);
  }
  if (mode === 'replace' || (mode !== 'refine' && !findRequests.value.some((entry) => entry.conditions?.length))) {
    findRequests.value = [next];
  } else if (mode === 'add') {
    findRequests.value.push(next);
  } else {
    const included = findRequests.value.filter((entry) => !entry.omit);
    if (included.length) included.forEach((entry) => entry.conditions.push(...structuredClone(next.conditions)));
    else findRequests.value.push(next);
  }
  activeFindIndex.value = Math.max(0, findRequests.value.length - 1);
}
async function refreshPreviewCount() {
  if (!convenientSearchOpen.value) return;
  previewing.value = true;
  try {
    const response = await axios.post(route('coordinator.mginbon.search.preview_count'), {
      year: form.year, search: form.search, media: form.media, subject: form.subject, status: form.status,
      find: serializeFind(),
    });
    previewCount.value = response.data.count;
  } catch { previewCount.value = null; }
  finally { previewing.value = false; }
}
function schedulePreviewCount() {
  window.clearTimeout(previewTimer);
  previewTimer = window.setTimeout(refreshPreviewCount, 400);
}
function toggleConvenientSearch() {
  convenientSearchOpen.value = !convenientSearchOpen.value;
  if (convenientSearchOpen.value) schedulePreviewCount();
}
function removeSearchChip(index) {
  const requests = structuredClone(parsedExecutedRequests.value);
  requests.splice(index, 1);
  form.find = requests.length ? serializeFind(requests) : '';
  search();
}

async function recordSearchHistory(criteria, resultCount) {
  try {
    await axios.post(route('coordinator.mginbon.search_histories.store'), {
      project_id: props.project.id, criteria, result_count: resultCount, display_mode: displayMode.value,
    });
  } catch { /* 検索結果の表示を履歴保存失敗で妨げない */ }
}
function restoreConvenientSearch({ criteria, displayMode: restoredMode, execute }) {
  simulationActive.value = false;
  const requests = Array.isArray(criteria) ? criteria : (criteria?.requests ?? []);
  findRequests.value = structuredClone(requests.length ? requests : [emptyFindRequest()]);
  activeFindIndex.value = 0;
  if (displayModes.includes(restoredMode)) setDisplayMode(restoredMode);
  if (execute) nextTick(executeFind);
  else schedulePreviewCount();
}
function simulateFind() {
  if (!currentFindCriteria.value.requests.some((request) => request.conditions?.length)) return;
  form.find = JSON.stringify(currentFindCriteria.value);
  router.get(route('coordinator.mginbon.index'), { ...form }, {
    preserveState: true, preserveScroll: true, replace: true,
    onSuccess: () => { simulationActive.value = true; },
  });
}
function retrySimulation() {
  simulationActive.value = false;
  nextTick(() => schedulePreviewCount());
}
function executeFind() {
  simulationActive.value = false;
  const criteria = currentFindCriteria.value;
  form.find = JSON.stringify(criteria);
  router.get(route('coordinator.mginbon.index'), { ...form }, {
    preserveState: true, preserveScroll: true, replace: true,
    onSuccess: (page) => recordSearchHistory(criteria, Number(page.props.units?.total ?? 0)),
  });
  findRequests.value = [emptyFindRequest()];
  activeFindIndex.value = 0;
  findMode.value = false;
  convenientSearchOpen.value = false;
}
function cancelFind() {
  simulationActive.value = false;
  findRequests.value = [emptyFindRequest()];
  activeFindIndex.value = 0;
  findMode.value = false;
  convenientSearchOpen.value = false;
}
let lastScrollY = 0;
function handleWindowScroll() {
  const currentY = Math.max(0, window.scrollY);
  if (currentY < 24 || currentY < lastScrollY - 6) toolbarVisible.value = true;
  else if (currentY > lastScrollY + 6 && currentY > 110) toolbarVisible.value = false;
  lastScrollY = currentY;
}
onMounted(() => {
  const savedDisplayMode = window.localStorage.getItem(displayModeStorageKey);
  if (displayModes.includes(savedDisplayMode)) displayMode.value = savedDisplayMode;
  lastScrollY = window.scrollY;
  window.addEventListener('scroll', handleWindowScroll, { passive: true });
});
onBeforeUnmount(() => {
  window.removeEventListener('scroll', handleWindowScroll);
  window.clearTimeout(previewTimer);
});
const ledgerColumns = [
  ['date', 'manuscript_received_on', '入稿', '入稿', 'intake'], ['date', 'manuscript_due_on', '入稿指定', '入稿', 'intake'],
  ['date', 'text_input_completed_on', '文字UP', '入稿', 'prepress'], ['date', 'drawing_completed_on', '作図UP', '入稿', 'prepress'],
  ['stage', 'initial_operation', '初校組', '初校', 'operation'], ['stage', 'initial_check', 'チェック', '初校', 'operation'],
  ['date', 'initial_shared_on', '初校出', '初校', 'output'], ['stage', 'initial_text_proof', '初校校正', '初校', 'proof'],
  ['date', 'initial_text_proof_started_on', '初校校正入', '初校', 'proof'], ['date', 'initial_text_proof_completed_on', '初校校正UP', '初校', 'proof'],
  ['shared', 'original_received_on', '原本入稿', '入稿', 'scan'], ['shared', 'original_scan_completed_on', 'スキャンUP', '入稿', 'scan'],
  ['date', 'initial_returned_on', '初校戻り', '初校', 'intake'],
  ['stage', 'reproof_operation', '初校修正', '再校', 'operation'], ['stage', 'reproof_scan_check', '校正①校正', '再校', 'proof'],
  ['date', 'reproof_scan_check_started_on', '校正①校正入', '再校', 'proof'], ['date', 'reproof_scan_check_completed_on', '校正①校正UP', '再校', 'proof'],
  ['date', 'reproof_shared_on', '再校出', '再校', 'output'], ['stage', 'reproof_text_proof', '校正②校正', '再校', 'proof'],
  ['date', 'reproof_text_proof_started_on', '校正②校正入', '再校', 'proof'], ['date', 'reproof_text_proof_completed_on', '校正②校正UP', '再校', 'proof'],
  ['date', 'reproof_returned_on', '再校戻り', '再校', 'intake'],
  ['stage', 'third_operation', '三校修正', '三校以降', 'operation'], ['stage', 'third_proof', '三校校正', '三校以降', 'proof'],
  ['date', 'third_shared_on', '三校出', '三校以降', 'output'], ['date', 'third_returned_on', '三校戻り', '三校以降', 'intake'],
  ['stage', 'client_return_operation', 'みくに戻りOP', '三校以降', 'operation'], ['stage', 'fourth_operation', '四校修正', '三校以降', 'operation'],
  ['stage', 'fourth_proof', '四校校正', '三校以降', 'proof'], ['date', 'fourth_shared_on', '四校出', '三校以降', 'output'],
  ['date', 'fourth_returned_on', '四校戻り', '三校以降', 'intake'], ['stage', 'fifth_operation', '五校修正', '三校以降', 'operation'],
  ['date', 'fifth_shared_on', '五校出', '三校以降', 'output'], ['date', 'completed_on', '校了', '三校以降', 'complete'],
].map(([type, code, label, group, tone]) => ({ type, code, label, group, tone }));
const reproofSharedIndex = ledgerColumns.findIndex((column) => column.code === 'reproof_shared_on');
const ledgerBands = [
  { key: 'first', columns: ledgerColumns.slice(0, reproofSharedIndex) },
  { key: 'second', columns: ledgerColumns.slice(reproofSharedIndex) },
];
const descriptorFieldLabels = { mikuni_code: 'みくにコード', n_code: '日能研コード', category: '分類', school_name: '学校名', media: '媒体', publication_status: '銀本掲載', note: '備考', subject: '科目', milestone: '工程日付', actor: '担当者', anomaly: '異常チェック' };
const descriptorCodeLabel = (code) => ledgerColumns.find((column) => column.code === code)?.label || code;
const matchesDescriptor = (item, field, code = '', subject = '') => (item.match_descriptors ?? []).some((descriptor) => descriptor.field === field && (!code || descriptor.code === code) && (!descriptor.subject || !subject || descriptor.subject === subject));
const matchCellClass = (item, field, code = '', subject = '') => matchesDescriptor(item, field, code, subject) ? 'ring-2 ring-inset ring-emerald-600' : '';
const descriptorReason = (descriptor) => { const field = descriptorFieldLabels[descriptor.field] || descriptor.field; const code = descriptor.code ? ` ${descriptorCodeLabel(descriptor.code)}` : ''; const subject = descriptor.subject ? `（${({ japanese: '国語', math: '算数', social: '社会', science: '理科' })[descriptor.subject] || descriptor.subject}）` : ''; return `${descriptor.omit ? '除外: ' : ''}${field}${code}${subject}：${descriptor.value}`; };
const displayedRange = computed(() => props.units.total ? `${props.units.from}～${props.units.to} / ${props.units.total}レコード` : '0レコード');
const search = () => router.get(route('coordinator.mginbon.index'), { ...form }, { preserveState: true, preserveScroll: true, replace: true });
function setView(view) { form.view = view; search(); }
function resetFilters() {
  Object.assign(form, { search: '', media: '', subject: '', status: 'all', per_page: 25, find: '' });
  findRequests.value = [emptyFindRequest()];
  activeFindIndex.value = 0;
  findMode.value = false;
  convenientSearchOpen.value = false;
  simulationActive.value = false;
  search();
}
function saveProjectLink() { projectLinkForm.put(route('coordinator.mginbon.projects.project_link', { project: props.project.id }), { preserveScroll: true }); }
function createProjectLink() { if (window.confirm(`${props.project.year}年の銀本専用案件を新規作成して接続しますか？`)) router.post(route('coordinator.mginbon.projects.project_link.create', { project: props.project.id }), {}, { preserveScroll: true }); }
const task = (subject, code) => subject.stages?.find((row) => row.code === code);
const columnClass = (tone) => ({ intake: 'bg-[#ffccff]', prepress: 'bg-[#fff0cb]', scan: 'bg-[#dadada]', operation: 'bg-[#66ffcc]', output: 'bg-[#fffb83]', proof: 'bg-white', complete: 'bg-[#ccffcc]' })[tone] ?? 'bg-white';
const columnBorderClass = (tone) => tone === 'proof'
  ? 'border border-black/50 outline outline-1 -outline-offset-1 outline-dotted outline-black/50'
  : 'border-r border-white';
const paginationLabel = (label) => label.replace('&laquo; Previous', '前へ').replace('Next &raquo;', '次へ');
function cellSaved(item, subject, payload) {
  item.updated_at = payload.updated_at;
  if (payload.kind === 'date') {
    if (payload.subjectIds === null) item.shared_dates[payload.code] = payload.date;
    else item.subjects.filter((row) => payload.subjectIds.includes(row.id)).forEach((row) => { row.dates[payload.code] = payload.date; });
    return;
  }
  const subjectIds = payload.subjectIds ?? [subject.id];
  item.subjects.filter((row) => subjectIds.includes(row.id)).forEach((targetSubject) => {
    const row = task(targetSubject, payload.code);
    if (row) Object.assign(row, { actor: payload.actor, target: payload.target, assignment_id: payload.assignment_id, status: payload.status, planned: payload.planned });
  });
}
</script>

<template>
  <Head :title="pageTitle" />
  <div class="min-h-screen w-full bg-gray-100 mginbon-ledger-root p-2 print:bg-white print:p-0 sm:p-3">
    <ToastUnified />
    <div class="space-y-3">
      <section class="no-print sticky top-0 z-50 -mx-2 -mt-2 border-b border-gray-500 bg-white shadow-sm transition-transform duration-200 sm:-mx-3 sm:-mt-3" :class="toolbarVisible ? 'translate-y-0' : '-translate-y-full'">
        <div class="overflow-x-auto">
          <div class="min-w-[72rem] text-xs text-gray-800">
            <div class="flex h-12 items-stretch border-b border-gray-300">
              <div class="flex items-center border-r border-gray-300 px-1">
                <button type="button" class="h-8 w-8 border border-gray-300 bg-white text-2xl leading-none hover:bg-gray-100 disabled:text-gray-300" title="前のレコード" :disabled="units.total === 0 || recordNumber <= 1" @click="moveSingleRecord(-1)">‹</button>
                <button type="button" class="ml-1 h-8 w-8 border border-gray-500 bg-white text-2xl leading-none hover:bg-gray-100 disabled:text-gray-300" title="次のレコード" :disabled="units.total === 0 || recordNumber >= units.total" @click="moveSingleRecord(1)">›</button>
              </div>
              <div class="flex min-w-40 items-center gap-2 border-r border-gray-300 px-3">
                <span class="flex h-7 w-7 items-center justify-center rounded-full border-4 border-blue-300 bg-white"></span>
                <div>
                  <div class="flex items-center gap-1"><input v-model.number="recordNumber" type="number" min="1" :max="units.total || 1" class="h-6 w-16 border-gray-300 px-1 py-0 text-xs" @change="goToRecord(recordNumber)" /><span>/ {{ units.total }}レコード</span></div>
                  <div class="text-[10px]">{{ units.total }}件（検索結果）</div>
                </div>
              </div>
              <button type="button" class="min-w-24 border-r border-gray-300 px-3 hover:bg-gray-100" @click="resetFilters"><span class="block text-lg">▣</span>すべてを表示</button>
              <template v-if="form.view !== 'preparation'">
                <button v-if="!findMode" type="button" class="min-w-20 border-r border-gray-300 px-3 hover:bg-gray-100" @click="beginFind"><span class="block text-lg">⌕</span>検索</button>
                <template v-else>
                  <button type="button" class="min-w-24 border-r border-gray-300 px-2 hover:bg-gray-100" @click="addFindRequest"><span class="block text-lg">⌕＋</span>新規検索条件</button>
                  <button type="button" class="min-w-24 border-r border-gray-300 px-2 hover:bg-gray-100" @click="deleteFindRequest"><span class="block text-lg">⌕－</span>検索条件削除</button>
                  <button type="button" class="min-w-24 border-r border-teal-400 px-2 text-teal-900 hover:bg-teal-50" :class="convenientSearchOpen ? 'bg-teal-100' : ''" @click="toggleConvenientSearch"><span class="block text-lg">★</span>便利な検索</button>
                  <button type="button" class="min-w-20 border-r border-gray-300 px-2 font-semibold text-green-800 hover:bg-green-50" @click="executeFind"><span class="block text-lg">◉</span>検索実行</button>
                  <button type="button" class="min-w-24 border-r border-gray-300 px-2 text-gray-700 hover:bg-gray-100" @click="cancelFind"><span class="block text-lg">×</span>検索をキャンセル</button>
                </template>
              </template>
              <Link :href="route('coordinator.mginbon.import_preview')" class="min-w-20 border-r border-gray-300 px-3 py-1 text-center hover:bg-gray-100"><span class="block text-lg">＋</span>取込確認</Link>
              <Link :href="route('coordinator.mginbon.aggregations.index', { year: project.year })" class="min-w-20 border-r border-gray-300 px-3 py-1 text-center hover:bg-gray-100"><span class="block text-lg">Σ</span>集計</Link>
              <Link :href="route('coordinator.mginbon.annual_import.create')" class="min-w-20 border-r border-gray-300 px-3 py-1 text-center hover:bg-gray-100"><span class="block text-lg">⇧</span>年度取込</Link>
              <Link :href="route('coordinator.mginbon.value_masters.index', { year: project.year })" class="min-w-20 border-r border-gray-300 px-3 py-1 text-center hover:bg-gray-100"><span class="block text-lg">≡</span>値一覧</Link>
              <button v-if="project.project_job_id && form.view !== 'preparation'" type="button" class="min-w-24 border-r border-indigo-300 px-2 hover:bg-indigo-50" :class="filenameBulkOpen ? 'bg-indigo-100 text-indigo-900' : ''" @click="filenameBulkOpen = !filenameBulkOpen"><span class="block text-lg">≫</span>一括登録</button>
              <a v-if="form.view === 'list'" :href="route('coordinator.mginbon.export.csv', { year: form.year, search: form.search, media: form.media, subject: form.subject, status: form.status, find: form.find })" class="min-w-20 border-r border-gray-300 px-3 py-1 text-center hover:bg-gray-100"><span class="block text-lg">⇩</span>LIST CSV</a>
              <button v-if="!project.project_job_id" type="button" class="min-w-20 border-r border-gray-300 px-3 hover:bg-gray-100" @click="createProjectLink"><span class="block text-lg">■</span>案件作成</button>
              <Link :href="route('coordinator.dashboard')" class="min-w-24 border-r border-gray-300 px-3 py-1 text-center hover:bg-gray-100"><span class="block text-lg">↗</span>通常サイト</Link>
              <div class="ml-auto px-3 text-[11px] text-gray-600">{{ findMode ? '検索条件 ' + (activeFindIndex + 1) + ' / ' + findRequests.length : (units.from || 0) + '–' + (units.to || 0) + ' / ' + units.total + 'レコード' }}</div>
            </div>
            <div class="flex h-8 items-center gap-3 border-b border-gray-400 bg-gray-50 px-1">
              <button type="button" class="h-full border-x px-4 font-semibold" :class="form.view === 'preparation' ? 'border-green-700 bg-green-700 text-white' : 'border-gray-300 bg-white hover:bg-gray-100'" @click="setView('preparation')">年度準備</button>
              <button type="button" :disabled="!preparation.media_imported" class="h-full border-r px-4 font-semibold disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-400" :class="form.view !== 'preparation' ? 'border-green-700 bg-green-700 text-white' : 'border-gray-300 bg-white hover:bg-gray-100'" @click="setView('list')">制作進行</button>
              <template v-if="findMode">
                <span class="flex items-center gap-1">一致するレコード:
                  <button type="button" class="border border-gray-500 px-3 py-0.5" :class="!findRequests[activeFindIndex]?.omit ? 'bg-gray-700 text-white' : 'bg-white'" @click="setFindRequestOmit(false)">含める(D)</button>
                  <button type="button" class="border border-gray-500 px-3 py-0.5" :class="findRequests[activeFindIndex]?.omit ? 'bg-gray-700 text-white' : 'bg-white'" @click="setFindRequestOmit(true)">除外(O)</button>
                </span>
                <label class="flex items-center gap-1">挿入:
                  <select v-model="selectedFindOperator" class="h-6 w-80 border-gray-500 py-0 pl-2 pr-7 text-xs" @change="insertFindOperator">
                    <option value="">演算子(P)</option>
                    <option v-for="(operator, index) in findOperators" :key="index" :disabled="operator.separator" :value="operator.value || ''">{{ operator.separator ? '────────────' : operator.label }}</option>
                  </select>
                </label>
              </template>
              <label v-if="form.view !== 'preparation' && !findMode" class="flex items-center gap-1">レイアウト:<select v-model="form.view" :disabled="!project.project_job_id || !preparation.media_imported" class="h-6 w-52 border-gray-400 py-0 pl-2 pr-7 text-xs" @change="search"><option value="list">LIST</option><option value="intake">入稿チェック</option><option value="text_proof">文字校正</option><option value="composition_outsource">組版外注</option><option value="composition_initial_order">組版外注_初校 御中</option><option value="school_outing_problem">出稿表</option><option value="order_form">作図＆文字発注フォーム</option><option value="text_order">C&amp;C文字発注</option><option value="drawing_order">大連若葉作図発注書</option><option value="scan_order">原本スキャン発注書</option></select></label>
              <span v-if="form.view === 'list'" class="flex items-center gap-1">表示方法の切り替え:
                <button type="button" title="1レコード表示" aria-label="1レコード表示" class="border border-gray-400 px-2 py-0.5" :class="displayMode === 'single' ? 'bg-gray-600 text-white' : 'bg-white hover:bg-gray-100'" @click="setDisplayMode('single')">▤</button>
                <button type="button" title="全レコード表示" aria-label="全レコード表示" class="border border-gray-400 px-2 py-0.5" :class="displayMode === 'list' ? 'bg-gray-600 text-white' : 'bg-white hover:bg-gray-100'" @click="setDisplayMode('list')">▦</button>
                <button type="button" title="表形式" aria-label="表形式" class="border border-gray-400 px-2 py-0.5" :class="displayMode === 'table' ? 'bg-gray-600 text-white' : 'bg-white hover:bg-gray-100'" @click="setDisplayMode('table')">▦</button>
              </span>
              <span v-if="form.view === 'list' && displayMode === 'single'" class="flex items-center gap-1">
                <button type="button" title="前のレコード" class="border border-gray-400 bg-white px-2 disabled:text-gray-300" :disabled="singleRecordIndex === 0" @click="moveSingleRecord(-1)">‹</button>
                <span>{{ listRecords.length ? singleRecordIndex + 1 : 0 }} / {{ listRecords.length }}レコード</span>
                <button type="button" title="次のレコード" class="border border-gray-400 bg-white px-2 disabled:text-gray-300" :disabled="singleRecordIndex >= listRecords.length - 1" @click="moveSingleRecord(1)">›</button>
              </span>
              <span class="ml-auto font-semibold">{{ displayedRange }}（全{{ summary.total }}媒体）</span>
            </div>
            <form class="flex h-8 items-center gap-2 bg-[#aeb2b5] px-1" @submit.prevent="search">
              <select v-model.number="form.year" class="h-6 w-28 border-gray-400 py-0 pl-2 pr-7 text-xs" @change="search"><option v-for="p in projects" :key="p.id" :value="p.year">{{ p.year }}年</option></select>
              <select v-model="form.media" class="h-6 w-36 border-gray-400 py-0 pl-2 pr-7 text-xs" @change="search"><option value="">媒体: すべて</option><option v-for="m in uniqueMediaOptions" :key="m" :value="m">{{ m }}</option></select>
              <select v-model="form.subject" class="h-6 w-32 border-gray-400 py-0 pl-2 pr-7 text-xs" @change="search"><option value="">教科: すべて</option><option v-for="s in subjects" :key="s.code" :value="s.code">{{ s.name }}</option></select>
              <select v-model="form.status" class="h-6 w-32 border-gray-400 py-0 pl-2 pr-7 text-xs" @change="search"><option value="all">状態: すべて</option><option value="draft">下書き</option><option value="review_required">要確認</option></select>
              <select v-model.number="form.per_page" class="h-6 w-28 border-gray-400 py-0 pl-2 pr-7 text-xs" @change="search"><option :value="25">25レコード／頁</option><option :value="50">50レコード／頁</option><option :value="75">75レコード／頁</option><option :value="100">100レコード／頁</option></select>
              <button class="h-6 border border-gray-500 bg-white px-4 hover:bg-gray-100">絞り込む</button>
              <label class="ml-auto flex items-center gap-1 text-white">連携案件:<select v-model="projectLinkForm.project_job_id" :disabled="projectLinkLocked" class="h-6 w-72 border-gray-400 py-0 pl-2 pr-7 text-xs text-gray-800"><option v-if="!project.project_job_id" value="">未接続</option><option v-for="job in projectJobs" :key="job.id" :value="job.id">{{ job.jobcode ? `${job.jobcode} / ` : '' }}{{ job.title }}</option></select></label>
              <button type="button" :disabled="projectLinkLocked" class="h-6 border border-gray-600 bg-gray-700 px-3 text-white disabled:opacity-40" @click="saveProjectLink">保存</button>
            </form>
            <div v-if="reportViews.includes(form.view)" id="mginbon-report-controls" class="flex min-h-10 items-center border-t border-gray-400 bg-gray-100"></div>
          </div>
        </div>
      </section>
      <FilenameBulkMenu v-if="filenameBulkOpen" :project="project" @close="filenameBulkOpen = false" @completed="router.reload({ preserveScroll: true })" />
      <ConvenientSearchMenu v-if="findMode && convenientSearchOpen" :preview-count="previewCount" :previewing="previewing" :project-id="project.id" :current-criteria="currentFindCriteria" :display-mode="displayMode" :simulation-active="simulationActive" @apply="applyConvenientSearch" @restore="restoreConvenientSearch" @simulate="simulateFind" @retry="retrySimulation" @execute="executeFind" @close="convenientSearchOpen = false" />
      <div v-if="findMode && simulationActive" class="no-print flex items-center gap-3 border border-blue-400 bg-blue-50 px-3 py-2 text-xs text-blue-900"><strong>検索シミュレーション結果：{{ units.total }}件</strong><span>便利な検索窓を開いたまま結果を確認できます。</span><button type="button" class="ml-auto border border-amber-600 bg-white px-3 py-1 font-semibold text-amber-900" @click="retrySimulation">条件をやり直す</button></div>
      <div v-if="!findMode && searchConditionChips.length" class="no-print flex flex-wrap items-center gap-2 border border-teal-300 bg-teal-50 px-3 py-2 text-xs">
        <strong class="text-teal-900">現在の検索:</strong>
        <button v-for="chip in searchConditionChips" :key="chip.index" type="button" class="rounded-full border px-3 py-1" :class="chip.omit ? 'border-red-400 bg-red-50 text-red-800' : 'border-teal-500 bg-white text-teal-900'" title="クリックしてこの条件を解除" @click="removeSearchChip(chip.index)">{{ chip.omit ? '除外: ' : '' }}{{ chip.label }} ×</button>
        <button type="button" class="ml-auto text-teal-800 underline" @click="resetFilters">条件をすべて解除</button>
      </div>
      <LedgerFindLayout v-if="findMode && !simulationActive" v-model="findRequests" :active-index="activeFindIndex" :columns="ledgerColumns" :actor-options="actorOptions" @update:activeIndex="activeFindIndex = $event" />
      <section v-if="showLedgerResults && form.view === 'preparation'" class="mx-auto w-full max-w-5xl rounded bg-white p-5 shadow sm:p-8">
        <div class="flex flex-col gap-3 border-b border-gray-200 pb-5 sm:flex-row sm:items-center sm:justify-between">
          <div><p class="text-sm font-medium text-green-700">{{ project.year }}年 中学入試問題集</p><h1 class="mt-1 text-2xl font-bold text-gray-900">年度準備</h1><p class="mt-2 text-sm text-gray-600">制作進行を開始する前に、対象校・連携案件・媒体データを確認します。</p></div>
          <span class="self-start rounded-full px-3 py-1 text-sm font-semibold" :class="preparation.media_imported ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800'">{{ preparation.media_imported ? '制作進行を開始できます' : '媒体データの取込待ち' }}</span>
        </div>
        <ol class="mt-6 grid gap-4 md:grid-cols-3">
          <li class="rounded border p-4" :class="preparation.school_imported ? 'border-green-300 bg-green-50' : 'border-amber-300 bg-amber-50'"><div class="flex items-center justify-between"><span class="font-semibold">1. 対象校リスト</span><span class="rounded px-2 py-0.5 text-xs font-semibold" :class="preparation.school_imported ? 'bg-green-200 text-green-900' : 'bg-amber-200 text-amber-900'">{{ preparation.school_imported ? '取込済み' : '未取込' }}</span></div><p class="mt-3 text-sm text-gray-700">{{ preparation.school_count }}校を登録済み</p></li>
          <li class="rounded border p-4" :class="preparation.project_linked ? 'border-green-300 bg-green-50' : 'border-amber-300 bg-amber-50'"><div class="flex items-center justify-between"><span class="font-semibold">2. ProjectJob接続</span><span class="rounded px-2 py-0.5 text-xs font-semibold" :class="preparation.project_linked ? 'bg-green-200 text-green-900' : 'bg-amber-200 text-amber-900'">{{ preparation.project_linked ? '接続済み' : '未接続' }}</span></div><p class="mt-3 text-sm text-gray-700">{{ preparation.project_linked ? '担当・JobBox連動の準備ができています。' : '上部の連携案件を選び、保存してください。' }}</p></li>
          <li class="rounded border p-4" :class="preparation.media_imported ? 'border-green-300 bg-green-50' : 'border-amber-300 bg-amber-50'"><div class="flex items-center justify-between"><span class="font-semibold">3. 媒体データ</span><span class="rounded px-2 py-0.5 text-xs font-semibold" :class="preparation.media_imported ? 'bg-green-200 text-green-900' : 'bg-amber-200 text-amber-900'">{{ preparation.media_imported ? '取込済み' : '未取込' }}</span></div><p class="mt-3 text-sm text-gray-700">{{ preparation.media_count }}媒体を登録済み</p></li>
        </ol>
        <div class="mt-6 flex flex-wrap items-center gap-3 rounded border border-blue-200 bg-blue-50 p-4">
          <div class="min-w-0 flex-1"><p class="font-semibold text-blue-950">{{ preparation.media_imported ? '媒体データを確認する' : '次に媒体データを取り込みます' }}</p><p class="mt-1 text-sm text-blue-800">既存の取込確認画面で、原文と正規化候補を確認してください。</p></div>
          <Link v-if="preparation.import_batch_id" :href="route('coordinator.mginbon.import_preview', { batch: preparation.import_batch_id })" class="rounded bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700">取込確認へ進む</Link>
          <span v-else class="rounded bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-500">取込バッチ作成待ち</span>
          <button v-if="preparation.media_imported" type="button" class="rounded border border-green-700 bg-white px-4 py-2 text-sm font-semibold text-green-800 hover:bg-green-50" @click="setView('list')">制作進行LISTを開く</button>
        </div>
      </section>
      <div v-if="showLedgerResults && !project.project_job_id" class="rounded border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">この年度はSBWork案件へ未接続です。年度準備のみ可能です。上部から既存案件へ接続するか、銀本専用案件を作成してください。</div>
      <div v-else-if="showLedgerResults && projectLinkLocked" class="rounded border border-blue-200 bg-blue-50 p-3 text-xs text-blue-900">工程データを利用済みのため、連携案件は変更・解除できません。</div>
      <section v-if="showLedgerResults && form.view === 'intake'" class="grid min-w-[72rem] grid-cols-[17rem_1fr_17rem] items-center border-b border-[#54aaa8] bg-[#fbffd7] px-2 py-1">
        <h1 class="text-center text-lg font-semibold">{{ project.year }}年　中学入試問題集</h1>
        <div class="flex items-center justify-center gap-2"><span class="inline-block rounded-md border-2 border-green-600 bg-[#ccffcc] px-7 py-1 text-base font-bold">入稿チェック</span></div>
        <div class="text-right text-[11px] leading-tight text-gray-700"><div>{{ displayedRange }}（全{{ summary.total }}媒体）</div><div class="mt-0.5 font-semibold text-blue-800">検索対象合計　社外 scan {{ summary.intake_totals?.['scan:subcontracted'] ?? 0 }}・作図 {{ summary.intake_totals?.['drawing:subcontracted'] ?? 0 }}</div><div class="font-semibold text-blue-800">社内 scan {{ summary.intake_totals?.['scan:internal'] ?? 0 }}・作図 {{ summary.intake_totals?.['drawing:internal'] ?? 0 }}</div></div>
      </section>
      <LedgerTableLayout v-if="showLedgerResults && form.view === 'list' && displayMode === 'table' && units.data.length" :units="visibleUnits" :columns="ledgerColumns" :actor-options="actorOptions" :project-linked="Boolean(project.project_job_id)" @saved="cellSaved" />
      <section v-else-if="showLedgerResults && form.view !== 'preparation' && form.view !== 'text_proof' && units.data.length && !reportViews.includes(form.view)" class="bg-white">
        <article v-for="unit in visibleUnits" :key="`${unit.id}-${unit.items[0]?.id ?? 'empty'}`" class="border-b border-gray-700 first:border-t" :class="unit.items.length === 0 && form.view === 'list' ? 'mx-auto w-[54rem] max-w-full' : ''">
          <template v-if="form.view === 'intake'">
            <IntakeCheckBlock v-for="item in unit.items" :key="item.id" :item="item" :unit="unit" :actor-options="actorOptions" :project-linked="Boolean(project.project_job_id)" />
          </template>
          <section v-else-if="unit.items.length === 0" class="mx-auto grid min-h-8 w-[54rem] max-w-full grid-cols-[5.5rem_6.5rem_6rem_18rem_10rem_8rem] items-stretch text-xs">
            <div class="flex items-center justify-center border-r border-gray-300 bg-gray-500 text-white">{{ unit.mikuni_code }}</div>
            <div class="flex items-center justify-center border-r border-gray-300">{{ unit.n_code }}</div>
            <div class="flex items-center justify-center border-r border-gray-300 bg-[#ccffff]">{{ unit.alpha_group ? unit.alpha_group : "通常校" }}</div>
            <div class="flex items-center border-r border-gray-300 bg-[#f6fad3] px-3 text-sm font-semibold">{{ unit.display_name }}</div>
            <div class="flex items-center justify-center border-r border-gray-300">{{ unit.exam_session }}</div>
            <div class="flex items-center justify-center bg-amber-50 font-semibold text-amber-800">媒体未取込</div>
          </section>
          <template v-else>
            <section v-for="item in unit.items" :key="item.id" class="grid border-b-2 border-gray-700 last:border-b-0 lg:grid-cols-[17rem_minmax(0,1fr)]">
              <div class="bg-white p-1 text-xs leading-snug">
                <div class="grid h-6 grid-cols-4 items-center bg-[#ccffff] text-center text-[11px]">
                  <span>みくにコード</span><span>日能研コード</span><span>分類</span><span>媒体</span>
                </div>
                <div class="grid h-6 grid-cols-4 gap-0.5 text-center text-xs">
                  <span class="border border-gray-400 bg-gray-500 py-1 text-white" :class="matchCellClass(item, 'mikuni_code')">{{ unit.mikuni_code || '' }}</span>
                  <span class="border border-gray-400 bg-white py-1" :class="matchCellClass(item, 'n_code')">{{ unit.n_code || '' }}</span>
                  <span class="border border-gray-400 bg-white py-1" :class="matchCellClass(item, 'category')">{{ unit.n_category || unit.school_category || '' }}</span>
                  <span class="border border-gray-400 bg-white py-1" :class="matchCellClass(item, 'media')">{{ item.media_name }}</span>
                </div>
                <div class="flex items-center gap-2 bg-[#f6fad3] px-1 py-1.5 text-sm font-semibold" :class="matchCellClass(item, 'school_name')"><span>{{ unit.display_name }}</span><span v-if="unit.exam_session" class="ml-auto text-[10px] font-normal">{{ unit.exam_session }}</span><span v-if="unit.alpha_group" class="rounded bg-cyan-100 px-1 text-[10px]">α {{ unit.alpha_group }}</span></div>
                <div class="grid h-7 grid-cols-[1fr_4.25rem_2.75rem] items-center gap-0.5">
                  <span class="bg-[#ccffcc] px-1 py-1">{{ item.subjects.map((subject) => subject.name).join('・') }}</span>
                  <span class="bg-[#ccffcc] px-1 py-1 text-center" :class="matchCellClass(item, 'publication_status')">{{ item.publication_status || '○' }}</span>
                  <Link :href="route('coordinator.mginbon.items.show', { item: item.id })" class="bg-gray-200 px-1 py-1 text-center hover:bg-gray-300">編集</Link>
                </div>
                <div class="relative mt-1 min-h-[7.25rem] bg-white px-0.5 py-1 text-gray-700" :class="matchCellClass(item, 'note')">
                  <div class="absolute inset-x-0 top-7 border-t border-dotted border-gray-400"></div>
                  <div class="absolute inset-x-0 top-14 border-t border-dotted border-gray-400"></div>
                  <div class="absolute inset-x-0 top-[5.25rem] border-t border-dotted border-gray-400"></div>
                  <span class="relative whitespace-pre-wrap">{{ item.note || '' }}</span>
                </div>
                <details v-if="item.match_descriptors?.length" class="mt-1 border border-emerald-500 bg-emerald-50 px-2 py-1 text-[10px] text-emerald-950"><summary class="cursor-pointer font-semibold">一致理由 {{ item.match_descriptors.length }}件</summary><ul class="mt-1 list-disc pl-4"><li v-for="(descriptor, descriptorIndex) in item.match_descriptors" :key="descriptorIndex">{{ descriptorReason(descriptor) }}</li></ul></details>
              </div>
              <div class="min-w-0 overflow-x-auto bg-white">
                <table v-for="band in ledgerBands" :key="band.key" class="w-[79.25rem] min-w-[79.25rem] table-fixed border-collapse border-b border-gray-600 text-[11px] leading-tight last:border-b-0">
                  <colgroup><col class="w-11" /><col v-for="column in band.columns" :key="`width-${column.code}`" class="w-[4.5rem]" /></colgroup>
                  <thead><tr class="h-6 bg-[#ccffff] text-center">
                    <th class="w-11 px-1 py-1"></th>
                    <th v-for="column in band.columns" :key="column.code" class="px-1 py-1 font-medium" :class="columnBorderClass(column.tone)">{{ column.label }}</th>
                  </tr></thead>
                  <tbody><tr v-for="subject in item.subjects" :key="subject.id" class="border-b border-white text-center last:border-b-0">
                    <th class="h-6 w-11 bg-white px-1 py-1 text-right text-xs font-semibold">{{ subject.name }}</th>
                    <td v-for="column in band.columns" :key="column.code" class="h-6 p-0 font-medium" :class="[columnClass(column.tone), columnBorderClass(column.tone), matchCellClass(item, column.type === 'stage' ? 'actor' : 'milestone', column.code, column.type === 'shared' ? '' : subject.code)]">
                      <LedgerCellEditor :item="item" :subject="subject" :column="column" :task="task(subject, column.code)" :actor-options="actorOptions" :project-linked="Boolean(project.project_job_id)" @saved="cellSaved(item, subject, $event)" />
                    </td>
                  </tr></tbody>
                </table>
              </div>
            </section>
          </template>
        </article>
      </section>
      <TextProofLayout v-else-if="showLedgerResults && units.data.length && form.view === 'text_proof'" :project="project" :units="sortedMediaUnits" :actor-options="actorOptions" :project-linked="Boolean(project.project_job_id)" />
      <CompositionOutsourceLayout v-else-if="showLedgerResults && units.data.length && form.view === 'composition_outsource'" :project="project" :units="sortedMediaUnits" />
      <CompositionInitialOrderLayout v-else-if="showLedgerResults && units.data.length && form.view === 'composition_initial_order'" :project="project" :units="sortedMediaUnits" />
      <SchoolOutingReportLayout v-else-if="showLedgerResults && units.data.length && form.view === 'school_outing_problem'" :project="project" :units="sortedMediaUnits" :actor-options="actorOptions" :project-linked="Boolean(project.project_job_id)" />
      <OrderReportLayout v-else-if="showLedgerResults && units.data.length && reportViews.includes(form.view)" :type="form.view" :project="project" :units="sortedMediaUnits" :summary-totals="summary.intake_totals" />
      <div v-else-if="showLedgerResults && form.view !== 'preparation'" class="rounded bg-white p-8 text-center text-sm text-gray-500 shadow">条件に一致する学校はありません。</div>
      <nav v-if="showLedgerResults && form.view !== 'preparation' && units.links.length > 3" class="no-print flex flex-wrap justify-center gap-1"><template v-for="link in units.links" :key="`${link.label}-${link.url}`"><Link v-if="link.url" :href="link.url" preserve-scroll class="rounded border px-3 py-1.5 text-sm" :class="link.active ? 'border-green-600 bg-green-600 text-white' : 'bg-white'">{{ paginationLabel(link.label) }}</Link><span v-else class="rounded border bg-gray-50 px-3 py-1.5 text-sm text-gray-400">{{ paginationLabel(link.label) }}</span></template></nav>
    </div>
  </div>
</template>
