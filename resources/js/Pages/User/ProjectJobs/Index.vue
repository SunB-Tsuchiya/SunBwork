<template>
    <AppLayout title="案件確認">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">案件確認</h2>
        </template>

        <div class="rounded bg-white p-6 shadow">
            <UserNavigationTabs active="project_jobs" />

            <!-- 検索・フィルター行 -->
            <div class="flex flex-col gap-3 md:flex-row md:items-center">
                <div class="flex items-center gap-2">
                    <input
                        v-model="qModel"
                        @keyup.enter="search"
                        placeholder="案件名・クライアント名で検索"
                        class="w-72 rounded border px-3 py-2 text-sm"
                    />
                    <button class="rounded bg-blue-600 px-3 py-2 text-white text-sm" @click.prevent="search">検索</button>
                    <button class="ml-1 rounded border px-3 py-2 text-sm" @click.prevent="clearSearch">クリア</button>
                </div>
            </div>

            <!-- 月セレクター -->
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
            </div>

            <!-- グループ表示切替タブ -->
            <div class="mt-4 flex gap-1 rounded-lg border border-gray-200 bg-gray-50 p-1 w-fit">
                <button
                    v-for="mode in viewModes"
                    :key="mode.key"
                    @click="viewMode = mode.key"
                    :class="viewMode === mode.key
                        ? 'bg-white text-blue-700 font-semibold shadow-sm'
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
                    <!-- グループヘッダー -->
                    <div class="mt-4 rounded bg-gray-100 px-4 py-1.5 text-sm font-semibold text-gray-700 first:mt-0">
                        {{ group.label }}
                        <span class="ml-2 text-xs font-normal text-gray-500">{{ group.items.length }} 件</span>
                    </div>

                    <table class="w-full table-fixed border" style="min-width: 640px;">
                        <colgroup>
                            <col style="width: 120px"> <!-- 登録日 -->
                            <col>                      <!-- 案件名 -->
                            <col style="width: 160px"> <!-- クライアント名 -->
                            <col style="width: 100px"> <!-- ステータス -->
                        </colgroup>
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">
                                    登録日 ↓
                                </th>
                                <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">案件名</th>
                                <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">クライアント名</th>
                                <th
                                    class="cursor-pointer border px-3 py-1.5 text-left text-xs font-medium text-gray-500 hover:bg-gray-100 select-none"
                                    @click="toggleStatusSort"
                                >
                                    ステータス {{ statusSortIcon }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="job in group.items"
                                :key="job.id"
                                class="cursor-pointer hover:bg-blue-50"
                                @click="goShow(job)"
                                role="button"
                            >
                                <td class="border px-3 py-2 text-sm text-gray-600">{{ formatDate(job.created_at) }}</td>
                                <td class="break-words border px-3 py-2 text-sm font-medium text-gray-900">{{ job.title || job.name || '-' }}</td>
                                <td class="break-words border px-3 py-2 text-sm text-gray-600">{{ job.client?.name || '-' }}</td>
                                <td class="border px-3 py-2">
                                    <span :class="statusBadgeClass(job)" class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium">
                                        {{ statusLabel(job) }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </template>
            </div>

            <!-- 件数 -->
            <div class="mt-4 text-sm text-gray-600">
                表示中 {{ totalDisplayCount }} 件
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import UserNavigationTabs from '@/Components/Tabs/UserNavigationTabs.vue';
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps({
    jobs: { type: Array, default: () => [] },
    monthOptions: { type: Array, default: () => [] },
    q: { type: String, default: '' },
    period: { type: String, default: '' },
    sortStatus: { type: String, default: '' },
});

const page = usePage();
const qModel = ref(props.q || '');
const periodModel = ref(props.period || '');
const localSortStatus = ref(props.sortStatus || '');

// グループ表示モード
const viewMode = ref('date');
const viewModes = [
    { key: 'date',   label: '月日ごと' },
    { key: 'client', label: 'クライアントごと' },
];

// ステータスソートアイコン
const statusSortIcon = computed(() => {
    if (localSortStatus.value === 'asc') return '↑';
    if (localSortStatus.value === 'desc') return '↓';
    return '↕';
});

function toggleStatusSort() {
    if (localSortStatus.value === '') localSortStatus.value = 'asc';
    else if (localSortStatus.value === 'asc') localSortStatus.value = 'desc';
    else localSortStatus.value = '';
    search();
}

// ===== ユーティリティ =====

function formatDate(dateStr) {
    if (!dateStr) return '-';
    try {
        const s = String(dateStr).split('T')[0];
        const [y, mo, d] = s.split('-');
        return `${y}/${mo}/${d}`;
    } catch {
        return String(dateStr).split('T')[0];
    }
}

function getDateMonthKey(job) {
    if (!job.created_at) return '日付なし';
    try {
        const s = String(job.created_at).split('T')[0];
        const [y, mo] = s.split('-');
        return `${y}-${mo}`;
    } catch {
        return '日付なし';
    }
}

function formatMonthLabel(key) {
    if (!key || key === '日付なし') return '日付なし';
    const [y, mo] = key.split('-');
    return `${y}年${parseInt(mo)}月`;
}

function statusLabel(job) {
    return job.completed ? '完了' : '進行中';
}

function statusBadgeClass(job) {
    return job.completed
        ? 'bg-yellow-100 text-yellow-800'
        : 'bg-indigo-100 text-indigo-800';
}

// ===== グループ表示 =====

const displayGroups = computed(() => {
    const jobs = Array.isArray(props.jobs) ? [...props.jobs] : [];

    // ステータスソート（クライアントサイド補助ソート）
    if (localSortStatus.value === 'asc') {
        jobs.sort((a, b) => {
            const ca = a.completed ? 1 : 0;
            const cb = b.completed ? 1 : 0;
            if (ca !== cb) return ca - cb;
            return new Date(b.created_at) - new Date(a.created_at);
        });
    } else if (localSortStatus.value === 'desc') {
        jobs.sort((a, b) => {
            const ca = a.completed ? 1 : 0;
            const cb = b.completed ? 1 : 0;
            if (ca !== cb) return cb - ca;
            return new Date(b.created_at) - new Date(a.created_at);
        });
    }

    const grouped = new Map();
    for (const job of jobs) {
        const key = viewMode.value === 'client'
            ? (job.client?.name || '未設定')
            : getDateMonthKey(job);
        if (!grouped.has(key)) grouped.set(key, []);
        grouped.get(key).push(job);
    }

    let sortedKeys = Array.from(grouped.keys());
    if (viewMode.value === 'date') {
        sortedKeys.sort((a, b) => b.localeCompare(a)); // 降順
    } else {
        sortedKeys.sort((a, b) => a.localeCompare(b, 'ja'));
    }

    return sortedKeys.map((key) => ({
        key,
        label: viewMode.value === 'date' ? formatMonthLabel(key) : key,
        items: grouped.get(key),
    }));
});

const totalDisplayCount = computed(() =>
    displayGroups.value.reduce((sum, g) => sum + g.items.length, 0)
);

// ===== ナビゲーション =====

function search() {
    try {
        router.get(
            route('user.project_jobs.index'),
            { q: qModel.value, period: periodModel.value, sort_status: localSortStatus.value },
            { preserveState: false },
        );
    } catch {
        const params = new URLSearchParams();
        if (qModel.value) params.set('q', qModel.value);
        if (periodModel.value) params.set('period', periodModel.value);
        if (localSortStatus.value) params.set('sort_status', localSortStatus.value);
        window.location.href = '/user/project-jobs?' + params.toString();
    }
}

function clearSearch() {
    qModel.value = '';
    periodModel.value = '';
    localSortStatus.value = '';
    search();
}

function goShow(job) {
    try {
        router.visit(route('user.project_jobs.show', { projectJob: job.id }));
    } catch {
        window.location.href = `/user/project-jobs/${job.id}`;
    }
}
</script>
