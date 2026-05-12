<template>
    <AppLayout title="案件一覧">
        <template #header>
            <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">案件一覧</h2>
        </template>

        <template #headerExtras>
            <div class="flex items-center gap-2">
                <Link :href="route('coordinator.project_jobs.bulk_create.index')"
                      class="rounded border border-green-600 px-4 py-2 text-sm text-green-700 hover:bg-green-50">
                    テンプレートから一括作成
                </Link>
                <Link :href="route('coordinator.project_jobs.create')"
                      class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    新規作成
                </Link>
            </div>
        </template>

        <div class="space-y-4">

            <!-- ★ お気に入り -->
            <div class="rounded bg-white shadow">
                <div class="flex items-center gap-2 border-b border-yellow-300 bg-yellow-200 px-4 py-3">
                    <span class="text-yellow-600 text-lg">★</span>
                    <span class="text-sm font-semibold text-yellow-900">お気に入り</span>
                    <span class="text-xs text-yellow-700">（完了案件含む・フィルター対象外）</span>
                </div>

                <div v-if="localFavoriteJobs.length === 0" class="px-4 py-6 text-center text-sm text-gray-400">
                    お気に入りの案件はありません。
                </div>

                <table v-else class="w-full table-fixed border">
                    <colgroup>
                        <col class="w-28" />
                        <col class="w-36" />
                        <col class="w-44" />
                        <col class="w-24" />
                        <col class="w-12" />
                    </colgroup>
                    <thead>
                        <tr class="bg-yellow-100">
                            <th class="border px-3 py-1.5 text-left text-xs font-medium text-yellow-900">登録日</th>
                            <th class="border px-3 py-1.5 text-left text-xs font-medium text-yellow-900">案件名</th>
                            <th class="border px-3 py-1.5 text-left text-xs font-medium text-yellow-900">クライアント名</th>
                            <th class="border px-3 py-1.5 text-left text-xs font-medium text-yellow-900">ステータス</th>
                            <th class="border px-3 py-1.5 text-center text-xs font-medium text-yellow-900">★</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="job in localFavoriteJobs" :key="job.id" class="cursor-pointer hover:bg-yellow-50" @click="rowClick($event, job)">
                            <td class="border px-3 py-2 text-sm text-gray-600">{{ formatDate(job.created_at) }}</td>
                            <td class="border px-3 py-2 text-sm font-medium text-gray-800 max-w-0 truncate" :title="job.title || job.name">{{ job.title || job.name }}</td>
                            <td class="border px-3 py-2 text-sm text-gray-600">{{ job.client?.name || '-' }}</td>
                            <td class="border px-3 py-2">
                                <span
                                    :class="job.completed ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-700'"
                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                >{{ job.completed ? '完了' : '進行中' }}</span>
                            </td>
                            <td class="border px-3 py-2 text-center" @click.stop>
                                <button
                                    @click="toggleFavorite(job, true)"
                                    class="text-lg leading-none transition-colors text-yellow-400 hover:text-yellow-300"
                                    title="お気に入り解除"
                                >★</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- 検索・一覧 -->
            <div class="rounded bg-white px-4 py-6 sm:p-6 shadow">
            <!-- 検索・フィルター行 -->
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div class="flex items-center gap-2">
                    <input
                        v-model="page.props.q_model"
                        @keyup.enter="search"
                        placeholder="案件名/クライアントで検索"
                        class="w-full sm:w-72 rounded border px-3 py-2 text-sm"
                    />
                    <button class="rounded bg-indigo-600 px-3 py-2 text-white" @click.prevent="search">検索</button>
                    <button class="ml-2 rounded border px-3 py-2" @click.prevent="clearSearch">クリア</button>
                </div>
            </div>

            <!-- 月セレクター + 完了非表示チェック -->
            <div class="mt-3 flex flex-wrap items-center gap-4">
                <div class="flex items-center gap-2">
                    <label class="text-sm text-gray-700">年月:</label>
                    <select
                        v-model="page.props.period_model"
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

            <!-- ビューモード切替ボタン -->
            <div class="mt-4 flex gap-1 rounded-lg border border-gray-200 bg-gray-50 p-1 w-fit">
                <button
                    v-for="mode in viewModes"
                    :key="mode.key"
                    @click="viewMode = mode.key"
                    :class="viewMode === mode.key
                        ? 'bg-white text-indigo-700 font-semibold shadow-sm'
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
                    <!-- 月ヘッダー -->
                    <div class="mt-4 rounded bg-gray-100 px-4 py-1.5 text-sm font-semibold text-gray-700 first:mt-0">
                        {{ group.label }}
                        <span class="ml-2 text-xs font-normal text-gray-500">{{ group.items.length }} 件</span>
                    </div>

                    <table class="w-full table-fixed border">
                        <colgroup>
                            <col class="w-28" />
                            <col class="w-36" />
                            <col class="w-44" />
                            <col class="w-24" />
                            <col class="w-12" />
                        </colgroup>
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
                                <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">
                                    <button class="flex items-center gap-1 hover:text-gray-800" @click="toggleSort('status')">
                                        ステータス<span class="text-gray-400">{{ sortIndicator('status') }}</span>
                                    </button>
                                </th>
                                <th class="border px-3 py-1.5 text-center text-xs font-medium text-gray-500">★</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="job in group.items" :key="job.id" class="cursor-pointer hover:bg-blue-50" @click="rowClick($event, job)">
                                <td class="border px-3 py-2 text-sm text-gray-600">{{ formatDate(job.created_at) }}</td>
                                <td class="border px-3 py-2 text-sm max-w-0 truncate" :title="job.title || job.name">{{ job.title || job.name }}</td>
                                <td class="border px-3 py-2 text-sm text-gray-600">{{ job.client?.name || '-' }}</td>
                                <td class="border px-3 py-2">
                                    <span
                                        :class="job.completed ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-700'"
                                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                    >{{ job.completed ? '完了' : '進行中' }}</span>
                                </td>
                                <td class="border px-3 py-2 text-center" @click.stop>
                                    <button
                                        @click="toggleFavorite(job)"
                                        class="text-lg leading-none transition-colors"
                                        :class="job.is_favorite ? 'text-yellow-400 hover:text-yellow-300' : 'text-gray-300 hover:text-yellow-400'"
                                        :title="job.is_favorite ? 'お気に入り解除' : 'お気に入りに追加'"
                                    >★</button>
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
            </div><!-- /検索・一覧 -->
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { useUIState } from '@/Composables/useUIState';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import axios from 'axios';

const props = defineProps({ jobs: Array, favoriteJobs: { type: Array, default: () => [] }, registerFlags: Array, jobid: [Number, String], monthOptions: Array, q: String, period: String });
const page = usePage();
page.props.q_model = props.q || '';
page.props.period_model = props.period || 'all';

const monthOptions = computed(() => (Array.isArray(props.monthOptions) ? props.monthOptions : []));
const hideCompleted = useUIState('sbw_coord_pj_hide_completed', true);

// ローカルコピー（完了ボタンで即時更新するため）
const localJobs = ref((props.jobs || []).map((j) => ({ ...j })));
const localFavoriteJobs = ref((props.favoriteJobs || []).map((j) => ({ ...j })));

async function toggleFavorite(job, isFavSection = false) {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    try {
        const res = await axios.post(
            route('coordinator.project_jobs.favorite', { projectJob: job.id }),
            {},
            { headers: { 'X-CSRF-TOKEN': csrf } },
        );
        const nowFav = res.data.is_favorite;

        const idx = localJobs.value.findIndex(j => j.id === job.id);
        if (idx !== -1) localJobs.value[idx].is_favorite = nowFav;

        if (nowFav) {
            if (!localFavoriteJobs.value.find(j => j.id === job.id)) {
                localFavoriteJobs.value.unshift({ ...job, is_favorite: true });
            }
        } else {
            localFavoriteJobs.value = localFavoriteJobs.value.filter(j => j.id !== job.id);
        }
    } catch (e) {
        console.error('お気に入り更新エラー', e);
    }
}

// ===== ビューモード =====

const viewModes = [
    { key: 'date', label: '日付ごと' },
    { key: 'client', label: 'クライアントごと' },
    { key: 'project', label: '案件ごと' },
];
const viewMode = useUIState('pj_index_view_mode', 'date');

// ===== ソート =====

const sortKey = useUIState('sbw_coord_pj_sort_key', 'created_at');
const sortDir = useUIState('sbw_coord_pj_sort_dir', 'desc');

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

    if (viewMode.value === 'client') {
        const grouped = new Map();
        for (const j of jobs) {
            const key = j.client?.name || '（クライアントなし）';
            if (!grouped.has(key)) grouped.set(key, []);
            grouped.get(key).push(j);
        }
        const sortedKeys = Array.from(grouped.keys()).sort((a, b) => a.localeCompare(b, 'ja'));
        return sortedKeys.map((k) => ({ key: k, label: k, items: grouped.get(k) }));
    }

    if (viewMode.value === 'project') {
        const sorted = [...jobs].sort((a, b) => (a.title || a.name || '').localeCompare(b.title || b.name || '', 'ja'));
        return [{ key: 'all', label: '全案件', items: sorted }];
    }

    // date モード（デフォルト）: 月グループ
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
        key: mk,
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
    router.visit(route('coordinator.project_jobs.show', { projectJob: job.id }));
}

// ===== 検索 =====

function search() {
    router.get(route('coordinator.project_jobs.index'), { q: page.props.q_model, period: page.props.period_model }, { preserveState: false });
}

function clearSearch() {
    page.props.q_model = '';
    search();
}

// ===== 登録後ナビゲーション =====

const registerFlags = props.registerFlags || [];
const latestJobId = props.jobid || (props.jobs?.length ? props.jobs[props.jobs.length - 1].id : null);

onMounted(() => {
    if (page.props.reload) {
        location.reload();
        return;
    }
    if (registerFlags.length && latestJobId) {
        if (registerFlags.includes('teammember') && registerFlags.includes('schedule')) {
            if (confirm('プロジェクト登録が完了しました。続いてメンバーを登録しますか？')) {
                router.visit(route('coordinator.project_team_members.create'));
            }
        } else if (registerFlags.includes('schedule')) {
            if (confirm('メンバー登録が完了しました。続いてスケジュールを登録しますか？')) {
                router.visit(route('coordinator.project_jobs.show', { projectJob: latestJobId }));
            }
        }
    }
});
</script>
