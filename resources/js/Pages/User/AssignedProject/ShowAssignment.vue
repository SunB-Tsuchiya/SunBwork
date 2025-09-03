<template>
    <AppLayout title="依頼詳細">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">依頼詳細</h2>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-3xl rounded bg-white p-6 shadow">
                <h1 class="mb-4 text-2xl font-bold">{{ assignment.title || (projectJob ? projectJob.name : '依頼') }}</h1>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">担当ユーザー</label>
                    <div class="mt-1 text-gray-900">{{ assignment.user ? assignment.user.name : '未指定' }}</div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">詳細</label>
                    <div class="mt-1 whitespace-pre-wrap text-gray-900">{{ assignment.detail || (projectJob && projectJob.detail) || '-' }}</div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">希望開始日</label>
                        <div class="mt-1 text-gray-900">{{ assignment.desired_start_date || '-' }}</div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">希望終了日</label>
                        <div class="mt-1 text-gray-900">{{ assignment.desired_end_date || '-' }}</div>
                    </div>
                </div>

                <div class="mt-6 flex gap-2">
                    <Link :href="route('events.create', { job: assignment.id })" class="rounded bg-blue-600 px-4 py-2 text-white">予定をセット</Link>
                    <Link :href="route('user.assigned-jobs.index')" class="rounded bg-gray-200 px-4 py-2">戻る</Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({ assignment: Object, projectJob: Object });
const assignment = props.assignment || {};
const projectJob = props.projectJob || null;
</script>

<style scoped>
/* minimal styles */
</style>
