<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import Calendar from '@/Components/Calendar.vue';

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
});
</script>

<template>
  <AppLayout title="予定表">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">予定表</h2>
    </template>
    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <Calendar :diaries="diaries" :events="events" />
      </div>
    </div>
  </AppLayout>
</template>
