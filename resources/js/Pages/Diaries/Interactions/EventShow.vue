<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import AssignmentDetailCard from '@/Components/AssignmentDetailCard.vue';
import { computed } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps({ event: Object, diary_id: { type: [String, Number], default: null } });

const assignment = computed(() => props.event?.project_job_assignment ?? null);

const eventTypeLabel = computed(() => props.event?.event_item_type?.name ?? null);

function isEventCompleted() {
    try {
        if (!props.event) return false;
        if (props.event.title && String(props.event.title).indexOf('【完了】') === 0) return true;
        if (assignment.value?.completed) return true;
        const s = assignment.value?.status_model ?? assignment.value?.statusModel;
        if (s?.key === 'completed' || String(s?.name || '').indexOf('完了') !== -1) return true;
        return false;
    } catch (e) {
        return false;
    }
}

function formatJstDateTime(dateStr) {
    if (!dateStr) return '';
    const s = String(dateStr);
    const m = s.match(/(\d{4}-\d{2}-\d{2})[T ]?(\d{2}:\d{2})/);
    if (m) return `${m[1]} ${m[2]}`;
    return s.replace('T', ' ').substring(0, 16);
}

function formatMins(mins) {
    const h = Math.floor(mins / 60);
    const m = mins % 60;
    if (h > 0 && m > 0) return `${h}時間${m}分`;
    if (h > 0) return `${h}時間`;
    return `${m}分`;
}

const interruptionMins = computed(() => props.event?.interruption_minutes ?? 0);

function totalRecordedMins() {
    const s = props.event?.start ?? props.event?.starts_at;
    const e = props.event?.end ?? props.event?.ends_at;
    if (!s || !e) return 0;
    return Math.max(0, Math.round((new Date(e) - new Date(s)) / 60000));
}

function durationText() { return formatMins(totalRecordedMins()); }

function actualDurationText() {
    return formatMins(Math.max(0, totalRecordedMins() - interruptionMins.value));
}

function onBack() {
    try {
        if (typeof window !== 'undefined' && window.history && window.history.length > 1) {
            window.history.back();
            return;
        }
    } catch (e) { /* ignore */ }
    try {
        if (props.diary_id) {
            const p = typeof window !== 'undefined' && window.location?.pathname ? window.location.pathname : '';
            const prefixMatch = p.match(/\/(leader|admin|admin2)(\/|$)/);
            const prefix = prefixMatch ? prefixMatch[1] : '';
            if (prefix) {
                try { window.location.href = route(`${prefix}.diaryinteractions.show`, { diary: props.diary_id }); return; } catch (e) { /* fall through */ }
            }
            try { window.location.href = route('diaryinteractions.interactions.index'); } catch (e) { /* ignore */ }
            return;
        }
    } catch (e) { /* ignore */ }
    window.location.href = route('diaryinteractions.interactions.index');
}
</script>

<template>
    <AppLayout title="スケジュール（閲覧）">
        <div class="mx-auto max-w-2xl space-y-4">

            <!-- イベント情報カード -->
            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <!-- カードヘッダー -->
                <div class="flex items-start justify-between gap-3 border-b bg-gray-50 px-5 py-4">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span v-if="eventTypeLabel"
                                  class="inline-flex items-center rounded-full bg-sky-100 px-2.5 py-0.5 text-xs font-medium text-sky-700">
                                {{ eventTypeLabel }}
                            </span>
                            <span v-if="isEventCompleted()"
                                  class="inline-flex items-center rounded-full bg-yellow-100 px-2.5 py-0.5 text-xs font-semibold text-yellow-800">
                                完了済み
                            </span>
                        </div>
                        <h1 class="mt-1 text-xl font-bold text-gray-900">{{ event.title }}</h1>
                    </div>
                </div>

                <!-- 日時 -->
                <div class="px-5 py-4">
                    <h4 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-400">日時</h4>
                    <div class="flex flex-wrap items-start gap-6">
                        <div>
                            <div class="text-xs text-gray-500">開始</div>
                            <div class="mt-0.5 text-sm font-medium text-gray-900">{{ formatJstDateTime(event.start) }}</div>
                        </div>
                        <div class="mt-4 text-gray-300">→</div>
                        <div>
                            <div class="text-xs text-gray-500">終了</div>
                            <div class="mt-0.5 text-sm font-medium text-gray-900">{{ formatJstDateTime(event.end) }}</div>
                        </div>
                        <div class="ml-auto text-right">
                            <div class="text-xs text-gray-500">作業時間</div>
                            <div class="mt-0.5 text-base font-bold text-indigo-700">{{ actualDurationText() }}</div>
                            <div v-if="interruptionMins > 0" class="mt-1 space-y-0.5 text-xs text-gray-400">
                                <div>記録 {{ durationText() }}</div>
                                <div class="text-orange-600">中断 −{{ formatMins(interruptionMins) }}</div>
                            </div>
                        </div>
                    </div>
                    <!-- 中断の注記 -->
                    <div v-if="interruptionMins > 0"
                         class="mt-3 rounded border border-orange-200 bg-orange-50 px-3 py-2 text-xs text-orange-700">
                        この予定は差し込み作業により合計 {{ formatMins(interruptionMins) }} 中断されました。
                    </div>
                </div>

                <!-- 詳細テキスト（ジョブイベントでない場合のみ表示） -->
                <div v-if="event.description && !assignment" class="border-t px-5 py-4">
                    <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">詳細</h4>
                    <p class="whitespace-pre-wrap text-sm leading-relaxed text-gray-700">{{ event.description }}</p>
                </div>

                <!-- ボタン類（読み取り専用：戻るのみ） -->
                <div class="flex flex-wrap items-center gap-2 border-t bg-gray-50 px-5 py-3">
                    <button type="button" @click.prevent="onBack"
                            class="ml-auto inline-flex items-center gap-1.5 rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300">
                        戻る
                    </button>
                </div>
            </div>

            <!-- ジョブ割り当て詳細カード -->
            <div v-if="assignment">
                <AssignmentDetailCard :assignment="assignment" />
            </div>

        </div>
    </AppLayout>
</template>
