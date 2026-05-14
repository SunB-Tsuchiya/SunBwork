<script setup>
import { ref } from 'vue';
import { route } from 'ziggy-js';
import axios from 'axios';

const props = defineProps({
    progressSheets: { type: Array, default: () => [] },
    jobId:          { type: [Number, String], default: null },
});

// ── 項目リスト オートコンプリート ──────────────────────────────────────
const itemSuggestions = ref([]);
let suggestionsFetched = false;

async function fetchItemSuggestions() {
    if (!props.jobId || suggestionsFetched) return;
    suggestionsFetched = true;
    try {
        const res = await axios.get(
            route('coordinator.item_entries.suggestions', { projectJob: props.jobId }) + '?q='
        );
        itemSuggestions.value = res.data.suggestions ?? [];
    } catch (e) {
        // サイレント失敗
    }
}

// ── state: シートごとに管理 ───────────────────────────────────────────────────
const sheetStates = ref({});

function initSheetState(sheetId) {
    if (!sheetStates.value[sheetId]) {
        sheetStates.value[sheetId] = {
            mode:           'view',
            items:          [],
            rows:           [],
            columns:        [],
            allColumns:     [],
            columnConfig:   [],
            schedules:      [],
            loading:        false,
            editRows:       [],
            editColumns:    [],
            proposal:       null,
            proposalLoading: false,
            showAddModal:   false,
            addModalTab:    'row',
        };
    }
    return sheetStates.value[sheetId];
}

// ── fetch ─────────────────────────────────────────────────────────────────────
async function recalculateAll(sheetId) {
    const st = sheetStates.value[sheetId];
    if (!st) return;
    try {
        await axios.post(route('coordinator.progress_sheets.link_settings.recalculate', { sheet: sheetId }));
    } catch (e) {
        alert('再計算に失敗しました');
    }
}

async function refreshSchedules(sheetId) {
    const st = sheetStates.value[sheetId];
    if (!st) return;
    try {
        const res = await axios.get(route('coordinator.progress_sheets.link_settings.index', { sheet: sheetId }));
        st.schedules = res.data.schedules ?? [];
    } catch (e) {
        console.error('スケジュール再読込エラー', e);
    }
}

async function fetchItems(sheetId) {
    const st = initSheetState(sheetId);
    st.loading = true;
    try {
        const res = await axios.get(route('coordinator.progress_sheets.link_settings.index', { sheet: sheetId }));
        st.items        = res.data.items        ?? [];
        st.rows         = res.data.rows         ?? [];
        st.columns      = res.data.columns      ?? [];
        st.allColumns   = res.data.allColumns   ?? [];
        st.columnConfig = res.data.columnConfig ?? [];
        st.schedules    = res.data.schedules    ?? [];
    } catch (e) {
        console.error('連携設定取得エラー', e);
        st.items = [];
    } finally {
        st.mode    = st.items.length === 0 ? 'create' : 'view';
        st.loading = false;
    }
}

// ── ヘルパー ──────────────────────────────────────────────────────────────────
function rowItems(sheetId)    { return (sheetStates.value[sheetId]?.items ?? []).filter(i => i.type === 'row'); }
function columnItems(sheetId) { return (sheetStates.value[sheetId]?.items ?? []).filter(i => i.type === 'column'); }

function groupBy(items, key) {
    const map = {};
    for (const item of items) {
        const k = item[key] ?? '';
        if (!map[k]) map[k] = [];
        map[k].push(item);
    }
    return map;
}

// 行のツリー構造（親→子）
function getRowTree(sheetId) {
    const rows = sheetStates.value[sheetId]?.rows ?? [];
    const parents  = rows.filter(r => !r.parent_id);
    const children = rows.filter(r =>  r.parent_id);
    if (children.length === 0) {
        return rows.map(r => ({ ...r, isLeaf: true, children: [] }));
    }
    return parents.map(p => ({
        ...p,
        isLeaf: false,
        children: children.filter(c => c.parent_id === p.id).map(c => ({ ...c, isLeaf: true })),
    }));
}

// columnConfig から表示ノードのフラットリストを生成（深さ付き）
function flattenConfigNodes(nodes, depth = 0, parentLabel = '') {
    const result = [];
    for (const node of nodes) {
        const label = node.label ?? node.key;
        const fullParent = parentLabel ? parentLabel + ' › ' + label : label;
        const children = node.children ?? [];
        if (children.length === 0) {
            result.push({ key: node.key, label, depth, parentLabel: parentLabel || null, isLeaf: true });
        } else {
            result.push({ key: node.key, label, depth, parentLabel: null, isLeaf: false });
            result.push(...flattenConfigNodes(children, depth + 1, fullParent));
        }
    }
    return result;
}

function getScheduleName(sheetId, linkedScheduleId) {
    if (!linkedScheduleId) return null;
    return (sheetStates.value[sheetId]?.schedules ?? []).find(s => s.id === linkedScheduleId)?.name ?? null;
}

// 現在の編集リストに row_id / col_key が既に含まれているか
function isRowAdded(sheetId, rowId) {
    const st = sheetStates.value[sheetId];
    if (!st) return false;
    if (st.mode === 'edit')   return st.editRows.some(i => i.row_id === rowId);
    if (st.mode === 'create' && st.proposal) return st.proposal.rows.some(i => i.row_id === rowId);
    return false;
}
function isColAdded(sheetId, colKey) {
    const st = sheetStates.value[sheetId];
    if (!st) return false;
    if (st.mode === 'edit')   return st.editColumns.some(i => i.col_key === colKey);
    if (st.mode === 'create' && st.proposal) return st.proposal.columns.some(i => i.col_key === colKey);
    return false;
}

// モーダルから項目を追加
function addItemFromModal(sheetId, type, { name, row_id, col_key, parent_label }) {
    const st = sheetStates.value[sheetId];
    if (!st) return;

    const newItem = {
        id: null, name, type,
        row_id: row_id ?? null,
        col_key: col_key ?? null,
        parent_label: parent_label ?? null,
        calendar_linked: true,
        linked_schedule_id: null,
        order: 0,
    };

    if (st.mode === 'edit') {
        const list = type === 'row' ? st.editRows : st.editColumns;
        newItem.order = list.length;
        list.push(newItem);
    } else if (st.mode === 'create' && st.proposal) {
        const list = type === 'row' ? st.proposal.rows : st.proposal.columns;
        newItem.order = list.length;
        list.push(newItem);
    }
}

// ── 閲覧 → 編集モード ────────────────────────────────────────────────────────
function enterEdit(sheetId) {
    const st = sheetStates.value[sheetId];
    if (!st) return;
    st.editRows    = rowItems(sheetId).map(i => ({ ...i }));
    st.editColumns = columnItems(sheetId).map(i => ({ ...i }));
    st.mode = 'edit';
}

function cancelEdit(sheetId) {
    const st = sheetStates.value[sheetId];
    st.showAddModal = false;
    st.mode = st.items.length === 0 ? 'create' : 'view';
}

// ── 編集：行追加（直接入力） ───────────────────────────────────────────────────
function addEditRow(sheetId, type) {
    const st = sheetStates.value[sheetId];
    const list = type === 'row' ? st.editRows : st.editColumns;
    list.push({ id: null, name: '', type, row_id: null, col_key: null, parent_label: null, calendar_linked: true, linked_schedule_id: null, order: list.length });
}

function removeEditRow(sheetId, type, idx) {
    const st = sheetStates.value[sheetId];
    const list = type === 'row' ? st.editRows : st.editColumns;
    list.splice(idx, 1);
}

// ── 保存 ─────────────────────────────────────────────────────────────────────
async function saveEdit(sheetId) {
    const st = sheetStates.value[sheetId];
    const allItems = [
        ...st.editRows.map((r, i) => ({ ...r, type: 'row', order: i })),
        ...st.editColumns.map((c, i) => ({ ...c, type: 'column', order: i })),
    ].filter(i => i.name.trim() !== '');

    try {
        const res = await axios.post(
            route('coordinator.progress_sheets.link_settings.import', { sheet: sheetId }),
            { items: allItems },
        );
        st.items        = res.data.items ?? [];
        st.showAddModal = false;
        st.mode         = 'view';
    } catch (e) {
        alert('保存に失敗しました');
    }
}

// ── 作成モード：進行表から提案を読み込む ─────────────────────────────────────
async function loadProposal(sheetId) {
    const st = sheetStates.value[sheetId];
    st.proposalLoading = true;
    try {
        const res = await axios.get(route('coordinator.progress_sheets.link_settings.propose', { sheet: sheetId }));
        st.proposal = {
            rows:    res.data.rows    ?? [],
            columns: res.data.columns ?? [],
        };
    } catch (e) {
        alert('読み込みに失敗しました');
    } finally {
        st.proposalLoading = false;
    }
}

function removeProposalRow(sheetId, type, idx) {
    const st = sheetStates.value[sheetId];
    if (type === 'row') st.proposal.rows.splice(idx, 1);
    else                st.proposal.columns.splice(idx, 1);
}

async function confirmProposal(sheetId) {
    const st = sheetStates.value[sheetId];
    const items = [
        ...st.proposal.rows.map((r, i) => ({ ...r, order: i })),
        ...st.proposal.columns.map((c, i) => ({ ...c, order: i })),
    ];
    try {
        const res = await axios.post(
            route('coordinator.progress_sheets.link_settings.import', { sheet: sheetId }),
            { items },
        );
        st.items        = res.data.items ?? [];
        st.proposal     = null;
        st.showAddModal = false;
        st.mode         = 'view';
    } catch (e) {
        alert('作成に失敗しました');
    }
}

// ── ライフサイクル ────────────────────────────────────────────────────────────
import { onMounted } from 'vue';
onMounted(() => {
    for (const sheet of props.progressSheets) {
        fetchItems(sheet.id);
    }
});
</script>

<template>
    <div class="space-y-6">
        <div v-if="progressSheets.length === 0" class="rounded border border-dashed border-gray-300 py-8 text-center text-sm text-gray-400">
            進行管理表がまだ登録されていません。
        </div>

        <div v-for="sheet in progressSheets" :key="sheet.id" class="rounded border border-gray-200 bg-white">
            <!-- セクションヘッダー -->
            <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50 px-4 py-3">
                <h3 class="font-semibold text-gray-800">{{ sheet.name }}－連携設定</h3>
                <div v-if="sheetStates[sheet.id]" class="flex gap-2">
                    <template v-if="sheetStates[sheet.id].mode === 'view'">
                        <button type="button" class="rounded border border-gray-300 px-3 py-1 text-sm text-gray-500 hover:bg-gray-50" @click="recalculateAll(sheet.id)" title="連携スケジュールの進捗を現在の完了セルから再計算します">↺ 全再計算</button>
                        <button type="button" class="rounded border border-indigo-300 px-3 py-1 text-sm text-indigo-600 hover:bg-indigo-50" @click="enterEdit(sheet.id)">編集</button>
                    </template>
                    <template v-else-if="sheetStates[sheet.id].mode === 'edit'">
                        <button type="button" class="rounded border border-gray-300 px-3 py-1 text-sm text-gray-500 hover:bg-gray-50" @click="refreshSchedules(sheet.id)" title="スケジュール一覧を再読み込みします">↻ スケジュール再読込</button>
                        <button type="button" class="rounded bg-indigo-600 px-3 py-1 text-sm font-medium text-white hover:bg-indigo-700" @click="saveEdit(sheet.id)">保存</button>
                        <button type="button" class="rounded border border-gray-300 px-3 py-1 text-sm text-gray-600 hover:bg-gray-50" @click="cancelEdit(sheet.id)">キャンセル</button>
                    </template>
                </div>
            </div>

            <!-- ローディング -->
            <div v-if="!sheetStates[sheet.id] || sheetStates[sheet.id].loading" class="py-6 text-center text-sm text-gray-400">読み込み中...</div>

            <!-- 作成モード -->
            <template v-else-if="sheetStates[sheet.id].mode === 'create'">
                <!-- 提案未読み込み -->
                <div v-if="!sheetStates[sheet.id].proposal" class="px-4 py-6 text-center">
                    <p class="mb-3 text-sm text-gray-500">連携設定がまだ登録されていません。</p>
                    <button type="button"
                        :disabled="sheetStates[sheet.id].proposalLoading"
                        class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                        @click="loadProposal(sheet.id)">
                        {{ sheetStates[sheet.id].proposalLoading ? '読み込み中...' : '進行表から読み込む' }}
                    </button>
                </div>

                <!-- 提案レビュー -->
                <div v-else class="p-4">
                    <p class="mb-3 text-sm text-gray-600">不要な項目を削除するか、「＋ 追加」で追加してから「この内容で作成」を押してください。</p>
                    <div class="grid grid-cols-2 gap-4">
                        <!-- 縦行提案 -->
                        <div>
                            <div class="mb-2 flex items-center justify-between">
                                <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">縦行</span>
                                <button type="button" class="text-xs text-indigo-600 hover:underline"
                                    @click="sheetStates[sheet.id].showAddModal = true; sheetStates[sheet.id].addModalTab = 'row'">
                                    ＋ 追加
                                </button>
                            </div>
                            <div v-if="sheetStates[sheet.id].proposal.rows.length === 0" class="text-sm text-gray-400">（なし）</div>
                            <div v-for="(item, idx) in sheetStates[sheet.id].proposal.rows" :key="idx"
                                class="mb-1 flex items-center justify-between rounded border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm">
                                <span>
                                    <span v-if="item.parent_label" class="mr-1 text-xs text-gray-400">{{ item.parent_label }} ›</span>
                                    {{ item.name }}
                                </span>
                                <button type="button" class="ml-2 text-red-400 hover:text-red-600" @click="removeProposalRow(sheet.id, 'row', idx)">×</button>
                            </div>
                        </div>
                        <!-- 横列提案 -->
                        <div>
                            <div class="mb-2 flex items-center justify-between">
                                <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">横列</span>
                                <button type="button" class="text-xs text-indigo-600 hover:underline"
                                    @click="sheetStates[sheet.id].showAddModal = true; sheetStates[sheet.id].addModalTab = 'column'">
                                    ＋ 追加
                                </button>
                            </div>
                            <div v-if="sheetStates[sheet.id].proposal.columns.length === 0" class="text-sm text-gray-400">（なし）</div>
                            <div v-for="(item, idx) in sheetStates[sheet.id].proposal.columns" :key="idx"
                                class="mb-1 flex items-center justify-between rounded border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm">
                                <span>
                                    <span v-if="item.parent_label" class="mr-1 text-xs text-gray-400">{{ item.parent_label }} ›</span>
                                    {{ item.name }}
                                </span>
                                <button type="button" class="ml-2 text-red-400 hover:text-red-600" @click="removeProposalRow(sheet.id, 'column', idx)">×</button>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 flex justify-end gap-2">
                        <button type="button" class="rounded border border-gray-300 px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50" @click="sheetStates[sheet.id].proposal = null">やり直す</button>
                        <button type="button" class="rounded bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700" @click="confirmProposal(sheet.id)">この内容で作成</button>
                    </div>
                </div>
            </template>

            <!-- 閲覧モード -->
            <template v-else-if="sheetStates[sheet.id].mode === 'view'">
                <div class="grid grid-cols-2 divide-x divide-gray-200 p-4">
                    <div class="pr-4">
                        <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">縦行</div>
                        <div v-if="rowItems(sheet.id).length === 0" class="text-sm text-gray-400">（なし）</div>
                        <div v-for="(items, group) in groupBy(rowItems(sheet.id), 'parent_label')" :key="group" class="mb-3">
                            <div v-if="group" class="mb-1 text-xs font-medium text-gray-500">{{ group }}</div>
                            <div v-for="item in items" :key="item.id" class="mb-1 flex items-center gap-2 text-sm text-gray-800">
                                <span :class="group ? 'ml-3' : ''">{{ item.name }}</span>
                                <span v-if="item.linked_schedule_id" class="rounded bg-green-100 px-1.5 py-0.5 text-xs font-medium text-green-700">{{ getScheduleName(sheet.id, item.linked_schedule_id) ?? '連携中' }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="pl-4">
                        <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">横列</div>
                        <div v-if="columnItems(sheet.id).length === 0" class="text-sm text-gray-400">（なし）</div>
                        <div v-for="(items, group) in groupBy(columnItems(sheet.id), 'parent_label')" :key="group" class="mb-3">
                            <div v-if="group" class="mb-1 text-xs font-medium text-gray-500">{{ group }}</div>
                            <div v-for="item in items" :key="item.id" class="mb-1 flex items-center gap-2 text-sm text-gray-800">
                                <span :class="group ? 'ml-3' : ''">{{ item.name }}</span>
                                <span v-if="item.linked_schedule_id" class="rounded bg-green-100 px-1.5 py-0.5 text-xs font-medium text-green-700">{{ getScheduleName(sheet.id, item.linked_schedule_id) ?? '連携中' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <!-- 編集モード -->
            <template v-else-if="sheetStates[sheet.id].mode === 'edit'">
                <div class="grid grid-cols-2 divide-x divide-gray-200 p-4">
                    <!-- 縦行 編集 -->
                    <div class="pr-4">
                        <div class="mb-2 flex items-center justify-between">
                            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">縦行</span>
                            <button type="button" class="text-xs text-indigo-600 hover:underline"
                                @click="sheetStates[sheet.id].showAddModal = true; sheetStates[sheet.id].addModalTab = 'row'">
                                ＋ 進行表から追加
                            </button>
                        </div>
                        <div v-for="(item, idx) in sheetStates[sheet.id].editRows" :key="idx" class="mb-2 rounded border border-gray-200 bg-gray-50 p-2">
                            <div class="mb-1.5 flex gap-2">
                                <input v-model="item.name" type="text" placeholder="名前"
                                    list="item-name-suggestions"
                                    class="flex-1 rounded border border-gray-300 px-2 py-1 text-sm focus:border-indigo-400 focus:outline-none"
                                    @focus="fetchItemSuggestions" />
                                <button type="button" class="px-1 text-sm text-red-400 hover:text-red-600" @click="removeEditRow(sheet.id, 'row', idx)">×</button>
                            </div>
                            <div class="mb-1.5 flex gap-2">
                                <div class="flex-1">
                                    <label class="mb-0.5 block text-xs text-gray-500">進行表の行</label>
                                    <select v-model="item.row_id" class="w-full rounded border border-gray-300 px-2 py-1 text-sm focus:border-indigo-400 focus:outline-none">
                                        <option :value="null">—</option>
                                        <option v-for="row in sheetStates[sheet.id].rows" :key="row.id" :value="row.id">
                                            {{ row.parent_id ? '　' : '' }}{{ row.label }}
                                        </option>
                                    </select>
                                </div>
                                <div class="flex-1">
                                    <label class="mb-0.5 block text-xs text-gray-500">グループ</label>
                                    <input v-model="item.parent_label" type="text" placeholder="例: 学校A"
                                        class="w-full rounded border border-gray-300 px-2 py-1 text-sm focus:border-indigo-400 focus:outline-none" />
                                </div>
                            </div>
                            <div>
                                <label class="mb-0.5 block text-xs text-gray-500">連携スケジュール</label>
                                <select v-model="item.linked_schedule_id" class="w-full rounded border border-gray-300 px-2 py-1 text-sm focus:border-indigo-400 focus:outline-none">
                                    <option :value="null">— 未連携 —</option>
                                    <option v-for="s in sheetStates[sheet.id].schedules" :key="s.id" :value="s.id">{{ s.name }}</option>
                                </select>
                            </div>
                        </div>
                        <button type="button" class="mt-1 w-full rounded border border-dashed border-gray-300 py-1.5 text-sm text-gray-500 hover:border-indigo-400 hover:text-indigo-600" @click="addEditRow(sheet.id, 'row')">＋ 空欄を追加</button>
                    </div>

                    <!-- 横列 編集 -->
                    <div class="pl-4">
                        <div class="mb-2 flex items-center justify-between">
                            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">横列</span>
                            <button type="button" class="text-xs text-indigo-600 hover:underline"
                                @click="sheetStates[sheet.id].showAddModal = true; sheetStates[sheet.id].addModalTab = 'column'">
                                ＋ 進行表から追加
                            </button>
                        </div>
                        <div v-for="(item, idx) in sheetStates[sheet.id].editColumns" :key="idx" class="mb-2 rounded border border-gray-200 bg-gray-50 p-2">
                            <div class="mb-1.5 flex gap-2">
                                <input v-model="item.name" type="text" placeholder="名前"
                                    list="item-name-suggestions"
                                    class="flex-1 rounded border border-gray-300 px-2 py-1 text-sm focus:border-indigo-400 focus:outline-none"
                                    @focus="fetchItemSuggestions" />
                                <button type="button" class="px-1 text-sm text-red-400 hover:text-red-600" @click="removeEditRow(sheet.id, 'column', idx)">×</button>
                            </div>
                            <div class="mb-1.5 flex gap-2">
                                <div class="flex-1">
                                    <label class="mb-0.5 block text-xs text-gray-500">進行表の列</label>
                                    <select v-model="item.col_key" class="w-full rounded border border-gray-300 px-2 py-1 text-sm focus:border-indigo-400 focus:outline-none">
                                        <option :value="null">—</option>
                                        <option v-for="col in sheetStates[sheet.id].allColumns" :key="col.key" :value="col.key">
                                            {{ col.label }}{{ !col.isLeaf ? '（グループ全体）' : '' }}
                                        </option>
                                    </select>
                                </div>
                                <div class="flex-1">
                                    <label class="mb-0.5 block text-xs text-gray-500">グループ</label>
                                    <input v-model="item.parent_label" type="text" placeholder="例: 初校"
                                        class="w-full rounded border border-gray-300 px-2 py-1 text-sm focus:border-indigo-400 focus:outline-none" />
                                </div>
                            </div>
                            <div>
                                <label class="mb-0.5 block text-xs text-gray-500">連携スケジュール</label>
                                <select v-model="item.linked_schedule_id" class="w-full rounded border border-gray-300 px-2 py-1 text-sm focus:border-indigo-400 focus:outline-none">
                                    <option :value="null">— 未連携 —</option>
                                    <option v-for="s in sheetStates[sheet.id].schedules" :key="s.id" :value="s.id">{{ s.name }}</option>
                                </select>
                            </div>
                        </div>
                        <button type="button" class="mt-1 w-full rounded border border-dashed border-gray-300 py-1.5 text-sm text-gray-500 hover:border-indigo-400 hover:text-indigo-600" @click="addEditRow(sheet.id, 'column')">＋ 空欄を追加</button>
                    </div>
                </div>
            </template>

            <!-- ── 追加モーダル ────────────────────────────────────────────── -->
            <div v-if="sheetStates[sheet.id]?.showAddModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
                @click.self="sheetStates[sheet.id].showAddModal = false">
                <div class="flex h-[80vh] w-full max-w-2xl flex-col rounded-lg bg-white shadow-xl">
                    <!-- モーダルヘッダー -->
                    <div class="flex items-center justify-between border-b border-gray-200 px-5 py-3">
                        <h3 class="font-semibold text-gray-800">進行表から追加 — {{ sheet.name }}</h3>
                        <button type="button" class="text-gray-400 hover:text-gray-600" @click="sheetStates[sheet.id].showAddModal = false">✕</button>
                    </div>

                    <!-- タブ -->
                    <div class="flex border-b border-gray-200">
                        <button type="button"
                            class="px-5 py-2.5 text-sm font-medium border-b-2 transition-colors"
                            :class="sheetStates[sheet.id].addModalTab === 'row'
                                ? 'border-indigo-600 text-indigo-600'
                                : 'border-transparent text-gray-500 hover:text-gray-700'"
                            @click="sheetStates[sheet.id].addModalTab = 'row'">
                            縦行（{{ sheetStates[sheet.id].rows.length }}件）
                        </button>
                        <button type="button"
                            class="px-5 py-2.5 text-sm font-medium border-b-2 transition-colors"
                            :class="sheetStates[sheet.id].addModalTab === 'column'
                                ? 'border-indigo-600 text-indigo-600'
                                : 'border-transparent text-gray-500 hover:text-gray-700'"
                            @click="sheetStates[sheet.id].addModalTab = 'column'">
                            横列（{{ flattenConfigNodes(sheetStates[sheet.id].columnConfig).filter(n => n.isLeaf).length }}件）
                        </button>
                    </div>

                    <!-- モーダルコンテンツ -->
                    <div class="flex-1 overflow-y-auto p-4">
                        <!-- 縦行ツリー -->
                        <template v-if="sheetStates[sheet.id].addModalTab === 'row'">
                            <p class="mb-3 text-xs text-gray-500">クリックして追加。グレーはすでに追加済みです。</p>
                            <div v-if="getRowTree(sheet.id).length === 0" class="text-sm text-gray-400">行がありません。</div>
                            <div v-for="node in getRowTree(sheet.id)" :key="node.id">
                                <!-- 親グループ（グループヘッダー） -->
                                <template v-if="!node.isLeaf">
                                    <div class="mb-1 mt-3 text-xs font-semibold text-gray-500">{{ node.label }}</div>
                                    <div v-for="child in node.children" :key="child.id"
                                        class="mb-1 ml-4 flex items-center gap-2 rounded border px-3 py-2 text-sm transition-colors"
                                        :class="isRowAdded(sheet.id, child.id)
                                            ? 'border-gray-200 bg-gray-100 text-gray-400 cursor-not-allowed'
                                            : 'border-indigo-200 bg-white text-gray-800 cursor-pointer hover:bg-indigo-50 hover:border-indigo-400'"
                                        @click="!isRowAdded(sheet.id, child.id) && addItemFromModal(sheet.id, 'row', { name: child.label, row_id: child.id, parent_label: node.label })">
                                        <span v-if="isRowAdded(sheet.id, child.id)" class="text-green-500">✓</span>
                                        <span>{{ child.label }}</span>
                                        <span v-if="!isRowAdded(sheet.id, child.id)" class="ml-auto text-xs text-indigo-400">追加</span>
                                    </div>
                                </template>
                                <!-- リーフ行（フラット構造） -->
                                <template v-else>
                                    <div class="mb-1 flex items-center gap-2 rounded border px-3 py-2 text-sm transition-colors"
                                        :class="isRowAdded(sheet.id, node.id)
                                            ? 'border-gray-200 bg-gray-100 text-gray-400 cursor-not-allowed'
                                            : 'border-indigo-200 bg-white text-gray-800 cursor-pointer hover:bg-indigo-50 hover:border-indigo-400'"
                                        @click="!isRowAdded(sheet.id, node.id) && addItemFromModal(sheet.id, 'row', { name: node.label, row_id: node.id, parent_label: null })">
                                        <span v-if="isRowAdded(sheet.id, node.id)" class="text-green-500">✓</span>
                                        <span>{{ node.label }}</span>
                                        <span v-if="!isRowAdded(sheet.id, node.id)" class="ml-auto text-xs text-indigo-400">追加</span>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <!-- 横列ツリー -->
                        <template v-else>
                            <p class="mb-3 text-xs text-gray-500">クリックして追加。グレーはすでに追加済みです。親グループを選ぶと配下の列すべてが対象になります。</p>
                            <div v-if="flattenConfigNodes(sheetStates[sheet.id].columnConfig).length === 0" class="text-sm text-gray-400">列がありません。</div>
                            <template v-for="node in flattenConfigNodes(sheetStates[sheet.id].columnConfig)" :key="node.key">
                                <!-- 全ノードをクリック可能 -->
                                <div class="mb-1 flex items-center gap-2 rounded border px-3 py-2 text-sm transition-colors"
                                    :style="{ marginLeft: (node.depth * 12) + 'px' }"
                                    :class="isColAdded(sheet.id, node.key)
                                        ? 'border-gray-200 bg-gray-100 text-gray-400 cursor-not-allowed'
                                        : 'border-indigo-200 bg-white text-gray-800 cursor-pointer hover:bg-indigo-50 hover:border-indigo-400'"
                                    @click="!isColAdded(sheet.id, node.key) && addItemFromModal(sheet.id, 'column', { name: node.label, col_key: node.key, parent_label: node.parentLabel })">
                                    <span v-if="isColAdded(sheet.id, node.key)" class="text-green-500">✓</span>
                                    <span>{{ node.label }}</span>
                                    <span v-if="!node.isLeaf" class="text-xs text-gray-400">（グループ全体）</span>
                                    <span v-if="!isColAdded(sheet.id, node.key)" class="ml-auto text-xs text-indigo-400">追加</span>
                                </div>
                            </template>
                        </template>
                    </div>

                    <!-- モーダルフッター -->
                    <div class="flex justify-end border-t border-gray-200 px-5 py-3">
                        <button type="button"
                            class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                            @click="sheetStates[sheet.id].showAddModal = false">
                            完了
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 項目リスト オートコンプリート用 datalist -->
    <datalist id="item-name-suggestions">
        <option v-for="s in itemSuggestions" :key="s" :value="s" />
    </datalist>
</template>
