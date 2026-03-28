<script setup>
import Calendar from '@/Components/Calendar.vue';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps({
    user: {
        type: Object,
        required: false,
    },
    diaries: {
        type: Array,
        default: () => [],
    },
    events: {
        type: Array,
        default: () => [],
    },
    jobs: {
        type: Array,
        default: () => [],
    },
    calendarView: {
        type: String,
        default: 'timeGridWeek',
    },
    defaultWorktype: {
        type: Object,
        default: null,
    },
    worktypes: {
        type: Array,
        default: () => [],
    },
    dailyWorktypes: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <AppLayout title="予定表">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">予定表</h2>
        </template>
        <div class="rounded bg-white p-6 shadow">
            <Calendar
                :diaries="diaries"
                :events="events"
                :jobs="jobs"
                :initial-view="calendarView"
                :default-worktype="defaultWorktype"
                :worktypes="worktypes"
                :daily-worktypes="dailyWorktypes"
            />
        </div>
    </AppLayout>
</template>
