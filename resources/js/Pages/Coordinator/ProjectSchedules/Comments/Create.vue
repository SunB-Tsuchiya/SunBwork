<template>
    <AppLayout title="メモ作成">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">プロジェクトスケジュール メモ作成</h2>
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
                    <button type="button" @click="goBack" class="rounded bg-gray-300 px-4 py-2">戻る</button>
                    <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-white">保存</button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
const props = defineProps({ project_schedule: Object });
const body = ref('');

function submit() {
    router.post(route('coordinator.project_schedule_comments.store', { project_schedule: props.project_schedule.id }), { body: body.value });
}

function goBack() {
    router.get(route('coordinator.project_schedules.show', { project_schedule: props.project_schedule.id }));
}
</script>
