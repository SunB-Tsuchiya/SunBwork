<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import SuperAdminGlobalGuard from '@/Components/SuperAdminGlobalGuard.vue';
import { Link, router } from '@inertiajs/vue3';

defineProps({
    announcements: Array,
    isGlobalMode: { type: Boolean, default: false },
});

const targetLabel = (type) => ({
    all: '全員',
    employees_only: '社員のみ',
    individual: '個別選択',
}[type] ?? type);

const goToShow = (id) => {
    router.visit(route('clerk.announcements.show', { announcement: id }));
};
</script>

<template>
    <AppLayout title="お知らせ通知">
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">お知らせ通知</h2>
        </template>

        <SuperAdminGlobalGuard :show="isGlobalMode">
        <div class="rounded bg-white px-4 py-6 sm:p-6 shadow">
            <div class="mb-4 flex justify-end">
                <Link
                    :href="route('clerk.announcements.create')"
                    class="rounded bg-purple-600 px-4 py-2 text-sm font-medium text-white hover:bg-purple-700"
                >
                    新規お知らせ作成
                </Link>
            </div>

            <div v-if="announcements.length === 0" class="py-10 text-center text-gray-500">
                送信したお知らせはありません
            </div>

            <div v-else class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">送信日時</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">タイトル</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">宛先</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">送信数</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">既読</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    <tr
                        v-for="a in announcements"
                        :key="a.id"
                        class="cursor-pointer hover:bg-purple-50"
                        @click="goToShow(a.id)"
                    >
                        <td class="px-4 py-3 text-sm text-gray-600">{{ a.created_at }}</td>
                        <td class="px-4 py-3 text-sm font-medium text-purple-700 underline">{{ a.title }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ targetLabel(a.target_type) }}</td>
                        <td class="px-4 py-3 text-right text-sm text-gray-600">{{ a.recipients_count }}人</td>
                        <td class="px-4 py-3 text-right text-sm">
                            <span :class="a.read_count === a.recipients_count ? 'text-green-600 font-medium' : 'text-gray-500'">
                                {{ a.read_count }} / {{ a.recipients_count }}
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
            </div>
        </div>
        </SuperAdminGlobalGuard>
    </AppLayout>
</template>
