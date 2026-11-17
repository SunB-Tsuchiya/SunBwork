<script setup>
import axios from 'axios';
import { ref, watch, onBeforeUnmount } from 'vue';
const props = defineProps({ reminder: { type: Object, default: null }, date: String, reminderUrl: String });
const activeReminder = ref(props.reminder);
const loadError = ref(false);
let controller;
let sequence = 0;
watch(() => [props.date, props.reminderUrl], async ([date, url]) => {
    controller?.abort();
    const current = ++sequence;
    activeReminder.value = null;
    loadError.value = false;
    if (!url || !/^\d{4}-\d{2}-\d{2}$/.test(date || '')) return;
    controller = new AbortController();
    try {
        const { data } = await axios.get(url, { params: { date }, signal: controller.signal });
        if (current === sequence) activeReminder.value = data.reminder;
    } catch (error) { if (current === sequence && error.code !== 'ERR_CANCELED') loadError.value = true; }
}, { immediate: true });
onBeforeUnmount(() => { ++sequence; controller?.abort(); });
defineEmits(['open-calendar']);

const duration = (minutes) => {
    const value = Number(minutes || 0);
    return `${Math.floor(value / 60)}時間${value % 60 ? `${value % 60}分` : ''}`;
};
</script>

<template>
    <div v-if="activeReminder || loadError" class="mb-4 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950" role="status">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <strong class="block text-base font-bold">このままでも日報を保存できます。</strong>
                <p class="mt-1 text-xs text-amber-800">以下は記録の見直し案内です。必要な項目は、あとからカレンダーで修正できます。</p>
                <ul v-if="loadError" class="mt-2 list-disc space-y-1 pl-5"><li>確認情報を取得できませんでした。必要に応じてカレンダーをご確認ください。</li></ul>
                <div v-for="issue in activeReminder?.issues || []" :key="issue.code" class="mt-3">
                    <p class="font-semibold">{{ issue.title }}</p>
                    <ul class="mt-1 list-disc space-y-1 pl-5"><li v-for="(line,index) in (issue.bullets || [issue.message,issue.hint]).filter(Boolean)" :key="index">{{ line }}</li></ul>
                </div>
                <div v-if="activeReminder?.missing_minutes >= 30" class="mt-3">
                    <p class="font-semibold">記録されていない時間があります</p>
                    <ul class="mt-1 list-disc space-y-1 pl-5">
                        <li>勤務枠{{ activeReminder.approximate ? 'の目安' : '（当日は現在まで）' }}：{{ duration(activeReminder.scheduled_minutes) }}</li>
                        <li>記録済み：{{ duration(activeReminder.recorded_minutes) }}／記録なし：{{ duration(activeReminder.missing_minutes) }}</li>
                    </ul>
                </div>
                <p v-if="activeReminder?.approximate" class="mt-2 text-xs text-amber-800">勤務形態が未設定のため、時間不足は判定していません。</p>
            </div>
            <button type="button" class="shrink-0 rounded border border-amber-500 bg-white px-3 py-1.5 font-medium text-amber-900 hover:bg-amber-100" @click="$emit('open-calendar')">
                カレンダーを確認
            </button>
        </div>
    </div>
</template>
