<template>
    <AppLayout :title="`ジョブ割り当て - ${projectJob.title}`">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">ジョブ割り当て — メッセージ表示</h2>
        </template>

        <div class="mx-auto max-w-3xl rounded bg-white p-6 shadow">
                    <h1 class="mb-4 flex items-center gap-2 text-2xl font-bold">
                        <span>ジョブ割り当て：{{ projectJob.title }}</span>
                        <svg
                            v-if="isAssignmentCompleted"
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                            role="img"
                            aria-label="完了済み"
                            class="h-6 w-6 text-yellow-500"
                            title="完了済み"
                        >
                            <path
                                fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-10.707a1 1 0 00-1.414-1.414L9 9.586 7.707 8.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                clip-rule="evenodd"
                            />
                        </svg>
                    </h1>

                    <div class="mb-4 rounded border p-4">
                        <div class="flex items-center gap-2">
                            <span class="font-semibold">ジョブ名：</span>
                            <span class="truncate">{{ assignment.title }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="font-semibold">クライアント：</span>
                            <span class="truncate">{{ assignment.project_job?.client?.name || projectJob.client?.name || '-' }}</span>
                        </div>
                        <div class="flex items-start gap-2">
                            <span class="font-semibold">概要：</span>
                            <div class="flex-1 break-words">{{ assignment.detail }}</div>
                        </div>

                        <label class="mb-1 mt-2 block font-semibold">作業詳細</label>

                        <div class="mb-2 text-sm text-gray-700">
                            <!-- 作業種別、サイズ、ステージ、ステータス をグリッド表示に変更 -->
                            <div class="grid grid-cols-1 gap-3 text-sm text-gray-700 sm:grid-cols-2">
                                <div>
                                    <div v-if="assignment.type_label && assignment.type_label !== displayType" class="text-sm text-gray-500">
                                        {{ assignment.type_label }}
                                    </div>
                                    <div class="text-sm text-gray-700">{{ displayType }}</div>
                                </div>

                                <div>
                                    <div v-if="assignment.size_label && assignment.size_label !== displaySize" class="text-sm text-gray-500">
                                        {{ assignment.size_label }}
                                    </div>
                                    <div class="text-sm text-gray-700">{{ displaySize }}</div>
                                </div>

                                <div>
                                    <div v-if="assignment.stage_label && assignment.stage_label !== displayStage" class="text-sm text-gray-500">
                                        {{ assignment.stage_label }}
                                    </div>
                                    <div class="text-sm text-gray-700">{{ displayStage }}</div>
                                </div>

                                <div>
                                    <div v-if="assignment.status_label && assignment.status_label !== displayStatus" class="text-sm text-gray-500">
                                        {{ assignment.status_label }}
                                    </div>
                                    <div class="text-sm text-gray-700">{{ displayStatus }}</div>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="font-semibold">難易度：</span>
                                <span>{{ difficultyLabel }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="font-semibold">割当希望日：</span>
                                <span>{{ assignment.desired_start_date || '-' }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="font-semibold">終了希望日, 希望時間：</span>
                                <span>{{ assignment.desired_end_date || '-' }}</span>
                                <span class="text-gray-600">/</span>
                                <span>{{ formatTime(assignment.desired_time) }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="font-semibold">見積時間：</span>
                                <span>{{ formatEstimatedHours(assignment.estimated_hours) }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="font-semibold">割当ユーザー：</span>
                                <span>{{ assignment.user?.name || '-' }}</span>
                            </div>
                        </div>

                        <!-- Scheduled events section (moved here) -->
                        <div v-if="showScheduledSection" class="mb-1 mt-2 block font-semibold">
                            <label class="mb-1 mt-2 block font-semibold">セットされた予定</label>
                            <div v-if="formattedEvents.length === 0" class="text-sm text-gray-600">予定が見つかりません。</div>
                            <table v-else class="w-full text-sm text-gray-700">
                                <thead>
                                    <tr class="border-b">
                                        <th class="py-2 text-left">作業日</th>
                                        <th class="py-2 text-left">開始時間</th>
                                        <th class="py-2 text-left">終了時間</th>
                                        <th class="py-2 text-left">作業時間合計</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="ev in formattedEvents" :key="ev.id">
                                        <td class="py-2">{{ ev.dateStr }}</td>
                                        <td class="py-2">{{ ev.startTime }}</td>
                                        <td class="py-2">{{ ev.endTime }}</td>
                                        <td class="py-2">{{ formatDurationFromMinutes(ev.minutes) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-2 text-right">
                            <div v-if="linkedAssignmentId">
                                <Link :href="getAssignmentLink(linkedAssignmentId)" class="ml-3 text-sm text-blue-600"
                                    >割当を見る (#{{ linkedAssignmentId }})</Link
                                >
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <Link :href="routeBack()" class="rounded bg-gray-200 px-4 py-2">戻る</Link>

                        <!-- Determine if current user is the assignee -->
                        <template v-if="isAssignee">
                            <!-- 今日の作業をMyJobとして記録 -->
                            <Link
                                :href="scheduleHref"
                                class="rounded bg-blue-500 px-3 py-2 text-sm text-white hover:bg-blue-600"
                            >
                                今日の予定をセット
                            </Link>

                            <!-- 完了にするボタン: イベントがある場合は表示 -->
                            <div v-if="(assignment.scheduled || assignment.scheduled_at) && formattedEvents.length > 0" class="flex items-center">
                                <button
                                    @click="submitComplete"
                                    :class="
                                        isAssignmentCompleted || isSubmittingComplete
                                            ? 'ml-2 cursor-not-allowed rounded bg-yellow-800 px-3 py-2 text-sm text-white opacity-80'
                                            : 'ml-2 rounded bg-yellow-600 px-3 py-2 text-sm text-white hover:bg-yellow-700'
                                    "
                                    :disabled="isAssignmentCompleted || isSubmittingComplete"
                                >
                                    {{ isAssignmentCompleted ? '完了済み' : '完了にする' }}
                                </button>
                            </div>
                        </template>

                        <!-- For non-assignee (sender/others), keep showing the 'セット済' badge when scheduled -->
                        <div v-else>
                            <div v-if="assignment.scheduled || assignment.scheduled_at">
                                <span class="text-sm font-semibold text-green-600">セット済</span>
                            </div>
                        </div>
                    </div>
                </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import { route } from 'ziggy-js';
const { projectJob, message } = defineProps({ projectJob: Object, message: Object });
const page = usePage();

// Safe route helper: prefer Ziggy `route()` when available, else return fallback string.
function safeRoute(name, params = {}, fallback = '#') {
    try {
        if (typeof route === 'function') return route(name, params || {});
    } catch (e) {
        // ignore
    }
    return fallback;
}

function getAssignmentLink(id) {
    try {
        if (!id) return '#';
        const fallback = projectJob && projectJob.id ? `/project_jobs/${projectJob.id}/assignments/${id}` : `#/assignments/${id}`;
        return safeRoute('coordinator.project_jobs.assignments.show', { projectJob: projectJob?.id, assignment: id }, fallback);
    } catch (e) {
        return '#';
    }
}

// Use assignment from the message payload (broadcast includes project_job_assignment)
const assignment = message?.project_job_assignment || {};

// Resolve difficulty label in a predictable order:
// 1) assignment.difficulty_label (provided by backend)
// 2) lookup by assignment.difficulty_id using page.props.difficulties
// 3) attempt to match legacy assignment.difficulty to a difficulty by name/slug
// 4) fallback to assignment.difficulty or '-'
const difficultyLabel = computed(() => {
    if (assignment?.difficulty_label) return assignment.difficulty_label;
    const did = assignment?.difficulty_id ?? null;
    const difficulties = page.props?.difficulties ?? null;
    if (did && Array.isArray(difficulties)) {
        const found = difficulties.find((d) => String(d.id) === String(did));
        if (found) return found.name;
    }
    if (assignment?.difficulty) {
        if (Array.isArray(difficulties)) {
            const found = difficulties.find((d) => d.name === assignment.difficulty || d.slug === assignment.difficulty);
            if (found) return found.name;
        }
        return assignment.difficulty;
    }
    return '-';
});

function formatTime(t) {
    if (!t) return '';
    const core = String(t).split('.')[0];
    const parts = core.split(':');
    if (parts.length >= 2) return parts[0].padStart(2, '0') + ':' + parts[1].padStart(2, '0');
    return t;
}

function formatEstimatedHours(h) {
    if (h === null || h === undefined || h === '') return '-';
    const n = Number(h);
    if (Number.isNaN(n)) return '-';
    return Number.isInteger(n) ? `${n}h` : `${n}h`;
}

function routeBack() {
    try {
        if (page.props.auth.user && page.props.auth.user.isCoordinator) {
            return safeRoute(
                'coordinator.project_jobs.assignments.index',
                { projectJob: projectJob?.id },
                projectJob && projectJob.id ? `/project_jobs/${projectJob.id}/assignments` : '/jobbox',
            );
        }
        return safeRoute('user.project_jobs.jobbox.index', { projectJob: projectJob?.id }, '/jobbox');
    } catch (e) {
        return '/jobbox';
    }
}

// Mark JAM read when assignee opens this SPA view. Silent if API fails.
onMounted(async () => {
    try {
        const jamId = message && message.id;
        if (!jamId) return;
        const apiReadUrl = safeRoute('api.jobbox.read', { id: jamId }, `/api/jobbox/read/${jamId}`);
        await fetch(apiReadUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
        });
    } catch (err) {
        // ignore
    }

    // If this assignment appears scheduled, fetch events for the assigned user
    try {
        // Only attempt if assignment has an assigned user and appears scheduled
        const assigneeId = assignment && (assignment.user?.id || assignment.user_id || null);
        const isScheduled = Boolean(assignment && (assignment.scheduled || assignment.scheduled_at));
        if (assigneeId && isScheduled) {
            // Request JSON explicitly so EventController returns JSON (we changed it
            // to render an Inertia page for normal browser requests). Also include
            // the job query so the server can pre-filter events linked to this job.
            const baseEventsUrl = safeRoute('events.index', {}, '/events');
            const url = baseEventsUrl + '?user_id=' + encodeURIComponent(assigneeId) + '&job=' + encodeURIComponent(assignment.id);
            const res = await fetch(url, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
            });
            if (res.ok) {
                const payload = await res.json();
                // Keep only events that are linked to this project job assignment
                const evs = Array.isArray(payload) ? payload.filter((e) => String(e.project_job_assignment_id) === String(assignment.id)) : [];
                events.value = evs;
            }
        }
    } catch (err) {
        // ignore fetch errors
    }
});

const events = ref([]);

// local UI state for submitting complete action
const isSubmittingComplete = ref(false);
// note: complete details modal and helpers were removed per UX decision

function totalMinutes() {
    return formattedEvents.value.reduce((acc, ev) => acc + (ev.minutes || 0), 0);
}

function formatDurationFromMinutes(mins) {
    const h = Math.floor(mins / 60);
    const m = mins % 60;
    if (h > 0) return `${h}時間${m}分`;
    return `${m}分`;
}

const showScheduledSection = computed(() => {
    // Show when assignment is marked scheduled and status suggests set/completed
    const statusLabel = assignment?.status_label || assignment?.status || '';
    const statusOk =
        String(statusLabel).includes('セット') ||
        String(statusLabel).includes('完了') ||
        String(statusLabel).toLowerCase() === 'completed' ||
        String(statusLabel).toLowerCase() === 'scheduled';
    return assignment && (assignment.scheduled || assignment.scheduled_at) && statusOk;
});

// computed display values for the table cells to avoid duplicated label/main text
const displayType = computed(() => {
    return assignment?.work_item_type?.name || assignment?.work_item_type?.label || assignment?.type_label || '-';
});

const displaySize = computed(() => {
    return assignment?.size?.name || assignment?.size?.label || assignment?.size_label || '-';
});

const displayStage = computed(() => {
    return assignment?.stage?.name || assignment?.stage?.label || assignment?.stage_label || '-';
});

const displayStatus = computed(() => {
    return assignment?.status?.name || assignment?.statusModel?.name || assignment?.statusModel?.label || assignment?.status_label || '-';
});

const linkedAssignmentId = computed(() => {
    try {
        return (
            assignment?.linked_assignment_id ||
            assignment?.project_job_assignment_id ||
            (assignment?.project_job_assignment && assignment.project_job_assignment.id) ||
            null
        );
    } catch (e) {
        return null;
    }
});

const isAssignee = computed(() => {
    try {
        return page.props.auth.user && assignment && assignment.user && page.props.auth.user.id === assignment.user.id;
    } catch (e) {
        return false;
    }
});

// Whether the assignment is already completed (backend may set flag or status)
const isAssignmentCompleted = computed(() => {
    try {
        if (!assignment) return false;
        if (assignment.completed) return true;
        // status may be present as object or label/key
        if (assignment.status && (assignment.status.key === 'completed' || String(assignment.status.name || '').indexOf('完了') !== -1)) return true;
        if (assignment.status_label && String(assignment.status_label).indexOf('完了') !== -1) return true;
        return false;
    } catch (e) {
        return false;
    }
});

// If events exist, pick the first event's date (ISO YYYY-MM-DD). Otherwise fall back
// to assignment.scheduled_at or assignment.date or today's date. Support both
// `start`/`end` and `starts_at`/`ends_at` field names returned by the API.
const editDate = computed(() => {
    if (Array.isArray(events.value) && events.value.length > 0) {
        const ev = events.value[0];
        if (ev.date) return ev.date;
        if (ev.start) return new Date(ev.start).toISOString().slice(0, 10);
        if (ev.starts_at) return new Date(ev.starts_at).toISOString().slice(0, 10);
    }
    if (assignment && assignment.scheduled_at) {
        return new Date(assignment.scheduled_at).toISOString().slice(0, 10);
    }
    if (assignment && assignment.date) {
        return assignment.date;
    }
    return new Date().toISOString().slice(0, 10);
});

// 「今日の予定をセット」→ events.create_job with today's date and assignment prefill
const scheduleHref = computed(() => {
    if (!assignment?.id) return '#';
    const today = new Date().toISOString().slice(0, 10);
    const base = safeRoute('events.create_job', {}, '/events/create-job');
    return base + '?job=' + encodeURIComponent(assignment.id) + '&date=' + encodeURIComponent(today);
});

const editHref = computed(() => {
    const isCoordinator = page.props.auth?.user?.isCoordinator;

    // Coordinator: go directly to the coordinator assignment edit page (full form + time setting)
    if (isCoordinator && projectJob?.id && assignment?.id) {
        return safeRoute(
            'coordinator.project_jobs.assignments.edit',
            { projectJob: projectJob.id, assignment: assignment.id },
            `/coordinator/project_jobs/${projectJob.id}/assignments/${assignment.id}/edit`,
        );
    }

    // Non-coordinator: load coordinator assignment data via edit_user (prefilled form)
    if (assignment?.id) {
        const fallback = '/project_jobs/assignments/edit-user?job=' + encodeURIComponent(assignment.id);
        try {
            if (typeof route === 'function') return route('user.project_jobs.assignments.edit') + '?job=' + encodeURIComponent(assignment.id);
        } catch (e) {}
        return fallback;
    }

    // Fallback: Navigate to calendar index with date and user_id so calendar focuses the day
    return (
        safeRoute('calendar.index', {}, '/calendar') +
        '?date=' +
        encodeURIComponent(editDate.value) +
        '&user_id=' +
        encodeURIComponent(assignment.user?.id || '')
    );
});

function submitComplete() {
    try {
        if (!confirm('このジョブを完了としてマークしますか？')) return;
        // Derive a best-effort event id from fetched events
        const ev = Array.isArray(events.value) && events.value.length > 0 ? events.value[0] : null;
        if (!ev) {
            alert('完了対象の予定が見つかりません。');
            return;
        }
        const evId = ev.id || ev.event_id || (ev.extendedProps && (ev.extendedProps.event_id || ev.extendedProps.id)) || null;
        if (!evId) {
            alert('完了対象の予定IDが見つかりません。');
            return;
        }
        isSubmittingComplete.value = true;
        const completeUrl = safeRoute('events.complete', { event: evId }, `/events/${evId}/complete`);
        router.post(
            completeUrl,
            {},
            {
                onSuccess: () => {
                    // reload to reflect server-side state (assignment marked completed)
                    window.location.reload();
                },
                onError: () => {
                    isSubmittingComplete.value = false;
                },
            },
        );
    } catch (e) {
        // swallow to avoid breaking the page
        console.debug('submitComplete error', e);
    }
}

function getSetHref() {
    try {
        const isCoordinator = page.props.auth?.user?.isCoordinator;

        if (linkedAssignmentId && linkedAssignmentId.value) {
            const jid = String(linkedAssignmentId.value);
            const fallback = '/project_jobs/assignments/edit-user?job=' + encodeURIComponent(jid);
            try {
                if (typeof route === 'function') return route('user.project_jobs.assignments.edit') + '?job=' + encodeURIComponent(jid);
            } catch (e) {
                return fallback;
            }
            return fallback;
        }

        // Coordinator: go to the coordinator assignment edit page (full form + time setting)
        if (isCoordinator && projectJob?.id && assignment?.id) {
            return safeRoute(
                'coordinator.project_jobs.assignments.edit',
                { projectJob: projectJob.id, assignment: assignment.id },
                `/coordinator/project_jobs/${projectJob.id}/assignments/${assignment.id}/edit`,
            );
        }

        // Regular user: load coordinator data via edit_user
        if (assignment?.id) {
            const fallback = '/project_jobs/assignments/edit-user?job=' + encodeURIComponent(assignment.id);
            try {
                if (typeof route === 'function') return route('user.project_jobs.assignments.edit') + '?job=' + encodeURIComponent(assignment.id);
            } catch (e) {
                return fallback;
            }
            return fallback;
        }

        return '#';
    } catch (e) {
        return '#';
    }
}

// Map events into display-friendly objects, supporting both legacy and newer field names
// for start/end timestamps.
const formattedEvents = computed(() => {
    return events.value.map((e) => {
        const rawStart = e.start ?? e.starts_at ?? null;
        const rawEnd = e.end ?? e.ends_at ?? null;
        const start = rawStart ? new Date(rawStart) : null;
        const end = rawEnd ? new Date(rawEnd) : null;
        const dateStr = e.date || (start ? start.toISOString().slice(0, 10) : '');
        const startTime = start ? start.toLocaleTimeString('ja-JP', { hour: '2-digit', minute: '2-digit' }) : '';
        const endTime = end ? end.toLocaleTimeString('ja-JP', { hour: '2-digit', minute: '2-digit' }) : '';
        const minutes = start && end ? Math.max(0, Math.round((end - start) / 60000)) : 0;
        return { ...e, dateStr, startTime, endTime, minutes };
    });
});
</script>

<style scoped></style>
