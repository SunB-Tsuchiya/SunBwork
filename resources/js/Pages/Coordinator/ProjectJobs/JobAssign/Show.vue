<template>
    <AppLayout :title="`割当 #${assignment.id}`">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">割当を見る</h2>
        </template>

        <div class="rounded bg-white p-6 shadow">
            <div class="mb-4 flex items-center gap-3">
                <h1 class="text-2xl font-bold">割当 #{{ assignment.id }}</h1>
            </div>

            <AssignmentForm :projectJob="projectJob" :members="$page.props.members || []" :assignments="[assignment]" :editMode="false" />

            <!-- ファイル一覧（file_info があれば常に表示） -->
            <div v-if="assignment.file_info" class="mt-6">
                <FileInfoDisplay :fileInfo="assignment.file_info" />
            </div>

            <div class="mt-4 flex gap-2">
                <Link :href="route('coordinator.project_jobs.assignments.index', { projectJob: projectJob.id })" class="rounded bg-gray-200 px-4 py-2">戻る</Link>
                <Link :href="route('coordinator.project_jobs.assignments.edit', { projectJob: projectJob.id, assignment: assignment.id })" class="rounded bg-yellow-500 px-4 py-2 text-white">編集</Link>
                <button class="rounded bg-red-500 px-4 py-2 text-white" @click.prevent="deleteAssignment">削除</button>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import AssignmentForm from '@/Pages/Coordinator/ProjectJobs/JobAssign/AssignmentForm.vue';
import FileInfoDisplay from '@/Components/FileInfoDisplay.vue';
import { Link, router } from '@inertiajs/vue3';

const { projectJob, assignment } = defineProps({ projectJob: Object, assignment: Object });

function deleteAssignment() {
    if (!confirm('この割当を本当に削除しますか？この操作は取り消せません。')) return;
    router.delete(route('coordinator.project_jobs.assignments.destroy', { projectJob: projectJob.id, assignment: assignment.id }), {
        onSuccess: () => router.visit(route('coordinator.project_jobs.assignments.index', { projectJob: projectJob.id })),
        onError: () => alert('削除に失敗しました。詳細はコンソールを確認してください。'),
    });
}
</script>

<style scoped></style>
