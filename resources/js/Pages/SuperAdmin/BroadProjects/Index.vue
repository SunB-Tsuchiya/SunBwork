<script setup>
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import Modal from '@/Components/Modal.vue';
import BroadProjectForm from './BroadProjectForm.vue';

defineProps({ projects: { type: Array, default: () => [] } });

const editingProject = ref(null);

function closeEdit() {
    editingProject.value = null;
}
</script>

<template>
    <AppLayout title="大まかな案件の管理">
        <template #header><h2 class="text-xl font-semibold leading-tight text-gray-800">大まかな案件の管理</h2></template>
        <div class="space-y-5">
            <div class="rounded bg-white p-5 shadow">
                <p class="text-sm text-gray-600">細かな案件を作る前に、その日の作業先として使う大まかな案件を登録します。情報出版の試用向けです。</p>
                <BroadProjectForm class="mt-4" />
            </div>
            <div class="rounded bg-white p-5 shadow">
                <h3 class="font-semibold">登録済みの大まかな案件</h3>
                <p v-if="!projects.length" class="mt-3 text-sm text-gray-500">まだ登録されていません。</p>
                <ul v-else class="mt-3 divide-y">
                    <li v-for="project in projects" :key="project.id" class="flex items-center justify-between gap-3 py-3 text-sm">
                        <div><span class="font-medium">{{ project.name }}</span><span class="ml-2 text-gray-500">{{ [project.client?.name, project.fiscal_year ? `${project.fiscal_year}年度` : null].filter(Boolean).join(' / ') }}</span><span v-if="!project.is_active" class="ml-2 rounded bg-gray-100 px-2 py-0.5 text-xs">休止中</span></div>
                        <button type="button" class="shrink-0 text-blue-700 underline" @click="editingProject = project">編集</button>
                    </li>
                </ul>
            </div>
        </div>
        <Modal :show="Boolean(editingProject)" max-width="lg" aria-label="大まかな案件を編集" @close="closeEdit">
            <div class="flex items-center justify-between border-b px-6 py-4">
                <h3 class="text-lg font-semibold text-gray-900">大まかな案件を編集</h3>
                <button type="button" class="text-sm text-gray-600 underline" @click="closeEdit">閉じる</button>
            </div>
            <div class="px-6 py-5">
                <BroadProjectForm v-if="editingProject" :key="editingProject.id" :project="editingProject" @saved="closeEdit" @cancel="closeEdit" />
            </div>
        </Modal>
    </AppLayout>
</template>
