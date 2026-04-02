<template>
    <AppLayout title="案件総覧">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">【リーダー】案件総覧</h2>
        </template>

        <div class="rounded bg-white p-6 shadow">
            <div class="mb-6">
                <h1 class="text-2xl font-bold">案件総覧</h1>
                <p class="mt-1 text-sm text-gray-500">部署内のすべての案件を表示しています（読み取り専用）</p>
            </div>

            <!-- 検索・フィルター行 -->
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div class="flex items-center gap-2">
                    <input
                        v-model="qModel"
                        @keyup.enter="search"
                        placeholder="案件名/クライアントで検索"
                        class="w-72 rounded border px-3 py-2 text-sm"
                    />
                    <button class="rounded bg-orange-600 px-3 py-2 text-sm text-white hover:bg-orange-700" @click.prevent="search">検索</button>
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
                        <option v-for="m in monthOptions" :key="m.value" :value="m.value">
                            {{ m.label }}
                        </option>
                    </select>
                </div>
                <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700 select-none">
                    <input type="checkbox" v-model="hideCompleted" class="h-4 w-4 rounded border-gray-300" />
                    完了を表示しない
                </label>
            </div>

            <!-- 月グループ表示 -->
            <div class="mt-4 overflow-x-auto">
                <div v-if="displayGroups.length === 0" class="py-8 text-center text-sm text-gray-400">
                    表示するデータがありません。
                </div>

                <template v-for="group in displayGroups" :key="group.month">
                    <!-- 月ヘッダー -->
                    <div class="mt-4 rounded bg-orange-50 px-4 py-1.5 text-sm font-semibold text-orange-800 first:mt-0">
                        {{ group.label }}
                        <span class="ml-2 text-xs font-normal text-orange-500">{{ group.items.length }} 件</span>
                    </div>

                    <table class="min-w-full divide-y divide-gray-200 border">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">
                                    <button class="flex items-center gap-1 hover:text-gray-800" @click="toggleSort('created_at')">
                                        登録日<span class="text-gray-400">{{ sortIndicator('created_at') }}</span>
                                    </button>
                                </th>
                                <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">案件名</th>
                                <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">
                                    <button class="flex items-center gap-1 hover:text-gray-800" @click="toggleSort('client')">
                                        クライアント名<span class="text-gray-400">{{ sortIndicator('client') }}</span>
                                    </button>
                                </th>
                                <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">担当Co</th>
                                <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">
                                    <button class="flex items-center gap-1 hover:text-gray-800" @click="toggleSort('status')">
                                        ステータス<span class="text-gray-400">{{ sortIndicator('status') }}</span>
                                    </button>
                                </th>
                                <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">詳細</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="job in group.items"
                                :key="job.id"
                                class="cursor-pointer hover:bg-orange-50"
                                @click="rowClick($event, job)"
                            >
                                <td class="border px-3 py-2 text-sm text-gray-600">{{ formatDate(job.created_at) }}</td>
                                <td class="border px-3 py-2 text-sm">{{ job.title || job.name }}</td>
                                <td class="border px-3 py-2 text-sm text-gray-600">{{ job.client?.name || '-' }}</td>
                                <td class="border px-3 py-2 text-sm text-gray-600">{{ job.user?.name || '-' }}</td>
                                <td class="border px-3 py-2">
                                    <span
                                        :class="job.completed ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-700'"
                                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                    >{{ job.completed ? '完了' : '進行中' }}</span>
                                </td>
                                <td class="border px-3 py-2">
                                    <Link
                                        :href="route('leader.project_jobs.show', { projectJob: job.id })"
                                        class="rounded bg-orange-100 px-2 py-1 text-xs text-orange-700 hover:bg-orange-200"
                                        @click.stop
                                    >詳細</Link>
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
import { Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    jobs: Array,
    monthOptions: Array,
    q: String,
    period: String,
});

const qModel = ref(props.q || '');
const periodModel = ref(props.period ?? '');
const hideCompleted = ref(true);

const monthOptions = computed(() => (Array.isArray(props.monthOptions) ? props.monthOptions : []));
const localJobs = ref((props.jobs || []).map((j) => ({ ...j })));

// ===== ソート =====

const sortKey = ref('created_at');
const sortDir = ref('desc');

function toggleSort(key) {
    if (sortKey.value === key) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortKey.value = key;
        sortDir.value = key === 'created_at' ? 'desc' : 'asc';
    }
}

function sortIndicator(key) {
    if (sortKey.value !== key) return ' ↕';
    return sortDir.value === 'asc' ? ' ↑' : ' ↓';
}

function sortJobs(jobs) {
    return [...jobs].sort((a, b) => {
        let va, vb;
        if (sortKey.value === 'created_at') {
            va = a.created_at || '';
            vb = b.created_at || '';
        } else if (sortKey.value === 'client') {
            va = a.client?.name || '';
            vb = b.client?.name || '';
        } else if (sortKey.value === 'status') {
            va = a.completed ? 1 : 0;
            vb = b.completed ? 1 : 0;
        }
        if (va < vb) return sortDir.value === 'asc' ? -1 : 1;
        if (va > vb) return sortDir.value === 'asc' ? 1 : -1;
        return 0;
    });
}

// ===== 月グループ =====

function formatDate(dateStr) {
    if (!dateStr) return '-';
    try {
        return String(dateStr).split('T')[0].split(' ')[0];
    } catch {
        return String(dateStr);
    }
}

function getMonthKey(job) {
    return job.created_at ? String(job.created_at).slice(0, 7) : '';
}

function formatMonthLabel(monthStr) {
    if (!monthStr) return '日付なし';
    const [y, m] = monthStr.split('-');
    return `${y}年${parseInt(m)}月`;
}

const displayGroups = computed(() => {
    let jobs = Array.isArray(localJobs.value) ? localJobs.value : [];

    if (hideCompleted.value) {
        jobs = jobs.filter((j) => !j.completed);
    }

    jobs = sortJobs(jobs);

    const grouped = new Map();
    for (const j of jobs) {
        const mk = getMonthKey(j);
        if (!grouped.has(mk)) grouped.set(mk, []);
        grouped.get(mk).push(j);
    }

    const sortedKeys = Array.from(grouped.keys()).sort((a, b) => {
        if (!a) return 1;
        if (!b) return -1;
        return b.localeCompare(a);
    });

    return sortedKeys.map((mk) => ({
        month: mk,
        label: formatMonthLabel(mk),
        items: grouped.get(mk),
    }));
});

const totalDisplayCount = computed(() => displayGroups.value.reduce((sum, g) => sum + g.items.length, 0));

const hiddenCompletedCount = computed(() => {
    if (!hideCompleted.value) return 0;
    return (Array.isArray(localJobs.value) ? localJobs.value : []).filter((j) => j.completed).length;
});

// ===== 行クリック =====

function rowClick(event, job) {
    if (event.target.closest('a, button')) return;
    router.visit(route('leader.project_jobs.show', { projectJob: job.id }));
}

// ===== 検索 =====

function search() {
    router.get(route('leader.project_jobs.index'), { q: qModel.value, period: periodModel.value }, { preserveState: false });
}

function clearSearch() {
    qModel.value = '';
    search();
}
</script>
