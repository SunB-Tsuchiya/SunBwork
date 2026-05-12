<template>
    <AppLayout :title="`ジョブ割り当て - ${projectJob.title}`">
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="routeBack()"
                    class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 whitespace-nowrap hover:bg-gray-300"
                >← 戻る</Link>
                <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">ジョブ割り当て — メッセージ表示</h2>
            </div>
        </template>

        <template #headerExtras>
            <div v-if="isPrivilegedUser" class="flex items-center gap-2">
                <Link :href="assignmentEditHref"
                    class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                >編集</Link>
                <button v-if="canEditDelete" @click.prevent="deleteMessage"
                    class="rounded bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700"
                >削除</button>
            </div>
        </template>

        <div class="mx-auto max-w-2xl space-y-4">

            <!-- メッセージ情報カード -->
            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <!-- カードヘッダー -->
                <div class="flex items-start justify-between gap-3 border-b bg-gray-50 px-5 py-4">
                    <div class="min-w-0 flex-1">
                        <div class="mb-1 flex flex-wrap gap-1.5">
                            <span v-if="isAssignmentCompleted" class="inline-flex items-center gap-1 rounded-full bg-yellow-100 px-2.5 py-0.5 text-xs font-semibold text-yellow-800">
                                <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                完了済み
                            </span>
                            <span v-else-if="assignment.scheduled || assignment.scheduled_at" class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-semibold text-blue-800">セット済み</span>
                        </div>
                        <h1 class="text-base font-bold text-gray-900">{{ assignment.title || projectJob.title }}</h1>
                        <p v-if="message.sender" class="mt-0.5 text-sm text-gray-500">送信者: {{ message.sender.name }}</p>
                    </div>
                </div>

                <!-- セットされた予定 -->
                <!-- assignment-job（Coordinator依頼）はevents.project_job_assignment_idを
                     使わないため、このセクションは表示しない。
                     ユーザーが自分でスケジュールする場合はMyJobBoxを使う。 -->

                <!-- ボタン類 -->
                <div v-if="isAssignee || linkedAssignmentId" class="flex flex-wrap items-center gap-2 border-t bg-gray-50 px-5 py-3">
                    <template v-if="isAssignee">
                        <!-- 進行表から依頼されたジョブ → events.create_job へ、独立ジョブ → マイジョブ作成フォームへ -->
                        <Link
                            v-if="!assignment.is_registered"
                            :href="myJobBoxHref"
                            class="inline-flex items-center gap-1.5 rounded bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700"
                        >
                            {{ assignment.progress_cell_id ? 'ジョブをセット（進行表から）' : 'マイジョブとして登録' }}
                        </Link>
                        <span
                            v-else
                            class="inline-flex items-center gap-1.5 rounded bg-gray-400 px-3 py-1.5 text-sm font-medium text-white cursor-not-allowed"
                        >登録済み</span>
                        <button
                            @click="submitComplete"
                            :class="isAssignmentCompleted || isSubmittingComplete
                                ? 'cursor-not-allowed inline-flex items-center gap-1.5 rounded bg-yellow-800 px-3 py-1.5 text-sm font-medium text-white opacity-70'
                                : 'inline-flex items-center gap-1.5 rounded bg-yellow-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-yellow-700'"
                            :disabled="isAssignmentCompleted || isSubmittingComplete"
                        >
                            {{ isAssignmentCompleted ? '完了済み' : '完了にする' }}
                        </button>
                    </template>

                    <div v-if="linkedAssignmentId" class="ml-auto">
                        <Link :href="getAssignmentLink(linkedAssignmentId)" class="text-sm text-blue-600">割当を見る (#{{ linkedAssignmentId }})</Link>
                    </div>
                </div>
            </div>

            <!-- ジョブ割り当て詳細カード -->
            <AssignmentDetailCard :assignment="assignment" />

            <!-- ファイル一覧（file_info があれば常に表示） -->
            <div v-if="assignment.file_info" class="mt-2">
                <FileInfoDisplay :fileInfo="assignment.file_info" />
            </div>

        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import AssignmentDetailCard from '@/Components/AssignmentDetailCard.vue';
import FileInfoDisplay from '@/Components/FileInfoDisplay.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import { route } from 'ziggy-js';
const { projectJob, message, canDelete, routeContext } = defineProps({ projectJob: Object, message: Object, canDelete: { type: Boolean, default: false }, routeContext: { type: String, default: 'coordinator' } });
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
        if (routeContext === 'coordinator') {
            // プロジェクト詳細から来た場合はプロジェクト詳細に戻る
            const fromProject = new URLSearchParams(window.location.search).get('from') === 'project';
            if (fromProject && projectJob?.id) {
                return safeRoute(
                    'coordinator.project_jobs.show',
                    { projectJob: projectJob.id },
                    `/coordinator/project_jobs/${projectJob.id}`,
                );
            }
            return safeRoute('coordinator.jobbox', {}, '/coordinator/jobbox');
        }
        // user コンテキスト
        return safeRoute('user.jobbox.index', {}, '/user/jobbox');
    } catch (e) {
        return '/user/jobbox';
    }
}

// Mark JAM read when assignee (recipient) opens this SPA view. Silent if API fails.
// Only the assigned user (recipient) triggers read_at — coordinator viewing must not.
onMounted(async () => {
    try {
        const jamId = message && message.id;
        if (!jamId) return;
        const authUserId = page.props.auth?.user?.id;
        const recipientId = message.project_job_assignment?.user_id
            ?? message.project_job_assignment?.user?.id
            ?? null;
        // Only call apiMarkRead if the viewer is the recipient
        if (!authUserId || !recipientId || Number(authUserId) !== Number(recipientId)) return;
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

const isPrivilegedUser = computed(() => {
    try {
        const u = page.props.auth?.user;
        return u && (u.isCoordinator || u.isLeader || u.isAdmin || u.isSuperAdmin);
    } catch (e) {
        return false;
    }
});

const isSender = computed(() => {
    try {
        return page.props.auth?.user?.id === message?.sender_id;
    } catch (e) {
        return false;
    }
});

// 送信者・リーダー以上・案件担当Coは編集・削除可能
const canEditDelete = computed(() => canDelete === true);

const assignmentEditHref = computed(() => {
    const assignmentId = assignment?.id;
    if (!assignmentId) return '#';
    return safeRoute(
        'coordinator.project_jobs.assignments.edit',
        { projectJob: projectJob?.id, assignment: assignmentId },
        `/coordinator/project_jobs/${projectJob?.id}/assignments/${assignmentId}/edit`,
    );
});

function deleteMessage() {
    if (!canEditDelete.value) {
        alert('削除する権限がありません。');
        return;
    }
    if (!confirm('このメッセージを本当に削除しますか？この操作は取り消せません。')) return;
    router.delete(
        safeRoute('coordinator.project_jobs.jobbox.destroy', { projectJob: projectJob?.id, message: message?.id }),
        {
            onSuccess: () => {
                router.visit(
                    safeRoute('coordinator.project_jobs.jobbox.index', { projectJob: projectJob?.id }, `/coordinator/project_jobs/${projectJob?.id}/jobbox`),
                );
            },
            onError: (errors) => {
                console.error('deleteMessage error', errors);
                alert('削除に失敗しました。');
            },
        },
    );
}

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

// 「予定をセット」ボタンのリンク先を判別する。
// 進行表から依頼されたジョブ（assignment.progress_cell_id が存在）→ events.create_job（カレンダーイベント登録）
// 独立の自己割当ジョブ（progress_cell_id なし）→ user.project_jobs.assignments.create（マイジョブ作成フォーム）
// どちらの場合もフォームのプレースホルダーに情報を渡す。
const myJobBoxHref = computed(() => {
    if (!assignment?.id) return safeRoute('user.myjobbox.index', {}, '/myjobbox');

    const params = new URLSearchParams();

    // 共通パラメーター
    if (assignment.project_job_id) params.set('project_job_id', String(assignment.project_job_id));
    if (assignment.title) params.set('title', assignment.title);
    if (assignment.desired_end_date) params.set('desired_end_date', assignment.desired_end_date);
    if (assignment.estimated_hours != null) params.set('estimated_hours', String(assignment.estimated_hours));
    if (assignment.work_item_type_id) params.set('work_item_type_id', String(assignment.work_item_type_id));
    if (assignment.size_id) params.set('size_id', String(assignment.size_id));
    if (assignment.stage_id) params.set('stage_id', String(assignment.stage_id));
    if (assignment.difficulty_id) params.set('difficulty_id', String(assignment.difficulty_id));
    if (assignment.detail) params.set('detail', assignment.detail);
    // 元の Coordinator 割当 ID を渡す（独立ジョブ作成時のリンク用）
    params.set('source_job_assignment_id', String(assignment.id));

    const query = params.toString();

    if (assignment.progress_cell_id) {
        // 進行表から依頼されたジョブ → events.create_job へ（日付なし、カレンダーで選択）
        // 進行表セルの row_id/col_key を渡して登録後に進行表と連動させる
        if (assignment.progress_row_id) params.set('row_id', String(assignment.progress_row_id));
        if (assignment.progress_col_key) params.set('col_key', assignment.progress_col_key);
        if (assignment.progress_sheet_id) params.set('progress_sheet_id', String(assignment.progress_sheet_id));
        const base = safeRoute('events.create_job', {}, '/events/create-job');
        return base + '?' + query;
    } else {
        // 独立の自己割当ジョブ → マイジョブ作成フォームへ
        const base = safeRoute('user.project_jobs.assignments.create', {}, '/project_jobs/assignments/create-user');
        return base + '?' + query;
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
    // Coordinator: go directly to the coordinator assignment edit page (full form + time setting)
    if (routeContext === 'coordinator' && projectJob?.id && assignment?.id) {
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

        // assignment-job（Coordinator依頼）を直接完了にする。
        // events.complete（assignment-job-by-myself用）ではなく、
        // user.jobbox.assignments.complete を使って project_job_assignments を更新する。
        // これにより Coordinator の一覧にも「完了」が反映される。
        const assId = assignment?.id;
        if (!assId) {
            alert('割当IDが見つかりません。');
            return;
        }
        isSubmittingComplete.value = true;
        const completeUrl = safeRoute(
            'user.jobbox.assignments.complete',
            { assignment: assId },
            `/coordinator/jobbox/assignments/${assId}/complete`,
        );
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        fetch(completeUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({}),
        })
            .then(async (res) => {
                if (res.ok) {
                    window.location.reload();
                } else {
                    const body = await res.json().catch(() => ({}));
                    isSubmittingComplete.value = false;
                    alert(body?.error || '完了処理に失敗しました。');
                }
            })
            .catch(() => {
                isSubmittingComplete.value = false;
                alert('完了処理に失敗しました。');
            });
    } catch (e) {
        console.debug('submitComplete error', e);
    }
}

function getSetHref() {
    try {
        if (linkedAssignmentId.value && linkedAssignmentId.value) {
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
        if (routeContext === 'coordinator' && projectJob?.id && assignment?.id) {
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
