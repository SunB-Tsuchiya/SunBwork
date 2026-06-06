<template>
    <div class="flex flex-col" style="min-height: 480px;">

        <!-- ── 検索・絞り込みバー ── -->
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <input
                v-model="searchInput"
                type="text"
                placeholder="投稿者・返信者名 / 内容で検索"
                class="w-64 rounded border px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none"
                @keyup.enter="applySearch"
            />
            <button
                class="rounded bg-indigo-600 px-3 py-2 text-sm text-white hover:bg-indigo-700"
                @click="applySearch"
            >検索</button>
            <button
                class="rounded border px-3 py-2 text-sm text-gray-600 hover:bg-gray-50"
                @click="clearSearch"
            >クリア</button>

            <div class="flex items-center gap-2 ml-2">
                <label class="text-sm text-gray-600">年月:</label>
                <select
                    v-model="selectedMonth"
                    class="rounded border px-3 py-2 text-sm"
                    style="width: 9.5em"
                    @change="applySearch"
                >
                    <option value="">全期間</option>
                    <option v-for="mo in monthOptions" :key="mo.value" :value="mo.value">
                        {{ mo.label }}
                    </option>
                </select>
            </div>
        </div>

        <!-- ── 投稿一覧 ── -->
        <div v-if="loading" class="py-8 text-center text-sm text-gray-400">読込中...</div>

        <div v-else class="mb-4 flex-1 space-y-0.5 overflow-y-auto pr-1" style="max-height: 540px;">
            <div v-if="displayList.length === 0" class="py-10 text-center text-sm text-gray-400">
                {{ activeKeyword || selectedMonth ? '該当する投稿がありません' : 'まだ投稿はありません' }}
            </div>

            <template v-for="item in displayList" :key="item.id">
                <div
                    class="rounded border bg-white"
                    :class="item.depth === 0 ? 'border-gray-200 mt-3 first:mt-0' : 'border-indigo-100'"
                    :style="{ marginLeft: Math.min(item.depth, 4) * 16 + 'px' }"
                >
                    <div class="px-3 py-2">
                        <!-- ヘッダー行 -->
                        <div class="mb-1 flex items-center justify-between gap-1">
                            <span class="flex items-center gap-1 text-xs font-semibold" :class="roleColorClass(item.user_role)">
                                <span v-if="item.depth > 0" class="text-gray-400">↳</span>
                                {{ item.user_name || '不明' }}
                                <span class="ml-0.5 text-[10px] font-normal opacity-70">{{ roleLabel(item.user_role) }}</span>
                            </span>
                            <span class="flex-shrink-0 text-xs text-gray-400">
                                {{ item.created_at }}
                                <span v-if="item.updated_at !== item.created_at" class="text-gray-300">（編集済）</span>
                            </span>
                        </div>

                        <!-- 本文（通常 or 編集中） -->
                        <div v-if="editingId !== item.id">
                            <div class="whitespace-pre-wrap text-sm text-gray-800" v-html="highlight(item.body)"></div>
                        </div>
                        <div v-else>
                            <textarea
                                v-model="editBody"
                                rows="3"
                                class="w-full rounded border border-indigo-300 p-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"
                                @keydown.enter="e => enterKey(e, () => submitEdit(item))"
                                @keydown.escape="cancelEdit"
                            ></textarea>
                            <div class="mt-1 flex justify-end gap-2">
                                <button @click="cancelEdit" class="rounded bg-gray-200 px-3 py-1 text-xs text-gray-600 hover:bg-gray-300">キャンセル</button>
                                <button
                                    @click="submitEdit(item)"
                                    :disabled="!editBody.trim() || editSubmitting"
                                    class="rounded bg-indigo-600 px-3 py-1 text-xs text-white hover:bg-indigo-700 disabled:opacity-40"
                                >{{ editSubmitting ? '保存中...' : '保存' }}</button>
                            </div>
                        </div>

                        <!-- アクションボタン行 -->
                        <div class="mt-1 flex items-center justify-end gap-3">
                            <button
                                v-if="item.user_id === authUserId || isSuperAdmin"
                                @click="startEdit(item)"
                                class="text-xs text-gray-400 hover:text-indigo-600 hover:underline"
                            >編集</button>
                            <button
                                v-if="item.user_id === authUserId || isSuperAdmin"
                                @click="deletePost(item)"
                                class="text-xs text-gray-400 hover:text-red-600 hover:underline"
                            >削除</button>
                            <button
                                @click="toggleReply(item.id)"
                                class="text-xs hover:underline"
                                :class="replyTargetId === item.id ? 'text-gray-400' : 'text-indigo-500 hover:text-indigo-700'"
                            >{{ replyTargetId === item.id ? 'キャンセル' : '返信する' }}</button>
                        </div>
                    </div>

                    <!-- 返信フォーム -->
                    <div v-if="replyTargetId === item.id" class="border-t border-indigo-100 bg-indigo-50/60 px-3 py-2">
                        <textarea
                            ref="replyTextareaRef"
                            v-model="replyBody"
                            rows="2"
                            class="w-full rounded border border-gray-300 p-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"
                            placeholder="返信内容を入力... (Enter で送信、Shift+Enter で改行)"
                            @keydown.enter="e => enterKey(e, () => submitReply(item))"
                            @keydown.escape="toggleReply(null)"
                        ></textarea>
                        <div class="mt-1 flex justify-end gap-2">
                            <button @click="toggleReply(null)" class="rounded bg-gray-200 px-3 py-1 text-xs text-gray-600 hover:bg-gray-300">キャンセル</button>
                            <button
                                @click="submitReply(item)"
                                :disabled="!replyBody.trim() || replySubmitting"
                                class="rounded bg-indigo-600 px-3 py-1 text-xs text-white hover:bg-indigo-700 disabled:opacity-40"
                            >{{ replySubmitting ? '送信中...' : '返信する' }}</button>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <!-- ── 新規投稿フォーム ── -->
        <div class="border-t border-gray-200 pt-4">
            <p class="mb-1 text-xs font-medium text-gray-600">新規投稿 <span class="font-normal text-gray-400">（Enter で送信、Shift+Enter で改行）</span></p>
            <textarea
                v-model="newBody"
                rows="3"
                class="w-full rounded border border-gray-300 p-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"
                placeholder="投稿内容を入力..."
                @keydown.enter="e => enterKey(e, submitPost)"
            ></textarea>
            <button
                @click="submitPost"
                :disabled="!newBody.trim() || postSubmitting"
                class="mt-2 w-full rounded bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-40"
            >{{ postSubmitting ? '送信中...' : '投稿する' }}</button>
        </div>
    </div>
</template>

<script setup>
import axios from 'axios';
import { computed, nextTick, onMounted, ref } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps({
    teamId:      { type: Number, required: true },
    authUserId:  { type: Number, default: null },
    isSuperAdmin: { type: Boolean, default: false },
});

// ─── ロールラベル・カラー ─────────────────────────────────────────
const ROLE_LABEL = {
    superadmin: 'SA', admin: 'Admin', leader: 'Leader',
    coordinator: 'Co', clerk: 'Clerk', user: '',
};
const ROLE_COLOR = {
    superadmin: 'text-yellow-700', admin: 'text-red-700',
    leader: 'text-orange-700', coordinator: 'text-green-700',
    clerk: 'text-purple-700', user: 'text-blue-700',
};
function roleLabel(r) { return ROLE_LABEL[(r || '').toLowerCase()] || ''; }
function roleColorClass(r) { return ROLE_COLOR[(r || '').toLowerCase()] || 'text-gray-700'; }

// ─── 投稿データ ──────────────────────────────────────────────────
const flatPosts = ref([]);
const loading   = ref(false);

function getCsrf() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
}

// IME 確定後の Enter のみ実行（変換中・Shift+Enter は無視）
function enterKey(event, action) {
    if (event.isComposing || event.shiftKey) return;
    event.preventDefault();
    action();
}

async function fetchPosts() {
    loading.value = true;
    try {
        const res = await axios.get(route('team-rooms.memo-posts.index', { team: props.teamId }));
        flatPosts.value = res.data;
    } catch (e) {
        console.error('[TeamMemoBoard] fetch error', e);
    } finally {
        loading.value = false;
    }
}

// ─── 検索・絞り込み ──────────────────────────────────────────────
const searchInput   = ref('');
const activeKeyword = ref('');
const selectedMonth = ref('');

function applySearch() {
    activeKeyword.value = searchInput.value.trim();
}

function clearSearch() {
    searchInput.value   = '';
    activeKeyword.value = '';
    selectedMonth.value = '';
}

// 年月オプション（ルート投稿の created_at から生成）
const monthOptions = computed(() => {
    const months = new Set(
        flatPosts.value
            .filter(p => !p.parent_id)
            .map(p => p.created_at.slice(0, 7))  // "YYYY-MM"
    );
    return [...months]
        .sort((a, b) => b.localeCompare(a))
        .map(m => ({ value: m, label: m.replace('-', '年') + '月' }));
});

// キーワードハイライト
function highlight(text) {
    if (!activeKeyword.value) return escapeHtml(text);
    const kw = activeKeyword.value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    return escapeHtml(text).replace(
        new RegExp(kw, 'gi'),
        m => `<mark class="bg-yellow-200 rounded px-0.5">${m}</mark>`,
    );
}
function escapeHtml(s) {
    return String(s)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// ルートIDを辿る
function getRootId(post) {
    let p = post;
    const map = Object.fromEntries(flatPosts.value.map(x => [x.id, x]));
    while (p.parent_id && map[p.parent_id]) p = map[p.parent_id];
    return p.id;
}

// スレッド内の全ID（rootId から下を収集）
function getThreadIds(rootId) {
    const ids = new Set([rootId]);
    let changed = true;
    while (changed) {
        changed = false;
        for (const p of flatPosts.value) {
            if (p.parent_id && ids.has(p.parent_id) && !ids.has(p.id)) {
                ids.add(p.id);
                changed = true;
            }
        }
    }
    return ids;
}

// 表示対象の絞り込み済みフラットリスト
const filteredPosts = computed(() => {
    let posts = flatPosts.value;

    // 年月フィルター（ルート投稿の created_at で判定）
    if (selectedMonth.value) {
        const rootsInMonth = new Set(
            posts
                .filter(p => !p.parent_id && p.created_at.startsWith(selectedMonth.value))
                .map(p => p.id),
        );
        const threadIds = new Set();
        rootsInMonth.forEach(rid => getThreadIds(rid).forEach(id => threadIds.add(id)));
        posts = posts.filter(p => threadIds.has(p.id));
    }

    // キーワードフィルター（投稿本文 + 投稿者名）
    if (activeKeyword.value) {
        const kw = activeKeyword.value.toLowerCase();
        const matchIds = new Set(
            posts
                .filter(p => p.body.toLowerCase().includes(kw) || (p.user_name || '').toLowerCase().includes(kw))
                .map(p => p.id),
        );
        // マッチした投稿のスレッド全体を含める
        const threadIds = new Set();
        matchIds.forEach(id => {
            const post = posts.find(p => p.id === id);
            if (post) getThreadIds(getRootId(post)).forEach(tid => threadIds.add(tid));
        });
        posts = posts.filter(p => threadIds.has(p.id));
    }

    return posts;
});

// ツリー展開（ルート投稿は新しい順、返信は古い順）
const displayList = computed(() => {
    const posts = filteredPosts.value;
    const map   = {};
    posts.forEach(p => { map[p.id] = { ...p, _children: [] }; });
    const roots = [];
    posts.forEach(p => {
        if (p.parent_id && map[p.parent_id]) map[p.parent_id]._children.push(map[p.id]);
        else if (map[p.id]) roots.push(map[p.id]);
    });
    // ルート投稿: 新しい順
    roots.sort((a, b) => b.created_at.localeCompare(a.created_at));

    const result = [];
    function traverse(items, depth) {
        // 返信: 古い順
        const sorted = depth === 0 ? items : [...items].sort((a, b) => a.created_at.localeCompare(b.created_at));
        sorted.forEach(item => {
            result.push({ ...item, depth });
            traverse(item._children, depth + 1);
        });
    }
    traverse(roots, 0);
    return result;
});

// ─── 新規投稿 ────────────────────────────────────────────────────
const newBody        = ref('');
const postSubmitting = ref(false);

async function submitPost() {
    if (!newBody.value.trim()) return;
    postSubmitting.value = true;
    try {
        const res = await axios.post(
            route('team-rooms.memo-posts.store', { team: props.teamId }),
            { body: newBody.value.trim() },
            { headers: { 'X-CSRF-TOKEN': getCsrf() } },
        );
        flatPosts.value.unshift(res.data);
        newBody.value = '';
    } catch (e) {
        console.error('[TeamMemoBoard] submitPost error', e);
    } finally {
        postSubmitting.value = false;
    }
}

// ─── 返信 ────────────────────────────────────────────────────────
const replyTargetId    = ref(null);
const replyBody        = ref('');
const replySubmitting  = ref(false);
const replyTextareaRef = ref(null);

function toggleReply(id) {
    if (replyTargetId.value === id) {
        replyTargetId.value = null;
        replyBody.value = '';
    } else {
        replyTargetId.value = id;
        replyBody.value = '';
        nextTick(() => {
            const el = Array.isArray(replyTextareaRef.value) ? replyTextareaRef.value[0] : replyTextareaRef.value;
            el?.focus();
        });
    }
}

async function submitReply(targetItem) {
    if (!replyBody.value.trim()) return;
    replySubmitting.value = true;
    try {
        const res = await axios.post(
            route('team-rooms.memo-posts.store', { team: props.teamId }),
            { body: replyBody.value.trim(), parent_id: targetItem.id },
            { headers: { 'X-CSRF-TOKEN': getCsrf() } },
        );
        flatPosts.value.push(res.data);
        replyBody.value = '';
        replyTargetId.value = null;
    } catch (e) {
        console.error('[TeamMemoBoard] submitReply error', e);
    } finally {
        replySubmitting.value = false;
    }
}

// ─── 編集 ────────────────────────────────────────────────────────
const editingId     = ref(null);
const editBody      = ref('');
const editSubmitting = ref(false);

function startEdit(item) {
    editingId.value = item.id;
    editBody.value  = item.body;
    replyTargetId.value = null;
}
function cancelEdit() {
    editingId.value = null;
    editBody.value  = '';
}

async function submitEdit(item) {
    if (!editBody.value.trim()) return;
    editSubmitting.value = true;
    try {
        const res = await axios.put(
            route('team-rooms.memo-posts.update', { team: props.teamId, memoPost: item.id }),
            { body: editBody.value.trim() },
            { headers: { 'X-CSRF-TOKEN': getCsrf() } },
        );
        const idx = flatPosts.value.findIndex(p => p.id === item.id);
        if (idx !== -1) flatPosts.value[idx] = res.data;
        editingId.value = null;
        editBody.value  = '';
    } catch (e) {
        console.error('[TeamMemoBoard] submitEdit error', e);
    } finally {
        editSubmitting.value = false;
    }
}

// ─── 削除 ────────────────────────────────────────────────────────
async function deletePost(item) {
    if (!confirm('この投稿を削除しますか？（返信も一緒に削除されます）')) return;
    try {
        await axios.delete(
            route('team-rooms.memo-posts.destroy', { team: props.teamId, memoPost: item.id }),
            { headers: { 'X-CSRF-TOKEN': getCsrf() } },
        );
        const descendants = new Set();
        function collect(id) {
            descendants.add(id);
            flatPosts.value.filter(p => p.parent_id === id).forEach(p => collect(p.id));
        }
        collect(item.id);
        flatPosts.value = flatPosts.value.filter(p => !descendants.has(p.id));
    } catch (e) {
        console.error('[TeamMemoBoard] deletePost error', e);
    }
}

onMounted(fetchPosts);
</script>
