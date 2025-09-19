<template>
    <AppLayout :title="`ジョブ割り当て - ${projectJob.title}`">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">【進行管理】{{ $page.props.auth.user.name || 'ユーザー' }}さんのページ</h2>
        </template>

        <div class="mx-auto max-w-3xl rounded bg-white p-6 shadow">
            <h1 class="mb-4 text-2xl font-bold">ジョブ割り当て：{{ projectJob.title }}</h1>

            <div class="mb-4 rounded border p-4">
                <label class="mb-1 block font-semibold">ジョブ名</label>
                <div class="w-full rounded border bg-gray-50 px-3 py-2">{{ assignment.title }}</div>

                <label class="mb-1 mt-2 block font-semibold">概要</label>
                <div class="w-full rounded border bg-gray-50 px-3 py-2">{{ assignment.detail }}</div>

                <label class="mb-1 mt-2 block font-semibold">作業詳細</label>
                <div class="mb-2 text-sm text-gray-700">
                    <div v-if="assignment.type_label">{{ assignment.type_label }}</div>
                    <div v-if="assignment.size_label">{{ assignment.size_label }}</div>
                    <div v-if="assignment.stage_label">{{ assignment.stage_label }}</div>
                    <div v-if="assignment.status_label">{{ assignment.status_label }}</div>
                </div>

                <label class="mb-1 mt-2 block font-semibold">難易度</label>
                <div class="w-full rounded border bg-gray-50 px-3 py-2">{{ assignment.difficulty }}</div>

                <div class="mt-2">
                    <label class="mb-1 block font-semibold">割当希望日</label>
                    <div class="w-full rounded border bg-gray-50 px-3 py-2">{{ assignment.desired_start_date || '-' }}</div>

                    <div class="mt-2">
                        <label class="mb-1 block font-semibold">終了希望日, 希望時間</label>
                        <div class="flex items-center gap-3">
                            <div class="rounded border bg-gray-50 px-3 py-2">{{ assignment.desired_end_date || '-' }}</div>
                            <div class="rounded border bg-gray-50 px-3 py-2">{{ formatTime(assignment.desired_time) }}</div>
                        </div>
                    </div>
                </div>

                <label class="mb-1 mt-2 block font-semibold">見積時間</label>
                <div class="w-full rounded border bg-gray-50 px-3 py-2">{{ formatEstimatedHours(assignment.estimated_hours) }}</div>

                <label class="mb-1 mt-2 block font-semibold">割当ユーザー</label>
                <div class="w-full rounded border bg-gray-50 px-3 py-2">{{ assignment.user?.name || '-' }}</div>

                <div class="mt-2 text-right">
                    <div v-if="assignment.linked_assignment_id">
                        <Link
                            :href="
                                route('coordinator.project_jobs.assignments.show', {
                                    projectJob: projectJob.id,
                                    assignment: assignment.linked_assignment_id,
                                })
                            "
                            class="ml-3 text-sm text-blue-600"
                            >割当を見る (#{{ assignment.linked_assignment_id }})</Link
                        >
                    </div>
                </div>
            </div>

            <div class="flex gap-2">
                <Link :href="route('coordinator.project_jobs.assignments.index', { projectJob: projectJob.id })" class="rounded bg-gray-200 px-4 py-2"
                    >戻る</Link
                >
                <Link
                    :href="route('coordinator.project_jobs.assignments.edit', { projectJob: projectJob.id, assignment: assignment.id })"
                    class="rounded bg-yellow-500 px-4 py-2 text-white"
                    >編集</Link
                >
                <button class="rounded bg-red-500 px-4 py-2 text-white" @click.prevent="deleteAssignment">削除</button>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
const props = defineProps({ projectJob: Object, assignment: Object });
const page = usePage();

function formatTime(t) {
    if (!t) return '';
    const core = String(t).split('.')[0];
    const parts = core.split(':');
    if (parts.length >= 2) return parts[0].padStart(2, '0') + ':' + parts[1].padStart(2, '0');
    return t;
}

function formatEstimatedHours(h) {
    if (h === null || h === undefined || h === '') return '-';
    const n = Number(h);
    if (Number.isNaN(n)) return '-';
    return Number.isInteger(n) ? `${n}h` : `${n}h`;
}

function deleteAssignment() {
    if (!confirm('この割当を本当に削除しますか？この操作は取り消せません。')) return;
    router.delete(route('coordinator.project_jobs.assignments.destroy', { projectJob: projectJob.id, assignment: assignment.id }), {
        onSuccess: () => {
            router.visit(route('coordinator.project_jobs.assignments.index', { projectJob: projectJob.id }));
        },
        onError: (errors) => {
            console.error('deleteAssignment error', errors);
            alert('削除に失敗しました。詳細はコンソールを確認してください。');
        },
    });
}
</script>

<style scoped></style>
