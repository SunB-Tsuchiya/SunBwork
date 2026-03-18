<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { route } from 'ziggy-js';

const props = defineProps({ event: Object, diary_id: { type: [String, Number], default: null } });

function backHref() {
    // If diary_id was provided in the query, prefer linking back to that diary's interactions
    if (props.diary_id) {
        const diary = props.diary_id;

        // 1) If the browser referrer contains the prefixed diary (user came from /leader/diaryinteractions/...), use that
        try {
            const ref = typeof document !== 'undefined' && document.referrer ? document.referrer : '';
            if (ref) {
                try {
                    const refUrl = new URL(ref, window.location.origin);
                    const refParts = refUrl.pathname.split('/').filter(Boolean);
                    const refPrefix =
                        refParts.length && (refParts[0] === 'leader' || refParts[0] === 'admin' || refParts[0] === 'admin2') ? `/${refParts[0]}` : '';
                    if (refPrefix) return `${refPrefix}/diaryinteractions/${diary}`;
                } catch (e) {
                    // ignore malformed referrer
                }
            }
        } catch (e) {
            // ignore
        }

        // 2) Next, inspect current pathname for a prefix (if user opened a prefixed page)
        try {
            const p = typeof window !== 'undefined' && window.location && window.location.pathname ? window.location.pathname : '';
            const parts = p.split('/').filter(Boolean);
            const prefix = parts.length && (parts[0] === 'leader' || parts[0] === 'admin' || parts[0] === 'admin2') ? `/${parts[0]}` : '';
            if (prefix) return `${prefix}/diaryinteractions/${diary}`;
        } catch (e) {
            // ignore
        }

        // 3) Try Ziggy route helper (may throw if route not available)
        try {
            return route('diaryinteractions.show', diary);
        } catch (e) {
            // fallthrough to global fallback
        }

        // 4) Final fallback: unprefixed canonical interactions index with diary as query
        return `/diaryinteractions/interactions?diary=${encodeURIComponent(diary)}`;
    }

    // Fallback to the diary interactions index (not the calendar)
    try {
        return route('diaryinteractions.index');
    } catch (e) {
        try {
            const p = typeof window !== 'undefined' && window.location && window.location.pathname ? window.location.pathname : '';
            const parts = p.split('/').filter(Boolean);
            const prefix = parts.length && (parts[0] === 'leader' || parts[0] === 'admin' || parts[0] === 'admin2') ? `/${parts[0]}` : '';
            // For prefixed admin/leader routes, the index is at /{prefix}/diaryinteractions
            if (prefix) return `${prefix}/diaryinteractions`;
            // For unprefixed, the canonical index route is /diaryinteractions/interactions
            return '/diaryinteractions/interactions';
        } catch (__e) {
            return '/diaryinteractions/interactions';
        }
    }
}

function formatJstDateTime(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    d.setHours(d.getHours() + 9);
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    const hh = String(d.getHours()).padStart(2, '0');
    const min = String(d.getMinutes()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd} ${hh}:${min}`;
}

function onBack() {
    try {
        if (typeof window !== 'undefined' && window.history && window.history.length > 1) {
            window.history.back();
            return;
        }
    } catch (e) {
        // ignore
    }

    try {
        if (props.diary_id) {
            const p = typeof window !== 'undefined' && window.location && window.location.pathname ? window.location.pathname : '';
            const parts = p.split('/').filter(Boolean);
            const prefix = parts.length && (parts[0] === 'leader' || parts[0] === 'admin' || parts[0] === 'admin2') ? `/${parts[0]}` : '';
            if (prefix) {
                window.location.href = `${prefix}/diaryinteractions/${props.diary_id}`;
                return;
            }
            window.location.href = `/diaryinteractions/interactions?diary=${encodeURIComponent(props.diary_id)}`;
            return;
        }
    } catch (e) {
        // ignore
    }

    // Final fallback
    try {
        window.location.href = '/diaryinteractions/interactions';
    } catch (e) {
        /* ignore */
    }
}

// No delete/complete actions in interactions read-only view
</script>

<template>
    <AppLayout title="スケジュール（閲覧）">
        <div class="rounded bg-white p-6 shadow">
                    <h1 class="mb-4 text-2xl font-bold">スケジュール（閲覧） {{ props.event.title }}</h1>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">日時</label>
                        <div class="mt-1 text-sm text-gray-900">
                            開始: {{ formatJstDateTime(props.event.start) }}<br />
                            終了: {{ formatJstDateTime(props.event.end) }}
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">詳細</label>
                        <div class="whitespace-pre-wrap text-sm text-gray-900">{{ props.event.description || '-' }}</div>
                    </div>
                    <div class="flex">
                        <button type="button" class="rounded bg-gray-200 px-4 py-2 text-gray-700" @click.prevent="onBack">戻る</button>
                    </div>
                </div>
    </AppLayout>
</template>
