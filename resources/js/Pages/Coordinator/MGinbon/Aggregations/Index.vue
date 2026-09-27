<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
  project: { type: Object, required: true },
  projects: { type: Array, default: () => [] },
  dimension: { type: String, required: true },
  rows: { type: Array, default: () => [] },
});
const form = reactive({ year: props.project.year, dimension: props.dimension });
const dimensions = [
  ['school', '学校'], ['media', '媒体'], ['subject', '教科'],
  ['stage', '工程'], ['actor', '担当者'], ['subcontractor', '外注先'],
];
const showPoints = computed(() => ['school', 'media', 'subject', 'subcontractor'].includes(form.dimension));
const sortKey = ref(['school', 'media'].includes(props.dimension) ? 'm_code' : (props.dimension === 'stage' ? 'stage_number' : 'label'));
const sortDirection = ref('asc');
const numericKeys = new Set(['stage_number', 'task_count', 'completed_count', 'in_progress_count', 'scan_points', 'drawing_points', 'subcontracted_points']);
watch(() => props.dimension, (dimension) => {
  sortKey.value = ['school', 'media'].includes(dimension) ? 'm_code' : (dimension === 'stage' ? 'stage_number' : 'label');
  sortDirection.value = 'asc';
});
const sortedRows = computed(() => [...props.rows].sort((left, right) => {
  const a = left[sortKey.value] ?? '';
  const b = right[sortKey.value] ?? '';
  const result = numericKeys.has(sortKey.value)
    ? Number(a) - Number(b)
    : String(a).localeCompare(String(b), 'ja', { numeric: true, sensitivity: 'base' });
  return sortDirection.value === 'asc' ? result : -result;
}));
function toggleSort(key) {
  if (sortKey.value === key) sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
  else { sortKey.value = key; sortDirection.value = 'asc'; }
}
function sortMark(key) {
  return sortKey.value === key ? (sortDirection.value === 'asc' ? ' ▲' : ' ▼') : '';
}
const totals = computed(() => props.rows.reduce((sum, row) => {
  ['task_count', 'completed_count', 'in_progress_count', 'scan_points', 'drawing_points', 'subcontracted_points']
    .forEach((key) => { sum[key] = (sum[key] || 0) + Number(row[key] || 0); });
  return sum;
}, {}));
const search = () => router.get(route('coordinator.mginbon.aggregations.index'), form, { preserveState: true, replace: true });
</script>

<template>
  <Head title="銀本集計" />
  <div class="min-h-screen bg-gray-100 p-3">
    <div class="mx-auto max-w-7xl space-y-3">
      <header class="flex flex-wrap items-center gap-3 rounded border border-gray-300 bg-white px-4 py-3 shadow-sm">
        <Link :href="route('coordinator.mginbon.index', { year: project.year })" class="rounded border bg-gray-100 px-3 py-1.5 text-sm hover:bg-gray-200">← 銀本進行</Link>
        <h1 class="text-xl font-semibold text-gray-800">MGinbon 集計</h1>
        <select v-model.number="form.year" class="ml-auto rounded border-gray-300 text-sm" @change="search">
          <option v-for="row in projects" :key="row.id" :value="row.year">{{ row.year }}年</option>
        </select>
      </header>

      <section class="rounded bg-white p-4 shadow">
        <div class="mb-4 flex flex-wrap gap-2">
          <button v-for="[value, label] in dimensions" :key="value" type="button"
            class="rounded border px-4 py-2 text-sm font-medium"
            :class="form.dimension === value ? 'border-green-700 bg-green-700 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'"
            @click="form.dimension = value; search()">{{ label }}別</button>
        </div>

        <div class="mb-3 grid grid-cols-2 gap-2 text-sm sm:grid-cols-4">
          <div class="rounded bg-gray-100 p-3"><div class="text-xs text-gray-500">工程件数</div><b class="text-lg">{{ totals.task_count || 0 }}</b></div>
          <div class="rounded bg-green-50 p-3"><div class="text-xs text-green-700">完了</div><b class="text-lg">{{ totals.completed_count || 0 }}</b></div>
          <div class="rounded bg-orange-50 p-3"><div class="text-xs text-orange-700">作業中</div><b class="text-lg">{{ totals.in_progress_count || 0 }}</b></div>
          <div v-if="showPoints" class="rounded bg-blue-50 p-3"><div class="text-xs text-blue-700">点数合計</div><b class="text-lg">{{ (totals.scan_points || 0) + (totals.drawing_points || 0) }}</b></div>
        </div>

        <div class="overflow-x-auto">
          <table class="min-w-full border-collapse text-sm">
            <thead><tr class="bg-gray-50 text-left text-xs text-gray-600">
              <th v-if="['school', 'media'].includes(form.dimension)" class="border px-3 py-2"><button type="button" @click="toggleSort('m_code')">Mコード{{ sortMark('m_code') }}</button></th>
              <th v-if="form.dimension === 'media'" class="border px-3 py-2"><button type="button" @click="toggleSort('school_name')">学校名{{ sortMark('school_name') }}</button></th>
              <th v-if="form.dimension === 'stage'" class="border px-3 py-2 text-right"><button type="button" @click="toggleSort('stage_number')">No.{{ sortMark('stage_number') }}</button></th>
              <th class="border px-3 py-2"><button type="button" @click="toggleSort(form.dimension === 'media' ? 'media_name' : 'label')">{{ form.dimension === 'media' ? '媒体' : dimensions.find(([value]) => value === form.dimension)?.[1] }}{{ sortMark(form.dimension === 'media' ? 'media_name' : 'label') }}</button></th>
              <th class="border px-3 py-2 text-right"><button type="button" @click="toggleSort('task_count')">工程件数{{ sortMark('task_count') }}</button></th>
              <th class="border px-3 py-2 text-right"><button type="button" @click="toggleSort('completed_count')">完了{{ sortMark('completed_count') }}</button></th>
              <th class="border px-3 py-2 text-right"><button type="button" @click="toggleSort('in_progress_count')">作業中{{ sortMark('in_progress_count') }}</button></th>
              <th v-if="showPoints" class="border px-3 py-2 text-right"><button type="button" @click="toggleSort('scan_points')">scan点数{{ sortMark('scan_points') }}</button></th>
              <th v-if="showPoints" class="border px-3 py-2 text-right"><button type="button" @click="toggleSort('drawing_points')">作図点数{{ sortMark('drawing_points') }}</button></th>
              <th v-if="showPoints" class="border px-3 py-2 text-right"><button type="button" @click="toggleSort('subcontracted_points')">外注点数{{ sortMark('subcontracted_points') }}</button></th>
            </tr></thead>
            <tbody>
              <tr v-for="(row, index) in sortedRows" :key="row.group_id || row.label || index" class="hover:bg-green-50">
                <td v-if="['school', 'media'].includes(form.dimension)" class="border px-3 py-2 font-mono">{{ row.m_code || '―' }}</td>
                <td v-if="form.dimension === 'media'" class="border px-3 py-2 font-medium">{{ row.school_name || '未設定' }}</td>
                <td v-if="form.dimension === 'stage'" class="border px-3 py-2 text-right font-mono">{{ row.stage_number === 999 ? '―' : row.stage_number }}</td>
                <td class="border px-3 py-2 font-medium">{{ form.dimension === 'media' ? row.media_name : (row.label || '未設定') }}</td>
                <td class="border px-3 py-2 text-right">{{ row.task_count || 0 }}</td>
                <td class="border px-3 py-2 text-right text-green-700">{{ row.completed_count || 0 }}</td>
                <td class="border px-3 py-2 text-right text-orange-700">{{ row.in_progress_count || 0 }}</td>
                <td v-if="showPoints" class="border px-3 py-2 text-right">{{ row.scan_points || 0 }}</td>
                <td v-if="showPoints" class="border px-3 py-2 text-right">{{ row.drawing_points || 0 }}</td>
                <td v-if="showPoints" class="border px-3 py-2 text-right">{{ row.subcontracted_points || 0 }}</td>
              </tr>
              <tr v-if="!rows.length"><td :colspan="(showPoints ? 7 : 4) + (form.dimension === 'media' ? 2 : (['school', 'stage'].includes(form.dimension) ? 1 : 0))" class="border p-8 text-center text-gray-500">集計対象がありません。</td></tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </div>
</template>
