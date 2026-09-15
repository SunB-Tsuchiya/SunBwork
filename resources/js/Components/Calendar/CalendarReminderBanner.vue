<script setup>
defineProps({ reminders: { type: Array, default: () => [] } });

const colorClasses = {
    indigo: 'border-indigo-500 bg-indigo-50 text-indigo-950',
    blue: 'border-blue-500 bg-blue-50 text-blue-950',
    cyan: 'border-cyan-500 bg-cyan-50 text-cyan-950',
    teal: 'border-teal-500 bg-teal-50 text-teal-950',
    green: 'border-green-500 bg-green-50 text-green-950',
    yellow: 'border-yellow-500 bg-yellow-50 text-yellow-950',
    orange: 'border-orange-500 bg-orange-50 text-orange-950',
    red: 'border-red-500 bg-red-50 text-red-950',
    pink: 'border-pink-500 bg-pink-50 text-pink-950',
    purple: 'border-purple-500 bg-purple-50 text-purple-950',
    gray: 'border-gray-500 bg-gray-50 text-gray-950',
};

const reminderClasses = (colorKey) => colorClasses[colorKey] ?? colorClasses.orange;
</script>

<template>
    <div v-if="reminders.length" class="mb-2 space-y-1" aria-label="事務からのお知らせ">
        <div
            v-for="reminder in reminders"
            :key="reminder.id"
            class="flex min-h-10 items-center justify-center rounded border-2 px-4 py-1.5 text-center text-sm font-bold shadow-sm"
            :class="reminderClasses(reminder.color_key)"
        >
            <span class="break-words">事務からのお知らせ：{{ reminder.content }}</span>
        </div>
    </div>
</template>
