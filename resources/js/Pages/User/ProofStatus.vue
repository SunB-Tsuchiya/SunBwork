<script setup>
import UserNavigationTabs from '@/Components/Tabs/UserNavigationTabs.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useUIState } from '@/Composables/useUIState';
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    proofRequests: { type: Object, default: () => ({ data: [], links: [] }) },
    q:             { type: String, default: '' },
    hideCompleted: { type: Boolean, default: true },
    period:        { type: String, default: '' },
    clientId:      { type: Number, default: null },
    clients:       { type: Array, default: () => [] },
    monthOptions:  { type: Array, default: () => [] },
});

// ── ローカル状態 ──
const localQ      = ref(props.q ?? '');
const localPeriod = ref(props.period ?? '');
const localClient = ref(props.clientId ? String(props.clientId) : '');

// 検索実行
function search() {
    router.get(
        route('user.proof.status'),
        {
            q:              localQ.value || undefined,
            hide_completed: props.hideCompleted ? 1 : 0,
            period:         localPeriod.value || 'all',
            client_id:      localClient.value || undefined,
        },
        { preserveState: true, replace: true },
    );
}

function clearSearch() {
    localQ.value      = '';
    localPeriod.value = '';
    localClient.value = '';
    router.get(route('user.proof.status'), {}, { replace: true });
}

function toggleHideCompleted() {
    router.get(
        route('user.proof.status'),
        {
            q:              localQ.value || undefined,
            hide_completed: props.hideCompleted ? 0 : 1,
            period:         localPeriod.value || 'all',
            client_id:      localClient.value || undefined,
        },
        { preserveState: true, replace: true },
    );
}

// ── グループ表示モード ──
const viewMode = useUIState('sbw_user_proof_view_mode', 'date');
const viewModes = [
    { key: 'date',   label: '日付ごと' },
    { key: 'client', label: 'クライアントごと' },
    { key: 'project', label: '案件ごと' },
];

// ── ステータス定義 ──
const statusLabel = {
    pending:     '依頼中',
    assigned:    '割り当て済み',
    in_progress: '校正中',
    completed:   '完了',
};
const statusBadge = {
    pending:     'bg-gray-100 text-gray-700',
    assigned:    'bg-blue-100 text-blue-800',
    in_progress: 'bg-indigo-100 text-indigo-800',
    completed:   'bg-yellow-100 text-yellow-800',
};

// ── フォーマット ──
function fmtDeadline(isoStr) {
    if (!isoStr) return '—';
    const fmt = new Intl.DateTimeFormat('ja-JP', {
        timeZone: 'Asia/Tokyo',
        month: 'numeric', day: 'numeric',
    });
    const p = Object.fromEntries(fmt.formatToParts(new Date(isoStr)).map(({ type, value }) => [type, value]));
    return `${p.month}月${p.day}日 00:00`;
}

function isOverdue(req) {
    return req.deadline && new Date(req.deadline) < new Date() && req.status !== 'completed';
}

// ── グループ化 ──
function getGroupKey(req) {
    if (viewMode.value === 'client') {
        return req.project_job?.client?.name || '（クライアント不明）';
    }
    if (viewMode.value === 'project') {
        return req.project_job?.title || req.project_job?.name || '（案件不明）';
    }
    // 日付ごと（deadline 基準）
    if (!req.deadline) return '期限なし';
    try {
        const d = new Date(req.deadline);
        const fmt = new Intl.DateTimeFormat('ja-JP', { timeZone: 'Asia/Tokyo', year: 'numeric', month: 'numeric', day: 'numeric' });
        const p = Object.fromEntries(fmt.formatToParts(d).map(({ type, value }) => [type, value]));
        return `${p.year}-${String(p.month).padStart(2,'0')}-${String(p.day).padStart(2,'0')}`;
    } catch { return '—'; }
}

function getGroupLabel(key) {
    if (viewMode.value === 'date') {
        if (!key || key === '期限なし' || key === '—') return key;
        try {
            const parts = key.split('-');
            const d = new Date(key + 'T00:00:00');
            const dow = ['日','月','火','水','木','金','土'][d.getDay()];
            return `${parts[0]}年${parseInt(parts[1])}月${parseInt(parts[2])}日（${dow}）`;
        } catch { return key; }
    }
    return key || '未設定';
}

const displayGroups = computed(() => {
    const rows = props.proofRequests.data ?? [];
    const map = new Map();
    for (const req of rows) {
        const key = getGroupKey(req);
        if (!map.has(key)) map.set(key, []);
        map.get(key).push(req);
    }
    return Array.from(map.entries()).map(([key, items]) => ({
        key,
        label: getGroupLabel(key),
        items,
    }));
});
</script>

<template>
    <AppLayout title="校正状況">
        <template #header>
            <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">校正状況</h2>
        </template>

        <template #tabs>
            <UserNavigationTabs active="proof_status" />
        </template>

        <div class="rounded bg-white px-4 py-6 sm:p-6 shadow">

            <!-- 検索・フィルター行 -->
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div class="flex items-center gap-2">
                    <input
                        v-model="localQ"
                        @keyup.enter="search"
                        placeholder="タイトル/詳細で検索"
                        class="w-full sm:w-72 rounded border px-3 py-2 text-sm"
                    />
                    <button class="rounded bg-indigo-600 px-3 py-2 text-white" @click.prevent="search">検索</button>
                    <button class="ml-2 rounded border px-3 py-2" @click.prevent="clearSearch">クリア</button>
                </div>
            </div>

            <!-- 月セレクター + 完了非表示チェック + クライアント絞り込み -->
            <div class="mt-3 flex flex-wrap items-center gap-4">
                <div class="flex items-center gap-2">
                    <label class="text-sm text-gray-700">年月:</label>
                    <select
                        v-model="localPeriod"
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
                    <input
                        type="checkbox"
                        :checked="hideCompleted"
                        @change="toggleHideCompleted"
                        class="h-4 w-4 rounded border-gray-300"
                    />
                    完了を表示しない
                </label>

                <div v-if="clients.length > 0" class="flex items-center gap-2">
                    <label class="text-sm text-gray-700">クライアント:</label>
                    <select
                        v-model="localClient"
                        @change="search"
                        class="rounded border px-3 py-2 text-sm"
                        style="min-width: 10em"
                    >
                        <option value="">すべて</option>
                        <option v-for="c in clients" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
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
                    校正依頼が見つかりません。
                </div>

                <template v-for="group in displayGroups" :key="group.key">
                    <!-- グループヘッダー -->
                    <div class="mt-4 rounded bg-gray-100 px-4 py-1.5 text-sm font-semibold text-gray-700 first:mt-0">
                        {{ group.label }}
                        <span class="ml-2 text-xs font-normal text-gray-500">{{ group.items.length }} 件</span>
                    </div>

                    <table class="w-full table-fixed border" style="min-width: 640px;">
                        <colgroup>
                            <col style="width: 28%">  <!-- タイトル -->
                            <col style="width: 18%">  <!-- クライアント -->
                            <col style="width: 22%">  <!-- 案件 -->
                            <col style="width: 18%">  <!-- 校正員 -->
                            <col style="width: 160px"> <!-- 期限 -->
                            <col style="width: 88px">  <!-- ステータス -->
                        </colgroup>
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">タイトル</th>
                                <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">クライアント</th>
                                <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">案件</th>
                                <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">校正員</th>
                                <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">期限</th>
                                <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">ステータス</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="req in group.items"
                                :key="req.id"
                                class="hover:bg-gray-50"
                            >
                                <td class="break-words border px-3 py-2 text-sm font-medium text-gray-900">{{ req.title || '—' }}</td>
                                <td class="break-words border px-3 py-2 text-sm text-gray-600">
                                    {{ req.project_job?.client?.name || '—' }}
                                </td>
                                <td class="break-words border px-3 py-2 text-sm text-gray-600">
                                    {{ req.project_job?.title || req.project_job?.name || '—' }}
                                </td>
                                <td class="whitespace-nowrap border px-3 py-2 text-sm text-gray-600">
                                    {{ req.proofreader?.name || '未割当' }}
                                </td>
                                <td
                                    class="whitespace-nowrap border px-3 py-2 text-sm"
                                    :class="isOverdue(req) ? 'font-bold text-red-600' : 'text-gray-600'"
                                >
                                    {{ fmtDeadline(req.deadline) }}
                                </td>
                                <td class="whitespace-nowrap border px-3 py-2">
                                    <span
                                        :class="['rounded px-2 py-1 text-xs font-medium', statusBadge[req.status] ?? 'bg-gray-100 text-gray-700']"
                                    >{{ statusLabel[req.status] ?? req.status }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </template>
            </div>

            <!-- 件数 -->
            <div class="mt-4 text-sm text-gray-600">
                表示中 {{ proofRequests.total ?? proofRequests.data?.length ?? 0 }} 件
            </div>

            <!-- ページネーション -->
            <div v-if="proofRequests.links && proofRequests.links.length > 3" class="mt-4 flex justify-center gap-1">
                <template v-for="link in proofRequests.links" :key="link.label">
                    <a
                        v-if="link.url"
                        :href="link.url"
                        class="rounded px-3 py-1 text-sm"
                        :class="link.active ? 'bg-gray-800 text-white' : 'border text-gray-600 hover:bg-gray-50'"
                        v-html="link.label"
                    />
                    <span v-else class="rounded px-3 py-1 text-sm text-gray-400" v-html="link.label" />
                </template>
            </div>
        </div>
    </AppLayout>
</template>
