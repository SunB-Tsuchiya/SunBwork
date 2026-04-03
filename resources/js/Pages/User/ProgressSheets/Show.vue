<template>
  <AppLayout :title="sheet.name + ' - 進行管理表'">
    <template #header>
      <div class="flex items-center gap-3">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
          進行管理表：{{ sheet.name }}
        </h2>
        <span class="text-sm text-gray-400">{{ projectJob.title }}</span>
      </div>
    </template>

    <div class="rounded bg-white p-6 shadow">

      <!-- ツールバー -->
      <div class="mb-4 flex items-center gap-3">
        <button
          type="button"
          class="rounded border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50"
          @click="$inertia.visit(route('user.myjobbox.index'))"
        >
          ← MyJobBoxに戻る
        </button>

        <!-- 未保存セルがある場合 -->
        <button
          v-if="pendingCells.length > 0"
          type="button"
          class="rounded bg-green-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-green-700"
          @click="saveCells"
        >
          変更を保存 ({{ pendingCells.length }})
        </button>
      </div>

      <!-- 進行管理表 -->
      <div v-if="localColumnConfig.length === 0" class="py-8 text-center text-gray-400">
        列が定義されていません。
      </div>
      <ProgressTable
        v-else
        :rows="localRows"
        :column-config="localColumnConfig"
        :cells="localCells"
        :users="[]"
        :can-edit="true"
        :edit-mode="false"
        @cell-update="onCellUpdate"
      />

      <!-- user型セルに「自分を担当者として登録」する操作説明 -->
      <p class="mt-3 text-xs text-gray-400">
        担当者欄に自分の名前を設定するとMyJobとして登録されます。
      </p>

    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import ProgressTable from '@/Components/ProgressTable.vue';

const props = defineProps({
  sheet: Object,
  rows: Array,
  cells: Array,
  projectJob: Object,
  authUser: Object,
});

const localColumnConfig = ref(JSON.parse(JSON.stringify(props.sheet.column_config ?? [])));
const localRows = ref(props.rows.map((r) => ({ ...r })));
const localCells = ref(props.cells.map((c) => ({ ...c })));
const pendingCells = ref([]);

// user 型セルの場合は assign/unassign API を呼ぶ
function onCellUpdate(payload) {
  if (payload.value_type === 'user') {
    const cell = localCells.value.find((c) => c.row_id === payload.row_id && c.col_key === payload.col_key);

    if (payload.value === props.authUser?.id) {
      // 自分を登録 → assign
      const cellId = cell?.id;
      if (!cellId) {
        // 既存セルなしは pending で後でまとめて保存
        updateLocalCell(payload);
        addPending(payload);
        return;
      }
      router.post(
        route('progress_sheets.cells.assign', { sheet: props.sheet.id, cell: cellId }),
        {},
        { preserveScroll: true, onSuccess: () => refreshCells() }
      );
    } else if (!payload.value && cell?.value_user_id === props.authUser?.id && cell?.id) {
      // 自分の登録を解除 → unassign
      router.delete(
        route('progress_sheets.cells.unassign', { sheet: props.sheet.id, cell: cell.id }),
        { preserveScroll: true, onSuccess: () => refreshCells() }
      );
    } else {
      // 他者の名前変更は pending でまとめて保存
      updateLocalCell(payload);
      addPending(payload);
    }
    return;
  }

  updateLocalCell(payload);
  addPending(payload);
}

function updateLocalCell(payload) {
  const fieldMap = { text: 'value_text', date: 'value_date', bool: 'value_bool', user: 'value_user_id' };
  const field = fieldMap[payload.value_type];
  const existing = localCells.value.find((c) => c.row_id === payload.row_id && c.col_key === payload.col_key);
  if (existing) {
    existing[field] = payload.value;
  } else {
    const cell = { row_id: payload.row_id, col_key: payload.col_key };
    cell[field] = payload.value;
    localCells.value.push(cell);
  }
}

function addPending(payload) {
  const idx = pendingCells.value.findIndex((c) => c.row_id === payload.row_id && c.col_key === payload.col_key);
  if (idx >= 0) {
    pendingCells.value[idx] = payload;
  } else {
    pendingCells.value.push(payload);
  }
}

function saveCells() {
  router.put(
    route('coordinator.progress_sheets.cells.update', { sheet: props.sheet.id }),
    { cells: pendingCells.value },
    {
      preserveScroll: true,
      onSuccess: () => { pendingCells.value = []; },
    }
  );
}

function refreshCells() {
  // ページ内セルを再取得（Inertia 標準 reload）
  router.reload({ only: ['cells'] });
}
</script>
