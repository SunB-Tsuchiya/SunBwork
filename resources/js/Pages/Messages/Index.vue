<template>
    <AppLayout title="メールボックス">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight">メール</h2>
        </template>

        <main>
            <div class="py-2">
                <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <!-- Top toolbar: compose, address book, search -->
                    <div class="mb-4 flex items-center gap-3">
                        <Link :href="route('messages.create')" class="inline-flex items-center gap-2 rounded bg-blue-600 px-3 py-2 text-white">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 20h9" />
                            </svg>
                            作成
                        </Link>
                        <button @click="showAddress = true" class="inline-flex items-center gap-2 rounded bg-gray-100 px-3 py-2 text-gray-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5s-3 1.34-3 3 1.34 3 3 3zM6 11c1.66 0 2.99-1.34 2.99-3S7.66 5 6 5 3 6.34 3 8s1.34 3 3 3zM6 13c-2.33 0-7 1.17-7 3.5V20h13v-3.5C12 14.17 7.33 13 6 13zM16 13c-.29 0-.62.02-.97.05C15.35 13.36 16 14.14 16 15v3h5v-3.5c0-2.33-4.67-3.5-5-3.5z"
                                />
                            </svg>
                            アドレス帳
                        </button>
                        <div class="flex-none">
                            <input type="search" placeholder="検索" class="w-[10em] rounded border px-3 py-2 text-left" />
                        </div>
                    </div>

                    <div class="flex gap-6">
                        <!-- Left: folders -->
                        <aside class="w-64 flex-shrink-0">
                            <div class="rounded-lg border bg-white shadow">
                                <div class="border-b p-4">
                                    <h4 class="font-medium">フォルダ</h4>
                                </div>
                                <nav class="p-2">
                                    <Link
                                        :href="route('messages.index', { folder: 'inbox' })"
                                        class="flex items-center gap-2 rounded px-3 py-2 hover:bg-gray-50"
                                        ><svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            class="h-4 w-4 text-gray-500"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v9a2 2 0 002 2z"
                                            /></svg
                                        >受信トレイ</Link
                                    >
                                    <Link
                                        :href="route('messages.index', { folder: 'sent' })"
                                        class="flex items-center gap-2 rounded px-3 py-2 hover:bg-gray-50"
                                        ><svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            class="h-4 w-4 text-gray-500"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M4 4v13a1 1 0 001 1h14a1 1 0 001-1V4"
                                            /></svg
                                        >送信済み</Link
                                    >
                                    <Link
                                        :href="route('messages.index', { folder: 'drafts' })"
                                        class="flex items-center gap-2 rounded px-3 py-2 hover:bg-gray-50"
                                        ><svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            class="h-4 w-4 text-gray-500"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 20h9" /></svg
                                        >下書き</Link
                                    >
                                    <Link
                                        :href="route('messages.index', { folder: 'trash' })"
                                        class="flex items-center gap-2 rounded px-3 py-2 hover:bg-gray-50"
                                        ><svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            class="h-4 w-4 text-gray-500"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M3 6h18M9 6V4h6v2M19 6l-1 14H6L5 6"
                                            /></svg
                                        >削除済み</Link
                                    >
                                </nav>
                            </div>
                        </aside>

                        <!-- Right: index (top) + preview (bottom) attached boxes -->
                        <section class="flex flex-1 flex-col gap-0">
                            <!-- Index box (top) -->
                            <div class="rounded-t-lg border-l border-r border-t bg-white shadow">
                                <div class="p-4">
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th>
                                                        <button
                                                            @click.prevent="setSort('subject')"
                                                            :aria-sort="ariaSort('subject')"
                                                            class="w-full rounded px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-600 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-300"
                                                        >
                                                            件名 <span v-if="sortBy === 'subject'">{{ sortDir === 'asc' ? '▲' : '▼' }}</span>
                                                        </button>
                                                    </th>
                                                    <th>
                                                        <button
                                                            @click.prevent="setSort('from')"
                                                            :aria-sort="ariaSort('from')"
                                                            class="w-full rounded px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-600 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-300"
                                                        >
                                                            差出人 <span v-if="sortBy === 'from'">{{ sortDir === 'asc' ? '▲' : '▼' }}</span>
                                                        </button>
                                                    </th>
                                                    <th>
                                                        <button
                                                            @click.prevent="setSort('attachments')"
                                                            :aria-sort="ariaSort('attachments')"
                                                            class="w-full rounded px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-600 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-300"
                                                        >
                                                            添付 <span v-if="sortBy === 'attachments'">{{ sortDir === 'asc' ? '▲' : '▼' }}</span>
                                                        </button>
                                                    </th>
                                                    <th>
                                                        <button
                                                            @click.prevent="setSort('time')"
                                                            :aria-sort="ariaSort('time')"
                                                            class="w-full rounded px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-600 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-300"
                                                        >
                                                            日時 <span v-if="sortBy === 'time'">{{ sortDir === 'asc' ? '▲' : '▼' }}</span>
                                                        </button>
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-200 bg-white">
                                                <tr
                                                    v-for="m in displayedList || []"
                                                    :key="m.id"
                                                    @click="selectMessage(m)"
                                                    @dblclick.prevent="openShow(m)"
                                                    :class="{ 'bg-gray-50': selected && selected.id === m.id, 'cursor-pointer': true }"
                                                >
                                                    <td class="px-4 py-2">{{ m.subject || '(件名なし)' }}</td>
                                                    <td class="px-4 py-2">{{ m.from_user_name || (m.from_user?.name ?? '') }}</td>
                                                    <td class="px-4 py-2">{{ hasAttachments(m) ? '📎' : '' }}</td>
                                                    <td class="px-4 py-2">{{ formatDate(m.sent_at || m.created_at) }}</td>
                                                    <td class="px-4 py-2 text-right">
                                                        <button
                                                            @click.stop.prevent="onIndexDeleteClick(m)"
                                                            class="rounded bg-red-50 px-2 py-1 text-sm text-red-700 hover:bg-red-100"
                                                            :title="folder === 'trash' || isTrashed(m) ? '完全削除' : '削除（ゴミ箱へ移動）'"
                                                        >
                                                            {{ folder === 'trash' || isTrashed(m) ? '完全削除' : '削除' }}
                                                        </button>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Preview box (bottom) attached to index -->
                            <div class="mt-0 flex-1 overflow-auto rounded-b-lg border-b border-l border-r bg-white shadow">
                                <div class="p-4">
                                    <div v-if="selected">
                                        <div class="flex items-start justify-between">
                                            <h3 class="text-lg font-semibold">{{ selected.subject || '(件名なし)' }}</h3>
                                            <Link :href="route('messages.show', selected.id)" class="text-sm text-blue-600">全文を見る</Link>
                                        </div>
                                        <div class="mt-1 text-sm text-gray-500">
                                            差出人: {{ selected.from_user?.name || selected.from_user_name }}
                                        </div>
                                        <div class="mt-4 text-sm text-gray-700" v-html="sanitize(selected.body)"></div>
                                        <div class="mt-3 text-sm text-gray-600">
                                            添付:
                                            <span class="font-medium">{{
                                                attachmentsCount(selected) > 0 ? attachmentsCount(selected) + ' 個あり' : 'なし'
                                            }}</span>
                                        </div>
                                    </div>
                                    <div v-else class="text-sm text-gray-500">プレビューを選択してください</div>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>
                <!-- pagination controls -->
                <div class="mx-auto mt-4 max-w-7xl sm:px-6 lg:px-8">
                    <div class="flex items-center justify-end gap-2 text-sm">
                        <template v-if="messages && messages.links">
                            <button
                                v-for="link in messages.links"
                                :key="link.label + '-' + (link.url || '')"
                                v-html="link.label"
                                :disabled="!link.url"
                                @click.prevent="onPageLinkClick(link.url)"
                                class="rounded px-3 py-1 text-gray-700 hover:bg-gray-100 disabled:opacity-50"
                                v-bind:aria-current="link.active ? 'page' : null"
                            ></button>
                        </template>
                    </div>
                </div>
            </div>
        </main>
        <AddressBookModal :show="showAddress" :companyId="currentUser.company_id" @close="showAddress = false" @select="onAddressSelect" />
    </AppLayout>
</template>

<script setup>
import AddressBookModal from '@/components/AddressBookModal.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import DOMPurify from 'dompurify';
import { computed, onMounted, ref, watch } from 'vue';
const props = defineProps({ messages: Object, folder: String });
const { messages, folder } = props;
const page = usePage();
const currentUser = page.props.user || {};
// ensure unread_messages_count fallback
if (typeof currentUser.unread_messages_count === 'undefined') {
    currentUser.unread_messages_count = page.props.unread_messages_count || 0;
}
const selected = ref(null);
const showAddress = ref(false);
// local copy of list rows so we can merge authoritative payloads without mutating props directly
const listData = ref(Array.isArray(messages && messages.data) ? [...messages.data] : []);

// sorting state (driven by server)
const sortBy = ref(page.props.sort_by || null);
const sortDir = ref(page.props.sort_dir || 'desc');

function ariaSort(field) {
    if (sortBy.value === field) return sortDir.value === 'asc' ? 'ascending' : 'descending';
    return 'none';
}

function setSort(field) {
    // toggle or set default
    let dir = 'desc';
    if (sortBy.value === field) {
        dir = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        dir = field === 'time' || field === 'attachments' ? 'desc' : 'asc';
    }
    // update local state for immediate ARIA feedback
    sortBy.value = field;
    sortDir.value = dir;

    // preserve current pagination page and folder
    const qs = Object.assign({}, page.props.reported_query || {}, { sort_by: field, sort_dir: dir, folder: folder });
    // use Inertia router to request the index with new sort params and keep pagination
    try {
        router.get(route('messages.index'), qs, { preserveState: false, preserveScroll: true });
    } catch (e) {
        // fallback: update window location
        const params = new URLSearchParams(qs).toString();
        window.location = route('messages.index') + (params ? '?' + params : '');
    }
}

// displayedList: server-ordered listData (do not apply local sorting)
const displayedList = computed(() => {
    return Array.isArray(listData.value) ? listData.value : [];
});

// keep listData in sync when props.messages updates (pagination/navigation)
watch(
    () => messages && messages.data,
    (nv) => {
        listData.value = Array.isArray(nv) ? [...nv] : [];
    },
    { immediate: true },
);

// On initial load, fetch authoritative small payloads for the first few rows to show sender/attachment info
onMounted(() => {
    const toFetch = (listData.value || [])
        .slice(0, 10)
        .filter((m) => m && m.id)
        .map((m) => m.id);
    // sequential to avoid hammering server
    (async () => {
        for (const id of toFetch) {
            try {
                const res = await axios.get(`/api/debug/messages/${id}/payload`);
                if (res && res.data) {
                    const idx = listData.value.findIndex((x) => x.id === id);
                    if (idx >= 0) listData.value.splice(idx, 1, Object.assign({}, listData.value[idx], res.data));
                }
            } catch (e) {
                try {
                    const r2 = await axios.get(`/api/debug/public/messages/${id}/payload`);
                    if (r2 && r2.data) {
                        const idx2 = listData.value.findIndex((x) => x.id === id);
                        if (idx2 >= 0) listData.value.splice(idx2, 1, Object.assign({}, listData.value[idx2], r2.data));
                    }
                } catch (e2) {
                    // ignore
                }
            }
        }
    })();
});

function hasAttachments(m) {
    if (!m) return false;
    // arrays
    if (Array.isArray(m.attachments) && m.attachments.length) return true;
    if (m.attachments && Array.isArray(m.attachments.data) && m.attachments.data.length) return true;
    if (Array.isArray(m.uploads) && m.uploads.length) return true;
    if (m.uploads && Array.isArray(m.uploads.data) && m.uploads.data.length) return true;
    if (Array.isArray(m.files) && m.files.length) return true;
    if (Array.isArray(m.attachment_ids) && m.attachment_ids.length) return true;
    // explicit count fields
    const counts = ['attachments_count', 'attachment_count', 'files_count', 'file_count', 'attachmentsCount'];
    for (const k of counts) {
        if (typeof m[k] === 'number' && m[k] > 0) return true;
        if (typeof m[k] === 'string' && Number(m[k]) > 0) return true;
    }
    // boolean flag
    if (m.has_attachments === true || m.hasAttachments === true) return true;
    // fallback: quick scan for legacy placeholders or bare links in body (do not expose URLs)
    if (m.body && typeof m.body === 'string') {
        if (m.body.indexOf('[[attachment:') >= 0) return true;
        if (/https?:\/\/.+/.test(m.body) && m.body.indexOf('![](') === -1) return true;
    }
    return false;
}

function attachmentsCount(m) {
    if (!m) return 0;
    if (Array.isArray(m.attachments)) return m.attachments.length;
    if (m.attachments && Array.isArray(m.attachments.data)) return m.attachments.data.length;
    if (Array.isArray(m.uploads)) return m.uploads.length;
    if (m.uploads && Array.isArray(m.uploads.data)) return m.uploads.data.length;
    if (Array.isArray(m.files)) return m.files.length;
    if (Array.isArray(m.attachment_ids)) return m.attachment_ids.length;
    const numericFields = ['attachments_count', 'attachment_count', 'files_count', 'file_count', 'attachmentsCount'];
    for (const k of numericFields) {
        if (typeof m[k] === 'number') return m[k];
        if (typeof m[k] === 'string' && !isNaN(Number(m[k]))) return Number(m[k]);
    }
    if (m.has_attachments === true || m.hasAttachments === true) return 1;
    // best-effort: count legacy placeholder occurrences
    if (m.body && typeof m.body === 'string') {
        const matches = m.body.match(/\[\[attachment:\d+:?/g);
        return matches ? matches.length : 0;
    }
    return 0;
}

function onAddressSelect(u) {
    // go to the standalone compose page and prefill recipient via query param
    showAddress.value = false;
    try {
        router.get(route('messages.create', { to: u.id }));
    } catch (e) {
        // fallback: navigate via window.location
        window.location = route('messages.create', { to: u.id });
    }
}

function selectMessage(m) {
    selected.value = m;
    // optimistically mark as read for this recipient via API
    if (m && m.id) {
        axios.post(route('messages.read', m.id)).catch(() => {});

        // fetch authoritative payload (attachments may not be included in list response)
        (async () => {
            try {
                const res = await axios.get(`/api/debug/messages/${m.id}/payload`);
                if (res && res.data) {
                    // merge returned payload into selected (do not overwrite client-only fields)
                    selected.value = Object.assign({}, selected.value || {}, res.data);
                    try {
                        // update the corresponding row in listData so the table reflects attachments
                        const idx = listData.value.findIndex((x) => x.id === m.id);
                        if (idx >= 0) listData.value.splice(idx, 1, Object.assign({}, listData.value[idx], res.data));
                    } catch (e) {}
                }
            } catch (err) {
                try {
                    const res2 = await axios.get(`/api/debug/public/messages/${m.id}/payload`);
                    if (res2 && res2.data) {
                        selected.value = Object.assign({}, selected.value || {}, res2.data);
                        try {
                            const idx2 = listData.value.findIndex((x) => x.id === m.id);
                            if (idx2 >= 0) listData.value.splice(idx2, 1, Object.assign({}, listData.value[idx2], res2.data));
                        } catch (e) {}
                    }
                } catch (err2) {
                    // ignore
                }
            }
        })();
    }
}

function sanitize(html) {
    return DOMPurify.sanitize(html || '');
}

function pad(n) {
    return String(n).padStart(2, '0');
}

function formatDate(s) {
    if (!s) return '';
    try {
        const d = new Date(s);
        if (isNaN(d.getTime())) return s;
        const y = d.getFullYear();
        const m = d.getMonth() + 1;
        const day = d.getDate();
        const hh = d.getHours();
        const mm = d.getMinutes();
        const ss = d.getSeconds();
        return `${y}年${m}月${day}日 ${pad(hh)}時${pad(mm)}分${pad(ss)}秒`;
    } catch (e) {
        return s;
    }
}

function onPageLinkClick(rawUrl) {
    if (!rawUrl) return;
    try {
        const u = new URL(rawUrl, window.location.origin);
        const params = Object.fromEntries(u.searchParams.entries());
        // ensure sort params are preserved if present in current state
        if (sortBy.value) params.sort_by = sortBy.value;
        if (sortDir.value) params.sort_dir = sortDir.value;
        if (folder) params.folder = folder;
        router.get(route('messages.index'), params, { preserveState: false, preserveScroll: true });
    } catch (e) {
        // fallback: follow link directly
        window.location = rawUrl;
    }
}

function openShow(m) {
    if (!m || !m.id) return;
    try {
        // use Inertia to navigate to the show route
        router.get(route('messages.show', m.id));
    } catch (e) {
        // fallback
        window.location = route('messages.show', m.id);
    }
}

async function trashMessage(m) {
    if (!m || !m.id) return;
    // confirm with the user
    if (!confirm('このメッセージをゴミ箱に移動しますか？')) return;
    try {
        await axios.post(route('messages.trash', m.id));
        // remove from local listData so UI updates immediately
        const idx = listData.value.findIndex((x) => x.id === m.id);
        if (idx >= 0) listData.value.splice(idx, 1);
        // clear selection if it was the removed message
        if (selected.value && selected.value.id === m.id) selected.value = null;
    } catch (err) {
        console.error('trashMessage error', err);
        alert('メッセージの削除に失敗しました。');
    }
}

/**
 * Return true if the message is already trashed for the current user.
 */
function isTrashed(m) {
    try {
        const uid = currentUser?.id || (page.props?.auth?.user?.id ?? null);
        if (!uid || !m) return false;
        if (Array.isArray(m.recipients)) {
            return m.recipients.some((r) => {
                const rid = r.user_id ?? r.user?.id ?? null;
                return rid === uid && r.deleted_at;
            });
        }
        return false;
    } catch (e) {
        return false;
    }
}

/**
 * Handle delete button in index: if in trash (folder==='trash') or message already trashed,
 * perform permanent delete; otherwise move to trash (reuse trashMessage).
 */
async function onIndexDeleteClick(m) {
    if (!m || !m.id) return;
    try {
        if (folder === 'trash' || isTrashed(m)) {
            if (!confirm('このメッセージを完全に削除します。元に戻せません。よろしいですか？')) return;
            await axios.delete(route('messages.destroy', m.id));
            const idx = listData.value.findIndex((x) => x.id === m.id);
            if (idx >= 0) listData.value.splice(idx, 1);
            if (selected.value && selected.value.id === m.id) selected.value = null;
            return;
        }

        // fallback to move-to-trash
        await (async () => {
            // reuse existing trashMessage flow which already confirms and updates listData
            await trashMessage(m);
        })();
    } catch (err) {
        console.error('onIndexDeleteClick error', err);
        alert('メッセージの削除に失敗しました。');
    }
}
</script>
