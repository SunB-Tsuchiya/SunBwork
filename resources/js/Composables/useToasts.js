import { ref } from 'vue';

// Shared (singleton) toast state so multiple imports share the same toasts
const toasts = ref([]);
const idCounter = ref(1);
// recentMessages: map of "type|message" -> timestamp ms when last shown
const recentMessages = ref({});
const DEDUPE_WINDOW_MS = 1500; // ignore identical toasts within 1.5s

/**
 * Show a toast message.
 * action: optional object { label: string, handler: function }
 */
function showToast(message, type = 'info', duration = 3000, action = null) {
    try {
        const key = `${type}|${String(message)}`;
        const now = Date.now();
        const last = recentMessages.value[key] || 0;
        if (now - last < DEDUPE_WINDOW_MS) {
            // duplicate within short window; ignore
            return null;
        }
        recentMessages.value[key] = now;
    } catch (err) {
        // fallback to showing toast on any error
    }

    const id = idCounter.value++;
    toasts.value.push({ id, message, type, action });
    if (duration && duration > 0) {
        setTimeout(() => dismissToast(id), duration);
    }
    return id;
}

function dismissToast(id) {
    const i = toasts.value.findIndex((t) => t.id === id);
    if (i !== -1) toasts.value.splice(i, 1);
}

function toastClass(type) {
    // Make toasts more prominent and use an orange theme by default
    const base = 'text-white p-4 rounded shadow-lg text-base';
    switch (type) {
        case 'success':
            return base + ' bg-orange-600';
        case 'error':
            return base + ' bg-red-600';
        case 'warning':
            return base + ' bg-orange-500';
        default:
            return base + ' bg-orange-600';
    }
}

export default function useToasts() {
    function showValidationErrors(errors, duration = 6000) {
        try {
            const fieldNames = {
                content: '内容',
                date: '日付',
                files: '添付ファイル',
                title: 'タイトル',
                subject: '件名',
            };

            const normalizeMsg = (raw, key) => {
                if (!raw) return null;
                let msg = String(raw);
                if (msg.startsWith('validation.')) {
                    // simple mappings
                    if (msg === 'validation.required') return `${fieldNames[key] || key}を入力してください`;
                    if (msg.includes('max')) return `${fieldNames[key] || key}のサイズが大きすぎます`;
                    if (msg.includes('min')) return `${fieldNames[key] || key}が短すぎます`;
                    return `${fieldNames[key] || key}に問題があります`;
                }
                return msg;
            };

            const parts = [];

            // errors can be: object (field -> [msgs]), array of msgs, or string
            if (Array.isArray(errors)) {
                // when array of messages (no field keys), try to infer
                const unique = Array.from(new Set(errors.map((e) => String(e)).filter(Boolean)));
                if (unique.length === 0) {
                    showToast('入力に問題があります', 'error', duration);
                    return;
                }
                // If array contains validation.required repeated, show a generic required message
                if (unique.every((u) => u === 'validation.required')) {
                    showToast('必須項目が入力されていません。フォームを確認してください。', 'error', duration);
                    return;
                }
                // map and join
                unique.forEach((u) => parts.push(normalizeMsg(u, null) || u));
            } else if (typeof errors === 'object' && errors !== null) {
                for (const k of Object.keys(errors)) {
                    const v = errors[k];
                    if (!v) continue;
                    const first = Array.isArray(v) && v.length ? v[0] : v;
                    const nm = normalizeMsg(first, k) || String(first);
                    parts.push(nm);
                }
            } else if (typeof errors === 'string') {
                const s = errors;
                if (s === 'validation.required') {
                    showToast('必須項目が入力されていません。フォームを確認してください。', 'error', duration);
                    return;
                }
                parts.push(s);
            } else {
                showToast('入力に問題があります', 'error', duration);
                return;
            }

            const uniqParts = Array.from(new Set(parts)).filter(Boolean);
            const out = uniqParts.length === 1 ? uniqParts[0] : uniqParts.join(' / ');
            showToast(out || '入力に問題があります', 'error', duration);
        } catch (e) {
            showToast('入力に問題があります', 'error', duration);
        }
    }

    return { toasts, showToast, dismissToast, toastClass, showValidationErrors };
}
