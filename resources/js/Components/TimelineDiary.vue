<template>
    <div>
        <div ref="scrollWrapperRef" class="w-full overflow-x-auto rounded border bg-gray-50">
            <div ref="timelineContentRef" :style="{ minWidth: windowMinutes * usedPxPerMin + 200 + 'px' }" class="px-2 py-2">
                <div class="flex items-center justify-between px-2">
                    <div ref="labelsRowRef" class="relative h-6 w-full text-xs text-gray-600">
                        <template v-for="h in hourLabels" :key="h">
                            <div
                                :style="{
                                    position: 'absolute',
                                    left: (h - startHour) * 60 * usedPxPerMin + 'px',
                                    transform: 'translateX(-50%)',
                                }"
                                class="text-center"
                            >
                                {{ h }}時
                            </div>
                        </template>
                    </div>
                    <div class="flex items-center space-x-2">
                        <button @click="shiftLeft" :disabled="startHour <= 0" class="rounded bg-gray-200 px-2 py-1 text-sm">◀</button>
                        <button @click="shiftRight" :disabled="endHour >= 24" class="rounded bg-gray-200 px-2 py-1 text-sm">▶</button>
                    </div>
                </div>

                <div class="flex gap-2 p-2">
                    <template v-for="ev in localEvents.filter((e) => e.allDay)" :key="`ad-${ev.id}`">
                        <div class="rounded px-2 py-1 text-xs" :style="{ background: ev.color, color: '#fff' }" @click.prevent="onEventClick(ev)">
                            {{ ev.title }}
                        </div>
                    </template>
                </div>

                <div ref="timelineRef" class="relative mt-2 h-40 border-t border-gray-200" @click.stop="onBackgroundClick($event)">
                    <div class="pointer-events-none absolute inset-y-0 left-0 top-0 w-full">
                        <template v-for="h in hourLabels" :key="'hour-' + h">
                            <div
                                :style="{
                                    position: 'absolute',
                                    left: (h - startHour) * 60 * usedPxPerMin + 'px',
                                    top: '0',
                                    height: '100%',
                                    borderLeft: '1px solid rgba(0,0,0,0.16)',
                                }"
                            ></div>
                        </template>
                    </div>
                    <div class="pointer-events-none absolute inset-y-0 left-0 top-0 w-full">
                        <template v-for="i in windowMinutes / 30" :key="'half-' + i">
                            <div
                                :style="{
                                    position: 'absolute',
                                    left: (i - 1) * 30 * usedPxPerMin + 'px',
                                    top: '0',
                                    height: '100%',
                                    borderLeft: '1px solid rgba(0,0,0,0.08)',
                                }"
                            ></div>
                        </template>
                    </div>

                    <template v-for="ev in localEvents" :key="ev.id">
                        <div
                            v-if="!ev.allDay && ev.start"
                            class="absolute top-2 cursor-pointer overflow-hidden rounded px-2 py-1 text-xs text-white"
                            :title="ev.title"
                            :style="getEventStyleWithOverrides(ev)"
                            @mousedown.prevent="editable ? startDrag(ev, $event) : null"
                            @click.stop.prevent="onEventClick(ev, $event)"
                        >
                            <span
                                class="absolute left-0 top-0 h-full w-2 cursor-col-resize"
                                @mousedown.stop.prevent="editable ? startResizeLeft(ev, $event) : null"
                            ></span>
                            <span
                                class="absolute right-0 top-0 h-full w-2 cursor-col-resize"
                                @mousedown.stop.prevent="editable ? startResizeRight(ev, $event) : null"
                            ></span>
                            <span
                                v-if="computeEventStyle(ev)['--clipped-left'] === '1'"
                                class="absolute left-2 top-0 flex h-full items-center bg-black bg-opacity-40 px-1"
                                >‹</span
                            >
                            <span
                                v-if="computeEventStyle(ev)['--clipped-right'] === '1'"
                                class="absolute right-2 top-0 flex h-full items-center bg-black bg-opacity-40 px-1"
                                >›</span
                            >
                            <span class="block truncate pl-3 pr-3">{{ ev.title }}</span>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    date: { type: String, required: true },
    events: { type: Array, required: true },
    startHour: { type: Number, default: 8 },
    endHour: { type: Number, default: 20 },
    editable: { type: Boolean, default: true },
    pxPerMinute: { type: Number, default: 1.5 },
});

const emit = defineEmits(['update:events', 'open-create', 'open-edit']);

const timelineContentRef = ref(null);
const scrollWrapperRef = ref(null);
const timelineRef = ref(null);
const labelsRowRef = ref(null);

const localEvents = ref([]);

watch(
    () => props.events,
    (v) => {
        localEvents.value = (v || []).map((e) => ({ ...e }));
    },
    { immediate: true },
);

const startHourRef = ref(props.startHour);
const endHourRef = ref(props.endHour);
const pxPerMinuteRef = ref(props.pxPerMinute);

const windowMinutes = computed(() => (endHourRef.value - startHourRef.value) * 60);
const usedPxPerMin = computed(() => {
    try {
        const measured =
            timelineContentRef && timelineContentRef.value && windowMinutes.value > 0
                ? timelineContentRef.value.clientWidth / windowMinutes.value
                : null;
        return measured || pxPerMinuteRef.value;
    } catch (e) {
        return pxPerMinuteRef.value;
    }
});

const hourLabels = computed(() => {
    const labels = [];
    for (let h = startHourRef.value; h <= endHourRef.value; h++) labels.push(h);
    return labels;
});

function shiftLeft() {
    const windowHours = endHourRef.value - startHourRef.value;
    const newStart = Math.max(0, startHourRef.value - 1);
    startHourRef.value = newStart;
    endHourRef.value = newStart + windowHours;
}
function shiftRight() {
    const windowHours = endHourRef.value - startHourRef.value;
    const newEnd = Math.min(24, endHourRef.value + 1);
    endHourRef.value = newEnd;
    startHourRef.value = newEnd - windowHours;
}

function pad2(n) {
    return String(n).padStart(2, '0');
}

function computeEventStyle(ev) {
    try {
        const baselineHour = startHourRef.value;
        const measuredPxPerMin =
            timelineContentRef && timelineContentRef.value && windowMinutes.value > 0
                ? timelineContentRef.value.clientWidth / windowMinutes.value
                : null;
        const pxPerMinuteLocal = measuredPxPerMin || pxPerMinuteRef.value;
        const start = new Date(ev.start);
        const end = new Date(ev.end || ev.start);
        const startMinutes = start.getHours() * 60 + start.getMinutes();
        const endMinutes = end.getHours() * 60 + end.getMinutes();
        const windowStartMin = baselineHour * 60;
        const windowEndMin = endHourRef.value * 60;
        const origLeft = startMinutes;
        const origRight = endMinutes;
        const clampedLeft = Math.max(origLeft, windowStartMin);
        const clampedRight = Math.min(origRight, windowEndMin);
        if (clampedRight <= windowStartMin || clampedLeft >= windowEndMin) {
            return { display: 'none' };
        }
        const leftMinutes = Math.max(0, clampedLeft - windowStartMin);
        const duration = Math.max(15, clampedRight - clampedLeft);
        const leftPx = leftMinutes * pxPerMinuteLocal;
        const widthPx = duration * pxPerMinuteLocal;
        const clippedLeft = origLeft < windowStartMin;
        const clippedRight = origRight > windowEndMin;
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
            '--clipped-left': clippedLeft ? '1' : '0',
            '--clipped-right': clippedRight ? '1' : '0',
        };
    } catch (e) {
        return { display: 'none' };
    }
}

const dragOverrides = ref({});
const suppressClick = ref({});
const suppressTimelineClick = ref(false);
let dragging = ref(false);
let draggingEventId = ref(null);
let dragStartX = ref(0);
let dragOrigLeftPx = ref(0);
let dragOrigDurationMin = ref(0);
let dragMoved = ref(false);
let resizeMode = ref(null);
let resizeEventId = ref(null);
let resizeOrigLeftPx = ref(0);
let resizeOrigWidthPx = ref(0);
let resizeOrigStartMin = ref(0);
let resizeOrigEndMin = ref(0);

function getEventStyleWithOverrides(ev) {
    const base = computeEventStyle(ev) || {};
    const over = dragOverrides.value[ev.id];
    if (over && typeof over === 'object') {
        return { ...base, left: over.left + 'px', width: over.width + 'px' };
    }
    if (typeof over === 'number') {
        return { ...base, left: over + 'px' };
    }
    return base;
}

function onEventClick(ev, mouseEvent) {
    try {
        if (!ev) return;
        if (ev && ev.id && suppressClick.value[ev.id]) {
            delete suppressClick.value[ev.id];
            return;
        }
    } catch (e) {}
    // if not editable, navigate to show only
    if (!props.editable) {
        emit('open-edit', { id: ev.id, readOnly: true });
        return;
    }
    // editable: open edit
    emit('open-edit', { id: ev.id, readOnly: false, mouseEvent });
}

function onBackgroundClick(e) {
    try {
        if (suppressTimelineClick.value) {
            suppressTimelineClick.value = false;
            return;
        }
    } catch (err) {}
    if (!props.editable) {
        emit('open-create', null);
        return;
    }
    // compute snapped minute
    const contentEl = timelineContentRef && timelineContentRef.value ? timelineContentRef.value : null;
    const scrollWrap = scrollWrapperRef && scrollWrapperRef.value ? scrollWrapperRef.value : null;
    const container = contentEl || e.currentTarget || e.target;
    if (!container || !container.getBoundingClientRect) return;
    const rect = container.getBoundingClientRect();
    const contentLeft = timelineContentRef && timelineContentRef.value ? timelineContentRef.value.getBoundingClientRect().left : rect.left;
    const scrollLeft = scrollWrap ? scrollWrap.scrollLeft || 0 : container.scrollLeft || 0;
    const clickX = e.clientX - contentLeft + scrollLeft;
    const measuredPxPerMin =
        timelineContentRef && timelineContentRef.value && windowMinutes.value > 0 ? timelineContentRef.value.clientWidth / windowMinutes.value : null;
    const pxPerMin = measuredPxPerMin || pxPerMinuteRef.value;
    const rawMin = startHourRef.value * 60 + clickX / pxPerMin;
    const hourPart = Math.floor(rawMin / 60);
    const minsPastHour = Math.floor(rawMin % 60);
    const snappedMins = minsPastHour < 30 ? 0 : 30;
    const minuteOffset = hourPart * 60 + snappedMins;
    emit('open-create', { minuteOffset });
}

function computeSnappedMinuteFromClientX(clientX) {
    const contentEl = timelineContentRef && timelineContentRef.value ? timelineContentRef.value : null;
    const scrollWrap = scrollWrapperRef && scrollWrapperRef.value ? scrollWrapperRef.value : null;
    const container = contentEl;
    if (!container || !container.getBoundingClientRect) return null;
    const rect = container.getBoundingClientRect();
    const contentLeft = timelineContentRef && timelineContentRef.value ? timelineContentRef.value.getBoundingClientRect().left : rect.left;
    const scrollLeft = scrollWrap ? scrollWrap.scrollLeft || 0 : container.scrollLeft || 0;
    const clickX = clientX - contentLeft + scrollLeft;
    const measuredPxPerMin =
        timelineContentRef && timelineContentRef.value && windowMinutes.value > 0 ? timelineContentRef.value.clientWidth / windowMinutes.value : null;
    const pxPerMin = measuredPxPerMin || pxPerMinuteRef.value;
    const rawMin = startHourRef.value * 60 + clickX / pxPerMin;
    const hourPart = Math.floor(rawMin / 60);
    const minsPastHour = Math.floor(rawMin % 60);
    const snappedMins = minsPastHour < 30 ? 0 : 30;
    return hourPart * 60 + snappedMins;
}

function startDrag(ev, mouseEvent) {
    mouseEvent.preventDefault();
    dragging.value = true;
    resizeMode.value = 'move';
    draggingEventId.value = ev.id;
    dragMoved.value = false;
    dragStartX.value = mouseEvent.clientX;
    const style = computeEventStyle(ev);
    const leftPx = parseFloat(style.left) || 0;
    dragOrigLeftPx.value = leftPx;
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
    if (resizeMode.value === 'move' && draggingEventId.value) {
        const newLeftPx = Math.max(0, dragOrigLeftPx.value + dx);
        dragOverrides.value = { ...dragOverrides.value, [draggingEventId.value]: newLeftPx };
        return;
    }
    if (resizeMode.value === 'resize-left' && resizeEventId.value) {
        const newLeftPx = Math.max(0, resizeOrigLeftPx.value + dx);
        const newWidthPx = Math.max(10, resizeOrigWidthPx.value - (newLeftPx - resizeOrigLeftPx.value));
        dragOverrides.value = { ...dragOverrides.value, [resizeEventId.value]: { left: newLeftPx, width: newWidthPx } };
        return;
    }
    if (resizeMode.value === 'resize-right' && resizeEventId.value) {
        const newWidthPx = Math.max(10, resizeOrigWidthPx.value + dx);
        dragOverrides.value = { ...dragOverrides.value, [resizeEventId.value]: { left: resizeOrigLeftPx.value, width: newWidthPx } };
        return;
    }
}

async function endDrag(e) {
    if (!dragging.value && !resizeMode.value) {
        dragging.value = false;
        draggingEventId.value = null;
        resizeMode.value = null;
        resizeEventId.value = null;
        dragOverrides.value = {};
        return;
    }
    const id = resizeMode.value && resizeMode.value !== 'move' ? resizeEventId.value : draggingEventId.value;
    const ev = localEvents.value.find((x) => x.id === id);
    if (!ev) {
        dragging.value = false;
        draggingEventId.value = null;
        resizeMode.value = null;
        resizeEventId.value = null;
        dragOverrides.value = {};
        return;
    }
    if (!dragMoved.value) {
        emit('open-edit', { id });
        dragging.value = false;
        draggingEventId.value = null;
        resizeMode.value = null;
        resizeEventId.value = null;
        dragOverrides.value = {};
        return;
    }

    // Immediately suppress clicks to avoid accidental navigation during confirm/async
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

    const pxPerMin = pxPerMinuteRef.value;
    const windowStartMin = startHourRef.value * 60;
    let newStartMin = resizeOrigStartMin.value;
    let newEndMin = resizeOrigEndMin.value;
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

    const date = props.date;
    const startHourStr = String(Math.floor(newStartMin / 60)).padStart(2, '0');
    const startMinStr = String(newStartMin % 60).padStart(2, '0');
    const endHourStr = String(Math.floor(newEndMin / 60)).padStart(2, '0');
    const endMinStr = String(newEndMin % 60).padStart(2, '0');

    const confirmMessage = `予定の時間を変更しますか？\n開始: ${date} ${startHourStr}:${startMinStr}\n終了: ${date} ${endHourStr}:${endMinStr}`;
    if (!confirm(confirmMessage)) {
        dragging.value = false;
        draggingEventId.value = null;
        resizeMode.value = null;
        resizeEventId.value = null;
        dragOverrides.value = {};
        return;
    }

    // emit update event so parent can perform API call and refresh events
    emit('update:events', { id, date, startHour: startHourStr, startMinute: startMinStr, endHour: endHourStr, endMinute: endMinStr });

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
