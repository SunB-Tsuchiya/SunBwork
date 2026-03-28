<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import AssignmentFormUser from '@/Pages/Coordinator/ProjectJobs/JobAssign/AssignmentForm.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { getCurrentInstance, nextTick, onMounted, ref, watch } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps({ date: String, job: { type: Object, default: null } });

// Inertia page props (used by job tab)
const page = usePage();
const projectJob = ref(page.props.projectJob || null);
const members = page.props.members || [];
const assignments = page.props.assignments || [];
const userClients = page.props.userClients || [];
const userProjects = page.props.userProjects || [];
const defaultUserId = page.props.auth && page.props.auth.user ? page.props.auth.user.id : null;

// Shared helpers for client-event form
function formatJstDate(dateStr) {
    if (!dateStr) return '';
    try {
        const d = new Date(dateStr);
        d.setHours(d.getHours() + 9);
        const yyyy = d.getFullYear();
        const mm = String(d.getMonth() + 1).padStart(2, '0');
        const dd = String(d.getDate()).padStart(2, '0');
        return `${yyyy}-${mm}-${dd}`;
    } catch (e) {
        return String(dateStr).split('T')[0];
    }
}
function buildJobDetails(job) {
    if (!job) return '';
    const assignedName = job.user?.name || job.assigned_user_name || '（未割当）';
    const detailsText = job.details || job.detail || job.description || job.body || '';
    const start = job.desired_start_date || job.preferred_date || job.start_date || '';
    const end = job.desired_end_date || job.end_date || '';
    const pjId = job.project_job_id || job.project_job || '';
    const lines = [];
    const jobName = job.title || job.name || (pjId ? `ID:${pjId}` : '');
    if (jobName) lines.push(`ジョブ名: ${jobName}`);
    if (job.client_name) lines.push(`クライアント: ${job.client_name}`);
    if (job.estimated_hours) lines.push(`見積時間: ${job.estimated_hours}`);
    lines.push(`担当ユーザー: ${assignedName}`);
    if (start || end) lines.push(`希望期間: ${start || '-'} 〜 ${end || '-'}`);
    lines.push('詳細:');
    if (detailsText) lines.push(detailsText);
    return lines.join('\n') + '\n';
}

// Client event form state (kept similar to existing)
const _jobText = buildJobDetails(props.job);
const content = ref(_jobText);
const activeTab = ref('client'); // 'client' or 'job'

const _desired =
    props.job && props.job.desired_time
        ? (function (t) {
              try {
                  const core = String(t).split('T').pop().split('.')[0];
                  const parts = core.split(':');
                  if (parts.length >= 2) return { h: parts[0].padStart(2, '0'), m: parts[1].padStart(2, '0') };
              } catch (e) {}
              return null;
          })(props.job.desired_time)
        : null;

const defaultStartHour = _desired ? _desired.h : '09';
const defaultStartMinute = _desired ? _desired.m : '00';
const defaultEndHour = _desired ? String(parseInt(_desired.h, 10) + 1).padStart(2, '0') : '10';
const defaultEndMinute = '00';

const form = useForm({
    date: props.job && props.job.preferred_date ? props.job.preferred_date : props.date || '',
    title: props.job && props.job.title ? props.job.title : '',
    description: _jobText,
    startHour: defaultStartHour,
    startMinute: defaultStartMinute,
    endHour: defaultEndHour,
    endMinute: defaultEndMinute,
    content,
    job_id: props.job && props.job.id ? props.job.id : null,
    files: [],
});

let returnTo = '';
try {
    const paramsRt = new URLSearchParams(window.location.search);
    const rt = paramsRt.get('return_to');
    if (rt && rt !== 'undefined' && rt !== 'null') {
        try {
            returnTo = decodeURIComponent(String(rt));
        } catch (e) {
            returnTo = String(rt);
        }
    }
} catch (e) {
    returnTo = '';
}

const errorMessage = ref('');

onMounted(async () => {
    try {
        await nextTick();
    } catch (e) {
        console.error('[Create.vue] onMounted error', e);
    }
});

function timestampForCompare(dateStr, hh, mm) {
    return `${dateStr} ${hh}:${mm}`;
}

const submit = () => {
    errorMessage.value = '';
    form.description = content.value;
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
    const minMs = 15 * 60 * 1000;
    if (newEnd - newStart < minMs) {
        errorMessage.value = '予定の最小長は15分です。';
        return;
    }

    const evUrl = `/events?date=${encodeURIComponent(form.date)}`;
    fetch(evUrl, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
    })
        .then((res) => {
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            return res.json();
        })
        .then((events) => {
            const overlap = events.some((ev) => {
                const evStart = new Date(ev.start);
                const evEnd = new Date(ev.end);
                return newStart < evEnd && newEnd > evStart;
            });
            if (overlap) {
                if (!confirm('同じ時間に予定があります。登録しますか？')) return;
            }
            if (props.job && props.job.id) form.job_id = props.job.id;

            try {
                form.post(route('events.store'), {
                    forceFormData: true,
                    onSuccess: () => {
                        errorMessage.value = '';
                        if (returnTo && returnTo !== '') {
                            try {
                                window.location.href = returnTo;
                            } catch (e) {
                                try {
                                    const vm = getCurrentInstance();
                                    vm?.proxy?.$inertia?.visit(returnTo);
                                } catch (e2) {
                                    window.location.href = returnTo;
                                }
                            }
                            return;
                        }
                        const target = props.job ? route('user.assigned-jobs.index') : route('calendar.index');
                        if (props.job) {
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
        })
        .catch((err) => {
            console.error('Failed to fetch events for overlap check', err);
            form.post(route('events.store'), {
                forceFormData: true,
                onSuccess: () => {
                    errorMessage.value = '';
                    if (returnTo && returnTo !== '') {
                        try {
                            window.location.href = returnTo;
                        } catch (e) {
                            try {
                                const vm = getCurrentInstance();
                                vm?.proxy?.$inertia?.visit(returnTo);
                            } catch (e2) {
                                window.location.href = returnTo;
                            }
                        }
                        return;
                    }
                    const target = props.job ? route('user.assigned-jobs.index') : route('calendar.index');
                    if (props.job) {
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
watch(
    () => [form.startHour, form.startMinute],
    ([h, m], [oldH, oldM]) => {
        if (form.endHour === oldH && form.endMinute === oldM) {
            form.endHour = h;
            form.endMinute = m;
        }
    },
);
</script>

<template>
    <AppLayout title="イベント作成">
        <div class="mx-auto max-w-3xl rounded bg-white p-6 shadow">
            <div class="mb-4 flex items-center justify-between">
                <h1 class="text-2xl font-bold">イベント作成</h1>
                <div class="flex items-center space-x-2">
                    <button
                        :class="['rounded px-3 py-1', activeTab === 'client' ? 'bg-blue-600 text-white' : 'border']"
                        @click="activeTab = 'client'"
                    >
                        個人
                    </button>
                    <button :class="['rounded px-3 py-1', activeTab === 'job' ? 'bg-blue-600 text-white' : 'border']" @click="activeTab = 'job'">
                        ジョブ
                    </button>
                </div>
            </div>

            <!-- Client tab: existing event form -->
            <div v-if="activeTab === 'client'">
                <h2 class="mb-2 text-lg font-medium text-gray-700">（個人向け）イベント作成 ({{ formatJstDate(form.date) }})</h2>
                <form @submit.prevent="submit">
                    <div v-if="errorMessage" class="mb-4 rounded border-l-4 border-red-500 bg-red-50 p-3 text-red-700">{{ errorMessage }}</div>

                    <div class="mb-4">
                        <label class="mb-1 block text-sm font-medium text-gray-700">タイトル</label>
                        <input v-model="form.title" type="text" class="w-full rounded border p-2" required />
                    </div>

                    <div class="mb-4">
                        <label class="mb-1 block text-sm font-medium text-gray-700">詳細</label>
                        <textarea v-model="content" rows="8" class="w-full rounded border bg-white p-2"></textarea>
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

                    <div class="flex space-x-4">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="flex items-center gap-2 rounded bg-blue-600 px-4 py-2 text-white disabled:opacity-50"
                        >
                            <template v-if="form.processing"> 保存中… </template>
                            <template v-else>保存</template>
                        </button>
                        <Link
                            :href="returnTo && returnTo !== '' ? returnTo : props.job ? route('user.assigned-jobs.index') : route('calendar.index')"
                            class="rounded bg-gray-200 px-4 py-2 text-gray-700"
                            >キャンセル</Link
                        >
                    </div>
                </form>
            </div>

            <!-- Job tab: embed AssignmentFormUser -->
            <div v-else>
                <h2 class="mb-4 text-lg font-medium text-gray-700">（ジョブ向け）ジョブ作成 / 割当フォーム</h2>
                <AssignmentFormUser
                    mode="user"
                    :projectJob="projectJob"
                    :members="members"
                    :assignments="assignments"
                    :editMode="true"
                    :defaultUserId="defaultUserId"
                    :user-clients="userClients"
                    :user-projects="userProjects"
                />
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* minimal spacing tweaks */
</style>
