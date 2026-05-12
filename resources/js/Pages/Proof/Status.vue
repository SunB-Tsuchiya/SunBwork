<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    proofRequests: { type: Object, default: () => ({}) },
    mine:          { type: Boolean, default: false },
});

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

function toggleMine() {
    router.get(route('proof.status'), { mine: !props.mine ? 1 : 0 }, {
        preserveState: true,
        replace: true,
    });
}
</script>

<template>
    <AppLayout title="校正状況">
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">校正状況</h2>
        </template>

        <div class="rounded bg-white px-4 py-6 sm:p-6 shadow">
            <div class="mb-4 flex items-center gap-3">
                <button
                    @click="toggleMine"
                    :class="mine
                        ? 'bg-blue-600 text-white'
                        : 'border border-blue-300 text-blue-600 hover:bg-blue-50'"
                    class="rounded px-3 py-1.5 text-sm font-medium"
                >
                    自分の依頼のみ
                </button>
            </div>

            <p v-if="proofRequests.data?.length === 0" class="text-gray-500">
                校正依頼はありません。
            </p>

            <div v-else class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">タイトル</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">依頼者</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">校正員</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">締め切り</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">ステータス</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    <tr v-for="req in proofRequests.data" :key="req.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm font-medium text-gray-900">
                            {{ req.title }}
                            <p v-if="req.project_job" class="mt-0.5 text-xs text-gray-500">{{ req.project_job.title }}</p>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">{{ req.requester?.name ?? '—' }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">{{ req.proofreader?.name ?? '—' }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm" :class="req.deadline && new Date(req.deadline) < new Date() && req.status !== 'completed' ? 'font-bold text-red-600' : 'text-gray-600'">
                            {{ fmtDeadline(req.deadline) }}
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
                        :class="link.active ? 'bg-gray-800 text-white' : 'border text-gray-600 hover:bg-gray-50'"
                        v-html="link.label"
                    />
                    <span v-else class="rounded px-3 py-1 text-sm text-gray-400" v-html="link.label" />
                </template>
            </div>
        </div>
    </AppLayout>
</template>
