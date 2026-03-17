<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';

const props = defineProps({
    companies: {
        type: Array,
        required: true,
    },
});

const confirmDelete = (companyId) => {
    if (confirm('会社を削除すると、部署・担当もすべて削除されます。よろしいですか？')) {
        if (confirm('本当に削除します。いいですか？')) {
            router.delete(route('admin.companies.destroy', companyId));
        }
    }
};
</script>

<template>
    <AppLayout title="会社管理">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">会社管理</h2>
                <!-- <Link :href="route('admin.companies.create')" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">新規会社登録</Link> -->
            </div>
        </template>
        <div class="mb-6 overflow-hidden bg-white p-6 shadow-xl sm:rounded-lg">
            <div v-for="company in companies" :key="company.id" class="mb-8 rounded-lg bg-blue-50 p-6 shadow">
                <div class="mb-4 flex items-center text-2xl font-bold text-blue-800">
                    <span>会社名：{{ company.name }}</span>
                    <Link
                        :href="route('admin.companies.edit', company.id)"
                        class="ml-[20px] rounded border border-blue-300 bg-blue-100 px-3 py-1 text-base font-bold text-blue-700 transition hover:bg-blue-200"
                        >会社編集</Link
                    >
                    <button
                        type="button"
                        class="ml-2 rounded border border-red-300 bg-red-100 px-3 py-1 text-base font-bold text-red-700 transition hover:bg-red-200"
                        @click="confirmDelete(company.id)"
                    >
                        会社削除
                    </button>
                </div>
                <div
                    v-for="department in [...company.departments].sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0))"
                    :key="department.id"
                    class="mb-4 ml-8 rounded border-l-4 border-blue-300 bg-blue-100 p-4"
                >
                    <div class="mb-2 text-lg font-semibold text-blue-900">部署名：{{ department.name }}</div>
                    <div class="ml-8 text-blue-700">
                        <span>担当名：</span>
                        <span v-for="(assignment, idx) in department.assignments" :key="assignment.id">
                            <span>{{ assignment.name }}</span
                            ><span v-if="idx < department.assignments.length - 1">，</span>
                        </span>
                    </div>
                </div>
                <!-- 会社編集・削除ボタンは会社名の横に移動 -->
            </div>
        </div>
    </AppLayout>
</template>
