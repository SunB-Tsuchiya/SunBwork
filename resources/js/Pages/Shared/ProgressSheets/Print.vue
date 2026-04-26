<template>
  <div class="min-h-screen bg-white">
    <!-- 印刷非表示: 操作パネル -->
    <div class="no-print bg-gray-100 border-b border-gray-200 px-4 py-2 flex items-center gap-3">
      <span class="text-sm text-gray-600">印刷プレビュー</span>
      <button
        type="button"
        class="rounded bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700"
        @click="doPrint"
      >印刷を実行</button>
    </div>

    <!-- 印刷コンテンツ -->
    <div class="px-6 py-4">
      <!-- ヘッダー情報 -->
      <div class="mb-4 border-b border-gray-300 pb-3">
        <div class="flex flex-wrap items-baseline gap-x-3 gap-y-0.5">
          <span v-if="projectJob.client_name" class="text-sm font-medium text-gray-700">{{ projectJob.client_name }}</span>
          <span v-if="projectJob.client_name" class="text-gray-400 text-sm">/</span>
          <span class="text-base font-semibold text-gray-900">{{ projectJob.title }}</span>
          <span v-if="projectJob.size_name" class="text-xs text-gray-500">（{{ projectJob.size_name }}）</span>
          <span v-if="projectJob.page_count" class="text-xs text-gray-500">総{{ projectJob.page_count }}ページ</span>
        </div>
        <h1 class="mt-1 text-lg font-bold text-gray-800">{{ sheet.name }}</h1>
        <p class="text-xs text-gray-400 mt-0.5">印刷日: {{ printDate }}</p>
      </div>

      <!-- 進行表テーブル -->
      <div v-if="!sheet.column_config?.length" class="py-8 text-center text-gray-400">
        列が定義されていません。
      </div>
      <div v-else class="overflow-visible">
        <ProgressTable
          :rows="localRows"
          :column-config="sheet.column_config"
          :cells="localCells"
          :users="[]"
          :stages="[]"
          :sizes="[]"
          :assignments="[]"
          :work-item-types="[]"
          :project-schedules="[]"
          :can-edit="false"
          :edit-mode="false"
          :job-link-only="false"
          :auth-user-id="null"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import ProgressTable from '@/Components/ProgressTable.vue';

const props = defineProps({
  sheet: Object,
  rows: Array,
  cells: Array,
  projectJob: Object,
});

const localRows  = ref(props.rows.map((r) => ({ ...r })));
const localCells = ref(props.cells.map((c) => ({ ...c })));

const printDate = computed(() => {
  const d = new Date();
  return `${d.getFullYear()}/${String(d.getMonth() + 1).padStart(2, '0')}/${String(d.getDate()).padStart(2, '0')}`;
});

function doPrint() {
  window.print();
}

onMounted(() => {});
</script>

<style>
@media print {
  .no-print {
    display: none !important;
  }

  body {
    background: white !important;
  }

  /* テーブルを用紙幅に合わせる */
  table {
    width: 100% !important;
    font-size: 10px !important;
  }

  th, td {
    padding: 2px 4px !important;
  }

  /* 改ページ調整 */
  tr {
    page-break-inside: avoid;
  }
}
</style>
