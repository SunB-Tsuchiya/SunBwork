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
        @job-link-open="openJobLinkModal"
        @job-link-detail="openJobLinkDetail"
      />

      <!-- user型セルに「自分を担当者として登録」する操作説明 -->
      <p class="mt-3 text-xs text-gray-400">
        担当者欄に自分の名前を設定するとMyJobとして登録されます。
      </p>

    </div>

    <!-- ── ジョブリンク登録モーダル ──────────────────── -->
    <div
      v-if="jobLinkModal.open"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
      @click.self="jobLinkModal.open = false"
    >
      <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
        <h3 class="mb-4 text-lg font-semibold text-gray-800">MyJobとして登録</h3>
        <div class="space-y-3">
          <div>
            <label class="block text-xs font-medium text-gray-600">ジョブタイトル</label>
            <input
              v-model="jobLinkForm.title"
              type="text"
              class="mt-1 w-full rounded border border-gray-300 px-3 py-1.5 text-sm focus:border-indigo-400 focus:outline-none"
            />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600">期限</label>
            <input
              v-model="jobLinkForm.desiredEndDate"
              type="date"
              class="mt-1 w-full rounded border border-gray-300 px-3 py-1.5 text-sm focus:border-indigo-400 focus:outline-none"
            />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600">メモ</label>
            <textarea
              v-model="jobLinkForm.detail"
              rows="2"
              class="mt-1 w-full rounded border border-gray-300 px-3 py-1.5 text-sm focus:border-indigo-400 focus:outline-none"
            />
          </div>
        </div>
        <div class="mt-5 flex justify-end gap-3">
          <button
            type="button"
            class="rounded border border-gray-300 px-4 py-1.5 text-sm text-gray-600 hover:bg-gray-50"
            @click="jobLinkModal.open = false"
          >キャンセル</button>
          <button
            type="button"
            class="rounded bg-blue-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-700"
            :disabled="!jobLinkForm.title"
            @click="submitJobLink"
          >MyJobに登録</button>
        </div>
      </div>
    </div>

    <!-- ── ジョブリンク詳細モーダル ──────────────────── -->
    <div
      v-if="jobLinkDetailModal.open"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
      @click.self="jobLinkDetailModal.open = false"
    >
      <div class="w-full max-w-sm rounded-lg bg-white p-6 shadow-xl">
        <h3 class="mb-3 text-lg font-semibold text-gray-800">登録済みMyJob</h3>
        <dl class="space-y-2 text-sm">
          <div><dt class="text-xs font-medium text-gray-500">タイトル</dt><dd class="text-gray-800">{{ jobLinkDetailModal.title }}</dd></div>
          <div v-if="jobLinkDetailModal.endDate"><dt class="text-xs font-medium text-gray-500">期限</dt><dd class="text-gray-800">{{ jobLinkDetailModal.endDate }}</dd></div>
          <div><dt class="text-xs font-medium text-gray-500">状態</dt><dd><span :class="jobLinkDetailModal.completed ? 'text-yellow-700' : 'text-blue-700'">{{ jobLinkDetailModal.completed ? '完了' : '未完了' }}</span></dd></div>
        </dl>
        <div class="mt-5 flex justify-end">
          <button
            type="button"
            class="rounded border border-gray-300 px-4 py-1.5 text-sm text-gray-600 hover:bg-gray-50"
            @click="jobLinkDetailModal.open = false"
          >閉じる</button>
        </div>
      </div>
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

// ── ジョブリンク ──────────────────────────────────────
const jobLinkModal = ref({ open: false, rowId: null, colKey: null });
const jobLinkForm = ref({ title: '', detail: '', desiredEndDate: '' });
const jobLinkDetailModal = ref({ open: false, title: '', endDate: '', completed: false });

function findBreadcrumb(nodes, key, path = []) {
  for (const node of nodes) {
    const currentPath = [...path, node.label];
    if (node.key === key) return currentPath;
    if (node.children?.length) {
      const found = findBreadcrumb(node.children, key, currentPath);
      if (found) return found;
    }
  }
  return null;
}

function buildJobTitle(rowId, colKey) {
  const row = localRows.value.find((r) => r.id === rowId);
  const breadcrumb = findBreadcrumb(localColumnConfig.value, colKey);
  const parentPath = breadcrumb ? breadcrumb.slice(0, -1) : [];
  const parts = [
    props.projectJob.client_name ?? props.projectJob.title,
    row?.label,
    ...parentPath,
  ].filter(Boolean);
  return parts.join('ー');
}

function openJobLinkModal({ rowId, colKey }) {
  const title = buildJobTitle(rowId, colKey);
  router.visit(route('events.create_job', { title }));
}

function submitJobLink() {
  router.post(
    route('progress_sheets.cells.link_job_user', { sheet: props.sheet.id }),
    {
      row_id: jobLinkModal.value.rowId,
      col_key: jobLinkModal.value.colKey,
      title: jobLinkForm.value.title,
      detail: jobLinkForm.value.detail || null,
      desired_end_date: jobLinkForm.value.desiredEndDate || null,
    },
    {
      preserveScroll: true,
      onSuccess: (page) => {
        jobLinkModal.value.open = false;
        if (page.props.cells) {
          localCells.value = page.props.cells.map((c) => ({ ...c }));
        }
      },
    }
  );
}

function openJobLinkDetail({ assignmentTitle, endDate, completed }) {
  jobLinkDetailModal.value = {
    open: true,
    title: assignmentTitle ?? '(タイトルなし)',
    endDate: endDate ?? null,
    completed: !!completed,
  };
}

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
