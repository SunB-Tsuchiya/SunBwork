<template>
    <AppLayout :title="`JobBox - ${props.projectJob?.name || ''}`">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">JobBox — ジョブ関連メッセージ</h2>
        </template>

        <div class="mx-auto max-w-6xl rounded bg-white p-6 shadow">
            <h1 class="mb-4 text-2xl font-bold">JobBox：{{ props.projectJob?.name || '' }}</h1>
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div class="mb-4 flex items-center gap-2">
                    <input
                        v-model="page.props.q_model"
                        @keyup.enter="search"
                        placeholder="タイトル/詳細/担当で検索"
                        class="w-72 rounded border px-3 py-2 text-sm"
                    />
                    <button class="rounded bg-blue-600 px-3 py-2 text-white" @click.prevent="search">検索</button>
                    <button class="ml-2 rounded border px-3 py-2" @click.prevent="clearSearch">クリア</button>
                </div>
                <div class="mb-4 md:ml-4 md:mt-0">
                    <Link
                        :href="typeof route === 'function' ? route('project_jobs.assignments.create_user') : '/project_jobs/assignments/create-user'"
                        class="rounded bg-blue-600 px-4 py-2 text-white"
                        >新規ジョブ作成</Link
                    >
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full border">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="border px-4 py-2">送受信</th>
                            <th class="border px-4 py-2">相手</th>
                            <th class="cursor-pointer border px-4 py-2" @click.prevent="changeSort('desired_start_date')">
                                希望日 <span v-if="isSorted('desired_start_date')">{{ sortIcon() }}</span>
                            </th>
                            <th class="cursor-pointer border px-4 py-2" @click.prevent="changeSort('subject')">
                                タイトル <span v-if="isSorted('subject')">{{ sortIcon() }}</span>
                            </th>
                            <th class="border px-4 py-2">クライアント</th>
                            <th class="border px-4 py-2">既読</th>
                            <th class="border px-4 py-2">ステータス</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="m in displayMessages"
                            :key="m.id"
                            :class="['cursor-pointer hover:bg-gray-100', m.__is_new ? 'new-highlight' : '']"
                            @click.prevent="rowClick(m, $event)"
                            role="button"
                        >
                            <td class="border px-4 py-2">
                                <span class="inline-flex items-center gap-2">
                                    <span
                                        :class="
                                            page.props.auth.user &&
                                            page.props.auth.user.id &&
                                            m.sender &&
                                            m.sender.id &&
                                            page.props.auth.user.id === m.sender.id
                                                ? 'bg-blue-500'
                                                : 'bg-gray-400'
                                        "
                                        class="inline-block h-3 w-3 rounded-full"
                                        :title="
                                            page.props.auth.user &&
                                            page.props.auth.user.id &&
                                            m.sender &&
                                            m.sender.id &&
                                            page.props.auth.user.id === m.sender.id
                                                ? '送信'
                                                : '受信'
                                        "
                                    ></span>
                                    <span class="text-sm text-gray-700">{{
                                        page.props.auth.user &&
                                        page.props.auth.user.id &&
                                        m.sender &&
                                        m.sender.id &&
                                        page.props.auth.user.id === m.sender.id
                                            ? '送信'
                                            : '受信'
                                    }}</span>
                                </span>
                            </td>
                            <td class="border px-4 py-2 text-sm text-gray-700">{{ getCounterparty(m) }}</td>
                            <!-- m is JobAssignmentMessage; load related assignment via m.project_job_assignment? -->
                            <td class="border px-4 py-2">{{ m.project_job_assignment?.desired_start_date || '-' }}</td>
                            <td class="border px-4 py-2">{{ m.subject || (m.body && m.body.slice(0, 80)) }}</td>
                            <td class="border px-4 py-2">{{ getClientName(m) }}</td>
                            <td class="border px-4 py-2">
                                <template v-if="isUnread(m)">
                                    <span class="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800"
                                        >未読</span
                                    >
                                </template>
                                <template v-else>
                                    <span class="text-sm text-gray-600">既読</span>
                                </template>
                            </td>
                            <td class="border px-4 py-2">
                                <TooltipProvider :delay-duration="0">
                                    <Tooltip>
                                        <TooltipTrigger>
                                            <span
                                                :class="statusBadgeClass(getAssignmentStatus(m))"
                                                class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                            >
                                                <span v-html="statusIcon(getAssignmentStatus(m))" class="mr-1 inline-flex h-3 w-3"></span>
                                                {{ getAssignmentStatus(m) }}
                                            </span>
                                        </TooltipTrigger>
                                        <TooltipContent class="jobbox-tooltip max-w-xs">{{ statusTooltip(getAssignmentStatus(m)) }}</TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex items-center justify-between">
                <div class="text-sm text-gray-600">全 {{ props.messages?.total || localMessages.length || 0 }} 件</div>
                <div class="flex items-center space-x-2">
                    <button
                        :disabled="!props.messages?.prev_page_url"
                        @click.prevent="goto(props.messages?.prev_page_url)"
                        class="rounded border px-3 py-1 disabled:opacity-50"
                    >
                        前へ
                    </button>
                    <div class="text-sm">{{ props.messages?.current_page || 0 }} / {{ props.messages?.last_page || 0 }}</div>
                    <button
                        :disabled="!props.messages?.next_page_url"
                        @click.prevent="goto(props.messages?.next_page_url)"
                        class="rounded border px-3 py-1 disabled:opacity-50"
                    >
                        次へ
                    </button>
                </div>
            </div>

            <div class="mt-4">
                <Link :href="getBackLink()" class="rounded bg-gray-200 px-4 py-2">戻る</Link>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { reactive } from 'vue';
const props = defineProps({ projectJob: Object, messages: Object });
const page = usePage();
page.props.q_model = page.props.q || '';
// propagate server sort state into the component for UI
const currentSort = page.props.sort || null;
const currentDir = page.props.dir || 'desc';

const sortState = reactive({ sort: currentSort, dir: currentDir });

function isSorted(key) {
    return sortState.sort === key;
}

function sortIcon() {
    return sortState.dir === 'asc' ? '▲' : '▼';
}

function deleteMessage(m) {
    if (!confirm('このメッセージを本当に削除しますか？この操作は取り消せません。')) return;
    router.delete(route('coordinator.project_jobs.jobbox.destroy', { projectJob: props.projectJob?.id, message: m.id }), {
        onSuccess: () => {
            router.reload();
        },
        onError: (errors) => {
            console.error('deleteMessage error', errors);
            alert('削除に失敗しました。');
        },
    });
}

function sendFromMessage(m) {
    // reuse sendRequest semantics: post a JobBox message already exists, so this may trigger the Messages flow
    if (!confirm('このジョブ情報を発信しますか？')) return;
    const to = m.project_job_assignment?.user_id ? [m.project_job_assignment.user_id] : [];
    const payload = {
        project_job_assignment_id: m.project_job_assignment?.id || null,
        to: to,
        // Do not auto-prefix subject; use existing subject or fallback to assignment title
        subject: m.subject || m.project_job_assignment?.title || null,
        body: m.body || null,
        attachments: [],
    };
    router.post(route('coordinator.project_jobs.jobbox.store', { projectJob: props.projectJob?.id }), payload, {
        onSuccess: () => {
            alert('発信しました。');
            router.reload();
        },
        onError: (errors) => {
            console.error('sendFromMessage error', errors);
            alert('発信に失敗しました。');
        },
    });
}

function formatDate(d) {
    if (!d) return '-';
    return String(d).split('T')[0];
}

function goto(url) {
    if (!url) return;
    router.visit(url, { preserveState: false });
}

function gotoCreate() {
    try {
        // Prefer standalone assignment form route, then fall back to events.create
        try {
            return router.visit(route('project_jobs.assignments.create_user'));
        } catch (err) {
            try {
                return router.visit(route('events.create'));
            } catch (err2) {
                return router.visit('/events/create');
            }
        }
    } catch (err) {
        // swallow navigation errors
        console.error('gotoCreate error', err);
    }
}

function rowClick(m, event) {
    // If click originated from a link or button, let that element handle it
    const tag = event.target && event.target.tagName ? event.target.tagName.toLowerCase() : '';
    if (tag === 'a' || tag === 'button' || (event.target.closest && event.target.closest('a,button'))) {
        return;
    }
    const url = getMessageLink(m);
    if (url && url !== '#') {
        router.visit(url, { preserveState: false });
    }
}

function search() {
    const pjId = props.projectJob?.id;
    if (!pjId) {
        // no project context yet; avoid calling Ziggy with missing params
        return;
    }
    const r = page.props.auth.user && page.props.auth.user.isCoordinator ? 'coordinator.project_jobs.jobbox.index' : 'project_jobs.jobbox.index';
    router.get(route(r, { projectJob: pjId }), { q: page.props.q_model }, { preserveState: false });
}

function changeSort(key) {
    // toggle direction if same key
    if (sortState.sort === key) {
        sortState.dir = sortState.dir === 'asc' ? 'desc' : 'asc';
    } else {
        sortState.sort = key;
        sortState.dir = 'asc';
    }

    const pjId = props.projectJob?.id;
    const params = { q: page.props.q_model, sort: sortState.sort, dir: sortState.dir };
    try {
        if (pjId) {
            const r =
                page.props.auth.user && page.props.auth.user.isCoordinator ? 'coordinator.project_jobs.jobbox.index' : 'project_jobs.jobbox.index';
            router.get(route(r, { projectJob: pjId }), params, { preserveState: false });
            return;
        }
        // global jobbox route: named 'project_jobs.index' or fallback route
        router.get(route('project_jobs.index'), params, { preserveState: false });
    } catch (err) {
        // fallback: reload current url with query params
        router.get(window.location.pathname, params, { preserveState: false });
    }
}

function clearSearch() {
    page.props.q_model = '';
    search();
}

// build a safe back link target
function getBackLink() {
    const pjId = props.projectJob?.id;
    try {
        if (!pjId) {
            return '/jobbox';
        }
        // Ziggy may not contain all named routes in some contexts; guard route() with try/catch
        try {
            if (page.props.auth.user && page.props.auth.user.isCoordinator) {
                return route('coordinator.project_jobs.show', { projectJob: pjId });
            }
            return route('project_jobs.show', { projectJob: pjId });
        } catch (zigErr) {
            // Ziggy route not available — fallback used
            // fallback to a safe jobbox index route
            return '/jobbox';
        }
    } catch (err) {
        // getBackLink error suppressed
        return '#';
    }
}

// helper to build message detail link safely (avoid Ziggy errors when projectJob is undefined)
function getMessageLink(m) {
    // Try to use explicit projectJob prop first; if not present (global jobbox), derive from the message
    let pjId = props.projectJob?.id;
    try {
        if (!pjId) {
            pjId = m.project_job_assignment?.project_job?.id || m.project_job_assignment?.project_job_id || null;
        }
        if (!pjId) return '#';
        if (page.props.auth.user && page.props.auth.user.isCoordinator) {
            return route('coordinator.project_jobs.jobbox.show', { projectJob: pjId, message: m.id });
        }
        return route('project_jobs.jobbox.show', { projectJob: pjId, message: m.id });
    } catch (err) {
        // getMessageLink error suppressed
        return '#';
    }
}

// Return the counterpart name: if current user is sender -> show to recipients; else show from user
function getCounterparty(m) {
    try {
        const authUser = page.props.auth.user;
        const authId = authUser && authUser.id;
        const isSender = authId && m.sender && m.sender.id && authId === m.sender.id;

        if (isSender) {
            // Prefer explicit recipients on the linked Message
            const recs = m.message && Array.isArray(m.message.recipients) ? m.message.recipients : [];
            if (recs.length) {
                const names = recs
                    .map((r) => {
                        if (r.user && r.user.name) return r.user.name;
                        if (r.name) return r.name;
                        if (r.email) return r.email;
                        if (r.user_id) return `user:${r.user_id}`;
                        return null;
                    })
                    .filter(Boolean);
                if (names.length) return names.join(', ');
            }

            // Fallback: assignment user
            if (m.project_job_assignment && m.project_job_assignment.user && m.project_job_assignment.user.name) {
                return m.project_job_assignment.user.name;
            }

            // Another possible shape: message.to array of users or ids
            if (m.message && Array.isArray(m.message.to) && m.message.to.length) {
                const tnames = m.message.to.map((u) => (u && u.name) || (typeof u === 'number' ? `user:${u}` : null)).filter(Boolean);
                if (tnames.length) return tnames.join(', ');
            }

            return '-';
        }

        // Received: prefer explicit fromUser, then sender
        if (m.message && m.message.fromUser && m.message.fromUser.name) return m.message.fromUser.name;
        if (m.sender && m.sender.name) return m.sender.name;
        return '-';
    } catch (e) {
        // getCounterparty error suppressed
        return '-';
    }
}

// Get client name for a message: prefer assignment.project_job.client.name, then page prop projectJob.client.name, then fallback to '-'
function getClientName(m) {
    try {
        if (
            m.project_job_assignment &&
            m.project_job_assignment.project_job &&
            m.project_job_assignment.project_job.client &&
            m.project_job_assignment.project_job.client.name
        ) {
            return m.project_job_assignment.project_job.client.name;
        }
        if (props.projectJob && props.projectJob.client && props.projectJob.client.name) return props.projectJob.client.name;
        // as a last attempt, check message payload for client-like fields
        if (m.message && m.message.client && m.message.client.name) return m.message.client.name;
        return '-';
    } catch (e) {
        return '-';
    }
}

// Determine assignment status: 完了, セット済み, 確認済み, 受信済み
function getAssignmentStatus(m) {
    try {
        // Prefer canonical status object when available (in assignment.status.key)
        const jam = m || {};
        const assignment = m.project_job_assignment || {};

        const statusKey = (assignment.status && assignment.status.key) || (jam.status && jam.status.key) || null;
        if (statusKey) {
            switch (statusKey) {
                case 'completed':
                    return '完了';
                case 'scheduled':
                    return 'セット済み';
                case 'confirmed':
                    return '確認済み';
                case 'received':
                    return '受信済み';
                // legacy slugs fallback
                case 'order':
                    return '受信済み';
                case 'in_progress':
                    return '受信済み';
                default:
                    // unknown key — fall back to flag logic below
                    break;
            }
        }

        // Fallback for older data shapes: use existing flag/timestamp heuristics
        const completed = Boolean(jam.completed) || Boolean(assignment.completed);
        if (completed) return '完了';

        const scheduled = Boolean(jam.scheduled) || Boolean(assignment.scheduled) || Boolean(assignment.scheduled_at);
        if (scheduled) return 'セット済み';

        const accepted = Boolean(jam.accepted) || Boolean(assignment.accepted);
        const readAt = jam.read_at || assignment.read_at || null;
        // If message has been read but not necessarily accepted, show '既読済み'
        if (readAt) {
            if (accepted) return '確認済み';
            return '既読済み';
        }
        if (accepted) return '受信済み';

        return '-';
    } catch (err) {
        return '-';
    }
}

function statusBadgeClass(status) {
    const s = status || '';
    switch (s) {
        case '完了':
            // Match calendar completed color (yellow)
            return 'bg-yellow-100 text-yellow-800';
        case 'セット済み':
            // Scheduled matches calendar blue
            return 'bg-blue-100 text-blue-800';
        case '確認済み':
            // Confirmed -> green to align with calendar/positive state
            return 'bg-green-100 text-green-800';
        case '受信済み':
            return 'bg-indigo-100 text-indigo-800';
        case '既読済み':
            return 'bg-gray-100 text-gray-700';
        default:
            return 'bg-gray-100 text-gray-700';
    }
}

function statusTooltip(status) {
    switch (status) {
        case '完了':
            return '作業が完了しています。';
        case 'セット済み':
            return '作業の予定がカレンダーにセットされています。';
        case '確認済み':
            return '受信者が内容を確認しました（既読）。';
        case '受信済み':
            return '受信者にメッセージが届いています（未確認）。';
        case '既読済み':
            return '既に既読となっています。';
        default:
            return '';
    }
}

function statusIcon(status) {
    // return small SVG icons as strings
    switch (status) {
        case '完了':
            return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3 w-3 text-yellow-800"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 00-1.414-1.414L7 12.172 4.707 9.879a1 1 0 00-1.414 1.414l3 3a1 1 0 001.414 0l9-9z" clip-rule="evenodd"/></svg>`;
        case 'セット済み':
            return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3 w-3 text-blue-800"><path d="M6 2a1 1 0 000 2h8a1 1 0 100-2H6zM3 6a1 1 0 011-1h12a1 1 0 011 1v9a2 2 0 01-2 2H5a2 2 0 01-2-2V6z"/></svg>`;
        case '確認済み':
            return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3 w-3 text-green-800"><path d="M10 2a8 8 0 100 16 8 8 0 000-16zM9 7h2v5H9V7zm0 7h2v2H9v-2z"/></svg>`;
        case '受信済み':
            return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3 w-3 text-indigo-800"><path d="M2 5a2 2 0 012-2h12a2 2 0 012 2v1l-8 4.5L2 6V5z"/><path d="M18 8.118V15a2 2 0 01-2 2H4a2 2 0 01-2-2V8.118l8 4.5 8-4.5z"/></svg>`;
        case '既読済み':
            return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3 w-3 text-gray-800"><path d="M3 5a1 1 0 011-1h12a1 1 0 011 1v9a2 2 0 01-2 2H5a2 2 0 01-2-2V5z"/></svg>`;
        default:
            return '';
    }
}

// Real-time: subscribe to jobmessages channel and prepend new job messages
import useToasts from '@/Composables/useToasts';
import { computed, onMounted, ref } from 'vue';
const localMessages = ref(props.messages && props.messages.data ? [...props.messages.data] : []);

// displayMessages deduplicates by project_job_assignment id and returns the latest message per assignment
const displayMessages = computed(() => {
    const arr = Array.isArray(localMessages.value) ? localMessages.value : [];
    const byAssign = new Map();
    for (const m of arr) {
        const aid = m.project_job_assignment && m.project_job_assignment.id ? String(m.project_job_assignment.id) : `noassign-${m.id}`;
        if (!byAssign.has(aid)) {
            byAssign.set(aid, m);
            continue;
        }
        // prefer the most recently created message
        const existing = byAssign.get(aid);
        const eCreated = existing && existing.created_at ? new Date(existing.created_at) : null;
        const mCreated = m && m.created_at ? new Date(m.created_at) : null;
        if (!eCreated && mCreated) {
            byAssign.set(aid, m);
        } else if (eCreated && mCreated && mCreated > eCreated) {
            byAssign.set(aid, m);
        }
    }
    return Array.from(byAssign.values());
});
const { showToast } = useToasts();

// Determine if a JobAssignmentMessage should be considered unread for the current user
function isUnread(m) {
    try {
        const authUser = page.props.auth.user;
        if (!authUser) return false;

        // Normalize id comparison to handle string/number mismatches
        const authId = Number(authUser.id);

        // 1) If current user is the assignee for the project_job_assignment, JAM.read_at is authoritative
        if (m.project_job_assignment && m.project_job_assignment.user && m.project_job_assignment.user.id) {
            if (Number(m.project_job_assignment.user.id) === authId) {
                return !m.read_at; // unread when read_at is null
            }
        }

        // 2) If current user is the sender, treat as unread if JAM hasn't been read (read_at is null)
        if (m.sender && m.sender.id && Number(m.sender.id) === authId) {
            // If JAM has no read_at (no one has marked it read), show as unread for sender as well
            return !m.read_at;
        }

        // 3) If JAM has a global read_at (non-null), consider it read for everyone (conservative)
        if (m.read_at) return false;

        // 4) If JAM links to a Message, check MessageRecipient rows for the current user
        if (m.message && Array.isArray(m.message.recipients)) {
            const rec = m.message.recipients.find((r) => {
                const rId = r && (r.user_id || (r.user && r.user.id));
                return typeof rId !== 'undefined' && Number(rId) === authId;
            });
            if (rec) return !rec.read_at;
        }

        // 5) Fallback: if not sender, treat as unread so recipients/coordinators notice new items
        return !(m.sender && m.sender.id && Number(m.sender.id) === authId);
    } catch (err) {
        // isUnread error suppressed
        return false;
    }
}

onMounted(() => {
    try {
        const authUser = page.props.auth.user;
        if (!authUser || !window.Echo) return;
        const channel = window.Echo.private('jobmessages.' + authUser.id);
        channel.listen('JobMessageCreated', async (e) => {
            // event payload received (silently handled)
            // DO NOT show a page-local toast here; toasts are centralized in AppLayout
            // to avoid duplicate toasts across the app.

            // If the event includes a full jam payload, use it. Otherwise, if only an id is provided,
            // attempt to fetch the jam via a lightweight endpoint.
            try {
                let jam = null;
                if (e.jam) {
                    jam = e.jam;
                } else if (e.job_assignment_message_id) {
                    // Try to fetch the jam item from the server (lightweight show endpoint)
                    try {
                        const resp = await fetch(route('api.jobbox.show', { id: e.job_assignment_message_id }), {
                            credentials: 'same-origin',
                            headers: { Accept: 'application/json' },
                        });
                        if (resp.ok) {
                            const json = await resp.json();
                            jam = json.data || json;
                        }
                    } catch (fetchErr) {
                        // fetch jam failed (non-fatal)
                    }
                }

                const newJamBase = jam
                    ? { ...jam }
                    : {
                          id: e.job_assignment_message_id || e.message_id || `tmp-${Date.now()}`,
                          subject: e.subject || null,
                          body: e.jam && e.jam.body ? e.jam.body : null,
                          sender: {
                              name: e.from_user_name || null,
                              id: e.from_user_id || (e.jam && e.jam.sender && e.jam.sender.id ? e.jam.sender.id : null),
                          },
                          project_job_assignment: null,
                          read_at: e.jam && e.jam.read_at ? e.jam.read_at : null,
                          from_user_id: e.from_user_id || (e.jam && e.jam.sender && e.jam.sender.id ? e.jam.sender.id : null),
                      };

                // If body contains an inline desired_start_date, try to extract YYYY-MM-DD and populate the preview
                try {
                    const bodyHtml = (newJamBase.body || '') + (e.jam && e.jam.body ? e.jam.body : '');
                    const match =
                        bodyHtml.match(/希望開始日[:：\s]*([0-9]{4}-[0-9]{2}-[0-9]{2})/i) ||
                        bodyHtml.match(/希望日[:：\s]*([0-9]{4}-[0-9]{2}-[0-9]{2})/i);
                    if (match && match[1]) {
                        newJamBase.project_job_assignment = newJamBase.project_job_assignment || {};
                        newJamBase.project_job_assignment.desired_start_date = match[1];
                    } else if (e.jam && e.jam.project_job_assignment && e.jam.project_job_assignment.desired_start_date) {
                        newJamBase.project_job_assignment = newJamBase.project_job_assignment || {};
                        newJamBase.project_job_assignment.desired_start_date = e.jam.project_job_assignment.desired_start_date;
                    }
                    // Prefer jam-level read_at if provided
                    if (e.jam && e.jam.read_at) {
                        newJamBase.read_at = e.jam.read_at;
                    }
                } catch (err) {
                    // ignore parsing errors
                }

                const newJam = { ...newJamBase, __is_new: true };

                // Prepend and keep pagination length consistent
                localMessages.value.unshift(newJam);
                const perPage = props.messages?.per_page || 20;
                if (localMessages.value.length > perPage) {
                    localMessages.value.splice(perPage);
                }

                // If server provided total, increment it in a local shadow so UI shows updated total
                try {
                    if (!props.messages) props.messages = {};
                    props.messages.total = (props.messages.total || localMessages.value.length) + 1;
                } catch (err) {
                    // non-fatal
                }

                // Clear the new highlight after 20 seconds
                setTimeout(() => {
                    const idx = localMessages.value.findIndex((x) => x.id === newJam.id);
                    if (idx >= 0) {
                        localMessages.value[idx].__is_new = false;
                    }
                }, 20000);
            } catch (err) {
                // JobMessageCreated handling failed (non-fatal)
            }
        });

        // Listen for JobMessageRead so the UI can mark specific JAM rows as read in real-time.
        channel.listen('JobMessageRead', (e) => {
            try {
                const mid = e && e.message_id ? e.message_id : null;
                if (!mid) return;
                const idx = localMessages.value.findIndex((x) => Number(x.id) === Number(mid));
                if (idx >= 0) {
                    // Prefer the read_at timestamp from the event payload when available
                    const eventReadAt = e && e.read_at ? e.read_at : null;
                    localMessages.value[idx].read_at = eventReadAt || new Date().toISOString();
                }
            } catch (err) {
                // non-fatal
            }
        });
    } catch (err) {
        // JobBox Echo subscribe failed (non-fatal)
    }
});
</script>

<style>
/* Tooltip bubble override for JobBox: white background, shadow, rounded, arrow matches background */
.jobbox-tooltip {
    background-color: #eff6ff !important; /* light blue (bg-blue-50) */
    color: #0f172a !important; /* gray-900-ish */
    box-shadow: 0 6px 18px rgba(15, 23, 42, 0.06) !important;
    border: 1px solid rgba(14, 165, 233, 0.12) !important; /* subtle blue border */
    border-radius: 8px !important;
    padding: 0.5rem 0.75rem !important;
    font-size: 0.875rem !important;
}
/* Ensure the arrow inherits the white background when rendered with bg-primary/fill-primary classes */
.jobbox-tooltip .bg-primary {
    background-color: #eff6ff !important;
}
.jobbox-tooltip .fill-primary {
    fill: #eff6ff !important;
}

/* Slightly larger arrow size adjustment if needed */
.jobbox-tooltip .size-2\.5 {
    width: 10px;
    height: 10px;
}
</style>

<style scoped>
.new-highlight {
    background-color: #fff7cc; /* pale yellow */
}

.bg-yellow-50 {
    background-color: #fff7cc;
}
</style>
