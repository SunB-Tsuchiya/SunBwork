<template>
  <div class="overflow-x-auto">
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
          >
            {{ cell.label }}
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

            <!-- セル群（リーフ列の順番に合わせて表示） -->
            <ProgressCell
              v-for="leaf in leafCols"
              :key="leaf.key"
              :cell="getCellData(row.id, leaf.key)"
              :col-def="leaf"
              :row-id="row.id"
              :can-edit="canEdit && !editMode"
              :users="users"
              @update="emit('cell-update', $event)"
            />
          </tr>
        </template>
      </tbody>
    </table>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import ProgressCell from '@/Components/ProgressCell.vue';

const props = defineProps({
  rows: { type: Array, default: () => [] },
  columnConfig: { type: Array, default: () => [] },
  cells: { type: Array, default: () => [] },
  users: { type: Array, default: () => [] },
  canEdit: { type: Boolean, default: false },
  editMode: { type: Boolean, default: false },
});

const emit = defineEmits(['cell-update', 'edit-row', 'delete-row']);

// ── リーフ列を深さ優先で列挙 ──
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

const leafCols = computed(() => collectLeaves(props.columnConfig));
const leafCount = computed(() => leafCols.value.length);

// ── ツリーの最大深さ ──
function calcMaxDepth(nodes, depth = 1) {
  let max = depth;
  for (const node of nodes) {
    if (node.children?.length) {
      max = Math.max(max, calcMaxDepth(node.children, depth + 1));
    }
  }
  return max;
}

const maxDepth = computed(() => calcMaxDepth(props.columnConfig));

// ── リーフ数（colspan 計算用）──
function countLeaves(node) {
  if (!node.children || node.children.length === 0) return 1;
  return node.children.reduce((sum, c) => sum + countLeaves(c), 0);
}

// ── 各深さのヘッダーセル配列を生成 ──
// ヘッダー行ごとに { key, label, colspan, rowspan } の配列
const headerRows = computed(() => {
  const depth = maxDepth.value;
  const result = Array.from({ length: depth }, () => []);

  function walk(nodes, currentDepth) {
    for (const node of nodes) {
      const isLeaf = !node.children || node.children.length === 0;
      const colspan = isLeaf ? 1 : countLeaves(node);
      const rowspan = isLeaf ? depth - currentDepth + 1 : 1;
      result[currentDepth - 1].push({
        key: node.key,
        label: node.label,
        colspan,
        rowspan,
      });
      if (!isLeaf) {
        walk(node.children, currentDepth + 1);
      }
    }
  }

  walk(props.columnConfig, 1);
  return result;
});

// ── セルデータ取得 ──
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
</script>
