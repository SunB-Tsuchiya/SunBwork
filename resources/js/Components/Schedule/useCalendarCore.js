import { ref, computed, watch } from 'vue';

/**
 * カレンダー共通ナビゲーションロジック
 * Schedule / Calendar 両ページで共有
 */
export function useCalendarCore({ storageKey = 'calendar_view_mode', initialDate = '' } = {}) {
    const viewMode = ref(localStorage.getItem(storageKey) || 'week');
    watch(viewMode, (v) => localStorage.setItem(storageKey, v));

    const currentDate = ref(initialDate || new Date().toLocaleDateString('sv-SE'));

    const weekStart = computed(() => {
        const d   = new Date(currentDate.value + 'T00:00:00');
        const sun = new Date(d);
        sun.setDate(d.getDate() - d.getDay()); // 日曜始まり (getDay(): 日=0,月=1,...土=6)
        return sun.toLocaleDateString('sv-SE');
    });

    const viewYear  = computed(() => new Date(currentDate.value + 'T00:00:00').getFullYear());
    const viewMonth = computed(() => new Date(currentDate.value + 'T00:00:00').getMonth() + 1);

    const viewLabel = computed(() => {
        const d = new Date(currentDate.value + 'T00:00:00');
        if (viewMode.value === 'month') {
            return `${d.getFullYear()}年${d.getMonth() + 1}月`;
        }
        if (viewMode.value === 'week') {
            const wkStart = new Date(weekStart.value + 'T00:00:00');
            const wkEnd   = new Date(wkStart); wkEnd.setDate(wkStart.getDate() + 6);
            return `${wkStart.getFullYear()}年${wkStart.getMonth()+1}月${wkStart.getDate()}日 – ${wkEnd.getMonth()+1}月${wkEnd.getDate()}日`;
        }
        const DAYS_JA = ['日', '月', '火', '水', '木', '金', '土'];
        return `${d.getFullYear()}年${d.getMonth()+1}月${d.getDate()}日（${DAYS_JA[d.getDay()]}）`;
    });

    function navigate(dir) {
        const d = new Date(currentDate.value + 'T00:00:00');
        if (viewMode.value === 'month') {
            // 月末日（例:1/31）から月移動すると日付がずれるので1日に正規化してから移動
            d.setDate(1);
            d.setMonth(d.getMonth() + dir);
        } else if (viewMode.value === 'week') {
            d.setDate(d.getDate() + dir * 7);
        } else {
            d.setDate(d.getDate() + dir);
        }
        currentDate.value = d.toLocaleDateString('sv-SE');
    }

    function goToday() {
        currentDate.value = new Date().toLocaleDateString('sv-SE');
    }

    const loadRange = computed(() => {
        const d = new Date(currentDate.value + 'T00:00:00');
        if (viewMode.value === 'month') {
            return {
                start: new Date(d.getFullYear(), d.getMonth(), 1).toLocaleDateString('sv-SE'),
                end:   new Date(d.getFullYear(), d.getMonth() + 1, 0).toLocaleDateString('sv-SE'),
            };
        }
        if (viewMode.value === 'week') {
            const mon = new Date(weekStart.value + 'T00:00:00');
            const sun = new Date(mon); sun.setDate(mon.getDate() + 6);
            return { start: weekStart.value, end: sun.toLocaleDateString('sv-SE') };
        }
        return { start: currentDate.value, end: currentDate.value };
    });

    return { viewMode, currentDate, weekStart, viewYear, viewMonth, viewLabel, navigate, goToday, loadRange };
}
