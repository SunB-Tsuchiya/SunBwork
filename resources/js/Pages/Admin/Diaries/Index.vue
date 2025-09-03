<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ departments: Array, date: String });
const selectedDate = ref(props.date || new Date().toISOString().slice(0, 10));

function changeDate() {
    // reload with selected date
    window.location.href = route('admin.diaries.index', { date: selectedDate.value });
}
</script>

<template>
    <AppLayout title="管理者 日報一覧">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">管理者用 日報一覧</h2>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">日付</label>
                    <input type="date" v-model="selectedDate" @change="changeDate" class="mt-1 rounded border p-2" />
                </div>

                <div v-for="group in props.departments" :key="group.department" class="mb-8">
                    <h3 class="mb-2 text-lg font-bold">{{ group.department }}</h3>
                    <div class="overflow-hidden bg-white shadow sm:rounded-lg">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">user-id</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">description</th>
                                    <th class="px-6 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <tr v-for="d in group.diaries" :key="d.id">
                                    <td class="whitespace-nowrap px-6 py-4">{{ d.user_id }}</td>
                                    <td class="whitespace-nowrap px-6 py-4">{{ d.name }}</td>
                                    <td class="whitespace-nowrap px-6 py-4">{{ d.description }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right">
                                        <Link :href="route('admin.diaries.show', d.id)" class="rounded bg-blue-500 px-3 py-1 text-xs text-white"
                                            >詳細</Link
                                        >
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
