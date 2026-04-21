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
        // start_date / end_date はバックエンドで date:Y-m-d キャストされるため YYYY-MM-DD で届く
        const startDateOnly = s.start_date ? String(s.start_date).split('T')[0] : null;
        const endDateOnly = s.end_date ? String(s.end_date).split('T')[0] : null;

        // FullCalendar の allDay イベントは end が exclusive なので +1日する
        let endForCalendar = endDateOnly;
        if (endDateOnly) {
            try {
                const parts = endDateOnly.split('-').map(Number);
                const d = new Date(parts[0], parts[1] - 1, parts[2]);
                d.setDate(d.getDate() + 1);
                endForCalendar = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
            } catch (e) {
                endForCalendar = endDateOnly;
            }
        }

        const chosen = s.color ?? (s.progress >= 100 ? '#9ca3af' : '#2563eb');
        let textColor = '#ffffff';
        try {
            if (chosen && chosen.startsWith('#') && chosen.length === 7) {
                const r = parseInt(chosen.slice(1, 3), 16);
                const g = parseInt(chosen.slice(3, 5), 16);
                const b = parseInt(chosen.slice(5, 7), 16);
                const lum = 0.2126 * (r / 255) + 0.7152 * (g / 255) + 0.0722 * (b / 255);
                textColor = lum < 0.6 ? '#ffffff' : '#111827';
            }
        } catch (e) {}

        return {
            id: s.id,           // FullCalendar の publicId として使用
            title: s.name,
            start: startDateOnly,
            end: endForCalendar,
            allDay: true,
            color: chosen,
            backgroundColor: chosen,
            borderColor: chosen,
            textColor: textColor,
            description: s.description ?? '',
            extendedProps: {
                schedule_id: s.id,
                progress: s.progress,
                description: s.description ?? '',
            },
        };
    }),
);

// Debug: log incoming props and computed events
// debug logging removed
// Provide the converted events to the Calendar component
const diaries = ref([]);
</script>

<template>
    <AppLayout title="プロジェクトスケジュール">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">プロジェクト スケジュール</h2>
        </template>
        <div class="rounded bg-white p-6 shadow">
                    <!-- Project header: show project name and client when available -->
                    <div class="mb-4 flex items-baseline justify-between">
                        <div>
                            <h1 class="text-2xl font-semibold text-gray-900">{{ project ? project.name : 'プロジェクト' }}</h1>
                            <div class="text-sm text-gray-600">{{ client ? client.name : '' }}</div>
                        </div>
                    </div>

                    <ProjectCalendar :diaries="diaries" :events="events" :comments="props.comments" :memos="props.memos" :project="props.project" />
        </div>
    </AppLayout>
</template>
