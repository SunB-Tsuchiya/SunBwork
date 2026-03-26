<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import useToasts from '@/Composables/useToasts';
import { router, Link } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const props = defineProps({
    adminTitles:  { type: Array, default: () => [] },
    leaderTitles: { type: Array, default: () => [] },
});

const { addToast } = useToasts();

// ---- ステート ----
const state = reactive({
    adminRows: props.adminTitles.map(t => ({
        id: t.id, name: t.name, applicable_role: 'admin',
        sort_order: t.sort_order ?? 0, _deleted: false,
    })),
    leaderRows: props.leaderTitles.map(t => ({
        id: t.id, name: t.name, applicable_role: 'leader',
        sort_order: t.sort_order ?? 0, _deleted: false,
    })),
});

// ---- ソート済みビュー ----
const adminSorted  = computed(() => [...state.adminRows].sort((a, b) => a.sort_order - b.sort_order));
const leaderSorted = computed(() => [...state.leaderRows].sort((a, b) => a.sort_order - b.sort_order));

// ---- 並び替えヘルパー ----
function moveUp(item, sorted) {
    const idx = sorted.indexOf(item);
    if (idx <= 0) return;
    const prev = sorted[idx - 1];
    [item.sort_order, prev.sort_order] = [prev.sort_order, item.sort_order];
}
function moveDown(item, sorted) {
    const idx = sorted.indexOf(item);
    if (idx >= sorted.length - 1) return;
    const next = sorted[idx + 1];
    [item.sort_order, next.sort_order] = [next.sort_order, item.sort_order];
}

// ---- 追加 ----
function addRow(rows) {
    const maxOrder = rows.length ? Math.max(...rows.map(r => r.sort_order ?? 0)) : 0;
    const role = rows === state.adminRows ? 'admin' : 'leader';
    rows.push({ id: null, name: '', applicable_role: role, sort_order: maxOrder + 1, _deleted: false });
}

// ---- 保存 ----
const processing = ref(false);

function submit() {
    const allRows = [...state.adminRows, ...state.leaderRows];
    const deletedIds = allRows.filter(r => r._deleted && r.id).map(r => r.id);
    const titles = allRows
        .filter(r => !r._deleted)
        .map(r => ({ id: r.id || undefined, name: r.name, applicable_role: r.applicable_role, sort_order: r.sort_order }));

    for (const t of titles) {
        if (!t.name.trim()) {
            addToast('称号名を入力してください', 'error');
            return;
        }
    }

    processing.value = true;
    router.put(route('superadmin.position_titles.update'), { titles, deleted_ids: deletedIds }, {
        onError:  () => { addToast('保存に失敗しました', 'error'); processing.value = false; },
        onFinish: () => { processing.value = false; },
    });
}
</script>

<template>
    <AppLayout title="役職称号 編集">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">役職称号 編集</h2>
                <Link :href="route('superadmin.position_titles.index')" class="text-gray-600 hover:text-gray-900">
                    ← 一覧に戻る
                </Link>
            </div>
        </template>

        <div class="rounded bg-white p-6 shadow">
            <div class="mx-auto w-full md:w-1/2">

                <!-- Admin 用称号 -->
                <section class="mb-10">
                    <h3 class="mb-4 text-base font-semibold text-gray-700">Admin 用（社長・役員クラス）</h3>
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="w-20 px-3 py-2 text-left font-medium text-gray-600">順序</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-600">称号名</th>
                                <th class="w-16 px-3 py-2 text-left font-medium text-gray-600">操作</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr
                                v-for="(item, idx) in adminSorted"
                                :key="item.id ?? 'new-admin-' + idx"
                                :class="item._deleted ? 'bg-red-50 opacity-60' : 'hover:bg-gray-50'"
                            >
                                <td class="px-3 py-2">
                                    <div class="flex items-center gap-1">
                                        <span class="w-5 text-right text-gray-700">{{ idx + 1 }}</span>
                                        <div class="flex flex-col">
                                            <button
                                                type="button"
                                                :disabled="idx === 0 || item._deleted"
                                                class="flex h-5 w-5 items-center justify-center rounded text-gray-500 hover:bg-gray-200 disabled:cursor-not-allowed disabled:opacity-30"
                                                @click="moveUp(item, adminSorted)"
                                            >▲</button>
                                            <button
                                                type="button"
                                                :disabled="idx === adminSorted.length - 1 || item._deleted"
                                                class="flex h-5 w-5 items-center justify-center rounded text-gray-500 hover:bg-gray-200 disabled:cursor-not-allowed disabled:opacity-30"
                                                @click="moveDown(item, adminSorted)"
                                            >▼</button>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-2">
                                    <input
                                        v-model="item.name"
                                        type="text"
                                        :disabled="item._deleted"
                                        class="w-full rounded border border-gray-300 px-2 py-1 text-sm focus:border-yellow-500 focus:ring-yellow-500 disabled:bg-gray-100 disabled:text-gray-400"
                                        placeholder="称号名"
                                    />
                                </td>
                                <td class="whitespace-nowrap px-3 py-2">
                                    <button v-if="!item._deleted" type="button" class="text-red-600 hover:underline" @click="item.id ? (item._deleted = true) : state.adminRows.splice(state.adminRows.indexOf(item), 1)">削除</button>
                                    <button v-else type="button" class="text-green-600 hover:underline" @click="item._deleted = false">元に戻す</button>
                                </td>
                            </tr>
                            <tr v-if="state.adminRows.filter(r => !r._deleted).length === 0">
                                <td colspan="3" class="px-3 py-4 text-center text-gray-400">データがありません</td>
                            </tr>
                        </tbody>
                    </table>
                    <button
                        type="button"
                        @click="addRow(state.adminRows)"
                        class="mt-3 rounded border border-yellow-300 px-3 py-1 text-sm text-yellow-700 hover:bg-yellow-50"
                    >
                        + 追加
                    </button>
                </section>

                <!-- Leader 用称号 -->
                <section class="mb-6">
                    <h3 class="mb-4 text-base font-semibold text-gray-700">Leader 用（部長・課長クラス）</h3>
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="w-20 px-3 py-2 text-left font-medium text-gray-600">順序</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-600">称号名</th>
                                <th class="w-16 px-3 py-2 text-left font-medium text-gray-600">操作</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr
                                v-for="(item, idx) in leaderSorted"
                                :key="item.id ?? 'new-leader-' + idx"
                                :class="item._deleted ? 'bg-red-50 opacity-60' : 'hover:bg-gray-50'"
                            >
                                <td class="px-3 py-2">
                                    <div class="flex items-center gap-1">
                                        <span class="w-5 text-right text-gray-700">{{ idx + 1 }}</span>
                                        <div class="flex flex-col">
                                            <button
                                                type="button"
                                                :disabled="idx === 0 || item._deleted"
                                                class="flex h-5 w-5 items-center justify-center rounded text-gray-500 hover:bg-gray-200 disabled:cursor-not-allowed disabled:opacity-30"
                                                @click="moveUp(item, leaderSorted)"
                                            >▲</button>
                                            <button
                                                type="button"
                                                :disabled="idx === leaderSorted.length - 1 || item._deleted"
                                                class="flex h-5 w-5 items-center justify-center rounded text-gray-500 hover:bg-gray-200 disabled:cursor-not-allowed disabled:opacity-30"
                                                @click="moveDown(item, leaderSorted)"
                                            >▼</button>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-2">
                                    <input
                                        v-model="item.name"
                                        type="text"
                                        :disabled="item._deleted"
                                        class="w-full rounded border border-gray-300 px-2 py-1 text-sm focus:border-yellow-500 focus:ring-yellow-500 disabled:bg-gray-100 disabled:text-gray-400"
                                        placeholder="称号名"
                                    />
                                </td>
                                <td class="whitespace-nowrap px-3 py-2">
                                    <button v-if="!item._deleted" type="button" class="text-red-600 hover:underline" @click="item.id ? (item._deleted = true) : state.leaderRows.splice(state.leaderRows.indexOf(item), 1)">削除</button>
                                    <button v-else type="button" class="text-green-600 hover:underline" @click="item._deleted = false">元に戻す</button>
                                </td>
                            </tr>
                            <tr v-if="state.leaderRows.filter(r => !r._deleted).length === 0">
                                <td colspan="3" class="px-3 py-4 text-center text-gray-400">データがありません</td>
                            </tr>
                        </tbody>
                    </table>
                    <button
                        type="button"
                        @click="addRow(state.leaderRows)"
                        class="mt-3 rounded border border-yellow-300 px-3 py-1 text-sm text-yellow-700 hover:bg-yellow-50"
                    >
                        + 追加
                    </button>
                </section>

            </div><!-- /mx-auto w-full md:w-1/2 -->

            <!-- 保存ボタン -->
            <div class="flex items-center justify-end gap-3 border-t border-gray-200 pt-6">
                <Link
                    :href="route('superadmin.position_titles.index')"
                    class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                >
                    保存せずに戻る
                </Link>
                <button
                    type="button"
                    @click="submit"
                    :disabled="processing"
                    class="rounded bg-yellow-600 px-4 py-2 text-sm font-bold text-white hover:bg-yellow-700 disabled:opacity-50"
                >
                    保存
                </button>
            </div>
        </div>
    </AppLayout>
</template>
