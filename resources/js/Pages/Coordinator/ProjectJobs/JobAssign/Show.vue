<template>
    <AppLayout :title="`割当 #${assignment.id}`">
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('coordinator.project_jobs.assignments.index', { projectJob: projectJob.id })"
                      class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 whitespace-nowrap hover:bg-gray-300"
                >← 割り当て一覧に戻る</Link>
                <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">ジョブ割り当て詳細</h2>
            </div>
        </template>

        <template #headerExtras>
            <div class="flex items-center gap-2">
                <Link :href="route('coordinator.project_jobs.assignments.edit', { projectJob: projectJob.id, assignment: assignment.id })"
                      class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                >編集</Link>
                <button class="rounded bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700" @click.prevent="deleteAssignment">削除</button>
            </div>
        </template>

        <div class="mx-auto max-w-2xl rounded bg-white px-4 py-6 sm:p-6 shadow">
            <AssignmentForm :projectJob="projectJob" :members="$page.props.members || []" :assignments="[assignment]" :editMode="false" />

            <!-- ファイル一覧（file_info があれば常に表示） -->
            <div v-if="assignment.file_info" class="mt-6">
                <FileInfoDisplay :fileInfo="assignment.file_info" />
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import AssignmentForm from '@/Pages/Coordinator/ProjectJobs/JobAssign/AssignmentForm.vue';
import FileInfoDisplay from '@/Components/FileInfoDisplay.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const { projectJob, assignment } = defineProps({ projectJob: Object, assignment: Object });

function deleteAssignment() {
    if (!confirm('この割当を本当に削除しますか？この操作は取り消せません。')) return;
    router.delete(route('coordinator.project_jobs.assignments.destroy', { projectJob: projectJob.id, assignment: assignment.id }), {
        onSuccess: () => router.visit(route('coordinator.project_jobs.show', { projectJob: projectJob.id }) + '?tab=history'),
        onError: () => alert('削除に失敗しました。詳細はコンソールを確認してください。'),
    });
}
</script>

<style scoped></style>
