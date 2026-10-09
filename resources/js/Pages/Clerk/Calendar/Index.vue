<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import ClerkScheduleCalendar from '@/Components/Clerk/ClerkScheduleCalendar.vue';
import { Link } from '@inertiajs/vue3';

defineProps({ departmentWorkEnabled: { type: Boolean, default: false } });
</script>

<template>
    <AppLayout title="カレンダー">
        <template #header>
            <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">カレンダー</h2>
        </template>

        <div v-if="departmentWorkEnabled" class="mb-3 flex flex-wrap gap-2 rounded bg-white p-3 shadow">
            <Link :href="route('calendar.index')" class="rounded border border-blue-300 bg-blue-50 px-3 py-2 text-sm text-blue-700">個人カレンダーで作業予定を入力</Link>
            <Link :href="route('schedule.index', { tab: 'work' })" class="rounded border border-blue-300 px-3 py-2 text-sm text-blue-700">部署の作業・実績を見る</Link>
        </div>
        <div class="rounded bg-white p-6 shadow">
            <ClerkScheduleCalendar />
        </div>
    </AppLayout>
</template>
