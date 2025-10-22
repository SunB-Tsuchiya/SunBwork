<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import SummaryPanel from '@/Pages/Bot/SummaryPanel.vue';
import MessageArea from '@/Pages/Chat/MessageArea.vue';
import axios from 'axios';
import MarkdownIt from 'markdown-it';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

const messages = ref([]);
const newMessage = ref('');
const loading = ref(false);

const messageArea = ref(null);

// Bot-specific UI state
const systemPrompt = ref('');
const showPromptModal = ref(false);
const showFileModal = ref(false);
const selectedFiles = ref([]); // File objects selected in modal before upload
const uploadProgress = ref({});
const uploading = ref(false);
const uploadedFiles = ref([]); // metadata returned from /bot/files
const conversationId = ref(null);
const showAttachmentModal = ref(false);
const attachmentToShow = ref(null); // file meta object
const attachmentContent = ref(''); // for fetched text content
const loadingAttachment = ref(false);
const exporting = ref(false);
const exportFormat = ref('md');
// markdown renderer for AI messages (no raw HTML allowed)
const md = new MarkdownIt({ html: false, linkify: true, typographer: true });

function openBotFileModal() {
    showFileModal.value = true;
}

// wrapper to upload a single file via /bot/files and return metadata similar to chat upload
async function uploadSingleFile(file) {
    if (!file) return null;
    const form = new FormData();
    form.append('file', file);
    // If we have a conversationId, ask server to attach this file to the AiConversation
    if (conversationId.value) {
        form.append('attachable_type', '\\App\\Models\\AiConversation');
        form.append('attachable_id', String(conversationId.value));
    }
    try {
        const res = await axios.post('/bot/files', form, {
            withCredentials: true,
            headers: { 'Content-Type': 'multipart/form-data', 'X-XSRF-TOKEN': getXsrfToken() },
        });
        if (res && res.data && res.data.file) return res.data.file;
        return null;
    } catch (e) {
        console.error('uploadSingleFile failed', e);
        return null;
    }
}

// wrapper so MessageArea can call the same send flow used by ChatBot
async function sendViaMessageArea(text) {
    if (!text || !text.trim()) return;
    const t = text.trim();
    const tempId = 'tmp-' + Date.now();
    messages.value.push({ id: tempId, user: '自分', text: t, isTemp: true });
    loading.value = true;
    try {
        console.info('[sendViaMessageArea] before send messages_count=', messages.value.length, 'conversationId=', conversationId.value);
        const payload = { message: t };
        if (systemPrompt.value && systemPrompt.value.trim()) payload.system_prompt = systemPrompt.value.trim();
        if (uploadedFiles.value.length) payload.files = uploadedFiles.value;
        if (conversationId.value) payload.conversation_id = conversationId.value;
        const res = await axios.post('/bot/chat', payload, {
            withCredentials: true,
            headers: { 'X-XSRF-TOKEN': getXsrfToken() },
        });
        const reply = res.data.reply || '(応答なし)';
        // replace temp message
        const idx = messages.value.findIndex((m) => m.id === tempId);
        if (idx >= 0) messages.value.splice(idx, 1, { id: Date.now(), user: '自分', text: t });
        const aiMsg = { id: Date.now() + 1, user: 'AI', text: reply };
        normalizeMessage(aiMsg);
        messages.value.push(aiMsg);
        console.info('[sendViaMessageArea] after send messages_count=', messages.value.length);
        uploadedFiles.value = [];
    } catch (e) {
        // try to show a friendly error coming from the server (OpenAI error detail)
        let errMsg = 'エラー: 応答を取得できませんでした';
        try {
            if (e && e.response && e.response.data) {
                const d = e.response.data;
                if (d.error) {
                    errMsg = 'エラー: ' + (typeof d.error === 'string' ? d.error : d.error.message || JSON.stringify(d.error));
                } else if (d.detail) {
                    errMsg = 'エラー: ' + (typeof d.detail === 'string' ? d.detail : JSON.stringify(d.detail));
                }
            }
        } catch (ee) {}
        messages.value.push({ id: Date.now() + 2, user: 'AI', text: errMsg });
    } finally {
        loading.value = false;
        await nextTick();
        scrollToBottom();
    }
}

function renderMarkdown(text) {
    try {
        const raw = md.render(text || '');
        // sanitize links in generated HTML: disallow javascript: and other unsafe schemes
        try {
            const parser = new DOMParser();
            const doc = parser.parseFromString(raw, 'text/html');
            const anchors = doc.querySelectorAll('a');
            anchors.forEach((a) => {
                try {
                    const href = a.getAttribute('href') || '';
                    const safe = sanitizeUrl(href);
                    if (!safe) {
                        // replace unsafe href with harmless label
                        a.setAttribute('href', '#');
                        a.removeAttribute('target');
                    } else {
                        a.setAttribute('href', safe);
                        a.setAttribute('rel', 'noopener noreferrer');
                        a.setAttribute('target', '_blank');
                    }
                } catch (e) {}
            });
            return doc.body.innerHTML;
        } catch (e) {
            return raw;
        }
    } catch (e) {
        console.error('markdown render failed', e);
        return (text || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
}

// allow only safe url schemes for links/attachment urls
function sanitizeUrl(u) {
    if (!u || typeof u !== 'string') return null;
    const s = u.trim();
    // allow relative paths
    if (s.startsWith('/')) return s;
    // allow mailto
    if (s.toLowerCase().startsWith('mailto:')) return s;
    try {
        const parsed = new URL(s, window.location.origin);
        const proto = parsed.protocol.toLowerCase();
        // allow http, https, blob, data:image/*
        if (proto === 'http:' || proto === 'https:' || proto === 'blob:') return parsed.toString();
        if (proto === 'data:') {
            // only allow image data URIs
            if (/^data:image\//i.test(s)) return s;
            return null;
        }
        return null;
    } catch (e) {
        return null;
    }
}

function getCookie(name) {
    if (typeof document === 'undefined') return null;
    const match = document.cookie.split('; ').find((row) => row.startsWith(name + '='));
    if (!match) return null;
    return decodeURIComponent(match.split('=')[1] || '');
}

function getXsrfToken() {
    return getCookie('XSRF-TOKEN') || '';
}

// Ensure a local path has a leading slash when used in hrefs
function ensureLeadingSlashIfLocal(u) {
    if (!u || typeof u !== 'string') return u;
    const s = u.trim();
    if (s.startsWith('http://') || s.startsWith('https://') || s.startsWith('blob:') || s.startsWith('data:')) return s;
    return s.startsWith('/') ? s : '/' + s;
}

function isUrlString(s) {
    return typeof s === 'string' && /^https?:\/\//.test(s.trim());
}

// Local ensureUrlSafe helper (mirrors MessageArea.ensureUrlSafe)
function ensureUrlSafe(candidate) {
    if (!candidate || typeof candidate !== 'string') return null;
    const s = candidate.trim();
    if (!s) return null;
    if (s.startsWith('/')) return s;
    if (s.startsWith('storage/')) return '/' + s;
    if (s.startsWith('attachments/')) return `/chat/attachments?path=${encodeURIComponent(s)}`;
    if (s.startsWith('chat/')) return `/chat/attachments?path=${encodeURIComponent(s)}`;
    if (s.startsWith('bot/')) return `/bot/attachments?path=${encodeURIComponent(s)}`;
    return '/' + s;
}

function normalizeMessage(msg) {
    if (!msg) return msg;
    try {
        // If message already marked as file upload, ensure label is friendly
        if (msg.file) {
            msg.isFileUpload = true;
            const meta = msg.file;
            if (typeof meta === 'string') {
                // sometimes server returns URL string
                msg.file = { url: meta, original_name: meta.split('/').pop() };
            } else {
                // ensure original_name exists
                if (!meta.original_name && meta.name) meta.original_name = meta.name;
            }
            msg.text = `ファイルがアップされました\n${msg.file.original_name || msg.file.name || ''}`;
            return msg;
        }

        // If text contains serialized file meta, parse it
        if (msg.text && typeof msg.text === 'string') {
            const t = msg.text.trim();
            // plain URL and meta available elsewhere
            if (isUrlString(t) && msg.meta && msg.meta.file) {
                msg.isFileUpload = true;
                msg.file = msg.meta.file;
                if (!msg.file.original_name && msg.file.name) msg.file.original_name = msg.file.name;
                msg.text = `ファイルがアップされました\n${msg.file.original_name || msg.file.name || ''}`;
                return msg;
            }
            try {
                const parsed = JSON.parse(t);
                if (parsed && (parsed.original_name || parsed.name || parsed.url)) {
                    msg.isFileUpload = true;
                    msg.file = parsed;
                    if (!msg.file.original_name && msg.file.name) msg.file.original_name = msg.file.name;
                    msg.text = `ファイルがアップされました\n${msg.file.original_name || msg.file.name || ''}`;
                    return msg;
                }
            } catch (e) {
                // not JSON, ignore
            }
        }
    } catch (e) {
        console.warn('normalizeMessage failed', e);
    }
    return msg;
}
// maximum allowed upload size per file (10 MB)
const MAX_UPLOAD_BYTES = 10 * 1024 * 1024;

// canUpload: true when there are selected files and we're not currently uploading
const canUpload = computed(() => {
    try {
        return Array.isArray(selectedFiles.value) && selectedFiles.value.length > 0 && !uploading.value;
    } catch (e) {
        return false;
    }
});

const fileInput = ref(null);

// conversation histories shown in left column
const histories = ref([]);

async function fetchHistories() {
    try {
        const res = await axios.get('/bot/history', { withCredentials: true });
        let list = res && res.data ? res.data : [];
        // support Inertia-style payloads (data) or plain arrays
        if (list && list.data && Array.isArray(list.data)) list = list.data;
        if (!Array.isArray(list)) list = [];
        histories.value = list;
        return list;
    } catch (e) {
        histories.value = [];
        return [];
    }
}

function selectHistory(h) {
    if (!h || !h.id) return;
    // load via existing loader
    loadConversation(h.id);
    try {
        window.history.pushState({}, '', `/bot/chat?load_conversation=${encodeURIComponent(h.id)}`);
    } catch (e) {}
}

// show summary panel when a conversation is loaded
const showSummary = ref(true);

function onFileInputChange(e) {
    const files = e.target && e.target.files ? Array.from(e.target.files) : [];
    // Debug logging removed
    // filter by max size and alert user for oversized files
    const accepted = [];
    for (const f of files) {
        if (f.size > MAX_UPLOAD_BYTES) {
            alert(
                `${f.name} は許容サイズ（${Math.round(MAX_UPLOAD_BYTES / 1024 / 1024)}MB）を超えています。容量を軽くしてから再度アップロードしてください。`,
            );
        } else {
            accepted.push(f);
        }
    }
    selectedFiles.value = accepted;
}

watch(selectedFiles, (val) => {
    // Debug logging removed
});

function debugClick() {
    // Debug logging removed
}

function getAttachmentUrl(meta) {
    if (!meta) return '';
    // Prefer explicit thumbnail urls/paths when present
    if (meta.thumb_url) {
        const safe = sanitizeUrl(meta.thumb_url);
        if (safe) return safe;
        if (/^https?:\/\//.test(meta.thumb_url) || meta.thumb_url.startsWith('/')) return meta.thumb_url;
    }
    if (meta.thumb_path) {
        // map to public storage URL so browsers can load it directly
        return ensureUrlSafe('/storage/' + meta.thumb_path.replace(/^\//, ''));
    }

    // Prefer a public storage URL when available (Storage::url), this avoids hitting
    // the auth-protected stream endpoint which can return 302 -> /login on reload.

    // Fallback: if we have internal storage path, use the stream endpoint.
    // Prefer internal storage path (thumb_path or path) and stream it via the bot endpoint.
    // This avoids returning direct /storage/... URLs which can be blocked by the webserver
    // (causing 403). If we have an explicit thumb_path or path, build the proxied URL.
    try {
        if (meta && (meta.thumb_path || meta.path)) {
            const p = meta.thumb_path || meta.path;
            return ensureUrlSafe('/bot/attachments?path=' + encodeURIComponent(p));
        }
    } catch (e) {
        // fall through to other fallbacks
    }

    // If a public URL is provided (remote or storage URL), validate and return it as last resort.
    if (meta && meta.url) {
        // if it's a /storage/... URL, prefer to convert to streaming path where possible
        if (typeof meta.url === 'string' && meta.url.startsWith('/storage/')) {
            // strip leading /storage/ and stream
            return ensureUrlSafe('/bot/attachments?path=' + encodeURIComponent(meta.url.replace(/^\/storage\//, '')));
        }
        // otherwise sanitize and return the provided url
        const s = sanitizeUrl(meta.url);
        return s || '';
    }

    return '';
}

async function openAttachment(meta) {
    if (!meta) return;
    attachmentToShow.value = meta;
    attachmentContent.value = '';
    showAttachmentModal.value = true;
    // if text-like and no preview available, fetch full content
    const isText = meta.mime && meta.mime.startsWith && meta.mime.startsWith('text');
    const maybeTextExt = (meta.original_name || '').toLowerCase().match(/\.(txt|md|csv|json|log|xml)$/);
    if ((isText || maybeTextExt) && !meta.preview) {
        try {
            loadingAttachment.value = true;
            const url = getAttachmentUrl(meta);
            if (url) {
                const res = await axios.get(url, { responseType: 'text', withCredentials: true });
                attachmentContent.value = res.data;
            }
        } catch (e) {
            console.error('fetch attachment content failed', e);
        } finally {
            loadingAttachment.value = false;
        }
    }
}

function closeAttachment() {
    showAttachmentModal.value = false;
    attachmentToShow.value = null;
    attachmentContent.value = '';
    loadingAttachment.value = false;
}

// Scroll to the message element that references this file and briefly highlight it
function scrollToFileMessage(fileMeta) {
    try {
        const sel = fileMeta.path || fileMeta.url || fileMeta.original_name;
        if (!sel) return;
        // search for element with matching data-file-path
        const area =
            document.querySelector('[ref="messageArea"]') || document.querySelector('[ref=messageArea]') || document.querySelector('.mb-2.ml-auto');
        if (!area) return;
        const el = Array.from(area.querySelectorAll('[data-file-path]')).find(
            (e) => e.getAttribute('data-file-path') === sel || e.getAttribute('data-file-path') === '/' + sel,
        );
        if (el) {
            el.scrollIntoView({ block: 'center', behavior: 'smooth' });
            el.classList.add('ring-4', 'ring-indigo-300');
            setTimeout(() => el.classList.remove('ring-4', 'ring-indigo-300'), 2500);
        }
    } catch (e) {
        console.error('scrollToFileMessage failed', e);
    }
}

async function exportConversation() {
    // build payload similar to saveConversation
    if ((!messages.value || messages.value.length === 0) && !systemPrompt.value) return alert('エクスポートする会話がありません。');
    exporting.value = true;
    try {
        const firstUser = messages.value.find((m) => m.user === '自分');
        const extractFirstN = (s, n) => {
            if (!s) return '';
            return Array.from(s.trim()).slice(0, n).join('');
        };
        const titlePrefix = extractFirstN(firstUser ? firstUser.text : '', 20) || `会話`;
        const payload = {
            title: titlePrefix,
            system_prompt: systemPrompt.value,
            messages: messages.value.map((m) => ({
                role: m.user === '自分' ? 'user' : m.user === 'AI' ? 'assistant' : 'system',
                content: m.text,
                meta: m.file ? { file: m.file } : null,
            })),
            format: exportFormat.value || 'md',
        };
        const res = await axios.post('/bot/export', payload);
        if (res && res.data && res.data.url) {
            // open download in new tab
            window.open(res.data.url, '_blank');
        } else if (res && res.data && res.data.filename) {
            window.location.href = '/bot/export/download/' + encodeURIComponent(res.data.filename);
        } else {
            alert('エクスポートに失敗しました。');
        }
    } catch (e) {
        console.error('export failed', e);
        alert('エクスポート中にエラーが発生しました。');
    } finally {
        exporting.value = false;
    }
}

async function sendMessage() {
    if (!newMessage.value.trim()) return;
    const text = newMessage.value.trim();
    const tempId = 'tmp-' + Date.now();
    messages.value.push({ id: tempId, user: '自分', text, isTemp: true });
    newMessage.value = '';
    loading.value = true;
    scrollToBottom();
    try {
        // include system prompt and uploaded files metadata when present
        const payload = { message: text };
        if (systemPrompt.value && systemPrompt.value.trim()) payload.system_prompt = systemPrompt.value.trim();
        if (uploadedFiles.value.length) payload.files = uploadedFiles.value;
        if (conversationId.value) payload.conversation_id = conversationId.value;
        const res = await axios.post('/bot/chat', payload, {
            withCredentials: true,
            headers: { 'X-XSRF-TOKEN': getXsrfToken() },
        });
        const reply = res.data.reply || '(応答なし)';
        // mark temp as sent
        const idx = messages.value.findIndex((m) => m.id === tempId);
        if (idx >= 0) messages.value.splice(idx, 1, { id: Date.now(), user: '自分', text });
        const aiMsg = { id: Date.now() + 1, user: 'AI', text: reply };
        normalizeMessage(aiMsg);
        messages.value.push(aiMsg);
        // after successful send, clear selected uploaded files
        uploadedFiles.value = [];
    } catch (e) {
        let errMsg = 'エラー: 応答を取得できませんでした';
        try {
            if (e && e.response && e.response.data) {
                const d = e.response.data;
                if (d.error) {
                    errMsg = 'エラー: ' + (typeof d.error === 'string' ? d.error : d.error.message || JSON.stringify(d.error));
                } else if (d.detail) {
                    errMsg = 'エラー: ' + (typeof d.detail === 'string' ? d.detail : JSON.stringify(d.detail));
                }
            }
        } catch (ee) {}
        messages.value.push({ id: Date.now() + 2, user: 'AI', text: errMsg });
    } finally {
        loading.value = false;
        await nextTick();
        scrollToBottom();
    }
}

function scrollToBottom() {
    nextTick(() => {
        try {
            if (messageArea.value) messageArea.value.scrollTop = messageArea.value.scrollHeight;
        } catch (e) {}
    });
}

// Upload selected files in the file modal to /bot/files
async function uploadSelectedFiles() {
    // Debug logging removed
    if (!canUpload.value) {
        console.warn('uploadSelectedFiles: cannot upload, canUpload=', canUpload.value);
        return;
    }
    uploading.value = true;
    uploadProgress.value = {};
    for (let i = 0; i < selectedFiles.value.length; i++) {
        const f = selectedFiles.value[i];
        const form = new FormData();
        form.append('file', f);
        if (conversationId.value) {
            form.append('attachable_type', '\\App\\Models\\AiConversation');
            form.append('attachable_id', String(conversationId.value));
        }
        try {
            const res = await axios.post('/bot/files', form, {
                withCredentials: true,
                headers: { 'Content-Type': 'multipart/form-data', 'X-XSRF-TOKEN': getXsrfToken() },
                onUploadProgress: (ev) => {
                    const pct = ev.total ? Math.round((ev.loaded / ev.total) * 100) : 0;
                    uploadProgress.value[f.name] = pct;
                },
            });
            if (res.status === 409 && res.data && res.data.error === 'file_exists') {
                // notify user that a file with the same name exists
                alert(res.data.message || '同名のファイルが既に存在します。別名でお試しください。');
                continue;
            }
            if (res.data && res.data.file) {
                const meta = res.data.file;
                uploadedFiles.value.push(meta);
                // show a simple chat message indicating upload succeeded (no thumbnail needed)
                const newMsg = {
                    id: 'file-' + Date.now() + '-' + Math.floor(Math.random() * 10000),
                    user: '自分',
                    text: `ファイルがアップされました\n${meta.original_name || meta.name || ''}`,
                    isFileUpload: true,
                    type: 'file',
                    file: meta,
                };
                normalizeMessage(newMsg);
                messages.value.push(newMsg);
                // ensure chat scrolls to show the new message
                await nextTick();
                scrollToBottom();

                // 非表示でAIにファイルを参照させる（次の質問の文脈として準備）
                (async () => {
                    try {
                        const payload = {
                            message: `参照: ${meta.original_name || meta.name || meta.path}`,
                            summarize_file: meta,
                            hidden_reference: true,
                        };
                        if (conversationId.value) payload.conversation_id = conversationId.value;
                        // fire-and-forget; server may use summarize_file or hidden_reference to ingest file
                        await axios.post('/bot/chat', payload, { withCredentials: true, headers: { 'X-XSRF-TOKEN': getXsrfToken() } });
                    } catch (e) {
                        // don't surface errors to the user
                        console.debug('hidden reference request failed', e?.message || e);
                    }
                })();
            }
        } catch (err) {
            console.error('upload failed', err);
        }
    }
    // clear selection after upload
    selectedFiles.value = [];
    uploading.value = false;
    // persist the conversation so uploaded-file messages are saved to history
    try {
        await saveConversation();
    } catch (e) {
        console.warn('saveConversation after upload failed', e);
    }
    // close modal and return to chat view
    showFileModal.value = false;
    await nextTick();
    scrollToBottom();
}

function removeUploaded(idx) {
    if (idx >= 0 && idx < uploadedFiles.value.length) {
        uploadedFiles.value.splice(idx, 1);
    }
}

// Called by MessageArea when user requests deletion of an attachment from the chat UI
async function deleteUploadedFile(fileMeta) {
    if (!fileMeta) return;
    // Attempt server-side deletion if endpoint exists
    try {
        // API not guaranteed; try a conventional route first
        const url = '/bot/files/delete';
        const res = await axios
            .post(
                url,
                { path: fileMeta.path || fileMeta.url || fileMeta.original_name },
                { withCredentials: true, headers: { 'X-XSRF-TOKEN': getXsrfToken() } },
            )
            .catch(() => null);
        if (res && res.data && (res.data.success || res.status === 200)) {
            // remove messages referencing this file and save conversation
            const idxs = [];
            messages.value.forEach((m, i) => {
                try {
                    if (m.file && (m.file.path === fileMeta.path || m.file.url === fileMeta.url || m.file.original_name === fileMeta.original_name))
                        idxs.push(i);
                } catch (e) {}
            });
            for (let i = idxs.length - 1; i >= 0; i--) messages.value.splice(idxs[i], 1);
            await saveConversation();
            return true;
        }
    } catch (e) {
        console.warn('server delete attempt failed or endpoint not present', e);
    }

    // Fallback: remove message locally and persist conversation
    try {
        const idxs = [];
        messages.value.forEach((m, i) => {
            try {
                if (m.file && (m.file.path === fileMeta.path || m.file.url === fileMeta.url || m.file.original_name === fileMeta.original_name))
                    idxs.push(i);
            } catch (e) {}
        });
        for (let i = idxs.length - 1; i >= 0; i--) messages.value.splice(idxs[i], 1);
        await saveConversation();
    } catch (e) {
        console.error('local delete fallback failed', e);
    }
}

// Ask AI to summarize a specific uploaded file. Sends a special payload to /bot/chat.
async function summarizeFile(meta) {
    if (!meta) return;
    // show a temporary message indicating summarization started
    const tempId = 'tmp-sum-' + Date.now();
    messages.value.push({ id: tempId, user: 'AI', text: '要約を生成しています…', isTemp: true });
    loading.value = true;
    try {
        const payload = { message: `このファイルを要約してください: ${meta.original_name || meta.name || meta.path}` };
        if (conversationId.value) payload.conversation_id = conversationId.value;
        // include file meta so server can fetch/process it; server must handle summarize_file key
        payload.summarize_file = meta;
        const res = await axios.post('/bot/chat', payload, {
            withCredentials: true,
            headers: { 'X-XSRF-TOKEN': getXsrfToken() },
        });
        const summary = res.data && res.data.reply ? res.data.reply : '(要約が返されませんでした)';
        // replace temp message with final AI summary
        const idx = messages.value.findIndex((m) => m.id === tempId);
        if (idx >= 0) messages.value.splice(idx, 1, { id: Date.now(), user: 'AI', text: summary });
        else messages.value.push({ id: Date.now(), user: 'AI', text: summary });
    } catch (e) {
        console.error('summarizeFile failed', e);
        const errMsg = 'エラー: ファイルの要約に失敗しました';
        const idx = messages.value.findIndex((m) => m.id === tempId);
        if (idx >= 0) messages.value.splice(idx, 1, { id: Date.now(), user: 'AI', text: errMsg });
        else messages.value.push({ id: Date.now(), user: 'AI', text: errMsg });
    } finally {
        loading.value = false;
        await nextTick();
        scrollToBottom();
    }
}

async function loadConversation(id) {
    try {
        const res = await axios.get(`/bot/history/${id}/json`);
        const conv = res.data;
        conversationId.value = conv.id;
        systemPrompt.value = conv.system_prompt || '';
        messages.value = conv.messages.map((m) => {
            const user = m.role === 'user' ? '自分' : m.role === 'assistant' ? 'AI' : 'system';
            // base message
            const msg = { id: m.id, user, text: m.content };
            // always try to normalize message content (cover cases where content is raw JSON string)
            try {
                // Attach meta.file if available
                const meta = m.meta || {};
                const fileMeta = meta.file || (meta.original_name ? meta : null);
                if (fileMeta) {
                    msg.type = 'file';
                    msg.file = fileMeta;
                }
                if (fileMeta) msg.file = fileMeta;
                normalizeMessage(msg);
            } catch (err) {
                console.warn('loadConversation: invalid meta for message', m.id, err);
            }
            return msg;
        });
        await nextTick();
        scrollToBottom();
    } catch (e) {
        console.error('loadConversation failed', e);
    }
}

async function saveConversation() {
    // only save if there are messages or a system prompt
    if ((!messages.value || messages.value.length === 0) && !systemPrompt.value) return null;
    try {
        // build title from first user prompt (first 10 chars) + timestamp
        const firstUser = messages.value.find((m) => m.user === '自分');
        const extractFirstN = (s, n) => {
            if (!s) return '';
            return Array.from(s.trim()).slice(0, n).join('');
        };
        const pad2 = (v) => (v < 10 ? '0' + v : v);
        const d = new Date();
        const ts = `${d.getFullYear()}/${d.getMonth() + 1}/${d.getDate()} ${pad2(d.getHours())}:${pad2(d.getMinutes())}:${pad2(d.getSeconds())}`;
        const titlePrefix = extractFirstN(firstUser ? firstUser.text : '', 10) || `会話`;
        const title = `${titlePrefix}_${ts}`;

        const payload = {
            title: title,
            system_prompt: systemPrompt.value,
            messages: messages.value.map((m) => ({
                role: m.user === '自分' ? 'user' : m.user === 'AI' ? 'assistant' : 'system',
                content: m.text,
                meta: m.file ? { file: m.file } : null,
            })),
        };
        if (conversationId.value) {
            const res = await axios.put(`/bot/history/${conversationId.value}`, payload, {
                withCredentials: true,
                headers: { 'X-XSRF-TOKEN': getXsrfToken() },
            });
            return res.data;
        } else {
            const res = await axios.post('/bot/history', payload, {
                withCredentials: true,
                headers: { 'X-XSRF-TOKEN': getXsrfToken() },
            });
            // remember conversation id so subsequent saves update same conversation
            if (res && res.data && res.data.id) {
                conversationId.value = res.data.id;
            }
            return res.data;
        }
    } catch (e) {
        console.error('saveConversation failed', e);
        return null;
    }
}

async function startNewConversation() {
    // Ask user whether to save current conversation before starting a new one.
    if ((messages.value && messages.value.length > 0) || (systemPrompt.value && systemPrompt.value.trim())) {
        const shouldSave = confirm('現在の会話を保存しますか？ 保存しない場合は内容は破棄されます。');
        if (shouldSave) {
            await saveConversation();
        }
    }
    conversationId.value = null;
    messages.value = [];
    systemPrompt.value = '';
    uploadedFiles.value = [];
}

function handleBeforeUnload(e) {
    // try to save conversation on unload. sendBeacon cannot set headers, so include CSRF token as query param when present.
    try {
        const payload = {
            title: `会話 ${new Date().toLocaleString()}`,
            system_prompt: systemPrompt.value,
            messages: messages.value.map((m) => ({
                role: m.user === '自分' ? 'user' : m.user === 'AI' ? 'assistant' : 'system',
                content: m.text,
                meta: m.file ? { file: m.file } : null,
            })),
        };
        let url = '/bot/history';
        const token = getXsrfToken();
        // If we have a token, append as _token so Laravel's VerifyCsrfToken can see it from request input.
        if (token) {
            const sep = url.indexOf('?') === -1 ? '?' : '&';
            url = `${url}${sep}_token=${encodeURIComponent(token)}`;
        }

        if (navigator.sendBeacon) {
            const blob = new Blob([JSON.stringify(payload)], { type: 'application/json' });
            try {
                navigator.sendBeacon(url, blob);
            } catch (beErr) {
                // fallback to async post if beacon fails
                axios.post('/bot/history', payload, { withCredentials: true, headers: { 'X-XSRF-TOKEN': token } }).catch(() => {});
            }
        } else {
            // best-effort async
            axios.post('/bot/history', payload, { withCredentials: true, headers: { 'X-XSRF-TOKEN': token } }).catch(() => {});
        }
    } catch (err) {}
}

onMounted(async () => {
    // check query param load_conversation
    try {
        const params = new URLSearchParams(window.location.search);
        const id = params.get('load_conversation');
        if (id) {
            await loadConversation(id);
        } else {
            // load conversation histories for left column and auto-continue the latest conversation
            await fetchHistories();
            try {
                // if there are histories and we have no active messages, auto-load the most recent one
                if ((!messages.value || messages.value.length === 0) && histories.value && histories.value.length > 0) {
                    const latest = histories.value[0];
                    if (latest && latest.id) {
                        await loadConversation(latest.id);
                    }
                }
            } catch (ee) {}
        }
    } catch (e) {}
    window.addEventListener('beforeunload', handleBeforeUnload);
});

onUnmounted(() => {
    window.removeEventListener('beforeunload', handleBeforeUnload);
});
</script>

<template>
    <AppLayout title="AIチャット">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">AIチャット</h2>
        </template>
        <div class="py-6">
            <div class="mx-auto flex max-w-6xl flex-col gap-4 rounded bg-white p-4 shadow" style="height: calc(100vh - 140px)">
                <div class="mb-4 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <!-- <a href="/chat" class="inline-flex items-center text-blue-600 hover:underline px-3 py-2">← チャットルーム一覧へ戻る</a> -->
                        <button
                            @click="startNewConversation"
                            type="button"
                            class="inline-flex items-center rounded border bg-gray-100 px-3 py-2 text-sm"
                        >
                            新規会話を始める
                        </button>
                        <a href="/bot/history" class="inline-flex items-center rounded border bg-white px-3 py-2 text-sm hover:bg-gray-50"
                            >履歴を閲覧</a
                        >
                        <button
                            type="button"
                            @click="showPromptModal = true"
                            class="inline-flex items-center rounded bg-indigo-600 px-3 py-2 text-white shadow-lg"
                        >
                            プロンプト編集
                        </button>
                        <button
                            type="button"
                            @click="showFileModal = true"
                            class="inline-flex items-center rounded bg-green-600 px-3 py-2 text-white shadow-lg"
                        >
                            ファイルアップロード
                        </button>
                        <!-- Export controls -->
                        <div class="flex items-center gap-2">
                            <select v-model="exportFormat" class="rounded border px-2 py-1 text-sm">
                                <option value="md">Markdown (.md)</option>
                                <option value="txt">Text (.txt)</option>
                                <option value="doc">Word (.doc)</option>
                            </select>
                            <button
                                type="button"
                                @click="exportConversation"
                                :disabled="exporting"
                                class="inline-flex items-center rounded bg-yellow-600 px-3 py-2 text-white shadow"
                            >
                                エクスポート
                            </button>
                        </div>
                    </div>
                </div>
                <div class="flex w-full flex-1 gap-4 overflow-hidden">
                    <aside class="hidden w-64 flex-shrink-0 overflow-auto border-r border-gray-100 px-3 py-2 md:block">
                        <div class="mb-2 flex items-center justify-between">
                            <div class="text-sm font-medium">会話履歴</div>
                            <button @click="fetchHistories" class="text-xs text-blue-600 hover:underline">更新</button>
                        </div>
                        <ul class="space-y-2 text-sm leading-tight">
                            <li v-for="h in histories.slice(0, 5)" :key="h.id" class="rounded">
                                <div
                                    role="button"
                                    tabindex="0"
                                    @click.prevent="selectHistory(h)"
                                    @keydown.enter.prevent="selectHistory(h)"
                                    class="cursor-pointer px-2 py-2 hover:bg-indigo-50 hover:text-indigo-700"
                                >
                                    <div class="block w-full break-words font-medium">
                                        {{
                                            h.title ||
                                            h.name ||
                                            (h.system_prompt ? h.system_prompt.slice(0, 80) + '...' : (h.meta && h.meta.title) || '会話')
                                        }}
                                    </div>
                                    <div class="mt-1 text-xs text-gray-500">
                                        {{ new Date(h.updated_at || h.created_at || Date.now()).toLocaleString() }}
                                    </div>
                                </div>
                            </li>
                        </ul>
                        <div class="mt-3 px-2">
                            <a href="/bot/history" class="text-xs text-blue-600 hover:underline">全履歴を見る</a>
                        </div>
                        <div v-if="!histories || histories.length === 0" class="mt-4 text-sm text-gray-500">
                            履歴がありません。<br />右上の「ファイルアップロード」または「新規会話を始める」で会話を作成してください。
                        </div>
                    </aside>
                    <main class="flex-1 overflow-auto px-4 py-2">
                        <SummaryPanel :conversationId="conversationId" :visible="showSummary" widthClass="w-full" />
                        <MessageArea
                            :room="{ id: null, name: 'AIチャット' }"
                            :initialMessages="messages"
                            :user="{ id: null, name: '自分' }"
                            :externalMessages="messages"
                            widthClass="w-full"
                            :openUploadModal="openBotFileModal"
                            :sendFn="sendViaMessageArea"
                            :summarizeFn="summarizeFile"
                            :uploadFn="uploadSingleFile"
                            :openAttachmentFn="openAttachment"
                            :renderMarkdownFn="renderMarkdown"
                            :aiWorking="loading"
                            :deleteAttachmentFn="deleteUploadedFile"
                        />
                    </main>
                </div>

                <!-- Prompt Modal -->
                <div v-if="showPromptModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
                    <div class="w-11/12 max-w-2xl rounded bg-white p-4 shadow-lg">
                        <h3 class="mb-2 font-semibold">システムプロンプトを編集</h3>
                        <div class="mb-3 text-sm text-gray-600">
                            <p>システムプロンプトはモデルに対する全体的な指示です。会話の口調、出力形式、守るべきルールなどをここで指定できます。</p>
                            <p class="mt-2">例：</p>
                            <div class="mt-1 rounded bg-gray-100 p-2 text-xs">
                                <div class="mb-1">
                                    <strong>例1：</strong>あなたは丁寧な日本語で答えるアシスタントです。専門用語には簡単な注釈を付けてください。
                                </div>
                                <div><strong>例2：</strong>箇条書きで要点を3つ以内にまとめ、最後に短い結論を書いてください。</div>
                            </div>
                        </div>
                        <textarea v-model="systemPrompt" rows="6" class="w-full rounded border p-2"></textarea>
                        <div class="mt-3 flex justify-end gap-2">
                            <button @click="showPromptModal = false" class="rounded border px-3 py-1">キャンセル</button>
                            <button @click="showPromptModal = false" class="rounded bg-blue-600 px-3 py-1 text-white">保存</button>
                        </div>
                    </div>
                </div>

                <!-- File Upload Modal -->
                <div v-if="showFileModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
                    <div class="w-11/12 max-w-2xl rounded bg-white p-4 shadow-lg">
                        <h3 class="mb-2 font-semibold">ファイルをアップロード</h3>
                        <div>
                            <input ref="fileInput" type="file" multiple @change="onFileInputChange" class="cursor-pointer" />
                            <div class="mt-2 text-xs text-gray-600">Debug: 選択ファイル {{ selectedFiles.length }} • canUpload: {{ canUpload }}</div>
                        </div>
                        <div class="mt-3">
                            <div v-if="selectedFiles && selectedFiles.length">
                                <div v-for="(f, i) in selectedFiles" :key="i" class="mb-2 flex items-center justify-between rounded border p-2">
                                    <div class="text-sm">
                                        {{ f.name }} <span class="text-xs text-gray-500">({{ Math.round(f.size / 1024) }} KB)</span>
                                    </div>
                                    <div class="text-sm text-gray-600">{{ f.type }}</div>
                                </div>
                            </div>
                            <div v-else class="text-sm text-gray-500">選択されたファイルはありません。</div>
                        </div>
                        <div class="mt-3 flex items-center justify-between">
                            <div class="text-sm text-gray-600">アップロード済み: {{ uploadedFiles.length }}</div>
                            <div class="flex gap-2">
                                <button type="button" @click="showFileModal = false" class="rounded border px-3 py-1">閉じる</button>
                                <button
                                    type="button"
                                    @click="uploadSelectedFiles"
                                    :disabled="!canUpload"
                                    class="pointer-events-auto cursor-pointer rounded bg-blue-600 px-3 py-1 text-white disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    アップロード
                                </button>
                                <!-- テスト用: クリックハンドラが届いているかを確認するボタン -->
                                <button type="button" @click="debugClick" class="rounded border px-2 py-1 text-sm">テストクリック</button>
                            </div>
                        </div>
                        <div class="mt-3">
                            <div v-if="uploading" class="text-sm text-gray-600">アップロード中...</div>
                            <div v-for="(meta, idx) in uploadedFiles" :key="meta.path" class="mt-2 rounded border p-2">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <template v-if="meta && (meta.thumb_path || meta.thumb_url || meta.path || meta.url)">
                                            <img
                                                :src="getAttachmentUrl({ path: meta.thumb_path || meta.path, url: meta.thumb_url || meta.url })"
                                                class="h-12 w-12 object-cover"
                                            />
                                        </template>
                                        <template v-else>
                                            <div class="flex h-12 w-12 items-center justify-center rounded bg-gray-100 text-gray-600">
                                                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                                    <path d="M14 2v6h6" />
                                                </svg>
                                            </div>
                                        </template>
                                        <div>
                                            <div class="font-medium">{{ meta.original_name }}</div>
                                            <div class="text-xs text-gray-500">{{ meta.mime }} • {{ Math.round(meta.size / 1024) }} KB</div>
                                            <div v-if="meta.preview" class="mt-1 text-xs text-gray-700">{{ meta.preview }}</div>
                                        </div>
                                    </div>
                                    <div class="ml-4 flex items-center gap-2">
                                        <button @click="removeUploaded(idx)" class="text-sm text-red-600">削除</button>
                                        <button @click="summarizeFile(meta)" class="rounded bg-indigo-600 px-2 py-1 text-sm text-white">要約</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Attachment Modal -->
                <div v-if="showAttachmentModal" class="z-60 fixed inset-0 flex items-center justify-center bg-black/50">
                    <div class="max-h-[90vh] w-11/12 max-w-3xl overflow-auto rounded bg-white p-4 shadow-lg">
                        <div class="flex items-start justify-between">
                            <div class="font-medium">
                                {{ attachmentToShow ? attachmentToShow.original_name || attachmentToShow.name : '添付ファイル' }}
                            </div>
                            <div>
                                <button
                                    @click="
                                        (async () => {
                                            scrollToFileMessage(attachmentToShow);
                                            closeAttachment();
                                        })()
                                    "
                                    class="mr-2 rounded border px-2 py-1 text-blue-700"
                                >
                                    チャットで表示
                                </button>
                                <button
                                    @click="
                                        (async () => {
                                            await deleteUploadedFile(attachmentToShow);
                                            closeAttachment();
                                        })()
                                    "
                                    class="mr-2 rounded border px-2 py-1 text-red-700"
                                >
                                    削除
                                </button>
                                <button @click="closeAttachment" class="rounded border px-2 py-1">閉じる</button>
                            </div>
                        </div>
                        <div class="mt-3">
                            <template v-if="attachmentToShow">
                                <!-- Image -->
                                <div v-if="attachmentToShow.mime && attachmentToShow.mime.startsWith('image/')" class="flex w-full justify-center">
                                    <img
                                        :src="getAttachmentUrl(attachmentToShow)"
                                        class="block h-auto max-h-[80vh] w-auto max-w-full"
                                        style="object-fit: contain"
                                        alt="attachment"
                                    />
                                </div>

                                <!-- PDF -->
                                <iframe
                                    v-else-if="attachmentToShow.mime && attachmentToShow.mime.includes('pdf')"
                                    :src="getAttachmentUrl(attachmentToShow)"
                                    class="h-[70vh] w-full border"
                                />

                                <!-- Text-like -->
                                <div v-else class="prose max-w-full">
                                    <div v-if="attachmentToShow.preview" class="whitespace-pre-line text-sm text-gray-900">
                                        {{ attachmentToShow.preview }}
                                    </div>
                                    <div v-else-if="loadingAttachment" class="text-sm text-gray-600">読み込み中...</div>
                                    <pre v-else class="whitespace-pre-wrap bg-white text-sm text-gray-900">{{ attachmentContent }}</pre>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Rendered markdown styles inside chat bubbles */
.chat-bubble .prose h1,
.chat-bubble h1 {
    font-size: 1.25rem;
    font-weight: 700;
}
.chat-bubble .prose h2,
.chat-bubble h2 {
    font-size: 1.1rem;
    font-weight: 700;
}
.chat-bubble pre {
    background: #0b0b0b;
    color: #e6e6e6;
    padding: 0.75rem;
    border-radius: 0.375rem;
    overflow: auto;
}
.chat-bubble code {
    background: #f4f4f5;
    padding: 0.2rem 0.35rem;
    border-radius: 0.25rem;
    color: #111;
}
.chat-bubble strong {
    font-weight: 700;
}
.chat-bubble a {
    color: #3b82f6;
}
</style>
