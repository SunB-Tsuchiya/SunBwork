<script setup>
import DiaryTable from '@/Components/Diaries/DiaryTable.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Inertia } from '@inertiajs/inertia';
import { usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';

function formatDate(d) {
    if (!d) return '不明';
    const dt = new Date(d);
    if (isNaN(dt.getTime())) return d;
    const y = dt.getFullYear();
    const m = String(dt.getMonth() + 1).padStart(2, '0');
    const da = String(dt.getDate()).padStart(2, '0');
    return `${y}/${m}/${da}`;
}

const props = defineProps({
    departments: Array,
    date: String,
    meta: Object,
    filters: Object,
    routePrefix: { type: String, default: 'diaries' },
    pageTitle: { type: String, default: '日報（日付別）' },
    headerTitle: { type: String, default: '日報（日付別）' },
});
const selectedDate = ref(props.date || null);

// whether server requested unread-only
const serverUnread = computed(() =>
    Boolean(props.filters && (props.filters.unread === 1 || props.filters.unread === '1' || props.filters.unread === true)),
);

function routeForIndex(date) {
    const prefix = props.routePrefix || 'diaries';
    if (prefix === 'diaries') return 'diaryinteractions.interactions.index';
    return `${prefix}.diaryinteractions.index`;
}

function markReadAllRoute() {
    const prefix = props.routePrefix || 'diaries';
    if (prefix === 'diaries') return 'diaryinteractions.mark_read_all';
    return `${prefix}.diaryinteractions.mark_read_all`;
}

const clientUnread = ref(serverUnread.value);
// displayedUnread should reflect the local client toggle immediately.
// Use clientUnread only; serverUnread is used to initialize/sync client state.
const displayedUnread = computed(() => Boolean(clientUnread.value));

// keep local state in sync when server props change (navigations)
watch(
    () => serverUnread.value,
    (v) => {
        clientUnread.value = v;
    },
);

function navigateWithParams(date, unreadVal) {
    const params = {
        q: props.filters && props.filters.q,
        days: props.filters && props.filters.days,
        perPage: props.filters && props.filters.perPage,
        date: date,
    };
    // only include unread when explicitly provided (1 or 0)
    if (typeof unreadVal !== 'undefined' && unreadVal !== null) {
        params.unread = unreadVal;
    }
    try {
        if (typeof route === 'function') {
            Inertia.get(route(routeForIndex(date), params));
            return;
        }
    } catch (e) {
        // ignore and fallback
    }
    // fallback: request current path with query params
    const url = window.location.pathname;
    Inertia.get(url, params);
}

async function toggleUnread(event, date) {
    // flip local state immediately for instant UI feedback
    clientUnread.value = !clientUnread.value;

    // Update button text directly to guarantee immediate visual feedback
    try {
        const btn = event && (event.currentTarget || event.target);
        if (btn && btn.textContent !== undefined) {
            btn.textContent = clientUnread.value ? '全件表示' : '未読のみ表示';
        }
    } catch (e) {
        // ignore DOM write errors
    }

    // ensure Vue has flushed any reactive updates
    try {
        await nextTick();
    } catch (e) {
        // ignore
    }

    navigateWithParams(date, clientUnread.value ? 1 : 0);
}

// show unread-only (include unread=1)
async function showUnread(event, date) {
    clientUnread.value = true;
    try {
        const btn = event && (event.currentTarget || event.target);
        if (btn && btn.textContent !== undefined) btn.textContent = '未読のみ表示';
    } catch (e) {}
    try {
        await nextTick();
    } catch (e) {}
    navigateWithParams(date, 1);
}

// show all (do not include unread param)
async function showAll(event, date) {
    clientUnread.value = false;
    try {
        const btn = event && (event.currentTarget || event.target);
        if (btn && btn.textContent !== undefined) btn.textContent = '全件表示';
    } catch (e) {}
    try {
        await nextTick();
    } catch (e) {}
    navigateWithParams(date, null);
}

const groupedByDate = computed(() => {
    const map = {};
    const page = usePage();
    const showOnlyRead = Boolean(props.filters && (props.filters.unread === 0 || props.filters.unread === '0' || props.filters.unread === false));
    // Determine current user id: prefer authenticated user, fall back to page.props.user
    // AppLayout supplies `page.props.user`, but prefer `page.props.auth.user` when present.
    const currentUserId = page.props?.auth?.user?.id ?? page.props?.user?.id ?? null;
    function isReadByCurrentUser(d) {
        try {
            // If we don't have a current user, we cannot reliably determine read state
            // on the client — don't treat that as "read".
            if (!currentUserId) return false;
            if (!Array.isArray(d.read_by)) return false;
            if (d.read_by.includes('optimistic')) return true;
            const curStr = String(currentUserId);
            return d.read_by.some((x) => String(x) === curStr);
        } catch (e) {
            return false;
        }
    }

    // debug info removed in production
    // Note: apply read/unread filters here regardless of whether the server
    // populated read_by entries. Client-side determination relies on the
    // available read_by data and the current user id.

    (props.departments || []).forEach((group) => {
        (group.diaries || []).forEach((d, idx) => {
            // debug diary logging removed
            // if server requested unread-only, skip diaries already read by current user
            if (displayedUnread.value && isReadByCurrentUser(d)) return;
            // if server requested read-only (unread === 0), skip diaries NOT read by current user
            // Only apply this client-side filter when we actually know the current user id.
            if (showOnlyRead && currentUserId !== null && !isReadByCurrentUser(d)) return;
            const date = d.date || '不明';
            if (!map[date]) map[date] = [];
            map[date].push(d);
        });
    });
    Object.keys(map).forEach((k) => {
        map[k].sort((a, b) => b.id - a.id);
    });
    const ordered = {};
    Object.keys(map)
        .sort((a, b) => (a < b ? 1 : a > b ? -1 : 0))
        .forEach((k) => {
            ordered[k] = map[k];
        });
    return ordered;
});
</script>

<template>
    <AppLayout :title="props.pageTitle">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ props.headerTitle }}</h2>
        </template>

        <!-- 一覧に戻るリンクを見出し下に表示 -->
        <div class="mb-2">
            <a :href="route(routeForIndex())" class="text-sm text-blue-600 hover:underline">一覧に戻る</a>
        </div>

        <div v-for="(list, date) in groupedByDate" :key="date" class="mb-8">
                    <div class="mb-2">
                        <h3 class="flex items-center gap-2 text-lg font-bold">
                            <span>{{ formatDate(date) }}</span>
                            <div class="flex gap-2 text-sm">
                                <button
                                    @click.prevent="(e) => showUnread(e, date)"
                                    class="rounded border bg-white px-2 py-1 text-xs hover:bg-gray-50"
                                >
                                    未読のみ表示
                                </button>
                                <button @click.prevent="(e) => showAll(e, date)" class="rounded border bg-white px-2 py-1 text-xs hover:bg-gray-50">
                                    全件表示
                                </button>
                            </div>

                            <button
                                v-if="props.date === date"
                                @click.prevent="() => Inertia.post(route(markReadAllRoute()), { date: date })"
                                class="ml-2 inline-flex items-center rounded bg-blue-600 px-2 py-1 text-xs text-white hover:bg-blue-700"
                            >
                                全部既読にする
                            </button>
                        </h3>
                    </div>
                    <DiaryTable
                        :diaries="list"
                        :routePrefix="props.routePrefix"
                        :serverMode="true"
                        :meta="props.meta"
                        :filters="props.filters"
                        :maxDescriptionLines="5"
                        :fullContent="props.date === date"
                        :useInteractionRoutes="true"
                    />
                </div>
    </AppLayout>
</template>
