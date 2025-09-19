<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

// Inertiaからpropsでroom, messagesを受け取る
const props = defineProps({
    room: { type: Object, default: () => ({ users: [] }) },
    messages: { type: Array, default: () => [] },
});

const page = usePage();
const user = page.props.user;
const messages = ref(Array.isArray(props.messages) ? [...props.messages] : []);
const newMessage = ref('');
const isDragging = ref(false);
const uploadProgress = ref({});
const fileModal = ref({ open: false, file: null });

// ファイルアップロード処理
async function uploadFile(file, tempId) {
    if (!file || !props.room.id) return null;
    const form = new FormData();
    form.append('file', file);
    try {
        const res = await axios.post(`/chat/rooms/${props.room.id}/messages`, form, {
            headers: { 'Content-Type': 'multipart/form-data' },
            onUploadProgress: (progressEvent) => {
                const p = Math.round((progressEvent.loaded * 100) / (progressEvent.total || 1));
                // progress keyed by tempId to support duplicate filenames
                uploadProgress.value[tempId] = p;
                // update optimistic message progress if present
                const m = messages.value.find((x) => x._tmpId === tempId);
                if (m) m._progress = p;
            },
        });
        // clear progress
        delete uploadProgress.value[tempId];
        return res.data;
    } catch (e) {
        console.error('upload failed', e);
        delete uploadProgress.value[tempId];
        return null;
    }
}

function handleDrop(e) {
    e.preventDefault();
    isDragging.value = false;
    const dt = e.dataTransfer;
    if (!dt || !dt.files || dt.files.length === 0) return;
    for (const f of dt.files) {
        // create optimistic/temp message while uploading
        const tempId = 'tmp-' + Date.now() + '-' + Math.floor(Math.random() * 10000);
        const tempMsg = {
            _tmpId: tempId,
            id: tempId,
            user_id: user.id,
            user_name: user.name,
            message: `ファイルをアップロードしています： ${f.name}`,
            created_at: new Date().toISOString(),
            type: 'file',
            file: { original_name: f.name, mime: f.type || 'application/octet-stream', size: f.size, path: '' },
            uploading: true,
            _progress: 0,
            is_read: false,
        };
        messages.value.push(tempMsg);
        scrollToLatest();
        // perform upload and replace temp message when done
        (async () => {
            const data = await uploadFile(f, tempId);
            // find index of temp message
            const idx = messages.value.findIndex((m) => m._tmpId === tempId);
            if (data && data.id) {
                // ensure streamUrl exists on file meta
                if (data.file && !data.file.streamUrl) {
                    const p = data.file.path || data.file.thumb_path || data.file.url || data.file.original_name;
                    data.file.streamUrl = `/chat/attachments?path=${encodeURIComponent(p)}`;
                }
                let real = {
                    id: data.id,
                    user_id: data.user_id,
                    user_name: data.user_name,
                    message: data.message,
                    created_at: data.created_at,
                    type: data.type || 'file',
                    file: data.file || null,
                    is_read: false,
                };
                try {
                    real = normalizeMessage(real) || real;
                } catch (e) {
                    console.warn('normalizeMessage failed for upload', e);
                }
                if (idx >= 0) {
                    // replace temp with real
                    messages.value.splice(idx, 1, real);
                    if (real.type === 'file' && real.file) prefetchFilePreview(real.file);
                } else {
                    // push if not found
                    if (!messages.value.some((m) => m.id === data.id)) messages.value.push(real);
                }
                scheduleMarkAsRead(data.id, 5000);
            } else {
                // upload failed: mark temp as failed
                if (idx >= 0) {
                    const t = messages.value[idx];
                    t.uploading = false;
                    t._failed = true;
                    t.message = `アップロードに失敗しました： ${f.name}`;
                    t._progress = 0;
                }
            }
            scrollToLatest();
        })();
    }
}

function handleDragOver(e) {
    e.preventDefault();
    isDragging.value = true;
}
function handleDragLeave(e) {
    e.preventDefault();
    isDragging.value = false;
}

function openFileModal(file) {
    if (!file) return;
    // build proxy URL to stream through Laravel
    const streamUrl = `/chat/attachments?path=${encodeURIComponent(file.path || file.url || file.thumb_path || file.original_name)}`;
    fileModal.value.open = true;
    fileModal.value.file = { ...file, streamUrl };
    // for text files, prefetch content
    // accept any text/* mime (some servers append charset)
    if (file.mime && typeof file.mime === 'string' && file.mime.startsWith('text/')) {
        // include credentials so auth cookie/session is sent
        fetch(streamUrl, { credentials: 'same-origin' })
            .then((r) => (r.ok ? r.text() : Promise.reject('fetch-failed')))
            .then((txt) => {
                fileModal.value.file.text = txt;
            })
            .catch(() => {
                fileModal.value.file.text = null;
            });
    }
}
function closeFileModal() {
    fileModal.value.open = false;
    fileModal.value.file = null;
}

// build a stream URL for a file meta (always prefer internal path/thumb_path)
function buildStreamUrl(file) {
    if (!file) return null;
    // prefer explicit path or thumb_path
    const candidate = file.path || file.thumb_path || file.url || file.original_name;
    if (!candidate) return null;
    // if url like /storage/chat/xxx, convert to 'chat/xxx'
    if (typeof candidate === 'string') {
        // /storage/... URLs (local storage) -> strip and use internal stream
        if (candidate.startsWith('/storage/')) {
            return `/chat/attachments?path=${encodeURIComponent(candidate.replace(/^\/storage\//, ''))}`;
        }
        // full http(s) URL pointing to our host and /storage/ -> extract path
        try {
            const url = new URL(candidate, window.location.origin);
            if (url.pathname && url.pathname.startsWith('/storage/')) {
                return `/chat/attachments?path=${encodeURIComponent(url.pathname.replace(/^\/storage\//, ''))}`;
            }
        } catch (e) {
            // not a full URL, ignore
        }
        // if it already looks like a storage-relative path (chat/...), use it
        if (candidate.startsWith('chat/')) {
            return `/chat/attachments?path=${encodeURIComponent(candidate)}`;
        }
        // fallback: validate candidate and return only if safe
        try {
            const safe = sanitizeUrl(candidate);
            return safe || null;
        } catch (e) {
            return null;
        }
    }
    return null;
}

function sanitizeUrl(u) {
    if (!u || typeof u !== 'string') return null;
    const s = u.trim();
    if (s.startsWith('/')) return s;
    try {
        const parsed = new URL(s, window.location.origin);
        const proto = parsed.protocol.toLowerCase();
        if (proto === 'http:' || proto === 'https:' || proto === 'blob:') return parsed.toString();
        if (proto === 'data:') {
            if (/^data:image\//i.test(s)) return s;
            return null;
        }
        return null;
    } catch (e) {
        return null;
    }
}

// lightweight preview fetch for text files (non-blocking)
function prefetchFilePreview(file) {
    if (!file || !file.mime) return;
    try {
        const url = buildStreamUrl(file);
        if (!url) return;
        // text preview
        if (typeof file.mime === 'string' && file.mime.startsWith('text/')) {
            fetch(url, { credentials: 'same-origin' })
                .then((r) => (r.ok ? r.text() : Promise.reject('nope')))
                .then((txt) => {
                    // keep only first ~800 chars to avoid huge content
                    file.previewText = txt && txt.length ? txt.slice(0, 800) : '';
                })
                .catch(() => {
                    file.previewText = null;
                });
        }
        // optionally for pdf we could set a flag to indicate preview is available
        if (file.mime === 'application/pdf') {
            // only use url if it's safe
            file.previewPdf = sanitizeUrl(url) || null;
        }
    } catch (e) {
        // ignore
    }
}

// スクロール用ref
const messageArea = ref(null);
const lastMessageRef = ref(null);

// 最新メッセージが追加されたら下にスクロール
function scrollToLatest() {
    nextTick(() => {
        const area = messageArea.value;
        if (!area) return;
        const lastEl = (lastMessageRef.value && lastMessageRef.value) || area.querySelector('[data-last="true"]') || area.lastElementChild;
        if (!lastEl) {
            area.scrollTop = area.scrollHeight;
            return;
        }

        const doScroll = () => {
            try {
                // まずは scrollIntoView を試す（Chrome で効果があることが多い）
                if (typeof lastEl.scrollIntoView === 'function') {
                    lastEl.scrollIntoView({ block: 'end', inline: 'nearest', behavior: 'auto' });
                }
                // それでも完全に下に行かない場合は scrollTop を直接設定
                if (area.scrollTop + area.clientHeight < area.scrollHeight - 2) {
                    area.scrollTop = area.scrollHeight;
                }
            } catch (err) {
                try {
                    area.scrollTop = area.scrollHeight;
                } catch (e) {}
            }
        };

        // rAF -> setTimeout と順に再試行することで、Chrome のレンダリングタイミング問題を吸収
        doScroll();
        requestAnimationFrame(() => {
            doScroll();
            setTimeout(() => doScroll(), 50);
        });
    });
}

onMounted(() => {
    scrollToLatest();
});

watch(messages, () => {
    scrollToLatest();
});

// モーダル表示状態

// フラッシュメッセージ用
const flashMessage = ref('');
const flashTimeout = ref(null);

// 未読を遅延して既読登録するためのタイマー管理
const pendingReadTimeouts = ref(new Map()); // messageId -> timeoutId

import { onUnmounted } from 'vue';
const echoChannel = ref(null);

// --- message normalization helpers ---
function isUrlString(s) {
    if (!s || typeof s !== 'string') return false;
    try {
        // simple heuristic: starts with / or http(s) or contains /storage/
        if (s.startsWith('/') || s.startsWith('http://') || s.startsWith('https://')) return true;
        if (s.includes('/storage/')) return true;
    } catch (e) {}
    return false;
}

function normalizeMessage(msg) {
    if (!msg || typeof msg !== 'object') return msg;
    // if message already has file object, ensure streamUrl
    if (msg.type === 'file' && msg.file) {
        if (!msg.file.streamUrl) msg.file.streamUrl = buildStreamUrl(msg.file);
        try {
            prefetchFilePreview(msg.file);
        } catch (e) {}
        return msg;
    }

    // sometimes server returns a JSON string in message or body
    const content = msg.message || msg.body || msg.text || '';
    // if content looks like JSON with file/meta, try parse
    if (typeof content === 'string') {
        const trimmed = content.trim();
        // JSON blob
        if (trimmed.startsWith('{') && trimmed.endsWith('}')) {
            try {
                const parsed = JSON.parse(trimmed);
                if (parsed && (parsed.url || parsed.path || parsed.original_name || parsed.streamUrl || parsed.file)) {
                    // normalize to file message
                    msg.type = 'file';
                    msg.file = parsed.file || parsed;
                    if (!msg.file.original_name && msg.file.name) msg.file.original_name = msg.file.name;
                    if (!msg.file.streamUrl) msg.file.streamUrl = buildStreamUrl(msg.file);
                    msg.message = `ファイルがアップされました\n${msg.file.original_name || ''}`;
                    try {
                        prefetchFilePreview(msg.file);
                    } catch (e) {}
                    return msg;
                }
            } catch (e) {
                // not JSON
            }
        }

        // sometimes the server returns a raw url string in the message
        if (isUrlString(trimmed)) {
            // create a synthetic file meta
            msg.type = 'file';
            msg.file = msg.file || {};
            const candidate = trimmed;
            // if it's a /storage/ path, normalize to internal path
            if (candidate.startsWith('/storage/')) {
                msg.file.path = candidate.replace(/^\/storage\//, '');
            } else {
                msg.file.url = candidate;
            }
            msg.file.original_name = msg.file.original_name || (msg.file.url ? msg.file.url.split('/').pop() : 'file');
            msg.file.streamUrl = buildStreamUrl(msg.file);
            msg.message = `ファイルがアップされました\n${msg.file.original_name || ''}`;
            try {
                prefetchFilePreview(msg.file);
            } catch (e) {}
            return msg;
        }
    }

    return msg;
}

onMounted(() => {
    // 初期履歴をセット
    messages.value = props.messages
        ? props.messages.map((m) => {
              if (m.type === 'file' && m.file && !m.file.streamUrl) {
                  m.file.streamUrl = buildStreamUrl(m.file);
              }
              if (m.type === 'file' && m.file) prefetchFilePreview(m.file);
              return m;
          })
        : [];
    fetchMessages();
    scrollToLatest();
    if (props.room && props.room.id) {
        // Echo subscribe log removed
        echoChannel.value = window.Echo.private('chatroom.' + props.room.id).listen('ChatMessageSent', (e) => {
            // ChatMessageSent reception debug removed
            if (e.chat_room_id === props.room.id) {
                // すでに同じIDのメッセージが存在する場合は追加しない
                if (!messages.value.some((m) => m.id === e.id)) {
                    // pre-add messages.length log removed
                    let pushed = {
                        id: e.id,
                        user_id: e.user_id ?? e.from_user_id,
                        user_name: e.user_name || (e.user ? e.user.name : ''),
                        message: e.body || e.message,
                        created_at: e.created_at,
                        is_read: false,
                    };
                    try {
                        pushed = normalizeMessage(pushed) || pushed;
                    } catch (err) {
                        console.warn('normalizeMessage failed', err);
                    }
                    messages.value.push(pushed);
                    // 受信メッセージは5秒後に既読にする（自分のメッセージは除く）
                    if ((e.user_id ?? e.from_user_id) !== user.id) {
                        scheduleMarkAsRead(e.id, 5000);
                    }
                    // 新着メッセージ通知（自分以外の投稿のみ）
                    if ((e.user_id ?? e.from_user_id) !== user.id) {
                        const sender = e.user_name || (e.user ? e.user.name : '誰か');
                        flashMessage.value = `${sender} さんから新着メッセージです`;
                        if (flashTimeout.value) clearTimeout(flashTimeout.value);
                        flashTimeout.value = setTimeout(() => {
                            flashMessage.value = '';
                        }, 5000);
                    }
                    // post-add messages.length log removed
                    scrollToLatest();
                } else {
                    // duplicate message avoid log removed
                }
            } else {
                // ignored room id reception log removed
            }
        });
    }
});

onUnmounted(() => {
    if (echoChannel.value) {
        echoChannel.value.stopListening('ChatMessageSent');
        echoChannel.value = null;
    }
    // コンポーネント破棄時は未処理の既読タイマーをクリア
    for (const t of pendingReadTimeouts.value.values()) {
        clearTimeout(t);
    }
    pendingReadTimeouts.value.clear();
});

const showMembers = ref(false);

// メンバーリスト（自分が一番上、他はID順）
const sortedMembers = computed(() => {
    if (!props.room || !Array.isArray(props.room.users)) return [];
    const self = props.room.users.find((u) => u.id === user.id);
    const others = props.room.users.filter((u) => u.id !== user.id).sort((a, b) => a.id - b.id);
    return self ? [self, ...others] : others;
});

function getAssignmentName(assignment_id) {
    if (!assignment_id) return '';
    if (typeof assignment_id === 'string') return assignment_id;
    if (!props.room || !Array.isArray(props.room.users)) return assignment_id;
    const member = props.room.users.find((u) => u.assignment_id === assignment_id);
    return member && member.assignment ? member.assignment : assignment_id;
}

function getRoomDisplayName() {
    if (!props.room) return '';
    if (props.room.type === 'private') {
        if (!props.room.name && Array.isArray(props.room.users)) {
            const other = props.room.users.find((u) => u.id !== user.id);
            return other ? other.name : '(相手なし)';
        }
        return props.room.name;
    }
    return props.room.name;
}

// タイムスタンプ表示: 今日なら時刻のみ、今日以外は年月日を返す
function formatTimestamp(ts) {
    if (!ts) return '';
    const d = new Date(ts);
    if (isNaN(d)) return String(ts);
    const today = new Date();
    if (d.toDateString() === today.toDateString()) {
        // 例: 14:05
        return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }
    // 例: 2025/8/18 などロケール依存の短い日付
    return d.toLocaleDateString();
}

// メッセージ履歴取得（ルームベース）
async function fetchMessages() {
    if (!props.room.id) return;
    try {
        const res = await axios.get(`/chat/rooms/${props.room.id}/messages`);
        if (Array.isArray(res.data)) {
            // ensure file.streamUrl exists for each file message
            const mapped = res.data.map((m) => {
                try {
                    m = normalizeMessage(m) || m;
                } catch (e) {
                    /* ignore */
                }
                if (m.type === 'file' && m.file && !m.file.streamUrl) {
                    m.file.streamUrl = buildStreamUrl(m.file);
                }
                return m;
            });
            messages.value = mapped;
            // 未読メッセージは即時既読にしないで、表示してから遅延で既読登録する
            markAllAsRead(res.data);
        }
    } catch (e) {
        // debug placeholder removed
        messages.value = [];
    }
}

// 未読メッセージを既読にする
async function markAllAsRead(msgs) {
    if (!Array.isArray(msgs)) return;
    for (const msg of msgs) {
        // 自分以外のメッセージのみ遅延して既読APIを呼ぶ
        if (!msg.is_read && msg.user_id !== user.id) {
            scheduleMarkAsRead(msg.id, 5000);
        }
    }
}

// 既読API呼び出し
async function markAsRead(messageId) {
    try {
        await axios.post(`/api/chat/messages/${messageId}/read`);
        // 成功時はローカルのis_readもtrueに
        const target = messages.value.find((m) => m.id === messageId);
        if (target) target.is_read = true;
        // タイマーが残っていればクリア
        if (pendingReadTimeouts.value.has(messageId)) {
            clearTimeout(pendingReadTimeouts.value.get(messageId));
            pendingReadTimeouts.value.delete(messageId);
        }
    } catch (e) {
        const target = messages.value.find((m) => m.id === messageId);
        const userName = target ? target.user_name : '不明';
        const messageBody = target ? target.message : '';
        console.error(`markAsRead失敗: ユーザー=${userName}, メッセージ="${messageBody}", エラー=`, e);
    }
}

// 指定メッセージを遅延で既読登録する（重複タイマー防止）
function scheduleMarkAsRead(messageId, delay = 5000) {
    if (!messageId) return;
    if (pendingReadTimeouts.value.has(messageId)) return;
    const timeoutId = setTimeout(async () => {
        try {
            await markAsRead(messageId);
        } catch (e) {
            // markAsRead 内でログが出るためここでは特別な処理は不要
        }
        pendingReadTimeouts.value.delete(messageId);
    }, delay);
    pendingReadTimeouts.value.set(messageId, timeoutId);
}

// 送信処理
async function sendMessage() {
    if (!newMessage.value.trim() || !props.room.id) return;
    try {
        const res = await axios.post(`/chat/rooms/${props.room.id}/messages`, {
            body: newMessage.value,
        });
        // 送信成功時は入力欄のみクリアし、messagesへのpushはEchoイベントに任せる
        if (res.data && res.data.id) {
            newMessage.value = '';
            // scrollToLatest()はEchoイベントで呼ばれるため不要
        }
    } catch (e) {
        alert('送信に失敗しました');
    }
}

onMounted(() => {
    fetchMessages();
    scrollToLatest();
});
</script>

<template>
    <AppLayout title="チャットルーム">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">チャットルーム</h2>
        </template>
        <!-- フラッシュメッセージ -->
        <transition name="fade">
            <div
                v-if="flashMessage"
                class="fixed left-1/2 top-6 z-50 min-w-[280px] -translate-x-1/2 transform rounded border-2 border-purple-500 bg-white px-6 py-3 text-center text-lg font-semibold text-purple-800 shadow-lg"
            >
                {{ flashMessage }}
            </div>
        </transition>
        <div class="py-6">
            <div class="mx-auto flex max-w-4xl flex-col rounded bg-white p-6 shadow" style="height: calc(100vh - 160px)">
                <div class="mb-4">
                    <a href="/chat/rooms" class="text-blue-600 hover:underline">← ルーム一覧へ戻る</a>
                </div>
                <!-- ルーム名・相手名表示 -->
                <div class="mb-4 flex items-center">
                    <span class="text-lg font-bold">
                        <template v-if="props.room.type === 'private'">
                            {{ getRoomDisplayName() }}
                        </template>
                        <template v-else>
                            {{ props.room.name }}
                        </template>
                    </span>
                    <template v-if="props.room.type === 'group'">
                        <button class="ml-4 rounded bg-gray-200 px-3 py-1 text-sm hover:bg-gray-300" @click="showMembers = true">メンバー</button>
                    </template>
                </div>
                <!-- メンバーモーダル -->
                <div v-if="showMembers" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40">
                    <div class="min-w-[300px] rounded bg-white p-6 shadow-lg">
                        <div class="mb-4 flex items-center justify-between">
                            <span class="text-lg font-bold">メンバー一覧</span>
                            <button @click="showMembers = false" class="text-gray-500 hover:text-gray-800">×</button>
                        </div>
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">担当</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">名前</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="member in sortedMembers" :key="member.id">
                                    <td class="px-4 py-2">{{ getAssignmentName(member.assignment_id) }}</td>
                                    <td class="px-4 py-2">{{ member.name }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <div class="mt-4 flex justify-end">
                            <button @click="showMembers = false" class="rounded bg-blue-600 px-4 py-2 text-white">閉じる</button>
                        </div>
                    </div>
                </div>
                <div
                    ref="messageArea"
                    class="mb-4 flex-1 overflow-y-auto rounded border border-gray-200 bg-gradient-to-b from-white to-gray-50 p-4 shadow-inner"
                    @drop.prevent="handleDrop"
                    @dragover.prevent="handleDragOver"
                    @dragleave.prevent="handleDragLeave"
                >
                    <template v-if="messages.length === 0">
                        <div class="my-20 text-center text-gray-400">メッセージを入力して会話を開始してください</div>
                    </template>
                    <template v-else>
                        <div
                            v-for="(msg, idx) in messages"
                            :key="msg.id"
                            :ref="
                                (el) => {
                                    if (!lastMessageRef) return;
                                    if (idx === messages.length - 1) {
                                        lastMessageRef.value = el;
                                    } else if (lastMessageRef.value === el) {
                                        lastMessageRef.value = null;
                                    }
                                }
                            "
                            :data-last="idx === messages.length - 1"
                            class="mb-3 flex"
                            :class="msg.user_id === user.id ? 'justify-start' : 'justify-end'"
                        >
                            <div class="flex items-end" :class="msg.user_id === user.id ? 'flex-row' : 'flex-row-reverse'">
                                <div v-if="msg.user_name" :class="['flex-shrink-0', msg.user_id === user.id ? 'mr-3' : 'ml-3']">
                                    <div
                                        class="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-100 text-sm font-semibold text-indigo-800"
                                    >
                                        {{ (msg.user_name || '').charAt(0).toUpperCase() }}
                                    </div>
                                </div>
                                <div :class="msg.user_id === user.id ? 'chat-bubble-own' : 'chat-bubble-other'" class="chat-bubble relative">
                                    <div class="relative mb-1">
                                        <span
                                            v-if="!msg.is_read && msg.user_id !== user.id"
                                            class="text-xxs absolute -right-2 -top-2 rounded bg-purple-100 px-2 py-0.5 text-purple-700"
                                            >未読</span
                                        >
                                    </div>
                                    <div class="break-words text-sm leading-relaxed">
                                        <template v-if="msg.type === 'file' && msg.file">
                                            <div class="flex items-center gap-3">
                                                <div
                                                    class="relative flex h-36 w-36 items-center justify-center overflow-hidden rounded border bg-gray-100"
                                                >
                                                    <template v-if="msg.uploading">
                                                        <div class="flex h-full w-full flex-col items-center justify-center">
                                                            <svg
                                                                class="h-6 w-6 animate-spin text-gray-600"
                                                                xmlns="http://www.w3.org/2000/svg"
                                                                fill="none"
                                                                viewBox="0 0 24 24"
                                                            >
                                                                <circle
                                                                    class="opacity-25"
                                                                    cx="12"
                                                                    cy="12"
                                                                    r="10"
                                                                    stroke="currentColor"
                                                                    stroke-width="4"
                                                                ></circle>
                                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                                                            </svg>
                                                            <div class="mt-2 text-xs text-gray-600">ファイルをアップロードしています…</div>
                                                            <div class="mt-2 w-20 overflow-hidden rounded-full bg-gray-200">
                                                                <div class="h-1 bg-blue-600" :style="{ width: (msg._progress || 0) + '%' }"></div>
                                                            </div>
                                                        </div>
                                                    </template>
                                                    <template v-else>
                                                        <template v-if="msg.file.mime && msg.file.mime.startsWith('image/')">
                                                            <img
                                                                :src="msg.file.streamUrl || msg.file.url"
                                                                class="h-full w-full cursor-pointer object-cover"
                                                                @click="openFileModal(msg.file)"
                                                            />
                                                        </template>
                                                        <template v-else-if="msg.file.mime && msg.file.mime === 'application/pdf'">
                                                            <div
                                                                class="flex h-full w-full cursor-pointer items-center justify-center text-sm text-gray-700"
                                                                @click="openFileModal(msg.file)"
                                                            >
                                                                PDF プレビュー
                                                            </div>
                                                        </template>
                                                        <template v-else-if="msg.file.mime && msg.file.mime.startsWith('text/')">
                                                            <div
                                                                class="h-full w-full cursor-pointer p-2 text-sm text-gray-700"
                                                                @click="openFileModal(msg.file)"
                                                            >
                                                                <pre class="max-h-24 overflow-auto whitespace-pre-wrap text-xs">{{
                                                                    msg.file.original_name
                                                                }}</pre>
                                                            </div>
                                                        </template>
                                                        <template v-else>
                                                            <div class="px-2 text-xs text-gray-600">{{ msg.file.original_name }}</div>
                                                        </template>
                                                    </template>
                                                </div>
                                                <div class="flex-1">
                                                    <div class="font-medium">{{ msg.file.original_name }}</div>
                                                    <div class="text-sm text-gray-500">{{ (msg.file.size / 1024).toFixed(1) }} KB</div>
                                                    <div class="mt-2">
                                                        <a
                                                            :href="
                                                                msg.file.streamUrl ||
                                                                '/chat/attachments?path=' +
                                                                    encodeURIComponent(
                                                                        msg.file.path ||
                                                                            msg.file.url ||
                                                                            msg.file.thumb_path ||
                                                                            msg.file.original_name,
                                                                    )
                                                            "
                                                            target="_blank"
                                                            class="mr-3 text-blue-600 underline"
                                                            >開く</a
                                                        >
                                                        <a
                                                            :href="
                                                                msg.file.streamUrl ||
                                                                '/chat/attachments?path=' +
                                                                    encodeURIComponent(
                                                                        msg.file.path ||
                                                                            msg.file.url ||
                                                                            msg.file.thumb_path ||
                                                                            msg.file.original_name,
                                                                    )
                                                            "
                                                            :download="msg.file.original_name"
                                                            class="text-gray-600"
                                                            >ダウンロード</a
                                                        >
                                                    </div>
                                                </div>
                                            </div>
                                        </template>
                                        <template v-else>
                                            {{ msg.message }}
                                        </template>
                                    </div>
                                    <div class="mt-2 self-end text-right text-xs text-gray-400">{{ formatTimestamp(msg.created_at) }}</div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
                <form @submit.prevent="sendMessage" class="flex gap-2">
                    <input v-model="newMessage" class="flex-1 rounded border px-3 py-2" placeholder="メッセージを入力..." />
                    <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-white">送信</button>
                </form>
            </div>
        </div>
        <!-- File preview modal -->
        <div v-if="fileModal.open" class="z-60 fixed inset-0 flex items-center justify-center bg-black bg-opacity-60">
            <div class="mx-4 w-full max-w-3xl rounded bg-white p-4 shadow-lg">
                <div class="mb-4 flex items-center justify-between">
                    <div class="font-bold">{{ fileModal.file?.original_name || 'プレビュー' }}</div>
                    <div>
                        <a
                            :href="fileModal.file?.streamUrl || fileModal.file?.url"
                            :download="fileModal.file?.original_name"
                            class="mr-3 text-sm text-gray-700"
                            >ダウンロード</a
                        >
                        <button @click="closeFileModal" class="rounded border px-3 py-1 text-gray-700">閉じる</button>
                    </div>
                </div>
                <div class="max-h-[70vh] overflow-auto">
                    <template v-if="fileModal.file">
                        <template v-if="fileModal.file.mime && fileModal.file.mime.startsWith('image/')">
                            <img :src="fileModal.file.streamUrl || fileModal.file.url" class="h-auto w-full object-contain" />
                        </template>
                        <template v-else-if="fileModal.file.mime && fileModal.file.mime === 'application/pdf'">
                            <iframe :src="fileModal.file.streamUrl" class="h-[70vh] w-full" frameborder="0"></iframe>
                        </template>
                        <template v-else-if="fileModal.file.mime && fileModal.file.mime.startsWith('text/')">
                            <div class="max-h-[70vh] overflow-auto whitespace-pre-wrap p-4 font-mono text-sm text-gray-800">
                                {{ fileModal.file.text || '読み込み中...' }}
                            </div>
                        </template>
                        <template v-else>
                            <div class="p-8 text-center text-gray-700">プレビューできません。ダウンロードしてください。</div>
                        </template>
                    </template>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.5s;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

/* Chat bubble styles */
.chat-bubble-own {
    background: linear-gradient(180deg, #fbf7ff 0%, #f3ebff 100%);
    border: 1px solid rgba(128, 64, 255, 0.12);
    padding: 10px 14px;
    border-radius: 14px 14px 14px 4px;
    box-shadow:
        0 6px 18px rgba(99, 102, 241, 0.06),
        inset 0 1px 0 rgba(255, 255, 255, 0.6);
}
.chat-bubble-other {
    background: linear-gradient(180deg, #fff 0%, #f8fbff 100%);
    border: 1px solid rgba(59, 130, 246, 0.08);
    padding: 10px 14px;
    border-radius: 14px 14px 4px 14px;
    box-shadow:
        0 6px 18px rgba(14, 165, 233, 0.04),
        inset 0 1px 0 rgba(255, 255, 255, 0.6);
}

/* base bubble width: mobile 75% of container; on larger screens attempt to increase (2.5x) but cap to 95% to avoid overflow */
.chat-bubble {
    max-width: 75%;
}
@media (min-width: 768px) {
    .chat-bubble {
        max-width: min(95%, calc(75% * 2.5));
    }
}

.chat-bubble-own::after,
.chat-bubble-other::after {
    content: '';
    position: absolute;
    bottom: 8px;
    width: 12px;
    height: 12px;
    transform: rotate(45deg);
}
/* 吹き出し矢印をアイコン側へ寄せる */
.chat-bubble-own::after {
    left: -6px;
    background: linear-gradient(180deg, #fbf7ff 0%, #f3ebff 100%);
    border-left: 1px solid rgba(128, 64, 255, 0.12);
    border-bottom: 1px solid rgba(128, 64, 255, 0.06);
}
.chat-bubble-other::after {
    right: -6px;
    background: linear-gradient(180deg, #fff 0%, #f8fbff 100%);
    border-right: 1px solid rgba(59, 130, 246, 0.08);
    border-bottom: 1px solid rgba(59, 130, 246, 0.03);
}

.h-96 {
    height: 24rem;
}
</style>
