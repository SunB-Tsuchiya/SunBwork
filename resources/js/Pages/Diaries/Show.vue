<script setup>
const todayJst = (() => {
    const d = new Date();
    d.setHours(d.getHours() + 9);
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd}`;
})();
function formatJstDate(dateStr) {
    const d = new Date(dateStr);
    d.setHours(d.getHours() + 9);
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd}`;
}
// helper: return raw YYYY-MM-DD part from various date representations
function isoDateOnly(dateStr) {
    try {
        if (!dateStr) return '';
        return String(dateStr).split('T')[0];
    } catch (e) {
        return String(dateStr).split('T')[0];
    }
}
import TimelineDiary from '@/Components/TimelineDiary.vue';
import { ensureAttachmentUrl, ensureThumbUrl } from '@/Helpers/attachment';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import DOMPurify from 'dompurify';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps({
    diary: Object,
});

const editHref = computed(() => {
    try {
        // attach a return_to parameter so Edit.vue can navigate back correctly
        const current = window.location.pathname + window.location.search + window.location.hash;
        return route('diaries.edit', props.diary.id) + `?return_to=${encodeURIComponent(current)}`;
    } catch (e) {
        return route('diaries.edit', props.diary.id);
    }
});

// 追加: 既読ユーザー名を安全に取り出すヘルパー
const readerNames = computed(() => {
    const rb = props.diary?.read_by || [];
    if (!Array.isArray(rb) || rb.length === 0) return [];

    return rb
        .map((item) => {
            if (!item && item !== 0) return String(item);
            // 期待される形: { id, name } や { user_id, user_name } など複数パターンに対応

            if (typeof item === 'object') {
                if (item.name) return item.name;
                if (item.user_name) return item.user_name;
                if (item.admin_name) return item.admin_name;
                if (item.full_name) return item.full_name;
                // フォールバック: id として表示
                if (item.id) return `user#${item.id}`;
                return JSON.stringify(item);
            }
            // 単純な文字列/数値の場合
            return String(item);
        })
        .filter(Boolean);
});

// Handlers for TimelineDiary component emits
async function onTimelineUpdate(payload) {
    // payload: { id, date, startHour, startMinute, endHour, endMinute }
    try {
        await axios.put(`/events/${payload.id}/calendar`, {
            date: payload.date,
            startHour: payload.startHour,
            startMinute: payload.startMinute,
            endHour: payload.endHour,
            endMinute: payload.endMinute,
        });
        // refresh events
        const resp = await axios.get('/events', { params: { date: payload.date } });
        events.value = (resp.data || []).map((e) => ({
            id: e.id ?? e.event_id ?? e._id ?? null,
            title: e.title || e.name || '(無題)',
            start: e.start,
            end: e.end || e.start,
            allDay: !!e.allDay || !!e.all_day || false,
            color: e.color || e.backgroundColor || '#2563eb',
            description: e.description || e.extendedProps?.description || '',
        }));
    } catch (err) {
        alert('予定の更新に失敗しました');
    }
}

function onTimelineOpenCreate(payload) {
    // payload may be null or contain minuteOffset
    const current = window.location.pathname + window.location.search + window.location.hash;
    if (payload && payload.minuteOffset !== undefined && payload.minuteOffset !== null) {
        const totalMin = Math.round(payload.minuteOffset / 15) * 15;
        const clampedTotal = Math.max(0, Math.min(totalMin, 24 * 60 - 1));
        const hh = String(Math.floor(clampedTotal / 60)).padStart(2, '0');
        const mm = String(clampedTotal % 60).padStart(2, '0');
        try {
            router.get(
                route('events.create', {
                    date: formatJstDate(props.diary.date),
                    startHour: hh,
                    startMinute: mm,
                    endHour: String(Math.min(23, parseInt(hh) + 1)).padStart(2, '0'),
                    endMinute: mm,
                    return_to: current,
                }),
            );
            return;
        } catch (e) {
            window.location.href = `/events/create?date=${encodeURIComponent(formatJstDate(props.diary.date))}&startHour=${hh}&startMinute=${mm}&endHour=${String(Math.min(23, parseInt(hh) + 1)).padStart(2, '0')}&endMinute=${mm}&return_to=${encodeURIComponent(current)}`;
            return;
        }
    }
    try {
        router.get(route('events.create', { date: formatJstDate(props.diary.date), return_to: current }));
    } catch (e) {
        window.location.href = `/events/create?date=${encodeURIComponent(formatJstDate(props.diary.date))}&return_to=${encodeURIComponent(current)}`;
    }
}

function onTimelineOpenEdit(payload) {
    // payload: { id, readOnly, mouseEvent }
    if (!payload || !payload.id) return;
    const id = payload.id;
    if (payload.readOnly) {
        try {
            router.get(route('events.show', { event: id }));
        } catch (e) {
            window.location.href = `/events/${id}`;
        }
        return;
    }
    if (payload.mouseEvent && payload.mouseEvent.clientX) {
        const minuteOffset = computeSnappedMinuteFromClientX(payload.mouseEvent.clientX);
        if (minuteOffset !== null) {
            const hh = String(Math.floor(minuteOffset / 60)).padStart(2, '0');
            const mm = String(minuteOffset % 60).padStart(2, '0');
            try {
                const current = window.location.pathname + window.location.search + window.location.hash;
                router.get(route('events.edit', { event: id, startHour: hh, startMinute: mm, return_to: current }));
                return;
            } catch (e) {
                window.location.href = `/events/${id}/edit?startHour=${hh}&startMinute=${mm}`;
                return;
            }
        }
    }
    try {
        router.get(route('events.edit', { event: id }));
    } catch (e) {
        window.location.href = `/events/${id}/edit`;
    }
}

const deleteDiary = () => {
    if (confirm('この日報を削除してよろしいですか？')) {
        router.delete(route('diaries.destroy', props.diary.id));
    }
};

async function deleteComment(commentId, idx) {
    if (!confirm('コメントを削除してよいですか？')) return;

    const prefix = props.routePrefix || 'diaries';
    let target;
    try {
        target = route('diary_comments.destroy', commentId);
    } catch (e) {
        console.warn('Ziggy route failed for diary_comments.destroy', e);
        target = `/diary-comments/${commentId}`;
    }

    try {
        const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
        const csrf = match ? decodeURIComponent(match[1]) : null;
        const res = await fetch(target, {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                ...(csrf ? { 'X-XSRF-TOKEN': csrf } : {}),
            },
        });

        if (!res.ok) {
            console.error('deleteComment failed', res.status, await res.text());
            alert('コメントの削除に失敗しました');
            return;
        }

        // remove from local diary comments array (if present)
        try {
            const arr = props.diary.comments || [];
            if (arr[idx] && (arr[idx].id === commentId || arr[idx].id === Number(commentId))) {
                arr.splice(idx, 1);
            } else {
                // fallback: find by id
                const found = arr.findIndex((x) => x.id === commentId || x.id === Number(commentId));
                if (found >= 0) arr.splice(found, 1);
            }
        } catch (e) {
            console.warn('local comment array update failed', e);
        }
    } catch (e) {
        console.error('deleteComment error', e);
        alert('コメントの削除中にエラーが発生しました');
    }
}

import {} from 'vue';

const back = () => {
    try {
        // if browser history has entries, go back using Inertia
        if (window.history && window.history.length > 1) {
            // use native browser back to replicate exact browser behavior
            window.history.back();
            return;
        }
    } catch (e) {
        // ignore
    }
    // fallback to diaries index
    router.get(route('diaries.index'));
};

// --- events timetable state ---
const events = ref([]);
const showEventModal = ref(false);
const selectedEvent = ref(null);
const showSelectModal = ref(false);
// when user clicks timeline empty area, store clicked minute/hours to prefill create
const clickedStartHour = ref(null);
const clickedStartMinute = ref(null);
// drag state
const dragging = ref(false);
const draggingEventId = ref(null);
const dragStartX = ref(0);
const dragOrigLeftPx = ref(0);
const dragOrigDurationMin = ref(0);
const dragMoved = ref(false);
// map of temporary left override for preview during drag
const dragOverrides = ref({});
// map of event ids temporarily suppressing click-to-open after a drag/resize update
const suppressClick = ref({});
// suppress the next timeline background click (used to avoid opening select modal
// immediately after a drag/resize mouseup which can fire a click on the timeline)
const suppressTimelineClick = ref(false);
// resize mode: null | 'move' | 'resize-left' | 'resize-right'
const resizeMode = ref(null);
const resizeEventId = ref(null);
const resizeOrigLeftPx = ref(0);
const resizeOrigWidthPx = ref(0);
const resizeOrigStartMin = ref(0);
const resizeOrigEndMin = ref(0);
// ref for the inner timeline content (the element with minWidth)
const timelineContentRef = ref(null);
const scrollWrapperRef = ref(null);
const timelineRef = ref(null);
const labelsRowRef = ref(null);
// timetable range state (startHour .. endHour)
const startHour = ref(8); // left = 8
const endHour = ref(20); // right initial = 20
const extended = ref(false);
// px per minute scale (reduced by ~25% from 2 -> 1.5 to shrink widths)
const pxPerMinute = ref(1.5);
// window width in minutes and shift amount when navigating
const windowMinutes = computed(() => (endHour.value - startHour.value) * 60);
const shiftHours = 1; // shift by 1 hour per click

function shiftLeft() {
    const windowHours = endHour.value - startHour.value;
    const newStart = Math.max(0, startHour.value - shiftHours);
    startHour.value = newStart;
    endHour.value = newStart + windowHours;
}

function shiftRight() {
    const windowHours = endHour.value - startHour.value;
    const newEnd = Math.min(24, endHour.value + shiftHours);
    endHour.value = newEnd;
    startHour.value = newEnd - windowHours;
}

const hourLabels = computed(() => {
    const labels = [];
    for (let h = startHour.value; h <= endHour.value; h++) labels.push(h);
    return labels;
});

// used px per minute for label sizing (prefer measured width from rendered content)
const usedPxPerMin = computed(() => {
    try {
        const measured =
            timelineContentRef.value && timelineContentRef.value && windowMinutes.value > 0
                ? timelineContentRef.value.clientWidth / windowMinutes.value
                : null;
        return measured || pxPerMinute.value;
    } catch (e) {
        return pxPerMinute.value;
    }
});

function extendTo24() {
    endHour.value = 24;
    extended.value = true;
}

// fetch events for the diary's date
const cloneDeep = (obj) => {
    try {
        return structuredClone(obj);
    } catch (e) {
        try {
            return JSON.parse(JSON.stringify(obj));
        } catch (e2) {
            return obj;
        }
    }
};

// local reactive copy of diary so we can replace placeholders and poll attachments
const localDiary = ref(props.diary ? cloneDeep(props.diary) : null);

function startAttachmentPolling() {
    try {
        if (!localDiary.value) return;
        // If server provided attachments array, poll those
        if (Array.isArray(localDiary.value.attachments) && localDiary.value.attachments.length) {
            localDiary.value.attachments.forEach((f) => {
                if (!f) return;
                if ((f.status || '') !== 'ready' && f.id) pollAttachmentStatus(f.id, 0);
            });
        }

        // Also scan content for any [[attachment:id:...]] placeholders and start polling for them
        const body = localDiary.value.content || '';
        const ids = extractPlaceholderIds(body);
        ids.forEach((id) => {
            // ensure attachments array exists and contains an entry for this id so UI can show it when ready
            if (!Array.isArray(localDiary.value.attachments)) localDiary.value.attachments = [];
            const existing = localDiary.value.attachments.find((a) => String(a.id) === String(id));
            if (!existing) {
                localDiary.value.attachments.push({ id: Number(id), status: 'pending' });
            }
            pollAttachmentStatus(id, 0);
        });
    } catch (e) {}
}

async function pollAttachmentStatus(id, attempt) {
    const maxAttempts = 30;
    const interval = 2000;
    try {
        const r = await axios.get(`/api/uploads/status/${id}`);
        if (r && r.data) {
            const st = r.data.status;
            // upsert into attachments array so we have url/mime available
            if (!Array.isArray(localDiary.value.attachments)) localDiary.value.attachments = [];
            const idx = (localDiary.value.attachments || []).findIndex((f) => String(f.id) === String(id));
            const updatedMeta = {
                id: Number(id),
                status: st || 'pending',
                url: r.data.url || null,
                mime_type: r.data.mime || r.data.mime_type || null,
                original_name: r.data.original_name || null,
                size: r.data.size || null,
            };
            if (idx >= 0) {
                localDiary.value.attachments.splice(idx, 1, { ...localDiary.value.attachments[idx], ...updatedMeta });
            } else {
                localDiary.value.attachments.push(updatedMeta);
            }

            if (st === 'ready') {
                const placeholder = `[[attachment:${id}:`;
                if (localDiary.value.content && localDiary.value.content.indexOf(placeholder) >= 0) {
                    const url = r.data.url;
                    const original = r.data.original_name || '';
                    if (r.data.mime && r.data.mime.startsWith('image/')) {
                        localDiary.value.content = localDiary.value.content.replace(
                            new RegExp(`\\[\\[attachment:${id}:[^\\]]*\\]\\]`, 'g'),
                            `![](${url})`,
                        );
                    } else {
                        // remove placeholder entirely for non-image attachments
                        localDiary.value.content = localDiary.value.content.replace(new RegExp(`\\[\\[attachment:${id}:[^\\]]*\\]\\]`, 'g'), ``);
                    }
                }
                return;
            }
        }
    } catch (e) {}
    if (attempt < maxAttempts) setTimeout(() => pollAttachmentStatus(id, attempt + 1), interval);
}

function extractPlaceholderIds(body) {
    if (!body || typeof body !== 'string') return [];
    const ids = [];
    const re = /\[\[attachment:(\d+):[^\]]*\]\]/g;
    let m;
    while ((m = re.exec(body))) {
        if (m && m[1]) ids.push(m[1]);
    }
    // dedupe
    return Array.from(new Set(ids));
}

function isAttachmentUrl(url) {
    try {
        if (!localDiary.value || !Array.isArray(localDiary.value.attachments)) return false;
        return localDiary.value.attachments.some((a) => {
            if (!a || !a.url || !url) return false;
            try {
                const au = String(a.url);
                return url === au || url.startsWith(au) || au.startsWith(url);
            } catch (e) {
                return false;
            }
        });
    } catch (e) {
        return false;
    }
}

function extractAttachmentsFromBody(body) {
    if (!body || typeof body !== 'string') return [];
    const results = [];
    try {
        // If body contains HTML anchors, parse them first
        if (/<a\s+/i.test(body)) {
            const container = document.createElement('div');
            container.innerHTML = body;
            const links = container.querySelectorAll('a[href]');
            links.forEach((a) => {
                try {
                    const href = a.getAttribute('href');
                    if (href) results.push({ original_name: (a.textContent && a.textContent.trim()) || href.split('/').pop() || href, url: href });
                } catch (e) {}
            });
        }
    } catch (e) {}
    const mdRe = /!\[([^\]]*)\]\((https?:[^)]+)\)/g; // images
    let m;
    while ((m = mdRe.exec(body))) {
        results.push({ original_name: m[1] || m[2].split('/').pop(), url: m[2] });
    }
    // normal links and markdown links
    const linkRe = /\[([^\]]+)\]\((https?:[^)]+)\)/g;
    while ((m = linkRe.exec(body))) {
        results.push({ original_name: m[1], url: m[2] });
    }
    const urlRe = /(^|\s)(https?:\/\/[^\s<>"]+)/g;
    while ((m = urlRe.exec(body))) {
        const url = m[2] || m[1];
        if (!results.find((r) => r.url === url)) results.push({ original_name: url.split('/').pop() || url, url });
    }
    return results;
}

const attachmentsList = computed(() => {
    const explicit = localDiary.value && Array.isArray(localDiary.value.attachments) ? localDiary.value.attachments || [] : [];
    const extracted = extractAttachmentsFromBody(localDiary.value?.content || '');
    // merge explicit and extracted, preferring explicit entries when URLs match
    const out = [];
    const seen = new Set();
    explicit.forEach((e) => {
        const key = e.url || (e.original_name ? `name:${e.original_name}` : `id:${e.id}`) || JSON.stringify(e);
        seen.add(key);
        out.push(e);
    });
    extracted.forEach((ex) => {
        const key = ex.url || (ex.original_name ? `name:${ex.original_name}` : null);
        if (key && seen.has(key)) return;
        // normalize relative URLs to absolute when possible (leave as-is otherwise)
        out.push({ original_name: ex.original_name, url: ex.url });
        if (key) seen.add(key);
    });
    return out;
});

async function deleteAttachment(file) {
    if (!file) return;
    if (!confirm('添付ファイルを削除してよいですか？')) return;

    // If the file has an attachment id on the server, try to delete via API
    try {
        if (file.id) {
            // try server DELETE endpoint - adjust path if your backend mounts attachments elsewhere
            // Use API endpoint for attachment deletion (SPA session-auth)
            const res = await axios.delete(`/api/attachments/${file.id}`);
            if (res && (res.status === 200 || res.status === 204)) {
                // remove from local attachments
                localDiary.value.attachments = (localDiary.value.attachments || []).filter((a) => String(a.id) !== String(file.id));
                return;
            }
        }
    } catch (e) {
        // ignore and fallback to optimistic removal
        console.warn('attachment delete API failed', e);
    }

    // Fallback: optimistic remove from local state (client-only)
    try {
        if (Array.isArray(localDiary.value.attachments)) {
            localDiary.value.attachments = (localDiary.value.attachments || []).filter((a) => {
                // try matching by url or original_name if id not present
                if (file.id && a.id) return String(a.id) !== String(file.id);
                if (file.url && a.url) return file.url !== a.url;
                if (file.original_name && a.original_name) return file.original_name !== a.original_name;
                return true;
            });
        }
    } catch (e) {
        console.warn('optimistic remove failed', e);
    }
}

// sanitize and render body: convert ![]() to <img>, markdown links to anchors, but suppress attachment links already represented in attachmentsList
function sanitize(html) {
    const src = html || '';
    try {
        const container = document.createElement('div');
        container.innerHTML = src;
        const walker = document.createTreeWalker(container, NodeFilter.SHOW_TEXT, null, false);
        const textNodes = [];
        while (walker.nextNode()) textNodes.push(walker.currentNode);
        textNodes.forEach((tn) => {
            const text = tn.nodeValue || '';
            let replaced = text;
            replaced = replaced.replace(
                /!\[([^\]]*)\]\(([^)]+)\)/g,
                (m, alt, url) => `<img src="${String(url).trim()}" alt="${alt || ''}" class="max-w-full h-auto rounded" />`,
            );
            replaced = replaced.replace(/\[([^\]]+)\]\(([^)]+)\)/g, (m, txt, url) => {
                const u = String(url).trim();
                if (isAttachmentUrl(u)) return ``;
                return `<a href="${u}" target="_blank" rel="noopener noreferrer" class="text-blue-600 underline" download>${txt}</a>`;
            });
            replaced = replaced.replace(/(^|\s)(https?:\/\/[^\s<>]+)/g, (m, pre, url) => {
                const u = String(url).trim();
                if (isAttachmentUrl(u)) return `${pre}`;
                return `${pre}<a href="${u}" target="_blank" rel="noopener noreferrer" class="text-blue-600 underline" download>${u}</a>`;
            });
            if (replaced !== text) {
                const frag = document.createRange().createContextualFragment(replaced);
                tn.parentNode.replaceChild(frag, tn);
            }
        });
        return DOMPurify.sanitize(container.innerHTML);
    } catch (e) {
        return DOMPurify.sanitize(src);
    }
}

const sanitizedContent = computed(() => sanitize(localDiary.value?.content || ''));

// Preview modal state and helpers
const previewModal = ref({ open: false, url: null, mime: null, filename: null, isBlob: false });
let currentObjectUrl = null;
function revokeCurrentObjectUrl() {
    try {
        if (currentObjectUrl) {
            URL.revokeObjectURL(currentObjectUrl);
            currentObjectUrl = null;
        }
    } catch (e) {}
}
async function fetchBlobAndShow(url, filename) {
    try {
        const res = await axios.get(url, { responseType: 'blob', withCredentials: true });
        const blob = res.data;
        const mime = blob.type || res.headers['content-type'] || 'application/octet-stream';
        revokeCurrentObjectUrl();
        currentObjectUrl = URL.createObjectURL(blob);
        previewModal.value = { open: true, url: currentObjectUrl, mime, filename: filename || url.split('/').pop() || 'file', isBlob: true };
    } catch (e) {
        try {
            window.open(url, '_blank', 'noopener');
        } catch (e2) {}
    }
}

function openAttachmentInModal(file) {
    if (!file || !file.url) return;
    if (file.url.startsWith('blob:') || file.url.startsWith('data:')) {
        previewModal.value = {
            open: true,
            url: file.url,
            mime: file.mime_type || '',
            filename: file.original_name || '',
            isBlob: file.url.startsWith('blob:'),
        };
        return;
    }
    fetchBlobAndShow(file.url, file.original_name || 'file');
}

function onBodyClick(e) {
    try {
        const a = e.target.closest && e.target.closest('a');
        if (a && a.href) {
            e.preventDefault();
            const url = a.href;
            const filename = (a.textContent && a.textContent.trim()) || url.split('/').pop();
            fetchBlobAndShow(url, filename);
        }
    } catch (e) {}
}

function closePreviewModal() {
    previewModal.value.open = false;
    revokeCurrentObjectUrl();
}

onMounted(async () => {
    try {
        // pass only YYYY-MM-DD to backend using the app/JST-aware formatter so the diary's displayed date
        // (which may differ from the raw ISO UTC date) is used for server queries.
        const date = formatJstDate(props.diary.date);
        const resp = await axios.get('/events', { params: { date } });
        // normalize: ensure start/end are present and color fallback
        events.value = (resp.data || []).map((e) => ({
            id: e.id ?? e.event_id ?? e._id ?? null,
            title: e.title || e.name || '(無題)',
            start: e.start,
            end: e.end || e.start,
            allDay: !!e.allDay || !!e.all_day || false,
            color: e.color || e.backgroundColor || '#2563eb',
            description: e.description || e.extendedProps?.description || '',
        }));
    } catch (err) {
        // ignore fetch errors silently for now
        console.warn('Failed to load events for diary show', err);
    }
    // start attachment polling for any in-progress uploads
    startAttachmentPolling();
});

function computeSnappedMinuteFromClientX(clientX) {
    const contentEl = timelineContentRef.value && timelineContentRef.value ? timelineContentRef.value : null;
    const scrollWrap = scrollWrapperRef.value && scrollWrapperRef.value ? scrollWrapperRef.value : null;
    const container = contentEl;
    if (!container || !container.getBoundingClientRect) return null;
    const rect = container.getBoundingClientRect();
    const contentLeft = timelineContentRef.value && timelineContentRef.value ? timelineContentRef.value.getBoundingClientRect().left : rect.left;
    const scrollLeft = scrollWrap ? scrollWrap.scrollLeft || 0 : container.scrollLeft || 0;
    const clickX = clientX - contentLeft + scrollLeft;
    const measuredPxPerMin =
        timelineContentRef.value && timelineContentRef.value && windowMinutes.value > 0
            ? timelineContentRef.value.clientWidth / windowMinutes.value
            : null;
    const pxPerMin = measuredPxPerMin || pxPerMinute.value;
    const rawMin = startHour.value * 60 + clickX / pxPerMin;
    const hourPart = Math.floor(rawMin / 60);
    const minsPastHour = Math.floor(rawMin % 60);
    const snappedMins = minsPastHour < 30 ? 0 : 30;
    return hourPart * 60 + snappedMins;
}

function openEventModal(ev, mouseEvent) {
    // if this event was just updated via drag/resize, ignore the click-to-open (user likely released mouse)
    try {
        if (ev && ev.id && suppressClick.value[ev.id]) {
            // remove suppression after ignoring once
            delete suppressClick.value[ev.id];
            return;
        }
    } catch (e) {
        // ignore
    }
    // navigate to edit for existing events
    const id = ev && (ev.id ?? ev.event_id ?? ev.eventId);
    if (id) {
        // if click position provided, compute snapped minute and include as query params
        if (mouseEvent && mouseEvent.clientX) {
            const minuteOffset = computeSnappedMinuteFromClientX(mouseEvent.clientX);
            if (minuteOffset !== null) {
                const hh = String(Math.floor(minuteOffset / 60)).padStart(2, '0');
                const mm = String(minuteOffset % 60).padStart(2, '0');
                try {
                    const current = window.location.pathname + window.location.search + window.location.hash;
                    router.get(route('events.edit', { event: id, startHour: hh, startMinute: mm, return_to: current }));
                    return;
                } catch (e) {
                    window.location.href = `/events/${id}/edit?startHour=${hh}&startMinute=${mm}&return_to=${encodeURIComponent(current)}`;
                    return;
                }
            }
        }
        try {
            router.get(route('events.edit', { event: id }));
            return;
        } catch (e) {
            window.location.href = `/events/${id}/edit`;
            return;
        }
    }
    // fallback: show simple modal
    selectedEvent.value = ev;
    showEventModal.value = true;
}

function closeEventModal() {
    selectedEvent.value = null;
    showEventModal.value = false;
}

function openSelectModalAt(minuteOffset) {
    // minuteOffset is minutes from 00:00 of the day
    // Round to nearest 15 minutes to pick an approximate start time
    const totalMin = Math.round(minuteOffset / 15) * 15;
    // handle overflow (e.g., rounding up to next day) by clamping to 23:59
    const clampedTotal = Math.max(0, Math.min(totalMin, 24 * 60 - 1));
    const hh = Math.floor(clampedTotal / 60);
    const mm = clampedTotal % 60;
    clickedStartHour.value = hh;
    clickedStartMinute.value = mm;
    showSelectModal.value = true;
}

function createFromSelect() {
    const hh = String(clickedStartHour.value).padStart(2, '0');
    const mm = String(clickedStartMinute.value).padStart(2, '0');
    const endH = String(Math.min(23, clickedStartHour.value + 1)).padStart(2, '0');
    const endM = mm;
    showSelectModal.value = false;
    // navigate to events.create with date and time params
    // prepare current return_to url (always include it)
    const current = window.location.pathname + window.location.search + window.location.hash;
    try {
        // pass only the YYYY-MM-DD date (remove T...Z) so queries like ?date=2025-09-19 are used
        router.get(
            route('events.create', {
                date: formatJstDate(props.diary.date),
                startHour: hh,
                startMinute: mm,
                endHour: endH,
                endMinute: endM,
                return_to: current,
            }),
        );
    } catch (e) {
        // fallback to setting window.location (ensure current is encoded)
        window.location.href = `/events/create?date=${encodeURIComponent(formatJstDate(props.diary.date))}&startHour=${hh}&startMinute=${mm}&endHour=${endH}&endMinute=${endM}&return_to=${encodeURIComponent(current)}`;
    }
}

function closeSelectModal() {
    showSelectModal.value = false;
}

function pad2(n) {
    return String(n).padStart(2, '0');
}

function computeEventStyle(ev) {
    try {
        // baseline startHour. We'll compute minutes offset from startHour and width in pixels.
        const baselineHour = startHour.value;
        // Prefer measured px/min from the rendered timeline content so visual layout matches calculations
        const measuredPxPerMin =
            timelineContentRef.value && timelineContentRef.value && windowMinutes.value > 0
                ? timelineContentRef.value.clientWidth / windowMinutes.value
                : null;
        const pxPerMinuteLocal = measuredPxPerMin || pxPerMinute.value;
        const start = new Date(ev.start);
        const end = new Date(ev.end || ev.start);
        const startMinutes = start.getHours() * 60 + start.getMinutes();
        const endMinutes = end.getHours() * 60 + end.getMinutes();
        const windowStartMin = baselineHour * 60;
        const windowEndMin = endHour.value * 60;

        // original minutes
        const origLeft = startMinutes;
        const origRight = endMinutes;

        // clamp to visible window
        const clampedLeft = Math.max(origLeft, windowStartMin);
        const clampedRight = Math.min(origRight, windowEndMin);

        // if completely outside, return hidden
        if (clampedRight <= windowStartMin || clampedLeft >= windowEndMin) {
            return { display: 'none' };
        }

        const leftMinutes = Math.max(0, clampedLeft - windowStartMin);
        const duration = Math.max(15, clampedRight - clampedLeft);
        const leftPx = leftMinutes * pxPerMinuteLocal;
        const widthPx = duration * pxPerMinuteLocal;
        const clippedLeft = origLeft < windowStartMin;
        const clippedRight = origRight > windowEndMin;
        // attach data attributes via style object (Vue will apply them as attributes)
        return {
            left: `${leftPx}px`,
            width: `${widthPx}px`,
            background: ev.color || '#2563eb',
            position: 'absolute',
            height: '2.25rem',
            lineHeight: '2.25rem',
            borderRadius: '6px',
            overflow: 'hidden',
            whiteSpace: 'nowrap',
            textOverflow: 'ellipsis',
            // custom flags for template to read via dataset (we'll also add attributes in template)
            '--clipped-left': clippedLeft ? '1' : '0',
            '--clipped-right': clippedRight ? '1' : '0',
        };
    } catch (e) {
        return { display: 'none' };
    }
}

// timeline click handler to open select modal
function handleTimelineClick(e) {
    // If a drag/resize just finished, ignore the next timeline click which often
    // fires due to mouseup landing on the timeline background. This prevents the
    // select modal opening immediately after a drag and subsequent navigation to
    // the create page.
    try {
        if (suppressTimelineClick.value) {
            // clear once and ignore this click
            suppressTimelineClick.value = false;
            return;
        }
    } catch (err) {}
    // only respond when clicking on timeline background (not on event elements)
    // Use the inner timeline content element's bounding rect to map clickX to minutes
    // prefer the timeline content rect but account for the scroll wrapper's scrollLeft
    const contentEl = timelineContentRef.value && timelineContentRef.value ? timelineContentRef.value : null;
    const scrollWrap = scrollWrapperRef.value && scrollWrapperRef.value ? scrollWrapperRef.value : null;
    const container = contentEl || e.currentTarget || e.target;
    if (!container || !container.getBoundingClientRect) return;
    const rect = container.getBoundingClientRect();
    // compute clickX relative to the inner timeline content left edge (contentLeft) which matches measured widths
    const contentLeft = timelineContentRef.value && timelineContentRef.value ? timelineContentRef.value.getBoundingClientRect().left : rect.left;
    const scrollLeft = scrollWrap ? scrollWrap.scrollLeft || 0 : container.scrollLeft || 0;
    const clickX = e.clientX - contentLeft + scrollLeft;
    // derive pxPerMin from rendered content width if available to avoid mismatch
    const measuredPxPerMin =
        timelineContentRef.value && timelineContentRef.value && windowMinutes.value > 0
            ? timelineContentRef.value.clientWidth / windowMinutes.value
            : null;
    const pxPerMin = measuredPxPerMin || pxPerMinute.value;
    // compute raw minute and then snap to either :00 or :30
    const rawMin = startHour.value * 60 + clickX / pxPerMin;
    const hourPart = Math.floor(rawMin / 60);
    const minsPastHour = Math.floor(rawMin % 60);
    const snappedMins = minsPastHour < 30 ? 0 : 30; // 00 or 30
    const minuteOffset = hourPart * 60 + snappedMins;
    // debug logging removed
    openSelectModalAt(minuteOffset);
}

// Drag handlers
function startDrag(ev, mouseEvent) {
    // ev: event object
    mouseEvent.preventDefault();
    dragging.value = true;
    resizeMode.value = 'move';
    draggingEventId.value = ev.id;
    dragMoved.value = false;
    dragStartX.value = mouseEvent.clientX;
    // compute original left px
    const style = computeEventStyle(ev);
    const leftPx = parseFloat(style.left) || 0;
    dragOrigLeftPx.value = leftPx;
    // duration in minutes
    const start = new Date(ev.start);
    const end = new Date(ev.end || ev.start);
    dragOrigDurationMin.value = Math.max(15, end.getHours() * 60 + end.getMinutes() - (start.getHours() * 60 + start.getMinutes()));
}

function startResizeLeft(ev, mouseEvent) {
    mouseEvent.preventDefault();
    resizeMode.value = 'resize-left';
    resizeEventId.value = ev.id;
    dragMoved.value = false;
    dragStartX.value = mouseEvent.clientX;
    // compute original left px and width
    const style = computeEventStyle(ev);
    const leftPx = parseFloat(style.left) || 0;
    const widthPx = parseFloat(style.width) || 0;
    resizeOrigLeftPx.value = leftPx;
    resizeOrigWidthPx.value = widthPx;
    // original minutes
    const start = new Date(ev.start);
    const end = new Date(ev.end || ev.start);
    resizeOrigStartMin.value = start.getHours() * 60 + start.getMinutes();
    resizeOrigEndMin.value = end.getHours() * 60 + end.getMinutes();
}

function startResizeRight(ev, mouseEvent) {
    mouseEvent.preventDefault();
    resizeMode.value = 'resize-right';
    resizeEventId.value = ev.id;
    dragMoved.value = false;
    dragStartX.value = mouseEvent.clientX;
    const style = computeEventStyle(ev);
    const leftPx = parseFloat(style.left) || 0;
    const widthPx = parseFloat(style.width) || 0;
    resizeOrigLeftPx.value = leftPx;
    resizeOrigWidthPx.value = widthPx;
    const start = new Date(ev.start);
    const end = new Date(ev.end || ev.start);
    resizeOrigStartMin.value = start.getHours() * 60 + start.getMinutes();
    resizeOrigEndMin.value = end.getHours() * 60 + end.getMinutes();
}

function onDocumentMouseMove(e) {
    if (!dragging.value && !resizeMode.value) return;
    const dx = e.clientX - dragStartX.value;
    if (Math.abs(dx) > 2) dragMoved.value = true;
    // move mode: shift the whole event
    if (resizeMode.value === 'move' && draggingEventId.value) {
        const newLeftPx = Math.max(0, dragOrigLeftPx.value + dx);
        dragOverrides.value = { ...dragOverrides.value, [draggingEventId.value]: newLeftPx };
        return;
    }
    // resize-left: change left and width (increase/decrease) keeping right edge fixed
    if (resizeMode.value === 'resize-left' && resizeEventId.value) {
        const newLeftPx = Math.max(0, resizeOrigLeftPx.value + dx);
        const newWidthPx = Math.max(10, resizeOrigWidthPx.value - (newLeftPx - resizeOrigLeftPx.value));
        dragOverrides.value = { ...dragOverrides.value, [resizeEventId.value]: { left: newLeftPx, width: newWidthPx } };
        return;
    }
    // resize-right: keep left fixed, change width
    if (resizeMode.value === 'resize-right' && resizeEventId.value) {
        const newWidthPx = Math.max(10, resizeOrigWidthPx.value + dx);
        dragOverrides.value = { ...dragOverrides.value, [resizeEventId.value]: { left: resizeOrigLeftPx.value, width: newWidthPx } };
        return;
    }
}

async function endDrag(e) {
    // handle cases where no drag/resize is active
    if (!dragging.value && !resizeMode.value) {
        dragging.value = false;
        draggingEventId.value = null;
        resizeMode.value = null;
        resizeEventId.value = null;
        dragOverrides.value = {};
        return;
    }

    // determine id and event depending on mode
    const id = resizeMode.value && resizeMode.value !== 'move' ? resizeEventId.value : draggingEventId.value;
    const ev = events.value.find((x) => x.id === id);
    if (!ev) {
        dragging.value = false;
        draggingEventId.value = null;
        resizeMode.value = null;
        resizeEventId.value = null;
        dragOverrides.value = {};
        return;
    }
    // if didn't move/resize, treat as click: navigate to edit
    if (!dragMoved.value) {
        try {
            router.get(route('events.edit', { event: id }));
        } catch (err) {
            window.location.href = `/events/${id}/edit`;
        }
        dragging.value = false;
        draggingEventId.value = null;
        resizeMode.value = null;
        resizeEventId.value = null;
        dragOverrides.value = {};
        return;
    }

    // Immediately suppress clicks on this event and the timeline background to avoid
    // accidental navigation caused by click events firing during/after the confirm
    // and the async PUT. This is set before confirm so any click that fires while
    // the confirm dialog is active will be ignored.
    try {
        suppressClick.value[id] = true;
    } catch (e) {}
    try {
        suppressTimelineClick.value = true;
        setTimeout(() => {
            try {
                suppressTimelineClick.value = false;
            } catch (e) {}
        }, 500);
    } catch (e) {}

    const pxPerMin = pxPerMinute.value;
    const windowStartMin = startHour.value * 60;

    let newStartMin = resizeOrigStartMin.value;
    let newEndMin = resizeOrigEndMin.value;

    // compute based on mode and overrides
    const override = dragOverrides.value[id];
    if (resizeMode.value === 'move') {
        const newLeftPx = typeof override === 'number' ? override : dragOrigLeftPx.value;
        newStartMin = Math.round((newLeftPx / pxPerMin + windowStartMin) / 15) * 15;
        newEndMin = newStartMin + dragOrigDurationMin.value;
    } else if (resizeMode.value === 'resize-left') {
        const obj = override || { left: resizeOrigLeftPx.value, width: resizeOrigWidthPx.value };
        const leftPx = obj.left;
        const widthPx = obj.width;
        const leftMin = Math.round((leftPx / pxPerMin + windowStartMin) / 15) * 15;
        const rightMin = Math.round(((leftPx + widthPx) / pxPerMin + windowStartMin) / 15) * 15;
        newStartMin = Math.max(0, Math.min(leftMin, rightMin - 15));
        newEndMin = rightMin;
    } else if (resizeMode.value === 'resize-right') {
        const obj = override || { left: resizeOrigLeftPx.value, width: resizeOrigWidthPx.value };
        const leftPx = obj.left;
        const widthPx = obj.width;
        const leftMin = Math.round((leftPx / pxPerMin + windowStartMin) / 15) * 15;
        const rightMin = Math.round(((leftPx + widthPx) / pxPerMin + windowStartMin) / 15) * 15;
        newStartMin = leftMin;
        newEndMin = Math.max(newStartMin + 15, rightMin);
    }

    // build ISO strings
    const date = formatJstDate(props.diary.date);
    const startHourStr = String(Math.floor(newStartMin / 60)).padStart(2, '0');
    const startMinStr = String(newStartMin % 60).padStart(2, '0');
    const endHourStr = String(Math.floor(newEndMin / 60)).padStart(2, '0');
    const endMinStr = String(newEndMin % 60).padStart(2, '0');

    // confirm with user
    const confirmMessage = `予定の時間を変更しますか？\n開始: ${date} ${startHourStr}:${startMinStr}\n終了: ${date} ${endHourStr}:${endMinStr}`;
    if (!confirm(confirmMessage)) {
        // revert
        dragging.value = false;
        draggingEventId.value = null;
        resizeMode.value = null;
        resizeEventId.value = null;
        dragOverrides.value = {};
        return;
    }

    // call API similar to calendar's eventResize handler
    try {
        await axios.put(`/events/${id}/calendar`, {
            date: date,
            startHour: startHourStr,
            startMinute: startMinStr,
            endHour: endHourStr,
            endMinute: endMinStr,
        });
        alert('予定を更新しました');
        // suppress click-open for this event once to avoid immediate navigation triggered by mouseup
        try {
            suppressClick.value[id] = true;
        } catch (e) {}
        // suppress the timeline background click that may fire immediately after mouseup
        try {
            suppressTimelineClick.value = true;
            // clear after a short delay as a safety (in case click doesn't arrive)
            setTimeout(() => {
                try {
                    suppressTimelineClick.value = false;
                } catch (e) {}
            }, 500);
        } catch (e) {}
        // refresh events
        const resp = await axios.get('/events', { params: { date } });
        events.value = (resp.data || []).map((e) => ({
            id: e.id ?? e.event_id ?? e._id ?? null,
            title: e.title || e.name || '(無題)',
            start: e.start,
            end: e.end || e.start,
            allDay: !!e.allDay || !!e.all_day || false,
            color: e.color || e.backgroundColor || '#2563eb',
            description: e.description || e.extendedProps?.description || '',
        }));
    } catch (err) {
        alert('予定の更新に失敗しました');
    }

    dragging.value = false;
    draggingEventId.value = null;
    resizeMode.value = null;
    resizeEventId.value = null;
    dragOverrides.value = {};
}

onMounted(() => {
    document.addEventListener('mousemove', onDocumentMouseMove);
    document.addEventListener('mouseup', endDrag);
});

onUnmounted(() => {
    document.removeEventListener('mousemove', onDocumentMouseMove);
    document.removeEventListener('mouseup', endDrag);
});
</script>

<template>
    <AppLayout title="日報表示">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">日報一覧</h2>
        </template>

        <div class="rounded bg-white p-6 shadow">
                    <h1 class="mb-4 text-2xl font-bold">日報 {{ formatJstDate(props.diary.date) }}</h1>
                    <div class="prose mb-6">
                        <div v-html="sanitizedContent" @click="onBodyClick"></div>
                    </div>
                    <!-- 追加: 既読ユーザー名と保存されたコメントをユーザー日記にも表示 -->
                    <!-- 既読者表示 -->
                    <div v-if="readerNames.length" class="mb-4 text-sm text-gray-600">
                        <strong class="mr-2">既読:</strong>
                        <span>{{ readerNames.join(', ') }}</span>
                    </div>
                    <!-- 保存されたコメント表示 -->
                    <div class="mb-4">
                        <h3 class="mb-2 font-semibold">保存されたコメント</h3>
                        <div v-if="!(props.diary.comments || []).length" class="mb-2 text-sm text-gray-600">コメントはありません</div>
                        <div
                            v-for="(c, idx) in props.diary.comments || []"
                            :key="c.id || idx"
                            class="mb-2 flex items-start justify-between rounded border p-3"
                        >
                            <div class="text-sm text-gray-700">
                                <strong>{{ c.user_name || c.user_name }}</strong
                                >： <span class="whitespace-pre-wrap">{{ c.comment }}</span>
                            </div>
                            <div v-if="c.user_id === $page.props.auth?.user?.id" class="ml-4">
                                <button @click.prevent="deleteComment(c.id, idx)" class="text-sm text-red-600 hover:underline">削除</button>
                            </div>
                        </div>
                    </div>
                    <div class="mb-4 flex space-x-4">
                        <!-- 今日の日報表示時は新規作成ボタンを非表示 -->
                        <!-- <Link v-if="formatJstDate(props.diary.date) !== todayJst" :href="route('diaries.create')" class="px-4 py-2 bg-green-600 text-white rounded">新しく日報を書く</Link> -->
                        <Link :href="editHref" class="rounded bg-blue-600 px-4 py-2 text-white">編集</Link>
                        <button @click="deleteDiary" class="rounded bg-red-600 px-4 py-2 text-white">削除</button>
                        <button @click="back" class="rounded bg-gray-200 px-4 py-2 text-gray-700">戻る</button>
                    </div>
                    <!-- タイムライン（コンポーネント化） -->
                    <div class="mb-6">
                        <label class="mb-2 block text-sm font-medium text-gray-700">当日の予定</label>
                        <TimelineDiary
                            :date="formatJstDate(props.diary.date)"
                            :events="events"
                            :startHour="startHour"
                            :endHour="endHour"
                            :editable="true"
                            @update:events="onTimelineUpdate"
                            @open-create="onTimelineOpenCreate"
                            @open-edit="onTimelineOpenEdit"
                        />
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">添付ファイル</label>
                        <div v-if="attachmentsList && attachmentsList.length">
                            <ul class="mt-2 space-y-2">
                                <li
                                    v-for="file in attachmentsList"
                                    :key="file.id || file.url || file.original_name"
                                    class="flex items-center justify-between rounded bg-gray-50 p-2"
                                >
                                    <div class="flex items-center gap-3">
                                        <div
                                            v-if="ensureThumbUrl(file) || (file.url && (file.mime_type || file.mime || '').startsWith('image/'))"
                                            class="h-12 w-16 flex-shrink-0"
                                        >
                                            <img
                                                :src="ensureThumbUrl(file) || ensureAttachmentUrl(file) || file.url"
                                                class="h-12 w-16 rounded object-cover"
                                                alt="thumbnail"
                                            />
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">{{ file.original_name || file.name }}</div>
                                            <div class="text-xs text-gray-500">
                                                {{ file.size ? (file.size / 1024).toFixed(1) + ' KB' : '-' }} •
                                                {{ file.mime_type || file.mime || '-' }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <button
                                            v-if="ensureAttachmentUrl(file) || file.url"
                                            type="button"
                                            @click.prevent="openAttachmentInModal(file)"
                                            class="text-blue-600 underline"
                                        >
                                            開く
                                        </button>
                                        <a
                                            v-if="ensureAttachmentUrl(file) || file.url"
                                            :href="ensureAttachmentUrl(file) || file.url"
                                            :download="file.original_name"
                                            class="text-gray-600"
                                            >ダウンロード</a
                                        >
                                        <span v-else class="text-sm text-gray-500">(利用不可)</span>
                                        <button type="button" @click.prevent="deleteAttachment(file)" class="ml-3 text-sm text-red-600">削除</button>
                                    </div>
                                </li>
                            </ul>
                        </div>
                        <div v-else class="mt-2 text-sm text-gray-500">添付ファイルなし</div>
                    </div>
                </div>
                <!-- Event modal -->
                <div v-if="showEventModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
                    <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-lg">
                        <h2 class="mb-4 text-lg font-bold">{{ selectedEvent.title }}</h2>
                        <div class="mb-2 text-sm text-gray-700">
                            <div>開始: {{ selectedEvent.start }}</div>
                            <div>終了: {{ selectedEvent.end }}</div>
                        </div>
                        <div class="mb-4 whitespace-pre-wrap text-sm text-gray-900">{{ selectedEvent.description }}</div>
                        <div class="flex justify-end gap-2">
                            <button @click="closeEventModal" class="rounded bg-gray-200 px-4 py-2">閉じる</button>
                            <Link
                                v-if="selectedEvent.id"
                                :href="route('events.show', selectedEvent.id)"
                                class="rounded bg-blue-600 px-4 py-2 text-white"
                                >詳細</Link
                            >
                        </div>
                    </div>
                </div>
                <!-- Timeline select modal (opened when clicking empty timeline area) -->
                <div v-if="showSelectModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
                    <div class="w-full max-w-xs rounded-lg bg-white p-6 text-center shadow-lg">
                        <h2 class="mb-4 text-lg font-bold">
                            {{ formatJstDate(props.diary.date) }} {{ pad2(clickedStartHour) }}:{{ pad2(clickedStartMinute) }} の操作
                        </h2>
                        <div class="flex flex-col gap-4">
                            <button @click="createFromSelect" class="rounded bg-blue-600 px-4 py-2 text-white">予定作成</button>
                            <button @click="closeSelectModal" class="rounded bg-gray-300 px-4 py-2">キャンセル</button>
                        </div>
                    </div>
                </div>
                <!-- Preview Modal -->
                <div v-if="previewModal.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                    <div class="max-h-[90vh] w-full max-w-4xl overflow-auto rounded bg-white p-4">
                        <div class="mb-2 flex items-center justify-between">
                            <div class="text-sm font-medium">プレビュー: {{ previewModal.filename }}</div>
                            <button type="button" @click="closePreviewModal" class="text-gray-600">閉じる</button>
                        </div>
                        <div class="border p-2">
                            <template v-if="previewModal.mime && previewModal.mime.startsWith('image/')">
                                <img :src="previewModal.url" alt="preview" class="h-auto max-w-full" />
                            </template>
                            <template v-else-if="previewModal.mime && previewModal.mime === 'application/pdf'">
                                <iframe :src="previewModal.url" class="w-full" style="height: 70vh" frameborder="0"></iframe>
                            </template>
                            <template v-else>
                                <div class="text-sm">
                                    プレビューできません。<a :href="previewModal.url" target="_blank" rel="noopener" class="text-blue-600 underline"
                                        >新しいタブで開く</a
                                    >
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
    </AppLayout>
</template>
