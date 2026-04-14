<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import ProofCoordinatorNavigationTabs from '@/Components/Tabs/ProofCoordinatorNavigationTabs.vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    proofRequests: { type: Array, default: () => [] },
});

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
    <AppLayout title="校正依頼受信">
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">校正依頼受信ボックス</h2>
        </template>

        <template #tabs>
            <ProofCoordinatorNavigationTabs active="inbox" :pending-count="proofRequests.length" />
        </template>

        <div class="rounded bg-white p-6 shadow">
            <p v-if="proofRequests.length === 0" class="text-gray-500">
                未受理の校正依頼はありません。
            </p>

            <table v-else class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">依頼日時</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">ジョブ名</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">依頼者</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">関連案件</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">校正締め切り</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">操作</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    <tr v-for="req in proofRequests" :key="req.id" class="hover:bg-gray-50">
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">
                            {{ new Date(req.created_at).toLocaleDateString('ja-JP') }}
                        </td>
                        <td class="px-4 py-3 text-sm font-medium text-gray-900">
                            {{ req.title }}
                            <p v-if="req.note" class="mt-0.5 text-xs text-gray-500">{{ req.note }}</p>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">
                            {{ req.requester?.name }}
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            {{ req.project_job?.title ?? '—' }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">
                            <span :class="req.deadline && new Date(req.deadline) < new Date() ? 'font-bold text-red-600' : ''">
                                {{ fmtDeadline(req.deadline) }}
                            </span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm">
                            <Link
                                :href="route('proof_coordinator.inbox.assign_page', { proofRequest: req.id })"
                                class="inline-flex items-center rounded bg-pink-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-pink-700"
                            >
                                受理する
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
