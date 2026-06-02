<script setup>
import useToasts from '@/Composables/useToasts';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref, toRaw } from 'vue';

const props = defineProps({
    noCompanySelected: { type: Boolean, default: false },
    departments:       { type: Array,   default: () => [] },
    currentScope:      { type: String,  default: 'company' },
    canEditScope:      { type: Boolean, default: false },
    groupOrders:       { type: Array,   default: () => [] },
    stages:            { type: Array,   default: () => [] },
    work_item_types:   { type: Array,   default: () => [] },
    sizes:             { type: Array,   default: () => [] },
    statuses:          { type: Array,   default: () => [] },
    difficulties:      { type: Array,   default: () => [] },
});

// ─── 定数 ────────────────────────────────────────────────────────────────────
const TYPE_LABELS = {
    stages:          'Stages',
    work_item_types: 'Work Item Types',
    sizes:           'Sizes',
    statuses:        'Statuses',
    difficulties:    'Difficulties',
};

const SORT_KEY_BY_TYPE = {
    stages:          'order_index',
    work_item_types: 'sort_order',
    sizes:           'sort_order',
    statuses:        'sort_order',
    difficulties:    'sort_order',
};

const COLUMNS_BY_TYPE = {
    stages: [
        { key: 'name', label: '名前', inputType: 'text', required: true },
        { key: 'coefficient', label: '係数', inputType: 'number' },
        { key: 'description', label: '説明', inputType: 'text' },
    ],
    work_item_types: [
        { key: 'name', label: '名前', inputType: 'text', required: true },
        { key: 'coefficient', label: '係数', inputType: 'number' },
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

const GROUP_TYPES = ['work_item_types', 'sizes'];

const ALL_TYPES = ['stages', 'work_item_types', 'sizes', 'statuses', 'difficulties'];

// ─── 編集モード状態 ───────────────────────────────────────────────────────────
const editMode = ref(false);
const editStates = reactive({});

// グループ追加モーダル（タイプ共通、1つだけ開く）
const groupAddModal = reactive({ show: false, type: null, name: '', error: '' });

// グループ名インライン編集（タイプ共通）
const groupRenameState = reactive({ type: null, groupKey: null, input: '' });

// ─── グループ設定ビルド（BUG FIX: null を強制追加しない）────────────────────
function buildGroupConfig(type, items, savedOrder) {
    if (!GROUP_TYPES.includes(type)) return null;
    const fromItems = [...new Set(items.map((i) => i.group ?? null))];
    let groups;
    if (savedOrder && savedOrder.length > 0) {
        // savedOrder のうち実際にアイテムが存在するグループのみ保持
        const saved = savedOrder.map((k) => k ?? null).filter((k) => fromItems.includes(k));
        const rest  = fromItems.filter((k) => !saved.includes(k));
        groups = [...saved, ...rest];
    } else {
        const nonNull = fromItems.filter((k) => k !== null);
        const hasNull = fromItems.includes(null);
        groups = hasNull ? [...nonNull, null] : nonNull;
    }
    return { groups };
}

// ─── 編集モード ON/OFF ────────────────────────────────────────────────────────
function startEdit() {
    ALL_TYPES.forEach((type) => {
        const items      = props[type] ?? [];
        const savedOrder = type === 'work_item_types' ? props.groupOrders : [];
        editStates[type] = {
            items: items.map((i) => ({
                ...i,
                // 部署トグル初期状態: deptItemIds から { deptId: bool } を構築
                _deptToggles: i.deptItemIds
                    ? Object.fromEntries(Object.entries(i.deptItemIds).map(([k, v]) => [k, v !== null]))
                    : {},
            })),
            groupConfig: buildGroupConfig(type, items, savedOrder),
        };
    });
    editMode.value = true;
}

function cancelEdit() {
    editMode.value = false;
    Object.keys(editStates).forEach((k) => delete editStates[k]);
    groupAddModal.show = false;
    groupRenameState.type = null;
}

// ─── グループ別セクション（編集用）─────────────────────────────────────────
function getGroupedSections(type) {
    const gc = editStates[type]?.groupConfig;
    if (!gc) return null;
    const { groups } = gc;
    const items = editStates[type].items;
    const configKeys = groups.map((g) => g ?? null);
    const extraKeys  = [...new Set(items.map((i) => i.group ?? null))].filter((k) => !configKeys.includes(k));
    const sortKey    = SORT_KEY_BY_TYPE[type];
    return [...groups, ...extraKeys].map((key) => {
        const nk = key ?? null;
        return {
            key: nk,
            keyStr: nk !== null ? String(nk) : '__null__',
            label: nk !== null ? String(nk) : 'グループなし',
            items: items.filter((i) => (i.group ?? null) === nk)
                        .sort((a, b) => (a[sortKey] ?? 0) - (b[sortKey] ?? 0)),
        };
    });
}

// ─── 読み取り用グループ別セクション ─────────────────────────────────────────
function getReadGroupedSections(type, items) {
    if (!GROUP_TYPES.includes(type) || !items || items.length === 0) return null;
    const keys    = [...new Set(items.map((i) => i.group ?? null))];
    const nonNull = keys.filter((k) => k !== null);
    const hasNull = keys.includes(null);
    const sections = [
        ...nonNull.map((key) => ({
            key,
            label: String(key),
            items: items.filter((i) => (i.group ?? null) === key),
        })),
        ...(hasNull ? [{
            key: null, label: 'グループなし',
            items: items.filter((i) => (i.group ?? null) === null),
        }] : []),
    ].filter((s) => s.items.length > 0);
    return sections.length > 0 ? sections : null;
}

// ─── スコープラベル ──────────────────────────────────────────────────────────
function scopeLabel(scopeKey) {
    if (!scopeKey || scopeKey === 'company') return '会社全体';
    const dept = props.departments.find((d) => String(d.id) === String(scopeKey));
    return dept ? dept.name : scopeKey;
}

// ─── アイテム操作 ────────────────────────────────────────────────────────────
// targetScope: 省略時は現在のスコープ。部署ボタンで別スコープへ追加できる
function addRow(type, groupKey, targetScope) {
    const sortKey = SORT_KEY_BY_TYPE[type];
    const items   = editStates[type].items;
    const newRow  = { _new: true, _targetScope: targetScope ?? props.currentScope };
    if (groupKey !== undefined) {
        newRow.group = groupKey;
        if (sortKey) {
            const scope = items.filter((i) => (i.group ?? null) === (groupKey ?? null));
            newRow[sortKey] = scope.length ? Math.max(...scope.map((i) => i[sortKey] ?? 0)) + 1 : 0;
        }
    } else if (sortKey) {
        newRow[sortKey] = items.length ? Math.max(...items.map((i) => i[sortKey] ?? 0)) + 1 : 0;
    }
    items.push(newRow);
}

function markDelete(type, item) { item._deleted = true; }
function undoDelete(type, item) { item._deleted = false; }

function moveUp(type, item, contextItems) {
    const sortKey = SORT_KEY_BY_TYPE[type];
    if (!sortKey) return;
    const idx = contextItems.indexOf(item);
    if (idx <= 0) return;
    const prev = contextItems[idx - 1];
    [item[sortKey], prev[sortKey]] = [prev[sortKey], item[sortKey]];
}

function moveDown(type, item, contextItems) {
    const sortKey = SORT_KEY_BY_TYPE[type];
    if (!sortKey) return;
    const idx = contextItems.indexOf(item);
    if (idx >= contextItems.length - 1) return;
    const next = contextItems[idx + 1];
    [item[sortKey], next[sortKey]] = [next[sortKey], item[sortKey]];
}

// ─── グループ操作 ────────────────────────────────────────────────────────────
function openGroupAddModal(type) {
    groupAddModal.show  = true;
    groupAddModal.type  = type;
    groupAddModal.name  = '';
    groupAddModal.error = '';
}

function confirmAddGroup() {
    const name = groupAddModal.name.trim();
    if (!name) { groupAddModal.error = 'グループ名を入力してください'; return; }
    const gc = editStates[groupAddModal.type]?.groupConfig;
    if (!gc) return;
    if (gc.groups.map((g) => g ?? null).includes(name)) {
        groupAddModal.error = '同名のグループが既に存在します';
        return;
    }
    gc.groups.push(name);
    addRow(groupAddModal.type, name);
    groupAddModal.show = false;
}

function startRenameGroup(type, groupKey) {
    groupRenameState.type     = type;
    groupRenameState.groupKey = groupKey;
    groupRenameState.input    = groupKey !== null ? String(groupKey) : '';
}

function confirmRenameGroup(type, oldKey) {
    const newName = groupRenameState.input.trim();
    if (!newName || newName === (oldKey ?? '')) {
        groupRenameState.type = null;
        return;
    }
    const gc    = editStates[type]?.groupConfig;
    const items = editStates[type]?.items;
    if (!gc || !items) { groupRenameState.type = null; return; }
    const idx = gc.groups.findIndex((g) => (g ?? null) === (oldKey ?? null));
    if (idx >= 0) gc.groups[idx] = newName;
    items.forEach((item) => { if ((item.group ?? null) === (oldKey ?? null)) item.group = newName; });
    groupRenameState.type = null;
}

function deleteGroup(type, groupKey) {
    const gc    = editStates[type]?.groupConfig;
    const items = editStates[type]?.items;
    if (!gc || !items) return;
    const inUse = items.some((i) => !i._deleted && (i.group ?? null) === (groupKey ?? null));
    if (inUse) return;
    gc.groups = gc.groups.filter((g) => (g ?? null) !== (groupKey ?? null));
}

function isGroupInUse(type, groupKey) {
    return editStates[type]?.items.some((i) => !i._deleted && (i.group ?? null) === (groupKey ?? null)) ?? false;
}

function moveGroupUp(type, idx) {
    const groups = editStates[type]?.groupConfig?.groups;
    if (!groups || idx <= 0) return;
    [groups[idx - 1], groups[idx]] = [groups[idx], groups[idx - 1]];
}

function moveGroupDown(type, idx) {
    const groups = editStates[type]?.groupConfig?.groups;
    if (!groups || idx >= groups.length - 1) return;
    [groups[idx], groups[idx + 1]] = [groups[idx + 1], groups[idx]];
}

// ─── 保存 ────────────────────────────────────────────────────────────────────
const { showToast, showValidationErrors } = useToasts();

function postScope(type, scope, items, groupOrders) {
    return new Promise((resolve, reject) => {
        // 内部プロパティを除いたクリーンなデータを送信
        const clean = items.map(({ _new, _targetScope, ...rest }) => rest);
        router.post(
            route('workload_setting.store', { type }),
            { items: toRaw(clean), group_orders: groupOrders, scope },
            {
                preserveState: true,
                preserveScroll: true,
                onSuccess: resolve,
                onError:   (errors) => { showValidationErrors(errors); reject(errors); },
            },
        );
    });
}

async function saveType(type) {
    const state = editStates[type];
    if (!state) return;
    const groupOrders = state.groupConfig ? state.groupConfig.groups.map((g) => g ?? null) : undefined;
    const valid = state.items.filter((i) => !(i._new && i._deleted));

    // ① 通常スコープ保存（既存→現在スコープ、新規→_targetScope）
    const byScope = {};
    for (const item of valid) {
        const scope = item._new ? (item._targetScope ?? props.currentScope) : props.currentScope;
        (byScope[scope] ??= []).push(item);
    }

    // ② 部署トグル変更の収集（既存アイテムのみ、company-wide スコープのみ）
    const deptActions = {}; // deptId → [{action:'add'|'del', itemData}]
    if (props.currentScope === 'company') {
        for (const item of valid) {
            if (item._new || !item._deptToggles) continue;
            const original = props.deptItemIds ?? item.deptItemIds ?? {};
            for (const [deptIdStr, isOn] of Object.entries(item._deptToggles)) {
                const wasOn = (item.deptItemIds?.[deptIdStr] ?? null) !== null;
                if (isOn === wasOn) continue;
                if (!deptActions[deptIdStr]) deptActions[deptIdStr] = [];
                if (isOn) {
                    // 新規追加: 現在の item を複製してその部署用に
                    const { id, _deptToggles, deptItemIds, usedByDepts, _new, _deleted, _targetScope, ...rest } = item;
                    deptActions[deptIdStr].push({ ...rest });
                } else {
                    // 削除: 既存の item_id を使って削除マーク
                    const itemId = item.deptItemIds?.[deptIdStr];
                    if (itemId) deptActions[deptIdStr].push({ id: itemId, _deleted: true, name: item.name });
                }
            }
        }
    }

    try {
        for (const [scope, items] of Object.entries(byScope)) {
            await postScope(type, scope, items, groupOrders);
        }
        for (const [deptId, actions] of Object.entries(deptActions)) {
            if (actions.length > 0) await postScope(type, deptId, actions, undefined);
        }
        showToast(`${TYPE_LABELS[type]} を保存しました`, 'success');
    } catch (_) {
        // エラーは postScope 内で表示済み
    }
}

// ─── スコープ切り替え ──────────────────────────────────────────────────────
function switchScope(scopeKey) {
    if (editMode.value) cancelEdit();
    const params = scopeKey !== 'company' ? { dept: scopeKey } : {};
    router.get(route('workload_setting.index'), params, { preserveState: false });
}

// ─── バリデーションエラー ────────────────────────────────────────────────────
const page = usePage();
function fieldError(type, item, field) {
    const idx = editStates[type]?.items.indexOf(item) ?? -1;
    if (idx < 0) return null;
    return page.props.errors?.[`items.${idx}.${field}`] ?? null;
}

// ─── 読み取り用セクション一覧 ────────────────────────────────────────────────
const readSections = computed(() => ALL_TYPES.map((type) => ({
    type,
    label: TYPE_LABELS[type],
    items: props[type] ?? [],
})));
</script>

<template>
    <AppLayout title="作業項目設定">
        <template #header>
            <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">作業項目設定</h2>
        </template>

        <template #headerExtras>
            <button
                v-if="!editMode && !noCompanySelected"
                type="button"
                class="inline-flex items-center gap-1.5 rounded bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700"
                @click="startEdit"
            >
                ✎ 編集モード
            </button>
            <template v-else-if="editMode">
                <span class="text-xs font-medium text-amber-600 mr-2">✏ 編集中</span>
                <button
                    type="button"
                    class="rounded border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50"
                    @click="cancelEdit"
                >
                    終了
                </button>
            </template>
        </template>

        <Head title="作業項目設定" />

        <!-- SuperAdmin 未選択警告 -->
        <div v-if="noCompanySelected" class="rounded border border-yellow-300 bg-yellow-50 p-6 shadow">
            <div class="flex items-start gap-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 h-5 w-5 shrink-0 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>
                <div>
                    <p class="font-semibold text-yellow-800">会社が選択されていません</p>
                    <p class="mt-1 text-sm text-yellow-700">右上の会社コンテキスト切り替えで表示したい会社を選択してから、このページを開いてください。</p>
                </div>
            </div>
        </div>

        <div v-else class="space-y-4">
            <!-- 部署スコープバー -->
            <div class="rounded bg-white px-4 py-3 shadow">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs font-medium text-gray-500 mr-1">スコープ:</span>
                    <button
                        type="button"
                        :disabled="!canEditScope && currentScope !== 'company'"
                        :class="['rounded px-3 py-1 text-sm font-medium transition-colors',
                            currentScope === 'company'
                                ? 'bg-gray-700 text-white'
                                : canEditScope ? 'bg-gray-100 text-gray-600 hover:bg-gray-200' : 'bg-gray-100 text-gray-300 cursor-not-allowed']"
                        @click="canEditScope && switchScope('company')"
                    >会社全体</button>
                    <button
                        v-for="dept in departments"
                        :key="dept.id"
                        type="button"
                        :disabled="!canEditScope && currentScope !== String(dept.id)"
                        :class="['rounded px-3 py-1 text-sm font-medium transition-colors',
                            currentScope === String(dept.id)
                                ? 'bg-blue-600 text-white'
                                : canEditScope ? 'bg-gray-100 text-gray-600 hover:bg-blue-100 hover:text-blue-700' : 'bg-gray-100 text-gray-300 cursor-not-allowed']"
                        @click="(canEditScope || currentScope === String(dept.id)) && switchScope(String(dept.id))"
                    >{{ dept.name }}</button>
                </div>
            </div>

            <!-- タイプ別セクション -->
            <div
                v-for="section in readSections"
                :key="section.type"
                class="rounded bg-white shadow"
            >
                <!-- セクションヘッダー -->
                <div class="flex items-center justify-between border-b px-4 py-3">
                    <h3 class="font-semibold text-gray-800">{{ section.label }}</h3>
                    <div v-if="editMode" class="flex flex-wrap items-center gap-1.5">
                        <!-- 非グループ型: 会社全体 + 部署ごとの追加ボタン -->
                        <template v-if="!editStates[section.type]?.groupConfig">
                            <button
                                type="button"
                                class="rounded border border-blue-500 px-2 py-1 text-xs text-blue-600 hover:bg-blue-50 whitespace-nowrap"
                                @click="addRow(section.type, undefined, 'company')"
                            >＋ 会社全体</button>
                            <button
                                v-for="d in departments"
                                :key="d.id"
                                type="button"
                                class="rounded border border-indigo-400 px-2 py-1 text-xs text-indigo-600 hover:bg-indigo-50 whitespace-nowrap"
                                @click="addRow(section.type, undefined, String(d.id))"
                            >＋ {{ d.name }}</button>
                        </template>
                        <!-- グループ型: グループ追加ボタン -->
                        <button
                            v-if="editStates[section.type]?.groupConfig"
                            type="button"
                            class="rounded border border-green-600 px-2 py-1 text-xs text-green-600 hover:bg-green-50 whitespace-nowrap"
                            @click="openGroupAddModal(section.type)"
                        >＋ グループ追加</button>
                        <button
                            type="button"
                            class="rounded bg-blue-600 px-3 py-1 text-xs font-medium text-white hover:bg-blue-700 whitespace-nowrap"
                            @click="saveType(section.type)"
                        >保存</button>
                    </div>
                </div>

                <!-- ═══ 読み取りモード ═══ -->
                <div v-if="!editMode" class="px-4 py-4">
                    <!-- グループなし: 通常リスト -->
                    <template v-if="!getReadGroupedSections(section.type, section.items)">
                        <ul class="divide-y divide-gray-100 text-sm">
                            <li v-for="item in section.items" :key="item.id" class="flex items-center gap-2 py-1.5">
                                <span class="flex-1 text-gray-700">{{ item.name }}</span>
                                <span v-if="item.label" class="text-xs text-gray-400">{{ item.label }}</span>
                                <!-- 部署バッジ（company-wide スコープのみ） -->
                                <template v-if="currentScope === 'company' && item.usedByDepts?.length">
                                    <span
                                        v-for="d in item.usedByDepts"
                                        :key="d.id"
                                        class="inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700"
                                    >{{ d.name }}</span>
                                </template>
                            </li>
                            <li v-if="!section.items.length" class="py-2 text-sm text-gray-400">登録がありません</li>
                        </ul>
                    </template>

                    <!-- グループあり: グループ別 -->
                    <template v-else>
                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <div v-for="grp in getReadGroupedSections(section.type, section.items)" :key="grp.key ?? '__null__'">
                                <div class="mb-1 border-b border-gray-200 pb-0.5 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ grp.label }}</div>
                                <ul class="space-y-0.5 text-sm">
                                    <li v-for="item in grp.items" :key="item.id" class="flex items-center gap-1.5 py-0.5">
                                        <span class="flex-1 text-gray-700">{{ item.name }}</span>
                                        <template v-if="currentScope === 'company' && item.usedByDepts?.length">
                                            <span
                                                v-for="d in item.usedByDepts"
                                                :key="d.id"
                                                class="inline-flex items-center rounded-full bg-blue-100 px-1.5 py-0.5 text-xs font-medium text-blue-700"
                                            >{{ d.name }}</span>
                                        </template>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- ═══ 編集モード ═══ -->
                <div v-else class="px-4 py-4">

                    <!-- ■ 非グループ化タイプ ■ -->
                    <template v-if="!editStates[section.type]?.groupConfig">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="w-12 px-2 py-2 text-left font-medium text-gray-600">順序</th>
                                    <th class="w-48 px-2 py-2 text-left font-medium text-gray-600">
                                        名前<span class="text-red-500">*</span>
                                    </th>
                                    <th v-for="col in COLUMNS_BY_TYPE[section.type].filter(c => c.key !== 'name')" :key="col.key" class="px-2 py-2 text-left font-medium text-gray-600 whitespace-nowrap">
                                        {{ col.label }}
                                    </th>
                                    <th class="px-2 py-2 text-left font-medium text-gray-600">部署</th>
                                    <th class="w-24 px-2 py-2 text-left font-medium text-gray-600">操作</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr
                                    v-for="(item, idx) in [...(editStates[section.type]?.items ?? [])].sort((a,b) => (a[SORT_KEY_BY_TYPE[section.type]] ?? 0) - (b[SORT_KEY_BY_TYPE[section.type]] ?? 0))"
                                    :key="item.id ?? 'new-' + idx"
                                    :class="item._deleted ? 'bg-red-50 opacity-60' : 'hover:bg-gray-50'"
                                >
                                    <td class="px-2 py-1.5">
                                        <div class="flex flex-col gap-0.5">
                                            <button type="button" :disabled="idx === 0 || !!item._deleted" class="h-5 w-5 text-xs text-gray-500 hover:bg-gray-200 rounded disabled:opacity-30" @click="moveUp(section.type, item, [...(editStates[section.type]?.items ?? [])].sort((a,b) => (a[SORT_KEY_BY_TYPE[section.type]] ?? 0) - (b[SORT_KEY_BY_TYPE[section.type]] ?? 0)))">▲</button>
                                            <button type="button" :disabled="idx === (editStates[section.type]?.items ?? []).length - 1 || !!item._deleted" class="h-5 w-5 text-xs text-gray-500 hover:bg-gray-200 rounded disabled:opacity-30" @click="moveDown(section.type, item, [...(editStates[section.type]?.items ?? [])].sort((a,b) => (a[SORT_KEY_BY_TYPE[section.type]] ?? 0) - (b[SORT_KEY_BY_TYPE[section.type]] ?? 0)))">▼</button>
                                        </div>
                                    </td>
                                    <!-- 名前列 -->
                                    <td class="px-2 py-1.5">
                                        <input
                                            v-model="item['name']"
                                            type="text"
                                            :disabled="!!item._deleted"
                                            placeholder="名前"
                                            class="w-full rounded border px-2 py-1 text-sm focus:outline-none disabled:bg-gray-100"
                                            :class="fieldError(section.type, item, 'name') ? 'border-red-400' : 'border-gray-300 focus:border-blue-400'"
                                        />
                                    </td>
                                    <!-- 名前以外の列 -->
                                    <td v-for="col in COLUMNS_BY_TYPE[section.type].filter(c => c.key !== 'name')" :key="col.key" class="px-2 py-1.5">
                                        <input
                                            v-model="item[col.key]"
                                            :type="col.inputType"
                                            :disabled="!!item._deleted"
                                            :placeholder="col.label"
                                            class="rounded border px-2 py-1 text-sm focus:outline-none disabled:bg-gray-100"
                                            :class="[col.inputType === 'number' ? 'w-20' : 'w-32', fieldError(section.type, item, col.key) ? 'border-red-400' : 'border-gray-300 focus:border-blue-400']"
                                        />
                                    </td>
                                    <!-- 部署トグル / 追加先セレクト -->
                                    <td class="px-2 py-1.5">
                                        <!-- 新規行: 追加先セレクト -->
                                        <template v-if="item._new">
                                            <select v-model="item._targetScope" class="rounded border border-gray-300 px-1.5 py-0.5 text-xs focus:border-blue-400 focus:outline-none">
                                                <option value="company">会社全体</option>
                                                <option v-for="d in departments" :key="d.id" :value="String(d.id)">{{ d.name }}</option>
                                            </select>
                                        </template>
                                        <!-- 既存行: 部署トグルボタン -->
                                        <template v-else-if="departments.length && currentScope === 'company'">
                                            <div class="flex flex-wrap gap-1">
                                                <button
                                                    v-for="d in departments"
                                                    :key="d.id"
                                                    type="button"
                                                    :disabled="!!item._deleted"
                                                    :class="['rounded-full px-2 py-0.5 text-xs font-medium border transition-colors whitespace-nowrap',
                                                        item._deptToggles?.[d.id]
                                                            ? 'bg-blue-100 text-blue-700 border-blue-400 hover:bg-blue-200'
                                                            : 'bg-gray-100 text-gray-400 border-gray-300 hover:bg-gray-200']"
                                                    @click="item._deptToggles[d.id] = !item._deptToggles?.[d.id]"
                                                >
                                                    {{ d.name }}{{ item._deptToggles?.[d.id] ? ' ●' : ' ○' }}
                                                </button>
                                            </div>
                                        </template>
                                    </td>
                                    <td class="min-w-[5rem] px-2 py-1.5">
                                        <button v-if="!item._deleted" type="button" class="whitespace-nowrap text-sm font-medium text-red-500 hover:underline" @click="markDelete(section.type, item)">－ 削除</button>
                                        <button v-else type="button" class="whitespace-nowrap text-sm font-medium text-green-600 hover:underline" @click="undoDelete(section.type, item)">元に戻す</button>
                                    </td>
                                </tr>
                                <tr v-if="!(editStates[section.type]?.items ?? []).length">
                                    <td :colspan="COLUMNS_BY_TYPE[section.type].length + 3" class="px-2 py-4 text-center text-gray-400">登録がありません</td>
                                </tr>
                            </tbody>
                        </table>
                    </template>

                    <!-- ■ グループ化タイプ ■ -->
                    <template v-else>
                        <div v-for="(grp, grpIdx) in getGroupedSections(section.type)" :key="grp.keyStr" class="mb-4">
                            <!-- グループヘッダー -->
                            <div class="flex items-center gap-1 rounded bg-indigo-50 px-3 py-1.5 mb-1">
                                <!-- グループ並べ替え -->
                                <div class="flex flex-col gap-0.5 mr-1">
                                    <button type="button" :disabled="grpIdx === 0" class="h-4 w-4 text-xs text-indigo-400 hover:bg-indigo-100 rounded disabled:opacity-30" @click="moveGroupUp(section.type, grpIdx)">▲</button>
                                    <button type="button" :disabled="grpIdx === getGroupedSections(section.type).length - 1" class="h-4 w-4 text-xs text-indigo-400 hover:bg-indigo-100 rounded disabled:opacity-30" @click="moveGroupDown(section.type, grpIdx)">▼</button>
                                </div>

                                <!-- グループ名（インライン編集） -->
                                <template v-if="groupRenameState.type === section.type && (groupRenameState.groupKey ?? null) === (grp.key ?? null) && grp.key !== null">
                                    <input
                                        v-model="groupRenameState.input"
                                        type="text"
                                        class="flex-1 rounded border border-indigo-300 px-2 py-0.5 text-xs focus:outline-none"
                                        @keydown.enter.prevent="confirmRenameGroup(section.type, grp.key)"
                                        @keydown.escape="groupRenameState.type = null"
                                    />
                                    <button type="button" class="rounded bg-indigo-600 px-2 py-0.5 text-xs text-white" @click="confirmRenameGroup(section.type, grp.key)">確定</button>
                                    <button type="button" class="rounded border px-2 py-0.5 text-xs text-gray-600" @click="groupRenameState.type = null">キャンセル</button>
                                </template>
                                <template v-else>
                                    <span class="flex-1 text-xs font-semibold uppercase tracking-wide text-indigo-700">{{ grp.label }}</span>
                                    <button v-if="grp.key !== null" type="button" class="rounded px-1.5 py-0.5 text-xs text-indigo-500 hover:bg-indigo-100" @click="startRenameGroup(section.type, grp.key)">✎名前変更</button>
                                    <button
                                        type="button"
                                        :disabled="isGroupInUse(section.type, grp.key)"
                                        class="rounded px-1.5 py-0.5 text-xs text-red-500 hover:bg-red-50 disabled:text-gray-300 disabled:cursor-not-allowed"
                                        :title="isGroupInUse(section.type, grp.key) ? '項目があるため削除できません' : ''"
                                        @click="deleteGroup(section.type, grp.key)"
                                    >✕削除</button>
                                </template>
                                <!-- 追加ボタン: 会社全体 + 部署ごと -->
                                <div class="ml-2 flex flex-wrap gap-1">
                                    <button type="button" class="rounded border border-blue-500 px-2 py-0.5 text-xs text-blue-600 hover:bg-blue-50 whitespace-nowrap" @click="addRow(section.type, grp.key, 'company')">＋会社全体</button>
                                    <button
                                        v-for="d in departments"
                                        :key="d.id"
                                        type="button"
                                        class="rounded border border-indigo-400 px-2 py-0.5 text-xs text-indigo-600 hover:bg-indigo-50 whitespace-nowrap"
                                        @click="addRow(section.type, grp.key, String(d.id))"
                                    >＋{{ d.name }}</button>
                                </div>
                            </div>

                            <!-- グループ内アイテム -->
                            <table class="min-w-full divide-y divide-gray-100 text-sm">
                                <tbody class="divide-y divide-gray-100">
                                    <tr
                                        v-for="(item, idx) in grp.items"
                                        :key="item.id ?? 'new-' + grp.keyStr + '-' + idx"
                                        :class="item._deleted ? 'bg-red-50 opacity-60' : 'hover:bg-gray-50'"
                                    >
                                        <td class="w-14 px-2 py-1.5">
                                            <div class="flex flex-col gap-0.5">
                                                <button type="button" :disabled="idx === 0 || !!item._deleted" class="h-5 w-5 text-xs text-gray-500 hover:bg-gray-200 rounded disabled:opacity-30" @click="moveUp(section.type, item, grp.items)">▲</button>
                                                <button type="button" :disabled="idx === grp.items.length - 1 || !!item._deleted" class="h-5 w-5 text-xs text-gray-500 hover:bg-gray-200 rounded disabled:opacity-30" @click="moveDown(section.type, item, grp.items)">▼</button>
                                            </div>
                                        </td>
                                        <!-- 名前列 -->
                                        <td class="px-2 py-1.5">
                                            <input
                                                v-model="item['name']"
                                                type="text"
                                                :disabled="!!item._deleted"
                                                placeholder="名前"
                                                class="w-full rounded border px-2 py-1 text-sm focus:outline-none disabled:bg-gray-100"
                                                :class="fieldError(section.type, item, 'name') ? 'border-red-400' : 'border-gray-300 focus:border-blue-400'"
                                            />
                                        </td>
                                        <!-- 名前以外の列 -->
                                        <td v-for="col in COLUMNS_BY_TYPE[section.type].filter(c => c.key !== 'name')" :key="col.key" class="px-2 py-1.5">
                                            <input
                                                v-model="item[col.key]"
                                                :type="col.inputType"
                                                :disabled="!!item._deleted"
                                                :placeholder="col.label"
                                                class="rounded border px-2 py-1 text-sm focus:outline-none disabled:bg-gray-100"
                                                :class="[col.inputType === 'number' ? 'w-20' : 'w-32', fieldError(section.type, item, col.key) ? 'border-red-400' : 'border-gray-300 focus:border-blue-400']"
                                            />
                                        </td>
                                        <!-- 部署トグル / 追加先セレクト -->
                                        <td class="px-2 py-1.5">
                                            <!-- 新規行: 追加先セレクト -->
                                            <template v-if="item._new">
                                                <select v-model="item._targetScope" class="rounded border border-gray-300 px-1.5 py-0.5 text-xs focus:border-blue-400 focus:outline-none">
                                                    <option value="company">会社全体</option>
                                                    <option v-for="d in departments" :key="d.id" :value="String(d.id)">{{ d.name }}</option>
                                                </select>
                                            </template>
                                            <!-- 既存行: 部署トグルボタン（company-wide スコープのみ） -->
                                            <template v-else-if="departments.length && currentScope === 'company'">
                                                <div class="flex flex-wrap gap-1">
                                                    <button
                                                        v-for="d in departments"
                                                        :key="d.id"
                                                        type="button"
                                                        :disabled="!!item._deleted"
                                                        :class="['rounded px-2.5 py-1 text-xs font-medium border transition-colors whitespace-nowrap',
                                                            item._deptToggles?.[d.id]
                                                                ? 'bg-blue-600 text-white border-blue-600 hover:bg-blue-700'
                                                                : 'bg-white text-gray-400 border-gray-300 hover:bg-gray-50 hover:text-gray-600']"
                                                        @click="item._deptToggles[d.id] = !item._deptToggles?.[d.id]"
                                                    >
                                                        {{ d.name }}
                                                    </button>
                                                </div>
                                            </template>
                                        </td>
                                        <td class="min-w-[5rem] px-2 py-1.5">
                                            <button v-if="!item._deleted" type="button" class="whitespace-nowrap text-sm font-medium text-red-500 hover:underline" @click="markDelete(section.type, item)">－ 削除</button>
                                            <button v-else type="button" class="whitespace-nowrap text-sm font-medium text-green-600 hover:underline" @click="undoDelete(section.type, item)">元に戻す</button>
                                        </td>
                                    </tr>
                                    <tr v-if="grp.items.length === 0">
                                        <td :colspan="COLUMNS_BY_TYPE[section.type].length + 4" class="px-2 py-2 text-center text-xs text-gray-400">このグループに項目がありません</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <p v-if="!(editStates[section.type]?.groupConfig?.groups ?? []).length" class="text-sm text-gray-400 py-2">グループがありません。「＋ グループ追加」で追加してください。</p>
                    </template>
                </div>
            </div>
        </div>

        <!-- グループ追加モーダル -->
        <div
            v-if="groupAddModal.show"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
            @click.self="groupAddModal.show = false"
        >
            <div class="mx-4 w-full max-w-sm rounded-lg bg-white shadow-xl">
                <div class="border-b px-6 py-4">
                    <h2 class="text-lg font-semibold text-gray-800">新しいグループを追加</h2>
                </div>
                <div class="p-6">
                    <label class="mb-1 block text-sm font-medium text-gray-700">グループ名 <span class="text-red-500">*</span></label>
                    <input
                        v-model="groupAddModal.name"
                        type="text"
                        class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none"
                        placeholder="例：特殊処理"
                        @keydown.enter.prevent="confirmAddGroup"
                    />
                    <p v-if="groupAddModal.error" class="mt-1 text-xs text-red-500">{{ groupAddModal.error }}</p>
                </div>
                <div class="flex justify-end gap-2 border-t px-6 py-4">
                    <button type="button" class="rounded bg-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-300" @click="groupAddModal.show = false">キャンセル</button>
                    <button type="button" class="rounded bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700" @click="confirmAddGroup">追加</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
