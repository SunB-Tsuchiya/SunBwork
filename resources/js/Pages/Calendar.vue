<script setup>
import UserCalendar from '@/Components/Calendar/UserCalendar.vue';
import CalendarReminderBanner from '@/Components/Calendar/CalendarReminderBanner.vue';
import AppLayout    from '@/layouts/AppLayout.vue';

defineProps({
    user:               { type: Object, default: null },
    initialDate:        { type: String, default: '' },
    initialView:        { type: String, default: '' },
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
    calendarReminders:  { type: Array,  default: () => [] },
    departmentWorkEnabled: { type: Boolean, default: false },
    absencePlansEnabled: {type:Boolean,default:false},
    personalMonthEnabled: { type: Boolean, default: false },
    personalWorkEnabled: { type: Boolean, default: false },
});
</script>

<template>
    <AppLayout title="カレンダー">
        <template #header>
            <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">カレンダー</h2>
        </template>

        <CalendarReminderBanner :reminders="calendarReminders" />

        <UserCalendar
            :absence-plans-enabled="absencePlansEnabled"
            :personal-month-enabled="personalMonthEnabled"
            :initial-date="initialDate"
            :initial-view="initialView"
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
            :department-work-enabled="departmentWorkEnabled"
            :personal-work-enabled="personalWorkEnabled"
        />
    </AppLayout>
</template>
