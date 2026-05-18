<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import ProofCoordinatorNavigationTabs from '@/Components/Tabs/ProofCoordinatorNavigationTabs.vue';

const props = defineProps({
    sheets: { type: Array, default: () => [] },
    search: { type: String, default: '' },
});

const searchInput = ref(props.search);

function doSearch() {
    router.get(route('proof_coordinator.workflow_sheets.index'), { search: searchInput.value }, {
        preserveState: true,
        replace: true,
    });
}
</script>

<template>
    <AppLayout title="管理シート（校正）">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">管理シート（校正）</h2>
        </template>
        <template #tabs>
            <ProofCoordinatorNavigationTabs active="workflow_sheets" />
        </template>

        <div class="rounded bg-white p-6 shadow">
            <!-- 検索 -->
            <div class="mb-4 flex gap-2">
                <input
                    v-model="searchInput"
                    type="text"
                    placeholder="シート名・案件名・クライアント名で検索"
                    class="flex-1 rounded border border-gray-300 px-3 py-2 text-sm focus:border-pink-400 focus:outline-none"
                    @keydown.enter="doSearch"
                />
                <button
                    @click="doSearch"
                    class="rounded bg-pink-600 px-4 py-2 text-sm font-medium text-white hover:bg-pink-700"
                >検索</button>
            </div>

            <div v-if="sheets.length === 0" class="py-8 text-center text-gray-400 text-sm">
                管理シートが見つかりません
            </div>

            <table v-else class="w-full text-sm border-collapse">
                <thead>
                    <tr class="bg-gray-50 text-left text-xs font-medium text-gray-600 uppercase tracking-wide">
                        <th class="px-3 py-2 border border-gray-200">案件名</th>
                        <th class="px-3 py-2 border border-gray-200">クライアント</th>
                        <th class="px-3 py-2 border border-gray-200">シート名</th>
                        <th class="px-3 py-2 border border-gray-200 text-center">校正未アサイン</th>
                        <th class="px-3 py-2 border border-gray-200">作成日</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="sheet in sheets"
                        :key="sheet.id"
                        class="hover:bg-pink-50 cursor-pointer transition-colors"
                        @click="router.visit(route('proof_coordinator.workflow_sheets.show', { sheet: sheet.id }))"
                    >
                        <td class="px-3 py-2 border border-gray-200 font-medium text-gray-800">
                            {{ sheet.project_job_title }}
                        </td>
                        <td class="px-3 py-2 border border-gray-200 text-gray-600">
                            {{ sheet.client_name }}
                        </td>
                        <td class="px-3 py-2 border border-gray-200">
                            <Link
                                :href="route('proof_coordinator.workflow_sheets.show', { sheet: sheet.id })"
                                class="text-pink-600 hover:underline"
                                @click.stop
                            >
                                {{ sheet.name }}
                            </Link>
                        </td>
                        <td class="px-3 py-2 border border-gray-200 text-center">
                            <span
                                v-if="sheet.proof_unassigned > 0"
                                class="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-bold text-red-700"
                            >
                                {{ sheet.proof_unassigned }}件
                            </span>
                            <span v-else-if="sheet.proof_total > 0" class="text-xs text-green-600">
                                すべてアサイン済
                            </span>
                            <span v-else class="text-xs text-gray-400">—</span>
                        </td>
                        <td class="px-3 py-2 border border-gray-200 text-gray-500 text-xs">
                            {{ sheet.created_at }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
