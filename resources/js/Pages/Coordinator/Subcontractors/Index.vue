<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';

const props = defineProps({ subcontractors: Array });
</script>

<template>
    <AppLayout title="外注先一覧">
        <template #header>
            <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">外注先管理</h2>
        </template>
        <template #headerExtras>
            <Link :href="route('coordinator.subcontractors.create')" class="rounded bg-green-600 px-4 py-2 font-bold text-white hover:bg-green-700">
                新規作成
            </Link>
        </template>

        <div class="rounded bg-white px-4 py-6 sm:p-6 shadow">
            <template v-if="props.subcontractors.length === 0">
                <p class="py-8 text-gray-500">外注先はまだ登録されていません</p>
            </template>
            <template v-else>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">名前 / 会社名</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">担当Co</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">割当数</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">操作</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr v-for="sub in props.subcontractors" :key="sub.id"
                                class="cursor-pointer hover:bg-gray-50"
                                @click="router.visit(route('coordinator.subcontractors.show', sub.id))"
                            >
                                <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">
                                    <Link :href="route('coordinator.subcontractors.show', sub.id)" class="text-green-700 hover:underline">
                                        {{ sub.name }}
                                    </Link>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700">
                                    <span v-if="sub.coordinators && sub.coordinators.length">
                                        {{ sub.coordinators.map((c) => c.name).join('、') }}
                                    </span>
                                    <span v-else class="text-gray-400">未設定</span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-700">{{ sub.assignments_count }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm">
                                    <Link :href="route('coordinator.subcontractors.edit', sub.id)" class="text-blue-600 hover:text-blue-900" @click.stop>編集</Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
