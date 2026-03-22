<template>
    <AppLayout :title="`スケジュール設定 - ${assignment.title}`">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">スケジュール設定</h2>
        </template>

        <div class="mx-auto max-w-2xl space-y-4">
            <!-- ジョブ内容（読み取り専用） -->
            <div class="rounded bg-white p-6 shadow">
                <h3 class="mb-3 text-base font-semibold text-gray-700">依頼内容（変更不可）</h3>
                <dl class="grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
                    <div class="col-span-full flex gap-2">
                        <dt class="min-w-[7rem] font-medium text-gray-600">ジョブ名</dt>
                        <dd class="text-gray-900">{{ assignment.title || '-' }}</dd>
                    </div>
                    <div class="col-span-full flex gap-2">
                        <dt class="min-w-[7rem] font-medium text-gray-600">クライアント</dt>
                        <dd class="text-gray-900">{{ projectJob?.client?.name || '-' }}</dd>
                    </div>
                    <div class="col-span-full flex gap-2">
                        <dt class="min-w-[7rem] font-medium text-gray-600">概要</dt>
                        <dd class="flex-1 whitespace-pre-wrap text-gray-900">{{ assignment.detail || '-' }}</dd>
                    </div>
                    <div class="flex gap-2">
                        <dt class="min-w-[7rem] font-medium text-gray-600">作業種別</dt>
                        <dd class="text-gray-900">{{ assignment.work_item_type?.name || '-' }}</dd>
                    </div>
                    <div class="flex gap-2">
                        <dt class="min-w-[7rem] font-medium text-gray-600">サイズ</dt>
                        <dd class="text-gray-900">{{ assignment.size?.name || '-' }}</dd>
                    </div>
                    <div class="flex gap-2">
                        <dt class="min-w-[7rem] font-medium text-gray-600">ステージ</dt>
                        <dd class="text-gray-900">{{ assignment.stage?.name || '-' }}</dd>
                    </div>
                    <div class="flex gap-2">
                        <dt class="min-w-[7rem] font-medium text-gray-600">難易度</dt>
                        <dd class="text-gray-900">{{ assignment.difficulty_model?.name || '-' }}</dd>
                    </div>
                    <div class="flex gap-2">
                        <dt class="min-w-[7rem] font-medium text-gray-600">希望終了日</dt>
                        <dd class="text-gray-900">{{ assignment.desired_end_date || '-' }}</dd>
                    </div>
                    <div class="flex gap-2">
                        <dt class="min-w-[7rem] font-medium text-gray-600">希望時間</dt>
                        <dd class="text-gray-900">{{ formatTime(assignment.desired_time) || '-' }}</dd>
                    </div>
                    <div class="flex gap-2">
                        <dt class="min-w-[7rem] font-medium text-gray-600">見積時間</dt>
                        <dd class="text-gray-900">{{ assignment.estimated_hours != null ? `${assignment.estimated_hours}h` : '-' }}</dd>
                    </div>
                    <div class="flex gap-2">
                        <dt class="min-w-[7rem] font-medium text-gray-600">担当者</dt>
                        <dd class="text-gray-900">{{ assignment.user?.name || '-' }}</dd>
                    </div>
                </dl>
            </div>

            <!-- スケジュール入力フォーム -->
            <div class="rounded bg-white p-6 shadow">
                <h3 class="mb-4 text-base font-semibold text-gray-700">
                    {{ existingEvent ? 'スケジュール編集' : 'スケジュールをセット' }}
                </h3>

                <form @submit.prevent="submit" class="space-y-4">
                    <!-- 日付 -->
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">作業日 <span class="text-red-500">*</span></label>
                        <input
                            v-model="form.date"
                            type="date"
                            required
                            class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
                        />
                        <p v-if="errors.date" class="mt-1 text-xs text-red-500">{{ errors.date }}</p>
                    </div>

                    <!-- 開始時間 -->
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">開始時間 <span class="text-red-500">*</span></label>
                        <div class="flex items-center gap-2">
                            <select v-model="form.startHour" class="rounded border border-gray-300 px-2 py-2 text-sm focus:border-blue-500 focus:outline-none">
                                <option v-for="h in hours" :key="h" :value="h">{{ String(h).padStart(2, '0') }}</option>
                            </select>
                            <span class="text-gray-600">:</span>
                            <select v-model="form.startMinute" class="rounded border border-gray-300 px-2 py-2 text-sm focus:border-blue-500 focus:outline-none">
                                <option v-for="m in minutes" :key="m" :value="m">{{ String(m).padStart(2, '0') }}</option>
                            </select>
                        </div>
                    </div>

                    <!-- 終了時間 -->
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">終了時間 <span class="text-red-500">*</span></label>
                        <div class="flex items-center gap-2">
                            <select v-model="form.endHour" class="rounded border border-gray-300 px-2 py-2 text-sm focus:border-blue-500 focus:outline-none">
                                <option v-for="h in hours" :key="h" :value="h">{{ String(h).padStart(2, '0') }}</option>
                            </select>
                            <span class="text-gray-600">:</span>
                            <select v-model="form.endMinute" class="rounded border border-gray-300 px-2 py-2 text-sm focus:border-blue-500 focus:outline-none">
                                <option v-for="m in minutes" :key="m" :value="m">{{ String(m).padStart(2, '0') }}</option>
                            </select>
                        </div>
                    </div>

                    <p v-if="timeError" class="text-sm text-red-500">{{ timeError }}</p>

                    <div class="flex gap-3 pt-2">
                        <button
                            type="submit"
                            :disabled="submitting"
                            class="rounded bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60"
                        >
                            {{ submitting ? '保存中...' : (existingEvent ? '更新する' : '保存する') }}
                        </button>
                        <Link :href="backHref" class="rounded bg-gray-200 px-4 py-2 text-sm text-gray-700 hover:bg-gray-300">戻る</Link>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    assignment: { type: Object, required: true },
    projectJob: { type: Object, default: null },
    existingEvent: { type: Object, default: null },
});

const page = usePage();
const submitting = ref(false);
const errors = ref({});

const hours = Array.from({ length: 24 }, (_, i) => i);
const minutes = [0, 5, 10, 15, 20, 25, 30, 35, 40, 45, 50, 55];

// Parse existing event times if available
function parseEventTime(dateStr) {
    if (!dateStr) return null;
    const d = new Date(dateStr);
    return { hour: d.getHours(), minute: Math.floor(d.getMinutes() / 5) * 5 };
}

const existingStart = props.existingEvent ? parseEventTime(props.existingEvent.starts_at ?? props.existingEvent.start) : null;
const existingEnd = props.existingEvent ? parseEventTime(props.existingEvent.ends_at ?? props.existingEvent.end) : null;
const existingDate = props.existingEvent
    ? (props.existingEvent.date ?? (props.existingEvent.starts_at ?? props.existingEvent.start ?? '').slice(0, 10))
    : null;

// Suggest start time from assignment desired_time if available
function parseDesiredTime(t) {
    if (!t) return null;
    const parts = String(t).split(':');
    if (parts.length >= 2) {
        const h = parseInt(parts[0], 10);
        const m = Math.floor(parseInt(parts[1], 10) / 5) * 5;
        return { hour: h, minute: m };
    }
    return null;
}
const desiredTime = parseDesiredTime(props.assignment.desired_time);

const defaultDate = existingDate || props.assignment.desired_end_date || new Date().toISOString().slice(0, 10);
const defaultStartH = existingStart?.hour ?? desiredTime?.hour ?? 9;
const defaultStartM = existingStart?.minute ?? desiredTime?.minute ?? 0;
const defaultEndH = existingEnd?.hour ?? Math.min(defaultStartH + 1, 23);
const defaultEndM = existingEnd?.minute ?? defaultStartM;

const form = ref({
    date: defaultDate,
    startHour: defaultStartH,
    startMinute: defaultStartM,
    endHour: defaultEndH,
    endMinute: defaultEndM,
    event_id: props.existingEvent?.id ?? null,
});

const timeError = computed(() => {
    const start = form.value.startHour * 60 + form.value.startMinute;
    const end = form.value.endHour * 60 + form.value.endMinute;
    if (end <= start) return '終了時間は開始時間より後にしてください。';
    return '';
});

function formatTime(t) {
    if (!t) return '';
    const parts = String(t).split(':');
    if (parts.length >= 2) return parts[0].padStart(2, '0') + ':' + parts[1].padStart(2, '0');
    return t;
}

const backHref = computed(() => {
    // Try to go back to jobbox show via message lookup
    try {
        if (typeof route === 'function' && props.projectJob?.id) {
            return route('user.project_jobs.jobbox.index', { projectJob: props.projectJob.id });
        }
    } catch (e) {}
    return '/user/jobbox';
});

function submit() {
    if (timeError.value) return;
    submitting.value = true;
    errors.value = {};

    router.put(
        route('user.project_jobs.assignments.schedule.store', { assignment: props.assignment.id }),
        {
            date: form.value.date,
            startHour: form.value.startHour,
            startMinute: form.value.startMinute,
            endHour: form.value.endHour,
            endMinute: form.value.endMinute,
            event_id: form.value.event_id,
        },
        {
            onError: (errs) => {
                errors.value = errs;
                submitting.value = false;
            },
            onFinish: () => {
                submitting.value = false;
            },
        },
    );
}
</script>
