<script setup>
import useToasts from '@/Composables/useToasts';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref, toRaw } from 'vue';

const props = defineProps({
    type: { type: String, required: true },
    typeLabel: { type: String, required: true },
    items: { type: Array, default: () => [] },
    groupOrders: { type: Array, default: () => [] },
});

// タイプ別ソートキー（順序列に使うフィールド名。null = 順序なし）
const sortKeyByType = {
    stages: 'order_index',
    work_item_types: 'sort_order',
    sizes: 'sort_order',
    statuses: 'sort_order',
    difficulties: 'sort_order',
};

const sortKey = computed(() => sortKeyByType[props.type] ?? null);

// タイプ別カラム定義（順序列・グループ列は別途テンプレートで制御）
const columnsByType = {
    stages: [
        { key: 'name', label: '名前', inputType: 'text', required: true },
        { key: 'coefficient', label: '係数', inputType: 'number' },
        { key: 'description', label: '説明', inputType: 'text' },
    ],
    work_item_types: [
        { key: 'name', label: '名前', inputType: 'text', required: true },
        { key: 'coefficient', label: '係数', inputType: 'number' },
        { key: 'description', label: '説明', inputType: 'text' },
    ],
    sizes: [
        { key: 'name', label: '名前', inputType: 'text', required: true },
        { key: 'label', label: 'ラベル', inputType: 'text' },
        { key: 'coefficient', label: '係数', inputType: 'number' },
    ],
    statuses: [
        { key: 'name', label: '名前', inputType: 'text', required: true },
        { key: 'coefficient', label: '係数', inputType: 'number' },
    ],
    difficulties: [
        { key: 'name', label: '名前', inputType: 'text', required: true },
        { key: 'coefficient', label: '係数', inputType: 'number' },
        { key: 'description', label: '説明', inputType: 'text' },
    ],
};

const columns = computed(() => columnsByType[props.type] ?? [{ key: 'name', label: '名前', inputType: 'text', required: true }]);

// グループ設定（対象タイプのみ）
const groupConfigByType = {
    work_item_types: {
        groups: ['dtp', 'design', 'proof', 'mgmt', 'sales', 'common', null],
        labels: {
            dtp: 'DTP',
            design: 'デザイン',
            proof: '校正',
            mgmt: '管理・進行',
            sales: '営業・受発注',
            common: '共通',
            null: 'グループなし',
        },
    },
    sizes: {
        groups: ['paper', 'digital'],
        labels: {
            paper: '紙媒体',
            digital: 'デジタル・Web',
        },
    },
};

// groupConfig をミュータブルな ref に（カスタムグループ追加のため）
const _init = groupConfigByType[props.type];
const groupConfig = ref(
    _init
        ? { groups: [..._init.groups], labels: { ..._init.labels } }
        : null,
);

// サーバーから保存済み順序が送られてきた場合、groupConfig.groups を並べ替える
if (groupConfig.value && props.groupOrders && props.groupOrders.length > 0) {
    const gc = groupConfig.value;
    // props.groupOrders をそのまま順序として使う（カスタムグループも含む）
    // gc.groups にあるが DB 未保存のグループは末尾に追加
    const savedOrder = props.groupOrders.map((k) => k ?? null);
    const rest = gc.groups.filter((g) => !savedOrder.includes(g ?? null));
    gc.groups = [...savedOrder, ...rest];
}

// グループ追加モーダル用ステート
const showGroupModal = ref(false);
const newGroupName = ref('');
const groupModalError = ref('');

// グループ編集モーダル用ステート
const showGroupEditModal = ref(false);
const modalGroups = ref([]); // [{ key, nameInput }]

// props を reactive なローカルコピーに変換
const state = reactive({
    items: props.items.map((item) => ({ ...item })),
});

// Inertia バリデーションエラーをアイテムオブジェクト参照で引く
const page = usePage();
function fieldError(item, field) {
    const idx = state.items.indexOf(item);
    const errors = page.props.errors ?? {};
    return errors[`items.${idx}.${field}`] ?? null;
}

// sort_order / order_index の昇順でソート（非グループ化タイプ用）
const sortedItems = computed(() => {
    if (!sortKey.value) return state.items;
    return [...state.items].sort((a, b) => (a[sortKey.value] ?? 0) - (b[sortKey.value] ?? 0));
});

// グループ別セクション（グループ設定がある場合のみ）
// items に存在するが groupConfig にないカスタムグループも末尾に表示する
const groupedSections = computed(() => {
    if (!groupConfig.value) return null;
    const { groups, labels } = groupConfig.value;
    // items から groupConfig 未登録のグループを抽出して末尾追加
    const configKeys = groups.map((g) => g ?? null);
    const extraKeys = [...new Set(state.items.map((i) => i.group ?? null))].filter(
        (k) => !configKeys.includes(k),
    );
    const allGroups = [...groups, ...extraKeys];
    return allGroups.map((key) => {
        const normalizedKey = key ?? null;
        const items = state.items
            .filter((i) => (i.group ?? null) === normalizedKey)
            .sort((a, b) => (sortKey.value ? (a[sortKey.value] ?? 0) - (b[sortKey.value] ?? 0) : 0));
        return {
            key: normalizedKey,
            keyStr: key !== null && key !== undefined ? String(key) : 'null',
            label: labels[key] ?? (key !== null ? String(key) : 'グループなし'),
            items,
        };
    });
});

// テーブルの colspan
const colSpan = computed(() => columns.value.length + 1 + (sortKey.value ? 1 : 0) + (groupConfig.value ? 1 : 0));

// 隣の行と sort_order 値を入れ替える（contextItems = スコープ内のアイテム配列）
function moveUp(item, contextItems) {
    const idx = contextItems.indexOf(item);
    if (idx <= 0) return;
    const prev = contextItems[idx - 1];
    const temp = item[sortKey.value];
    item[sortKey.value] = prev[sortKey.value];
    prev[sortKey.value] = temp;
}

function moveDown(item, contextItems) {
    const idx = contextItems.indexOf(item);
    if (idx >= contextItems.length - 1) return;
    const next = contextItems[idx + 1];
    const temp = item[sortKey.value];
    item[sortKey.value] = next[sortKey.value];
    next[sortKey.value] = temp;
}

// groupKey: undefined = 非グループ化タイプ、それ以外（null 含む）= グループ値
function addRow(groupKey) {
    const newRow = { _new: true };
    if (groupKey !== undefined) {
        newRow.group = groupKey;
        if (sortKey.value) {
            const scopeItems = state.items.filter((i) => (i.group ?? null) === (groupKey ?? null));
            const maxOrder = scopeItems.length ? Math.max(...scopeItems.map((i) => i[sortKey.value] ?? 0)) : -1;
            newRow[sortKey.value] = maxOrder + 1;
        }
    } else {
        if (sortKey.value) {
            const maxOrder = state.items.length ? Math.max(...state.items.map((i) => i[sortKey.value] ?? 0)) : -1;
            newRow[sortKey.value] = maxOrder + 1;
        }
    }
    state.items.push(newRow);
}

function markDelete(item) {
    item._deleted = true;
}

function undoDelete(item) {
    item._deleted = false;
}

// 新しいグループを追加してセクションを作成する
function addNewGroup() {
    const name = newGroupName.value.trim();
    if (!name) {
        groupModalError.value = 'グループ名を入力してください';
        return;
    }
    // 既存グループキー（null も含む）と重複チェック
    const existing = groupConfig.value.groups.map((g) => g ?? null);
    if (existing.includes(name)) {
        groupModalError.value = '同名のグループがすでに存在します';
        return;
    }
    groupConfig.value.groups.push(name);
    groupConfig.value.labels[name] = name;
    showGroupModal.value = false;
    newGroupName.value = '';
    groupModalError.value = '';
    // 新グループに空行を自動追加
    addRow(name);
}

// ---- グループ編集モーダル ----
function openGroupEditModal() {
    if (!groupConfig.value) return;
    // groupConfig.groups に未登録のカスタムグループ（extraKeys）も含める
    const configKeys = groupConfig.value.groups.map((g) => g ?? null);
    const extraKeys = [...new Set(state.items.map((i) => i.group ?? null))].filter(
        (k) => !configKeys.includes(k),
    );
    const allGroups = [...groupConfig.value.groups, ...extraKeys];
    modalGroups.value = allGroups.map((g) => ({
        key: g ?? null,
        nameInput: groupConfig.value.labels[g] ?? (g !== null ? String(g) : 'グループなし'),
    }));
    showGroupEditModal.value = true;
}

function moveGroupUp(idx) {
    if (idx <= 0) return;
    const a = modalGroups.value;
    const tmp = a[idx - 1];
    a[idx - 1] = a[idx];
    a[idx] = tmp;
}

function moveGroupDown(idx) {
    const a = modalGroups.value;
    if (idx >= a.length - 1) return;
    const tmp = a[idx + 1];
    a[idx + 1] = a[idx];
    a[idx] = tmp;
}

function isGroupInUse(key) {
    return state.items.some((i) => !i._deleted && (i.group ?? null) === (key ?? null));
}

function deleteModalGroup(idx) {
    modalGroups.value.splice(idx, 1);
}

function applyGroupEdit() {
    if (!groupConfig.value) return;
    const originalGroupKeys = new Set((groupConfigByType[props.type]?.groups ?? []).map((g) => g ?? null));
    const newGroups = [];
    const newLabels = { ...groupConfig.value.labels };
    for (const mg of modalGroups.value) {
        const oldKey = mg.key ?? null;
        const inputLabel = mg.nameInput.trim();
        const isCustom = oldKey !== null && !originalGroupKeys.has(oldKey);
        // カスタムグループ: 名前変更 → キーも変更して items も更新（永続化のため）
        const newKey = isCustom && inputLabel && inputLabel !== oldKey ? inputLabel : oldKey;
        newGroups.push(newKey);
        const labelKey = newKey === null ? 'null' : String(newKey);
        newLabels[labelKey] = inputLabel || (newKey !== null ? String(newKey) : 'グループなし');
        if (isCustom && newKey !== oldKey) {
            for (const item of state.items) {
                if ((item.group ?? null) === oldKey) item.group = newKey;
            }
            delete newLabels[String(oldKey)];
        }
    }
    // 削除されたグループのラベルをクリア
    const remainingLabelKeys = new Set(newGroups.map((k) => (k === null ? 'null' : String(k))));
    for (const k of Object.keys(newLabels)) {
        if (!remainingLabelKeys.has(k)) delete newLabels[k];
    }
    groupConfig.value.groups = newGroups;
    groupConfig.value.labels = newLabels;
    showGroupEditModal.value = false;
}

function groupLabel(key) {
    if (!groupConfig.value) return String(key ?? 'グループなし');
    return groupConfig.value.labels[key] ?? (key !== null ? String(key) : 'グループなし');
}
// ---------------------------------

const { showToast, showValidationErrors } = useToasts();

function save() {
    // グループ化タイプ: 各グループに最低1件の有効な項目が必要
    if (groupConfig.value && groupedSections.value) {
        for (const section of groupedSections.value) {
            const active = section.items.filter((i) => !i._deleted);
            if (active.length === 0) {
                showToast(`「${section.label}」グループには最低1件の項目が必要です`, 'error');
                return;
            }
        }
    }
    router.post(
        route('workload_setting.store', { type: props.type }),
        { items: toRaw(state.items), group_orders: groupConfig.value ? groupConfig.value.groups.map((g) => g ?? null) : undefined },
        {
            preserveState: true,
            onSuccess: () => {
                showToast('保存しました', 'success');
            },
            onError: (errors) => {
                showValidationErrors(errors);
            },
        },
    );
}

function revert() {
    window.location.reload();
}
</script>

<template>
    <AppLayout :title="`${typeLabel} 編集`">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ typeLabel }} の編集</h2>
        </template>

        <Head :title="`${typeLabel} 編集`" />

        <div class="rounded bg-white p-6 shadow">
            <!-- 戻るリンク -->
            <div class="mb-4">
                <a :href="route('workload_setting.index')" class="text-sm text-gray-500 hover:text-gray-700"> ← 一覧に戻る </a>
            </div>

            <!-- テーブル -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th v-if="sortKey" class="w-28 px-3 py-2 text-left font-medium text-gray-600">順序</th>
                            <th v-for="col in columns" :key="col.key" class="px-3 py-2 text-left font-medium text-gray-600">
                                {{ col.label }}<span v-if="col.required" class="text-red-500">*</span>
                            </th>
                            <th v-if="groupConfig" class="w-36 px-3 py-2 text-left font-medium text-gray-600">グループ</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">操作</th>
                        </tr>
                    </thead>

                    <!-- ■ 非グループ化タイプ ■ -->
                    <tbody v-if="!groupedSections" class="divide-y divide-gray-100">
                        <tr
                            v-for="(item, idx) in sortedItems"
                            :key="item.id ?? 'new-' + idx"
                            :class="item._deleted ? 'bg-red-50 opacity-60' : 'hover:bg-gray-50'"
                        >
                            <td v-if="sortKey" class="px-3 py-2">
                                <div class="flex items-center gap-1">
                                    <span class="w-7 text-right text-gray-700">
                                        {{ (item[sortKey] ?? 0) + 1 }}
                                    </span>
                                    <div class="flex flex-col">
                                        <button
                                            type="button"
                                            :disabled="idx === 0 || !!item._deleted"
                                            class="flex h-5 w-5 items-center justify-center rounded text-gray-500 hover:bg-gray-200 disabled:cursor-not-allowed disabled:opacity-30"
                                            @click="moveUp(item, sortedItems)"
                                        >
                                            ▲
                                        </button>
                                        <button
                                            type="button"
                                            :disabled="idx === sortedItems.length - 1 || !!item._deleted"
                                            class="flex h-5 w-5 items-center justify-center rounded text-gray-500 hover:bg-gray-200 disabled:cursor-not-allowed disabled:opacity-30"
                                            @click="moveDown(item, sortedItems)"
                                        >
                                            ▼
                                        </button>
                                    </div>
                                </div>
                            </td>
                            <td v-for="col in columns" :key="col.key" class="px-3 py-2">
                                <input
                                    v-model="item[col.key]"
                                    :type="col.inputType"
                                    :disabled="!!item._deleted"
                                    :placeholder="col.label"
                                    class="rounded border px-2 py-1 text-sm focus:outline-none disabled:bg-gray-100 disabled:text-gray-400"
                                    :class="[
                                        col.inputType === 'number' ? 'w-24' : 'w-full',
                                        fieldError(item, col.key) ? 'border-red-400 focus:border-red-500' : 'border-gray-300 focus:border-blue-400',
                                    ]"
                                />
                                <p v-if="fieldError(item, col.key)" class="mt-0.5 text-xs text-red-500">
                                    {{ fieldError(item, col.key) }}
                                </p>
                            </td>
                            <td class="whitespace-nowrap px-3 py-2">
                                <button v-if="!item._deleted" type="button" class="text-red-600 hover:underline" @click="markDelete(item)">
                                    削除
                                </button>
                                <button v-else type="button" class="text-green-600 hover:underline" @click="undoDelete(item)">元に戻す</button>
                            </td>
                        </tr>

                        <tr v-if="state.items.length === 0">
                            <td :colspan="colSpan" class="px-3 py-4 text-center text-gray-400">登録がありません</td>
                        </tr>
                    </tbody>

                    <!-- ■ グループ化タイプ ■ -->
                    <template v-else>
                        <tbody v-for="section in groupedSections" :key="section.keyStr" class="divide-y divide-gray-100">
                            <!-- グループヘッダー行 -->
                            <tr class="bg-indigo-50">
                                <td :colspan="colSpan" class="px-3 py-2">
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs font-semibold uppercase tracking-wide text-indigo-700">
                                            {{ section.label }}
                                        </span>
                                        <button
                                            type="button"
                                            class="rounded border border-blue-500 px-2 py-0.5 text-xs text-blue-600 hover:bg-blue-50"
                                            @click="addRow(section.key)"
                                        >
                                            + 追加
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <!-- グループ内の行 -->
                            <tr
                                v-for="(item, idx) in section.items"
                                :key="item.id ?? 'new-' + section.keyStr + '-' + idx"
                                :class="item._deleted ? 'bg-red-50 opacity-60' : 'hover:bg-gray-50'"
                            >
                                <td v-if="sortKey" class="px-3 py-2">
                                    <div class="flex items-center gap-1">
                                        <span class="w-7 text-right text-gray-700">{{ idx + 1 }}</span>
                                        <div class="flex flex-col">
                                            <button
                                                type="button"
                                                :disabled="idx === 0 || !!item._deleted"
                                                class="flex h-5 w-5 items-center justify-center rounded text-gray-500 hover:bg-gray-200 disabled:cursor-not-allowed disabled:opacity-30"
                                                @click="moveUp(item, section.items)"
                                            >
                                                ▲
                                            </button>
                                            <button
                                                type="button"
                                                :disabled="idx === section.items.length - 1 || !!item._deleted"
                                                class="flex h-5 w-5 items-center justify-center rounded text-gray-500 hover:bg-gray-200 disabled:cursor-not-allowed disabled:opacity-30"
                                                @click="moveDown(item, section.items)"
                                            >
                                                ▼
                                            </button>
                                        </div>
                                    </div>
                                </td>
                                <td v-for="col in columns" :key="col.key" class="px-3 py-2">
                                    <input
                                        v-model="item[col.key]"
                                        :type="col.inputType"
                                        :disabled="!!item._deleted"
                                        :placeholder="col.label"
                                        class="rounded border px-2 py-1 text-sm focus:outline-none disabled:bg-gray-100 disabled:text-gray-400"
                                        :class="[
                                            col.inputType === 'number' ? 'w-24' : 'w-full',
                                            fieldError(item, col.key)
                                                ? 'border-red-400 focus:border-red-500'
                                                : 'border-gray-300 focus:border-blue-400',
                                        ]"
                                    />
                                    <p v-if="fieldError(item, col.key)" class="mt-0.5 text-xs text-red-500">
                                        {{ fieldError(item, col.key) }}
                                    </p>
                                </td>
                                <td class="px-3 py-2">
                                    <select
                                        v-model="item.group"
                                        :disabled="!!item._deleted"
                                        class="rounded border border-gray-300 px-2 py-1 text-sm focus:border-blue-400 focus:outline-none disabled:bg-gray-100 disabled:text-gray-400"
                                    >
                                        <option v-for="g in groupConfig.groups" :key="g ?? '__null__'" :value="g">
                                            {{ groupConfig.labels[g] ?? (g !== null ? String(g) : 'グループなし') }}
                                        </option>
                                    </select>
                                </td>
                                <td class="whitespace-nowrap px-3 py-2">
                                    <button v-if="!item._deleted" type="button" class="text-red-600 hover:underline" @click="markDelete(item)">
                                        削除
                                    </button>
                                    <button v-else type="button" class="text-green-600 hover:underline" @click="undoDelete(item)">元に戻す</button>
                                </td>
                            </tr>

                            <!-- グループが空の場合 -->
                            <tr v-if="section.items.length === 0">
                                <td :colspan="colSpan" class="px-3 py-3 text-center text-xs text-gray-400">このグループには登録がありません</td>
                            </tr>
                        </tbody>
                    </template>
                </table>
            </div>

            <!-- フッターボタン -->
            <div class="mt-4 flex items-center justify-between">
                <div class="flex gap-2">
                    <!-- グループ化タイプは各グループヘッダーに「+ 追加」ボタンがあるため非表示 -->
                    <button
                        v-if="!groupedSections"
                        type="button"
                        class="rounded border border-blue-600 px-4 py-2 text-sm text-blue-600 hover:bg-blue-50"
                        @click="addRow()"
                    >
                        + 行を追加
                    </button>
                    <!-- work_item_types のみ: 新しいグループを追加 -->
                    <button
                        v-if="groupConfig && type === 'work_item_types'"
                        type="button"
                        class="rounded border border-green-600 px-4 py-2 text-sm text-green-600 hover:bg-green-50"
                        @click="showGroupModal = true"
                    >
                        + グループを追加
                    </button>
                    <!-- work_item_types のみ: グループを編集 -->
                    <button
                        v-if="groupConfig && type === 'work_item_types'"
                        type="button"
                        class="rounded border border-indigo-500 px-4 py-2 text-sm text-indigo-600 hover:bg-indigo-50"
                        @click="openGroupEditModal"
                    >
                        ✎ グループを編集
                    </button>
                </div>
                <div class="flex gap-3">
                    <button type="button" class="rounded border px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" @click="revert">リセット</button>
                    <button type="button" class="rounded bg-blue-600 px-4 py-2 text-sm text-white hover:bg-blue-700" @click="save">保存する</button>
                </div>
            </div>
        </div>

        <!-- グループ追加モーダル -->
        <div
            v-if="showGroupModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
            @click.self="showGroupModal = false"
        >
            <div class="mx-4 w-full max-w-sm rounded-lg bg-white shadow-xl">
                <div class="border-b px-6 py-4">
                    <h2 class="text-lg font-semibold text-gray-800">新しいグループを追加</h2>
                </div>
                <div class="p-6">
                    <label class="mb-1 block text-sm font-medium text-gray-700">
                        グループ名 <span class="text-red-500">*</span>
                    </label>
                    <input
                        v-model="newGroupName"
                        type="text"
                        class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none"
                        placeholder="例：特殊処理"
                        @keydown.enter.prevent="addNewGroup"
                    />
                    <p v-if="groupModalError" class="mt-1 text-xs text-red-500">{{ groupModalError }}</p>
                </div>
                <div class="flex justify-end gap-2 border-t px-6 py-4">
                    <button
                        type="button"
                        class="rounded bg-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-300"
                        @click="showGroupModal = false; newGroupName = ''; groupModalError = ''"
                    >
                        キャンセル
                    </button>
                    <button
                        type="button"
                        class="rounded bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700"
                        @click="addNewGroup"
                    >
                        追加
                    </button>
                </div>
            </div>
        </div>

        <!-- グループ編集モーダル -->
        <div
            v-if="showGroupEditModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
            @click.self="showGroupEditModal = false"
        >
            <div class="mx-4 w-full max-w-md rounded-lg bg-white shadow-xl">
                <div class="border-b px-6 py-4">
                    <h2 class="text-lg font-semibold text-gray-800">グループを編集</h2>
                </div>
                <div class="max-h-96 overflow-y-auto p-4">
                    <ul class="divide-y divide-gray-100">
                        <li
                            v-for="(mg, idx) in modalGroups"
                            :key="mg.key ?? '__null__'"
                            class="py-2"
                        >
                            <div class="flex items-center gap-2">
                                <!-- 並べ替えボタン -->
                                <div class="flex flex-col gap-0.5">
                                    <button
                                        type="button"
                                        :disabled="idx === 0"
                                        class="flex h-6 w-6 items-center justify-center rounded text-gray-400 hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-30"
                                        @click="moveGroupUp(idx)"
                                    >
                                        ▲
                                    </button>
                                    <button
                                        type="button"
                                        :disabled="idx === modalGroups.length - 1"
                                        class="flex h-6 w-6 items-center justify-center rounded text-gray-400 hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-30"
                                        @click="moveGroupDown(idx)"
                                    >
                                        ▼
                                    </button>
                                </div>
                                <!-- 名前入力（null グループは変更不可） -->
                                <input
                                    v-if="mg.key !== null"
                                    v-model="mg.nameInput"
                                    type="text"
                                    class="flex-1 rounded border border-gray-300 px-2 py-1 text-sm focus:border-blue-400 focus:outline-none"
                                />
                                <span v-else class="flex-1 text-sm text-gray-400">グループなし（変更不可）</span>
                                <!-- 削除ボタン -->
                                <button
                                    type="button"
                                    :disabled="isGroupInUse(mg.key) || mg.key === null"
                                    class="whitespace-nowrap rounded px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50 disabled:cursor-not-allowed disabled:text-gray-300"
                                    @click="deleteModalGroup(idx)"
                                >
                                    削除
                                </button>
                            </div>
                            <!-- 使用中の注意書き -->
                            <p v-if="isGroupInUse(mg.key)" class="mt-1 pl-14 text-xs text-orange-600">
                                ⚠ このグループには項目があるため削除できません
                            </p>
                        </li>
                    </ul>
                </div>
                <div class="flex justify-end gap-2 border-t px-6 py-4">
                    <button
                        type="button"
                        class="rounded bg-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-300"
                        @click="showGroupEditModal = false"
                    >
                        キャンセル
                    </button>
                    <button
                        type="button"
                        class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                        @click="applyGroupEdit"
                    >
                        適用
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
