<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import UserNavigationTabs from '@/Components/Tabs/UserNavigationTabs.vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    setting: { type: Object, default: null },
});

const calendarViewLabel = {
    timeGridWeek: '週表示',
    dayGridMonth: '月表示',
};
</script>

<template>
    <AppLayout title="設定">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-blue-800">設定</h2>
        </template>
        <template #tabs>
            <UserNavigationTabs active="settings" />
        </template>

        <div class="rounded bg-white p-6 shadow">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-800">ユーザー設定</h3>
                <Link :href="route('user.settings.edit')" class="rounded bg-blue-600 px-4 py-2 text-sm text-white hover:bg-blue-700">
                    編集
                </Link>
            </div>

            <dl class="divide-y divide-gray-100">
                <div class="flex px-2 py-3">
                    <dt class="w-48 text-sm font-medium text-gray-500">基本勤務形態</dt>
                    <dd class="text-sm text-gray-900">
                        {{ setting?.worktype?.name ?? '未設定' }}
                    </dd>
                </div>
                <div class="flex px-2 py-3">
                    <dt class="w-48 text-sm font-medium text-gray-500">カレンダー表示</dt>
                    <dd class="text-sm text-gray-900">
                        {{ calendarViewLabel[setting?.calendar_view ?? 'timeGridWeek'] ?? '週表示' }}
                    </dd>
                </div>
            </dl>
        </div>
    </AppLayout>
</template>
