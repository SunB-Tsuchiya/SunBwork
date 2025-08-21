<script setup>
import { ref, watch, onMounted, onBeforeUnmount } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue'
import { useForm, Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import 'quill/dist/quill.snow.css';

import { QuillEditor } from '@vueup/vue-quill';
import { defaultToolbar } from '@/config/quillToolbar';
import axios from 'axios';

const props = defineProps({
  date: String
})

const content = ref('');
const form = useForm({
  date: props.date,
  content,
  files: []
});

// editor instance (for @ready)
let editorInstance = null;

// config
const MAX_UPLOAD_SIZE = 5 * 1024 * 1024; // 5MB per file (changeable)
const ALLOWED_EXT = ['jpg','jpeg','png','gif','pdf','docx','xlsx','txt'];
const MAX_IMAGE_WIDTH = 600; // px

function isAllowed(file) {
  const name = (file.name || '').toLowerCase();
  const ext = name.split('.').pop();
  if (!ext) return false;
  if (!ALLOWED_EXT.includes(ext)) return false;
  // prevent dangerous mime-types
  if (file.type && (file.type.includes('application/x-msdownload') || file.type.includes('application/x-sh'))) return false;
  return true;
}

function fileTooLarge(size) {
  return size > MAX_UPLOAD_SIZE;
}

// resize images (jpg/png) client-side
async function resizeImageFile(file) {
  if (!file.type.startsWith('image/')) return file;
  // load into image
  const img = await new Promise((res, rej) => {
    const url = URL.createObjectURL(file);
    const i = new Image();
    i.onload = () => { URL.revokeObjectURL(url); res(i); };
    i.onerror = rej;
    i.src = url;
  });
  const canvas = document.createElement('canvas');
  const ratio = Math.min(1, MAX_IMAGE_WIDTH / img.width);
  canvas.width = Math.round(img.width * ratio);
  canvas.height = Math.round(img.height * ratio);
  const ctx = canvas.getContext('2d');
  ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
  const mime = file.type === 'image/png' ? 'image/png' : 'image/jpeg';
  const blob = await new Promise(resolve => canvas.toBlob(resolve, mime, 0.8));
  // keep original filename
  const newFile = new File([blob], file.name, { type: blob.type });
  return newFile;
}

// insert base64 image into editor and add file to form.files
async function processAndInsertFile(file) {
  if (!isAllowed(file)) {
    alert(`許可されていないファイル形式です: ${file.name}`);
    return;
  }
  let working = file;
  if (file.type.startsWith('image/')) {
    working = await resizeImageFile(file);
  }
  if (fileTooLarge(working.size)) {
    alert(`ファイルが大きすぎます (最大 ${(MAX_UPLOAD_SIZE/1024/1024).toFixed(1)}MB): ${file.name}`);
    return;
  }

  // Upload to server immediately
  const fd = new FormData();
  fd.append('file', working);
  try {
    const res = await axios.post('/api/uploads', fd, { headers: { 'Content-Type': 'multipart/form-data' } });
    const attach = res.data;
    // insert placeholder with attachment id
    const placeholder = `[[attachment:${attach.id}:${attach.original_name}]]`;
    const idx = (editorInstance && editorInstance.getSelection && editorInstance.getSelection()?.index) || (editorInstance && editorInstance.getLength && editorInstance.getLength()) || 0;
    editorInstance.insertText(idx, placeholder);
    editorInstance.setSelection(idx + placeholder.length);

    // track in form.files as pending meta (the actual file already sent)
    form.files = [...(form.files || []), { id: attach.id, name: attach.original_name, status: attach.status }];

    // poll for status and replace placeholder when ready
    pollAttachmentAndReplace(attach.id, placeholder);
  } catch (e) {
    console.error('upload error', e);
    alert('ファイルのアップロードに失敗しました');
  }
}

function fileToDataURL(file) {
  return new Promise((res, rej) => {
    const r = new FileReader();
    r.onload = () => res(r.result);
    r.onerror = rej;
    r.readAsDataURL(file);
  });
}

async function pollAttachmentAndReplace(id, placeholder) {
  const maxAttempts = 30; // e.g., 30*2s = 60s
  let attempt = 0;
  const interval = 2000;
  const timer = setInterval(async () => {
    attempt++;
    try {
      const r = await axios.get(`/api/uploads/status/${id}`);
      if (r.data && r.data.status === 'ready') {
        // replace placeholder in editor with actual url or image
        const url = r.data.url;
        // find placeholder text position
        const contents = editorInstance.getContents();
        const plain = editorInstance.getText();
        const idx = plain.indexOf(placeholder);
        if (idx >= 0) {
          // delete placeholder length and insert link or image
          editorInstance.deleteText(idx, placeholder.length);
          if (r.data.mime && r.data.mime.startsWith('image/')) {
            editorInstance.insertEmbed(idx, 'image', url);
            editorInstance.setSelection(idx + 1);
          } else {
            editorInstance.insertText(idx, r.data.original_name, { link: url });
            editorInstance.setSelection(idx + r.data.original_name.length);
          }
        }
        // update form.files entry
        form.files = (form.files || []).map(f => f.id === id ? { ...f, status: 'ready', url } : f);
        clearInterval(timer);
      } else if (r.data && (r.data.status === 'failed' || r.data.status === 'rejected')) {
        alert(`アップロード処理に失敗しました: ${r.data.status}`);
        form.files = (form.files || []).filter(f => f.id !== id);
        // optionally remove placeholder
        const plain = editorInstance.getText();
        const pos = plain.indexOf(placeholder);
        if (pos >= 0) editorInstance.deleteText(pos, placeholder.length);
        clearInterval(timer);
      }
    } catch (err) {
      // ignore and retry
    }
    if (attempt >= maxAttempts) {
      clearInterval(timer);
      alert('アップロード処理がタイムアウトしました');
    }
  }, interval);
}

// handle dropped files
async function handleDrop(e) {
  const items = e.dataTransfer?.files || [];
  if (!items.length) return;
  for (let i=0;i<items.length;i++) {
    const f = items[i];
    try {
      await processAndInsertFile(f);
    } catch (err) {
      console.error('drop process error', err);
    }
  }
}

function handleDragOver(e) {
  e.dataTransfer.dropEffect = 'copy';
}

// Quill ready handler per ForQuillEditor guidelines
function handleEditorReady(editor) {
  editorInstance = editor;
  // if there is initial HTML content in props (for edit), convert and set
  if (props?.diary && props.diary.content) {
    try {
      const delta = editor.clipboard.convert(props.diary.content);
      editor.setContents(delta);
    } catch (e) {
      console.error('Quill convert error', e);
    }
  }
  // Ensure drops/pastes inside the Quill editor are intercepted so we can
  // upload files to the server instead of letting Quill embed base64 data.
  try {
    const root = editor.root;
    // drop handler
    const onDrop = async (e) => {
      if (!e || !e.dataTransfer) return;
      // prevent Quill's default embedding
      e.preventDefault();
      e.stopPropagation();
      // convert DataTransfer to FileList-like object and process
      await handleDrop(e);
    };
  root.addEventListener('drop', onDrop, true);

    // paste handler: if clipboard contains files, prevent default and upload
    const onPaste = async (e) => {
      const items = (e.clipboardData && e.clipboardData.items) || [];
      const files = [];
      for (let i = 0; i < items.length; i++) {
        const it = items[i];
        if (it.kind === 'file') {
          const f = it.getAsFile();
          if (f) files.push(f);
        }
      }
      if (files.length) {
        e.preventDefault();
        e.stopPropagation();
        for (const f of files) {
          try { await processAndInsertFile(f); } catch (err) { console.error('paste file upload', err); }
        }
      }
    };
  root.addEventListener('paste', onPaste, true);
    // keep references on editor for potential cleanup
    editor.__customDropHandler = onDrop;
    editor.__customPasteHandler = onPaste;
  } catch (err) {
    console.error('attach drop/paste handlers failed', err);
  }
}


const submit = () => {
  const html = form.content?.trim() || '';
  if (html === '' || html === '<p><br></p>' || html === '<p></p>') {
    form.content = '';
  }
  form.post(route('diaries.store'), { forceFormData: true })
}

function onInput(val) {
    // valがInputEventの場合はval.target.innerHTMLなどで取得
  if (typeof val === 'string') {
    form.content = val;
  } else if (val && val.target && val.target.innerHTML) {
    form.content = val.target.innerHTML;
  }
}
watch(() => form.content, (val) => {
  content.value = val;
});

function stripHtml(html) {
  if (!html) return '';
  return html.replace(/<[^>]+>/g, '').replace(/&nbsp;/g, ' ').replace(/&amp;/g, '&');
}
</script>


<template>
  <AppLayout title="日報作成">
    <div class="max-w-2xl mx-auto p-6 bg-white shadow rounded">
      <h1 class="text-2xl font-bold mb-4">日報作成 ({{ props.date }})</h1>
      <form @submit.prevent="submit">
        <div v-if="Object.keys(form.errors).length" class="mb-4 text-red-600">
          <ul>
          </ul>
        </div>
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">日付</label>
          <input type="date" v-model="form.date" class="w-full border rounded p-2" />
        </div>
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">内容</label>
          <div @drop.prevent="handleDrop" @dragover.prevent="handleDragOver" class="border rounded p-1 bg-white">
            <QuillEditor theme="snow"
            :toolbar="defaultToolbar"
            style="min-height:220px;height:220px;background:#fff"
            v-model="form.content"
            @input="onInput"
            @ready="handleEditorReady"
            />
            <div class="text-xs text-gray-500 mt-1">ここにファイルをドラッグ＆ドロップで添付できます（画像は自動で縮小して本文に埋め込みます）。</div>
          </div>
        </div>
        <!-- <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">入力内容がここに出ます（HTML）</label>
            <div class="p-2 bg-gray-100 border rounded min-h-[40px]">{{ stripHtml(form.content) }}</div>
        </div> -->
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">添付ファイル</label>
          <input type="file" multiple @change="e => Array.from(e.target.files).forEach(f => processAndInsertFile(f))" class="w-full border rounded p-2" />
          <div class="mt-2 text-sm text-gray-600">添付済み: <span v-if="form.files && form.files.length">{{ form.files.length }} 個</span><span v-else>0 個</span></div>
        </div>
        <div class="flex space-x-4">
          <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">保存</button>
          <Link :href="route('dashboard')" class="px-4 py-2 bg-gray-200 text-gray-700 rounded">キャンセル</Link>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<style scoped>
.editor-content {
  border-radius: 0.75rem;
  border: 1.5px solid #d1d5db;
  background: #fff;
  box-shadow: 0 2px 8px 0 #e5e7eb;
  padding: 1rem;
}
</style>
