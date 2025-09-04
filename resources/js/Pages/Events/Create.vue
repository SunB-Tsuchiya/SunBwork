<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
// No Quill: use simple textarea for details
import { getCurrentInstance, nextTick, onMounted, ref, watch } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps({ date: String, job: { type: Object, default: null } });

// Build initial content by including job assignment metadata if available
function buildJobDetails(job) {
    if (!job) return '';
    const assignedName = job.user?.name || job.assigned_user_name || '（未割当）';
    const detailsText = job.details || job.detail || job.description || job.body || '';
    const start = job.desired_start_date || job.preferred_date || job.start_date || '';
    const end = job.desired_end_date || job.end_date || '';
    const pjId = job.project_job_id || job.project_job || '';
    const difficulty = job.difficulty || job.level || '';

    const lines = [];
    const jobName = job.title || job.name || (pjId ? `ID:${pjId}` : '');
    if (jobName) lines.push(`ジョブ名: ${jobName}`);
    if (difficulty) lines.push(`難易度: ${difficulty}`);
    lines.push(`担当ユーザー: ${assignedName}`);
    if (start || end) lines.push(`希望期間: ${start || '-'} 〜 ${end || '-'}`);
    lines.push('詳細:');
    if (detailsText) lines.push(detailsText);
    return lines.join('\n') + '\n';
}

const _jobText = buildJobDetails(props.job) + (props.job && props.job.details ? '\n' + props.job.details : '');
const content = ref(_jobText);
// derive default start/end hour/minute from job.desired_time if available (expected formats: 'HH:MM', 'HH:MM:SS' or ISO time)
function parseDesiredTime(t) {
    if (!t) return null;
    const core = String(t).split('T').pop().split('.')[0];
    const parts = core.split(':');
    if (parts.length >= 2) return { h: parts[0].padStart(2, '0'), m: parts[1].padStart(2, '0') };
    return null;
}
const _desired = props.job && props.job.desired_time ? parseDesiredTime(props.job.desired_time) : null;
const defaultStartHour = _desired ? _desired.h : '09';
const defaultStartMinute = _desired ? _desired.m : '00';
const defaultEndHour = _desired ? String(parseInt(_desired.h, 10) + 1).padStart(2, '0') : '10';
const defaultEndMinute = '00';
const form = useForm({
    date: props.job && props.job.preferred_date ? props.job.preferred_date : props.date || '',
    // do NOT inject project_job_id into title; keep title as-is
    title: props.job && props.job.title ? props.job.title : '',
    // include job details in description (plain text with newlines)
    description: _jobText,
    startHour: defaultStartHour,
    startMinute: defaultStartMinute,
    endHour: defaultEndHour,
    endMinute: defaultEndMinute,
    content,
    job_id: props.job && props.job.id ? props.job.id : null,
    files: [],
});

const errorMessage = ref('');

console.log('[Create.vue] 初期 content:', content.value);
console.log('[Create.vue] 初期 description:', form.description);
console.log('[Create.vue] props:', props);
console.log('[Create.vue] derived defaults:', { defaultStartHour, defaultStartMinute, defaultEndHour, defaultEndMinute });
console.log('[Create.vue] initial form:', {
    date: form.date,
    title: form.title,
    startHour: form.startHour,
    startMinute: form.startMinute,
    endHour: form.endHour,
    endMinute: form.endMinute,
});

onMounted(async () => {
    try {
        console.log('[Create.vue] onMounted props.job:', props.job);
        await nextTick();
        const editor = document.querySelector('.ql-editor');
        console.log('[Create.vue] ql-editor innerHTML:', editor ? editor.innerHTML : null);
        console.log('[Create.vue] ql-editor textContent:', editor ? editor.textContent : null);
        // If editor appears empty, force a brief update to content to trigger Quill refresh
        if (editor && (!editor.textContent || editor.textContent.trim() === '')) {
            console.log('[Create.vue] ql-editor empty — forcing content update');
            // append a space then remove it to trigger update
            content.value = (content.value || '') + ' ';
            await nextTick();
            content.value = (content.value || '').trim();
            await nextTick();
            console.log('[Create.vue] after force ql-editor textContent:', editor.textContent);
        }
    } catch (e) {
        console.error('[Create.vue] onMounted debug error', e);
    }
});

function onContentFocus() {
    console.log('[Create.vue] content onFocus:', content.value);
}
function onContentBlur() {
    console.log('[Create.vue] content onBlur:', content.value);
}
function onDescriptionFocus() {
    console.log('[Create.vue] description onFocus:', form.description);
}
function onDescriptionBlur() {
    console.log('[Create.vue] description onBlur:', form.description);
}

// helper: return ISO-like timestamp string for comparison
function timestampForCompare(dateStr, hh, mm) {
    return `${dateStr} ${hh}:${mm}`;
}

const submit = () => {
    // clear previous error
    errorMessage.value = '';
    form.description = content.value;
    // 時刻チェック: Date オブジェクトで比較
    const newStart = new Date(`${form.date}T${form.startHour}:${form.startMinute}:00`);
    const newEnd = new Date(`${form.date}T${form.endHour}:${form.endMinute}:00`);
    if (isNaN(newStart.getTime()) || isNaN(newEnd.getTime())) {
        errorMessage.value = '開始/終了時刻が無効です。';
        return;
    }
    if (newEnd <= newStart) {
        errorMessage.value = '終了時刻は開始時刻より後にしてください。';
        return;
    }
    // 最小長チェック（15分）
    const minMs = 15 * 60 * 1000;
    if (newEnd - newStart < minMs) {
        errorMessage.value = '予定の最小長は15分です。';
        return;
    }

    // 重複チェック
    fetch(`/events?date=${form.date}`)
        .then((res) => res.json())
        .then((events) => {
            // newStart/newEnd already computed above, but recompute here for scope
            const newStart = new Date(`${form.date}T${form.startHour}:${form.startMinute}:00`);
            const newEnd = new Date(`${form.date}T${form.endHour}:${form.endMinute}:00`);
            const overlap = events.some((ev) => {
                const evStart = new Date(ev.start);
                const evEnd = new Date(ev.end);
                return newStart < evEnd && newEnd > evStart;
            });
            if (overlap) {
                if (!confirm('同じ時間に予定があります。登録しますか？')) {
                    return;
                }
            }
            // attach job_id if present so backend can optionally link the event
            if (props.job && props.job.id) {
                form.job_id = props.job.id;
            }

            try {
                console.debug('[Create.vue] submitting event', {
                    job_id: form.job_id,
                    title: form.title,
                    date: form.date,
                    start: form.startHour + ':' + form.startMinute,
                    end: form.endHour + ':' + form.endMinute,
                });
                form.post(route('events.store'), {
                    forceFormData: true,
                    onSuccess: () => {
                        errorMessage.value = '';
                        const target = props.job ? route('user.assigned-jobs.index') : route('calendar.index');
                        if (props.job) {
                            // force a full navigation to refresh server-side data
                            window.location.href = target;
                            return;
                        }
                        const vm = getCurrentInstance();
                        try {
                            vm?.proxy?.$inertia?.visit(target);
                        } catch (e) {
                            window.location.href = target;
                        }
                    },
                    onError: (errors) => {
                        console.error('events.store failed', errors);
                        errorMessage.value = '予定の保存に失敗しました。後でもう一度お試しください。';
                    },
                });
            } catch (e) {
                console.error('[Create.vue] form.post threw', e);
                errorMessage.value = '予定の保存に失敗しました（クライアントエラー）。';
            }
        });
};

function onInput(val) {
    if (typeof val === 'string') {
        content.value = val;
    } else if (val?.target?.innerHTML) {
        content.value = val.target.innerHTML;
    }
}
watch(
    () => form.content,
    (val) => {
        content.value = val;
    },
);

// When start (hour/minute) changes, always set end to the same time.
watch(
    () => [form.startHour, form.startMinute],
    ([h, m]) => {
        form.endHour = h;
        form.endMinute = m;
    },
);
</script>

<template>
    <AppLayout title="イベント作成">
        <div class="mx-auto max-w-2xl rounded bg-white p-6 shadow">
            <h1 class="mb-4 text-2xl font-bold">イベント作成 ({{ form.date }})</h1>
            <form @submit.prevent="submit">
                <div v-if="errorMessage" class="mb-4 rounded border-l-4 border-red-500 bg-red-50 p-3 text-red-700">
                    {{ errorMessage }}
                </div>
                <div class="mb-4">
                    <label class="mb-1 block text-sm font-medium text-gray-700">タイトル</label>
                    <input v-model="form.title" type="text" class="w-full rounded border p-2" required />
                </div>
                <div class="mb-4">
                    <label class="mb-1 block text-sm font-medium text-gray-700">詳細</label>
                    <textarea
                        v-model="content"
                        @focus="onContentFocus"
                        @blur="onContentBlur"
                        rows="8"
                        class="w-full rounded border bg-white p-2"
                    ></textarea>
                </div>
                <div class="mb-4">
                    <div class="flex items-center gap-8">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">開始時刻</label>
                            <div class="flex gap-2">
                                <select v-model="form.startHour" class="w-20 rounded border p-1">
                                    <option v-for="h in 24" :key="h" :value="String(h - 1).padStart(2, '0')">
                                        {{ String(h - 1).padStart(2, '0') }}
                                    </option>
                                </select>
                                <select v-model="form.startMinute" class="w-20 rounded border p-1">
                                    <option v-for="m in [0, 15, 30, 45]" :key="m" :value="String(m).padStart(2, '0')">
                                        {{ String(m).padStart(2, '0') }}
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">終了時刻</label>
                            <div class="flex gap-2">
                                <select v-model="form.endHour" class="w-20 rounded border p-1">
                                    <option v-for="h in 24" :key="h" :value="String(h - 1).padStart(2, '0')">
                                        {{ String(h - 1).padStart(2, '0') }}
                                    </option>
                                </select>
                                <select v-model="form.endMinute" class="w-20 rounded border p-1">
                                    <option v-for="m in [0, 15, 30, 45]" :key="m" :value="String(m).padStart(2, '0')">
                                        {{ String(m).padStart(2, '0') }}
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- attachments removed: not needed -->
                <div class="flex space-x-4">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        :aria-busy="form.processing"
                        class="flex items-center gap-2 rounded bg-blue-600 px-4 py-2 text-white disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <template v-if="form.processing">
                            <svg class="h-5 w-5 animate-spin text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                            </svg>
                            保存中…
                        </template>
                        <template v-else>保存</template>
                    </button>
                    <Link
                        :href="props.job ? route('user.assigned-jobs.index') : route('calendar.index')"
                        class="rounded bg-gray-200 px-4 py-2 text-gray-700"
                        >キャンセル</Link
                    >
                </div>
            </form>
        </div>
    </AppLayout>
</template>
