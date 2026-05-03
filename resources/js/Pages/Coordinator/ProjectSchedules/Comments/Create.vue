<template>
    <AppLayout title="メモ作成">
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('coordinator.project_schedules.show', { project_schedule: project_schedule.id })"
                    class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300"
                >← スケジュールに戻る</Link>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">プロジェクトスケジュール メモ作成</h2>
            </div>
        </template>
        <div class="mx-auto max-w-2xl rounded bg-white p-6 shadow">
            <div class="mb-4">
                <div class="text-sm text-gray-600">{{ project_schedule.name }}</div>
            </div>
            <form @submit.prevent="submit">
                <div class="mb-2">
                    <label class="block text-sm font-medium">内容</label>
                    <textarea v-model="body" class="w-full rounded border p-2" rows="6"></textarea>
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="submit" class="rounded bg-indigo-600 px-4 py-2 text-white">保存</button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
const props = defineProps({ project_schedule: Object });
const body = ref('');

function submit() {
    router.post(route('coordinator.project_schedule_comments.store', { project_schedule: props.project_schedule.id }), { body: body.value });
}
</script>
