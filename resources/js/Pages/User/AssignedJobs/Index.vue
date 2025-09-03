<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps({
    jobs: {
        type: Array,
        default: () => [],
    },
});

const today = new Date();
const twoDaysAgo = new Date();
twoDaysAgo.setDate(today.getDate() - 2);

function toDateOnly(val) {
    if (!val) return null;
    return String(val).split('T')[0];
}

const recentJobs = computed(() => {
    return props.jobs.filter((j) => {
        const d = new Date(j.preferred_date || j.scheduled_date || j.assigned_at || j.created_at);
        return d >= twoDaysAgo;
    });
});

const archivedJobs = computed(() => {
    return props.jobs.filter((j) => {
        const d = new Date(j.preferred_date || j.scheduled_date || j.assigned_at || j.created_at);
        return d < twoDaysAgo;
    });
});

const disabling = new Set();
function goToSetEvent(job) {
    if (disabling.has(job.id)) return;
    disabling.add(job.id);
    const date = job.preferred_date || job.scheduled_date || toDateOnly(job.assigned_at) || toDateOnly(job.created_at) || toDateOnly(new Date());
    const url = route('events.create', { date: date, job: job.id });
    router.get(url);
}

function formatTime(t) {
    if (!t) return '';
    const core = String(t).split('.')[0];
    const parts = core.split(':');
    if (parts.length >= 2) return parts[0].padStart(2, '0') + ':' + parts[1].padStart(2, '0');
    return t;
}
</script>

<template>
    <AppLayout title="割り当てられたジョブ">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">割り当てジョブ</h2>
                <div>
                    <Link :href="route('calendar.index')" class="rounded bg-gray-200 px-3 py-1 text-gray-700">カレンダーに戻る</Link>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-5xl py-8">
            <h3 class="mb-4 text-lg font-medium">割り当てられたジョブ一覧</h3>
            <div class="overflow-x-auto rounded bg-white p-4 shadow">
                <table class="min-w-full table-auto">
                    <thead>
                        <tr class="bg-gray-100 text-left">
                            <th class="px-4 py-2 text-sm font-medium">プロジェクトジョブID</th>
                            <th class="px-4 py-2 text-sm font-medium">タイトル</th>
                            <th class="px-4 py-2 text-sm font-medium">詳細</th>
                            <th class="px-4 py-2 text-sm font-medium">難易度</th>
                            <th class="px-4 py-2 text-sm font-medium">希望開始日</th>
                            <th class="px-4 py-2 text-sm font-medium">希望終了日</th>
                            <th class="px-4 py-2 text-sm font-medium">希望時刻</th>
                            <th class="px-4 py-2 text-sm font-medium">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="job in props.jobs" :key="job.id" class="border-b hover:bg-gray-50">
                            <td class="px-4 py-3 align-top text-sm">{{ job.project_job_id || job.project_job || '-' }}</td>
                            <td class="px-4 py-3 align-top text-sm">{{ job.title || '-' }}</td>
                            <td class="whitespace-pre-wrap px-4 py-3 align-top text-sm">{{ job.details || job.detail || job.description || '-' }}</td>
                            <td class="px-4 py-3 align-top text-sm">{{ job.difficulty || job.level || '-' }}</td>
                            <td class="px-4 py-3 align-top text-sm">{{ job.desired_start_date || job.preferred_date || '-' }}</td>
                            <td class="px-4 py-3 align-top text-sm">{{ job.desired_end_date || '-' }}</td>
                            <td class="px-4 py-3 align-top text-sm">
                                {{ formatTime(job.desired_time) || (job.desired_time ? job.desired_time : '-') }}
                            </td>
                            <td class="px-4 py-3 align-top text-sm">
                                <div class="flex items-center gap-2">
                                    <button v-if="job.scheduled_at || job.scheduled" class="rounded bg-gray-400 px-3 py-1 text-white" disabled>
                                        セット済
                                    </button>
                                    <button v-else @click="goToSetEvent(job)" class="rounded bg-blue-600 px-3 py-1 text-white">予定をセット</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!props.jobs || props.jobs.length === 0">
                            <td colspan="8" class="py-6 text-center text-gray-500">割り当てられたジョブはありません。</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
