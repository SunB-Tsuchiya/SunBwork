<template>
    <AppLayout :title="`MyJobBox - ${props.projectJob?.name || ''}`">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">MyJobBox — マイジョブ</h2>
        </template>

        <div class="mx-auto max-w-6xl rounded bg-white p-6 shadow">
            <h1 class="mb-4 text-2xl font-bold">MyJobBox：{{ props.projectJob?.name || '' }}</h1>
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
                            <!-- <th class="border px-4 py-2">送受信</th>
                            <th class="border px-4 py-2">相手</th> -->
                            <th class="cursor-pointer border px-4 py-2" @click.prevent="changeSort('desired_start_date')">
                                予定日・時刻 <span v-if="isSorted('desired_start_date')">{{ sortIcon() }}</span>
                            </th>
                            <th class="cursor-pointer border px-4 py-2" @click.prevent="changeSort('subject')">
                                タイトル <span v-if="isSorted('subject')">{{ sortIcon() }}</span>
                            </th>
                            <th class="border px-4 py-2">クライアント</th>
                            <!-- <th class="border px-4 py-2">既読</th> -->
                            <th class="border px-4 py-2">ステータス</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="m in props.myAssignments?.data || displayMessages"
                            :key="m.id"
                            :class="['cursor-pointer hover:bg-gray-100', m.__is_new ? 'new-highlight' : '']"
                            @click.prevent="rowClick(m, $event)"
                            role="button"
                        >
                            <!-- <td class="border px-4 py-2">
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
                            <td class="border px-4 py-2 text-sm text-gray-700">
                                {{ m.user?.name || m.sender?.name || m.project_job_assignment?.user?.name || '-' }}
                            </td> -->
                            <td class="border px-4 py-2">
                                <div v-if="getFirstEvent(m)">
                                    {{
                                        formatDateTimeRange(
                                            getFirstEvent(m).start || getFirstEvent(m).starts_at,
                                            getFirstEvent(m).end || getFirstEvent(m).ends_at,
                                        )
                                    }}
                                </div>
                                <div
                                    v-else-if="
                                        m.desired_start_date ||
                                        m.desired_at ||
                                        (m.project_job_assignment &&
                                            (m.project_job_assignment.desired_start_date || m.project_job_assignment.desired_at))
                                    "
                                >
                                    {{
                                        formatDateTimeRange(
                                            m.desired_start_date ||
                                                m.desired_at ||
                                                (m.project_job_assignment &&
                                                    (m.project_job_assignment.desired_start_date || m.project_job_assignment.desired_at)),
                                            m.desired_time || (m.project_job_assignment && m.project_job_assignment.desired_time),
                                        )
                                    }}
                                </div>
                                <div v-else>-</div>
                            </td>
                            <td class="border px-4 py-2">{{ m.title || m.subject || (m.body && m.body.slice(0, 80)) || '' }}</td>
                            <td class="border px-4 py-2">
                                {{
                                    m.projectJob?.client?.name ||
                                    m.project_job?.client?.name ||
                                    m.project_job_assignment?.projectJob?.client?.name ||
                                    '-'
                                }}
                            </td>
                            <!-- <td class="border px-4 py-2">
                                <template v-if="!(m.read_at || m.readAt || m.project_job_assignment?.read_at)">
                                    <span class="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800"
                                        >未読</span
                                    >
                                </template>
                                <template v-else>
                                    <span class="text-sm text-gray-600">既読</span>
                                </template>
                            </td> -->
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
                <div class="text-sm text-gray-600">全 {{ props.myAssignments?.total || props.messages?.total || localMessages?.length || 0 }} 件</div>
                <div class="flex items-center space-x-2">
                    <button
                        :disabled="!(props.myAssignments?.prev_page_url || props.messages?.prev_page_url)"
                        @click.prevent="navigateTo(props.myAssignments?.prev_page_url || props.messages?.prev_page_url)"
                        class="rounded border px-3 py-1 disabled:opacity-50"
                    >
                        前へ
                    </button>
                    <div class="text-sm">
                        {{ props.myAssignments?.current_page || props.messages?.current_page || 0 }} /
                        {{ props.myAssignments?.last_page || props.messages?.last_page || 0 }}
                    </div>
                    <button
                        :disabled="!(props.myAssignments?.next_page_url || props.messages?.next_page_url)"
                        @click.prevent="navigateTo(props.myAssignments?.next_page_url || props.messages?.next_page_url)"
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
import { computed, onMounted, reactive, ref } from 'vue';
const props = defineProps({ projectJob: Object, messages: Object, myAssignments: Object });
const page = usePage();
page.props.q_model = page.props.q || '';
// propagate server sort state into the component for UI
const currentSort = page.props.sort || null;
const currentDir = page.props.dir || 'desc';

const sortState = reactive({ sort: currentSort, dir: currentDir });

const localMessages = ref(props.messages?.data || []);
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

function isSorted(key) {
    return sortState.sort === key;
}

function sortIcon() {
    return sortState.dir === 'asc' ? '▲' : '▼';
}

function navigateTo(url) {
    if (!url) return;
    try {
        router.get(url);
    } catch (e) {
        try {
            window.location.href = url;
        } catch (err) {
            // ignore
        }
    }
}

function formatDate(d) {
    if (!d) return '-';
    try {
        const s = String(d).split('T')[0];
        return s;
    } catch (e) {
        return String(d).split('T')[0] || '-';
    }
}

function formatTime(t) {
    if (!t) return '';
    const core = String(t).split('.')[0];
    const parts = core.split(':');
    if (parts.length >= 2) return parts[0].padStart(2, '0') + ':' + parts[1].padStart(2, '0');
    return t;
}

function formatDateTimeRange(start, end) {
    if (!start && !end) return '-';

    let s = start == null ? '' : String(start);
    let e = end == null ? '' : String(end);

    // If start contains a full range like "2026-01-12T09:00-2026-01-12T17:00" and end is empty,
    // split it into start and end parts.
    if ((!e || e === '') && s.includes('T') && s.includes('-')) {
        const parts = s.split('-');
        if (parts.length >= 2) {
            const left = parts[0];
            const right = parts.slice(1).join('-');
            s = left;
            e = right;
        }
    }

    // If end contains a full range and start is a date-only string, pick times from end.
    if (e && e.includes('T') && e.includes('-') && !(s.includes('T') || s.includes(':'))) {
        const parts = e.split('-');
        if (parts.length >= 2) {
            const left = parts[0];
            const right = parts.slice(1).join('-');
            // left holds a datetime for the start time, right holds datetime for end time
            const startDate = formatDate(s);
            const startTime = formatTime(left);
            const endTime = formatTime(right);
            if (startTime && endTime) return `${startDate} ${startTime}-${endTime}`;
            if (startTime) return `${startDate} ${startTime}`;
            return startDate;
        }
    }

    const startDate = s ? formatDate(s) : '';
    const endDate = e ? formatDate(e) : '';

    // extract time parts
    function extractTimeFrom(str, preferLastSegment = false) {
        if (!str) return '';
        // if it's a combined range like "09:00-17:00"
        if (str.includes('-') && !str.includes('T')) {
            const p = str.split('-');
            return preferLastSegment ? formatTime(p[p.length - 1]) : formatTime(p[0]);
        }
        // if contains 'T', take substring after 'T' up to end or before '-' if present
        if (str.includes('T')) {
            const afterT = str.split('T')[1] || '';
            const beforeDash = afterT.split('-')[0];
            return formatTime(beforeDash);
        }
        // if contains space-separated time
        if (str.includes(' ')) {
            const parts = str.split(' ');
            return formatTime(parts[1] || parts[0]);
        }
        // otherwise treat as time
        return formatTime(str);
    }

    const startTime = extractTimeFrom(s, false);
    const endTime = extractTimeFrom(e, true);

    if (startDate && endDate && startDate === endDate) {
        if (startTime || endTime) return `${startDate} ${startTime || ''}${endTime ? '-' + endTime : ''}`.trim();
        return `${startDate}`;
    }

    if (startDate && endDate) {
        if (startTime && endTime) return `${startDate} ${startTime}-${endDate} ${endTime}`;
        if (startTime) return `${startDate} ${startTime}-${endDate}`;
        return `${startDate}-${endDate}`;
    }

    if (startDate) return startTime ? `${startDate} ${startTime}` : `${startDate}`;
    if (endDate) return endTime ? `${endDate} ${endTime}` : `${endDate}`;
    return '-';
}

function getFirstEvent(m) {
    // m.events may be an array or a paginated object { data: [...] }
    try {
        if (m.events) {
            if (Array.isArray(m.events) && m.events.length) return m.events[0];
            if (m.events.data && Array.isArray(m.events.data) && m.events.data.length) return m.events.data[0];
        }
        if (m.project_job_assignment && m.project_job_assignment.events) {
            const e = m.project_job_assignment.events;
            if (Array.isArray(e) && e.length) return e[0];
            if (e.data && Array.isArray(e.data) && e.data.length) return e.data[0];
        }
    } catch (e) {
        // defensive: ignore and return null
    }
    return null;
}

function getBackLink() {
    try {
        if (props.projectJob && props.projectJob.id) {
            return typeof route === 'function' ? route('project_jobs.show', props.projectJob.id) : `/project_jobs/${props.projectJob.id}`;
        }
        return typeof route === 'function' ? route('dashboard') : '/';
    } catch (e) {
        return '/';
    }
}

function getAssignmentLink(m) {
    try {
        if (props.myAssignments) {
            return typeof route === 'function' ? route('user.myjobbox.show', { assignment: m.id }) : `/myjobbox/${m.id}`;
        }
        // fallback to jobbox message link when shape is message
        let pjId = props.projectJob?.id;
        if (!pjId) {
            pjId = m.project_job_assignment?.project_job?.id || m.project_job_assignment?.project_job_id || null;
        }
        if (!pjId) return '#';
        try {
            if (page.props.auth.user && page.props.auth.user.isCoordinator) {
                return route('coordinator.project_jobs.jobbox.show', { projectJob: pjId, message: m.id });
            }
            return route('project_jobs.jobbox.show', { projectJob: pjId, message: m.id });
        } catch (e) {
            return '#';
        }
    } catch (e) {
        return '#';
    }
}

async function rowClick(m, event) {
    const tag = event.target && event.target.tagName ? event.target.tagName.toLowerCase() : '';
    if (tag === 'a' || tag === 'button' || (event.target.closest && event.target.closest('a,button'))) return;

    // If this is an assignment (myAssignments) try to find a linked event first
    try {
        if (props.myAssignments && m && (m.id || m.project_job_assignment_id || m.project_job_assignment?.id)) {
            const assId = m.id || m.project_job_assignment_id || (m.project_job_assignment && m.project_job_assignment.id);
            const userId = m.user?.id || m.user_id || (page.props.auth.user && page.props.auth.user.id) || '';
            // Query events index for this user and job id; backend returns JSON array
            let eventsUrl = null;
            try {
                eventsUrl = typeof route === 'function' ? route('events.index') : '/events';
                const query = [];
                if (userId) query.push('user_id=' + encodeURIComponent(userId));
                if (assId) query.push('job=' + encodeURIComponent(assId));
                if (query.length) eventsUrl = eventsUrl + '?' + query.join('&');
            } catch (e) {
                eventsUrl = '/events?job=' + encodeURIComponent(assId);
            }

            try {
                const res = await fetch(eventsUrl, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (res.ok) {
                    const payload = await res.json();
                    if (Array.isArray(payload) && payload.length > 0) {
                        const ev = payload[0];
                        const evId = ev.id || ev.event_id || (ev.extendedProps && (ev.extendedProps.event_id || ev.extendedProps.id));
                        if (evId) {
                            try {
                                router.get(typeof route === 'function' ? route('events.show', evId) : '/events/' + evId);
                                return;
                            } catch (err) {
                                try {
                                    window.location.href = typeof route === 'function' ? route('events.show', evId) : '/events/' + evId;
                                    return;
                                } catch (er) {}
                            }
                        }
                    }
                }
            } catch (e) {
                // ignore fetch errors and fallback to assignment link
            }
        }
    } catch (e) {
        // ignore
    }

    const url = getAssignmentLink(m);
    if (url && url !== '#') {
        try {
            router.visit(url, { preserveState: false });
        } catch (e) {
            try {
                window.location.href = url;
            } catch (err) {}
        }
    }
}

function getAssignmentStatus(m) {
    try {
        // Prefer canonical status object when available (in assignment.status.key)
        const jam = m || {};
        const assignment = m || {};

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
        // if (pjId) {
        //     const r =
        //         page.props.auth.user && page.props.auth.user.isCoordinator ? 'coordinator.project_jobs.jobbox.index' : 'project_jobs.jobbox.index';
        //     router.get(route(r, { projectJob: pjId }), params, { preserveState: false });
        //     return;
        // }
        // global jobbox route: named 'project_jobs.index' or fallback route
        router.get(route('project_jobs.index'), params, { preserveState: false });
    } catch (err) {
        // fallback: reload current url with query params
        router.get(window.location.pathname, params, { preserveState: false });
    }
}

onMounted(() => {
    try {
        // debugger;
        // console.log('DEBUG page.props', page.props);
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
