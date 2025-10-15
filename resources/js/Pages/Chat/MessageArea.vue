<script setup>
import axios from 'axios';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    room: { type: Object, required: true },
    initialMessages: { type: Array, default: () => [] },
    user: { type: Object, required: true },
    // optional: when provided the parent owns messages and MessageArea will only render them (no fetch/echo)
    externalMessages: { type: Array, default: null },
    // optional: when true, parent indicates the AI is currently generating a reply
    aiWorking: { type: Boolean, default: false },
    // optional hooks (parent may provide custom send/upload/open logic)
    sendFn: { type: Function, required: false },
    uploadFn: { type: Function, required: false },
    openAttachmentFn: { type: Function, required: false },
    // optional: handler to request deletion of an uploaded file (meta object)
    deleteAttachmentFn: { type: Function, required: false },
    // optional: handler to request the AI reference a file (meta object)
    referenceFn: { type: Function, required: false },
    // optional: handler passed by parent to request summarization of a file (meta object)
    summarizeFn: { type: Function, required: false },
    renderMarkdownFn: { type: Function, required: false },
});

// local state
const messages = ref(Array.isArray(props.initialMessages) ? [...props.initialMessages] : []);
// when externalMessages is provided, render that instead of internal messages
const displayedMessagesRaw = computed(() =>
    props.externalMessages && Array.isArray(props.externalMessages) ? props.externalMessages : messages.value,
);
// normalize a cloned copy for safe rendering (don't mutate parent props)
const displayedMessages = computed(() => {
    return (displayedMessagesRaw.value || []).map((m) => {
        try {
            const clone = JSON.parse(JSON.stringify(m));
            return normalizeMessage(clone) || clone;
        } catch (e) {
            try {
                const nm = normalizeMessage(m) || m;
                return nm;
            } catch (ee) {
                return m;
            }
        }
    });
});

// Post-process displayedMessages to ensure template-friendly fields exist
watch(
    displayedMessages,
    (list) => {
        for (const msg of list) {
            try {
                // ensure message body is available under `message`
                msg.message = msg.message || msg.body || msg.text || '';
                // ensure user identifiers are present for alignment/avatars
                if (msg.user_id === undefined || msg.user_id === null) {
                    if (msg.user === '自分') msg.user_id = props.user?.id ?? null;
                    else if (msg.user === 'AI') msg.user_id = msg.user_id ?? null;
                }
                msg.user_name = msg.user_name || msg.user_name || msg.user || (msg.user_id === props.user?.id ? props.user?.name : msg.user_name);
                // created_at fallback for temporary messages
                if (!msg.created_at && (msg._tmpId || msg.isTemp)) msg.created_at = new Date().toISOString();
            } catch (e) {}
        }
    },
    { immediate: true, deep: true },
);
const newMessage = ref('');
const newMessageRef = ref(null);
const isComposing = ref(false);
const isSending = ref(false);
const sendCooldown = ref(false);
const SEND_COOLDOWN_MS = 1000;
const uploadProgress = ref({});
const fileModal = ref({ open: false, file: null });
const messageArea = ref(null);
const lastMessageRef = ref(null);
const pendingReadTimeouts = ref(new Map());
const echoChannel = ref(null);

// helpers (sanitization & stream url builders)
function buildStreamUrl(file) {
    if (!file) return null;
    const candidate = file.path || file.thumb_path || file.url || file.original_name;
    if (!candidate) return null;
    if (typeof candidate === 'string') {
        // If candidate references the public storage path, map to the appropriate stream endpoint
        if (candidate.startsWith('/storage/')) {
            const inner = candidate.replace(/^\/storage\//, '');
            if (inner.startsWith('bot/')) return `/bot/attachments?path=${encodeURIComponent(inner)}`;
            return `/chat/attachments?path=${encodeURIComponent(inner)}`;
        }
        try {
            const url = new URL(candidate, window.location.origin);
            if (url.pathname && url.pathname.startsWith('/storage/')) {
                const inner = url.pathname.replace(/^\/storage\//, '');
                if (inner.startsWith('bot/')) return `/bot/attachments?path=${encodeURIComponent(inner)}`;
                return `/chat/attachments?path=${encodeURIComponent(inner)}`;
            }
        } catch (e) {}
        // If candidate explicitly names a bot path, use the bot stream endpoint
        if (candidate.startsWith('bot/')) return `/bot/attachments?path=${encodeURIComponent(candidate)}`;
        if (candidate.startsWith('chat/')) return `/chat/attachments?path=${encodeURIComponent(candidate)}`;
        // If candidate is an absolute local path, return as-is (it should already be prefixed)
        if (candidate.startsWith('/')) return candidate;
        // Otherwise return as-is (could be a full URL or relative string)
        return candidate;
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

// Ensure a local path has a leading slash when used in hrefs
function ensureLeadingSlashIfLocal(u) {
    if (!u || typeof u !== 'string') return u;
    const s = u.trim();
    if (s.startsWith('http://') || s.startsWith('https://') || s.startsWith('blob:') || s.startsWith('data:')) return s;
    // Ensure local paths are properly encoded for non-ASCII filenames
    const local = s.startsWith('/') ? s : '/' + s;
    try {
        // encodeURI preserves existing % escapes and encodes non-ASCII characters
        const parts = local
            .split('/')
            .map((p) => encodeURIComponent(decodeURIComponent(p)))
            .join('/');
        // preserve leading slash
        return parts.startsWith('/') ? parts : '/' + parts;
    } catch (e) {
        return local;
    }
}

// Build a safe href for attachments. preferStream=true will try streamUrl first.
function attachmentHref(file, preferStream = true) {
    if (!file) return null;
    const tryStream = file.streamUrl || null;
    const tryUrl = file.url || null;
    // Prefer a public /storage URL when we have a storage path
    try {
        // If file.path exists, map it to the public storage URL and return an absolute, encoded path
        if (file.path) {
            // common case: file.path is like 'bot/xxx.txt'
            const storagePath = '/storage/' + file.path.replace(/^\//, '');
            return ensureLeadingSlashIfLocal(storagePath);
        }

        // If file.url is provided, normalize storage URLs to local /storage/ path
        if (tryUrl) {
            const s = sanitizeUrl(tryUrl) || tryUrl;
            try {
                const parsed = new URL(s, window.location.origin);
                // If the URL's pathname contains /storage/, extract that portion and return a local storage path
                if (parsed.pathname && parsed.pathname.includes('/storage/')) {
                    const after = parsed.pathname.substring(parsed.pathname.indexOf('/storage/') + '/storage/'.length);
                    const storagePath = '/storage/' + after.replace(/^\//, '');
                    return ensureLeadingSlashIfLocal(storagePath);
                }
            } catch (e) {
                // not an absolute URL, fall back
            }
            // Always normalize the candidate via ensureLeadingSlashIfLocal.
            // This will add a leading slash for relative paths (so 'bot/..' -> '/bot/...')
            // and will return external http(s) URLs unchanged.
            return ensureLeadingSlashIfLocal(s);
        }

        // If streamer URL is present, use it as a fallback
        if (preferStream && tryStream) {
            const s = sanitizeUrl(tryStream) || tryStream;
            return ensureLeadingSlashIfLocal(s);
        }

        // Last resort: try to build a stream query using original_name
        if (file.original_name) return '/chat/attachments?path=' + encodeURIComponent(file.original_name);
    } catch (e) {
        // fallback to existing heuristics
        if (preferStream && tryStream) return ensureLeadingSlashIfLocal(sanitizeUrl(tryStream) || tryStream);
        if (tryUrl) return ensureLeadingSlashIfLocal(sanitizeUrl(tryUrl) || tryUrl);
        if (file.path) return '/chat/attachments?path=' + encodeURIComponent(file.path);
        if (file.original_name) return '/chat/attachments?path=' + encodeURIComponent(file.original_name);
    }
    return null;
}

function prefetchFilePreview(file) {
    if (!file || !file.mime) return;
    try {
        const url = buildStreamUrl(file);
        if (!url) return;
        if (typeof file.mime === 'string' && file.mime.startsWith('text/')) {
            fetch(url, { credentials: 'same-origin' })
                .then((r) => (r.ok ? r.text() : Promise.reject('nope')))
                .then((txt) => {
                    file.previewText = txt && txt.length ? txt.slice(0, 800) : '';
                })
                .catch(() => {
                    file.previewText = null;
                });
        }
        if (file.mime === 'application/pdf') {
            file.previewPdf = sanitizeUrl(url) || null;
        }
    } catch (e) {}
}

function normalizeMessage(msg) {
    if (!msg || typeof msg !== 'object') return msg;
    if (msg.type === 'file' && msg.file) {
        // If attachment_id is present but file lacks path/url, fetch metadata
        if (msg.file.attachment_id && !msg.file.path && !msg.file.url) {
            // asynchronous fetch (best-effort, don't block rendering)
            (async () => {
                try {
                    const res = await axios.get(`/attachments/${msg.file.attachment_id}`);
                    if (res && res.data) {
                        const a = res.data;
                        // merge fields
                        msg.file.path = msg.file.path || a.path || null;
                        msg.file.url = msg.file.url || (a.path ? '/storage/' + a.path : null) || a.url || null;
                        msg.file.mime = msg.file.mime || a.mime_type || a.mime || null;
                        msg.file.size = msg.file.size || a.size || null;
                        if (!msg.file.streamUrl) msg.file.streamUrl = buildStreamUrl(msg.file);
                        try {
                            prefetchFilePreview(msg.file);
                        } catch (e) {}
                    }
                } catch (e) {
                    // ignore fetch errors
                }
            })();
        }
        if (!msg.file.streamUrl) msg.file.streamUrl = buildStreamUrl(msg.file);
        try {
            prefetchFilePreview(msg.file);
        } catch (e) {}
        return msg;
    }
    const content = msg.message || msg.body || msg.text || '';
    if (typeof content === 'string') {
        const trimmed = content.trim();
        if (trimmed.startsWith('{') && trimmed.endsWith('}')) {
            try {
                const parsed = JSON.parse(trimmed);
                if (parsed && (parsed.url || parsed.path || parsed.original_name || parsed.streamUrl || parsed.file)) {
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
            } catch (e) {}
        }
        // simple url heuristics
        if (trimmed.startsWith('/') || trimmed.startsWith('http://') || trimmed.startsWith('https://') || trimmed.includes('/storage/')) {
            msg.type = 'file';
            msg.file = msg.file || {};
            const candidate = trimmed;
            if (candidate.startsWith('/storage/')) msg.file.path = candidate.replace(/^\/storage\//, '');
            else msg.file.url = candidate;
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

function formatDateForDisplay(msg) {
    if (!msg) return '';
    const raw = msg.created_at || msg.createdAt || msg.created || null;
    if (!raw) {
        // for temporary messages, show now
        if (msg._tmpId) return new Date().toLocaleString();
        return '';
    }
    try {
        const d = new Date(raw);
        if (isNaN(d.getTime())) return '';
        return d.toLocaleString();
    } catch (e) {
        return '';
    }
}

function isOwnMessage(msg) {
    if (!msg) return false;
    // If parent uses 'user' as '自分'/'AI'
    if (msg.user === '自分' || msg.user === 'me' || msg.user === 'you') return true;
    // fallback to id match when available
    try {
        if (props.user && props.user.id && msg.user_id && msg.user_id === props.user.id) return true;
    } catch (e) {}
    return false;
}

// scrolling
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
                if (typeof lastEl.scrollIntoView === 'function') lastEl.scrollIntoView({ block: 'end', inline: 'nearest', behavior: 'auto' });
                if (area.scrollTop + area.clientHeight < area.scrollHeight - 2) area.scrollTop = area.scrollHeight;
            } catch (err) {
                try {
                    area.scrollTop = area.scrollHeight;
                } catch (e) {}
            }
        };
        doScroll();
        requestAnimationFrame(() => {
            doScroll();
            setTimeout(() => doScroll(), 50);
        });
    });
}

// echo subscription
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
            if (e.chat_room_id === props.room.id) {
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
                    // if parent supplies messages, don't mutate internal list
                    if (!props.externalMessages) messages.value.push(pushed);
                    if ((e.user_id ?? e.from_user_id) !== props.user.id) scheduleMarkAsRead(e.id, 5000);
                    scrollToLatest();
                }
            }
        });
    } catch (err) {}
}

watch(
    () => props.room && props.room.id,
    (rid) => {
        // room changed: reset and fetch (only when internal messages are used)
        if (!props.externalMessages) {
            messages.value = [];
            if (rid) {
                fetchMessages();
                subscribeEchoFor(rid);
            } else {
                unsubscribeEcho();
            }
        }
    },
);

async function fetchMessages() {
    if (!props.room?.id) return;
    if (props.externalMessages) return; // parent owns messages
    try {
        const res = await axios.get(`/chat/rooms/${props.room.id}/messages`);
        if (Array.isArray(res.data)) {
            const mapped = res.data.map((m) => {
                try {
                    m = normalizeMessage(m) || m;
                } catch (e) {}
                if (m.type === 'file' && m.file && !m.file.streamUrl) m.file.streamUrl = buildStreamUrl(m.file);
                return m;
            });
            messages.value = mapped;
            markAllAsRead(res.data);
        }
    } catch (e) {
        messages.value = [];
    }
}

function markAllAsRead(msgs) {
    if (!Array.isArray(msgs)) return;
    for (const msg of msgs) {
        if (!msg.is_read && msg.user_id !== props.user.id) scheduleMarkAsRead(msg.id, 5000);
    }
}

async function markAsRead(messageId) {
    try {
        await axios.post(`/api/chat/messages/${messageId}/read`);
        const target = messages.value.find((m) => m.id === messageId);
        if (target) target.is_read = true;
        if (pendingReadTimeouts.value.has(messageId)) {
            clearTimeout(pendingReadTimeouts.value.get(messageId));
            pendingReadTimeouts.value.delete(messageId);
        }
    } catch (e) {
        console.error('markAsRead failed', e);
    }
}

function scheduleMarkAsRead(messageId, delay = 5000) {
    if (!messageId) return;
    if (pendingReadTimeouts.value.has(messageId)) return;
    const timeoutId = setTimeout(async () => {
        try {
            await markAsRead(messageId);
        } catch (e) {}
        pendingReadTimeouts.value.delete(messageId);
    }, delay);
    pendingReadTimeouts.value.set(messageId, timeoutId);
}

// file upload
async function uploadFile(file, tempId) {
    if (!file) return null;
    if (props.uploadFn) {
        try {
            const meta = await props.uploadFn(file);
            return meta || null;
        } catch (e) {
            return null;
        }
    }
    if (!props.room?.id) return null;
    const form = new FormData();
    form.append('file', file);
    try {
        const res = await axios.post(`/chat/rooms/${props.room.id}/messages`, form, {
            headers: { 'Content-Type': 'multipart/form-data' },
            onUploadProgress: (progressEvent) => {
                const p = Math.round((progressEvent.loaded * 100) / (progressEvent.total || 1));
                uploadProgress.value[tempId] = p;
                const m = messages.value.find((x) => x._tmpId === tempId);
                if (m) m._progress = p;
            },
        });
        delete uploadProgress.value[tempId];
        return res.data;
    } catch (e) {
        delete uploadProgress.value[tempId];
        return null;
    }
}

function handleDrop(e) {
    e.preventDefault();
    const dt = e.dataTransfer;
    if (!dt || !dt.files || dt.files.length === 0) return;
    for (const f of dt.files) {
        const tempId = 'tmp-' + Date.now() + '-' + Math.floor(Math.random() * 10000);
        const tempMsg = {
            _tmpId: tempId,
            id: tempId,
            user_id: props.user.id,
            user_name: props.user.name,
            message: `ファイルをアップロードしています： ${f.name}`,
            created_at: new Date().toISOString(),
            type: 'file',
            file: { original_name: f.name, mime: f.type || 'application/octet-stream', size: f.size, path: '' },
            uploading: true,
            _progress: 0,
            is_read: false,
        };
        // if parent provides externalMessages, parent should handle UI updates; otherwise show optimistic temp message
        if (!props.externalMessages) messages.value.push(tempMsg);
        scrollToLatest();
        (async () => {
            const data = await uploadFile(f, tempId);
            const idx = messages.value.findIndex((m) => m._tmpId === tempId);
            if (data && data.id) {
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
                } catch (e) {}
                if (!props.externalMessages) {
                    if (idx >= 0) {
                        messages.value.splice(idx, 1, real);
                        if (real.type === 'file' && real.file) prefetchFilePreview(real.file);
                    } else {
                        if (!messages.value.some((m) => m.id === data.id)) messages.value.push(real);
                    }
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
}

function handleDragOver(e) {
    e.preventDefault();
}
function handleDragLeave(e) {
    e.preventDefault();
}

function openFileModal(file) {
    if (!file) return;
    if (props.openAttachmentFn) return props.openAttachmentFn(file);
    const streamUrl =
        attachmentHref(file, false) || `/chat/attachments?path=${encodeURIComponent(file.path || file.url || file.thumb_path || file.original_name)}`;
    fileModal.value.open = true;
    fileModal.value.file = { ...file, streamUrl };
    if (file.mime && typeof file.mime === 'string' && file.mime.startsWith('text/')) {
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

// Request deletion of an attachment shown in the modal or in-message
async function requestDeleteAttachment(file) {
    if (!file) return;
    // If parent provided a handler, call it
    if (props.deleteAttachmentFn) {
        try {
            await props.deleteAttachmentFn(file);
        } catch (e) {
            console.error('deleteAttachmentFn failed', e);
        }
        // close modal after parent handled deletion
        closeFileModal();
        return;
    }

    // Fallback: try to remove message referencing this file from internal messages
    try {
        const idx = messages.value.findIndex(
            (m) => m.file && (m.file.path === file.path || m.file.url === file.url || m.file.original_name === file.original_name),
        );
        if (idx >= 0) messages.value.splice(idx, 1);
    } catch (e) {
        console.error('local delete fallback failed', e);
    }
    closeFileModal();
}

// send message
async function sendMessage() {
    if (!newMessage.value.trim()) return;
    if (isSending.value || sendCooldown.value) return;
    isSending.value = true;
    try {
        if (props.sendFn) {
            await props.sendFn(newMessage.value);
            newMessage.value = '';
        } else {
            if (!props.room?.id) return;
            const res = await axios.post(`/chat/rooms/${props.room.id}/messages`, { body: newMessage.value });
            if (res.data && res.data.id) newMessage.value = '';
        }
        sendCooldown.value = true;
        setTimeout(() => {
            sendCooldown.value = false;
        }, SEND_COOLDOWN_MS);
    } catch (e) {
        alert('送信に失敗しました');
    } finally {
        isSending.value = false;
        autosizeTextarea();
    }
}

// autosize
const MAX_TEXTAREA_HEIGHT = 200;
function autosizeTextarea() {
    nextTick(() => {
        const ta = newMessageRef.value;
        if (!ta) return;
        try {
            ta.style.height = 'auto';
            ta.style.overflowY = 'hidden';
            const newHeight = ta.scrollHeight + 2;
            if (newHeight > MAX_TEXTAREA_HEIGHT) {
                ta.style.height = MAX_TEXTAREA_HEIGHT + 'px';
                ta.style.overflowY = 'auto';
            } else {
                ta.style.height = newHeight + 'px';
                ta.style.overflowY = 'hidden';
            }
        } catch (e) {}
    });
}

watch(newMessage, () => {
    autosizeTextarea();
});

function onTextareaKeydown(e) {
    if (isComposing.value) return;
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        try {
            sendMessage();
        } catch (err) {}
    }
}
function onCompositionStart() {
    isComposing.value = true;
}
function onCompositionEnd() {
    isComposing.value = false;
    nextTick(() => autosizeTextarea());
}

onMounted(() => {
    messages.value = Array.isArray(props.initialMessages)
        ? props.initialMessages.map((m) => {
              if (m.type === 'file' && m.file && !m.file.streamUrl) m.file.streamUrl = buildStreamUrl(m.file);
              if (m.type === 'file' && m.file) prefetchFilePreview(m.file);
              return m;
          })
        : [];
    fetchMessages();
    if (props.room && props.room.id) subscribeEchoFor(props.room.id);
    autosizeTextarea();
});

// when parent passes externalMessages, ensure we scroll when they change
watch(
    () => displayedMessages.value,
    () => {
        scrollToLatest();
    },
    { deep: true },
);

// when parent indicates AI is working, scroll to show typing indicator
watch(
    () => props.aiWorking,
    (val) => {
        if (val) {
            scrollToLatest();
        }
    },
);

onUnmounted(() => {
    if (echoChannel.value) {
        echoChannel.value.stopListening('ChatMessageSent');
        echoChannel.value = null;
    }
    for (const t of pendingReadTimeouts.value.values()) clearTimeout(t);
    pendingReadTimeouts.value.clear();
});
</script>

<template>
    <div
        ref="messageArea"
        class="mb-2 ml-auto min-h-0 w-full max-w-[640px] flex-1 overflow-y-auto rounded border border-gray-100 bg-white p-3 shadow-inner md:p-4"
        @drop.prevent="handleDrop"
        @dragover.prevent="handleDragOver"
        @dragleave.prevent="handleDragLeave"
    >
        <div class="mb-4 flex items-center justify-between">
            <div class="text-lg font-bold">{{ props.room?.name || '' }}</div>
        </div>

        <template v-if="displayedMessages.length === 0">
            <div class="my-20 text-center text-gray-400">メッセージを入力して会話を開始してください</div>
        </template>
        <template v-else>
            <div
                v-for="(msg, idx) in displayedMessages"
                :key="msg.id"
                :ref="
                    (el) => {
                        if (!lastMessageRef) return;
                        if (idx === displayedMessages.length - 1) {
                            lastMessageRef.value = el;
                        } else if (lastMessageRef.value === el) {
                            lastMessageRef.value = null;
                        }
                    }
                "
                :data-last="idx === displayedMessages.length - 1"
                :data-file-path="msg.file ? msg.file.path || msg.file.url || msg.file.original_name : null"
                class="mb-3 flex"
                :class="isOwnMessage(msg) ? 'justify-start' : 'justify-end'"
            >
                <div class="flex items-end" :class="isOwnMessage(msg) ? 'flex-row' : 'flex-row-reverse'">
                    <div v-if="msg.user_name" :class="['flex-shrink-0', isOwnMessage(msg) ? 'mr-3' : 'ml-3']">
                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-100 text-sm font-semibold text-indigo-800">
                            {{ (msg.user_name || '').charAt(0).toUpperCase() }}
                        </div>
                    </div>
                    <div :class="['chat-bubble relative', isOwnMessage(msg) ? 'chat-bubble-own' : 'chat-bubble-other']">
                        <div class="relative mb-1">
                            <span
                                v-if="!msg.is_read && msg.user_id !== props.user.id"
                                class="text-xxs absolute -right-2 -top-2 rounded bg-purple-100 px-2 py-0.5 text-purple-700"
                                >未読</span
                            >
                        </div>
                        <div class="break-words text-sm leading-relaxed">
                            <template v-if="msg.type === 'file' && msg.file">
                                <div class="rounded border bg-gray-50 p-3">
                                    <div class="flex items-center gap-3">
                                        <div class="relative flex h-14 w-14 items-center justify-center overflow-hidden rounded border bg-gray-100">
                                            <template v-if="msg.uploading">
                                                <div class="flex h-full w-full flex-col items-center justify-center">
                                                    <svg
                                                        class="h-10 w-10 animate-spin text-gray-600"
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
                                                <!-- シンプルなアイコン表示に統一：内部に文字は入れず、クリックでプレビューを開く -->
                                                <div
                                                    class="flex h-full w-full cursor-pointer items-center justify-center text-gray-600"
                                                    @click="openFileModal(msg.file)"
                                                >
                                                    <svg
                                                        class="h-10 w-10 text-gray-400"
                                                        xmlns="http://www.w3.org/2000/svg"
                                                        viewBox="0 0 24 24"
                                                        fill="none"
                                                        stroke="currentColor"
                                                        stroke-width="1.5"
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                    >
                                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                                        <path d="M14 2v6h6" />
                                                    </svg>
                                                </div>
                                            </template>
                                        </div>
                                        <div class="flex-1">
                                            <div class="font-medium">{{ msg.file.original_name }}</div>
                                            <div class="text-sm text-gray-500">
                                                <span class="mr-3">{{ (msg.file.size / 1024).toFixed(1) }} KB</span>
                                                <span class="text-xs text-gray-400">{{ msg.file.mime || 'unknown' }}</span>
                                            </div>
                                            <div class="mt-2">
                                                <button
                                                    @click.prevent="
                                                        (props.referenceFn
                                                            ? props.referenceFn
                                                            : props.openAttachmentFn
                                                              ? props.openAttachmentFn
                                                              : openFileModal)(msg.file)
                                                    "
                                                    class="mr-3 text-sm text-blue-600 underline"
                                                >
                                                    参照
                                                </button>
                                                <!-- '開く' は UI から削除 (ダウンロードは残す) -->
                                                <!-- ダウンロード: 既存の '開く' 挙動（元の優先順）を使ってダウンロードさせる -->
                                                <a
                                                    :href="attachmentHref(msg.file, false)"
                                                    :download="msg.file.original_name"
                                                    class="mr-3 text-gray-600"
                                                    >ダウンロード</a
                                                >

                                                <template v-if="props.summarizeFn">
                                                    <button
                                                        @click.prevent="props.summarizeFn(msg.file)"
                                                        class="ml-1 rounded bg-indigo-600 px-2 py-1 text-xs text-white"
                                                    >
                                                        要約
                                                    </button>
                                                </template>
                                                <button @click.prevent="requestDeleteAttachment(msg.file)" class="ml-2 text-sm text-red-600">
                                                    削除
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <template v-else>{{ msg.message }}</template>
                        </div>
                        <div class="mt-2 self-end text-right text-xs text-gray-400">{{ formatDateForDisplay(msg) }}</div>
                    </div>
                </div>
            </div>
        </template>
        <!-- AI working / typing indicator -->
        <div v-if="props.aiWorking" class="mb-3 flex justify-end" aria-live="polite">
            <div class="flex flex-row-reverse items-end">
                <div class="ml-3 flex-shrink-0">
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 text-sm font-semibold text-gray-700">AI</div>
                </div>
                <div class="chat-bubble-other chat-bubble relative">
                    <div class="break-words text-sm leading-relaxed">
                        <div class="flex items-center gap-2">
                            <div class="h-3 w-12 rounded-full bg-gray-100 px-2 py-1">
                                <div class="typing-dots flex items-center justify-center gap-1">
                                    <span class="dot h-2 w-2 animate-bounce rounded-full bg-gray-500" style="animation-delay: 0s"></span>
                                    <span class="dot h-2 w-2 animate-bounce rounded-full bg-gray-500" style="animation-delay: 0.12s"></span>
                                    <span class="dot h-2 w-2 animate-bounce rounded-full bg-gray-500" style="animation-delay: 0.24s"></span>
                                </div>
                            </div>
                            <div class="text-xs text-gray-500">AIが応答中…</div>
                        </div>
                    </div>
                    <div class="mt-2 self-end text-right text-xs text-gray-400">&nbsp;</div>
                </div>
            </div>
        </div>
    </div>
    <form
        @submit.prevent="sendMessage"
        class="ml-auto mt-2 flex w-full max-w-[640px] flex-shrink-0 items-center gap-2 rounded border border-gray-200 bg-white p-2 shadow-sm"
    >
        <textarea
            ref="newMessageRef"
            v-model="newMessage"
            @input="autosizeTextarea"
            @keydown="onTextareaKeydown"
            @compositionstart="onCompositionStart"
            @compositionend="onCompositionEnd"
            rows="1"
            class="flex-1 resize-none rounded px-3 py-2 text-sm leading-relaxed focus:outline-none"
            enterkeyhint="send"
            placeholder="メッセージを入力..."
        ></textarea>
        <button
            type="submit"
            :disabled="isSending || sendCooldown"
            :class="['rounded px-3 py-2 text-sm text-white', isSending || sendCooldown ? 'cursor-not-allowed bg-blue-300 opacity-80' : 'bg-blue-600']"
        >
            {{ isSending ? '送信中…' : '送信' }}
        </button>
    </form>

    <div v-if="fileModal.open" class="z-60 fixed inset-0 flex items-center justify-center bg-black bg-opacity-60">
        <div class="mx-4 w-full max-w-3xl rounded bg-white p-4 shadow-lg">
            <div class="mb-4 flex items-center justify-between">
                <div class="font-bold">{{ fileModal.file?.original_name || 'プレビュー' }}</div>
                <div>
                    <a :href="attachmentHref(fileModal.file, true)" :download="fileModal.file?.original_name" class="mr-3 text-sm text-gray-700"
                        >ダウンロード</a
                    >
                    <button @click="requestDeleteAttachment(fileModal.file)" class="mr-3 rounded border px-3 py-1 text-red-700">削除</button>
                    <button @click="closeFileModal" class="rounded border px-3 py-1 text-gray-700">閉じる</button>
                </div>
            </div>
            <div class="max-h-[70vh] overflow-auto">
                <template v-if="fileModal.file">
                    <template v-if="fileModal.file.mime && fileModal.file.mime.startsWith('image/')">
                        <img :src="attachmentHref(fileModal.file, false)" class="h-auto w-full object-contain" />
                    </template>
                    <template v-else-if="fileModal.file.mime && fileModal.file.mime === 'application/pdf'">
                        <iframe :src="attachmentHref(fileModal.file, false)" class="h-[70vh] w-full" frameborder="0"></iframe>
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
</template>

<style scoped>
.chat-bubble {
    max-width: 420px;
}

/* typing indicator dots */
.typing-dots .dot {
    display: inline-block;
    opacity: 0.9;
}
@keyframes typing-bounce {
    0% {
        transform: translateY(0);
        opacity: 0.6;
    }
    50% {
        transform: translateY(-4px);
        opacity: 1;
    }
    100% {
        transform: translateY(0);
        opacity: 0.6;
    }
}
.animate-bounce {
    animation-name: typing-bounce;
    animation-duration: 900ms;
    animation-iteration-count: infinite;
    animation-timing-function: ease-in-out;
}
</style>
