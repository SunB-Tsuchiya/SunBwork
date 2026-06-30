<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import ProofCoordinatorNavigationTabs from '@/Components/Tabs/ProofCoordinatorNavigationTabs.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    reservation: { type: Object, required: true },
    canRegisterToCalendar: { type: Boolean, default: false },
});

const registering = ref(false);

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

function formatDateTime(value) {
    if (!value) return '—';
    return new Intl.DateTimeFormat('ja-JP', {
        timeZone: 'Asia/Tokyo',
        year: 'numeric',
        month: 'numeric',
        day: 'numeric',
        weekday: 'short',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    }).format(new Date(value));
}

function displayValue(prefix) {
    return props.reservation[`${prefix}_mode`] === 'text'
        ? props.reservation[`${prefix}_text`] || '—'
        : formatDateTime(props.reservation[prefix]);
}

function registerCalendar() {
    if (!props.canRegisterToCalendar || props.reservation.calendar_registered_at) return;
    registering.value = true;
    router.post(route('proof_coordinator.reservations.register_calendar', {
        reservation: props.reservation.id,
    }), {}, {
        preserveScroll: true,
        onFinish: () => {
            registering.value = false;
        },
    });
}

function updateStatus(status) {
    if (status === props.reservation.status) return;
    if (status === 'deleted' && !window.confirm('この校正予約を「削除」状態に変更しますか？')) return;

    router.patch(route('proof_coordinator.reservations.update_status', {
        reservation: props.reservation.id,
    }), { status }, { preserveScroll: true });
}
</script>

<template>
    <AppLayout :title="`校正予約詳細 - ${reservation.title}`">
        <template #header>
            <div class="flex items-center gap-3">
                <Link
                    :href="route('proof_coordinator.reservations.index')"
                    class="whitespace-nowrap rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300"
                >← 校正予約一覧に戻る</Link>
                <h2 class="text-base font-semibold leading-tight text-gray-800 sm:text-xl">校正予約 — 詳細</h2>
            </div>
        </template>

        <template #headerExtras>
            <div class="flex flex-wrap items-center justify-end gap-2">
                <button
                    type="button"
                    class="rounded border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-40"
                    :disabled="reservation.status === 'reserved'"
                    @click="updateStatus('reserved')"
                >予約受付</button>
                <button
                    type="button"
                    class="rounded bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-40"
                    :disabled="reservation.status === 'in_progress'"
                    @click="updateStatus('in_progress')"
                >校正中</button>
                <button
                    type="button"
                    class="rounded bg-green-600 px-3 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-40"
                    :disabled="reservation.status === 'completed'"
                    @click="updateStatus('completed')"
                >完了</button>
                <button
                    type="button"
                    class="rounded bg-red-600 px-3 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-40"
                    :disabled="reservation.status === 'deleted'"
                    @click="updateStatus('deleted')"
                >削除</button>
                <Link
                    v-if="reservation.calendar_registered_at"
                    :href="route('proof_coordinator.calendar')"
                    class="rounded bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700"
                >校正カレンダーを見る</Link>
                <button
                    v-else
                    type="button"
                    :disabled="!canRegisterToCalendar || registering"
                    class="rounded bg-pink-600 px-4 py-2 text-sm font-medium text-white hover:bg-pink-700 disabled:cursor-not-allowed disabled:opacity-50"
                    :title="canRegisterToCalendar ? '' : '依頼予定と締め切りの両方が日時入力の場合のみ登録できます'"
                    @click="registerCalendar"
                >{{ registering ? '登録中...' : 'カレンダーに登録' }}</button>
            </div>
        </template>

        <template #tabs>
            <ProofCoordinatorNavigationTabs active="reservations" />
        </template>

        <div class="mx-auto max-w-3xl space-y-4">
            <div class="overflow-hidden rounded-lg border border-pink-200 bg-white shadow-sm">
                <div class="flex items-start justify-between gap-3 border-b border-pink-100 bg-pink-50 px-5 py-4">
                    <div class="min-w-0 flex-1">
                        <h3 class="text-base font-bold text-gray-900">{{ reservation.title }}</h3>
                        <p v-if="reservation.project_job" class="mt-0.5 text-sm text-gray-500">
                            {{ reservation.project_job.title }}
                            <span v-if="reservation.project_job.client">／{{ reservation.project_job.client.name }}</span>
                        </p>
                    </div>
                    <span
                        class="shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold"
                        :class="statusBadge[reservation.status] ?? statusBadge.reserved"
                    >{{ statusLabel[reservation.status] ?? reservation.status }}</span>
                </div>

                <dl class="divide-y divide-gray-100">
                    <div class="grid gap-x-6 px-5 py-3 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs text-gray-500">依頼者</dt>
                            <dd class="mt-0.5 text-sm font-medium text-gray-900">{{ reservation.requester?.name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">予約送信日時</dt>
                            <dd class="mt-0.5 text-sm font-medium text-gray-900">{{ formatDateTime(reservation.created_at) }}</dd>
                        </div>
                    </div>
                    <div class="px-5 py-3">
                        <dt class="text-xs text-gray-500">依頼予定（開始）</dt>
                        <dd class="mt-0.5 whitespace-pre-wrap text-sm font-medium text-gray-900">{{ displayValue('requested_at') }}</dd>
                    </div>
                    <div class="px-5 py-3">
                        <dt class="text-xs text-gray-500">締め切り（終了）</dt>
                        <dd class="mt-0.5 whitespace-pre-wrap text-sm font-medium text-gray-900">{{ displayValue('deadline_at') }}</dd>
                    </div>
                    <div v-if="reservation.note" class="px-5 py-3">
                        <dt class="text-xs text-gray-500">備考</dt>
                        <dd class="mt-0.5 whitespace-pre-wrap text-sm text-gray-700">{{ reservation.note }}</dd>
                    </div>
                </dl>
            </div>

            <p v-if="!canRegisterToCalendar" class="rounded border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                自由記述を含む予約は日時が確定していないため、校正カレンダーへ登録できません。
            </p>
        </div>
    </AppLayout>
</template>
