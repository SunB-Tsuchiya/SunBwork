<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { ref, nextTick, computed, watch, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import MarkdownIt from 'markdown-it';

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

function renderMarkdown(text) {
  try {
    const raw = md.render(text || '');
    // sanitize links in generated HTML: disallow javascript: and other unsafe schemes
    try {
      const parser = new DOMParser();
      const doc = parser.parseFromString(raw, 'text/html');
      const anchors = doc.querySelectorAll('a');
      anchors.forEach(a => {
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
  const match = document.cookie.split('; ').find(row => row.startsWith(name + '='));
  if (!match) return null;
  return decodeURIComponent(match.split('=')[1] || '');
}

function getXsrfToken() {
  return getCookie('XSRF-TOKEN') || '';
}

function isUrlString(s) {
  return typeof s === 'string' && /^https?:\/\//.test(s.trim());
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

const canUpload = computed(() => {
  return !!(selectedFiles.value && selectedFiles.value.length) && !uploading.value;
});

const fileInput = ref(null);

function onFileInputChange(e) {
  const files = e.target && e.target.files ? Array.from(e.target.files) : [];
  console.log('onFileInputChange - files selected:', files.map(f=>f.name));
  // filter by max size and alert user for oversized files
  const accepted = [];
  for (const f of files) {
    if (f.size > MAX_UPLOAD_BYTES) {
      alert(`${f.name} は許容サイズ（${Math.round(MAX_UPLOAD_BYTES/1024/1024)}MB）を超えています。容量を軽くしてから再度アップロードしてください。`);
    } else {
      accepted.push(f);
    }
  }
  selectedFiles.value = accepted;
}

watch(selectedFiles, (val) => {
  console.log('selectedFiles watcher -> count=', val ? val.length : 0);
});

function debugClick() {
  console.log('debugClick called - canUpload=', canUpload.value, 'selectedFiles.length=', selectedFiles.value.length);
}

function getAttachmentUrl(meta) {
  if (!meta) return '';
  if (meta.url) {
    const safe = sanitizeUrl(meta.url);
    return safe || '';
  }
  if (meta.path) return `/bot/attachments?path=${encodeURIComponent(meta.path)}`;
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
        const res = await axios.get(url, { responseType: 'text' });
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

async function exportConversation() {
  // build payload similar to saveConversation
  if ((!messages.value || messages.value.length === 0) && !systemPrompt.value) return alert('エクスポートする会話がありません。');
  exporting.value = true;
  try {
    const firstUser = messages.value.find(m => m.user === '自分');
    const extractFirstN = (s, n) => { if (!s) return ''; return Array.from(s.trim()).slice(0, n).join(''); };
    const titlePrefix = extractFirstN(firstUser ? firstUser.text : '', 20) || `会話`;
    const payload = {
      title: titlePrefix,
      system_prompt: systemPrompt.value,
      messages: messages.value.map(m => ({ role: m.user === '自分' ? 'user' : (m.user === 'AI' ? 'assistant' : 'system'), content: m.text, meta: m.file ? { file: m.file } : null })),
      format: exportFormat.value || 'md'
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
    headers: { 'X-XSRF-TOKEN': getXsrfToken() }
  });
    const reply = res.data.reply || '(応答なし)';
    // mark temp as sent
    const idx = messages.value.findIndex(m => m.id === tempId);
    if (idx >= 0) messages.value.splice(idx, 1, { id: Date.now(), user: '自分', text });
  const aiMsg = { id: Date.now()+1, user: 'AI', text: reply };
  normalizeMessage(aiMsg);
  messages.value.push(aiMsg);
  // after successful send, clear selected uploaded files
  uploadedFiles.value = [];
  } catch (e) {
    messages.value.push({ id: Date.now()+2, user: 'AI', text: 'エラー: 応答を取得できませんでした' });
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
  console.log('uploadSelectedFiles called', selectedFiles.value);
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
    try {
      const res = await axios.post('/bot/files', form, {
        withCredentials: true,
        headers: { 'Content-Type': 'multipart/form-data', 'X-XSRF-TOKEN': getXsrfToken() },
        onUploadProgress: (ev) => {
          const pct = ev.total ? Math.round((ev.loaded / ev.total) * 100) : 0;
          uploadProgress.value[f.name] = pct;
        }
      });
      if (res.data && res.data.file) {
  const meta = res.data.file;
  uploadedFiles.value.push(meta);
  // show a simple chat message indicating upload succeeded (no thumbnail needed)
      const newMsg = { id: 'file-' + Date.now() + '-' + Math.floor(Math.random()*10000), user: '自分', text: `ファイルがアップされました\n${meta.original_name || meta.name || ''}`, isFileUpload: true, file: meta };
      normalizeMessage(newMsg);
      messages.value.push(newMsg);
  // ensure chat scrolls to show the new message
  await nextTick();
  scrollToBottom();
      }
    } catch (err) {
      console.error('upload failed', err);
    }
  }
  // clear selection after upload
  selectedFiles.value = [];
  uploading.value = false;
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

async function loadConversation(id) {
  try {
    const res = await axios.get(`/bot/history/${id}/json`);
    const conv = res.data;
    conversationId.value = conv.id;
    systemPrompt.value = conv.system_prompt || '';
    messages.value = conv.messages.map(m => {
      const user = m.role === 'user' ? '自分' : (m.role === 'assistant' ? 'AI' : 'system');
      // base message
      const msg = { id: m.id, user, text: m.content };
      // always try to normalize message content (cover cases where content is raw JSON string)
      try {
        // Attach meta.file if available
        const meta = m.meta || {};
        const fileMeta = meta.file || (meta.original_name ? meta : null);
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
    const firstUser = messages.value.find(m => m.user === '自分');
    const extractFirstN = (s, n) => {
      if (!s) return '';
      return Array.from(s.trim()).slice(0, n).join('');
    };
    const pad2 = (v) => (v < 10 ? '0' + v : v);
    const d = new Date();
    const ts = `${d.getFullYear()}/${d.getMonth()+1}/${d.getDate()} ${pad2(d.getHours())}:${pad2(d.getMinutes())}:${pad2(d.getSeconds())}`;
    const titlePrefix = extractFirstN(firstUser ? firstUser.text : '', 10) || `会話`;
    const title = `${titlePrefix}_${ts}`;

    const payload = {
      title: title,
      system_prompt: systemPrompt.value,
      messages: messages.value.map(m => ({ role: m.user === '自分' ? 'user' : (m.user === 'AI' ? 'assistant' : 'system'), content: m.text, meta: m.file ? { file: m.file } : null }))
    };
    if (conversationId.value) {
      const res = await axios.put(`/bot/history/${conversationId.value}`, payload, {
        withCredentials: true,
        headers: { 'X-XSRF-TOKEN': getXsrfToken() }
      });
      return res.data;
    } else {
      const res = await axios.post('/bot/history', payload, {
        withCredentials: true,
        headers: { 'X-XSRF-TOKEN': getXsrfToken() }
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
  // save current conversation then reset UI
  await saveConversation();
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
      messages: messages.value.map(m => ({ role: m.user === '自分' ? 'user' : (m.user === 'AI' ? 'assistant' : 'system'), content: m.text, meta: m.file ? { file: m.file } : null }))
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
        axios.post('/bot/history', payload, { withCredentials: true, headers: { 'X-XSRF-TOKEN': token } }).catch(()=>{});
      }
    } else {
      // best-effort async
      axios.post('/bot/history', payload, { withCredentials: true, headers: { 'X-XSRF-TOKEN': token } }).catch(()=>{});
    }
  } catch (err) {}
}

onMounted(() => {
  // check query param load_conversation
  try {
    const params = new URLSearchParams(window.location.search);
    const id = params.get('load_conversation');
    if (id) {
      loadConversation(id);
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
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">AIチャット</h2>
    </template>
    <div class="py-6">
      <div class="max-w-4xl mx-auto bg-white shadow rounded p-6 flex flex-col" style="height: calc(100vh - 160px);">
          <div class="mb-4 flex items-center justify-between">
          <div class="flex items-center gap-3">
            <!-- <a href="/chat" class="inline-flex items-center text-blue-600 hover:underline px-3 py-2">← チャットルーム一覧へ戻る</a> -->
            <button @click="startNewConversation" type="button" class="inline-flex items-center text-sm bg-gray-100 px-3 py-2 rounded border">新規会話を始める</button>
            <a href="/bot/history" class="inline-flex items-center text-sm bg-white px-3 py-2 rounded border hover:bg-gray-50">履歴を閲覧</a>
            <button type="button" @click="showPromptModal = true; console.log('header-floating: open prompt')" class="inline-flex items-center bg-indigo-600 text-white px-3 py-2 rounded shadow-lg">プロンプト編集</button>
            <button type="button" @click="showFileModal = true; console.log('header-floating: open file modal')" class="inline-flex items-center bg-green-600 text-white px-3 py-2 rounded shadow-lg">ファイルアップロード</button>
            <!-- Export controls -->
            <div class="flex items-center gap-2">
              <select v-model="exportFormat" class="border rounded px-2 py-1 text-sm">
                <option value="md">Markdown (.md)</option>
                <option value="txt">Text (.txt)</option>
                <option value="doc">Word (.doc)</option>
              </select>
              <button type="button" @click="exportConversation" :disabled="exporting" class="inline-flex items-center bg-yellow-600 text-white px-3 py-2 rounded shadow">エクスポート</button>
            </div>
          </div>
        </div>
        <div ref="messageArea" class="flex-1 overflow-y-auto border rounded p-4 mb-4 bg-gradient-to-b from-white to-gray-50 border-gray-200 shadow-inner">
          <div v-for="msg in messages" :key="msg.id" class="mb-3">
                  <div
                    :class="[ 'relative chat-bubble', msg.user === '自分' ? 'chat-bubble-own inline-block' : 'chat-bubble-other inline-block', msg.isFileUpload ? 'bg-blue-600 text-white w-4/5 cursor-pointer' : '' ]"
                    @click="msg.isFileUpload && msg.file ? openAttachment(msg.file) : null"
                  >
                    <div :class="['break-words text-sm leading-relaxed', msg.isFileUpload ? 'p-3 rounded' : '']">
                      <span :class="['font-semibold mr-2', msg.isFileUpload ? 'text-white' : '']">{{ msg.user }}</span>
                      <div :class="['whitespace-pre-line', msg.isFileUpload ? 'text-white' : '']" v-if="!msg.isFileUpload && msg.user === 'AI'" v-html="renderMarkdown(msg.text)"></div>
                      <div v-else :class="['whitespace-pre-line', msg.isFileUpload ? 'text-white' : '']">{{ msg.text }}</div>
                      <div v-if="msg.isFileUpload && msg.file" class="mt-2 text-xs italic text-white/80">クリックでファイルを表示</div>
                    </div>
                  </div>
          </div>
        </div>
        <form @submit.prevent="sendMessage" class="flex gap-2">
          <input v-model="newMessage" class="flex-1 border rounded px-3 py-2" placeholder="メッセージを入力..." />
          <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded" :disabled="loading">送信</button>
        </form>

        <!-- Prompt Modal -->
        <div v-if="showPromptModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
          <div class="bg-white rounded shadow-lg w-11/12 max-w-2xl p-4">
            <h3 class="font-semibold mb-2">システムプロンプトを編集</h3>
            <div class="text-sm text-gray-600 mb-3">
              <p>システムプロンプトはモデルに対する全体的な指示です。会話の口調、出力形式、守るべきルールなどをここで指定できます。</p>
              <p class="mt-2">例：</p>
              <div class="bg-gray-100 p-2 rounded text-xs mt-1">
                <div class="mb-1"><strong>例1：</strong>あなたは丁寧な日本語で答えるアシスタントです。専門用語には簡単な注釈を付けてください。</div>
                <div><strong>例2：</strong>箇条書きで要点を3つ以内にまとめ、最後に短い結論を書いてください。</div>
              </div>
            </div>
            <textarea v-model="systemPrompt" rows="6" class="w-full border rounded p-2"></textarea>
            <div class="mt-3 flex justify-end gap-2">
              <button @click="showPromptModal = false" class="px-3 py-1 rounded border">キャンセル</button>
              <button @click="showPromptModal = false" class="px-3 py-1 rounded bg-blue-600 text-white">保存</button>
            </div>
          </div>
        </div>

        <!-- File Upload Modal -->
        <div v-if="showFileModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
          <div class="bg-white rounded shadow-lg w-11/12 max-w-2xl p-4">
            <h3 class="font-semibold mb-2">ファイルをアップロード</h3>
            <div>
              <input ref="fileInput" type="file" multiple @change="onFileInputChange" class="cursor-pointer" />
              <div class="mt-2 text-xs text-gray-600">Debug: 選択ファイル {{ selectedFiles.length }} • canUpload: {{ canUpload }}</div>
            </div>
            <div class="mt-3">
              <div v-if="selectedFiles && selectedFiles.length">
                <div v-for="(f, i) in selectedFiles" :key="i" class="flex items-center justify-between border rounded p-2 mb-2">
                  <div class="text-sm">{{ f.name }} <span class="text-xs text-gray-500">({{ Math.round(f.size/1024) }} KB)</span></div>
                  <div class="text-sm text-gray-600">{{ f.type }}</div>
                </div>
              </div>
              <div v-else class="text-sm text-gray-500">選択されたファイルはありません。</div>
            </div>
            <div class="mt-3 flex justify-between items-center">
              <div class="text-sm text-gray-600">アップロード済み: {{ uploadedFiles.length }}</div>
                <div class="flex gap-2">
                <button type="button" @click="showFileModal = false" class="px-3 py-1 rounded border">閉じる</button>
                <button type="button" @click="uploadSelectedFiles" :disabled="!canUpload" class="px-3 py-1 rounded bg-blue-600 text-white cursor-pointer pointer-events-auto disabled:opacity-50 disabled:cursor-not-allowed">アップロード</button>
                <!-- テスト用: クリックハンドラが届いているかを確認するボタン -->
                <button type="button" @click="debugClick" class="px-2 py-1 rounded border text-sm">テストクリック</button>
              </div>
            </div>
            <div class="mt-3">
              <div v-if="uploading" class="text-sm text-gray-600">アップロード中...</div>
              <div v-for="(meta, idx) in uploadedFiles" :key="meta.path" class="mt-2 border rounded p-2">
                <div class="flex items-center justify-between">
                  <div>
                    <div class="font-medium">{{ meta.original_name }}</div>
                    <div class="text-xs text-gray-500">{{ meta.mime }} • {{ Math.round(meta.size/1024) }} KB</div>
                    <div v-if="meta.preview" class="text-xs mt-1 text-gray-700">{{ meta.preview }}</div>
                  </div>
                  <div class="ml-4">
                    <button @click="removeUploaded(idx)" class="text-sm text-red-600">削除</button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Attachment Modal -->
        <div v-if="showAttachmentModal" class="fixed inset-0 z-60 flex items-center justify-center bg-black/50">
          <div class="bg-white rounded shadow-lg w-11/12 max-w-3xl p-4 max-h-[90vh] overflow-auto">
            <div class="flex items-start justify-between">
              <div class="font-medium">{{ attachmentToShow ? attachmentToShow.original_name || attachmentToShow.name : '添付ファイル' }}</div>
              <div>
                <button @click="closeAttachment" class="px-2 py-1 border rounded">閉じる</button>
              </div>
            </div>
            <div class="mt-3">
              <template v-if="attachmentToShow">
                <!-- Image -->
                <img v-if="attachmentToShow.mime && attachmentToShow.mime.startsWith('image/')" :src="getAttachmentUrl(attachmentToShow)" class="w-full h-auto max-h-[70vh] object-contain" />

                <!-- PDF -->
                <iframe v-else-if="attachmentToShow.mime && attachmentToShow.mime.includes('pdf')" :src="getAttachmentUrl(attachmentToShow)" class="w-full h-[70vh] border" />

                <!-- Text-like -->
                <div v-else class="prose max-w-full">
                  <div v-if="attachmentToShow.preview" class="whitespace-pre-line text-sm text-gray-900">{{ attachmentToShow.preview }}</div>
                  <div v-else-if="loadingAttachment" class="text-sm text-gray-600">読み込み中...</div>
                  <pre v-else class="whitespace-pre-wrap text-sm text-gray-900">{{ attachmentContent }}</pre>
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
.chat-bubble .prose h1, .chat-bubble h1 { font-size: 1.25rem; font-weight: 700; }
.chat-bubble .prose h2, .chat-bubble h2 { font-size: 1.1rem; font-weight: 700; }
.chat-bubble pre { background: #0b0b0b; color: #e6e6e6; padding: 0.75rem; border-radius: 0.375rem; overflow:auto; }
.chat-bubble code { background:#f4f4f5; padding:0.2rem 0.35rem; border-radius:0.25rem; color:#111; }
.chat-bubble strong { font-weight: 700; }
.chat-bubble a { color: #3b82f6; }
</style>
