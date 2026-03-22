<template>
    <AppLayout title="割当作成 - 案件選択">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">【進行管理】割当作成</h2>
        </template>

        <div class="mx-auto max-w-lg rounded bg-white p-6 shadow">
            <h1 class="mb-6 text-2xl font-bold">案件を選択</h1>

            <div v-if="projects.length === 0" class="py-8 text-center text-sm text-gray-400">
                オーナーとして登録されている進行中の案件がありません。<br />
                <Link :href="route('coordinator.project_jobs.index')" class="mt-3 inline-block text-blue-600 underline hover:text-blue-800">案件一覧へ</Link>
            </div>

            <div v-else class="space-y-5">
                <!-- クライアント選択 -->
                <div>
                    <label class="mb-1 block font-semibold">クライアント</label>
                    <select v-model="selectedClientId" @change="selectedProjectId = ''" class="w-full rounded border px-3 py-2">
                        <option value="">-- すべての案件を表示 --</option>
                        <option v-for="c in clients" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>

                <!-- 案件選択 -->
                <div>
                    <label class="mb-1 block font-semibold">案件 <span class="text-red-500">*</span></label>
                    <select v-model="selectedProjectId" class="w-full rounded border px-3 py-2">
                        <option value="">-- 選択してください --</option>
                        <option v-for="p in filteredProjects" :key="p.id" :value="p.id">{{ p.title }}</option>
                    </select>
                    <p v-if="noProjectsInClient" class="mt-1 text-xs text-gray-400">
                        このクライアントに案件がありません。<Link :href="route('coordinator.project_jobs.index')" class="text-blue-600 underline">案件一覧</Link>から追加してください。
                    </p>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <button
                        @click="proceed"
                        :disabled="!selectedProjectId"
                        class="rounded bg-green-600 px-6 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-40"
                    >割当を作成する</button>
                    <Link :href="route('coordinator.jobbox')" class="rounded bg-gray-200 px-4 py-2 text-sm text-gray-700 hover:bg-gray-300">戻る</Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    clients: { type: Array, default: () => [] },
    projects: { type: Array, default: () => [] },
});

const selectedClientId = ref('');
const selectedProjectId = ref('');

const filteredProjects = computed(() => {
    if (!selectedClientId.value) return props.projects;
    return props.projects.filter((p) => String(p.client_id) === String(selectedClientId.value));
});

const noProjectsInClient = computed(() => {
    return selectedClientId.value && filteredProjects.value.length === 0;
});

function proceed() {
    if (!selectedProjectId.value) return;
    router.visit(route('coordinator.project_jobs.assignments.create', { projectJob: selectedProjectId.value }));
}
</script>
