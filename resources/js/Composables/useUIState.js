import { ref, watch } from 'vue';

export function useUIState(key, defaultValue) {
    const raw = localStorage.getItem(key);
    let initial = defaultValue;
    if (raw !== null) {
        try { initial = JSON.parse(raw); } catch { initial = raw; }
    }
    const state = ref(initial);
    watch(state, (v) => localStorage.setItem(key, JSON.stringify(v)));
    return state;
}
