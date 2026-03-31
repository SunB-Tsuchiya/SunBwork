<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import AssignmentDetailCard from '@/Components/AssignmentDetailCard.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps({ event: Object, hide_edit: { type: Boolean, default: false } });

const assignment = computed(() => props.event?.project_job_assignment ?? null);

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

function durationText() {
    const s = props.event?.start ?? props.event?.starts_at;
    const e = props.event?.end ?? props.event?.ends_at;
    if (!s || !e) return '';
    const mins = Math.max(0, Math.round((new Date(e) - new Date(s)) / 60000));
    const h = Math.floor(mins / 60);
    const m = mins % 60;
    if (h > 0 && m > 0) return `${h}時間${m}分`;
    if (h > 0) return `${h}時間`;
    return `${m}分`;
}

function confirmDelete() {
    if (!confirm('この予定を削除しますか？')) return;
    router.delete(route('events.destroy', { event: props.event.id }));
}

function submitComplete() {
    if (!confirm('このジョブを完了としてマークしますか？')) return;
    router.post(route('events.complete', { event: props.event.id }));
}

const eventTypeLabel = computed(() => props.event?.event_item_type?.name ?? null);
</script>

<template>
    <AppLayout title="イベント詳細">
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
                    <div class="flex flex-wrap items-center gap-6">
                        <div>
                            <div class="text-xs text-gray-500">開始</div>
                            <div class="mt-0.5 text-sm font-medium text-gray-900">{{ formatJstDateTime(event.start) }}</div>
                        </div>
                        <div class="text-gray-300">→</div>
                        <div>
                            <div class="text-xs text-gray-500">終了</div>
                            <div class="mt-0.5 text-sm font-medium text-gray-900">{{ formatJstDateTime(event.end) }}</div>
                        </div>
                        <div class="ml-auto">
                            <div class="text-xs text-gray-500">作業時間</div>
                            <div class="mt-0.5 text-base font-bold text-indigo-700">{{ durationText() }}</div>
                        </div>
                    </div>
                </div>

                <!-- 詳細テキスト -->
                <div v-if="event.description" class="border-t px-5 py-4">
                    <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">詳細</h4>
                    <p class="whitespace-pre-wrap text-sm leading-relaxed text-gray-700">{{ event.description }}</p>
                </div>

                <!-- ボタン類 -->
                <div class="flex flex-wrap items-center gap-2 border-t bg-gray-50 px-5 py-3">
                    <Link v-if="!hide_edit"
                          :href="route('events.edit', event.id)"
                          class="inline-flex items-center gap-1.5 rounded bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700">
                        編集
                    </Link>
                    <button @click="confirmDelete"
                            class="inline-flex items-center gap-1.5 rounded bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700">
                        削除
                    </button>
                    <template v-if="event.project_job_assignment_id">
                        <button
                            @click="submitComplete"
                            :disabled="isEventCompleted()"
                            :class="isEventCompleted()
                                ? 'inline-flex cursor-not-allowed items-center gap-1.5 rounded bg-yellow-800 px-3 py-1.5 text-sm font-medium text-white opacity-70'
                                : 'inline-flex items-center gap-1.5 rounded bg-yellow-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-yellow-700'"
                        >
                            {{ isEventCompleted() ? '完了済み' : '完了する' }}
                        </button>
                    </template>
                    <button @click="$router?.back ? $router.back() : window.history.back()"
                            class="ml-auto inline-flex items-center gap-1.5 rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300">
                        戻る
                    </button>
                </div>
            </div>

            <!-- ジョブ割り当て詳細カード -->
            <div v-if="assignment">
                <h2 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">紐づきジョブ割り当て</h2>
                <AssignmentDetailCard :assignment="assignment" />
            </div>

        </div>
    </AppLayout>
</template>
