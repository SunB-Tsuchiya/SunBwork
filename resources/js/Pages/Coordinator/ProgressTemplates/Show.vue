<template>
  <AppLayout title="テンプレート詳細">
    <template #header>
      <h2 class="text-xl font-semibold leading-tight text-gray-800">テンプレート詳細</h2>
    </template>

    <div class="space-y-6">

      <!-- メタ情報 -->
      <div class="rounded bg-white p-6 shadow">
        <div class="mb-4 flex items-start justify-between gap-3">
          <div>
            <h3 class="text-lg font-semibold text-gray-800">{{ template.name }}</h3>
            <p v-if="template.description" class="mt-1 text-sm text-gray-500">{{ template.description }}</p>
            <div class="mt-2 flex items-center gap-3 text-xs text-gray-400">
              <span>作成者: {{ template.creator_name ?? '—' }}</span>
              <span>更新日: {{ template.updated_at ?? '—' }}</span>
              <span
                v-if="template.is_shared"
                class="rounded bg-green-100 px-2 py-0.5 font-medium text-green-700"
              >共有中</span>
              <span v-else class="rounded bg-gray-100 px-2 py-0.5 text-gray-500">非共有</span>
            </div>
          </div>
          <div class="flex shrink-0 items-center gap-2">
            <Link
              v-if="canEdit"
              :href="route('coordinator.progress_templates.edit', { template: template.id })"
              class="rounded bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700"
            >
              編集
            </Link>
            <Link
              :href="route('coordinator.progress_templates.index')"
              class="rounded border border-gray-300 px-4 py-1.5 text-sm text-gray-600 hover:bg-gray-50"
            >
              一覧へ
            </Link>
          </div>
        </div>
      </div>

      <!-- 2カラム: 行・列構成 -->
      <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

        <!-- 列管理 -->
        <div class="rounded bg-white p-6 shadow">
          <h3 class="mb-3 font-semibold text-gray-700">列管理</h3>
          <div v-if="template.row_config.length === 0" class="text-sm text-gray-400 italic">
            行が設定されていません
          </div>
          <ul v-else class="space-y-1">
            <template v-for="row in template.row_config" :key="row.key">
              <!-- グループ（見出し） -->
              <li v-if="row.children?.length > 0">
                <div class="rounded bg-indigo-50 px-3 py-1.5 text-sm font-medium text-indigo-700">
                  {{ row.label || '（未入力）' }}
                </div>
                <ul class="ml-4 mt-1 space-y-1 border-l border-indigo-200 pl-3">
                  <li
                    v-for="child in row.children"
                    :key="child.key"
                    class="rounded border border-gray-100 bg-white px-3 py-1 text-sm text-gray-700"
                  >
                    {{ child.label || '（未入力）' }}
                  </li>
                </ul>
              </li>
              <!-- フラット行 -->
              <li
                v-else
                class="rounded border border-gray-100 px-3 py-1.5 text-sm text-gray-700"
              >
                {{ row.label || '（未入力）' }}
              </li>
            </template>
          </ul>
        </div>

        <!-- 行・ステージ構成 -->
        <div class="rounded bg-white p-6 shadow">
          <h3 class="mb-3 font-semibold text-gray-700">行・ステージ構成</h3>
          <div v-if="template.column_config.length === 0" class="text-sm text-gray-400 italic">
            列が設定されていません
          </div>
          <ColumnNodeList :nodes="template.column_config" />
        </div>

      </div>

      <!-- レイアウトプレビュー -->
      <div class="rounded bg-white p-6 shadow">
        <h3 class="mb-3 font-semibold text-gray-700">レイアウトプレビュー</h3>
        <div class="overflow-x-auto rounded border border-gray-200">
          <table class="min-w-full border-collapse text-xs">
            <thead class="bg-gray-50">
              <tr>
                <th class="border border-gray-200 px-3 py-2 text-left text-gray-500 whitespace-nowrap">
                  台割 ＼ 段階
                </th>
                <th
                  v-for="col in topLevelColumns"
                  :key="col.key"
                  class="border border-gray-200 px-3 py-2 text-center font-medium text-gray-700 whitespace-nowrap"
                >
                  {{ col.label || '（未入力）' }}
                </th>
                <th v-if="topLevelColumns.length === 0" class="border border-gray-200 px-3 py-2 text-gray-400 italic">
                  列なし
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="previewRows.length === 0">
                <td class="border border-gray-200 px-3 py-2 text-gray-400 italic" :colspan="topLevelColumns.length + 1">
                  行なし
                </td>
              </tr>
              <template v-for="row in previewRows" :key="row.key">
                <!-- グループ見出し行 -->
                <tr v-if="row.isGroup" class="bg-indigo-50">
                  <td
                    class="border border-gray-200 px-3 py-1.5 text-xs font-semibold text-indigo-700"
                    :colspan="topLevelColumns.length + 1"
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
                    v-for="col in topLevelColumns"
                    :key="col.key"
                    class="border border-gray-200 px-4 py-2 text-center text-gray-300"
                  >—</td>
                  <td v-if="topLevelColumns.length === 0" class="border border-gray-200 px-3 py-2"></td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </AppLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps({
  template: Object,
  canEdit:  { type: Boolean, default: false },
});

// 列ツリーの読み取り専用表示コンポーネント（インライン定義）
const ColumnNodeList = {
  name: 'ColumnNodeList',
  props: { nodes: Array, depth: { type: Number, default: 0 } },
  template: `
    <ul class="space-y-1">
      <li v-for="node in nodes" :key="node.key">
        <div
          class="flex items-center gap-2 rounded px-2 py-1 text-sm"
          :class="node.children?.length > 0
            ? 'bg-indigo-50 font-medium text-indigo-700'
            : 'border border-gray-100 text-gray-700'"
        >
          <span v-if="depth > 0" class="text-gray-300">└</span>
          {{ node.label || '（未入力）' }}
          <span class="ml-auto text-xs text-gray-400">{{ typeLabel(node.type) }}</span>
        </div>
        <div v-if="node.children?.length > 0" class="ml-4 mt-1 border-l border-indigo-100 pl-2">
          <column-node-list :nodes="node.children" :depth="depth + 1" />
        </div>
      </li>
    </ul>
  `,
  methods: {
    typeLabel(type) {
      const map = { text: '自由入力', date: '日付', checkbox: 'チェック', user: '担当者', worktime: '作業時間', stage: 'ステージ', size: 'サイズ', assignment: '作業分担', workItemType: '作業種別', joblink: '登録・詳細', group: 'グループ' };
      return map[type] ?? '';
    },
  },
};

const topLevelColumns = computed(() => props.template.column_config ?? []);

const previewRows = computed(() => {
  const result = [];
  for (const row of (props.template.row_config ?? [])) {
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
</script>
