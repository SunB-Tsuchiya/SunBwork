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

      <!-- ── ツールバー ──────────────────────────────── -->
      <div class="mb-4 flex flex-wrap items-center gap-3">
        <Link
          :href="route('coordinator.project_jobs.show', { projectJob: projectJob.id })"
          class="rounded border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50"
        >
          ← 案件詳細に戻る
        </Link>

        <template v-if="canEdit">
          <button
            type="button"
            class="rounded px-3 py-1.5 text-sm font-medium"
            :class="editMode ? 'bg-gray-600 text-white hover:bg-gray-700' : 'bg-indigo-600 text-white hover:bg-indigo-700'"
            @click="editMode = !editMode"
          >
            {{ editMode ? '編集モードを終了' : '編集モード' }}
          </button>

          <!-- テンプレートとして登録 -->
          <button
            v-if="!editMode"
            type="button"
            class="rounded border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50"
            @click="showRegisterModal = true"
          >
            テンプレートとして登録
          </button>

          <!-- シート削除 -->
          <button
            type="button"
            class="rounded border border-red-200 bg-white px-3 py-1.5 text-sm text-red-500 hover:bg-red-50"
            @click="confirmDelete"
          >
            シート削除
          </button>
        </template>

        <!-- 変更保存ボタン（セル編集後） -->
        <button
          v-if="pendingCells.length > 0 && !editMode"
          type="button"
          class="rounded bg-green-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-green-700"
          @click="saveCells"
        >
          変更を保存 ({{ pendingCells.length }})
        </button>
      </div>

      <!-- ── 編集モード：列ツリー + 行管理 ──────────────── -->
      <div v-if="editMode && canEdit" class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-2">

        <!-- 左：列構成エディタ -->
        <div>
          <h3 class="mb-2 font-semibold text-gray-700">列構成</h3>
          <ColumnTreeEditor
            :nodes="localColumnConfig"
            @change="onColumnChange"
          />
          <button
            type="button"
            class="mt-3 rounded bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700"
            @click="saveColumnConfig"
          >
            列構成を保存
          </button>
        </div>

        <!-- 右：行管理 -->
        <div>
          <h3 class="mb-2 font-semibold text-gray-700">行管理（台割）</h3>

          <!-- 行追加フォーム -->
          <div class="mb-3 flex gap-2">
            <input
              v-model="newRowLabel"
              type="text"
              class="flex-1 rounded border border-gray-300 px-2 py-1.5 text-sm focus:border-indigo-400 focus:outline-none"
              placeholder="例: P.1-4"
              @keydown.enter.prevent="addRow"
            />
            <button
              type="button"
              class="rounded bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700"
              @click="addRow"
            >
              追加
            </button>
          </div>

          <!-- テキストエリアで一括インポート -->
          <details class="mb-3">
            <summary class="cursor-pointer text-sm text-gray-500 hover:text-gray-700">一括インポート（改行区切り）</summary>
            <div class="mt-2 flex flex-col gap-2">
              <textarea
                v-model="importText"
                rows="5"
                class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm focus:border-indigo-400 focus:outline-none"
                placeholder="P.1-4&#10;P.5-8&#10;表紙"
              />
              <button
                type="button"
                class="self-start rounded bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700"
                @click="importRows"
              >
                インポート
              </button>
            </div>
          </details>

          <!-- 行一覧 -->
          <div class="space-y-1">
            <div
              v-for="(row, idx) in localRows"
              :key="row.id"
              class="flex items-center gap-2 rounded border border-gray-200 px-3 py-1.5"
            >
              <span class="flex-1 text-sm">{{ row.label }}</span>
              <button
                v-if="idx > 0"
                type="button"
                class="text-xs text-gray-400 hover:text-gray-600"
                @click="moveRowUp(idx)"
              >↑</button>
              <button
                v-if="idx < localRows.length - 1"
                type="button"
                class="text-xs text-gray-400 hover:text-gray-600"
                @click="moveRowDown(idx)"
              >↓</button>
              <button
                type="button"
                class="text-xs text-red-400 hover:text-red-600"
                @click="deleteRow(row)"
              >✕</button>
            </div>
            <div v-if="localRows.length === 0" class="py-2 text-center text-sm text-gray-400">行がありません</div>
          </div>

          <!-- 並び替え保存ボタン -->
          <button
            v-if="rowOrderChanged"
            type="button"
            class="mt-3 rounded bg-green-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-green-700"
            @click="saveRowOrder"
          >
            並び順を保存
          </button>
        </div>
      </div>

      <!-- ── 通常モード：進行管理表テーブル ──────────────── -->
      <div v-if="!editMode || !canEdit">
        <div v-if="localColumnConfig.length === 0" class="py-8 text-center text-gray-400">
          列が定義されていません。編集モードで列を追加してください。
        </div>
        <ProgressTable
          v-else
          :rows="localRows"
          :column-config="localColumnConfig"
          :cells="localCells"
          :users="users"
          :can-edit="canEdit"
          :edit-mode="false"
          @cell-update="onCellUpdate"
          @edit-row="onEditRow"
          @delete-row="deleteRow"
        />
      </div>

    </div>

    <!-- ── テンプレート登録モーダル ────────────────────── -->
    <div
      v-if="showRegisterModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
      @click.self="showRegisterModal = false"
    >
      <div class="w-full max-w-sm rounded-lg bg-white p-6 shadow-xl">
        <h3 class="mb-4 text-lg font-semibold text-gray-800">テンプレートとして登録</h3>
        <label class="block text-sm font-medium text-gray-700">テンプレート名</label>
        <input
          v-model="registerTemplateName"
          type="text"
          class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none"
          :placeholder="sheet.name + 'のテンプレート'"
        />
        <div class="mt-4 flex justify-end gap-3">
          <button
            type="button"
            class="rounded border border-gray-300 px-4 py-1.5 text-sm text-gray-600 hover:bg-gray-50"
            @click="showRegisterModal = false"
          >
            キャンセル
          </button>
          <button
            type="button"
            class="rounded bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700"
            @click="registerTemplate"
          >
            登録
          </button>
        </div>
      </div>
    </div>

  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import ProgressTable from '@/Components/ProgressTable.vue';
import ColumnTreeEditor from '@/Components/ColumnTreeEditor.vue';

const props = defineProps({
  sheet: Object,
  rows: Array,
  cells: Array,
  users: Array,
  projectJob: Object,
  canEdit: Boolean,
  templates: Array,
});

const editMode = ref(false);
const showRegisterModal = ref(false);
const registerTemplateName = ref('');
const newRowLabel = ref('');
const importText = ref('');

// ローカルコピー
const localColumnConfig = ref(JSON.parse(JSON.stringify(props.sheet.column_config ?? [])));
const localRows = ref(props.rows.map((r) => ({ ...r })));
const localCells = ref(props.cells.map((c) => ({ ...c })));

// セル pending（未保存の変更）
const pendingCells = ref([]);

const rowOrderChanged = computed(() => {
  return localRows.value.some((r, i) => r.order !== i);
});

// ── 列構成 ──
function onColumnChange(updated) {
  localColumnConfig.value = updated;
}

function saveColumnConfig() {
  router.put(
    route('coordinator.progress_sheets.update', { sheet: props.sheet.id }),
    { column_config: localColumnConfig.value },
    {
      preserveScroll: true,
      onSuccess: () => { editMode.value = false; },
    }
  );
}

// ── 行管理 ──
function addRow() {
  const label = newRowLabel.value.trim();
  if (!label) return;
  router.post(
    route('coordinator.progress_sheets.rows.store', { sheet: props.sheet.id }),
    { label },
    {
      preserveScroll: true,
      onSuccess: (page) => {
        localRows.value = page.props.rows?.map((r) => ({ ...r })) ?? localRows.value;
        newRowLabel.value = '';
      },
    }
  );
}

function importRows() {
  const labels = importText.value.split('\n').map((l) => l.trim()).filter(Boolean);
  if (labels.length === 0) return;
  router.post(
    route('coordinator.progress_sheets.rows.import', { sheet: props.sheet.id }),
    { labels },
    {
      preserveScroll: true,
      onSuccess: (page) => {
        localRows.value = page.props.rows?.map((r) => ({ ...r })) ?? localRows.value;
        importText.value = '';
      },
    }
  );
}

function deleteRow(row) {
  if (!confirm(`行「${row.label}」を削除しますか？セルデータも全て削除されます。`)) return;
  router.delete(
    route('coordinator.progress_sheets.rows.destroy', { sheet: props.sheet.id, row: row.id }),
    {
      preserveScroll: true,
      onSuccess: (page) => {
        localRows.value = page.props.rows?.map((r) => ({ ...r })) ?? localRows.value.filter((r) => r.id !== row.id);
      },
    }
  );
}

function moveRowUp(idx) {
  if (idx < 1) return;
  const tmp = localRows.value[idx - 1];
  localRows.value[idx - 1] = localRows.value[idx];
  localRows.value[idx] = tmp;
}

function moveRowDown(idx) {
  if (idx >= localRows.value.length - 1) return;
  const tmp = localRows.value[idx + 1];
  localRows.value[idx + 1] = localRows.value[idx];
  localRows.value[idx] = tmp;
}

function saveRowOrder() {
  const ids = localRows.value.map((r) => r.id);
  router.put(
    route('coordinator.progress_sheets.rows.reorder', { sheet: props.sheet.id }),
    { ids },
    { preserveScroll: true }
  );
}

function onEditRow(row) {
  const label = prompt('行ラベルを編集', row.label);
  if (label === null || label.trim() === '' || label === row.label) return;
  router.put(
    route('coordinator.progress_sheets.rows.update', { sheet: props.sheet.id, row: row.id }),
    { label: label.trim() },
    { preserveScroll: true }
  );
}

// ── セル更新 ──
function onCellUpdate(payload) {
  // ローカルに即時反映
  const key = `${payload.row_id}_${payload.col_key}`;
  const existing = localCells.value.find((c) => c.row_id === payload.row_id && c.col_key === payload.col_key);
  const fieldMap = { text: 'value_text', date: 'value_date', bool: 'value_bool', user: 'value_user_id' };
  const field = fieldMap[payload.value_type];
  if (existing) {
    existing[field] = payload.value;
    if (payload.value_type === 'user') {
      existing.value_user_name = props.users.find((u) => u.id === payload.value)?.name ?? null;
    }
  } else {
    const cell = { row_id: payload.row_id, col_key: payload.col_key };
    cell[field] = payload.value;
    if (payload.value_type === 'user') {
      cell.value_user_name = props.users.find((u) => u.id === payload.value)?.name ?? null;
    }
    localCells.value.push(cell);
  }

  // pending に追加（重複なら上書き）
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

// ── テンプレート登録 ──
function registerTemplate() {
  const name = registerTemplateName.value.trim() || `${props.sheet.name}のテンプレート`;
  router.post(
    route('coordinator.progress_sheets.register_template', { sheet: props.sheet.id }),
    { name },
    {
      preserveScroll: true,
      onSuccess: () => { showRegisterModal.value = false; registerTemplateName.value = ''; },
    }
  );
}

// ── シート削除 ──
function confirmDelete() {
  if (!confirm(`進行管理表「${props.sheet.name}」を削除しますか？`)) return;
  router.delete(route('coordinator.progress_sheets.destroy', { sheet: props.sheet.id }));
}
</script>
