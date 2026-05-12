<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    proofRequests: { type: Array,   default: () => [] },
    q:             { type: String,  default: '' },
    hideCompleted: { type: Boolean, default: true },
    period:        { type: String,  default: '' },
    clientId:      { type: Number,  default: null },
    clients:       { type: Array,   default: () => [] },
    monthOptions:  { type: Array,   default: () => [] },
});

// ── ローカル状態 ──
const localQ      = ref(props.q ?? '');
const localPeriod = ref(props.period ?? '');
const localClient = ref(props.clientId ? String(props.clientId) : '');

function search() {
    router.get(
        route('user.proof_jobs.index'),
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
    router.get(route('user.proof_jobs.index'), {}, { replace: true });
}

function toggleHideCompleted() {
    router.get(
        route('user.proof_jobs.index'),
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
const viewMode = ref('date');
const viewModes = [
    { key: 'date',    label: '日付ごと' },
    { key: 'client',  label: 'クライアントごと' },
    { key: 'project', label: '案件ごと' },
];

// ── ステータス定義 ──
const statusLabel = {
    pending:     '受理待ち',
    assigned:    '割り当て済み',
    in_progress: '校正中',
    completed:   '完了',
};
const statusBadge = {
    pending:     'bg-gray-100 text-gray-700',
    assigned:    'bg-blue-100 text-blue-800',
    in_progress: 'bg-pink-100 text-pink-800',
    completed:   'bg-yellow-100 text-yellow-800',
};

// ── フォーマット ──
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

function isOverdue(pr) {
    return pr.deadline && new Date(pr.deadline) < new Date() && pr.status !== 'completed';
}

// ── グループ化 ──
function getGroupKey(pr) {
    if (viewMode.value === 'client') {
        return pr.client_name || '（クライアント不明）';
    }
    if (viewMode.value === 'project') {
        return pr.job_title || '（案件不明）';
    }
    // 日付ごと（deadline 基準）
    if (!pr.deadline) return '期限なし';
    try {
        const d = new Date(pr.deadline);
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
    const map = new Map();
    for (const pr of props.proofRequests) {
        const key = getGroupKey(pr);
        if (!map.has(key)) map.set(key, []);
        map.get(key).push(pr);
    }
    return Array.from(map.entries()).map(([key, items]) => ({
        key,
        label: getGroupLabel(key),
        items,
    }));
});
</script>

<template>
    <AppLayout title="校正ジョブ">
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">校正ジョブ</h2>
        </template>

        <div class="mx-auto max-w-6xl rounded bg-white px-4 py-6 sm:p-6 shadow">
            <h1 class="mb-4 text-2xl font-bold">校正ジョブ：</h1>

            <!-- 検索・フィルター行 -->
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div class="flex items-center gap-2">
                    <input
                        v-model="localQ"
                        @keyup.enter="search"
                        placeholder="タイトルで検索"
                        class="w-full sm:w-72 rounded border px-3 py-2 text-sm"
                    />
                    <button class="rounded bg-pink-600 px-3 py-2 text-white" @click.prevent="search">検索</button>
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
                        ? 'bg-white text-pink-700 font-semibold shadow-sm'
                        : 'text-gray-600 hover:text-gray-900'"
                    class="rounded px-4 py-1.5 text-sm transition-all"
                >{{ mode.label }}</button>
            </div>

            <!-- グループ表示 -->
            <div class="mt-4 overflow-x-auto">
                <div v-if="displayGroups.length === 0" class="py-8 text-center text-sm text-gray-400">
                    校正ジョブが見つかりません。
                </div>

                <template v-for="group in displayGroups" :key="group.key">
                    <!-- グループヘッダー -->
                    <div class="mt-4 rounded bg-gray-100 px-4 py-1.5 text-sm font-semibold text-gray-700 first:mt-0">
                        {{ group.label }}
                        <span class="ml-2 text-xs font-normal text-gray-500">{{ group.items.length }} 件</span>
                    </div>

                    <table class="w-full table-fixed border" style="min-width: 700px;">
                        <colgroup>
                            <col style="width: 25%">   <!-- タイトル -->
                            <col style="width: 16%">   <!-- クライアント -->
                            <col style="width: 18%">   <!-- 案件 -->
                            <col style="width: 150px"> <!-- 期限 -->
                            <col style="width: 90px">  <!-- ステータス -->
                            <col style="width: 150px"> <!-- 作業時間 -->
                            <col style="width: 110px"> <!-- 操作 -->
                        </colgroup>
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">タイトル</th>
                                <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">クライアント</th>
                                <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">案件</th>
                                <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">期限</th>
                                <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">ステータス</th>
                                <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">作業時間</th>
                                <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="pr in group.items" :key="pr.id" class="hover:bg-gray-50">
                                <td class="break-words border px-3 py-2 text-sm font-medium">
                                    <Link :href="route('user.proof_jobs.show', { proofRequest: pr.id })" class="text-pink-700 hover:underline">
                                        {{ pr.title }}
                                    </Link>
                                </td>
                                <td class="break-words border px-3 py-2 text-sm text-gray-600">{{ pr.client_name || '—' }}</td>
                                <td class="break-words border px-3 py-2 text-sm text-gray-600">{{ pr.job_title || '—' }}</td>
                                <td
                                    class="whitespace-nowrap border px-3 py-2 text-sm"
                                    :class="isOverdue(pr) ? 'font-bold text-red-600' : 'text-gray-600'"
                                >{{ fmtDeadline(pr.deadline) }}</td>
                                <td class="whitespace-nowrap border px-3 py-2">
                                    <span :class="['rounded-full px-2 py-0.5 text-xs font-semibold', statusBadge[pr.status]]">
                                        {{ statusLabel[pr.status] ?? pr.status }}
                                    </span>
                                </td>
                                <td class="border px-3 py-2 text-xs text-gray-500">
                                    <div v-if="pr.work_slots.length > 0" class="space-y-0.5">
                                        <div v-for="(slot, i) in pr.work_slots" :key="i" class="whitespace-nowrap">
                                            {{ slot.date }} {{ slot.startTime }}〜{{ slot.endTime }}
                                        </div>
                                    </div>
                                    <span v-else class="text-gray-300">未設定</span>
                                </td>
                                <td class="border px-3 py-2 text-right">
                                    <Link
                                        v-if="pr.status !== 'completed'"
                                        :href="route('user.proof_jobs.set_page', { proofRequest: pr.id })"
                                        class="rounded bg-pink-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-pink-700 whitespace-nowrap"
                                    >{{ pr.is_set ? '予定を変更' : '校正をセット' }}</Link>
                                    <span v-else class="rounded bg-yellow-100 px-2 py-1 text-xs font-medium text-yellow-700">
                                        完了済み
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </template>
            </div>

            <!-- 件数 -->
            <div class="mt-4 text-sm text-gray-600">
                表示中 {{ proofRequests.length }} 件
            </div>
        </div>
    </AppLayout>
</template>

