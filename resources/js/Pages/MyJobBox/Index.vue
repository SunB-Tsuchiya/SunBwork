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
                            <th class="border px-4 py-2">送受信</th>
                            <th class="border px-4 py-2">相手</th>
                            <th class="cursor-pointer border px-4 py-2" @click.prevent="changeSort('desired_start_date')">
                                予定日・時刻 <span v-if="isSorted('desired_start_date')">{{ sortIcon() }}</span>
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
                            v-for="m in props.myAssignments?.data || displayMessages"
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
                            <td class="border px-4 py-2 text-sm text-gray-700">
                                {{ m.user?.name || m.sender?.name || m.project_job_assignment?.user?.name || '-' }}
                            </td>
                            <td class="border px-4 py-2">
                                <div v-if="getFirstEvent(m)">
                                    {{ formatDateTimeRange(
                                        getFirstEvent(m).start || getFirstEvent(m).starts_at,
                                        getFirstEvent(m).end || getFirstEvent(m).ends_at,
                                    ) }}
                                </div>
                                <div
                                    v-else-if="
                                        m.desired_start_date ||
                                        m.desired_at ||
                                        (m.project_job_assignment &&
                                            (m.project_job_assignment.desired_start_date || m.project_job_assignment.desired_at))
                                    "
                                >
                                    {{ formatDateTimeRange(
                                        m.desired_start_date ||
                                            m.desired_at ||
                                            (m.project_job_assignment &&
                                                (m.project_job_assignment.desired_start_date || m.project_job_assignment.desired_at)),
                                        m.desired_time || (m.project_job_assignment && m.project_job_assignment.desired_time),
                                    ) }}
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
                            <td class="border px-4 py-2">
                                <template v-if="!(m.read_at || m.readAt || m.project_job_assignment?.read_at)">
                                    <span class="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800"
                                        >未読</span
                                    >
                                </template>
                                <template v-else>
                                    <span class="text-sm text-gray-600">既読</span>
                                </template>
                            </td>
                            <td class="border px-4 py-2">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium">{{
                                    m.statusModel?.name || m.status_label || m.status_name || ''
                                }}</span>
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
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
const props = defineProps({ projectJob: Object, messages: Object, myAssignments: Object });
const page = usePage();
page.props.q_model = page.props.q || '';
// propagate server sort state into the component for UI
const currentSort = page.props.sort || null;
const currentDir = page.props.dir || 'desc';

const sortState = reactive({ sort: currentSort, dir: currentDir });

const localMessages = ref(props.messages?.data || []);
const displayMessages = computed(() => props.messages?.data || localMessages.value);

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
    if (!confirm('このジョブ情報を発信しますか？')) return;
    const to = m.project_job_assignment?.user_id ? [m.project_job_assignment.user_id] : [];
    const payload = {
        project_job_assignment_id: m.project_job_assignment?.id || null,
        to: to,
        subject: m.subject || m.project_job_assignment?.title || null,
        body: m.body || null,
        attachments: [],
    };
    router.post(route('coordinator.project_jobs.jobbox.store', { projectJob: props.projectJob?.id }), payload, {
        onSuccess: () => {
            alert('発信しました。');
        },
    });
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
</script>
