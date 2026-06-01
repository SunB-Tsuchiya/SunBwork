<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import SuperAdminGlobalGuard from '@/Components/SuperAdminGlobalGuard.vue';
import { Link, router } from '@inertiajs/vue3';

defineProps({
    sent:         { type: Array, default: () => [] },
    drafts:       { type: Array, default: () => [] },
    isGlobalMode: { type: Boolean, default: false },
});

const targetLabel = (type) => ({
    all: '全員',
    employees_only: '社員のみ',
    individual: '個別選択',
}[type] ?? type);

const goToShow = (id) => router.visit(route('leader.announcements.show', { announcement: id }));
</script>

<template>
    <AppLayout title="お知らせ通知">
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">お知らせ通知</h2>
        </template>

        <SuperAdminGlobalGuard :show="isGlobalMode">
        <div class="space-y-6">

            <!-- 下書き -->
            <div class="rounded bg-white px-4 py-6 sm:p-6 shadow">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-gray-700">
                        下書き
                        <span v-if="drafts.length > 0" class="ml-1 rounded-full bg-yellow-100 px-2 py-0.5 text-xs font-medium text-yellow-800">{{ drafts.length }}</span>
                    </h3>
                    <Link
                        :href="route('leader.announcements.create')"
                        class="rounded bg-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-orange-700"
                    >
                        新規作成
                    </Link>
                </div>

                <div v-if="drafts.length === 0" class="py-6 text-center text-sm text-gray-400">
                    下書きはありません
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">作成日時</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">タイトル</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">宛先</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">受信者数</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr
                                v-for="a in drafts"
                                :key="a.id"
                                class="cursor-pointer hover:bg-yellow-50"
                                @click="goToShow(a.id)"
                            >
                                <td class="px-4 py-3 text-sm text-gray-600">{{ a.created_at }}</td>
                                <td class="px-4 py-3 text-sm font-medium text-yellow-700 underline">{{ a.title }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ targetLabel(a.target_type) }}</td>
                                <td class="px-4 py-3 text-right text-sm text-gray-600">{{ a.recipients_count }}人</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 送信済み -->
            <div class="rounded bg-white px-4 py-6 sm:p-6 shadow">
                <h3 class="mb-4 text-base font-semibold text-gray-700">送信済み</h3>

                <div v-if="sent.length === 0" class="py-6 text-center text-sm text-gray-400">
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
                                v-for="a in sent"
                                :key="a.id"
                                class="cursor-pointer hover:bg-orange-50"
                                @click="goToShow(a.id)"
                            >
                                <td class="px-4 py-3 text-sm text-gray-600">{{ a.created_at }}</td>
                                <td class="px-4 py-3 text-sm font-medium text-orange-700 underline">{{ a.title }}</td>
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

        </div>
        </SuperAdminGlobalGuard>
    </AppLayout>
</template>
