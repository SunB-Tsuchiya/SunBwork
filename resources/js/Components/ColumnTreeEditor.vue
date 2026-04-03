<template>
  <div class="space-y-1">
    <div
      v-for="(node, idx) in nodes"
      :key="node.key"
      class="rounded border border-gray-200 bg-white"
    >
      <!-- ノードヘッダー -->
      <div class="flex items-center gap-2 px-3 py-2">
        <!-- ドラッグハンドル（見た目のみ） -->
        <span class="cursor-grab text-gray-400">⠿</span>

        <!-- ラベル編集 -->
        <input
          v-model="node.label"
          type="text"
          class="flex-1 rounded border border-gray-300 px-2 py-1 text-sm focus:border-indigo-400 focus:outline-none"
          placeholder="列名"
          @input="emit('change', nodes)"
        />

        <!-- type 選択（リーフのみ） -->
        <select
          v-if="!node.children || node.children.length === 0"
          v-model="node.type"
          class="rounded border border-gray-300 px-1 py-1 text-xs focus:border-indigo-400 focus:outline-none"
          @change="emit('change', nodes)"
        >
          <option value="text">テキスト</option>
          <option value="date">日付</option>
          <option value="checkbox">チェック</option>
          <option value="user">担当者</option>
        </select>
        <span v-else class="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-500">グループ</span>

        <!-- 操作ボタン群 -->
        <button
          v-if="!node.children || node.children.length === 0"
          type="button"
          class="rounded bg-indigo-50 px-2 py-0.5 text-xs text-indigo-600 hover:bg-indigo-100"
          title="サブグループ追加"
          @click="addGroup(node)"
        >
          ＋グループ
        </button>
        <button
          v-if="node.type === 'group' || (node.children && node.children.length > 0)"
          type="button"
          class="rounded bg-blue-50 px-2 py-0.5 text-xs text-blue-600 hover:bg-blue-100"
          title="子列追加"
          @click="addChild(node)"
        >
          ＋列
        </button>

        <!-- 上へ -->
        <button
          v-if="idx > 0"
          type="button"
          class="text-gray-400 hover:text-gray-600"
          @click="moveUp(idx)"
        >
          ↑
        </button>
        <!-- 下へ -->
        <button
          v-if="idx < nodes.length - 1"
          type="button"
          class="text-gray-400 hover:text-gray-600"
          @click="moveDown(idx)"
        >
          ↓
        </button>

        <!-- 削除 -->
        <button
          type="button"
          class="rounded px-1.5 py-0.5 text-xs text-red-400 hover:bg-red-50 hover:text-red-600"
          @click="removeNode(idx)"
        >
          ✕
        </button>
      </div>

      <!-- 子ノード（再帰） -->
      <div v-if="node.children && node.children.length > 0" class="ml-6 border-l border-gray-200 pb-2 pl-2 pr-2">
        <ColumnTreeEditor
          :nodes="node.children"
          @change="(updated) => { node.children = updated; emit('change', nodes); }"
        />
      </div>
    </div>

    <!-- 列追加ボタン -->
    <button
      type="button"
      class="mt-1 flex w-full items-center justify-center rounded border border-dashed border-gray-300 py-1.5 text-sm text-gray-500 hover:border-indigo-400 hover:text-indigo-500"
      @click="addLeaf"
    >
      ＋ 列を追加
    </button>
  </div>
</template>

<script setup>
function genKey() {
  return crypto.randomUUID ? crypto.randomUUID() : Math.random().toString(36).slice(2);
}
const props = defineProps({
  nodes: {
    type: Array,
    required: true,
  },
});

const emit = defineEmits(['change']);

function newLeaf(label = '新しい列') {
  return { key: genKey(), label, type: 'text' };
}

function addLeaf() {
  props.nodes.push(newLeaf());
  emit('change', props.nodes);
}

function addGroup(node) {
  // リーフをグループに変換して最初の子を追加
  node.children = [newLeaf(node.label + '（項目）')];
  node.type = 'group';
  emit('change', props.nodes);
}

function addChild(node) {
  if (!node.children) node.children = [];
  node.children.push(newLeaf());
  node.type = 'group';
  emit('change', props.nodes);
}

function removeNode(idx) {
  props.nodes.splice(idx, 1);
  emit('change', props.nodes);
}

function moveUp(idx) {
  if (idx < 1) return;
  const tmp = props.nodes[idx - 1];
  props.nodes[idx - 1] = props.nodes[idx];
  props.nodes[idx] = tmp;
  emit('change', props.nodes);
}

function moveDown(idx) {
  if (idx >= props.nodes.length - 1) return;
  const tmp = props.nodes[idx + 1];
  props.nodes[idx + 1] = props.nodes[idx];
  props.nodes[idx] = tmp;
  emit('change', props.nodes);
}
</script>
