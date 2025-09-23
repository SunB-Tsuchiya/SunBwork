<template>
    <AppLayout :title="`ジョブ割り当て - ${projectJob.title}`">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">ジョブ割り当て — メッセージ表示</h2>
        </template>

        <div class="mx-auto max-w-3xl rounded bg-white p-6 shadow">
            <h1 class="mb-4 text-2xl font-bold">ジョブ割り当て：{{ projectJob.title }}</h1>

            <div class="mb-4 rounded border p-4">
                <label class="mb-1 block font-semibold">ジョブ名</label>
                <div class="w-full rounded border bg-gray-50 px-3 py-2">{{ assignment.title }}</div>

                <label class="mb-1 block font-semibold">クライアント</label>
                <div class="w-full rounded border bg-gray-50 px-3 py-2">
                    {{ assignment.project_job?.client?.name || projectJob.client?.name || '-' }}
                </div>

                <label class="mb-1 mt-2 block font-semibold">概要</label>
                <div class="w-full rounded border bg-gray-50 px-3 py-2">{{ assignment.detail }}</div>

                <label class="mb-1 mt-2 block font-semibold">作業詳細</label>
                <div class="mb-2 text-sm text-gray-700">
                    <div v-if="assignment.type_label">作業種別: {{ assignment.type_label }}</div>
                    <div v-else-if="assignment.work_item_type">
                        作業種別: {{ assignment.work_item_type?.name || assignment.work_item_type?.label || '' }}
                    </div>

                    <div v-if="assignment.size_label">サイズ: {{ assignment.size_label }}</div>
                    <div v-else-if="assignment.size">サイズ: {{ assignment.size?.name || assignment.size?.label || '' }}</div>

                    <div v-if="assignment.stage_label">ステージ: {{ assignment.stage_label }}</div>
                    <div v-else-if="assignment.stage">ステージ: {{ assignment.stage?.name || assignment.stage?.label || '' }}</div>

                    <div v-if="assignment.status_label">ステータス: {{ assignment.status_label }}</div>
                    <div v-else-if="assignment.statusModel">
                        ステータス: {{ assignment.statusModel?.name || assignment.statusModel?.label || '' }}
                    </div>
                </div>

                <label class="mb-1 mt-2 block font-semibold">難易度</label>
                <div class="w-full rounded border bg-gray-50 px-3 py-2">{{ difficultyLabel }}</div>

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

            <div class="flex items-center gap-2">
                <Link :href="routeBack()" class="rounded bg-gray-200 px-4 py-2">戻る</Link>
                <div v-if="assignment.scheduled || assignment.scheduled_at">
                    <span class="text-sm font-semibold text-green-600">セット済</span>
                </div>
                <!-- Show "予定をセット" only to the assignee (the user assigned to the job), not to the sender -->
                <div v-else-if="page.props.auth.user && assignment.user && page.props.auth.user.id === assignment.user.id">
                    <Link
                        :href="route('events.create', { job: assignment.id })"
                        class="rounded bg-blue-500 px-3 py-2 text-sm text-white hover:bg-blue-600"
                        >予定をセット</Link
                    >
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted } from 'vue';
const { projectJob, message } = defineProps({ projectJob: Object, message: Object });
const page = usePage();

// Use assignment from the message payload (broadcast includes project_job_assignment)
const assignment = message?.project_job_assignment || {};

// Resolve difficulty label in a predictable order:
// 1) assignment.difficulty_label (provided by backend)
// 2) lookup by assignment.difficulty_id using page.props.difficulties
// 3) attempt to match legacy assignment.difficulty to a difficulty by name/slug
// 4) fallback to assignment.difficulty or '-'
const difficultyLabel = computed(() => {
    if (assignment?.difficulty_label) return assignment.difficulty_label;
    const did = assignment?.difficulty_id ?? null;
    const difficulties = page.props?.difficulties ?? null;
    if (did && Array.isArray(difficulties)) {
        const found = difficulties.find((d) => String(d.id) === String(did));
        if (found) return found.name;
    }
    if (assignment?.difficulty) {
        if (Array.isArray(difficulties)) {
            const found = difficulties.find((d) => d.name === assignment.difficulty || d.slug === assignment.difficulty);
            if (found) return found.name;
        }
        return assignment.difficulty;
    }
    return '-';
});

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

function routeBack() {
    return page.props.auth.user && page.props.auth.user.isCoordinator
        ? route('coordinator.project_jobs.assignments.index', { projectJob: projectJob.id })
        : route('project_jobs.jobbox.index', { projectJob: projectJob.id });
}

// Mark JAM read when assignee opens this SPA view. Silent if API fails.
onMounted(async () => {
    try {
        const jamId = message && message.id;
        if (!jamId) return;
        await fetch(route('api.jobbox.read', { id: jamId }), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
        });
    } catch (err) {
        // ignore
    }
});
</script>

<style scoped></style>
