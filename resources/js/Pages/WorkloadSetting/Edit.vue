<script setup>
import useToasts from '@/Composables/useToasts';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, toRaw } from 'vue';

const props = defineProps({
    type: { type: String, required: true },
    typeLabel: { type: String, required: true },
    items: { type: Array, default: () => [] },
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

const groupConfig = computed(() => groupConfigByType[props.type] ?? null);

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
const groupedSections = computed(() => {
    if (!groupConfig.value) return null;
    const { groups, labels } = groupConfig.value;
    return groups.map((key) => {
        const normalizedKey = key ?? null;
        const items = state.items
            .filter((i) => (i.group ?? null) === normalizedKey)
            .sort((a, b) => (sortKey.value ? (a[sortKey.value] ?? 0) - (b[sortKey.value] ?? 0) : 0));
        return {
            key: normalizedKey,
            keyStr: key !== null && key !== undefined ? String(key) : 'null',
            label: labels[key] ?? labels['null'] ?? 'グループなし',
            items,
        };
    });
});

// テーブルの colspan
const colSpan = computed(() => columns.value.length + 1 + (sortKey.value ? 1 : 0));

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

const { showToast, showValidationErrors } = useToasts();

function save() {
    router.post(
        route('workload_setting.store', { type: props.type }),
        { items: toRaw(state.items) },
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
                <!-- グループ化タイプは各グループヘッダーに「+ 追加」ボタンがあるため非表示 -->
                <button
                    v-if="!groupedSections"
                    type="button"
                    class="rounded border border-blue-600 px-4 py-2 text-sm text-blue-600 hover:bg-blue-50"
                    @click="addRow()"
                >
                    + 行を追加
                </button>
                <div v-else />
                <div class="flex gap-3">
                    <button type="button" class="rounded border px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" @click="revert">リセット</button>
                    <button type="button" class="rounded bg-blue-600 px-4 py-2 text-sm text-white hover:bg-blue-700" @click="save">保存する</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
