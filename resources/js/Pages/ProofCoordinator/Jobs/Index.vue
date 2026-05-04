<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import ProofCoordinatorNavigationTabs from '@/Components/Tabs/ProofCoordinatorNavigationTabs.vue';
import { router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    activeRequests:    { type: Array,  default: () => [] },
    completedRequests: { type: Array,  default: () => [] },
    tab:               { type: String, default: 'active' },
    search:            { type: String, default: '' },
    period:            { type: String, default: '' },
    dateField:         { type: String, default: 'created_at' },
    monthOptions:      { type: Array,  default: () => [] },
});

const currentTab      = ref(props.tab);
const searchInput     = ref(props.search);
const periodInput     = ref(props.period);
const dateFieldInput  = ref(props.dateField);
const groupMode       = ref('deadline'); // 'project' | 'proofreader' | 'deadline'

const statusLabel = {
    assigned:    '割り当て済み',
    in_progress: '校正中',
    completed:   '完了',
};

const statusBadge = {
    assigned:    'bg-blue-100 text-blue-800',
    in_progress: 'bg-indigo-100 text-indigo-800',
    completed:   'bg-yellow-100 text-yellow-800',
};

const groupModeOptions = [
    { key: 'deadline',    label: '締め切りごと' },
    { key: 'project',     label: '案件ごと' },
    { key: 'proofreader', label: '校正員ごと' },
];

function fmtDeadline(isoStr) {
    if (!isoStr) return '—';
    const fmt = new Intl.DateTimeFormat('ja-JP', {
        timeZone: 'Asia/Tokyo',
        year: 'numeric', month: 'numeric', day: 'numeric',
        hour: '2-digit', minute: '2-digit', hour12: false,
    });
    const p = Object.fromEntries(fmt.formatToParts(new Date(isoStr)).map(({ type, value }) => [type, value]));
    return `${p.year}年${p.month}月${p.day}日 ${p.hour}時${p.minute}分`;
}

function fmtDate(isoStr) {
    if (!isoStr) return '—';
    return new Date(isoStr).toLocaleDateString('ja-JP');
}

function isOverdue(deadline) {
    return deadline && new Date(deadline) < new Date();
}

// グループ化（クライアントサイド）
const groupedRows = computed(() => {
    const rows = currentTab.value === 'active' ? props.activeRequests : props.completedRequests;

    const getKey = (req) => {
        if (groupMode.value === 'project')     return req.project_job?.title ?? '案件なし';
        if (groupMode.value === 'proofreader') return req.proofreader?.name  ?? '未割り当て';
        // deadline
        if (!req.deadline) return '締め切りなし';
        const d = new Date(req.deadline);
        const tz = new Intl.DateTimeFormat('ja-JP', { timeZone: 'Asia/Tokyo', year: 'numeric', month: 'numeric', day: 'numeric' });
        const parts = Object.fromEntries(tz.formatToParts(d).map(({ type, value }) => [type, value]));
        return `${parts.year}年${parts.month}月${parts.day}日`;
    };

    const map = new Map();
    for (const req of rows) {
        const key = getKey(req);
        if (!map.has(key)) map.set(key, []);
        map.get(key).push(req);
    }
    const keys = [...map.keys()].sort((a, b) => {
        // 締め切りごとは日付順、その他はアルファベット順
        if (groupMode.value === 'deadline') {
            if (a === '締め切りなし') return 1;
            if (b === '締め切りなし') return -1;
            // "YYYY年M月D日" → 比較用
            const toDate = (s) => {
                const m = s.match(/(\d+)年(\d+)月(\d+)日/);
                return m ? new Date(m[1], m[2] - 1, m[3]) : new Date(0);
            };
            return toDate(a) - toDate(b);
        }
        return a.localeCompare(b, 'ja');
    });
    return keys.map(key => ({ key, rows: map.get(key) }));
});

function doSearch() {
    router.get(route('proof_coordinator.jobs'), {
        tab:        currentTab.value,
        search:     searchInput.value,
        period:     periodInput.value,
        date_field: dateFieldInput.value,
    }, { preserveState: false, replace: true });
}

function clearFilters() {
    searchInput.value    = '';
    periodInput.value    = '';
    dateFieldInput.value = 'created_at';
    doSearch();
}

function switchTab(tab) {
    currentTab.value = tab;
    doSearch();
}

function goToShow(id) {
    router.get(route('proof_coordinator.assignments.show', { proofRequest: id }));
}

function complete(id) {
    if (!confirm('この校正を完了にしますか？依頼者に通知されます。')) return;
    router.put(route('proof_coordinator.assignments.complete', { proofRequest: id }), {}, {
        preserveScroll: true,
    });
}

function uncomplete(id) {
    if (!confirm('この校正を未完了（校正中）に戻しますか？')) return;
    router.put(route('proof_coordinator.assignments.uncomplete', { proofRequest: id }), {}, {
        preserveScroll: true,
    });
}
</script>

<template>
    <AppLayout title="ジョブ管理">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">ジョブ管理</h2>
        </template>

        <template #tabs>
            <ProofCoordinatorNavigationTabs active="jobs" />
        </template>

        <div class="rounded bg-white p-6 shadow">

            <!-- 検索・フィルター -->
            <div class="flex flex-col gap-3">
                <!-- テキスト検索 -->
                <div class="flex items-center gap-2">
                    <input
                        v-model="searchInput"
                        type="text"
                        placeholder="タイトル・案件名で検索"
                        class="w-72 rounded border-gray-300 text-sm"
                        @keyup.enter="doSearch"
                    />
                    <button
                        @click="doSearch"
                        class="rounded bg-pink-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-pink-700"
                    >検索</button>
                    <button
                        @click="clearFilters"
                        class="rounded border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50"
                    >クリア</button>
                </div>

                <!-- 日付フィールド選択 + 年月フィルター -->
                <div class="flex flex-wrap items-center gap-4">
                    <div class="flex items-center gap-3">
                        <label class="flex items-center gap-1 text-sm text-gray-700 cursor-pointer select-none">
                            <input
                                type="radio"
                                v-model="dateFieldInput"
                                value="created_at"
                                class="h-4 w-4 border-gray-300 text-pink-600"
                                @change="doSearch"
                            />
                            依頼日
                        </label>
                        <label class="flex items-center gap-1 text-sm text-gray-700 cursor-pointer select-none">
                            <input
                                type="radio"
                                v-model="dateFieldInput"
                                value="deadline"
                                class="h-4 w-4 border-gray-300 text-pink-600"
                                @change="doSearch"
                            />
                            締め切り日
                        </label>
                    </div>
                    <div class="flex items-center gap-2">
                        <label class="text-sm text-gray-700">年月:</label>
                        <select
                            v-model="periodInput"
                            @change="doSearch"
                            class="rounded border-gray-300 text-sm"
                            style="width: 9.5em"
                        >
                            <option value="">全期間</option>
                            <option v-for="m in monthOptions" :key="m.value" :value="m.value">
                                {{ m.label }}
                            </option>
                        </select>
                    </div>
                </div>

                <!-- グループ表示ピル -->
                <div class="flex items-center gap-1 rounded-lg border border-gray-200 bg-gray-50 p-1 w-fit">
                    <button
                        v-for="opt in groupModeOptions"
                        :key="opt.key"
                        @click="groupMode = opt.key"
                        :class="groupMode === opt.key
                            ? 'bg-white text-pink-700 font-semibold shadow-sm'
                            : 'text-gray-600 hover:text-gray-800'"
                        class="rounded px-3 py-1 text-sm transition-colors"
                    >
                        {{ opt.label }}
                    </button>
                </div>
            </div>

            <!-- タブ切り替え -->
            <div class="mt-5 flex border-b border-gray-200">
                <button
                    @click="switchTab('active')"
                    :class="currentTab === 'active'
                        ? 'border-b-2 border-pink-600 text-pink-700 font-semibold'
                        : 'text-gray-500 hover:text-gray-700'"
                    class="px-4 py-2 text-sm transition-colors"
                >
                    進行中のジョブ
                    <span
                        v-if="activeRequests.length > 0"
                        class="ml-1 rounded-full bg-pink-100 px-2 py-0.5 text-xs text-pink-700"
                    >{{ activeRequests.length }}</span>
                </button>
                <button
                    @click="switchTab('completed')"
                    :class="currentTab === 'completed'
                        ? 'border-b-2 border-pink-600 text-pink-700 font-semibold'
                        : 'text-gray-500 hover:text-gray-700'"
                    class="px-4 py-2 text-sm transition-colors"
                >
                    完了したジョブ
                </button>
            </div>

            <!-- 完了タブ：直近3か月注記 -->
            <p
                v-if="currentTab === 'completed' && !periodInput"
                class="mt-2 text-xs text-gray-400"
            >
                ※ 直近3か月を表示しています。年月を選択すると対象期間を変更できます。
            </p>

            <!-- データなし -->
            <p
                v-if="groupedRows.length === 0"
                class="mt-6 text-sm text-gray-500"
            >
                {{ currentTab === 'active' ? '進行中の校正ジョブはありません。' : '完了した校正ジョブはありません。' }}
            </p>

            <!-- テーブル -->
            <div v-else class="mt-4 overflow-x-auto">
                <template v-for="group in groupedRows" :key="group.key">
                    <table class="min-w-full divide-y divide-gray-200">
                        <!-- グループヘッダー -->
                        <thead>
                            <tr class="bg-pink-200">
                                <th colspan="9" class="px-4 py-2 text-left text-sm font-semibold text-pink-900">
                                    {{ group.key }}
                                    <span class="ml-2 text-xs font-normal text-gray-500">{{ group.rows.length }}件</span>
                                </th>
                            </tr>
                            <tr class="bg-gray-50">
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">依頼日</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">タイトル</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">案件</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">依頼者</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">校正員</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">締め切り</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">完了日</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">ステータス</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">操作</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr
                                v-for="req in group.rows"
                                :key="req.id"
                                class="cursor-pointer hover:bg-pink-50"
                                @click="goToShow(req.id)"
                            >
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">
                                    {{ fmtDate(req.created_at) }}
                                </td>
                                <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                    {{ req.title }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    {{ req.project_job?.title ?? '—' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">
                                    {{ req.requester?.name ?? '—' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">
                                    {{ req.proofreader?.name ?? '—' }}
                                </td>
                                <td
                                    class="whitespace-nowrap px-4 py-3 text-sm"
                                    :class="isOverdue(req.deadline) && currentTab === 'active' ? 'font-bold text-red-600' : 'text-gray-600'"
                                >
                                    {{ fmtDeadline(req.deadline) }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">
                                    {{ req.completed_at ? fmtDate(req.completed_at) : '—' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm">
                                    <span :class="['rounded px-2 py-1 text-xs font-medium', statusBadge[req.status]]">
                                        {{ statusLabel[req.status] }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm" @click.stop>
                                    <button
                                        v-if="currentTab === 'active'"
                                        @click.stop="complete(req.id)"
                                        class="rounded bg-green-600 px-2 py-1 text-xs font-medium text-white hover:bg-green-700"
                                    >
                                        完了にする
                                    </button>
                                    <button
                                        v-else
                                        @click.stop="uncomplete(req.id)"
                                        class="rounded border border-gray-300 bg-white px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50"
                                    >
                                        未完了に戻す
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </template>
            </div>

        </div>
    </AppLayout>
</template>
