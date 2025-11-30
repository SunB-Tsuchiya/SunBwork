<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
// No Quill: use simple textarea for details
import { usePage } from '@inertiajs/vue3';
import { getCurrentInstance, nextTick, onMounted, ref, watch } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps({ date: String, job: { type: Object, default: null } });

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
        // fallback: take date part before 'T' if present
        return String(dateStr).split('T')[0];
    }
}

// Build initial content by including job assignment metadata if available
function buildJobDetails(job) {
    if (!job) return '';
    const assignedName = job.user?.name || job.assigned_user_name || '（未割当）';
    const detailsText = job.details || job.detail || job.description || job.body || '';
    const start = job.desired_start_date || job.preferred_date || job.start_date || '';
    const end = job.desired_end_date || job.end_date || '';
    const pjId = job.project_job_id || job.project_job || '';
    const difficulty = job.difficulty || job.level || '';
    const sizeLabel = job.size_label || job.size?.name || job.size?.label || null;
    const stageLabel = job.stage_label || job.stage?.name || job.stage?.label || null;
    const typeLabel = job.type_label || job.work_item_type?.name || job.work_item_type?.label || null;
    const statusLabel = job.status_label || job.statusModel?.name || job.statusModel?.label || null;

    const lines = [];
    const jobName = job.title || job.name || (pjId ? `ID:${pjId}` : '');
    if (jobName) lines.push(`ジョブ名: ${jobName}`);
    if (job.client_name) lines.push(`クライアント: ${job.client_name}`);
    if (difficulty) lines.push(`難易度: ${difficulty}`);
    if (typeLabel) lines.push(`種別: ${typeLabel}`);
    if (sizeLabel) lines.push(`サイズ: ${sizeLabel}`);
    if (stageLabel) lines.push(`ステージ: ${stageLabel}`);
    if (statusLabel) lines.push(`ステータス: ${statusLabel}`);
    if (job.estimated_hours) lines.push(`見積時間: ${job.estimated_hours}`);
    if (job.scheduled) lines.push(`セット済み: ${job.scheduled_at || 'はい'}`);
    if (job.accepted) lines.push(`確認済み: ${job.accepted ? 'はい' : 'いいえ'}`);
    if (job.completed) lines.push(`完了: ${job.completed ? 'はい' : 'いいえ'}`);
    if (job.linked_assignment_id) lines.push(`リンク割当ID: ${job.linked_assignment_id}`);
    if (job.project_job_name) lines.push(`プロジェクトジョブ: ${job.project_job_name}`);
    if (job.project_job_detail) lines.push(`プロジェクトジョブ詳細: ${job.project_job_detail}`);
    if (job.assigned_user_id) lines.push(`割当ユーザーID: ${job.assigned_user_id}`);
    lines.push(`担当ユーザー: ${assignedName}`);
    if (start || end) lines.push(`希望期間: ${start || '-'} 〜 ${end || '-'}`);
    lines.push('詳細:');
    if (detailsText) lines.push(detailsText);
    return lines.join('\n') + '\n';
}

// Build job text once; buildJobDetails already includes the assignment details if present.
const _jobText = buildJobDetails(props.job);
const content = ref(_jobText);
// UI state for tabs: 'event' or 'job'
const activeTab = ref('event');
const nullProjectJob = null;

// Pull optional props from Inertia page props so AssignmentForm can be prefilled
const page = usePage();
const projectJob = ref(page.props.projectJob || null);
const members = page.props.members || [];
const assignments = page.props.assignments || [];

// user-scoped clients/projects passed from controller
const userClients = page.props.userClients || [];
const userProjects = page.props.userProjects || [];

const selectedClientId = ref(userClients.length ? userClients[0].id : '');
const selectedProjectId = ref('');

// compute projects filtered by selected client
function projectsForClient(clientId) {
    if (!clientId) return userProjects;
    return userProjects.filter((p) => String(p.client_id) === String(clientId));
}

// when client changes, reset project selection
watch(selectedClientId, (val) => {
    const list = projectsForClient(val || '');
    selectedProjectId.value = list.length ? list[0].id : '';
    // update projectJob binding
    if (selectedProjectId.value) {
        const pj = userProjects.find((p) => String(p.id) === String(selectedProjectId.value));
        projectJob.value = pj ? { id: pj.id, title: pj.title, client: { id: val } } : null;
    } else {
        projectJob.value = null;
    }
});

// when project selected directly
watch(selectedProjectId, (val) => {
    if (val) {
        const pj = userProjects.find((p) => String(p.id) === String(val));
        projectJob.value = pj ? { id: pj.id, title: pj.title, client: { id: selectedClientId.value || pj.client_id } } : null;
    } else {
        projectJob.value = null;
    }
});

// Initialize selections
if (userClients.length && !selectedClientId.value) selectedClientId.value = userClients[0].id;
if (!selectedProjectId.value) {
    const initList = projectsForClient(selectedClientId.value);
    if (initList.length) selectedProjectId.value = initList[0].id;
}

// default assigned user id (current user)
const defaultUserId = page.props.auth && page.props.auth.user ? page.props.auth.user.id : null;
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

// If URL query contains startHour/startMinute/endHour/endMinute, use them to prefill the form.
// This allows links like /events/create?date=2025-09-19&startHour=11&startMinute=00&endHour=12&endMinute=00
// to populate the selectors correctly.
try {
    const params = new URLSearchParams(window.location.search);
    const qStartH = params.get('startHour');
    const qStartM = params.get('startMinute');
    const qEndH = params.get('endHour');
    const qEndM = params.get('endMinute');
    if (qStartH) form.startHour = String(qStartH).padStart(2, '0');
    if (qStartM) form.startMinute = String(qStartM).padStart(2, '0');
    if (qEndH) form.endHour = String(qEndH).padStart(2, '0');
    if (qEndM) form.endMinute = String(qEndM).padStart(2, '0');
} catch (e) {
    // ignore if window or URLSearchParams not available in test environments
}

// capture return_to query param so we can navigate back after save/cancel
let returnTo = '';
try {
    const paramsRt = new URLSearchParams(window.location.search);
    const rt = paramsRt.get('return_to');
    if (rt && rt !== 'undefined' && rt !== 'null') {
        // decode in case value was encoded
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

// Development logs removed

onMounted(async () => {
    try {
        await nextTick();
        const editor = document.querySelector('.ql-editor');
        // If editor appears empty, force a brief update to content to trigger Quill refresh
        if (editor && (!editor.textContent || editor.textContent.trim() === '')) {
            // ql-editor empty handling: debug removed
            // append a space then remove it to trigger update
            content.value = (content.value || '') + ' ';
            await nextTick();
            content.value = (content.value || '').trim();
            await nextTick();
            // post-force editor content debug suppressed
        }
    } catch (e) {
        console.error('[Create.vue] onMounted debug error', e);
    }
});

function onContentFocus() {
    // content focus debug removed
}
function onContentBlur() {
    // content blur debug removed
}
function onDescriptionFocus() {
    // description focus debug removed
}
function onDescriptionBlur() {
    // description blur debug removed
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
    const evUrl = `/events?date=${encodeURIComponent(form.date)}`;
    fetch(evUrl, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
    })
        .then((res) => {
            if (!res.ok) {
                // 非 200 レスポンスはここで扱う（例: 401/419/302 など）
                throw new Error(`HTTP ${res.status}`);
            }
            return res.json();
        })
        .then((events) => {
            const newStart = new Date(`${form.date}T${form.startHour}:${form.startMinute}:00`);
            const newEnd = new Date(`${form.date}T${form.endHour}:${form.endMinute}:00`);
            const overlap = events.some((ev) => {
                // 自分と重複判定（Create の場合は ev.id 比較は不要だが保守のため残す）
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
                // submitting event debug removed
                form.post(route('events.store'), {
                    forceFormData: true,
                    onSuccess: () => {
                        errorMessage.value = '';
                        // prefer returning to origin if provided (use full navigation to ensure the diary page re-fetches events)
                        if (returnTo && returnTo !== '') {
                            try {
                                // If it's a relative path like /diaries/123, force a full navigation to ensure mounted hooks run.
                                window.location.href = returnTo;
                            } catch (e) {
                                try {
                                    const vm = getCurrentInstance();
                                    vm?.proxy?.$inertia?.visit(returnTo);
                                } catch (e2) {
                                    // last-resort fallback
                                    window.location.href = returnTo;
                                }
                            }
                            return;
                        }
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
        })
        .catch((err) => {
            console.error('Failed to fetch events for overlap check', err);
            // JSON で取れなかった / 非 OK / ネットワークエラーのときは重複チェックをスキップして投稿を継続
            console.warn('events overlap fetch failed or returned non-JSON. Proceeding to submit. Error:', err);
            // フォールバック: 元の投稿ロジックを実行
            form.post(route('events.store'), {
                forceFormData: true,
                onSuccess: () => {
                    errorMessage.value = '';
                    // prefer returning to origin if provided (use full navigation to ensure the diary page re-fetches events)
                    if (returnTo && returnTo !== '') {
                        try {
                            // If it's a relative path like /diaries/123, force a full navigation to ensure mounted hooks run.
                            window.location.href = returnTo;
                        } catch (e) {
                            try {
                                const vm = getCurrentInstance();
                                vm?.proxy?.$inertia?.visit(returnTo);
                            } catch (e2) {
                                // last-resort fallback
                                window.location.href = returnTo;
                            }
                        }
                        return;
                    }
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
// Only update end time automatically when it previously matched the old start time.
// This prevents overriding an explicit endHour/endMinute provided via query params.
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
        <div class="mx-auto max-w-2xl rounded bg-white p-6 shadow">
            <h1 class="mb-4 text-2xl font-bold">イベント作成 ({{ formatJstDate(form.date) }})</h1>

            <!-- tabs removed: job creation moved to a dedicated page -->

            <div v-if="activeTab === 'event'">
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
                            :href="returnTo && returnTo !== '' ? returnTo : props.job ? route('user.assigned-jobs.index') : route('calendar.index')"
                            class="rounded bg-gray-200 px-4 py-2 text-gray-700"
                            >キャンセル</Link
                        >
                    </div>
                </form>
            </div>

            <!-- job tab removed; use dedicated job creation page instead -->
        </div>
    </AppLayout>
</template>
