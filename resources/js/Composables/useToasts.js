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
    return { toasts, showToast, dismissToast, toastClass };
}
