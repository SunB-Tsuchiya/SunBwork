<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import ProofCoordinatorNavigationTabs from '@/Components/Tabs/ProofCoordinatorNavigationTabs.vue';
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    proofRequests: { type: Array, default: () => [] },
    proofreaders:  { type: Array, default: () => [] },
});

const selectedProofreader = ref({});

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
    assigned:    '割り当て済み',
    in_progress: '校正中',
};

const statusBadge = {
    assigned:    'bg-blue-100 text-blue-800',
    in_progress: 'bg-indigo-100 text-indigo-800',
};

function assign(req) {
    const proofreaderId = selectedProofreader.value[req.id];
    if (!proofreaderId) return;
    router.put(route('proof_coordinator.assignments.assign', { proofRequest: req.id }), {
        proofreader_id: proofreaderId,
    }, { preserveScroll: true });
}

function start(id) {
    router.put(route('proof_coordinator.assignments.start', { proofRequest: id }), {}, {
        preserveScroll: true,
    });
}

function complete(id) {
    if (!confirm('この校正を完了にしますか？依頼者に通知されます。')) return;
    router.put(route('proof_coordinator.assignments.complete', { proofRequest: id }), {}, {
        preserveScroll: true,
    });
}

function goToShow(id) {
    router.get(route('proof_coordinator.assignments.show', { proofRequest: id }));
}
</script>

<template>
    <AppLayout title="割り振り管理">
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">割り振り管理</h2>
        </template>

        <template #tabs>
            <ProofCoordinatorNavigationTabs active="assignments" />
        </template>

        <div class="rounded bg-white p-6 shadow">
            <p v-if="proofRequests.length === 0" class="text-gray-500">
                進行中の校正依頼はありません。
            </p>

            <table v-else class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">タイトル</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">依頼者</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">校正締め切り</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">ステータス</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">担当校正員</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">操作</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    <tr
                        v-for="req in proofRequests"
                        :key="req.id"
                        class="cursor-pointer hover:bg-pink-50"
                        @click="goToShow(req.id)"
                    >
                        <td class="px-4 py-3 text-sm font-medium text-gray-900">
                            {{ req.title }}
                            <p v-if="req.project_job" class="mt-0.5 text-xs text-gray-500">{{ req.project_job.title }}</p>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">{{ req.requester?.name }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm" :class="req.deadline && new Date(req.deadline) < new Date() ? 'font-bold text-red-600' : 'text-gray-600'">
                            {{ fmtDeadline(req.deadline) }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm">
                            <span :class="['rounded px-2 py-1 text-xs font-medium', statusBadge[req.status]]">
                                {{ statusLabel[req.status] }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm" @click.stop>
                            <span v-if="req.proofreader" class="text-gray-700">{{ req.proofreader.name }}</span>
                            <div v-else class="flex items-center gap-2">
                                <select
                                    v-model="selectedProofreader[req.id]"
                                    class="rounded border-gray-300 text-sm"
                                >
                                    <option value="">校正員を選択</option>
                                    <option v-for="p in proofreaders" :key="p.id" :value="p.id">
                                        {{ p.name }}
                                    </option>
                                </select>
                                <button
                                    @click.stop="assign(req)"
                                    class="rounded bg-blue-600 px-2 py-1 text-xs font-medium text-white hover:bg-blue-700"
                                >
                                    割り当て
                                </button>
                            </div>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm" @click.stop>
                            <div class="flex gap-2">
                                <button
                                    v-if="req.status === 'assigned'"
                                    @click.stop="start(req.id)"
                                    class="rounded bg-indigo-600 px-2 py-1 text-xs font-medium text-white hover:bg-indigo-700"
                                >
                                    開始
                                </button>
                                <button
                                    @click.stop="complete(req.id)"
                                    class="rounded bg-green-600 px-2 py-1 text-xs font-medium text-white hover:bg-green-700"
                                >
                                    完了
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
