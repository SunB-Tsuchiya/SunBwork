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
            router.delete(route('superadmin.companies.destroy', companyId));
        }
    }
};
</script>

<template>
    <AppLayout title="会社管理">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">会社管理</h2>
                <Link
                    :href="route('superadmin.companies.create')"
                    class="rounded bg-green-600 px-4 py-2 font-bold text-white hover:bg-green-700"
                >
                    新規会社登録
                </Link>
            </div>
        </template>

        <div class="rounded bg-white p-6 shadow">
            <div v-if="companies.length === 0" class="py-8 text-center text-gray-500">
                会社が登録されていません。
            </div>

            <div v-else class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                会社名
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                代表者
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                部署
                            </th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">
                                操作
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr v-for="company in companies" :key="company.id" class="hover:bg-gray-50">
                            <td class="whitespace-nowrap px-4 py-3">
                                <div class="text-sm font-medium text-gray-900">{{ company.name }}</div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                <div v-if="company.representative" class="text-sm text-gray-900">
                                    {{ company.representative.name }}
                                </div>
                                <div v-else class="text-xs text-gray-400">未設定</div>
                            </td>
                            <td class="px-4 py-3">
                                <div
                                    v-for="department in [...company.departments].sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0))"
                                    :key="department.id"
                                    class="text-sm text-gray-700"
                                >
                                    <span class="font-medium">{{ department.name }}</span>
                                    <span v-if="department.assignments?.length" class="ml-1 text-xs text-gray-500">
                                        （{{ department.assignments.map(a => a.name).join('，') }}）
                                    </span>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-center">
                                <Link
                                    :href="route('superadmin.companies.edit', company.id)"
                                    class="mr-2 rounded bg-indigo-600 px-3 py-1 text-xs font-bold text-white hover:bg-indigo-700"
                                >
                                    編集
                                </Link>
                                <button
                                    type="button"
                                    class="rounded bg-red-600 px-3 py-1 text-xs font-bold text-white hover:bg-red-700"
                                    @click="confirmDelete(company.id)"
                                >
                                    削除
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
