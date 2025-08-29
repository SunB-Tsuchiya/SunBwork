<template>
    <div class="p-6">
        <h1 class="mb-4 text-xl font-bold">メモ</h1>
        <div class="prose">
            <div v-html="comment.body"></div>
        </div>
        <div class="mt-4">
            <router-link :href="route('coordinator.project_schedules.show', { project_schedule: comment.project_schedule_id })" class="text-blue-600"
                >戻る</router-link
            >
        </div>
    </div>
</template>

<script setup>
const props = defineProps({ comment: Object });
</script>
