<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import AdminNavigationTabs from '@/Components/Tabs/AdminNavigationTabs.vue';
import DepartmentFieldConfigForm from '@/Components/DepartmentFieldConfigForm.vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    department: { type: Object, required: true },
    configs:    { type: Object, default: () => ({}) },
    masters:    { type: Object, default: () => ({}) },
});

function handleSubmit(payload) {
    router.post(
        route('admin.departments.field_config.update', { department: props.department.id }),
        payload
    );
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
            <AdminNavigationTabs active="departments" />
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
                :save-route="route('admin.departments.field_config.update', { department: department.id })"
                :back-route="route('admin.departments.index')"
                @submit="handleSubmit"
            />
        </div>
    </AppLayout>
</template>
