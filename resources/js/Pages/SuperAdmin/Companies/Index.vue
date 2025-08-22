<script setup>
// copied from Admin/Companies/Index.vue with route names adjusted to superadmin
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
            router.delete(route('superadmin.companies.destroy', companyId));
        }
    }
};
</script>

<template>
    <AppLayout title="会社管理">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">会社管理</h2>
                <Link :href="route('superadmin.companies.create')" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">新規会社登録</Link>
            </div>
        </template>
        <div class="py-6 max-w-4xl mx-auto">
            <div v-for="company in companies" :key="company.id" class="mb-8 p-6 bg-blue-50 rounded-lg shadow">
                <div class="font-bold text-2xl text-blue-800 mb-4 flex items-center">
                    <span>会社名：{{ company.name }}</span>
                    <Link :href="route('superadmin.companies.edit', company.id)"
                        class="ml-[20px] px-3 py-1 rounded font-bold text-blue-700 bg-blue-100 hover:bg-blue-200 text-base transition border border-blue-300"
                    >会社編集</Link>
                    <button
                        type="button"
                        class="ml-2 px-3 py-1 rounded font-bold text-red-700 bg-red-100 hover:bg-red-200 text-base transition border border-red-300"
                        @click="confirmDelete(company.id)"
                    >会社削除</button>
         </div>
                <div v-for="department in [...company.departments].sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0))" :key="department.id" class="mb-4 ml-8 p-4 bg-blue-100 rounded border-l-4 border-blue-300">
                    <div class="font-semibold text-lg text-blue-900 mb-2">
                        部署名：{{ department.name }}
                    </div>
                    <div class="ml-8 text-blue-700">
                        <span>担当名：</span>
                        <span v-for="(assignment, idx) in department.assignments" :key="assignment.id">
                            <span>{{ assignment.name }}</span><span v-if="idx < department.assignments.length - 1">，</span>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
