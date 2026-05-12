<template>
    <AppLayout :title="`複合ジョブ作成 - ${projectJob?.title ?? ''}`">
        <template #header>
            <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">複合ジョブ作成</h2>
        </template>

        <div class="mx-auto max-w-3xl rounded bg-white px-4 py-6 sm:p-6 shadow">
            <div class="mb-4 flex items-center justify-between">
                <h1 class="text-2xl font-bold">複合ジョブ作成</h1>
                <span class="rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-700">複合</span>
            </div>

            <CompositeAssignmentForm :projectJob="projectJob" :members="members" />
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue'
import CompositeAssignmentForm from '@/Components/CompositeAssignmentForm.vue'

defineProps({
    projectJob: Object,
    members:    { type: Array, default: () => [] },
})
</script>
