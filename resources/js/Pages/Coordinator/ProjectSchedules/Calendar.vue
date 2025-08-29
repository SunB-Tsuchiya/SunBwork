<script setup>
import ProjectCalendar from '@/Components/ProjectCalendar.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { ref } from 'vue';

const props = defineProps({
    schedules: { type: Array, default: () => [] },
    project: { type: Object, default: null },
    client: { type: Object, default: null },
    comments: { type: Array, default: () => [] },
    memos: { type: Array, default: () => [] },
});

// Convert schedules to FullCalendar events
const events = ref(
    (props.schedules || []).map((s) => {
        // normalize start/end to date-only (YYYY-MM-DD) so allDay events don't shift due to TZ
        const fmt = (v) => {
            if (!v) return null;
            try {
                return String(v).split('T')[0];
            } catch (e) {
                return String(v);
            }
        };
        // FullCalendar treats allDay end as exclusive, so add 1 day to end when passing as allDay
        const startDateOnly = fmt(s.start_date);
        const endDateOnly = fmt(s.end_date);
        let endForCalendar = endDateOnly;
        if (endDateOnly) {
            try {
                const d = new Date(endDateOnly);
                d.setDate(d.getDate() + 1);
                endForCalendar = d.toISOString().split('T')[0];
            } catch (e) {
                endForCalendar = endDateOnly;
            }
        }
        return {
            title: s.name,
            start: startDateOnly,
            end: endForCalendar,
            allDay: true,
            color: s.progress >= 100 ? '#9ca3af' : '#2563eb',
            extendedProps: { schedule_id: s.id, progress: s.progress },
        };
    }),
);

// Debug: log incoming props and computed events
console.log('Coordinator ProjectSchedules props.schedules', props.schedules);
// Provide the converted events to the Calendar component
const diaries = ref([]);
console.log('Coordinator ProjectSchedules computed events', events.value);
</script>

<template>
    <AppLayout title="プロジェクトスケジュール">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">プロジェクト スケジュール</h2>
        </template>
        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <!-- Project header: show project name and client when available -->
                <div class="mb-4 flex items-baseline justify-between">
                    <div>
                        <h1 class="text-2xl font-semibold text-gray-900">{{ project ? project.name : 'プロジェクト' }}</h1>
                        <div class="text-sm text-gray-600">{{ client ? client.name : '' }}</div>
                    </div>
                </div>

                <ProjectCalendar :diaries="diaries" :events="events" :comments="props.comments" :memos="props.memos" :project="props.project" />
            </div>
        </div>
    </AppLayout>
</template>
