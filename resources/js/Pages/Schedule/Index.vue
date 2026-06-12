<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import ScheduleCalendar from '@/Components/Schedule/ScheduleCalendar.vue';

defineProps({
    initialDate:    { type: String, default: '' },
    overlays:       { type: Array,  default: () => [] },
    rooms:          { type: Array,  default: () => [] },
    eventItemTypes: { type: Array,  default: () => [] },
    companies:      { type: Array,  default: () => [] },
    departments:    { type: Array,  default: () => [] },
});
</script>

<template>
    <AppLayout title="予定表">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">予定表</h2>
        </template>

        <div class="rounded-lg bg-white p-4 shadow" style="min-height: calc(100vh - 200px)">
            <ScheduleCalendar
                :initial-date="initialDate"
                :initial-overlays="overlays"
                :event-item-types="eventItemTypes"
                :rooms="rooms"
                :companies="companies"
                :departments="departments" />
        </div>
    </AppLayout>
</template>
