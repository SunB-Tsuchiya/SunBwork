<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    grouped: Array,
});
</script>

<template>
    <AppLayout title="お知らせ">
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">お知らせ</h2>
        </template>

        <div class="rounded bg-white px-4 py-6 sm:p-6 shadow">
            <div v-if="grouped.length === 0" class="py-12 text-center text-gray-500">
                お知らせはありません
            </div>

            <div v-else class="space-y-6">
                <div v-for="group in grouped" :key="group.date">
                    <!-- 日付ヘッダー -->
                    <div class="mb-2 flex items-center gap-3">
                        <span class="text-sm font-semibold text-gray-700">{{ group.date }}</span>
                        <hr class="flex-1 border-gray-200" />
                    </div>

                    <!-- その日のお知らせ一覧 -->
                    <div class="divide-y divide-gray-100 overflow-hidden rounded border border-gray-200">
                        <Link
                            v-for="item in group.items"
                            :key="item.id"
                            :href="route('announcements.show', { recipient: item.id })"
                            class="flex items-center gap-3 px-4 py-3 transition hover:bg-gray-50"
                        >
                            <!-- 未読・既読マーク -->
                            <span
                                class="inline-flex h-2 w-2 flex-shrink-0 rounded-full"
                                :class="item.is_read ? 'bg-gray-300' : 'bg-red-500'"
                            ></span>

                            <div class="flex-1 min-w-0">
                                <p
                                    class="truncate text-sm"
                                    :class="item.is_read ? 'text-gray-600' : 'font-semibold text-gray-900'"
                                >
                                    {{ item.title }}
                                </p>
                                <p class="text-xs text-gray-400">{{ item.sender }} · {{ item.created_at }}</p>
                            </div>

                            <span
                                v-if="!item.is_read"
                                class="flex-shrink-0 rounded-full bg-red-100 px-2 py-0.5 text-xs text-red-600"
                            >
                                未読
                            </span>
                            <span
                                v-else
                                class="flex-shrink-0 rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500"
                            >
                                既読
                            </span>
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
