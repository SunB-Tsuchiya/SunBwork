<template>
  <div class="min-h-screen bg-gray-100">
    <!-- ヘッダー -->
    <div class="bg-white shadow">
      <div class="mx-auto max-w-7xl px-4 py-3">
        <div class="flex flex-col gap-1">
          <div class="flex items-center gap-2">
            <span class="rounded bg-gray-200 px-2 py-0.5 text-xs font-medium text-gray-600">共有 / 閲覧専用</span>
            <h1 class="text-lg font-semibold text-gray-800">{{ sheet.name }}</h1>
            <button
              v-if="token"
              type="button"
              class="ml-2 rounded border border-gray-300 bg-white px-3 py-1 text-sm text-gray-600 hover:bg-gray-50"
              @click="openPrint"
            >印刷</button>
          </div>
          <div class="flex flex-wrap items-center gap-x-4 gap-y-0.5 text-sm text-gray-600">
            <span v-if="projectJob.client_name" class="font-medium text-gray-700">{{ projectJob.client_name }}</span>
            <span v-if="projectJob.client_name && projectJob.title" class="text-gray-400">/</span>
            <span class="font-medium text-indigo-700">{{ projectJob.title }}</span>
            <span v-if="projectJob.size_name" class="rounded bg-blue-50 px-2 py-0.5 text-xs text-blue-700">サイズ: {{ projectJob.size_name }}</span>
            <span v-if="projectJob.page_count" class="rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-600">総{{ projectJob.page_count }}ページ</span>
          </div>
        </div>
      </div>
    </div>

    <!-- テーブル -->
    <div class="mx-auto max-w-full px-4 py-4">
      <div v-if="!sheet.column_config?.length" class="rounded bg-white p-8 text-center text-gray-400 shadow">
        列が定義されていません。
      </div>
      <div
        v-else
        ref="tableWrapRef"
        class="overflow-auto rounded bg-white shadow"
        :style="{ height: tableHeight, minHeight: '200px' }"
      >
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

    <!-- フッター -->
    <div class="mt-8 pb-6 text-center text-xs text-gray-400">
      SunB Work — 進行管理表（読み取り専用）
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import ProgressTable from '@/Components/ProgressTable.vue';

const props = defineProps({
  sheet: Object,
  rows: Array,
  cells: Array,
  projectJob: Object,
  token: { type: String, default: null },
});

function openPrint() {
  window.open(route('shared.progress_sheets.print', { token: props.token }), '_blank');
}

const tableWrapRef = ref(null);
const tableHeight = ref('calc(100vh - 160px)');

const localRows  = ref(props.rows.map((r) => ({ ...r })));
const localCells = ref(props.cells.map((c) => ({ ...c })));

function calcTableHeight() {
  if (!tableWrapRef.value) return;
  const top = tableWrapRef.value.getBoundingClientRect().top;
  tableHeight.value = `${window.innerHeight - top - 8}px`;
}

onMounted(() => {
  calcTableHeight();
  window.addEventListener('resize', calcTableHeight);
});
onBeforeUnmount(() => window.removeEventListener('resize', calcTableHeight));
</script>
