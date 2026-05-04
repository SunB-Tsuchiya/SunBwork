<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import ProofCoordinatorNavigationTabs from '@/Components/Tabs/ProofCoordinatorNavigationTabs.vue';
import { useUIState } from '@/Composables/useUIState';
import { Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    proofRequests: { type: Array, default: () => [] },
});

const searchInput = ref('');
const groupMode   = useUIState('sbw_proof_inbox_group_mode', 'deadline'); // 'deadline' | 'created_at' | 'project'

const groupModeOptions = [
    { key: 'deadline',   label: '締め切りごと' },
    { key: 'created_at', label: '依頼日ごと' },
    { key: 'project',    label: '案件ごと' },
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

function toJstDateStr(isoStr) {
    if (!isoStr) return null;
    const fmt = new Intl.DateTimeFormat('ja-JP', {
        timeZone: 'Asia/Tokyo',
        year: 'numeric', month: 'numeric', day: 'numeric',
    });
    const p = Object.fromEntries(fmt.formatToParts(new Date(isoStr)).map(({ type, value }) => [type, value]));
    return `${p.year}年${p.month}月${p.day}日`;
}

function isOverdue(deadline) {
    return deadline && new Date(deadline) < new Date();
}

const filteredRequests = computed(() => {
    const q = searchInput.value.trim().toLowerCase();
    if (!q) return props.proofRequests;
    return props.proofRequests.filter(req =>
        (req.title ?? '').toLowerCase().includes(q) ||
        (req.project_job?.title ?? '').toLowerCase().includes(q),
    );
});

const groupedRows = computed(() => {
    const getKey = (req) => {
        if (groupMode.value === 'project')    return req.project_job?.title ?? '案件なし';
        if (groupMode.value === 'created_at') return toJstDateStr(req.created_at) ?? '依頼日不明';
        return toJstDateStr(req.deadline) ?? '締め切りなし';
    };

    const map = new Map();
    for (const req of filteredRequests.value) {
        const key = getKey(req);
        if (!map.has(key)) map.set(key, []);
        map.get(key).push(req);
    }

    const noDateLabel = groupMode.value === 'deadline' ? '締め切りなし' : '依頼日不明';
    const keys = [...map.keys()].sort((a, b) => {
        if (groupMode.value === 'project') return a.localeCompare(b, 'ja');
        if (a === noDateLabel) return 1;
        if (b === noDateLabel) return -1;
        const toDate = (s) => {
            const m = s.match(/(\d+)年(\d+)月(\d+)日/);
            return m ? new Date(m[1], m[2] - 1, m[3]) : new Date(0);
        };
        return toDate(a) - toDate(b);
    });

    return keys.map(key => ({ key, rows: map.get(key) }));
});
</script>

<template>
    <AppLayout title="校正依頼受信">
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">校正依頼受信ボックス</h2>
        </template>

        <template #tabs>
            <ProofCoordinatorNavigationTabs active="inbox" :pending-count="proofRequests.length" />
        </template>

        <div class="rounded bg-white p-6 shadow">

            <!-- 検索 -->
            <div class="flex items-center gap-2">
                <input
                    v-model="searchInput"
                    type="text"
                    placeholder="タイトル・案件名で検索"
                    class="w-72 rounded border-gray-300 text-sm"
                />
                <button
                    @click="searchInput = ''"
                    class="rounded border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50"
                >クリア</button>
            </div>

            <!-- グループ表示ピル -->
            <div class="mt-3 flex w-fit items-center gap-1 rounded-lg border border-gray-200 bg-gray-50 p-1">
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

            <!-- データなし -->
            <p v-if="groupedRows.length === 0" class="mt-6 text-sm text-gray-500">
                未受理の校正依頼はありません。
            </p>

            <!-- テーブル -->
            <div v-else class="mt-4 overflow-x-auto">
                <template v-for="group in groupedRows" :key="group.key">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr class="bg-pink-200">
                                <th colspan="6" class="px-4 py-2 text-left text-sm font-semibold text-pink-900">
                                    {{ group.key }}
                                    <span class="ml-2 text-xs font-normal text-gray-500">{{ group.rows.length }}件</span>
                                </th>
                            </tr>
                            <tr class="bg-gray-50">
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">依頼日</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">ジョブ名</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">依頼者</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">関連案件</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">校正締め切り</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">操作</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr
                                v-for="req in group.rows"
                                :key="req.id"
                                class="cursor-pointer hover:bg-pink-50"
                                @click="router.get(route('proof_coordinator.inbox.assign_page', { proofRequest: req.id }))"
                            >
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">
                                    {{ toJstDateStr(req.created_at) ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                    {{ req.title }}
                                    <p v-if="req.note" class="mt-0.5 text-xs text-gray-500">{{ req.note }}</p>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">
                                    {{ req.requester?.name ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    {{ req.project_job?.title ?? '—' }}
                                </td>
                                <td
                                    class="whitespace-nowrap px-4 py-3 text-sm"
                                    :class="isOverdue(req.deadline) ? 'font-bold text-red-600' : 'text-gray-600'"
                                >
                                    {{ fmtDeadline(req.deadline) }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm" @click.stop>
                                    <Link
                                        :href="route('proof_coordinator.inbox.assign_page', { proofRequest: req.id })"
                                        class="inline-flex items-center rounded bg-pink-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-pink-700"
                                    >受理する</Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </template>
            </div>

        </div>
    </AppLayout>
</template>
