<template>
  <AppLayout :title="isEdit ? 'テンプレート編集' : 'テンプレート新規作成'">
    <template #header>
      <h2 class="text-xl font-semibold leading-tight text-gray-800">
        {{ isEdit ? 'テンプレート編集' : 'テンプレート新規作成' }}
      </h2>
    </template>

    <div class="rounded bg-white p-6 shadow">

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
            シート作成時にこの行が初期値として追加されます。
          </p>
          <div class="space-y-1">
            <div
              v-for="(row, idx) in form.row_config"
              :key="row.key"
              class="flex items-center gap-2 rounded border border-gray-200 bg-white px-3 py-2"
            >
              <span class="cursor-grab text-gray-400">⠿</span>
              <input
                v-model="row.label"
                type="text"
                class="flex-1 rounded border border-gray-300 px-2 py-1 text-sm focus:border-indigo-400 focus:outline-none"
                placeholder="例：表紙、P.1-4、奥付"
              />
              <button
                v-if="idx > 0"
                type="button"
                class="text-gray-400 hover:text-gray-600"
                @click="moveRowUp(idx)"
              >↑</button>
              <button
                v-if="idx < form.row_config.length - 1"
                type="button"
                class="text-gray-400 hover:text-gray-600"
                @click="moveRowDown(idx)"
              >↓</button>
              <button
                type="button"
                class="rounded px-1.5 py-0.5 text-xs text-red-400 hover:bg-red-50 hover:text-red-600"
                @click="removeRow(idx)"
              >✕</button>
            </div>
          </div>
          <button
            type="button"
            class="mt-2 flex w-full items-center justify-center rounded border border-dashed border-gray-300 py-1.5 text-sm text-gray-500 hover:border-indigo-400 hover:text-indigo-500"
            @click="addRow"
          >
            ＋ 行を追加
          </button>
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
            @change="(updated) => { form.column_config = updated; }"
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
              <tr>
                <th class="border border-gray-200 px-3 py-2 text-left text-gray-500 whitespace-nowrap">
                  台割 ＼ 段階
                </th>
                <th
                  v-for="stage in topLevelStages"
                  :key="stage.key"
                  class="border border-gray-200 px-3 py-2 text-center font-medium text-gray-700 whitespace-nowrap"
                >
                  {{ stage.label || '（未入力）' }}
                </th>
                <th
                  v-if="topLevelStages.length === 0"
                  class="border border-gray-200 px-3 py-2 text-gray-400 italic"
                >
                  ← 列・ステージを追加してください
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="form.row_config.length === 0">
                <td
                  class="border border-gray-200 px-3 py-2 text-gray-400 italic"
                  :colspan="topLevelStages.length + 1"
                >
                  ← 台割行を追加してください
                </td>
              </tr>
              <tr
                v-for="row in form.row_config"
                :key="row.key"
                class="hover:bg-gray-50"
              >
                <td class="border border-gray-200 bg-gray-50 px-3 py-2 font-medium text-gray-700 whitespace-nowrap">
                  {{ row.label || '（未入力）' }}
                </td>
                <td
                  v-for="stage in topLevelStages"
                  :key="stage.key"
                  class="border border-gray-200 px-4 py-2 text-center text-gray-300"
                >
                  ―
                </td>
                <td
                  v-if="topLevelStages.length === 0"
                  class="border border-gray-200 px-3 py-2"
                ></td>
              </tr>
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
          class="rounded border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50"
        >
          キャンセル
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
  form.value.row_config.push({ key: genKey(), label: '' });
}

function removeRow(idx) {
  form.value.row_config.splice(idx, 1);
}

function moveRowUp(idx) {
  if (idx < 1) return;
  const arr = form.value.row_config;
  const tmp = arr[idx - 1];
  arr[idx - 1] = arr[idx];
  arr[idx] = tmp;
}

function moveRowDown(idx) {
  const arr = form.value.row_config;
  if (idx >= arr.length - 1) return;
  const tmp = arr[idx + 1];
  arr[idx + 1] = arr[idx];
  arr[idx] = tmp;
}

// ── プレビュー用 ────────────────────────────────

const topLevelStages = computed(() => form.value.column_config);

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

