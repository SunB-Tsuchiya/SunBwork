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

        <!-- ラベル編集：type に応じてinput / selectを切り替え（全ノード共通） -->
        <!-- 項目型（管理シート用） -->
        <template v-if="node.type === 'item'">
          <button
            v-if="props.itemEntries.length > 0"
            type="button"
            class="rounded border border-gray-300 px-1.5 py-0.5 text-xs text-gray-500 hover:bg-gray-50 whitespace-nowrap"
            :title="itemModes.get(node.key) === 'text' ? '項目リストから選択' : '手入力に切替'"
            @click="toggleItemMode(node.key)"
          >{{ itemModes.get(node.key) === 'text' ? '📋' : '✏️' }}</button>
          <select
            v-if="itemModes.get(node.key) !== 'text' && props.itemEntries.length > 0"
            v-model="node.label"
            class="flex-1 rounded border border-indigo-300 bg-indigo-50 px-2 py-1 text-sm focus:border-indigo-500 focus:outline-none"
            @change="emit('change', nodes)"
          >
            <option value="">— 項目を選択 —</option>
            <option v-for="e in props.itemEntries" :key="e.id" :value="e.name">{{ e.name }}</option>
          </select>
          <input
            v-else
            v-model="node.label"
            type="text"
            class="flex-1 rounded border border-gray-300 px-2 py-1 text-sm focus:border-indigo-400 focus:outline-none"
            placeholder="項目名"
            @input="emit('change', nodes)"
          />
        </template>
        <!-- ステージ型（左：項目入力, 右：ステージ選択） -->
        <template v-else-if="node.type === 'stage'">
          <!-- 項目入力（左） -->
          <button
            v-if="props.itemEntries.length > 0"
            type="button"
            class="rounded border border-gray-300 px-1.5 py-0.5 text-xs text-gray-500 hover:bg-gray-50 whitespace-nowrap"
            :title="stageItemModes.get(node.key) === 'text' ? '項目リストから選択' : '手入力に切替'"
            @click="toggleStageItemMode(node.key)"
          >{{ stageItemModes.get(node.key) === 'text' ? '📋' : '✏️' }}</button>
          <select
            v-if="stageItemModes.get(node.key) !== 'text' && props.itemEntries.length > 0"
            v-model="node.item_label"
            class="rounded border border-blue-300 bg-blue-50 px-2 py-1 text-sm focus:border-blue-500 focus:outline-none"
            style="max-width:130px"
            @change="emit('change', nodes)"
          >
            <option value="">— 項目選択 —</option>
            <option v-for="e in props.itemEntries" :key="e.id" :value="e.name">{{ e.name }}</option>
          </select>
          <input
            v-else
            v-model="node.item_label"
            type="text"
            class="rounded border border-blue-200 px-2 py-1 text-sm focus:border-blue-400 focus:outline-none"
            style="max-width:130px"
            placeholder="項目名（任意）"
            @input="emit('change', nodes)"
          />
          <!-- ステージ選択（右） -->
          <select
            v-model="node.label"
            class="flex-1 rounded border border-indigo-300 bg-indigo-50 px-2 py-1 text-sm focus:border-indigo-500 focus:outline-none"
            @change="emit('change', nodes)"
          >
            <option value="">— ステージを選択 —</option>
            <option v-for="s in labelSelectOptions(node)" :key="s.id" :value="s.name">{{ s.name }}</option>
          </select>
        </template>
        <!-- サイズ型 -->
        <select
          v-else-if="node.type === 'size'"
          v-model="node.label"
          class="flex-1 rounded border border-indigo-300 bg-indigo-50 px-2 py-1 text-sm focus:border-indigo-500 focus:outline-none"
          @change="emit('change', nodes)"
        >
          <option value="">— サイズを選択 —</option>
          <template v-for="grp in sizesGrouped()" :key="grp.group">
            <optgroup :label="grp.label">
              <option v-for="s in grp.items" :key="s.id" :value="s.name">{{ s.name }}</option>
            </optgroup>
          </template>
        </select>
        <!-- 作業分担型 -->
        <select
          v-else-if="node.type === 'assignment'"
          v-model="node.label"
          class="flex-1 rounded border border-indigo-300 bg-indigo-50 px-2 py-1 text-sm focus:border-indigo-500 focus:outline-none"
          @change="emit('change', nodes)"
        >
          <option value="">— 作業分担を選択 —</option>
          <option v-for="a in labelSelectOptions(node)" :key="a.id" :value="a.name">{{ a.name }}</option>
        </select>
        <!-- 作業種別型 -->
        <select
          v-else-if="node.type === 'workItemType'"
          v-model="node.label"
          class="flex-1 rounded border border-indigo-300 bg-indigo-50 px-2 py-1 text-sm focus:border-indigo-500 focus:outline-none"
          @change="emit('change', nodes)"
        >
          <option value="">— 作業種別を選択 —</option>
          <template v-for="grp in workItemTypesGrouped()" :key="grp.group">
            <optgroup :label="grp.label">
              <option v-for="t in grp.items" :key="t.id" :value="t.name">{{ t.name }}</option>
            </optgroup>
          </template>
        </select>
        <!-- それ以外（text / date / bool / checkbox / user / worktime / joblink / worker / coordinator / proof_v2 / group[legacy]） -->
        <input
          v-else
          v-model="node.label"
          type="text"
          class="flex-1 rounded border border-gray-300 px-2 py-1 text-sm focus:border-indigo-400 focus:outline-none"
          placeholder="セレクターの値"
          @input="emit('change', nodes)"
        />

        <!-- type 選択（全ノード共通・常時表示） -->
        <select
          :value="getNodeType(node)"
          class="rounded border border-gray-300 px-1 py-1 text-xs focus:border-indigo-400 focus:outline-none"
          @change="setNodeType(node, $event.target.value)"
        >
          <option value="item">項目</option>
          <option value="stage">ステージ</option>
          <option value="worker">組版担当</option>
          <option value="coordinator">進行担当</option>
          <option value="proof_v2">校正担当</option>
          <option value="schedlink">スケジュール連携</option>
          <option value="workItemType">作業種別</option>
          <option value="date">日付</option>
          <option value="bool">チェック</option>
          <option value="checkbox">チェック(旧)</option>
          <option value="text">自由入力</option>
        </select>
        <!-- グループ（子あり）インジケーター -->
        <span v-if="node.children?.length > 0" class="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-500">グループ</span>

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

        <!-- グループ複製 -->
        <button
          v-if="node.children && node.children.length > 0"
          type="button"
          class="rounded bg-orange-50 px-2 py-0.5 text-xs text-orange-600 hover:bg-orange-100"
          title="このグループを複製して下に追加"
          @click="duplicateNode(idx)"
        >
          複製
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
          :stages="stages"
          :sizes="sizes"
          :assignments="assignments"
          :work-item-types="workItemTypes"
          :item-entries="itemEntries"
          @change="(updated) => { node.children = updated.slice(); emit('change', nodes); }"
        />
      </div>
    </div>

    <!-- 列追加ボタン -->
    <div class="mt-1 flex gap-2">
      <button
        type="button"
        class="flex flex-1 items-center justify-center rounded border border-dashed border-gray-300 py-1.5 text-sm text-gray-500 hover:border-indigo-400 hover:text-indigo-500"
        @click="addLeaf"
      >
        ＋ 列を追加
      </button>
      <button
        type="button"
        class="flex flex-1 items-center justify-center rounded border border-dashed border-indigo-300 py-1.5 text-sm text-indigo-600 hover:border-indigo-500 hover:bg-indigo-50"
        title="組版担当＋校正担当のセットをグループとして追加"
        @click="addKumihanKoseiPreset"
      >
        ＋ 組版＋校正セット
      </button>
      <button
        type="button"
        class="flex flex-1 items-center justify-center rounded border border-dashed border-blue-300 py-1.5 text-sm text-blue-600 hover:border-blue-500 hover:bg-blue-50"
        title="項目＋ステージ＋組版・校正のセットを追加"
        @click="addItemStagePreset"
      >
        ＋ 項目＋ステージ
      </button>
    </div>
  </div>
</template>

<script setup>
import { reactive } from 'vue';

function genKey() {
  return crypto.randomUUID ? crypto.randomUUID() : Math.random().toString(36).slice(2);
}
const props = defineProps({
  nodes: {
    type: Array,
    required: true,
  },
  stages:        { type: Array, default: () => [] },
  sizes:         { type: Array, default: () => [] },
  assignments:   { type: Array, default: () => [] },
  workItemTypes: { type: Array, default: () => [] },
  itemEntries:   { type: Array, default: () => [] },
});

// item タイプのラベルモード管理（select: 項目リストから / text: 手入力）
const itemModes = reactive(new Map());
function toggleItemMode(key) {
  const cur = itemModes.get(key) ?? 'select';
  itemModes.set(key, cur === 'select' ? 'text' : 'select');
}

// stage タイプの item_label モード管理（select / text）
const stageItemModes = reactive(new Map());
function toggleStageItemMode(key) {
  const cur = stageItemModes.get(key) ?? 'select';
  stageItemModes.set(key, cur === 'select' ? 'text' : 'select');
}

const emit = defineEmits(['change']);

const GROUP_LABELS = { paper: '紙媒体', digital: 'デジタル', web: 'Web', other: 'その他', dtp: 'DTP・組版', proof: '校正', design: 'デザイン', common: '共通' };

const LABEL_SELECT_TYPES = ['stage', 'size', 'assignment', 'workItemType'];

function labelSelectOptions(node) {
  if (node.type === 'stage') return props.stages;
  if (node.type === 'assignment') return props.assignments;
  return [];
}

function sizesGrouped() {
  const map = new Map();
  for (const s of props.sizes) {
    const g = s.group || 'other';
    if (!map.has(g)) map.set(g, []);
    map.get(g).push(s);
  }
  return [...map.entries()].map(([group, items]) => ({
    group,
    label: GROUP_LABELS[group] ?? group,
    items,
  }));
}

function workItemTypesGrouped() {
  const map = new Map();
  for (const t of props.workItemTypes) {
    const g = t.group || 'common';
    if (!map.has(g)) map.set(g, []);
    map.get(g).push(t);
  }
  return [...map.entries()].map(([group, items]) => ({
    group,
    label: GROUP_LABELS[group] ?? group,
    items,
  }));
}

const TYPE_DEFAULT_LABELS = {
  item: '',
  date: '日付',
  bool: 'チェック',
  checkbox: 'チェック',
  user: '担当者',
  worktime: '作業時間',
  joblink: '登録',
  worker: '担当',
  coordinator: '進行',
  proof_v2: '校正',
  schedlink: '予定',
};

/** タイプが変わったとき、不一致なラベルをリセット / デフォルトラベルを自動入力 */
function onTypeChange(node) {
  if (LABEL_SELECT_TYPES.includes(node.type)) {
    node.label = '';
  } else if (TYPE_DEFAULT_LABELS[node.type] !== undefined) {
    node.label = TYPE_DEFAULT_LABELS[node.type];
  }
  emit('change', props.nodes);
}

/** 'group'（廃止予定）は 'text' として扱う */
function getNodeType(node) {
  return node.type === 'group' ? 'text' : (node.type || 'text');
}

function setNodeType(node, val) {
  node.type = val;
  onTypeChange(node);
}

function newLeaf(label = '新しい列') {
  return { key: genKey(), label, type: 'text' };
}

function addLeaf() {
  props.nodes.push(newLeaf());
  emit('change', props.nodes);
}

function addGroup(node) {
  // リーフをグループに変換して最初の子を追加（typeはそのまま保持）
  node.children = [newLeaf(node.label + '（項目）')];
  emit('change', props.nodes);
}

function addChild(node) {
  if (!node.children) node.children = [];
  node.children.push(newLeaf());
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

// ── グループ複製（全子孫を含む深いコピー・キーは新規発行）──
function cloneNode(node) {
  const cloned = { key: genKey(), label: node.label, type: node.type };
  if (node.item_label) cloned.item_label = node.item_label;
  if (node.children && node.children.length > 0) {
    cloned.children = node.children.map(cloneNode);
  }
  return cloned;
}

function duplicateNode(idx) {
  const clone = cloneNode(props.nodes[idx]);
  props.nodes.splice(idx + 1, 0, clone);
  emit('change', props.nodes);
}

function addItemStagePreset() {
  props.nodes.push({
    key: genKey(),
    label: '',
    item_label: '',
    type: 'stage',
    children: [
      { key: genKey(), label: '組版', type: 'worker' },
      { key: genKey(), label: '校正', type: 'proof_v2' },
    ],
  });
  emit('change', props.nodes);
}

function addKumihanKoseiPreset() {
  props.nodes.push({
    key: genKey(),
    label: '組版・校正',
    type: 'text',
    children: [
      { key: genKey(), label: '組版', type: 'worker' },
      { key: genKey(), label: '校正', type: 'proof_v2' },
    ],
  });
  emit('change', props.nodes);
}
</script>
