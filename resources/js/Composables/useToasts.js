import { ref } from 'vue';

// Shared (singleton) toast state so multiple imports share the same toasts
const toasts = ref([]);
const idCounter = ref(1);

/**
 * Show a toast message.
 * action: optional object { label: string, handler: function }
 */
function showToast(message, type = 'info', duration = 3000, action = null) {
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
    switch (type) {
        case 'success':
            return 'bg-green-600 text-white p-3 rounded shadow';
        case 'error':
            return 'bg-red-600 text-white p-3 rounded shadow';
        case 'warning':
            return 'bg-yellow-500 text-white p-3 rounded shadow';
        default:
            return 'bg-gray-800 text-white p-3 rounded shadow';
    }
}

export default function useToasts() {
    return { toasts, showToast, dismissToast, toastClass };
}
