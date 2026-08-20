<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import ClerkScheduleCalendar from '@/Components/Clerk/ClerkScheduleCalendar.vue';
</script>

<template>
    <AppLayout title="カレンダー">
        <template #header>
            <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">カレンダー</h2>
        </template>

        <div class="rounded bg-white p-6 shadow">
            <ClerkScheduleCalendar />
        </div>
    </AppLayout>
</template>
