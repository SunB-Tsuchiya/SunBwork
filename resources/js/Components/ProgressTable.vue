<template>
  <div class="overflow-x-auto">
    <!-- トグルバー -->
    <div v-if="topLevelGroups.length > 0" class="mb-2 flex flex-wrap items-center gap-2">
      <span class="text-xs text-gray-500">表示切替：</span>
      <button
        v-for="grp in topLevelGroups"
        :key="grp.key"
        type="button"
        class="rounded border px-2 py-0.5 text-xs transition-colors"
        :class="collapsedGroups.has(grp.key)
          ? 'border-gray-300 bg-white text-gray-500 hover:bg-gray-50'
          : 'border-indigo-300 bg-indigo-50 text-indigo-700 hover:bg-indigo-100'"
        @click="toggleGroup(grp.key)"
      >
        {{ collapsedGroups.has(grp.key) ? '▶' : '▼' }} {{ grp.label }}
      </button>
    </div>

    <table class="min-w-full border-collapse text-sm">
      <!-- ── 多段ヘッダー ── -->
      <thead>
        <tr v-for="(headerRow, depth) in headerRows" :key="depth" class="bg-gray-50">
          <!-- 行ラベル列（最初の thead 行にのみ rowspan で表示） -->
          <th
            v-if="depth === 0"
            :rowspan="maxDepth"
            class="border border-gray-300 bg-gray-100 px-3 py-2 text-left text-xs font-semibold text-gray-600 align-middle"
          >
            台割
          </th>
          <th
            v-for="cell in headerRow"
            :key="cell.key"
            :colspan="cell.colspan"
            :rowspan="cell.rowspan"
            class="border border-gray-300 px-2 py-1.5 text-center text-xs font-semibold text-gray-700 align-middle whitespace-nowrap"
            :class="cell.isCollapsed ? 'bg-gray-100 text-gray-400 cursor-pointer hover:bg-gray-200' : ''"
            @click="cell.isCollapsed ? toggleGroup(cell.key) : undefined"
          >
            <span v-if="cell.isCollapsed" class="flex items-center justify-center gap-1">
              <span>▶</span>
              <span>{{ cell.label }}</span>
            </span>
            <span v-else>{{ cell.label }}</span>
          </th>
        </tr>
      </thead>

      <!-- ── データ行 ── -->
      <tbody>
        <tr v-if="rows.length === 0">
          <td :colspan="leafCount + 1" class="border border-gray-200 px-3 py-4 text-center text-gray-400">
            行がありません
          </td>
        </tr>

        <template v-for="row in rows" :key="row.id">
          <tr class="hover:bg-gray-50">
            <!-- 行ラベル -->
            <td class="border border-gray-200 bg-gray-50 px-3 py-1.5 font-medium text-gray-700 whitespace-nowrap align-middle">
              <div class="flex items-center gap-2">
                <span>{{ row.label }}</span>
                <template v-if="editMode">
                  <button
                    type="button"
                    class="text-xs text-blue-500 hover:underline"
                    @click="emit('edit-row', row)"
                  >
                    編集
                  </button>
                  <button
                    type="button"
                    class="text-xs text-red-400 hover:underline"
                    @click="emit('delete-row', row)"
                  >
                    削除
                  </button>
                </template>
              </div>
            </td>

            <!-- セル群 -->
            <template v-for="leaf in leafCols" :key="leaf.key">
              <!-- サマリーセル（折りたたみ時） -->
              <td
                v-if="leaf._isSummary"
                class="border border-gray-200 bg-gray-50 px-3 py-1.5 text-center align-middle text-xs text-gray-500 cursor-pointer hover:bg-gray-100"
                @click="toggleGroup(leaf.key)"
              >
                {{ groupSummary(row.id, leaf.key) }}
              </td>
              <!-- 通常セル -->
              <ProgressCell
                v-else
                :cell="getCellData(row.id, leaf.key)"
                :col-def="leaf"
                :row-id="row.id"
                :can-edit="canEdit && !editMode"
                :job-link-only="jobLinkOnly"
                :users="users"
                :stages="stages"
                :sizes="sizes"
                :assignments="assignments"
                :work-item-types="workItemTypes"
                @update="emit('cell-update', $event)"
                @job-link-open="emit('job-link-open', $event)"
                @job-link-detail="emit('job-link-detail', $event)"
              />
            </template>
          </tr>
        </template>
      </tbody>
    </table>
  </div>
</template>

<script setup>
import { computed, ref, reactive } from 'vue';
import ProgressCell from '@/Components/ProgressCell.vue';

const props = defineProps({
  rows: { type: Array, default: () => [] },
  columnConfig: { type: Array, default: () => [] },
  cells: { type: Array, default: () => [] },
  users: { type: Array, default: () => [] },
  stages: { type: Array, default: () => [] },
  sizes: { type: Array, default: () => [] },
  assignments: { type: Array, default: () => [] },
  workItemTypes: { type: Array, default: () => [] },
  canEdit: { type: Boolean, default: false },
  editMode: { type: Boolean, default: false },
  jobLinkOnly: { type: Boolean, default: false },
});

const emit = defineEmits(['cell-update', 'edit-row', 'delete-row', 'job-link-open', 'job-link-detail']);

// ── 折りたたみ状態 ──────────────────────────────────────────
const collapsedGroups = reactive(new Set());

const topLevelGroups = computed(() =>
  props.columnConfig.filter((n) => n.children && n.children.length > 0)
);

function toggleGroup(key) {
  if (collapsedGroups.has(key)) {
    collapsedGroups.delete(key);
  } else {
    collapsedGroups.add(key);
  }
}

// ── リーフ列を列挙（折りたたみ考慮）─────────────────────────
function collectLeaves(nodes) {
  const leaves = [];
  for (const node of nodes) {
    if (!node.children || node.children.length === 0) {
      leaves.push(node);
    } else {
      leaves.push(...collectLeaves(node.children));
    }
  }
  return leaves;
}

// トップレベルのグループ内の全リーフキーを収集
function collectLeavesOfGroup(node) {
  return collectLeaves(node.children ?? []);
}

const leafCols = computed(() => {
  const result = [];
  for (const node of props.columnConfig) {
    const isGroup = node.children && node.children.length > 0;
    if (!isGroup) {
      result.push(node);
    } else if (collapsedGroups.has(node.key)) {
      // 折りたたみ中 → サマリー擬似列を1つ追加
      result.push({ key: node.key, label: node.label, type: '_summary', _isSummary: true, _groupNode: node });
    } else {
      result.push(...collectLeaves(node.children));
    }
  }
  return result;
});

const leafCount = computed(() => leafCols.value.length);

// ── ツリーの最大深さ ──────────────────────────────────────
function calcMaxDepth(nodes, depth = 1) {
  let max = depth;
  for (const node of nodes) {
    if (node.children?.length && !collapsedGroups.has(node.key)) {
      max = Math.max(max, calcMaxDepth(node.children, depth + 1));
    }
  }
  return max;
}

const maxDepth = computed(() => calcMaxDepth(props.columnConfig));

// ── リーフ数（colspan 計算用）──────────────────────────────
function countLeaves(node) {
  if (collapsedGroups.has(node.key)) return 1;
  if (!node.children || node.children.length === 0) return 1;
  return node.children.reduce((sum, c) => sum + countLeaves(c), 0);
}

// ── 各深さのヘッダーセル配列を生成 ────────────────────────
const headerRows = computed(() => {
  const depth = maxDepth.value;
  const result = Array.from({ length: depth }, () => []);

  function walk(nodes, currentDepth) {
    for (const node of nodes) {
      const isCollapsed = collapsedGroups.has(node.key);
      const isLeaf = !node.children || node.children.length === 0 || isCollapsed;
      const colspan = isLeaf ? 1 : countLeaves(node);
      const rowspan = isLeaf ? depth - currentDepth + 1 : 1;
      result[currentDepth - 1].push({
        key: node.key,
        label: node.label,
        colspan,
        rowspan,
        isCollapsed,
      });
      if (!isLeaf) {
        walk(node.children, currentDepth + 1);
      }
    }
  }

  walk(props.columnConfig, 1);
  return result;
});

// ── セルデータ取得 ────────────────────────────────────────
const cellMap = computed(() => {
  const map = {};
  for (const c of props.cells) {
    map[`${c.row_id}_${c.col_key}`] = c;
  }
  return map;
});

function getCellData(rowId, colKey) {
  return cellMap.value[`${rowId}_${colKey}`] ?? {};
}

// ── グループのサマリー文字列（折りたたみ時） ────────────────
function groupSummary(rowId, groupKey) {
  const grpNode = props.columnConfig.find((n) => n.key === groupKey);
  if (!grpNode) return '—';
  const leaves = collectLeavesOfGroup(grpNode);

  // checkboxリーフがあれば「✓N/M」形式
  const checkLeaves = leaves.filter((l) => l.type === 'checkbox');
  if (checkLeaves.length > 0) {
    const done = checkLeaves.filter((l) => cellMap.value[`${rowId}_${l.key}`]?.value_bool).length;
    return `✓ ${done}/${checkLeaves.length}`;
  }

  // それ以外は入力済みリーフ数
  const filled = leaves.filter((l) => {
    const c = cellMap.value[`${rowId}_${l.key}`];
    return c && (c.value_text || c.value_date || c.value_bool != null || c.value_user_id);
  }).length;
  return filled > 0 ? `${filled}/${leaves.length}件` : '—';
}
</script>
