<template>
  <div class="calendar-container">
    <FullCalendar
      :options="calendarOptions"
      @dateClick="handleDateClick"
    />
  </div>
</template>

<script setup>
import FullCalendar from '@fullcalendar/vue3'
import dayGridPlugin from '@fullcalendar/daygrid'
import interactionPlugin from '@fullcalendar/interaction'
import { router } from '@inertiajs/vue3'

const handleDateClick = (info) => {
  // 日付クリックで日報作成ページへ遷移
  // use global Ziggy route() function
  router.get(window.route('diaries.create', { date: info.dateStr }))
}

const calendarOptions = {
  plugins: [dayGridPlugin, interactionPlugin],
  initialView: 'dayGridMonth',
  events: [
    { title: '会議', start: '2025-08-10' },
    { title: '日報提出', start: '2025-08-12' }
  ],
  locale: 'ja',
  headerToolbar: {
    left: 'prev,next today',
    center: 'title',
    right: 'dayGridMonth'
  },
  selectable: true,
  dateClick: handleDateClick
}
</script>

<style scoped>
.calendar-container {
  padding: 1rem;
}

/* FullCalendarの基本スタイル */
.fc {
  font-family: inherit;
}

.fc-button {
  background-color: #3b82f6;
  border-color: #3b82f6;
  color: white;
}

.fc-button:hover {
  background-color: #2563eb;
  border-color: #2563eb;
}

.fc-daygrid-day {
  cursor: pointer;
}

.fc-daygrid-day:hover {
  background-color: #f3f4f6;
}
</style>