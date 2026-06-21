import { ref } from 'vue';

// モジュールレベルシングルトン（全コンポーネントで共有・一度だけフェッチ）
const holidays = ref({});
let fetchState = 'idle';

async function fetchHolidays() {
    if (fetchState !== 'idle') return;
    fetchState = 'loading';
    try {
        const res = await fetch('https://holidays-jp.github.io/api/v1/date.json');
        if (res.ok) holidays.value = await res.json();
    } catch { /* ignore */ }
    fetchState = 'done';
}

export function useJapaneseHolidays() {
    return {
        holidays,
        fetchHolidays,
        isHoliday:   (d) => d != null && d in holidays.value,
        holidayName: (d) => holidays.value[d] ?? null,
    };
}
