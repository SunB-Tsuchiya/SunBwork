<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import UserNavigationTabs from '@/Components/Tabs/UserNavigationTabs.vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    proofRequests: { type: Array, default: () => [] },
});

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
</script>

<template>
    <AppLayout title="校正ジョブ">
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">校正ジョブ</h2>
        </template>
        <template #tabs>
            <UserNavigationTabs active="proof_jobs" />
        </template>

        <div class="rounded bg-white shadow">
            <div v-if="proofRequests.length === 0"
                 class="px-6 py-12 text-center text-sm text-gray-400">
                割り当てられた校正ジョブはありません。
            </div>
            <table v-else class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">タイトル</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">案件</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">締め切り</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">ステータス</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">作業時間</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="pr in proofRequests" :key="pr.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ pr.title }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ pr.job_title ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500 whitespace-nowrap">{{ fmtDeadline(pr.deadline) }}</td>
                        <td class="px-4 py-3">
                            <span :class="['rounded-full px-2 py-0.5 text-xs font-semibold', statusBadge[pr.status]]">
                                {{ statusLabel[pr.status] ?? pr.status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500">
                            <div v-if="pr.work_slots.length > 0" class="space-y-0.5">
                                <div v-for="(slot, i) in pr.work_slots" :key="i" class="whitespace-nowrap">
                                    {{ slot.date }} {{ slot.startTime }}〜{{ slot.endTime }}
                                </div>
                            </div>
                            <span v-else class="text-gray-300">未設定</span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <Link
                                v-if="pr.status !== 'completed'"
                                :href="route('user.proof_jobs.set_page', { proofRequest: pr.id })"
                                class="rounded bg-pink-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-pink-700 whitespace-nowrap"
                            >
                                {{ pr.is_set ? '予定を変更' : '校正をセット' }}
                            </Link>
                            <span v-else
                                  class="rounded bg-yellow-100 px-2 py-1 text-xs font-medium text-yellow-700">
                                完了済み
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
