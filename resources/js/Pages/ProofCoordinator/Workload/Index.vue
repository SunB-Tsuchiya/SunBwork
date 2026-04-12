<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import ProofCoordinatorNavigationTabs from '@/Components/Tabs/ProofCoordinatorNavigationTabs.vue';
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    proofreaders: { type: Array, default: () => [] },
    yearMonth:    { type: String, default: '' },
});

const selectedMonth = ref(props.yearMonth);

function changeMonth() {
    router.get(route('proof_coordinator.workload'), { year_month: selectedMonth.value }, {
        preserveState: true,
        replace: true,
    });
}
</script>

<template>
    <AppLayout title="校正員作業量">
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">校正員作業量</h2>
        </template>

        <template #tabs>
            <ProofCoordinatorNavigationTabs active="workload" />
        </template>

        <div class="rounded bg-white p-6 shadow">
            <!-- 月選択 -->
            <div class="mb-4 flex items-center gap-3">
                <label class="text-sm font-medium text-gray-700">対象月</label>
                <input
                    v-model="selectedMonth"
                    type="month"
                    class="rounded border-gray-300 text-sm"
                    @change="changeMonth"
                />
            </div>

            <p v-if="proofreaders.length === 0" class="text-gray-500">
                校正員が登録されていません。
            </p>

            <table v-else class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">校正員名</th>
                        <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">担当件数</th>
                        <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">進行中</th>
                        <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">完了件数</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    <tr v-for="p in proofreaders" :key="p.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ p.name }}</td>
                        <td class="px-4 py-3 text-center text-sm text-gray-600">{{ p.total_count }}</td>
                        <td class="px-4 py-3 text-center text-sm">
                            <span :class="p.active_count > 0 ? 'font-semibold text-indigo-600' : 'text-gray-400'">
                                {{ p.active_count }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center text-sm text-gray-600">{{ p.completed_count }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
