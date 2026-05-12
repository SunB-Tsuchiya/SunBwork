<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import ProofCoordinatorNavigationTabs from '@/Components/Tabs/ProofCoordinatorNavigationTabs.vue';
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    proofRequests:  { type: Object, default: () => ({}) },
    search:         { type: String, default: '' },
    period:         { type: String, default: '' },
    hideCompleted:  { type: Boolean, default: false },
    monthOptions:   { type: Array, default: () => [] },
});

const searchInput     = ref(props.search);
const periodInput     = ref(props.period);
const hideCompletedInput = ref(props.hideCompleted);

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

function doSearch() {
    router.get(route('proof_coordinator.history'), {
        search:         searchInput.value,
        period:         periodInput.value,
        hide_completed: hideCompletedInput.value ? '1' : '',
    }, {
        preserveState: false,
        replace: true,
    });
}

function clearFilters() {
    searchInput.value      = '';
    periodInput.value      = '';
    hideCompletedInput.value = false;
    doSearch();
}
</script>

<template>
    <AppLayout title="案件校正履歴">
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">案件校正履歴</h2>
        </template>

        <template #tabs>
            <ProofCoordinatorNavigationTabs active="history" />
        </template>

        <div class="rounded bg-white px-4 py-6 sm:p-6 shadow">
            <!-- 検索・フィルター行 -->
            <div class="flex flex-col gap-3 md:flex-row md:flex-wrap md:items-center">
                <!-- 検索ワード -->
                <div class="flex items-center gap-2">
                    <input
                        v-model="searchInput"
                        type="text"
                        placeholder="タイトル・案件名で検索"
                        class="w-64 rounded border-gray-300 text-sm"
                        @keyup.enter="doSearch"
                    />
                    <button
                        @click="doSearch"
                        class="rounded bg-pink-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-pink-700"
                    >
                        検索
                    </button>
                    <button
                        @click="clearFilters"
                        class="rounded border px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50"
                    >
                        クリア
                    </button>
                </div>

                <!-- 年月セレクター -->
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

                <!-- 完了を表示しない -->
                <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700 select-none">
                    <input
                        type="checkbox"
                        v-model="hideCompletedInput"
                        @change="doSearch"
                        class="h-4 w-4 rounded border-gray-300"
                    />
                    完了を表示しない
                </label>
            </div>

            <p v-if="proofRequests.data?.length === 0" class="mt-6 text-gray-500">
                該当する校正履歴はありません。
            </p>

            <div v-else class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">依頼日</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">タイトル</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">案件</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">依頼者</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">校正員</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">締め切り</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">完了日</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">ステータス</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr v-for="req in proofRequests.data" :key="req.id" class="hover:bg-gray-50">
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">
                                {{ new Date(req.created_at).toLocaleDateString('ja-JP') }}
                            </td>
                            <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ req.title }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ req.project_job?.title ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">{{ req.requester?.name ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">{{ req.proofreader?.name ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">{{ fmtDeadline(req.deadline) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">
                                {{ req.completed_at ? new Date(req.completed_at).toLocaleDateString('ja-JP') : '—' }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm">
                                <span :class="['rounded px-2 py-1 text-xs font-medium', statusBadge[req.status]]">
                                    {{ statusLabel[req.status] }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- ページネーション -->
            <div v-if="proofRequests.links" class="mt-4 flex justify-center gap-1">
                <template v-for="link in proofRequests.links" :key="link.label">
                    <a
                        v-if="link.url"
                        :href="link.url"
                        class="rounded px-3 py-1 text-sm"
                        :class="link.active ? 'bg-pink-600 text-white' : 'border text-gray-600 hover:bg-gray-50'"
                        v-html="link.label"
                    />
                    <span v-else class="rounded px-3 py-1 text-sm text-gray-400" v-html="link.label" />
                </template>
            </div>
        </div>
    </AppLayout>
</template>
