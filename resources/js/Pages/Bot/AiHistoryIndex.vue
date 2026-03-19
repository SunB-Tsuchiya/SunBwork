<template>
    <AppLayout title="AI 会話履歴">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">AI 会話履歴</h2>
        </template>

        <div class="rounded bg-white p-6 shadow">
                    <!-- Admin: scope tabs -->
                    <div
                        v-if="$page.props.auth && ($page.props.auth.user?.user_role === 'admin' || $page.props.auth.user?.user_role === 'superadmin')"
                        class="mb-4"
                    >
                        <nav class="flex space-x-2">
                            <button
                                @click.prevent="fetchScope('mine')"
                                :class="scope === 'mine' ? 'bg-gray-200' : 'bg-white'"
                                class="rounded px-3 py-1"
                            >
                                自分の履歴
                            </button>
                            <button
                                @click.prevent="fetchScope('all')"
                                :class="scope === 'all' ? 'bg-gray-200' : 'bg-white'"
                                class="rounded px-3 py-1"
                            >
                                全ての履歴
                            </button>
                        </nav>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr class="text-left text-sm font-medium text-gray-700">
                                    <th class="px-4 py-2">ID</th>
                                    <th class="px-4 py-2">ユーザー名</th>
                                    <th class="px-4 py-2">タイトル</th>
                                    <th class="px-4 py-2">メッセージ数</th>
                                    <th class="px-4 py-2">更新</th>
                                    <th class="px-4 py-2">操作</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <tr
                                    v-for="conv in localConversations"
                                    :key="conv.id"
                                    class="cursor-pointer hover:bg-gray-50"
                                    @click="goToShow(conv.id)"
                                >
                                    <td class="px-4 py-2 text-sm text-gray-600">
                                        {{ conv.user_id || (conv.user && conv.user.id) || '' }}
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-600">
                                        {{ conv.user?.name || conv.user_name || '' }}
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-800">{{ conv.title || '（無題）' }}</td>
                                    <td class="px-4 py-2 text-sm text-gray-600">{{ conv.messages_count }}</td>
                                    <td class="px-4 py-2 text-sm text-gray-600">{{ formatDateJP(conv.updated_at) }}</td>
                                    <td class="px-4 py-2 text-sm">
                                        <button
                                            :disabled="deleting.has(conv.id)"
                                            @click.stop.prevent="confirmDelete(conv.id)"
                                            class="ml-2 inline-flex items-center rounded bg-red-600 px-3 py-1 text-white disabled:cursor-not-allowed disabled:opacity-60"
                                            title="会話を削除"
                                        >
                                            <template v-if="deleting.has(conv.id)">
                                                <svg class="mr-2 h-4 w-4 animate-spin" viewBox="0 0 24 24">
                                                    <circle
                                                        class="opacity-25"
                                                        cx="12"
                                                        cy="12"
                                                        r="10"
                                                        stroke="currentColor"
                                                        stroke-width="4"
                                                        fill="none"
                                                    ></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                                </svg>
                                                削除中
                                            </template>
                                            <template v-else>
                                                <svg
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    class="mr-2 h-4 w-4"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                    aria-hidden="true"
                                                >
                                                    <path
                                                        fill-rule="evenodd"
                                                        d="M6 2a1 1 0 00-1 1v1H3a1 1 0 100 2h14a1 1 0 100-2h-2V3a1 1 0 00-1-1H6zm2 6a1 1 0 012 0v6a1 1 0 11-2 0V8zm4 0a1 1 0 10-2 0v6a1 1 0 102 0V8z"
                                                        clip-rule="evenodd"
                                                    />
                                                </svg>
                                                削除
                                            </template>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- Pagination (rendered below the table) -->
                        <div v-if="pagination && pagination.last_page" class="mt-4 flex items-center justify-between">
                            <div class="text-sm text-gray-600">{{ pagination.total ? pagination.total + ' 件' : '' }}</div>
                            <div class="flex items-center space-x-2">
                                <button
                                    @click.prevent="goToPage(pagination.current_page - 1)"
                                    :disabled="pagination.current_page <= 1"
                                    class="rounded border px-3 py-1 disabled:opacity-50"
                                >
                                    前へ
                                </button>
                                <div class="text-sm">{{ pagination.current_page }} / {{ pagination.last_page }}</div>
                                <button
                                    @click.prevent="goToPage(pagination.current_page + 1)"
                                    :disabled="pagination.current_page >= pagination.last_page"
                                    class="rounded border px-3 py-1 disabled:opacity-50"
                                >
                                    次へ
                                </button>
                            </div>
                        </div>
                    </div>
        </div>
    </AppLayout>
</template>

<script setup>
import useToasts from '@/Composables/useToasts';
import AppLayout from '@/layouts/AppLayout.vue';
import { Inertia } from '@inertiajs/inertia';
import axios from 'axios';
import { computed, onMounted, ref } from 'vue';

const props = defineProps({
    conversations: { type: Object, required: true },
});

const { showToast } = useToasts();

// local reactive copy so we can mutate list without reloading
const localConversations = ref(Array.isArray(props.conversations?.data) ? [...props.conversations.data] : []);

// scope for admin: 'mine' or 'all'
const scope = ref('mine');

// whether current user is admin/superadmin
const isAdmin = computed(() => {
    try {
        const userRole = $page.props?.auth?.user?.user_role || null;
        return userRole === 'admin' || userRole === 'superadmin';
    } catch (e) {
        return false;
    }
});

// pagination state
const pagination = ref({});

async function fetchScope(s, page = 1) {
    if (!s) s = 'mine';
    scope.value = s;
    try {
        const res = await axios.get('/bot/history', { params: { scope: s, page } });
        const payload = res.data || {};
        localConversations.value = Array.isArray(payload.data) ? payload.data : [];

        if (payload.current_page) {
            pagination.value = {
                current_page: payload.current_page,
                last_page: payload.last_page,
                per_page: payload.per_page,
                total: payload.total,
                links: payload.links || null,
            };
        } else if (payload.meta && payload.meta.current_page) {
            pagination.value = {
                current_page: payload.meta.current_page,
                last_page: payload.meta.last_page,
                per_page: payload.meta.per_page,
                total: payload.meta.total,
                links: payload.meta.links || null,
            };
        } else {
            pagination.value = {};
        }
    } catch (err) {
        console.error(err);
        showToast('履歴の取得に失敗しました。', 'error');
    }
}

function goToPage(page) {
    if (!page || !pagination.value || page < 1 || page > (pagination.value.last_page || 1)) return;
    fetchScope(scope.value, page);
}

// deletion state
const deleting = new Set();
const pendingDeletes = new Map();
const DELETE_DELAY_MS = 7000;

onMounted(() => {
    try {
        const userRole = $page.props?.auth?.user?.user_role || null;
        if (userRole === 'admin' || userRole === 'superadmin') {
            scope.value = 'all';
            fetchScope('all', 1);
            return;
        }
    } catch (e) {
        // ignore
    }

    // non-admin: use server-rendered props
    localConversations.value = Array.isArray(props.conversations?.data) ? [...props.conversations.data] : [];
    if (props.conversations && typeof props.conversations === 'object') {
        if (props.conversations.current_page) {
            pagination.value = {
                current_page: props.conversations.current_page,
                last_page: props.conversations.last_page,
                per_page: props.conversations.per_page,
                total: props.conversations.total,
                links: props.conversations.links || null,
            };
        } else if (props.conversations.meta && props.conversations.meta.current_page) {
            pagination.value = {
                current_page: props.conversations.meta.current_page,
                last_page: props.conversations.meta.last_page,
                per_page: props.conversations.meta.per_page,
                total: props.conversations.meta.total,
                links: props.conversations.meta.links || null,
            };
        }
    }
});

// debug: log admin state and sample conversation user info
try {
    // don't throw in SSR
    if (typeof console !== 'undefined') {
        console.log('AiHistoryIndex init', {
            isAdmin: isAdmin.value,
            localCount: localConversations.value.length,
            sampleUser: localConversations.value[0]?.user || null,
        });
    }
} catch (e) {
    // ignore
}

function pad(n) {
    return String(n).padStart(2, '0');
}

function formatDateJP(iso) {
    if (!iso) return '';
    try {
        const d = new Date(iso);
        if (Number.isNaN(d.getTime())) return iso;
        const Y = d.getFullYear();
        const M = pad(d.getMonth() + 1);
        const D = pad(d.getDate());
        const h = pad(d.getHours());
        const m = pad(d.getMinutes());
        const s = pad(d.getSeconds());
        return `${Y}年${M}月${D}日 ${h}時${m}分${s}秒`;
    } catch (e) {
        return iso;
    }
}

function confirmDelete(id) {
    if (!window.confirm('この会話を削除しますか？ アタッチメントファイルは完全に削除されます。')) return;
    if (deleting.has(id) || pendingDeletes.has(id)) return;

    const idx = localConversations.value.findIndex((c) => c.id === id);
    if (idx === -1) return;
    const conv = localConversations.value[idx];

    // optimistic remove
    localConversations.value.splice(idx, 1);

    function undoHandler() {
        const entry = pendingDeletes.get(id);
        if (!entry) return;
        clearTimeout(entry.timer);
        const insertIndex = Math.min(entry.index, localConversations.value.length);
        localConversations.value.splice(insertIndex, 0, entry.conv);
        pendingDeletes.delete(id);
        showToast('削除を取り消しました。', 'success');
    }

    showToast('会話を削除しました。', 'success', DELETE_DELAY_MS, { label: '元に戻す', handler: undoHandler });

    const timer = setTimeout(() => {
        deleting.add(id);
        axios
            .delete(`/bot/history/${id}`)
            .then(() => showToast('完全に削除しました。', 'success'))
            .catch((err) => {
                console.error(err);
                const msg = err?.response?.data?.message || 'サーバ削除に失敗しました。';
                showToast(msg, 'error');
                const entry = pendingDeletes.get(id);
                if (entry) {
                    const insertIndex = Math.min(entry.index, localConversations.value.length);
                    localConversations.value.splice(insertIndex, 0, entry.conv);
                }
            })
            .finally(() => {
                deleting.delete(id);
                pendingDeletes.delete(id);
            });
    }, DELETE_DELAY_MS);

    pendingDeletes.set(id, { conv, timer, index: idx });
}

function goToShow(id) {
    try {
        Inertia.visit(`/bot/chat?load_conversation=${id}`);
    } catch (e) {
        // fallback
        window.location.href = `/bot/chat?load_conversation=${id}`;
    }
}
</script>
