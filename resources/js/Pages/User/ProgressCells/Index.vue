<template>
    <AppLayout title="進行表担当一覧">
        <template #header>
            <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">進行表担当一覧</h2>
        </template>

        <template #tabs>
            <nav class="flex gap-1">
                <Link
                    :href="route('user.jobbox.index')"
                    class="rounded-t px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-900"
                >ジョブ一覧</Link>
                <span class="rounded-t bg-white px-4 py-2 text-sm font-semibold text-blue-700 border-b-2 border-blue-600">
                    進行表担当
                </span>
            </nav>
        </template>

        <div class="rounded bg-white px-4 py-6 sm:p-6 shadow">
            <!-- 検索・フィルター行 -->
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div class="flex items-center gap-2">
                    <input
                        v-model="searchQ"
                        @keyup.enter="() => {}"
                        placeholder="案件名/進行表/行/列/クライアントで検索"
                        class="w-full sm:w-72 rounded border px-3 py-2 text-sm"
                    />
                    <button class="ml-2 rounded border px-3 py-2 text-sm" @click="searchQ = ''">クリア</button>
                </div>
            </div>

            <!-- 月セレクター + 完了非表示チェック -->
            <div class="mt-3 flex flex-wrap items-center gap-4">
                <div class="flex items-center gap-2">
                    <label class="text-sm text-gray-700">年月:</label>
                    <select v-model="selectedMonth" class="rounded border px-3 py-2 text-sm" style="width: 9.5em">
                        <option value="all">全期間</option>
                        <option v-for="m in monthOptions" :key="m.value" :value="m.value">{{ m.label }}</option>
                    </select>
                </div>
                <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700 select-none">
                    <input type="checkbox" v-model="hideCompleted" class="h-4 w-4 rounded border-gray-300" />
                    完了を表示しない
                </label>
            </div>

            <!-- グループ表示切替ボタン -->
            <div class="mt-4 flex gap-1 rounded-lg border border-gray-200 bg-gray-50 p-1 w-fit">
                <button
                    v-for="mode in viewModes"
                    :key="mode.key"
                    @click="viewMode = mode.key"
                    :class="viewMode === mode.key ? 'bg-white text-blue-700 font-semibold shadow-sm' : 'text-gray-600 hover:text-gray-900'"
                    class="rounded px-4 py-1.5 text-sm transition-all"
                >{{ mode.label }}</button>
            </div>

            <!-- グループ表示 -->
            <div class="mt-4 overflow-x-auto">
                <div v-if="displayGroups.length === 0" class="py-8 text-center text-sm text-gray-400">
                    表示するデータがありません。
                </div>

                <template v-for="group in displayGroups" :key="group.key">
                    <div class="mt-4 rounded bg-gray-100 px-4 py-1.5 text-sm font-semibold text-gray-700 first:mt-0">
                        {{ group.label }}
                        <span class="ml-2 text-xs font-normal text-gray-500">{{ group.items.length }} 件</span>
                    </div>

                    <table class="w-full border text-sm" style="min-width: 800px;">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="border px-3 py-2 text-left font-medium text-gray-500">クライアント</th>
                                <th class="border px-3 py-2 text-left font-medium text-gray-500">案件名</th>
                                <th class="border px-3 py-2 text-left font-medium text-gray-500">進行表</th>
                                <th class="border px-3 py-2 text-left font-medium text-gray-500">行</th>
                                <th class="border px-3 py-2 text-left font-medium text-gray-500">列</th>
                                <th class="border px-3 py-2 text-left font-medium text-gray-500">締め切り</th>
                                <th class="border px-3 py-2 text-left font-medium text-gray-500">状況</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="cell in group.items" :key="cell.id" :class="rowClass(cell)">
                                <td class="border px-3 py-2 text-gray-600">{{ cell.client_name }}</td>
                                <td class="border px-3 py-2 text-gray-800">{{ cell.project_job_title }}</td>
                                <td class="border px-3 py-2">
                                    <Link
                                        v-if="cell.sheet_id"
                                        :href="route('user.progress_sheets.show', { sheet: cell.sheet_id })"
                                        class="text-blue-600 hover:underline"
                                    >{{ cell.sheet_name }}</Link>
                                    <span v-else class="text-gray-500">{{ cell.sheet_name }}</span>
                                </td>
                                <td class="border px-3 py-2 text-gray-700">{{ cell.row_label }}</td>
                                <td class="border px-3 py-2 text-gray-700">{{ cell.col_label }}</td>
                                <td class="border px-3 py-2 text-gray-600">{{ formatDeadline(cell.deadline) }}</td>
                                <td class="border px-3 py-2">
                                    <span v-if="cell.completed_at" class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">完了</span>
                                    <span v-else class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">未完了</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </template>
            </div>

            <!-- 件数 -->
            <div class="mt-4 text-sm text-gray-600">
                表示中 {{ totalCount }} 件
                <span v-if="hideCompleted && hiddenCount > 0" class="ml-2 text-xs text-gray-400">（完了 {{ hiddenCount }} 件を非表示）</span>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({ cells: Array });

const searchQ = ref('');
const selectedMonth = ref('all');
const hideCompleted = ref(true);
const viewMode = ref('deadline');

const viewModes = [
    { key: 'deadline', label: '締め切りごと' },
    { key: 'client',   label: 'クライアントごと' },
    { key: 'project',  label: '案件ごと' },
];

const today = new Date();
today.setHours(0, 0, 0, 0);

// 年月セレクターの選択肢（deadlineがある月のみ）
const monthOptions = computed(() => {
    const months = new Set();
    for (const c of props.cells ?? []) {
        if (c.deadline) months.add(c.deadline.slice(0, 7));
    }
    return Array.from(months).sort().reverse().map((m) => {
        const [y, mo] = m.split('-');
        return { value: m, label: `${y}年${parseInt(mo)}月` };
    });
});

function matchesSearch(cell) {
    if (!searchQ.value) return true;
    const q = searchQ.value.toLowerCase();
    return [cell.client_name, cell.project_job_title, cell.sheet_name, cell.row_label, cell.col_label]
        .some((v) => v && v.toLowerCase().includes(q));
}

function matchesPeriod(cell) {
    if (selectedMonth.value === 'all') return true;
    return cell.deadline && cell.deadline.startsWith(selectedMonth.value);
}

const filteredCells = computed(() => {
    return (props.cells ?? []).filter((c) => {
        if (hideCompleted.value && c.completed_at) return false;
        if (!matchesSearch(c)) return false;
        if (!matchesPeriod(c)) return false;
        return true;
    });
});

function getGroupKey(cell) {
    if (viewMode.value === 'client')  return cell.client_name || '未設定';
    if (viewMode.value === 'project') return cell.project_job_title || '未設定';
    return cell.deadline ? cell.deadline.slice(0, 7) : '期日なし';
}

function getGroupLabel(key) {
    if (viewMode.value === 'deadline') {
        if (key === '期日なし') return '期日なし';
        const [y, mo] = key.split('-');
        return `${y}年${parseInt(mo)}月`;
    }
    return key || '未設定';
}

const displayGroups = computed(() => {
    const grouped = new Map();
    for (const c of filteredCells.value) {
        const key = getGroupKey(c);
        if (!grouped.has(key)) grouped.set(key, []);
        grouped.get(key).push(c);
    }

    // グループ内ソート（締め切り昇順）
    for (const items of grouped.values()) {
        items.sort((a, b) => (a.deadline ?? '9999').localeCompare(b.deadline ?? '9999'));
    }

    const sortedKeys = Array.from(grouped.keys());
    if (viewMode.value === 'deadline') {
        // 年月降順、期日なしは末尾
        sortedKeys.sort((a, b) => {
            if (a === '期日なし') return 1;
            if (b === '期日なし') return -1;
            return b.localeCompare(a);
        });
    } else {
        sortedKeys.sort((a, b) => a.localeCompare(b, 'ja'));
    }

    return sortedKeys.map((key) => ({ key, label: getGroupLabel(key), items: grouped.get(key) }));
});

const totalCount = computed(() => displayGroups.value.reduce((s, g) => s + g.items.length, 0));

const hiddenCount = computed(() => {
    if (!hideCompleted.value) return 0;
    return (props.cells ?? []).filter((c) => c.completed_at && matchesSearch(c) && matchesPeriod(c)).length;
});

function formatDeadline(dateStr) {
    if (!dateStr) return '-';
    const [y, mo, d] = dateStr.split('-');
    return `${y}/${mo}/${d}`;
}

function rowClass(cell) {
    if (cell.completed_at) return 'bg-green-50 border-l-4 border-l-green-400';
    if (!cell.deadline) return '';
    const dl = new Date(cell.deadline + 'T00:00:00');
    if (today > dl) return 'bg-red-50 border-l-4 border-l-red-400';
    if (Math.floor((dl - today) / 86400000) <= 3) return 'bg-yellow-50 border-l-4 border-l-yellow-400';
    return '';
}
</script>
