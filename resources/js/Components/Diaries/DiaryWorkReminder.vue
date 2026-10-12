<script setup>
defineProps({ reminder: { type: Object, default: null } });
defineEmits(['open-calendar']);

const duration = (minutes) => {
    const value = Number(minutes || 0);
    return `${Math.floor(value / 60)}時間${value % 60 ? `${value % 60}分` : ''}`;
};
</script>

<template>
    <div v-if="reminder" class="mb-4 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950" role="status">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <strong class="font-semibold">この日の作業記録を確認してください</strong>
                <p v-if="reminder.missing_minutes >= 30" class="mt-1">
                    {{ reminder.approximate ? 'A日程を仮に使った勤務時間の目安' : '通常勤務枠' }} {{ duration(reminder.scheduled_minutes) }} のうち、予定表に記録された時間は
                    {{ duration(reminder.recorded_minutes) }}。記録のない時間が {{ duration(reminder.missing_minutes) }} あります。
                </p>
                <p class="mt-1 text-xs text-amber-800">
                    必要ならカレンダーで予定を追加・修正してください。日報はこのまま保存できます。
                </p>
                <p v-if="reminder.approximate" class="mt-1 text-xs text-amber-800">勤務形態が未設定のため、空き時間は作業記録の合計から推定しています。</p>
            </div>
            <button type="button" class="shrink-0 rounded border border-amber-500 bg-white px-3 py-1.5 font-medium text-amber-900 hover:bg-amber-100" @click="$emit('open-calendar')">
                カレンダーで追記
            </button>
        </div>
    </div>
</template>
