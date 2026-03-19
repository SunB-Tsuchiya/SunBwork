<script setup>
import { ensureAttachmentUrl } from '@/Helpers/attachment';
import AppLayout from '@/layouts/AppLayout.vue';
import MessageArea from '@/Pages/Chat/MessageArea.vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

// Inertiaからpropsでroom, messagesを受け取る
const props = defineProps({
    room: { type: Object, default: () => ({ users: [] }) },
    messages: { type: Array, default: () => [] },
    // in some pages auth is passed as a prop; accept it optionally
    auth: { type: Object, required: false },
});

const page = usePage();
// page.props may expose user directly or under auth.user depending on app setup
const user = page.props?.auth?.user ?? page.props?.user ?? props.auth?.user ?? null;
const messages = ref(Array.isArray(props.messages) ? [...props.messages] : []);
const newMessage = ref('');
const newMessageRef = ref(null);
const isComposing = ref(false);
const isDragging = ref(false);
const isSending = ref(false);
const sendCooldown = ref(false);
const SEND_COOLDOWN_MS = 1000; // ms, prevent rapid repeated sends
const uploadProgress = ref({});
const fileModal = ref({ open: false, file: null });
const uploadInput = ref(null);
const uploadModalInput = ref(null);
const showFileModal = ref(false);

function openChatRoomFileModal() {
    showFileModal.value = true;
}
// rooms list (try page props first, fall back to props, otherwise fetch)
const rooms = ref(page.props?.rooms ?? props.rooms ?? []);

// local current room so we can switch client-side without full page navigation
const currentRoom = ref(props.room ? JSON.parse(JSON.stringify(props.room)) : { users: [] });

// manage echo subscription per-room
function unsubscribeEcho() {
    try {
        if (echoChannel.value) {
            echoChannel.value.stopListening && echoChannel.value.stopListening('ChatMessageSent');
            echoChannel.value = null;
        }
    } catch (e) {
        echoChannel.value = null;
    }
}

function subscribeEchoFor(roomId) {
    if (!window.Echo || !roomId) return;
    unsubscribeEcho();
    try {
        echoChannel.value = window.Echo.private('chatroom.' + roomId).listen('ChatMessageSent', (e) => {
            if (e.chat_room_id === currentRoom.value.id) {
                if (!messages.value.some((m) => m.id === e.id)) {
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
                    if ((e.user_id ?? e.from_user_id) !== user.id) {
                        scheduleMarkAsRead(e.id, 5000);
                    }
                    if ((e.user_id ?? e.from_user_id) !== user.id) {
                        const sender = e.user_name || (e.user ? e.user.name : '誰か');
                        flashMessage.value = `${sender} さんから新着メッセージです`;
                        if (flashTimeout.value) clearTimeout(flashTimeout.value);
                        flashTimeout.value = setTimeout(() => {
                            flashMessage.value = '';
                        }, 5000);
                    }
                    scrollToLatest();
                }
            }
        });
    } catch (err) {
        // ignore echo subscribe errors
    }
}

// select a room client-side: switch currentRoom, fetch messages, update URL and Echo
function selectRoom(r) {
    if (!r || !r.id) return;
    // set current room (shallow clone)
    currentRoom.value = r;
    messages.value = [];
    fetchMessages();
    scrollToLatest();
    subscribeEchoFor(r.id);
    try {
        window.history.pushState({}, '', `/chat/rooms/${r.id}`);
    } catch (e) {}
}

async function fetchRoomsList() {
    try {
        const res = await axios.get('/chat/rooms', { headers: { Accept: 'application/json' } });
        // expect array of rooms or { data: [...] }
        const payload = res.data;
        if (Array.isArray(payload)) {
            rooms.value = payload;
        } else if (payload && Array.isArray(payload.data)) {
            rooms.value = payload.data;
        } else {
            console.debug('fetchRoomsList: unexpected payload', payload);
        }
    } catch (e) {
        // ignore; leave rooms as-is
        console.debug('fetchRoomsList failed', e && e.message ? e.message : e);
    }
}

// ファイルアップロード処理
async function uploadFile(file, tempId) {
    if (!file || !currentRoom.value?.id) return null;
    const form = new FormData();
    form.append('file', file);
    try {
        const res = await axios.post(`/chat/rooms/${currentRoom.value.id}/messages`, form, {
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
                    // prefer the centralized builder which handles storage/ and attachments/ prefixes
                    data.file.streamUrl =
                        buildStreamUrl(data.file) ||
                        ensureUrlSafe(
                            '/chat/attachments?path=' +
                                encodeURIComponent(data.file.path || data.file.thumb_path || data.file.url || data.file.original_name || ''),
                        );
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

// Open native file picker for upload button
function openUploadDialog() {
    try {
        if (uploadInput.value && typeof uploadInput.value.click === 'function') {
            uploadInput.value.value = null; // reset
            uploadInput.value.click();
        }
    } catch (e) {
        console.warn('openUploadDialog failed', e);
    }
}

// Handle files selected via the upload input
async function onUploadInputChange(e) {
    const files = e && e.target && e.target.files ? Array.from(e.target.files) : [];
    if (!files.length) return;
    for (const f of files) {
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
        (async () => {
            const data = await uploadFile(f, tempId);
            const idx = messages.value.findIndex((m) => m._tmpId === tempId);
            if (data && data.id) {
                if (data.file && !data.file.streamUrl) {
                    data.file.streamUrl =
                        buildStreamUrl(data.file) ||
                        ensureUrlSafe(
                            '/chat/attachments?path=' +
                                encodeURIComponent(data.file.path || data.file.thumb_path || data.file.url || data.file.original_name || ''),
                        );
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
                    messages.value.splice(idx, 1, real);
                    if (real.type === 'file' && real.file) prefetchFilePreview(real.file);
                } else {
                    if (!messages.value.some((m) => m.id === data.id)) messages.value.push(real);
                }
                scheduleMarkAsRead(data.id, 5000);
            } else {
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
    // reset input
    try {
        if (uploadInput.value) uploadInput.value.value = null;
    } catch (e) {}
}

function openFileModal(file) {
    if (!file) return;
    // build proxy URL to stream through Laravel
    const streamUrl =
        buildStreamUrl(file) ||
        ensureUrlSafe('/chat/attachments?path=' + encodeURIComponent(file.path || file.url || file.thumb_path || file.original_name || ''));
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
        // delegate to centralized helper which yields streamable or safe URLs
        return ensureAttachmentUrl(candidate);
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
            // fetch rooms list if not provided by server
            if (!rooms.value || rooms.value.length === 0) {
                fetchRoomsList();
            }
            return null;
        }
        return null;
    } catch (e) {
        return null;
    }
}

// Ensure incoming candidate paths that look like 'attachments/...' are converted
// to safe URLs (prefer streaming endpoint). Also ensure simple relative paths
// are returned with a leading slash so the browser doesn't resolve them
// relative to the current page path.
function ensureUrlSafe(candidate) {
    if (!candidate || typeof candidate !== 'string') return null;
    const s = candidate.trim();
    if (!s) return null;
    // already absolute local path
    if (s.startsWith('/')) return s;
    // Delegate storage/attachments/chat/bot cases to the centralized helper
    if (s.startsWith('storage/') || s.startsWith('attachments/') || s.startsWith('chat/') || s.startsWith('bot/')) {
        return ensureAttachmentUrl(s);
    }
    // if it looks like a hostless URL (no scheme), avoid returning raw relative paths; prepend '/'
    return '/' + s;
}

// lightweight preview fetch for text files (non-blocking)
function prefetchFilePreview(file) {
    if (!file || !file.mime) return;
    try {
        const url = buildStreamUrl(file);

        // text preview (non-blocking)
        if (typeof file.mime === 'string' && file.mime.startsWith('text/')) {
            if (!url) return;
            fetch(url, { credentials: 'same-origin' })
                .then((r) => (r.ok ? r.text() : Promise.reject('nope')))
                .then((txt) => {
                    // keep only first ~800 chars to avoid huge content
                    file.previewText = txt && txt.length ? txt.slice(0, 800) : '';
                })
                .catch(() => {
                    file.previewText = null;
                });
            return;
        }

        // mark pdf preview availability
        if (file.mime === 'application/pdf') {
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
    // ensure rooms list is loaded for left column
    if (!rooms.value || rooms.value.length === 0) {
        fetchRoomsList();
    }
    scrollToLatest();
    // subscribe Echo for initial current room
    if (currentRoom.value && currentRoom.value.id) {
        subscribeEchoFor(currentRoom.value.id);
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
    if (!currentRoom.value || !Array.isArray(currentRoom.value.users)) return [];
    const self = currentRoom.value.users.find((u) => u.id === user.id);
    const others = currentRoom.value.users.filter((u) => u.id !== user.id).sort((a, b) => a.id - b.id);
    return self ? [self, ...others] : others;
});

function getAssignmentName(assignment_id) {
    if (!assignment_id) return '';
    if (typeof assignment_id === 'string') return assignment_id;
    if (!currentRoom.value || !Array.isArray(currentRoom.value.users)) return assignment_id;
    const member = currentRoom.value.users.find((u) => u.assignment_id === assignment_id);
    return member && member.assignment ? member.assignment : assignment_id;
}

function getRoomDisplayName() {
    if (!currentRoom.value) return '';
    if (currentRoom.value.type === 'private') {
        if (!currentRoom.value.name && Array.isArray(currentRoom.value.users)) {
            const other = currentRoom.value.users.find((u) => u.id !== user.id);
            return other ? other.name : '(相手なし)';
        }
        return currentRoom.value.name;
    }
    return currentRoom.value.name;
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
    if (!currentRoom.value?.id) return;
    try {
        const res = await axios.get(`/chat/rooms/${currentRoom.value.id}/messages`);
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

// 送信処理（アンチスパムと送信中フラグを含む）
async function sendMessage() {
    if (!newMessage.value.trim() || !currentRoom.value?.id) return;
    if (isSending.value || sendCooldown.value) return;
    isSending.value = true;
    try {
        const res = await axios.post(`/chat/rooms/${currentRoom.value.id}/messages`, {
            body: newMessage.value,
        });
        // 送信成功時は入力欄のみクリアし、messagesへのpushはEchoイベントに任せる
        if (res.data && res.data.id) {
            newMessage.value = '';
            // scrollToLatest()はEchoイベントで呼ばれるため不要
        }
        // start cooldown to prevent spam
        sendCooldown.value = true;
        setTimeout(() => {
            sendCooldown.value = false;
        }, SEND_COOLDOWN_MS);
    } catch (e) {
        alert('送信に失敗しました');
    } finally {
        isSending.value = false;
        // ensure textarea is resized after clearing
        autosizeTextarea();
    }
}

onMounted(() => {
    fetchMessages();
    scrollToLatest();
});

// autosize textarea for message input with max-height and overflow
const MAX_TEXTAREA_HEIGHT = 200; // px
function autosizeTextarea() {
    nextTick(() => {
        const ta = newMessageRef.value;
        if (!ta) return;
        try {
            // reset height to measure scrollHeight
            ta.style.height = 'auto';
            ta.style.overflowY = 'hidden';
            const newHeight = ta.scrollHeight + 2; // small buffer
            if (newHeight > MAX_TEXTAREA_HEIGHT) {
                ta.style.height = MAX_TEXTAREA_HEIGHT + 'px';
                ta.style.overflowY = 'auto';
            } else {
                ta.style.height = newHeight + 'px';
                ta.style.overflowY = 'hidden';
            }
        } catch (e) {
            // ignore
        }
    });
}

watch(newMessage, () => {
    autosizeTextarea();
});

onMounted(() => {
    // ensure textarea has correct initial height
    autosizeTextarea();
});

// handle Enter to send, Shift+Enter for newline, respect IME composition
function onTextareaKeydown(e) {
    if (isComposing.value) return;
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        // call sendMessage and ensure autosize will run after newMessage cleared
        try {
            sendMessage();
        } catch (err) {
            // ignore
        }
    }
}

function onCompositionStart() {
    isComposing.value = true;
}

function onCompositionEnd() {
    // small timeout to allow input event to update value
    isComposing.value = false;
    nextTick(() => autosizeTextarea());
}
</script>

<template>
    <AppLayout title="チャットルーム">
        <!-- hidden file input used by Upload button -->
        <input ref="uploadInput" type="file" style="display: none" @change="onUploadInputChange" multiple />
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
        <div class="mx-auto flex max-w-6xl flex-col gap-4 rounded bg-white p-4 shadow" style="height: calc(100vh - 140px)">
            <div class="mb-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        @click="openChatRoomFileModal"
                        class="inline-flex items-center rounded bg-green-600 px-3 py-2 text-white shadow-lg"
                    >
                        ファイルアップロード
                    </button>
                </div>
            </div>

            <div class="flex w-full flex-1 gap-4 overflow-hidden">
                <!-- 左カラム: ルーム一覧のみ (モバイルは非表示) -->
                <aside class="hidden w-64 flex-shrink-0 border-r border-gray-100 px-3 py-2 md:block">
                    <div class="mb-2 flex items-center justify-between">
                        <div class="text-sm font-medium">ルーム一覧</div>
                        <a href="/chat/rooms" class="text-xs text-blue-600 hover:underline">一覧へ</a>
                    </div>
                    <ul class="room-index space-y-1 text-sm leading-tight">
                        <li v-for="r in rooms" :key="r.id" class="rounded px-0 py-0">
                            <a
                                :href="`/chat/rooms/${r.id}`"
                                @click="onRoomClick($event, r)"
                                @keydown.enter.prevent="selectRoom(r)"
                                :class="[
                                    'flex w-full cursor-pointer items-center justify-between rounded px-2 py-2 transition-colors hover:bg-indigo-100 hover:text-gray-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-300',
                                    r.id === currentRoom.id ? 'bg-indigo-200 font-semibold text-gray-900' : '',
                                ]"
                                :aria-current="r.id === currentRoom.id ? 'true' : null"
                            >
                                <div class="truncate text-sm text-gray-800">
                                    {{ r.name || (r.type === 'private' ? r.users && r.users.find((u) => u.id !== user.id)?.name : '(無名)') }}
                                </div>
                                <span
                                    v-if="r.unread_count"
                                    class="ml-2 inline-flex items-center rounded-full bg-red-500 px-2 py-0.5 text-xs font-semibold text-white"
                                    >{{ r.unread_count }}</span
                                >
                            </a>
                        </li>
                    </ul>
                </aside>

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

                <main class="flex-1 overflow-auto px-4 py-2">
                    <MessageArea
                        :room="currentRoom"
                        :initialMessages="messages"
                        :user="user"
                        widthClass="w-full"
                        :openUploadModal="openChatRoomFileModal"
                        @request-open-upload="openChatRoomFileModal"
                    />
                </main>
            </div>
        </div>
        <!-- File upload modal (ChatRoom) -->
        <div v-if="showFileModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40">
            <div class="w-11/12 max-w-2xl rounded bg-white p-4 shadow-lg">
                <h3 class="mb-2 font-semibold">ファイルをアップロード</h3>
                <div>
                    <input ref="uploadModalInput" type="file" multiple @change="onUploadInputChange" class="cursor-pointer" />
                    <div class="mt-2 text-xs text-gray-600">ファイル選択後、アップロードは自動で開始されます。</div>
                </div>
                <div class="mt-3 flex justify-end gap-2">
                    <button @click="showFileModal = false" class="rounded border px-3 py-1">閉じる</button>
                </div>
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
    /* mobile-like width: keep messages narrow for readability */
    max-width: 420px;
}
@media (min-width: 768px) {
    .chat-bubble {
        max-width: 420px;
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

/* left index tweaks */
.room-index {
    font-size: 0.9rem; /* slightly smaller */
    line-height: 1.1; /* tighter */
}
.room-index .active {
    background: linear-gradient(90deg, rgba(99, 102, 241, 0.06), rgba(199, 210, 254, 0.03));
    border-radius: 6px;
    padding: 6px 8px;
}
</style>
