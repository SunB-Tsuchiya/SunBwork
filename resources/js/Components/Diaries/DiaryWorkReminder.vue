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
                <strong class="font-semibold">この日の作業記録を確認してください</strong>
                <p v-if="loadError" class="mt-1">記録の確認情報を取得できませんでした。カレンダーで予定をご確認ください。日報は保存できます。</p>
                <div v-for="issue in activeReminder?.issues || []" :key="issue.code" class="mt-2">
                    <p class="font-semibold">{{ issue.title }}</p>
                    <p class="mt-1">{{ issue.message }}</p>
                    <p class="mt-1 text-xs">{{ issue.hint }}</p>
                </div>
                <p v-if="activeReminder?.missing_minutes >= 30" class="mt-1">
                    {{ activeReminder.approximate ? 'A日程を仮に使った勤務時間の目安' : '勤務枠（当日は現在まで）' }} {{ duration(activeReminder.scheduled_minutes) }} のうち、予定表に記録された時間は
                    {{ duration(activeReminder.recorded_minutes) }}。記録のない時間が {{ duration(activeReminder.missing_minutes) }} あります。
                </p>
                <p class="mt-1 text-xs text-amber-800">
                    必要ならカレンダーで予定を追加・修正してください。日報はこのまま保存できます。
                </p>
                <p v-if="activeReminder?.approximate" class="mt-1 text-xs text-amber-800">勤務形態が未設定のため、時間不足は判定していません。</p>
            </div>
            <button type="button" class="shrink-0 rounded border border-amber-500 bg-white px-3 py-1.5 font-medium text-amber-900 hover:bg-amber-100" @click="$emit('open-calendar')">
                カレンダーで追記
            </button>
        </div>
    </div>
</template>
