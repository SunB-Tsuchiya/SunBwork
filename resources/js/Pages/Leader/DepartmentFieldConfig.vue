<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import LeaderNavigationTabs from '@/Components/Tabs/LeaderNavigationTabs.vue';
import DepartmentFieldConfigForm from '@/Components/DepartmentFieldConfigForm.vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    department: { type: Object, required: true },
    configs:    { type: Object, default: () => ({}) },
    masters:    { type: Object, default: () => ({}) },
});

function handleSubmit(payload) {
    router.post(route('leader.department_field_config.update'), payload);
}
</script>

<template>
    <AppLayout :title="`${department.name} — フィールド設定`">
        <template #header>
            <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">
                {{ department.name }} — ジョブフィールド設定
            </h2>
        </template>
        <template #tabs>
            <LeaderNavigationTabs active="workload_setting" />
        </template>

        <div class="rounded bg-white px-4 py-6 sm:p-6 shadow">
            <p class="mb-5 text-sm text-gray-500">
                ジョブ割り当て画面に表示するフィールドをカスタマイズします。<br>
                設定なしの場合はデフォルト（全スロット表示）が使われます。
            </p>
            <DepartmentFieldConfigForm
                :department="department"
                :configs="configs"
                :masters="masters"
                :save-route="route('leader.department_field_config.update')"
                :back-route="route('workload_setting.index')"
                @submit="handleSubmit"
            />
        </div>
    </AppLayout>
</template>
