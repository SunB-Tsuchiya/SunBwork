<template>
    <div>
        <!-- provide min-height and define gantt CSS variables so layout doesn't collapse -->
        <div
            ref="ganttEl"
            class="gantt-container"
            style="
                min-height: 170px;
                --gv-grid-height: 170px;
                --gv-column-width: 45px;
                --gv-lower-header-height: 36px;
                --gv-upper-header-height: 36px;
            "
        ></div>
    </div>
</template>

<script setup>
import Gantt from 'frappe-gantt';
import { nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
// Import CSS for frappe-gantt (local fallback)
import '../../css/frappe-gantt.css';

const props = defineProps({ tasks: Array });
const emit = defineEmits(['update-task', 'select-task']);
const ganttEl = ref(null);
let gantt = null;
let ganttOptions = null;
const currentTasks = ref([]);

function computeRequiredHeight(tasks) {
    // determine number of visible rows (fallback to 1)
    const rows = tasks && tasks.length ? tasks.length : 1;
    // sensible defaults — tuned to make bars visible
    const rowHeight = 40; // px per task row
    const upperHeader = 36; // upper header height
    const lowerHeader = 36; // lower header height
    const padding = 20; // extra spacing
    const total = upperHeader + lowerHeader + rows * rowHeight + padding;
    // ensure a reasonable minimum
    return Math.max(170, Math.ceil(total));
}

function applyContainerHeight(height) {
    if (!ganttEl.value) return;
    // apply to outer container
    ganttEl.value.style.minHeight = height + 'px';
    ganttEl.value.style.setProperty('--gv-grid-height', height + 'px');

    // also apply to inner container that frappe-gantt creates (it may set explicit height)
    const inner = ganttEl.value.querySelector('.gantt-container');
    // compute a reasonable bar height based on assumed row height
    const rowHeight = 40;
    const barHeight = Math.max(20, rowHeight - 10);
    if (inner) {
        try {
            inner.style.height = height + 'px';
            inner.style.minHeight = height + 'px';
            inner.style.setProperty('--gv-grid-height', height + 'px');
            inner.style.setProperty('--gv-bar-height', barHeight + 'px');
        } catch (e) {}
    }
}

function fmtDate(v) {
    if (!v) return null;
    if (v instanceof Date) return v.toISOString().slice(0, 10);
    try {
        const d = new Date(v);
        if (!isNaN(d)) return d.toISOString().slice(0, 10);
    } catch (e) {}
    return String(v).split('T')[0];
}

onMounted(async () => {
    if (!ganttEl.value) return;

    const normalize = (arr) =>
        (arr || []).map((t) => ({
            id: String(t.id),
            name: t.name || 'untitled',
            start: fmtDate(t.start) || new Date().toISOString().slice(0, 10),
            end: fmtDate(t.end) || new Date().toISOString().slice(0, 10),
            progress: Number(t.progress ?? 0),
        }));

    currentTasks.value = normalize(props.tasks || []);
    // set container height based on tasks
    const h = computeRequiredHeight(currentTasks.value);
    applyContainerHeight(h);

    ganttOptions = {
        on_click: (task) => emit('select-task', task),
        on_date_change: (task, start, end) => emit('update-task', { id: task.id, start, end }),
        on_progress_change: (task, progress) => emit('update-task', { id: task.id, progress }),
    };

    // safe progress handlers (monkey-patch) — will intercept progress handle events and run a guarded implementation
    let _cleanupProgressHandlers = null;
    let _todayClickCleanup = null;
    function installSafeProgressHandlers(chart) {
        const svg = chart.$svg;
        if (!svg) return () => {};
        let dragging = false;
        let active = null; // { bar, startX, startWidth }
        // bar dragging state (for moving whole task bar)
        let draggingBar = false;
        let activeBar = null; // { bar, startMouseX, origX }

        const getOffsetX = (e) => {
            const rect = svg.getBoundingClientRect();
            // account for horizontal scroll inside the chart container so coordinates don't drift
            const scrollLeft =
                chart && chart.$container && typeof chart.$container.scrollLeft === 'number'
                    ? chart.$container.scrollLeft
                    : ganttEl && ganttEl.value
                      ? ganttEl.value.scrollLeft || 0
                      : 0;
            return e.clientX - rect.left + scrollLeft;
        };

        const onMouseDown = (e) => {
            // If user pressed on any bar/handle, temporarily disable container scrolling
            const maybeBarOrHandle = e.target.closest && e.target.closest('.bar-wrapper, .handle');
            if (maybeBarOrHandle) {
                try {
                    // hide native scrollbar movement while dragging to avoid auto-scroll to far future
                    if (ganttEl && ganttEl.value) {
                        ganttEl.value.dataset._scrollOverflow = ganttEl.value.style.overflow;
                        ganttEl.value.style.overflow = 'hidden';
                    }
                } catch (err) {}
            }

            const handle = e.target.closest && e.target.closest('.handle.progress');
            // If clicked on the bar wrapper (but not the progress handle) we will intercept bar dragging
            let wrapper = e.target.closest && e.target.closest('.bar-wrapper');
            if (wrapper && !handle) {
                // prevent library handlers and start our own drag capture for snapping
                try {
                    e.stopImmediatePropagation();
                    e.preventDefault();
                } catch (e) {}
                const id = wrapper.getAttribute('data-id');
                const barObj = chart.get_bar(id);
                if (barObj) {
                    draggingBar = true;
                    // disable library drag handlers while our wrapper handles the drag
                    try {
                        if (chart && chart.options) {
                            activeBar = activeBar || {};
                            activeBar._oldReadonly = chart.options.readonly;
                            chart.options.readonly = true;
                        }
                        // disable text selection while dragging
                        if (document && document.body) {
                            activeBar = activeBar || {};
                            activeBar._oldUserSelect = document.body.style.userSelect;
                            document.body.style.userSelect = 'none';
                        }
                    } catch (err) {}
                    activeBar = Object.assign(activeBar || {}, {
                        bar: barObj,
                        startMouseX: getOffsetX(e),
                        origX: barObj.$bar.getX ? barObj.$bar.getX() : Number(barObj.$bar.getAttribute('x') || 0),
                    });
                    // lock the chart's own scroll container if available
                    try {
                        if (chart.$container) {
                            chart.$container.dataset._scrollOverflow = chart.$container.style.overflowX || chart.$container.style.overflow || '';
                            chart.$container.style.overflowX = 'hidden';
                            chart.$container.style.overflow = 'hidden';
                        }
                    } catch (err) {}
                }
                return;
            }
            if (!handle) return;
            // stop library handlers
            e.stopImmediatePropagation();
            e.preventDefault();
            wrapper = handle.closest('.bar-wrapper');
            if (!wrapper) return;
            const id = wrapper.getAttribute('data-id');
            const barObj = chart.get_bar(id);
            if (!barObj) return;
            dragging = true;
            const startX = getOffsetX(e);
            const startWidth = Number(barObj.$bar_progress.getAttribute('width')) || 0;
            active = { bar: barObj, startX, startWidth };
        };

        const onMouseMove = (e) => {
            if (!dragging || !active) return;
            const curX = getOffsetX(e);
            const dx = curX - active.startX;
            const bar = active.bar;
            const barTotalWidth = Number(bar.$bar.getAttribute('width')) || bar.$bar.getWidth?.() || 0;
            let newWidth = active.startWidth + dx;
            if (newWidth < 0) newWidth = 0;
            if (newWidth > barTotalWidth) newWidth = barTotalWidth;
            try {
                bar.$bar_progress.setAttribute('width', String(Math.round(newWidth)));
                // update handle position if exists
                const handleEl = bar.group.querySelector('.handle.progress');
                const endX =
                    (bar.$bar_progress.getEndX && bar.$bar_progress.getEndX()) || Number(bar.$bar_progress.getAttribute('x') || 0) + newWidth;
                if (handleEl) handleEl.setAttribute('cx', String(endX));
            } catch (err) {}
            // compute and emit progress
            const progress = Math.round((newWidth / (barTotalWidth || 1)) * 100);
            try {
                // update internal task model and trigger library event so our on_progress_change runs
                bar.task.progress = progress;
                chart.trigger_event('progress_change', [bar.task, progress]);
            } catch (err) {}
            return;
        };

        // separate mousemove handler for bar dragging (snap to column width)
        const onMouseMoveBar = (e) => {
            if (!draggingBar || !activeBar) return;
            const curX = getOffsetX(e);
            const dx = curX - activeBar.startMouseX;
            const colWidth = chart && chart.config && chart.config.column_width ? chart.config.column_width : 45;
            // compute new X and snap to nearest column
            const rawX = activeBar.origX + dx;
            const snappedX = Math.round(rawX / colWidth) * colWidth;
            try {
                activeBar.bar.update_bar_position({ x: snappedX });
            } catch (err) {}
            // prevent default scroll while dragging
            try {
                e.preventDefault();
            } catch (e) {}
        };

        const onMouseUp = (e) => {
            // handle progress drag finish
            if (dragging || active) {
                dragging = false;
                active = null;
            }
            // handle bar drag finish
            if (draggingBar || activeBar) {
                // restore chart options and selections
                try {
                    if (activeBar && activeBar._oldReadonly !== undefined && chart && chart.options) chart.options.readonly = activeBar._oldReadonly;
                } catch (err) {}
                try {
                    if (activeBar && activeBar._oldUserSelect !== undefined && document && document.body)
                        document.body.style.userSelect = activeBar._oldUserSelect || '';
                } catch (err) {}
                draggingBar = false;
                activeBar = null;
                // restore chart container overflow if locked
                try {
                    if (chart.$container && chart.$container.dataset && chart.$container.dataset._scrollOverflow !== undefined) {
                        chart.$container.style.overflowX = chart.$container.dataset._scrollOverflow || '';
                        chart.$container.style.overflow = chart.$container.dataset._scrollOverflow || '';
                        delete chart.$container.dataset._scrollOverflow;
                    }
                } catch (err) {}
            }
            // restore outer container scrolling
            try {
                if (ganttEl && ganttEl.value && ganttEl.value.dataset && ganttEl.value.dataset._scrollOverflow !== undefined) {
                    ganttEl.value.style.overflow = ganttEl.value.dataset._scrollOverflow || '';
                    delete ganttEl.value.dataset._scrollOverflow;
                }
            } catch (err) {}
        };

        // capture phase handler to prevent library handlers from running
        svg.addEventListener('mousedown', onMouseDown, true);
        document.addEventListener('mousemove', onMouseMove, true);
        document.addEventListener('mousemove', onMouseMoveBar, true);
        document.addEventListener('mouseup', onMouseUp, true);

        // ensure cleanup also restores overflow if mouseup happens outside svg
        const onDocMouseUpFallback = () => {
            try {
                if (ganttEl && ganttEl.value && ganttEl.value.dataset && ganttEl.value.dataset._scrollOverflow !== undefined) {
                    ganttEl.value.style.overflow = ganttEl.value.dataset._scrollOverflow || '';
                    delete ganttEl.value.dataset._scrollOverflow;
                }
            } catch (err) {}
        };
        document.addEventListener('mouseup', onDocMouseUpFallback, false);

        return () => {
            svg.removeEventListener('mousedown', onMouseDown, true);
            document.removeEventListener('mousemove', onMouseMove, true);
            document.removeEventListener('mouseup', onMouseUp, true);
            document.removeEventListener('mouseup', onDocMouseUpFallback, false);
        };
    }

    // Ensure globals used by the bundled frappe-gantt don't cause ReferenceError
    try {
        if (typeof window !== 'undefined') {
            // some bundled versions reference `y_on_start` without declaring it
            if (window.y_on_start === undefined) window.y_on_start = 0;
        }
    } catch (e) {}

    gantt = new Gantt(ganttEl.value, currentTasks.value, ganttOptions);
    // install safe progress handlers and remember cleanup
    try {
        _cleanupProgressHandlers = installSafeProgressHandlers(gantt);
    } catch (e) {}
    // helper to center today in the visible viewport
    const centerToday = () => {
        try {
            // Prefer library-provided API
            if (gantt && typeof gantt.scroll_current === 'function') {
                try {
                    gantt.scroll_current();
                    return;
                } catch (e) {}
            }
            if (gantt && typeof gantt.set_scroll_position === 'function') {
                try {
                    gantt.set_scroll_position('today');
                    return;
                } catch (e) {}
            }

            // Prefer internal references created by the library if available
            try {
                const internalHighlight = gantt && (gantt.$current_highlight || gantt.$current_ball_highlight || null);
                if (internalHighlight && ganttEl && ganttEl.value) {
                    // library may attach a plain DOM element or a wrapper; try style.left then bounding rect
                    let left = 0;
                    try {
                        if (internalHighlight.style && internalHighlight.style.left)
                            left = parseFloat(internalHighlight.style.left.replace('px', '')) || 0;
                    } catch (e) {}
                    if (!left) {
                        try {
                            left = internalHighlight.offsetLeft || 0;
                        } catch (e) {
                            left = 0;
                        }
                    }
                    // center target
                    const target = Math.max(0, Math.round(left - ganttEl.value.clientWidth / 2));
                    try {
                        ganttEl.value.scrollTo({ left: target, behavior: 'smooth' });
                    } catch (e) {
                        ganttEl.value.scrollLeft = target;
                    }
                    return;
                }
            } catch (e) {}

            // fallback: compute left from element query
            const highlight =
                (gantt && gantt.$container && gantt.$container.querySelector('.current-highlight')) || document.querySelector('.current-highlight');
            if (highlight && ganttEl && ganttEl.value) {
                let left = 0;
                const ls = highlight.style && highlight.style.left;
                if (ls) left = parseFloat(ls.replace('px', '')) || 0;
                if (!left) left = highlight.offsetLeft || 0;
                const target = Math.max(0, Math.round(left - ganttEl.value.clientWidth / 2));
                try {
                    ganttEl.value.scrollTo({ left: target, behavior: 'smooth' });
                } catch (e) {
                    ganttEl.value.scrollLeft = target;
                }
            }
        } catch (err) {}
    };
    // attempt to center today after initial render and several short delays to allow library to finish layout
    try {
        await nextTick();
        centerToday();
        setTimeout(centerToday, 20);
        setTimeout(centerToday, 120);
        setTimeout(centerToday, 400);

        // If the library inserts the today highlight slightly later, watch for it and center once observed.
        try {
            const containerToObserve = gantt && gantt.$container ? gantt.$container : ganttEl && ganttEl.value ? ganttEl.value : null;
            if (containerToObserve && typeof MutationObserver !== 'undefined') {
                const mo = new MutationObserver((mutations) => {
                    for (const m of mutations) {
                        for (const n of m.addedNodes) {
                            if (!n || n.nodeType !== 1) continue;
                            const el = /** @type {Element} */ (n);
                            try {
                                if (el.classList && (el.classList.contains('current-highlight') || el.classList.contains('current-ball-highlight'))) {
                                    centerToday();
                                    try {
                                        mo.disconnect();
                                    } catch (err) {}
                                    return;
                                }
                                // also check descendants
                                if (el.querySelector && (el.querySelector('.current-highlight') || el.querySelector('.current-ball-highlight'))) {
                                    centerToday();
                                    try {
                                        mo.disconnect();
                                    } catch (err) {}
                                    return;
                                }
                            } catch (err) {}
                        }
                    }
                });
                mo.observe(containerToObserve, { childList: true, subtree: true });
                // merge cleanup so onUnmounted disconnects observer as well
                const prev = _todayClickCleanup;
                _todayClickCleanup = () => {
                    try {
                        mo.disconnect();
                    } catch (e) {}
                    try {
                        if (typeof prev === 'function') prev();
                    } catch (e) {}
                };
            }
        } catch (err) {}
    } catch (e) {}
    // Add click handler to the "today" marker so clicking it centers today (if possible)
    try {
        await nextTick();
        // try header (some versions append the ball into gantt.$header), then container, then document
        let todayMarker = null;
        try {
            if (gantt && gantt.$header && typeof gantt.$header.querySelector === 'function') {
                todayMarker = gantt.$header.querySelector('.current-ball-highlight, .current-highlight');
            }
        } catch (err) {}
        if (!todayMarker && ganttEl && ganttEl.value) {
            todayMarker = ganttEl.value.querySelector('.current-ball-highlight, .current-highlight');
        }
        if (!todayMarker) {
            todayMarker = document.querySelector('.current-ball-highlight, .current-highlight');
        }
        if (todayMarker) {
            // inject a tiny CSS rule so the ball shows pointer cursor
            let styleTag = null;
            try {
                styleTag = document.createElement('style');
                styleTag.setAttribute('data-gantt-today-cursor', '1');
                styleTag.innerText = '.current-ball-highlight, .current-highlight, .current-date-highlight{cursor: pointer !important;}';
                document.head.appendChild(styleTag);
            } catch (err) {
                styleTag = null;
            }

            const todayClickHandler = (ev) => {
                try {
                    const clicked = ev.target.closest && ev.target.closest('.current-ball-highlight, .current-highlight, .current-date-highlight');
                    if (!clicked) return;

                    // Prefer library centering methods if available
                    if (gantt && typeof gantt.scroll_current === 'function') {
                        gantt.scroll_current();
                        return;
                    }
                    if (gantt && typeof gantt.set_scroll_position === 'function') {
                        try {
                            gantt.set_scroll_position('today');
                            return;
                        } catch (e) {}
                    }

                    // fallback: find the vertical highlight inside the container and center it
                    try {
                        const highlight =
                            (ganttEl && ganttEl.value && ganttEl.value.querySelector('.current-highlight')) ||
                            document.querySelector('.current-highlight');
                        if (highlight && ganttEl && ganttEl.value) {
                            // prefer numeric left from style, otherwise use offsetLeft
                            let left = 0;
                            const ls = highlight.style && highlight.style.left;
                            if (ls) {
                                // style might be like '123px'
                                left = parseFloat(ls.replace('px', '')) || 0;
                            }
                            if (!left) left = highlight.offsetLeft || 0;
                            const target = Math.max(0, Math.round(left - ganttEl.value.clientWidth / 2));
                            try {
                                ganttEl.value.scrollTo({ left: target, behavior: 'smooth' });
                            } catch (e) {
                                ganttEl.value.scrollLeft = target;
                            }
                            return;
                        }
                    } catch (err) {}
                } catch (err) {}
            };

            document.addEventListener('click', todayClickHandler, false);
            _todayClickCleanup = () => {
                try {
                    document.removeEventListener('click', todayClickHandler, false);
                } catch (e) {}
                try {
                    if (styleTag && styleTag.parentNode) styleTag.parentNode.removeChild(styleTag);
                } catch (e) {}
            };
        }
    } catch (err) {}
    // ensure height is applied after gantt creates internal elements
    try {
        await nextTick();
        applyContainerHeight(h);
        // reapply after small delays to counter library's later DOM/style writes
        setTimeout(() => applyContainerHeight(h), 0);
        setTimeout(() => applyContainerHeight(h), 120);
    } catch (e) {}
});

onUnmounted(() => {
    try {
        if (_cleanupProgressHandlers) _cleanupProgressHandlers();
    } catch (e) {}
    try {
        if (_todayClickCleanup) _todayClickCleanup();
    } catch (e) {}
    try {
        if (gantt && typeof gantt.clear === 'function') gantt.clear();
    } catch (e) {}
});

watch(
    () => props.tasks,
    async (n) => {
        if (!gantt) return;
        try {
            const normalize = (arr) =>
                (arr || []).map((t) => ({
                    id: String(t.id),
                    name: t.name || 'untitled',
                    start: fmtDate(t.start) || new Date().toISOString().slice(0, 10),
                    end: fmtDate(t.end) || new Date().toISOString().slice(0, 10),
                    progress: Number(t.progress ?? 0),
                }));

            currentTasks.value = normalize(n || []);
            // adjust container height for new tasks
            const h = computeRequiredHeight(currentTasks.value);
            applyContainerHeight(h);

            // If frappe-gantt supports passing new tasks into refresh, use it.
            if (typeof gantt.refresh === 'function') {
                // some versions accept array param
                try {
                    gantt.refresh(currentTasks.value);
                    return;
                } catch (e) {
                    // continue to recreate fallback
                }
            }

            // fallback: destroy and recreate
            try {
                if (typeof gantt.clear === 'function') gantt.clear();
            } catch (e) {}
            try {
                if (_todayClickCleanup) _todayClickCleanup();
            } catch (e) {}
            try {
                if (typeof window !== 'undefined') {
                    if (window.y_on_start === undefined) window.y_on_start = 0;
                }
            } catch (e) {}
            gantt = new Gantt(ganttEl.value, currentTasks.value, ganttOptions || {});
            try {
                _cleanupProgressHandlers && _cleanupProgressHandlers();
            } catch (e) {}
            try {
                _cleanupProgressHandlers = installSafeProgressHandlers(gantt);
            } catch (e) {}
            // center today after recreate
            try {
                await nextTick();
                centerToday();
            } catch (e) {}
            // reapply height after recreate
            try {
                await nextTick();
                applyContainerHeight(h);
            } catch (e) {}
        } catch (e) {
            // ignore errors in PoC
        }
    },
    { deep: true },
);
</script>

<style scoped>
.gantt-container {
    /* ensure the internal svg/table have space */
    min-height: 170px;
    overflow: auto;
}
</style>
