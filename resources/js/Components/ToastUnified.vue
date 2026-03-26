<template>
    <div class="fixed left-1/2 top-4 z-50 w-full max-w-xl -translate-x-1/2 transform">
        <!-- Shared composable toasts (calls to showToast) -->
        <transition-group name="toast" tag="div" class="w-full space-y-3">
            <div v-for="t in composableToasts" :key="t.id" class="toast-wrapper mx-auto w-full">
                <div :class="toastClass(t.type)">
                    <div class="flex items-center justify-between">
                        <div class="flex-1 pr-4">{{ t.message }}</div>
                        <div class="flex items-center gap-3">
                            <button
                                v-if="t.action && typeof t.action.handler === 'function'"
                                @click="
                                    () => {
                                        t.action.handler();
                                        dismiss(t.id);
                                    }
                                "
                                class="text-sm text-white underline"
                            >
                                {{ t.action.label || 'Action' }}
                            </button>
                            <button @click="dismiss(t.id)" class="ml-3 text-sm text-white">✕</button>
                        </div>
                    </div>
                </div>
            </div>
        </transition-group>

        <!-- Event-driven toasts (flash/errors/jobrequest/message) managed locally -->
        <transition-group name="toast" tag="div" class="mt-3">
            <div
                v-for="toast in localToasts"
                :key="toast.id"
                v-show="toast.visible"
                class="flex items-start justify-between rounded p-4 text-base shadow-lg"
                :class="toast.type === 'error' ? 'bg-red-600 text-white' : 'bg-orange-600 text-white'"
            >
                <div class="flex-1">
                    <div class="text-lg font-semibold">{{ toast.type === 'error' ? 'エラー' : '通知' }}</div>
                    <div class="mt-1">{{ toast.message }}</div>
                </div>
                <button @click="dismissLocal(toast.id)" class="ms-4 text-white opacity-90 hover:opacity-100">✕</button>
            </div>
        </transition-group>
    </div>
</template>

<script setup>
import useToasts from '@/Composables/useToasts';
import { router, usePage } from '@inertiajs/vue3';
import { onMounted, onUnmounted, reactive } from 'vue';

const { toasts: composableToasts, dismissToast, toastClass } = useToasts();

// local event-driven toasts (mimics previous Toast.vue behavior)
const page = usePage();
const localToasts = reactive([]);
const recentLocal = {};
const LOCAL_DEDUPE_MS = 1500;
// record of seen notification ids (from server broadcasts) to prevent double-show
const seenNotificationIds = new Set();

function pushLocal({ id, type = 'success', message = '' }) {
    try {
        const key = `${type}|${String(message)}`;
        const now = Date.now();
        const last = recentLocal[key] || 0;
        if (now - last < LOCAL_DEDUPE_MS) {
            return;
        }
        recentLocal[key] = now;
    } catch (err) {}

    const toast = { id, type, message, visible: true };
    localToasts.push(toast);
    setTimeout(() => dismissLocal(id), 4000);
}

function dismissLocal(id) {
    const idx = localToasts.findIndex((t) => t.id === id);
    if (idx !== -1) {
        localToasts[idx].visible = false;
        setTimeout(() => {
            const i = localToasts.findIndex((t) => t.id === id);
            if (i !== -1) localToasts.splice(i, 1);
        }, 300);
    }
}

// helper to dismiss composable toasts
function dismiss(id) {
    dismissToast(id);
}

function checkFlashAndErrors() {
    const flash = page.props.flash;
    if (flash && flash.message) {
        pushLocal({ id: `flash-${Date.now()}`, type: flash.type || 'success', message: flash.message });
    }
    const errors = page.props.errors || {};
    if (errors && Object.keys(errors).length) {
        const firstKey = Object.keys(errors)[0];
        const firstVal = errors[firstKey];
        const msg = Array.isArray(firstVal) ? firstVal[0] : String(firstVal || '');
        pushLocal({ id: `errors-${Date.now()}`, type: 'error', message: msg || 'エラーがあります。詳細はページをご確認ください。' });
    }
}

let removeNavigateListener = null;

onMounted(() => {
    checkFlashAndErrors();

    // ナビゲーション（Inertiaのリダイレクト含む）後にもflash/errorsを検知
    removeNavigateListener = router.on('navigate', () => {
        checkFlashAndErrors();
    });

    // Listen for events dispatched by AppLayout (jobrequest/message)
    window.addEventListener('jobrequest:received', (ev) => {
        const message = ev?.detail?.message || '新しい依頼があります';
        const nid = ev?.detail?.id || `jobreq-${Date.now()}`;
        if (nid && seenNotificationIds.has(String(nid))) return;
        if (nid) seenNotificationIds.add(String(nid));
        pushLocal({ id: nid, type: 'success', message });
    });
    window.addEventListener('message:received', (ev) => {
        const message = ev?.detail?.message || '新しいメールがあります';
        const nid = ev?.detail?.id || `message-${Date.now()}`;
        if (nid && seenNotificationIds.has(String(nid))) return;
        if (nid) seenNotificationIds.add(String(nid));
        pushLocal({ id: nid, type: 'success', message });
    });

    // Also subscribe to server-side lightweight toast broadcasts on the 'toasts' channel.
    try {
        if (window.Echo && typeof window.Echo.channel === 'function') {
            window.Echo.channel('toasts').listen('AssignmentStatusToast', (e) => {
                try {
                    const p = e.payload || (e.payloads ? e.payloads : {});
                    const title = p.title ? String(p.title) : '';
                    let message = '';
                    if (p.action === 'scheduled') {
                        message = `「${title}」の予定がセットされました`;
                    } else if (p.action === 'completed') {
                        message = `「${title}」の作業が完了しました`;
                    } else {
                        message = p.message || '操作が完了しました';
                    }
                    const nid = p.assignment_id || p.event_id || `toast-${Date.now()}`;
                    if (nid && seenNotificationIds.has(String(nid))) return;
                    if (nid) seenNotificationIds.add(String(nid));
                    pushLocal({ id: nid, type: 'success', message });
                } catch (err) {
                    // non-fatal
                }
            });
        }
    } catch (err) {
        // non-fatal
    }
});

onUnmounted(() => {
    if (removeNavigateListener) removeNavigateListener();
});
</script>

<style scoped>
.toast-enter-from,
.toast-leave-to {
    opacity: 0;
    transform: translateY(8px) scale(0.98);
}
.toast-enter-active,
.toast-leave-active {
    transition:
        opacity 240ms ease,
        transform 240ms ease;
}

.toast-enter-from,
.toast-leave-to {
    opacity: 0;
    transform: translateY(-6px);
}
.toast-enter-to,
.toast-leave-from {
    opacity: 1;
    transform: translateY(0);
}
.toast-enter-active,
.toast-leave-active {
    transition: all 0.25s ease;
}
</style>
