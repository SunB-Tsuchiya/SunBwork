import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import axios from 'axios';
import { route } from 'ziggy-js';
import { addDays, dayClass, jstToday, monthStart, stripRange, sunday } from './clerkCalendarDates';

export function useClerkCalendarStrip(calendarRef) {
    const today = jstToday();
    const selectedYear = ref(Number(today.slice(0, 4)));
    const selectedMonth = ref(Number(today.slice(5, 7)));
    const topDate = ref(monthStart(selectedYear.value, selectedMonth.value));
    const holidays = ref({});
    const holidayError = ref('');
    const years = Array.from({ length: 201 }, (_, i) => 1900 + i);
    const rangeLabel = computed(() => `${topDate.value.replaceAll('-', '/')} 〜 ${addDays(topDate.value, 34).replaceAll('-', '/')}`);
    let renderedYear = selectedYear.value;
    let scroller = null;
    let timer;
    let positioning = false;
    let dragging = false;
    let disposed = false;
    let revision = 0;
    let holidayRequest = 0;
    let observer;
    let lastWidth = 0;

    const api = () => calendarRef.value?.getApi?.();
    const root = () => calendarRef.value?.$el;
    const rows = () => [...(root()?.querySelectorAll('.fc-scrollgrid-sync-table > tbody > tr') ?? [])];
    const offset = (row) => row.getBoundingClientRect().top - scroller.getBoundingClientRect().top + scroller.scrollTop;

    async function fetchHolidays() {
        const request = ++holidayRequest;
        try {
            const response = await axios.get(route('clerk.calendar.holidays.data'), { params: { year: renderedYear } });
            if (disposed || request !== holidayRequest) return;
            holidays.value = Object.fromEntries(response.data.map(item => [item.date, item.name]));
            holidayError.value = '';
            // 関数の再設定で同じ日付セルにも最新の休日クラスを適用する。
            api()?.setOption('dayCellClassNames', ({ date }) => dayClass(localKey(date), holidays.value));
        } catch {
            if (disposed || request !== holidayRequest) return;
            holidayError.value = '祝日を読み込めませんでした。再読み込みしてください。';
        }
    }

    function bindScroller() {
        const next = root()?.querySelector('.fc-daygrid-body')?.closest('.fc-scroller');
        if (scroller === next) return;
        scroller?.removeEventListener('scroll', onScroll);
        scroller = next;
        scroller?.addEventListener('scroll', onScroll, { passive: true });
    }

    // 曜日見出しはFullCalendar側で固定。スクロール領域を実測の5週分にする。
    function fitFiveWeeks() {
        if (!scroller || !root()?.getBoundingClientRect().width) return;
        const weekRows = rows();
        if (weekRows.length < 6) return;
        const fiveWeeks = weekRows[5].getBoundingClientRect().top - weekRows[0].getBoundingClientRect().top;
        const chrome = root().getBoundingClientRect().height - scroller.clientHeight;
        const height = Math.ceil(fiveWeeks + chrome);
        if (Math.abs(root().getBoundingClientRect().height - height) > 1) api()?.setOption('height', height);
    }

    async function jump(date, rowOffset = 0) {
        const current = ++revision;
        positioning = true;
        topDate.value = sunday(date);
        const year = Math.max(1900, Math.min(2100, Number(addDays(topDate.value, 4).slice(0, 4))));
        if (renderedYear !== year) {
            renderedYear = year;
            api()?.setOption('visibleRange', stripRange(year));
            fetchHolidays();
        }
        await nextTick();
        if (disposed || current !== revision) return;
        bindScroller();
        fitFiveWeeks();
        await nextTick();
        if (disposed || current !== revision) return;
        const row = root()?.querySelector(`[data-date="${topDate.value}"]`)?.closest('tr');
        if (row && scroller) scroller.scrollTop = offset(row) + rowOffset;
        // FullCalendarの再描画によるscrollイベントまで抑止する。
        requestAnimationFrame(() => { if (current === revision) positioning = false; });
    }

    function onScroll() {
        if (positioning || dragging) return;
        clearTimeout(timer);
        timer = setTimeout(() => {
            if (!scroller || positioning || dragging) return;
            const row = rows().findLast(item => offset(item) <= scroller.scrollTop + 1);
            const date = row?.querySelector('[data-date]')?.getAttribute('data-date');
            if (!date) return;
            topDate.value = date;
            const middle = addDays(date, 4);
            const year = Number(middle.slice(0, 4));
            selectedYear.value = Math.max(1900, Math.min(2100, year));
            selectedMonth.value = Number(middle.slice(5, 7));
            if (year !== renderedYear && year >= 1900 && year <= 2100) jump(date, scroller.scrollTop - offset(row));
        }, 100);
    }

    function selectMonth() {
        jump(monthStart(selectedYear.value, selectedMonth.value));
    }
    function thisMonth() {
        const date = jstToday();
        selectedYear.value = Number(date.slice(0, 4));
        selectedMonth.value = Number(date.slice(5, 7));
        selectMonth();
    }
    function moveWeek(days) {
        const date = addDays(topDate.value, days);
        const middle = addDays(date, 4);
        const year = Number(middle.slice(0, 4));
        if (year < 1900 || year > 2100) return;
        selectedYear.value = year;
        selectedMonth.value = Number(middle.slice(5, 7));
        jump(date);
    }
    async function refreshSize() {
        await nextTick();
        api()?.updateSize();
        await jump(topDate.value);
    }
    function datesSet() {
        nextTick(() => { if (!disposed && !positioning) jump(topDate.value); });
    }
    function localKey(date) {
        // FullCalendarのfloatingな終日日付から日付部分だけを取り出す。
        return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
    }

    onMounted(async () => {
        await jump(topDate.value);
        fetchHolidays();
        observer = new ResizeObserver(entries => {
            const width = entries[0]?.contentRect.width;
            if (width > 0 && width !== lastWidth) { lastWidth = width; refreshSize(); }
        });
        if (root()) observer.observe(root());
    });
    onBeforeUnmount(() => {
        disposed = true;
        clearTimeout(timer);
        observer?.disconnect();
        scroller?.removeEventListener('scroll', onScroll);
    });

    return {
        selectedYear, selectedMonth, years, rangeLabel, holidays, holidayError,
        selectMonth, thisMonth, moveWeek, refreshSize, fetchHolidays, localKey,
        options: {
            initialView: 'clerkYearStrip',
            initialDate: today,
            now: () => `${jstToday()}T12:00:00`,
            views: { clerkYearStrip: { type: 'dayGrid' } },
            visibleRange: stripRange(renderedYear),
            firstDay: 0,
            weekends: true,
            fixedWeekCount: false,
            headerToolbar: false,
            height: 635,
            dayMaxEventRows: 3,
            moreLinkText: count => `他${count}件`,
            datesSet,
            dayCellClassNames: ({ date }) => dayClass(localKey(date), holidays.value),
            dayHeaderClassNames: ({ date }) => dayClass(localKey(date), {}),
            eventDragStart: () => { dragging = true; },
            eventDragStop: () => { dragging = false; onScroll(); },
            eventResizeStart: () => { dragging = true; },
            eventResizeStop: () => { dragging = false; onScroll(); },
        },
    };
}
