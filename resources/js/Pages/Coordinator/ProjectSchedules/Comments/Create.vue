<template>
    <div class="p-6">
        <h1 class="mb-4 text-xl font-bold">プロジェクトスケジュール メモ作成</h1>
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
</template>

<script setup>
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
