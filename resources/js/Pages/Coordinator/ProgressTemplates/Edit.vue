<template>
  <AppLayout :title="isEdit ? 'テンプレート編集' : 'テンプレート新規作成'">
    <template #header>
      <div class="flex items-center gap-3">
        <Link
          :href="route('coordinator.progress_templates.index')"
          class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 whitespace-nowrap hover:bg-gray-300"
        >← テンプレート一覧に戻る</Link>
        <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">
          {{ isEdit ? 'テンプレート編集' : 'テンプレート新規作成' }}
        </h2>
      </div>
    </template>

    <div class="rounded bg-white px-4 py-6 sm:p-6 shadow">

      <!-- ── メタ情報 ─────────────────────────────── -->
      <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
          <label class="block text-sm font-medium text-gray-700">テンプレート名 <span class="text-red-500">*</span></label>
          <input
            v-model="form.name"
            type="text"
            class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none"
          />
          <p v-if="errors.name" class="mt-1 text-xs text-red-500">{{ errors.name }}</p>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">説明</label>
          <input
            v-model="form.description"
            type="text"
            class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none"
          />
        </div>
        <div class="flex items-center gap-2">
          <input id="is_shared" v-model="form.is_shared" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-indigo-600" />
          <label for="is_shared" class="text-sm text-gray-700">全Coordinatorに共有する</label>
        </div>
      </div>

      <!-- ── 2カラム エディタ ───────────────────────── -->
      <div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-2">

        <!-- ▼ 台割行テンプレート（縦軸） -->
        <div class="rounded border border-gray-200 p-4">
          <h3 class="mb-1 font-semibold text-gray-700">台割行テンプレート</h3>
          <p class="mb-3 text-xs text-gray-400">
            進行管理表の<span class="font-medium text-gray-600">縦軸（行）</span>となる台割の項目を設定します。
            グループで見出し行を作り、その下に子行を追加できます。
          </p>
          <div class="rounded border border-gray-200 p-2 space-y-2">
              <div
                v-for="(row, idx) in form.row_config"
                :key="row.key"
              >
              <!-- 親行ヘッダー -->
              <div
                class="flex items-center gap-2 rounded border px-3 py-2"
                :class="row.children?.length > 0
                  ? 'border-indigo-200 bg-indigo-50'
                  : 'border-gray-200 bg-white'"
              >
                <span class="cursor-grab text-gray-400">⠿</span>
                <input
                  v-model="row.label"
                  type="text"
                  class="flex-1 rounded border border-gray-300 px-2 py-1 text-sm focus:border-indigo-400 focus:outline-none"
                  :placeholder="row.children?.length > 0 ? 'グループ見出し（例：本文）' : '例：表紙、P.1-4、奥付'"
                />
                <!-- グループバッジ -->
                <span
                  v-if="row.children?.length > 0"
                  class="rounded bg-indigo-100 px-1.5 py-0.5 text-xs text-indigo-600"
                >見出し</span>
                <!-- グループ化ボタン（リーフのみ） -->
                <button
                  v-if="!row.children || row.children.length === 0"
                  type="button"
                  class="rounded bg-indigo-50 px-2 py-0.5 text-xs text-indigo-600 hover:bg-indigo-100"
                  title="グループ化して子行を追加"
                  @click="makeRowGroup(idx)"
                >グループ化</button>
                <!-- 子行追加ボタン（グループのみ） -->
                <button
                  v-if="row.children?.length > 0"
                  type="button"
                  class="rounded bg-blue-50 px-2 py-0.5 text-xs text-blue-600 hover:bg-blue-100"
                  @click="addRowChild(idx)"
                >＋子行</button>
                <!-- 上へ -->
                <button
                  v-if="idx > 0"
                  type="button"
                  class="text-gray-400 hover:text-gray-600"
                  @click="moveRowUp(idx)"
                >↑</button>
                <!-- 下へ -->
                <button
                  v-if="idx < form.row_config.length - 1"
                  type="button"
                  class="text-gray-400 hover:text-gray-600"
                  @click="moveRowDown(idx)"
                >↓</button>
                <!-- 削除 -->
                <button
                  type="button"
                  class="rounded px-1.5 py-0.5 text-xs text-red-400 hover:bg-red-50 hover:text-red-600"
                  @click="removeRow(idx)"
                >✕</button>
              </div>

              <!-- 子行リスト -->
              <div
                v-if="row.children?.length > 0"
                class="ml-6 mt-1 space-y-1 border-l border-indigo-200 pb-1 pl-2"
              >
                <div
                  v-for="(child, cidx) in row.children"
                  :key="child.key"
                  class="flex items-center gap-2 rounded border border-gray-200 bg-white px-3 py-1.5"
                >
                  <span class="cursor-grab text-gray-300">⠿</span>
                  <input
                    v-model="child.label"
                    type="text"
                    class="flex-1 rounded border border-gray-300 px-2 py-1 text-sm focus:border-indigo-400 focus:outline-none"
                    placeholder="例：P.1-4"
                  />
                  <button
                    v-if="cidx > 0"
                    type="button"
                    class="text-gray-400 hover:text-gray-600"
                    @click="moveRowChildUp(idx, cidx)"
                  >↑</button>
                  <button
                    v-if="cidx < row.children.length - 1"
                    type="button"
                    class="text-gray-400 hover:text-gray-600"
                    @click="moveRowChildDown(idx, cidx)"
                  >↓</button>
                  <button
                    type="button"
                    class="rounded px-1.5 py-0.5 text-xs text-red-400 hover:bg-red-50 hover:text-red-600"
                    @click="removeRowChild(idx, cidx)"
                  >✕</button>
                </div>
              </div>
            </div>
          </div>
          <div class="mt-2">
            <button
              type="button"
              class="flex-1 flex items-center justify-center rounded border border-dashed border-gray-300 py-1.5 text-sm text-gray-500 hover:border-indigo-400 hover:text-indigo-500"
              @click="addRow"
            >
              ＋ 行を追加
            </button>
          </div>
          <p v-if="errors.row_config" class="mt-1 text-xs text-red-500">{{ errors.row_config }}</p>
        </div>

        <!-- ▼ 列・ステージ構成（横軸） -->
        <div class="rounded border border-gray-200 p-4">
          <h3 class="mb-1 font-semibold text-gray-700">列・ステージ構成</h3>
          <p class="mb-3 text-xs text-gray-400">
            進行管理表の<span class="font-medium text-gray-600">横軸（列）</span>となるステージと各セル項目を設定します。
            グループ内に担当者・日付・チェックなどのセルを追加できます。
          </p>
          <ColumnTreeEditor
            :nodes="form.column_config"
            :stages="props.stages"
            :sizes="props.sizes"
            :assignments="props.assignments"
            :work-item-types="props.workItemTypes"
            @change="(updated) => { form.column_config = updated.slice(); }"
          />
          <p v-if="errors.column_config" class="mt-1 text-xs text-red-500">{{ errors.column_config }}</p>
        </div>

      </div>

      <!-- ── レイアウトプレビュー ───────────────────── -->
      <div class="mb-6">
        <h3 class="mb-2 font-semibold text-gray-700">レイアウトプレビュー</h3>
        <div class="overflow-x-auto rounded border border-gray-200">
          <table class="min-w-full border-collapse text-xs">
            <thead class="bg-gray-50">
              <!-- 多段ヘッダー -->
              <tr v-for="(headerRow, depth) in previewHeaderRows" :key="depth">
                <!-- 台割ラベル列（最初の行だけ rowspan で結合） -->
                <th
                  v-if="depth === 0"
                  :rowspan="previewMaxDepth"
                  class="border border-gray-200 px-3 py-2 text-left text-gray-500 whitespace-nowrap align-middle"
                >
                  台割 ＼ 段階
                </th>
                <th
                  v-for="cell in headerRow"
                  :key="cell.key"
                  :colspan="cell.colspan"
                  :rowspan="cell.rowspan"
                  class="border border-gray-200 px-3 py-2 text-center font-medium text-gray-700 whitespace-nowrap align-middle"
                >
                  {{ cell.label || '（未入力）' }}
                </th>
              </tr>
              <!-- 列が0件のとき -->
              <tr v-if="previewHeaderRows.length === 0">
                <th class="border border-gray-200 px-3 py-2 text-left text-gray-500 whitespace-nowrap">台割 ＼ 段階</th>
                <th class="border border-gray-200 px-3 py-2 text-gray-400 italic">← 列・ステージを追加してください</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="previewRows.length === 0">
                <td
                  class="border border-gray-200 px-3 py-2 text-gray-400 italic"
                  :colspan="previewLeafCols.length + 1"
                >
                  ← 台割行を追加してください
                </td>
              </tr>
              <template v-for="row in previewRows" :key="row.key">
                <!-- グループ見出し行 -->
                <tr v-if="row.isGroup" class="bg-indigo-50">
                  <td
                    class="border border-gray-200 px-3 py-1.5 text-xs font-semibold text-indigo-700"
                    :colspan="previewLeafCols.length + 1"
                  >
                    {{ row.label || '（未入力）' }}
                  </td>
                </tr>
                <!-- データ行 -->
                <tr v-else class="hover:bg-gray-50">
                  <td
                    class="border border-gray-200 bg-gray-50 px-3 py-2 font-medium text-gray-700 whitespace-nowrap"
                    :class="row.isChild ? 'pl-6' : ''"
                  >
                    {{ row.label || '（未入力）' }}
                  </td>
                  <td
                    v-for="leaf in previewLeafCols"
                    :key="leaf.key"
                    class="border border-gray-200 px-3 py-2 text-center"
                  >
                    <span class="text-gray-400">{{ PREVIEW_TYPE_LABELS[leaf.type] ?? leaf.type }}</span>
                  </td>
                  <td v-if="previewLeafCols.length === 0" class="border border-gray-200 px-3 py-2"></td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
        <p class="mt-1 text-xs text-gray-400">
          ※ 進行管理表のレイアウトイメージです。各ステージ内のセル詳細は「列・ステージ構成」の設定に従います。
        </p>
      </div>

      <!-- ── 保存ボタン ──────────────────────────────── -->
      <div class="flex items-center gap-3">
        <button
          type="button"
          class="rounded bg-indigo-600 px-5 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
          :disabled="processing"
          @click="submit"
        >
          {{ isEdit ? '更新' : '作成' }}
        </button>
        <Link
          :href="route('coordinator.progress_templates.index')"
          class="rounded bg-gray-100 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-200"
        >
          テンプレート一覧に戻る
        </Link>
      </div>

    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import ColumnTreeEditor from '@/Components/ColumnTreeEditor.vue';

const props = defineProps({
  template: Object, // null のとき新規作成
  stages: { type: Array, default: () => [] },
  sizes:  { type: Array, default: () => [] },
  assignments: { type: Array, default: () => [] },
  workItemTypes: { type: Array, default: () => [] },
});

const isEdit = computed(() => !!props.template);
const page = usePage();
const errors = computed(() => page.props.errors ?? {});
const processing = ref(false);

function genKey() {
  return crypto.randomUUID ? crypto.randomUUID() : Math.random().toString(36).slice(2);
}

const form = ref({
  name:          props.template?.name ?? '',
  description:   props.template?.description ?? '',
  column_config: JSON.parse(JSON.stringify(props.template?.column_config ?? [])),
  row_config:    JSON.parse(JSON.stringify(props.template?.row_config ?? [])),
  is_shared:     props.template?.is_shared ?? false,
});

// ── 台割行の操作 ───────────────────────────────

function addRow() {
  // 最後のグループに子行を追加、グループがない場合はトップレベルの行を追加
  const rows = form.value.row_config;
  const lastGroup = rows.slice().reverse().find(row => row.children && row.children.length > 0);
  if (lastGroup) {
    if (!lastGroup.children) lastGroup.children = [];
    lastGroup.children.push({ key: genKey(), label: '' });
  } else {
    // グループがない場合はトップレベルの行を追加
    rows.push({ key: genKey(), label: '' });
  }
}

function removeRow(idx) {
  form.value.row_config.splice(idx, 1);
}

function moveRowUp(idx) {
  if (idx < 1) return;
  const arr = form.value.row_config;
  [arr[idx - 1], arr[idx]] = [arr[idx], arr[idx - 1]];
}

function moveRowDown(idx) {
  const arr = form.value.row_config;
  if (idx >= arr.length - 1) return;
  [arr[idx], arr[idx + 1]] = [arr[idx + 1], arr[idx]];
}

/** リーフ行をグループに変換し、元のラベルを持つ子行を1件追加 */
function makeRowGroup(idx) {
  const row = form.value.row_config[idx];
  const childLabel = row.label;
  row.label = '';
  row.children = [{ key: genKey(), label: childLabel }];
}

function addRowChild(idx) {
  const row = form.value.row_config[idx];
  if (!row.children) row.children = [];
  row.children.push({ key: genKey(), label: '' });
}

function removeRowChild(idx, cidx) {
  const row = form.value.row_config[idx];
  row.children.splice(cidx, 1);
  // 子がなくなったらグループ解除
  if (row.children.length === 0) {
    delete row.children;
  }
}

function moveRowChildUp(idx, cidx) {
  if (cidx < 1) return;
  const children = form.value.row_config[idx].children;
  [children[cidx - 1], children[cidx]] = [children[cidx], children[cidx - 1]];
}

function moveRowChildDown(idx, cidx) {
  const children = form.value.row_config[idx].children;
  if (cidx >= children.length - 1) return;
  [children[cidx], children[cidx + 1]] = [children[cidx + 1], children[cidx]];
}

// ── プレビュー用 ────────────────────────────────

const PREVIEW_TYPE_LABELS = {
  text: '自由入力',
  date: '日付',
  checkbox: 'チェック',
  user: '担当者',
  worktime: '作業時間',
  stage: 'ステージ',
  size: 'サイズ',
  assignment: '作業分担',
  workItemType: '作業種別',
  joblink: '登録',
  worker: '担当＋ジョブ',
  schedlink: '予定連携',
};

function collectPreviewLeaves(nodes) {
  const leaves = [];
  for (const node of nodes) {
    if (!node.children || node.children.length === 0) {
      leaves.push(node);
    } else {
      leaves.push(...collectPreviewLeaves(node.children));
    }
  }
  return leaves;
}

function countPreviewLeaves(node) {
  if (!node.children || node.children.length === 0) return 1;
  return node.children.reduce((sum, c) => sum + countPreviewLeaves(c), 0);
}

function calcPreviewMaxDepth(nodes, depth = 1) {
  let max = depth;
  for (const node of nodes) {
    if (node.children?.length) {
      max = Math.max(max, calcPreviewMaxDepth(node.children, depth + 1));
    }
  }
  return max;
}

const previewMaxDepth = computed(() => {
  if (form.value.column_config.length === 0) return 1;
  return calcPreviewMaxDepth(form.value.column_config);
});

const previewLeafCols = computed(() => collectPreviewLeaves(form.value.column_config));

const previewHeaderRows = computed(() => {
  if (form.value.column_config.length === 0) return [];
  const depth = previewMaxDepth.value;
  const result = Array.from({ length: depth }, () => []);

  function walk(nodes, currentDepth) {
    for (const node of nodes) {
      const isLeaf = !node.children || node.children.length === 0;
      const colspan = isLeaf ? 1 : countPreviewLeaves(node);
      const rowspan = isLeaf ? depth - currentDepth + 1 : 1;
      result[currentDepth - 1].push({ key: node.key, label: node.label, colspan, rowspan });
      if (!isLeaf) walk(node.children, currentDepth + 1);
    }
  }

  walk(form.value.column_config, 1);
  return result;
});

const previewRows = computed(() => {
  const result = [];
  for (const row of form.value.row_config) {
    if (row.children?.length > 0) {
      result.push({ key: row.key, label: row.label, isGroup: true });
      for (const child of row.children) {
        result.push({ key: child.key, label: child.label, isChild: true });
      }
    } else {
      result.push({ key: row.key, label: row.label });
    }
  }
  return result;
});

// ── 保存 ───────────────────────────────────────

function submit() {
  processing.value = true;
  if (isEdit.value) {
    router.put(
      route('coordinator.progress_templates.update', { template: props.template.id }),
      form.value,
      { onFinish: () => { processing.value = false; } }
    );
  } else {
    router.post(
      route('coordinator.progress_templates.store'),
      form.value,
      { onFinish: () => { processing.value = false; } }
    );
  }
}
</script>
