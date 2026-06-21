<script setup>
import UserCalendar from '@/Components/Calendar/UserCalendar.vue';
import AppLayout    from '@/layouts/AppLayout.vue';

defineProps({
    user:               { type: Object, default: null },
    eventItemTypes:     { type: Array,  default: () => [] },
    meetingDefinitions: { type: Array,  default: () => [] },
    rooms:              { type: Array,  default: () => [] },
    companies:          { type: Array,  default: () => [] },
    departments:        { type: Array,  default: () => [] },
    worktypes:          { type: Array,  default: () => [] },
    dailyWorktypes:     { type: Array,  default: () => [] },
    dailyBreaks:        { type: Array,  default: () => [] },
    defaultBreak:       { type: Object, default: () => ({ start: '12:00', end: '13:00' }) },
    defaultWorktype:    { type: Object, default: null },
});
</script>

<template>
    <AppLayout title="カレンダー">
        <template #header>
            <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">カレンダー</h2>
        </template>

        <!-- 会議室予約テスト中バナー（rooms が存在するときのみ表示） -->
        <div v-if="rooms.length"
            class="mb-2 flex items-center gap-2 rounded border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs text-amber-800">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 text-amber-500" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
            </svg>
            <span><strong>会議室予約はテスト機能です。</strong>会議室の予約は引き続き Outlook で行ってください。</span>
        </div>

        <UserCalendar
            :event-item-types="eventItemTypes"
            :meeting-definitions="meetingDefinitions"
            :rooms="rooms"
            :companies="companies"
            :departments="departments"
            :worktypes="worktypes"
            :daily-worktypes="dailyWorktypes"
            :daily-breaks="dailyBreaks"
            :default-break="defaultBreak"
            :default-worktype="defaultWorktype"
        />
    </AppLayout>
</template>
