<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import ProofCoordinatorNavigationTabs from '@/Components/Tabs/ProofCoordinatorNavigationTabs.vue';
import { useUIState } from '@/Composables/useUIState';
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    reservations: { type: Array, default: () => [] },
    search: { type: String, default: '' },
    period: { type: String, default: '' },
    dateField: { type: String, default: 'requested_at' },
    sortOrder: { type: String, default: 'desc' },
    monthOptions: { type: Array, default: () => [] },
});

const searchInput = ref(props.search);
const periodInput = ref(props.period);
const dateFieldInput = ref(props.dateField);
const sortOrderInput = ref(props.sortOrder);
const groupMode = useUIState('sbw_proof_reservations_group_mode', 'requested_at');
const hideCompleted = useUIState('sbw_proof_reservations_hide_completed', true);

const statusLabel = {
    reserved: '予約受付',
    in_progress: '校正中',
    completed: '完了',
    deleted: '削除',
};

const statusBadge = {
    reserved: 'bg-gray-100 text-gray-700',
    in_progress: 'bg-indigo-100 text-indigo-800',
    completed: 'bg-green-100 text-green-800',
    deleted: 'bg-red-100 text-red-700',
};

const groupModeOptions = [
    { key: 'requested_at', label: '依頼予定ごと' },
    { key: 'deadline_at', label: '締め切りごと' },
    { key: 'project', label: '案件ごと' },
];

function formatDateTime(value) {
    if (!value) return '—';
    return new Intl.DateTimeFormat('ja-JP', {
        timeZone: 'Asia/Tokyo',
        year: 'numeric',
        month: 'numeric',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    }).format(new Date(value));
}

function displayValue(reservation, prefix) {
    return reservation[`${prefix}_mode`] === 'text'
        ? reservation[`${prefix}_text`] || '—'
        : formatDateTime(reservation[prefix]);
}

function groupDate(value, fallback) {
    if (!value) return fallback;
    return new Intl.DateTimeFormat('ja-JP', {
        timeZone: 'Asia/Tokyo',
        year: 'numeric',
        month: 'numeric',
        day: 'numeric',
    }).format(new Date(value));
}

const groupedRows = computed(() => {
    const groups = new Map();
    props.reservations
        .filter((reservation) => !hideCompleted.value || reservation.status !== 'completed')
        .forEach((reservation) => {
        let key;
        if (groupMode.value === 'project') {
            key = reservation.project_job?.title ?? '案件なし';
        } else if (reservation[`${groupMode.value}_mode`] === 'text') {
            key = `${groupMode.value === 'requested_at' ? '依頼予定' : '締め切り'}（テキスト指定）`;
        } else {
            key = groupDate(
                reservation[groupMode.value],
                groupMode.value === 'requested_at' ? '依頼予定なし' : '締め切りなし',
            );
        }
        if (!groups.has(key)) groups.set(key, []);
        groups.get(key).push(reservation);
    });
    return [...groups].map(([key, rows]) => ({ key, rows }));
});

function doSearch() {
    router.get(route('proof_coordinator.reservations.index'), {
        search: searchInput.value,
        period: periodInput.value,
        date_field: dateFieldInput.value,
        sort_order: sortOrderInput.value,
    }, { preserveState: false, replace: true });
}

function clearFilters() {
    searchInput.value = '';
    periodInput.value = '';
    dateFieldInput.value = 'requested_at';
    sortOrderInput.value = 'desc';
    doSearch();
}

function goToShow(id) {
    router.get(route('proof_coordinator.reservations.show', { reservation: id }));
}
</script>

<template>
    <AppLayout title="校正予約一覧">
        <template #header>
            <h2 class="text-base font-semibold leading-tight text-gray-800 sm:text-xl">校正予約一覧</h2>
        </template>

        <template #tabs>
            <ProofCoordinatorNavigationTabs active="reservations" />
        </template>

        <div class="rounded bg-white px-4 py-6 shadow sm:p-6">
            <div class="flex flex-col gap-3">
                <div class="flex flex-wrap items-center gap-2">
                    <input
                        v-model="searchInput"
                        type="text"
                        placeholder="タイトル・案件名・クライアント名で検索"
                        class="w-72 rounded border-gray-300 text-sm"
                        @keyup.enter="doSearch"
                    />
                    <button class="rounded bg-pink-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-pink-700" @click="doSearch">検索</button>
                    <button class="rounded border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50" @click="clearFilters">クリア</button>
                    <select
                        v-model="sortOrderInput"
                        class="rounded border-gray-300 text-sm"
                        aria-label="予約日の並べ替え"
                        @change="doSearch"
                    >
                        <option value="desc">日付：新しい順</option>
                        <option value="asc">日付：古い順</option>
                    </select>
                </div>

                <div class="flex flex-wrap items-center gap-4">
                    <div class="flex items-center gap-3">
                        <label class="flex cursor-pointer select-none items-center gap-1 text-sm text-gray-700">
                            <input v-model="dateFieldInput" type="radio" value="requested_at" class="h-4 w-4 border-gray-300 text-pink-600" @change="doSearch" />
                            依頼予定日
                        </label>
                        <label class="flex cursor-pointer select-none items-center gap-1 text-sm text-gray-700">
                            <input v-model="dateFieldInput" type="radio" value="deadline_at" class="h-4 w-4 border-gray-300 text-pink-600" @change="doSearch" />
                            締め切り日
                        </label>
                    </div>
                    <div class="flex items-center gap-2">
                        <label class="text-sm text-gray-700">年月:</label>
                        <select v-model="periodInput" class="w-40 rounded border-gray-300 text-sm" @change="doSearch">
                            <option value="">全期間</option>
                            <option v-for="month in monthOptions" :key="month.value" :value="month.value">{{ month.label }}</option>
                        </select>
                    </div>
                </div>

                <div class="flex w-fit items-center gap-1 rounded-lg border border-gray-200 bg-gray-50 p-1">
                    <button
                        v-for="option in groupModeOptions"
                        :key="option.key"
                        class="rounded px-3 py-1 text-sm transition-colors"
                        :class="groupMode === option.key ? 'bg-white font-semibold text-pink-700 shadow-sm' : 'text-gray-600 hover:text-gray-800'"
                        @click="groupMode = option.key"
                    >{{ option.label }}</button>
                </div>

                <button
                    type="button"
                    class="flex w-fit items-center gap-2 rounded border px-3 py-1.5 text-sm font-medium transition-colors"
                    :class="hideCompleted
                        ? 'border-pink-300 bg-pink-50 text-pink-700'
                        : 'border-gray-300 bg-white text-gray-600 hover:bg-gray-50'"
                    :aria-pressed="hideCompleted"
                    @click="hideCompleted = !hideCompleted"
                >
                    <span
                        class="flex h-4 w-4 items-center justify-center rounded border text-xs"
                        :class="hideCompleted ? 'border-pink-600 bg-pink-600 text-white' : 'border-gray-400 bg-white'"
                    >{{ hideCompleted ? '✓' : '' }}</span>
                    完了を表示しない
                </button>
            </div>

            <p v-if="groupedRows.length === 0" class="mt-6 text-sm text-gray-500">校正予約はありません。</p>

            <div v-else class="mt-4 overflow-x-auto">
                <template v-for="group in groupedRows" :key="group.key">
                    <table class="mb-5 min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr class="bg-pink-200">
                                <th colspan="8" class="px-4 py-2 text-left text-sm font-semibold text-pink-900">
                                    {{ group.key }}
                                    <span class="ml-2 text-xs font-normal text-gray-500">{{ group.rows.length }}件</span>
                                </th>
                            </tr>
                            <tr class="bg-gray-50">
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">予約日</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">タイトル</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">案件</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">依頼者</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">依頼予定</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">締め切り</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">ステータス</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">カレンダー</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr
                                v-for="reservation in group.rows"
                                :key="reservation.id"
                                class="cursor-pointer hover:bg-pink-50"
                                @click="goToShow(reservation.id)"
                            >
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">{{ formatDateTime(reservation.created_at) }}</td>
                                <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ reservation.title }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ reservation.project_job?.title ?? '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">{{ reservation.requester?.name ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ displayValue(reservation, 'requested_at') }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ displayValue(reservation, 'deadline_at') }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm">
                                    <span
                                        class="rounded-full px-2.5 py-1 text-xs font-medium"
                                        :class="statusBadge[reservation.status] ?? statusBadge.reserved"
                                    >{{ statusLabel[reservation.status] ?? reservation.status }}</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm">
                                    <span
                                        class="rounded-full px-2.5 py-1 text-xs font-medium"
                                        :class="reservation.calendar_registered_at ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'"
                                    >{{ reservation.calendar_registered_at ? '登録済み' : '未登録' }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </template>
            </div>
        </div>
    </AppLayout>
</template>
