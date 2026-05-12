<template>
    <AppLayout :title="`ジョブ割り当て一覧 - ${projectJob.title}`">
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('coordinator.project_jobs.show', { projectJob: projectJob.id })"
                      class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 whitespace-nowrap hover:bg-gray-300"
                >← 案件詳細に戻る</Link>
                <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">ジョブ割り当て一覧</h2>
            </div>
        </template>

        <div class="rounded bg-white px-4 py-6 sm:p-6 shadow">

            <!-- 検索・フィルター行 -->
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div class="flex items-center gap-2">
                    <input
                        v-model="page.props.q_model"
                        @keyup.enter="search"
                        placeholder="タイトル/詳細/担当で検索"
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
                    <input type="checkbox" v-model="hideCompleted" @change="search" class="h-4 w-4 rounded border-gray-300" />
                    完了を表示しない
                </label>
            </div>

            <!-- グループ表示切替ボタン -->
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

                    <table class="w-full table-fixed border" style="min-width: 840px;">
                        <colgroup>
                            <col style="width: 130px"> <!-- 作成日 -->
                            <col>                      <!-- タイトル -->
                            <col style="width: 100px"> <!-- 担当 -->
                            <col style="width: 150px"> <!-- 終了希望日 -->
                            <col style="width: 100px"> <!-- 見積時間 -->
                            <col style="width: 100px">  <!-- ステータス -->
                        </colgroup>
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">作成日</th>
                                <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">タイトル</th>
                                <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">担当</th>
                                <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">終了希望日</th>
                                <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">見積時間</th>
                                <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">ステータス</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="a in group.items"
                                :key="a.id"
                                class="cursor-pointer hover:bg-gray-100"
                                @click.prevent="rowClick(a)"
                            >
                                <td class="border px-3 py-2 text-sm text-gray-600">{{ formatDate(a.created_at) }}</td>
                                <td class="break-words border px-3 py-2 text-sm">
                                    <span v-if="a.proof_completed_at" class="mr-1 inline-flex items-center rounded-full bg-green-100 px-1.5 py-0.5 text-xs font-medium text-green-700">校正済</span>{{ a.title }}
                                </td>
                                <td class="break-words border px-3 py-2 text-sm text-gray-700">
                                    <span>{{ a.user?.name || '-' }}</span>
                                    <span
                                        v-if="a.user?.employment_type && a.user.employment_type !== 'regular'"
                                        class="ml-1 inline-block rounded-full px-1.5 py-0 text-xs"
                                        :class="{
                                            'bg-orange-100 text-orange-700': a.user.employment_type === 'dispatch',
                                            'bg-purple-100 text-purple-700': a.user.employment_type === 'outsource',
                                            'bg-green-100 text-green-700': a.user.employment_type === 'contract',
                                        }"
                                    >{{ a.user.employment_type_label }}</span>
                                </td>
                                <td class="border px-3 py-2 text-sm text-gray-600">
                                    {{ a.desired_end_date || '-' }}
                                    <span v-if="a.desired_time" class="ml-1">{{ formatTime(a.desired_time) }}</span>
                                </td>
                                <td class="border px-3 py-2 text-sm text-gray-600">{{ formatEstimatedHours(a.estimated_hours) }}</td>
                                <td class="border px-3 py-2">
                                    <span
                                        :class="statusBadgeClass(getStatus(a))"
                                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                    >{{ getStatus(a) }}</span>
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
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    projectJob:    Object,
    assignments:   Array,
    sort_by:       String,
    sort_dir:      String,
    q:             String,
    period:        String,
    hideCompleted: Boolean,
    monthOptions:  Array,
});

const page = usePage();
page.props.q_model      = props.q || '';
page.props.period_model = props.period || 'all';

const monthOptions   = computed(() => (Array.isArray(props.monthOptions) ? props.monthOptions : []));
const hideCompleted  = ref(props.hideCompleted ?? false);

// グループ表示モード
const viewMode  = ref('date');
const viewModes = [
    { key: 'date',   label: '日付ごと' },
    { key: 'user',   label: '担当ごと' },
    { key: 'project', label: '案件ごと' },
];

// ===== ユーティリティ =====

function formatDate(d) {
    if (!d) return '-';
    const s = String(d);
    if (s.includes('T')) return s.split('T')[0];
    if (s.includes(' ')) return s.split(' ')[0];
    return s;
}

function formatTime(t) {
    if (!t) return '';
    const core = String(t).split('.')[0];
    const parts = core.split(':');
    if (parts.length >= 2) return parts[0].padStart(2, '0') + ':' + parts[1].padStart(2, '0');
    return t;
}

function formatEstimatedHours(h) {
    if (h === null || h === undefined || h === '') return '-';
    const n = Number(h);
    if (Number.isNaN(n)) return '-';
    return `${n}h`;
}

function formatDateLabel(dateStr) {
    if (!dateStr) return '日付なし';
    try {
        const d = new Date(dateStr + 'T00:00:00');
        const y  = d.getFullYear();
        const mo = d.getMonth() + 1;
        const day = d.getDate();
        const dow = ['日','月','火','水','木','金','土'][d.getDay()];
        return `${y}年${mo}月${day}日（${dow}）`;
    } catch {
        return dateStr;
    }
}

function getDateKey(a) {
    return a.created_at ? String(a.created_at).split('T')[0] : '';
}

function getStatus(a) {
    const statusKey = a.status_model?.key ?? a.status?.key ?? null;
    // 優先順位: 完了 > セット済み > 確認済み > 未読
    if (statusKey === 'completed' || Boolean(a.completed)) return '完了';
    if (statusKey === 'scheduled' || Boolean(a.accepted) || Boolean(a.scheduled) || Boolean(a.scheduled_at)) return 'セット済み';
    if (statusKey === 'confirmed' || a.read_at) return '確認済み';
    return '未読';
}

function statusBadgeClass(status) {
    switch (status) {
        case '完了':     return 'bg-yellow-100 text-yellow-800';
        case 'セット済み': return 'bg-blue-100 text-blue-800';
        case '確認済み':  return 'bg-green-100 text-green-800';
        case '未読':     return 'bg-red-100 text-red-800';
        default:         return 'bg-gray-100 text-gray-700';
    }
}

// ===== グループ表示 =====

const localAssignments = ref(Array.isArray(props.assignments) ? [...props.assignments] : []);

function getGroupKey(a) {
    if (viewMode.value === 'user')    return a.user?.name || '担当なし';
    if (viewMode.value === 'project') return props.projectJob?.title || props.projectJob?.name || '案件';
    return getDateKey(a);
}

function getGroupLabel(key) {
    if (viewMode.value === 'date') return formatDateLabel(key);
    return key || '未設定';
}

const displayGroups = computed(() => {
    let items = Array.isArray(localAssignments.value) ? [...localAssignments.value] : [];

    if (hideCompleted.value) {
        items = items.filter((a) => getStatus(a) !== '完了');
    }

    const grouped = new Map();
    for (const a of items) {
        const key = getGroupKey(a);
        if (!grouped.has(key)) grouped.set(key, []);
        grouped.get(key).push(a);
    }

    const sortedKeys = Array.from(grouped.keys());
    if (viewMode.value === 'date') {
        sortedKeys.sort((a, b) => {
            if (!a) return 1;
            if (!b) return -1;
            return b.localeCompare(a);
        });
    } else {
        sortedKeys.sort((a, b) => a.localeCompare(b, 'ja'));
    }

    return sortedKeys.map((key) => ({
        key,
        label: getGroupLabel(key),
        items: grouped.get(key),
    }));
});

const totalDisplayCount  = computed(() => displayGroups.value.reduce((sum, g) => sum + g.items.length, 0));
const hiddenCompletedCount = computed(() => {
    if (!hideCompleted.value) return 0;
    return (Array.isArray(localAssignments.value) ? localAssignments.value : []).filter((a) => getStatus(a) === '完了').length;
});

// ===== アクション =====

function rowClick(a) {
    try {
        let url;
        try {
            url = route('coordinator.project_jobs.assignments.show', { projectJob: props.projectJob.id, assignment: a.id });
        } catch {
            const _base = (window.Ziggy && window.Ziggy.url) ? (() => { try { return new URL(window.Ziggy.url).pathname.replace(/\/$/, ''); } catch { return ''; } })() : '';
            url = `${_base}/coordinator/project_jobs/${props.projectJob.id}/assignments/${a.id}`;
        }
        router.visit(url, { preserveState: false });
    } catch {}
}

function search() {
    router.get(
        route('coordinator.project_jobs.assignments.index', { projectJob: props.projectJob.id }),
        {
            q:              page.props.q_model,
            period:         page.props.period_model !== 'all' ? page.props.period_model : '',
            hide_completed: hideCompleted.value ? '1' : '',
        },
        { preserveState: false },
    );
}

function clearSearch() {
    page.props.q_model = '';
    search();
}
</script>

<style scoped></style>
