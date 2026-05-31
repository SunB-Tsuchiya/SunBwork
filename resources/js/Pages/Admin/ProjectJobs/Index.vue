<template>
    <AppLayout title="案件総覧">
        <template #header>
            <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">【Admin】案件総覧</h2>
        </template>

        <template #tabs>
            <AdminNavigationTabs active="project_jobs" />
        </template>

        <!-- SuperAdmin グローバルモード警告 -->
        <div v-if="props.noCompanySelected" class="rounded border border-yellow-300 bg-yellow-50 p-6 shadow">
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

        <div v-else class="rounded bg-white px-4 py-6 sm:p-6 shadow">

            <!-- 部署切り替えボタン -->
            <div class="mb-4 flex flex-wrap gap-2">
                <button
                    v-for="dept in departments"
                    :key="dept.id"
                    type="button"
                    :class="selectedDepartment === dept.id
                        ? 'bg-red-600 text-white'
                        : 'border border-red-300 bg-white text-red-600 hover:bg-red-50'"
                    class="rounded px-4 py-1.5 text-sm font-medium transition-colors"
                    @click="switchDepartment(dept.id)"
                >{{ dept.name }}</button>
            </div>

            <!-- 検索・フィルター行 -->
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div class="flex items-center gap-2">
                    <input
                        v-model="qModel"
                        @keyup.enter="search"
                        placeholder="案件名/クライアント/伝票番号で検索"
                        class="w-80 rounded border px-3 py-2 text-sm"
                    />
                    <button class="rounded bg-red-600 px-3 py-2 text-sm text-white hover:bg-red-700" @click.prevent="search">検索</button>
                    <button class="ml-2 rounded border px-3 py-2 text-sm" @click.prevent="clearSearch">クリア</button>
                </div>
            </div>

            <!-- 月セレクター + 完了非表示チェック -->
            <div class="mt-3 flex flex-wrap items-center gap-4">
                <div class="flex items-center gap-2">
                    <label class="text-sm text-gray-700">年月:</label>
                    <select
                        v-model="periodModel"
                        @change="search"
                        class="rounded border px-3 py-2 text-sm"
                        style="width: 9.5em"
                    >
                        <option value="all">全期間</option>
                        <option v-for="mo in monthOptions" :key="mo.value" :value="mo.value">
                            {{ mo.label }}
                        </option>
                    </select>
                </div>
                <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700 select-none">
                    <input type="checkbox" v-model="hideCompleted" class="h-4 w-4 rounded border-gray-300" />
                    完了を表示しない
                </label>
            </div>

            <!-- グループ表示切替タブ -->
            <div class="mt-4 flex gap-1 rounded-lg border border-gray-200 bg-gray-50 p-1 w-fit">
                <button
                    v-for="mode in viewModes"
                    :key="mode.key"
                    @click="viewMode = mode.key"
                    :class="viewMode === mode.key
                        ? 'bg-white text-red-700 font-semibold shadow-sm'
                        : 'text-gray-600 hover:text-gray-900'"
                    class="rounded px-4 py-1.5 text-sm transition-all"
                >{{ mode.label }}</button>
            </div>

            <!-- グループ表示 -->
            <div class="mt-4 overflow-x-auto">
                <div v-if="displayGroups.length === 0" class="py-8 text-center text-sm text-gray-400">
                    表示するデータがありません。
                </div>

                <template v-for="group in displayGroups" :key="group.key">
                    <div class="mt-4 rounded bg-red-50 px-4 py-1.5 text-sm font-semibold text-red-800 first:mt-0">
                        {{ group.label }}
                        <span class="ml-2 text-xs font-normal text-red-400">{{ group.items.length }} 件</span>
                    </div>

                    <table class="w-full table-fixed border" style="min-width: 500px;">
                        <colgroup>
                            <col style="width: 60px">
                            <col style="width: 60px">
                            <col style="width: 160px">
                            <col style="width: 90px">
                            <col style="width: 60px">
                            <col style="width: 44px">
                        </colgroup>
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">伝票番号</th>
                                <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">
                                    <button class="flex items-center gap-1 hover:text-gray-800" @click="toggleSort('created_at')">
                                        登録日<span class="text-gray-400">{{ sortIndicator('created_at') }}</span>
                                    </button>
                                </th>
                                <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">案件名</th>
                                <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">
                                    <button class="flex items-center gap-1 hover:text-gray-800" @click="toggleSort('client')">
                                        クライアント<span class="text-gray-400">{{ sortIndicator('client') }}</span>
                                    </button>
                                </th>
                                <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">担当Co</th>
                                <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">
                                    <button class="flex items-center gap-1 hover:text-gray-800" @click="toggleSort('status')">
                                        ステータス<span class="text-gray-400">{{ sortIndicator('status') }}</span>
                                    </button>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="job in group.items"
                                :key="job.id"
                                class="cursor-pointer hover:bg-red-50"
                                @click="rowClick($event, job)"
                            >
                                <td class="break-words border px-3 py-2 text-sm text-gray-600">{{ job.jobcode || '-' }}</td>
                                <td class="break-words border px-3 py-2 text-sm text-gray-600">{{ formatDate(job.created_at) }}</td>
                                <td class="break-words border px-3 py-2 text-sm">{{ job.title || job.name }}</td>
                                <td class="break-words border px-3 py-2 text-sm text-gray-600">{{ job.client?.name || '-' }}</td>
                                <td class="break-words border px-3 py-2 text-sm text-gray-600">{{ job.user?.name || '-' }}</td>
                                <td class="border px-3 py-2">
                                    <span
                                        :class="job.completed ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-700'"
                                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                    >{{ job.completed ? '完了' : '進行中' }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </template>
            </div>

            <!-- 件数 -->
            <div class="mt-4 text-sm text-gray-600">
                表示中 {{ totalDisplayCount }} 件
                <span v-if="hideCompleted && hiddenCompletedCount > 0" class="ml-2 text-xs text-gray-400">（完了 {{ hiddenCompletedCount }} 件を非表示）</span>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import AdminNavigationTabs from '@/Components/Tabs/AdminNavigationTabs.vue';
import { useUIState } from '@/Composables/useUIState';
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    noCompanySelected:  { type: Boolean, default: false },
    jobs:               { type: Array,  default: () => [] },
    departments:        { type: Array,  default: () => [] },
    selectedDepartment: { type: Number, default: null },
    monthOptions:       { type: Array,  default: () => [] },
    q:                  { type: String, default: '' },
    period:             { type: String, default: '' },
});

const qModel      = ref(props.q || '');
const periodModel = ref(props.period ?? '');
const hideCompleted = useUIState('sbw_admin_pj_hide_completed', true);
const localJobs     = ref((props.jobs || []).map((j) => ({ ...j })));

const viewMode  = useUIState('sbw_admin_pj_view_mode', 'date');
const viewModes = [
    { key: 'date',    label: '日付ごと' },
    { key: 'client',  label: 'クライアントごと' },
    { key: 'project', label: '案件ごと' },
];

const sortKey = useUIState('sbw_admin_pj_sort_key', 'created_at');
const sortDir = useUIState('sbw_admin_pj_sort_dir', 'desc');

function toggleSort(key) {
    if (sortKey.value === key) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortKey.value = key;
        sortDir.value = key === 'created_at' ? 'desc' : 'asc';
    }
}

function sortIndicator(key) {
    if (sortKey.value !== key) return ' ⇕';
    return sortDir.value === 'asc' ? ' ↑' : ' ↓';
}

function sortJobs(jobs) {
    return [...jobs].sort((a, b) => {
        let va, vb;
        if (sortKey.value === 'created_at') {
            va = a.created_at || ''; vb = b.created_at || '';
        } else if (sortKey.value === 'client') {
            va = a.client?.name || ''; vb = b.client?.name || '';
        } else if (sortKey.value === 'status') {
            va = a.completed ? 1 : 0; vb = b.completed ? 1 : 0;
        }
        if (va < vb) return sortDir.value === 'asc' ? -1 : 1;
        if (va > vb) return sortDir.value === 'asc' ? 1 : -1;
        return 0;
    });
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    try { return String(dateStr).split('T')[0].split(' ')[0]; } catch { return String(dateStr); }
}

function getGroupKey(job) {
    if (viewMode.value === 'client')  return job.client?.name || '未設定';
    if (viewMode.value === 'project') return job.title || job.name || '未設定';
    return job.created_at ? String(job.created_at).slice(0, 7) : '';
}

function getGroupLabel(key) {
    if (viewMode.value === 'date') {
        if (!key) return '日付なし';
        const [y, m] = key.split('-');
        return `${y}年${parseInt(m)}月`;
    }
    return key || '未設定';
}

const displayGroups = computed(() => {
    let jobs = Array.isArray(localJobs.value) ? localJobs.value : [];
    if (hideCompleted.value) jobs = jobs.filter((j) => !j.completed);
    jobs = sortJobs(jobs);

    const grouped = new Map();
    for (const j of jobs) {
        const key = getGroupKey(j);
        if (!grouped.has(key)) grouped.set(key, []);
        grouped.get(key).push(j);
    }

    const sortedKeys = Array.from(grouped.keys());
    if (viewMode.value === 'date') {
        sortedKeys.sort((a, b) => { if (!a) return 1; if (!b) return -1; return b.localeCompare(a); });
    } else {
        sortedKeys.sort((a, b) => a.localeCompare(b, 'ja'));
    }

    return sortedKeys.map((key) => ({ key, label: getGroupLabel(key), items: grouped.get(key) }));
});

const totalDisplayCount  = computed(() => displayGroups.value.reduce((sum, g) => sum + g.items.length, 0));
const hiddenCompletedCount = computed(() => {
    if (!hideCompleted.value) return 0;
    return (Array.isArray(localJobs.value) ? localJobs.value : []).filter((j) => j.completed).length;
});

function rowClick(event, job) {
    if (event.target.closest('a, button')) return;
    router.visit(route('admin.project_jobs.show', { projectJob: job.id }));
}

function switchDepartment(deptId) {
    router.get(route('admin.project_jobs.index'), {
        department: deptId,
        q: qModel.value,
        period: periodModel.value,
    }, { preserveState: false });
}

function search() {
    router.get(route('admin.project_jobs.index'), {
        department: props.selectedDepartment,
        q: qModel.value,
        period: periodModel.value,
    }, { preserveState: false });
}

function clearSearch() {
    qModel.value = '';
    search();
}
</script>
