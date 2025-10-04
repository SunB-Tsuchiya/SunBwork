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
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import axios from 'axios';
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
        timelineContentRef.value && timelineContentRef.value && windowMinutes.value > 0 ? timelineContentRef.value.clientWidth / windowMinutes.value : null;
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
        timelineContentRef.value && timelineContentRef.value && windowMinutes.value > 0 ? timelineContentRef.value.clientWidth / windowMinutes.value : null;
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

        <div class="py-6">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="rounded bg-white p-6 shadow">
                    <h1 class="mb-4 text-2xl font-bold">日報 {{ formatJstDate(props.diary.date) }}</h1>
                    <div class="prose mb-6">
                        <p v-html="props.diary.content"></p>
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
            </div>
        </div>
    </AppLayout>
</template>
