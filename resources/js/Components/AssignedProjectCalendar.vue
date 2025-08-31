<template>
    <div class="assigned-calendar-container">
        <div class="mb-4 flex gap-4">
            <button @click="openMemoModalTop" class="rounded bg-orange-500 px-4 py-2 text-white">メモ作成</button>
        </div>
        <FullCalendar ref="calendarRef" :options="calendarOptions" :events="plainCalendarEvents" />

        <!-- メモ作成モーダル -->
        <div v-if="showMemoModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-lg">
                <h2 class="mb-4 text-lg font-bold">メモ作成</h2>
                <div v-if="selectedScheduleIdForMemo" class="mb-2 text-sm text-gray-600">スケジュールID: {{ selectedScheduleIdForMemo }}</div>
                <div class="mb-2">
                    <label class="block text-sm font-medium">日付</label>
                    <input type="date" v-model="memoDate" class="w-full rounded border p-2" />
                </div>
                <div class="mb-2">
                    <label class="block text-sm font-medium">メモ</label>
                    <textarea v-model="memoBody" class="w-full rounded border p-2" rows="6"></textarea>
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" @click="closeMemoModal" class="rounded bg-gray-300 px-4 py-2">キャンセル</button>
                    <button type="button" @click="submitScheduleMemo" class="rounded bg-green-600 px-4 py-2 text-white">保存</button>
                </div>
            </div>
        </div>
        <!-- メモ表示モーダル (読み取り専用) -->
        <div v-if="showMemoShowModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-lg">
                <h2 class="mb-4 text-lg font-bold">メモ詳細</h2>
                <div class="mb-2 text-sm text-gray-600">メモID: {{ memoShowData.id }}</div>
                <div v-if="memoShowData.author" class="mb-2 text-sm text-gray-600">
                    作成者: {{ memoShowData.author.id }} - {{ memoShowData.author.name }}
                </div>
                <!-- <div class="mb-2 text-sm text-gray-600">日付: {{ memoShowData.date }}</div> -->
                <div class="mb-2">
                    <label class="block text-sm font-medium">本文</label>
                    <div class="mt-1 whitespace-pre-wrap text-gray-900">{{ memoShowData.body }}</div>
                </div>
                <div class="mt-4 flex justify-end">
                    <button type="button" @click="closeMemoShowModal" class="rounded bg-gray-300 px-4 py-2">閉じる</button>
                    <button
                        v-if="memoCanEdit(memoShowData)"
                        type="button"
                        @click="openEditFromShow"
                        class="ml-2 rounded bg-blue-600 px-4 py-2 text-white"
                    >
                        編集
                    </button>
                    <button
                        v-if="memoCanEdit(memoShowData)"
                        type="button"
                        @click="deleteMemoFromShow"
                        class="ml-2 rounded bg-red-600 px-4 py-2 text-white"
                    >
                        削除
                    </button>
                </div>
            </div>
        </div>

        <!-- メモ編集モーダル -->
        <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-lg">
                <h2 class="mb-4 text-lg font-bold">メモ編集</h2>
                <div class="mb-2 text-sm text-gray-600">メモID: {{ editingCommentId }}</div>
                <div v-if="editingCommentAuthor" class="mb-2 text-sm text-gray-600">
                    作成者: {{ editingCommentAuthor.id }} - {{ editingCommentAuthor.name }}
                </div>
                <!-- date is intentionally hidden in edit modal: memos are date-only and not editable here -->
                <div class="mb-2">
                    <label class="block text-sm font-medium">メモ</label>
                    <textarea v-model="editingCommentBody" class="w-full rounded border p-2" rows="6"></textarea>
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" @click="showEditModal = false" class="rounded bg-gray-300 px-4 py-2">キャンセル</button>
                    <button
                        v-if="memoCanEdit({ id: editingCommentId, user_id: editingCommentAuthor && editingCommentAuthor.id })"
                        type="button"
                        @click="submitEditComment"
                        class="rounded bg-blue-600 px-4 py-2 text-white"
                    >
                        更新
                    </button>
                    <button
                        v-if="memoCanEdit({ id: editingCommentId, user_id: editingCommentAuthor && editingCommentAuthor.id })"
                        type="button"
                        @click="deleteEditingMemo"
                        class="rounded bg-red-600 px-4 py-2 text-white"
                    >
                        削除
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import timeGridPlugin from '@fullcalendar/timegrid';
import FullCalendar from '@fullcalendar/vue3';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps({
    schedules: { type: Array, default: () => [] },
    events: { type: [Array, Object], default: () => [] },
    comments: { type: Array, default: () => [] },
    memos: { type: Array, default: () => [] },
    project: { type: Object, default: null },
});

const calendarRef = ref(null);
const plainCalendarEvents = ref([]);
const didForceAddEvents = ref(false);

// memo UI state
const showMemoModal = ref(false);
const selectedScheduleIdForMemo = ref(null);
const memoBody = ref('');
const today = new Date();
const yyyy = today.getFullYear();
const mm = String(today.getMonth() + 1).padStart(2, '0');
const dd = String(today.getDate()).padStart(2, '0');
const memoDate = ref(`${yyyy}-${mm}-${dd}`);

// memo show modal state
const showMemoShowModal = ref(false);
const memoShowData = ref({ id: null, date: null, body: '', author: null });

// edit modal state (was missing and caused ReferenceError when opening Edit)
const showEditModal = ref(false);
const editingCommentId = ref(null);
const editingCommentBody = ref('');
const editingCommentDate = ref('');
const editingCommentAuthor = ref(null);

function closeMemoShowModal() {
    showMemoShowModal.value = false;
    memoShowData.value = { id: null, date: null, body: '', author: null };
}

const page = usePage();

const userPropsLocal = computed(() => {
    try {
        const p = page.props;
        if (p && p.user) return p.user;
        if (p && p.value) {
            const v = p.value;
            if (v.auth && v.auth.user) return v.auth.user;
            if (v.user) return v.user;
        }
        if (p && p.auth && p.auth.user) return p.auth.user;
    } catch (e) {}
    return {};
});

const memoCanEdit = (memo) => {
    const u = userPropsLocal.value || {};
    if (!u) return false;
    try {
        if (typeof u.isSuperAdmin === 'function' && u.isSuperAdmin()) return true;
        if (typeof u.isAdmin === 'function' && u.isAdmin()) return true;
        if (typeof u.isLeader === 'function' && u.isLeader()) return true;
        if (typeof u.isCoordinator === 'function' && u.isCoordinator()) return true;
        if (u.id && memo && memo.user_id && u.id === memo.user_id) return true;
        if (u.isSuperAdmin === true || u.isAdmin === true || u.isLeader === true || u.isCoordinator === true) return true;
        if (u.user_role && ['superadmin', 'admin', 'leader', 'coordinator'].includes(String(u.user_role).toLowerCase())) return true;
    } catch (e) {}
    return false;
};

// cache authors by memo id for immediate lookup (helps when props.memos not present)
const memoAuthorById = ref({});

// helper: extract date-only part (YYYY-MM-DD) from various inputs
// - If passed a Date object, return its local date
// - If passed an ISO string with timezone (e.g. 2025-08-07T15:00:00Z), parse it to Date and return local date
// - Otherwise attempt simple string splitting
const fmtDateOnly = (v) => {
    try {
        if (v === null || v === undefined) return v;
        // Date object -> format local date
        if (v instanceof Date) {
            const y = v.getFullYear();
            const m = String(v.getMonth() + 1).padStart(2, '0');
            const d = String(v.getDate()).padStart(2, '0');
            return `${y}-${m}-${d}`;
        }
        const s = String(v);
        // ISO-like string with 'T' -> try to parse and return local date
        if (s.indexOf('T') >= 0) {
            // try parsing to Date to get local date
            const parsed = new Date(s);
            if (!Number.isNaN(parsed.getTime())) {
                const y = parsed.getFullYear();
                const m = String(parsed.getMonth() + 1).padStart(2, '0');
                const d = String(parsed.getDate()).padStart(2, '0');
                return `${y}-${m}-${d}`;
            }
            return s.split('T')[0];
        }
        if (s.indexOf(' ') >= 0) return s.split(' ')[0];
        return s;
    } catch (e) {
        return v;
    }
};

// local memos to show immediately without reload
const localMemos = ref([]);

function openMemoModalTop() {
    selectedScheduleIdForMemo.value = null;
    memoDate.value = `${yyyy}-${mm}-${dd}`;
    memoBody.value = '';
    showMemoModal.value = true;
}

function closeMemoModal() {
    showMemoModal.value = false;
}

// open edit modal from show modal (reuse simple flow: populate local edit state then open edit modal)
function openEditFromShow() {
    // populate edit modal fields (fallback to memoShowData)
    editingCommentId.value = memoShowData.value.id;
    editingCommentBody.value = memoShowData.value.body || '';
    // ensure editing date is date-only
    editingCommentDate.value = fmtDateOnly(memoShowData.value.date) || '';
    editingCommentAuthor.value = memoShowData.value.author || null;
    showMemoShowModal.value = false;
    showEditModal.value = true;
}

async function deleteMemoFromShow() {
    const id = memoShowData.value.id;
    if (!id) return;
    if (!confirm('このメモを削除してよいですか？')) return;
    try {
        await axios.delete(route('coordinator.project_memos.destroy', { memo: id }));
        // remove from localMemos
        localMemos.value = localMemos.value.filter((m) => m.id !== id);
        // also remove from props.memos if present (in place filter won't mutate prop proxy, but helps event list)
        if (Array.isArray(props.memos)) {
            const idx = props.memos.findIndex((m) => m.id === id);
            if (idx >= 0) props.memos.splice(idx, 1);
        }
        showMemoShowModal.value = false;
        alert('メモを削除しました');
    } catch (e) {
        console.error('deleteMemoFromShow error', e);
        alert('メモの削除に失敗しました');
    }
}

// Build a simple normalized event list from props.schedules (read-only)
const calendarEvents = computed(() => {
    const list = [];

    // prefer parent-provided events if present
    const rawEvents = (() => {
        try {
            if (!props.events) return [];
            if (Array.isArray(props.events)) return props.events;
            if (props.events.value && Array.isArray(props.events.value)) return props.events.value;
            return Array.isArray(props.events) ? props.events : [];
        } catch (e) {
            return [];
        }
    })();

    if (rawEvents && rawEvents.length > 0) {
        rawEvents.forEach((ev) => {
            list.push({
                id: ev.id ?? ev.event_id ?? undefined,
                title: ev.title ?? ev.name ?? '',
                start: ev.start ?? ev.start_date ?? undefined,
                end: ev.end ?? ev.end_date ?? undefined,
                allDay: ev.allDay ?? ev.all_day ?? false,
                color: ev.backgroundColor ?? ev.color ?? '#3b82f6',
                description: ev.description ?? ev.extendedProps?.description ?? '',
                extendedProps: ev.extendedProps ?? {},
            });
        });
        return list;
    }

    // fallback to schedules
    (props.schedules || []).forEach((s) => {
        if (!s || !s.start_date) return;
        const fmt = (v) => {
            try {
                return String(v).split('T')[0];
            } catch (e) {
                return String(v);
            }
        };
        const startDateOnly = fmt(s.start_date);
        let endDateOnly = s.end_date ? fmt(s.end_date) : undefined;
        if (endDateOnly) {
            try {
                const d = new Date(endDateOnly);
                d.setDate(d.getDate() + 1);
                endDateOnly = d.toISOString().split('T')[0];
            } catch (e) {}
        }
        const color = s.color ?? '#3b82f6';
        list.push({
            id: s.id,
            title: s.name ?? '',
            start: startDateOnly,
            end: endDateOnly ?? undefined,
            allDay: true,
            color: color,
            backgroundColor: color,
            borderColor: color,
            description: s.description ?? '',
            extendedProps: { project_schedule_id: s.id, progress: s.progress ?? null },
        });
    });

    // include comments/memos as simple icons if present
    (props.comments || []).forEach((c) => {
        if (!c.date) return;
        list.push({
            id: `comment-${c.id}`,
            title: '🗒️',
            start: c.date,
            allDay: true,
            color: '#f59e42',
            extendedProps: { comment_id: c.id, body: c.body },
        });
    });
    (props.memos || []).forEach((m) => {
        if (!m.date) return;
        // normalize author: prefer explicit `author`, fallback to `user` relation
        const author = m.author ?? (m.user ? { id: m.user.id, name: m.user.name } : null);
        // create a local Date object at local midnight to avoid timezone shifts when FullCalendar renders
        const dateOnly = fmtDateOnly(m.date);
        let startDate = dateOnly;
        try {
            const parts = String(dateOnly)
                .split('-')
                .map((x) => parseInt(x, 10));
            if (parts.length === 3 && parts.every((n) => !Number.isNaN(n))) {
                startDate = new Date(parts[0], parts[1] - 1, parts[2]);
            }
        } catch (e) {}
        list.push({
            id: `memo-${m.id}`,
            title: '📝',
            start: startDate,
            allDay: true,
            color: m.color ?? '#60a5fa',
            extendedProps: { memo_id: m.id, body: m.body ?? (m.body === 0 ? '0' : ''), author: author },
        });
    });

    // local client-created memos (avoid duplicates by id)
    localMemos.value.forEach((m) => {
        if (!m.date) return;
        // don't duplicate if server already returned same memo id
        const exists = list.some((ev) => ev.extendedProps && ev.extendedProps.memo_id === m.id);
        if (!exists) {
            const memoColor = m.color ?? '#60a5fa';
            // build a local Date object for start
            let startDate = m.date;
            try {
                const parts = String(fmtDateOnly(m.date))
                    .split('-')
                    .map((x) => parseInt(x, 10));
                if (parts.length === 3 && parts.every((n) => !Number.isNaN(n))) startDate = new Date(parts[0], parts[1] - 1, parts[2]);
            } catch (e) {}
            list.push({
                id: `memo-local-${m.id}`,
                title: '📝',
                start: startDate,
                allDay: true,
                color: memoColor,
                extendedProps: { memo_id: m.id, body: m.body, author: m.author ?? null },
            });
        }
    });

    return list;
});

// keep plain clone
watch(
    calendarEvents,
    (val) => {
        try {
            if (!Array.isArray(val)) {
                plainCalendarEvents.value = [];
                return;
            }
            // prefer structuredClone to preserve Date objects; fallback to JSON cycle if not available
            try {
                if (typeof structuredClone === 'function') {
                    plainCalendarEvents.value = val.map((e) => structuredClone(e));
                } else {
                    plainCalendarEvents.value = val.map((e) => JSON.parse(JSON.stringify(e)));
                }
            } catch (e) {
                plainCalendarEvents.value = Array.isArray(val) ? val.slice() : [];
            }
        } catch (e) {
            plainCalendarEvents.value = Array.isArray(val) ? val.slice() : [];
        }
    },
    { immediate: true },
);

// inject into FullCalendar if needed
watch(
    plainCalendarEvents,
    (events) => {
        try {
            const apiNow = calendarRef.value && calendarRef.value.getApi ? calendarRef.value.getApi() : null;
            if (!apiNow) return;
            try {
                // remove existing sources and add the new events array
                apiNow.getEventSources().forEach((s) => s.remove());
            } catch (e) {}
            try {
                if (Array.isArray(events) && events.length > 0) {
                    // If structuredClone is available, use it to preserve Date objects
                    if (typeof structuredClone === 'function') {
                        apiNow.addEventSource(events.map((e) => structuredClone(e)));
                    } else {
                        // Fallback: ensure start is a date-only string (YYYY-MM-DD) to avoid timezone shifts
                        apiNow.addEventSource(
                            events.map((ev) => {
                                try {
                                    const copy = JSON.parse(JSON.stringify(ev));
                                    if (copy.start && typeof copy.start === 'string') copy.start = String(copy.start).split('T')[0];
                                    return copy;
                                } catch (e) {
                                    return ev;
                                }
                            }),
                        );
                    }
                    didForceAddEvents.value = true;
                }
            } catch (e) {}
        } catch (e) {}
    },
    { immediate: true },
);

const calendarOptions = computed(() => ({
    plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
    initialView: 'dayGridMonth',
    locale: 'ja',
    firstDay: 1,
    headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek' },
    selectable: true,
    height: 'auto',
    editable: false,
    eventClick: function (info) {
        try {
            // If this is a memo, open the memo show modal
            if (info.event && info.event.extendedProps && info.event.extendedProps.memo_id) {
                const mid = info.event.extendedProps.memo_id;
                // prefer extendedProps values (they come from the event mapping) and fallback to props/local memos
                const ext = info.event.extendedProps || {};
                const found =
                    (props.memos || []).find((m) => String(m.id) === String(mid)) ||
                    (localMemos.value || []).find((m) => String(m.id) === String(mid));
                // debug: log what we found for this click
                try {
                    console.log('eventClick memo mid', mid, 'ext', ext, 'found', found);
                } catch (e) {}
                // populate memoShowData.value so Vue reactivity updates the modal
                memoShowData.value = {
                    id: found?.id ?? mid ?? ext.memo_id ?? null,
                    // date: prefer event's parsed start (info.event.start) to avoid UTC-string misinterpretation,
                    // then prefer found.date if event.start is not available
                    date: fmtDateOnly(
                        info.event && info.event.start
                            ? info.event.start
                            : (found?.date ?? (info.event.startStr ? info.event.startStr.split('T')[0] : info.event.start)),
                    ),
                    // body: prefer extendedProps.body (from event) then found.body
                    body: ext.body ?? found?.body ?? '',
                    // author: prefer cache, then extendedProps.author, then found.author, then found.user
                    author:
                        memoAuthorById.value[String(mid)] ??
                        ext.author ??
                        found?.author ??
                        (found?.user ? { id: found.user.id, name: found.user.name } : null) ??
                        null,
                };
                try {
                    console.log('memoShowData set', memoShowData.value);
                } catch (e) {}
                showMemoShowModal.value = true;
                return;
            }
            // otherwise fallback to a simple alert for now
            const desc = info.event.extendedProps && info.event.extendedProps.description ? info.event.extendedProps.description : '';
            const title = info.event.title || '';
            if (desc && desc.trim() !== '') alert(`${title}\n\n${desc}`);
            else alert(title);
        } catch (e) {}
    },
    select: function (selectionInfo) {
        const dateStr = selectionInfo.startStr ? selectionInfo.startStr.split('T')[0] : selectionInfo.start;
        memoDate.value = dateStr;
        selectedScheduleIdForMemo.value = null;
        memoBody.value = '';
        showMemoModal.value = true;
    },
}));

onMounted(() => {
    nextTick(() => {
        // debug: log received memos prop on mount
        try {
            console.log('AssignedProjectCalendar mounted props.memos', props.memos);
        } catch (e) {}
        // small initial injection attempt
        try {
            setTimeout(() => {
                try {
                    const apiNow = calendarRef.value && calendarRef.value.getApi ? calendarRef.value.getApi() : null;
                    if (apiNow && Array.isArray(plainCalendarEvents.value) && plainCalendarEvents.value.length > 0) {
                        apiNow.getEventSources().forEach((s) => s.remove());
                        apiNow.addEventSource(JSON.parse(JSON.stringify(plainCalendarEvents.value)));
                        didForceAddEvents.value = true;
                    }
                } catch (e) {}
            }, 250);
        } catch (e) {}
    });
});

async function submitScheduleMemo() {
    if (!memoBody.value || memoBody.value.trim() === '') {
        alert('メモの内容を入力してください');
        return;
    }
    try {
        // If a schedule id was selected (not implemented via click here), post to schedule comments
        if (selectedScheduleIdForMemo.value) {
            await axios.post(route('coordinator.project_schedule_comments.store', { project_schedule: selectedScheduleIdForMemo.value }), {
                body: memoBody.value,
                metadata: { date: memoDate.value },
            });
            showMemoModal.value = false;
            memoBody.value = '';
            return;
        }
        // project-level memo
        // send a timezone-safe datetime (set to 13:00 local) to avoid date shifting when server treats as UTC
        const safeDateTime = (dStr) => {
            try {
                if (!dStr) return dStr;
                // prefer YYYY-MM-DD input (from <input type=date>)
                const dateOnly = String(dStr).split('T')[0];
                // construct local 13:00 and format as ISO without timezone offset
                const dt = new Date(dateOnly + 'T13:00:00');
                return dt.toISOString();
            } catch (e) {
                return dStr;
            }
        };

        const payload = {
            project_id: props.project ? props.project.id : null,
            // send a datetime at 13:00 to avoid shifting to previous day in UTC
            date: safeDateTime(memoDate.value),
            body: memoBody.value,
        };
        // debug: log payload before send
        try {
            console.log('submitScheduleMemo payload', payload);
        } catch (e) {}
        const resp = await axios.post(route('coordinator.project_memos.store'), payload);
        if (resp && resp.data && resp.data.memo) {
            const m = resp.data.memo;
            // debug: log POST response
            try {
                console.log('submitScheduleMemo resp', resp && resp.data ? resp.data : resp);
            } catch (e) {}
            // optimistic push
            localMemos.value.push({
                id: m.id,
                project_id: m.project_id,
                // store plain date-only for display
                date: fmtDateOnly(m.date),
                body: m.body,
                color: m.color ?? null,
                author: m.author ?? (m.user ? { id: m.user.id, name: m.user.name } : null) ?? null,
            });
            // cache author for immediate lookup
            memoAuthorById.value[String(m.id)] = m.author ?? (m.user ? { id: m.user.id, name: m.user.name } : null) ?? null;
            // fetch canonical list of memos for this project and replace localMemos
            try {
                const listResp = await axios.get(route('coordinator.project_memos.index'), {
                    params: { project_id: props.project ? props.project.id : null },
                });
                // debug: log list GET response
                try {
                    console.log('submitScheduleMemo listResp', listResp && listResp.data ? listResp.data : listResp);
                } catch (e) {}
                if (listResp && listResp.data && Array.isArray(listResp.data.memos)) {
                    // map to localMemos shape (ensure author present)
                    localMemos.value = listResp.data.memos.map((mm) => ({
                        id: mm.id,
                        project_id: mm.project_id,
                        date: fmtDateOnly(mm.date),
                        body: mm.body,
                        color: mm.color ?? null,
                        author: mm.author ?? null,
                    }));
                }
            } catch (e) {
                // if fetching memos failed, keep optimistic entry and fallback to reload
                console.error('fetch memos after create failed', e);
                try {
                    window.location.reload();
                } catch (er) {}
            }
        }
        showMemoModal.value = false;
        memoBody.value = '';
    } catch (e) {
        console.error('submitScheduleMemo (assigned) error', e);
        alert('メモの保存に失敗しました');
    }
}

// --- Edit modal template is inserted near other modals in the template section.

// Submit edits for an existing project memo
async function submitEditComment() {
    const id = editingCommentId.value;
    if (!id) return;
    if (!editingCommentBody.value || editingCommentBody.value.trim() === '') {
        alert('メモの内容を入力してください');
        return;
    }
    try {
        // send existing memo date unchanged (use memoShowData as source-of-truth)
        const payload = { date: fmtDateOnly(memoShowData.value.date), body: editingCommentBody.value };
        const resp = await axios.put(route('coordinator.project_memos.update', { memo: id }), payload);
        // update local copies: localMemos and props.memos
        const updated = resp && resp.data && (resp.data.memo || resp.data) ? resp.data.memo || resp.data : null;
        if (updated) {
            // update localMemos
            localMemos.value = localMemos.value.map((m) => {
                if (String(m.id) === String(id)) {
                    return {
                        ...m,
                        date: fmtDateOnly(updated.date ?? memoShowData.value.date),
                        body: updated.body ?? editingCommentBody.value,
                        author: updated.author ?? m.author ?? editingCommentAuthor.value,
                    };
                }
                return m;
            });
            // update props.memos if present
            if (Array.isArray(props.memos)) {
                const idx = props.memos.findIndex((mm) => String(mm.id) === String(id));
                if (idx >= 0) {
                    try {
                        props.memos[idx].date = fmtDateOnly(updated.date ?? memoShowData.value.date);
                        props.memos[idx].body = updated.body ?? editingCommentBody.value;
                        props.memos[idx].author = updated.author ?? props.memos[idx].author ?? editingCommentAuthor.value;
                    } catch (e) {}
                }
            }
        }
        // reflect back into memoShowData (but do not re-open the details modal) — keep modals closed after edit
        memoShowData.value = {
            id: id,
            date: fmtDateOnly(updated?.date ?? memoShowData.value.date),
            body: updated?.body ?? editingCommentBody.value,
            author: updated?.author ?? editingCommentAuthor.value,
        };
        // close edit modal and ensure details modal remains closed
        showEditModal.value = false;
        showMemoShowModal.value = false;
        alert('メモを更新しました');
    } catch (e) {
        console.error('submitEditComment error', e);
        alert('メモの更新に失敗しました');
    }
}

// Delete while editing
async function deleteEditingMemo() {
    const id = editingCommentId.value;
    if (!id) return;
    if (!confirm('このメモを削除してよいですか？')) return;
    try {
        await axios.delete(route('coordinator.project_memos.destroy', { memo: id }));
        // remove from localMemos
        localMemos.value = localMemos.value.filter((m) => String(m.id) !== String(id));
        // also remove from props.memos if present
        if (Array.isArray(props.memos)) {
            const idx = props.memos.findIndex((m) => String(m.id) === String(id));
            if (idx >= 0) props.memos.splice(idx, 1);
        }
        showEditModal.value = false;
        // ensure details modal is closed as well
        showMemoShowModal.value = false;
        alert('メモを削除しました');
    } catch (e) {
        console.error('deleteEditingMemo error', e);
        alert('メモの削除に失敗しました');
    }
}
</script>

<style scoped>
.assigned-calendar-container {
    padding: 1rem;
}
</style>
