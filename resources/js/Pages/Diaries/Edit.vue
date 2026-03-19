<script setup>
import useToasts from '@/Composables/useToasts';
import AppLayout from '@/layouts/AppLayout.vue';
import { router, useForm } from '@inertiajs/vue3';
import { QuillEditor } from '@vueup/vue-quill';
import '@vueup/vue-quill/dist/vue-quill.snow.css';
import axios from 'axios';
import { onMounted, ref } from 'vue';
import { route } from 'ziggy-js';
// original toolbar configuration (kept for later use):
// import { defaultToolbar } from '@/config/quillToolbar';

// default-like toolbar (image insertion intentionally removed)
const simpleToolbar = [
    [{ header: [1, 2, 3, false] }],
    ['bold', 'italic', 'underline', 'strike'],
    [{ list: 'ordered' }, { list: 'bullet' }, { indent: '-1' }, { indent: '+1' }],
    ['blockquote', 'code-block'],
    [{ align: [] }],
    ['clean'],
];

const props = defineProps({ diary: Object });
const content = ref('');
let editorInstance = null;

onMounted(() => {
    // mounted
});

function handleEditorReady(editor) {
    editorInstance = editor;
    const delta = editor.clipboard.convert(props.diary.content || '<p>初期値がありません</p>');
    editor.setContents(delta);
    try {
        const html = editor.root && editor.root.innerHTML ? editor.root.innerHTML : props.diary.content || '';
        content.value = html;
        if (typeof form !== 'undefined') {
            form.content = html;
        }
    } catch (e) {
        // ignore
    }

    // attach drop/paste handlers to intercept files and upload them
    try {
        const root = editor.root;
        const onDrop = async (e) => {
            if (!e || !e.dataTransfer) return;
            e.preventDefault();
            e.stopPropagation();
            await handleDrop(e);
        };
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
                    try {
                        await processAndInsertFile(f);
                    } catch (err) {
                        console.error('paste file upload', err);
                    }
                }
            }
        };
        root.addEventListener('drop', onDrop, true);
        root.addEventListener('paste', onPaste, true);
        editor.__customDropHandler = onDrop;
        editor.__customPasteHandler = onPaste;
    } catch (err) {
        console.error('attach drop/paste handlers failed', err);
    }
}

const { showToast } = useToasts();

const isSubmitting = ref(false);

const form = useForm({
    content: content.value,
    files: [],
});

// Upload config and helpers (copied/adapted from Create.vue)
const MAX_UPLOAD_SIZE = 5 * 1024 * 1024; // 5MB
const ALLOWED_EXT = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'docx', 'xlsx', 'txt'];
const MAX_IMAGE_WIDTH = 600; // px

function isAllowed(file) {
    const name = (file.name || '').toLowerCase();
    const ext = name.split('.').pop();
    if (!ext) return false;
    if (!ALLOWED_EXT.includes(ext)) return false;
    if (file.type && (file.type.includes('application/x-msdownload') || file.type.includes('application/x-sh'))) return false;
    return true;
}

function fileTooLarge(size) {
    return size > MAX_UPLOAD_SIZE;
}

async function resizeImageFile(file) {
    if (!file.type.startsWith('image/')) return file;
    const img = await new Promise((res, rej) => {
        const url = URL.createObjectURL(file);
        const i = new Image();
        i.onload = () => {
            URL.revokeObjectURL(url);
            res(i);
        };
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
    const blob = await new Promise((resolve) => canvas.toBlob(resolve, mime, 0.8));
    const newFile = new File([blob], file.name, { type: blob.type });
    return newFile;
}

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
        alert(`ファイルが大きすぎます (最大 ${(MAX_UPLOAD_SIZE / 1024 / 1024).toFixed(1)}MB): ${file.name}`);
        return;
    }

    const fd = new FormData();
    fd.append('file', working);
    try {
        const res = await axios.post('/api/uploads', fd, { headers: { 'Content-Type': 'multipart/form-data' } });
        const attach = res.data;
        const placeholder = `[[attachment:${attach.id}:${attach.original_name}]]`;
        const idx =
            (editorInstance && editorInstance.getSelection && editorInstance.getSelection()?.index) ||
            (editorInstance && editorInstance.getLength && editorInstance.getLength()) ||
            0;
        if (editorInstance && editorInstance.insertText) {
            editorInstance.insertText(idx, placeholder);
            editorInstance.setSelection(idx + placeholder.length);
        }
        form.files = [...(form.files || []), { id: attach.id, name: attach.original_name, status: attach.status }];
        pollAttachmentAndReplace(attach.id, placeholder);
    } catch (e) {
        console.error('upload error', e);
        alert('ファイルのアップロードに失敗しました');
    }
}

async function pollAttachmentAndReplace(id, placeholder) {
    const maxAttempts = 30;
    let attempt = 0;
    const interval = 2000;
    const timer = setInterval(async () => {
        attempt++;
        try {
            const r = await axios.get(`/api/uploads/status/${id}`);
            if (r.data && r.data.status === 'ready') {
                const url = r.data.url;
                const plain = editorInstance.getText();
                const idx = plain.indexOf(placeholder);
                if (idx >= 0) {
                    editorInstance.deleteText(idx, placeholder.length);
                    if (r.data.mime && r.data.mime.startsWith('image/')) {
                        editorInstance.insertEmbed(idx, 'image', url);
                        editorInstance.setSelection(idx + 1);
                    } else {
                        editorInstance.insertText(idx, r.data.original_name, { link: url });
                        editorInstance.setSelection(idx + r.data.original_name.length);
                    }
                }
                form.files = (form.files || []).map((f) => (f.id === id ? { ...f, status: 'ready', url } : f));
                clearInterval(timer);
            } else if (r.data && (r.data.status === 'failed' || r.data.status === 'rejected')) {
                alert(`アップロード処理に失敗しました: ${r.data.status}`);
                form.files = (form.files || []).filter((f) => f.id !== id);
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
    for (let i = 0; i < items.length; i++) {
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

const submit = () => {
    console.debug('Diary Edit: submit called');
    try {
        if (editorInstance && editorInstance.root && editorInstance.root.innerHTML !== undefined) {
            form.content = editorInstance.root.innerHTML;
        } else {
            form.content = content.value;
        }
    } catch (e) {
        form.content = content.value;
    }
    // If there are files, Inertia will send multipart/form-data.
    // Only force FormData when files exist to avoid surprising server parsing.
    const hasFiles = form.files && Array.isArray(form.files) && form.files.length > 0;
    console.debug('Diary Edit: submitting', { date: props.diary.date, contentLength: (form.content || '').length, hasFiles });
    // When sending files with a PUT/PATCH verb, PHP may not parse multipart PUT bodies.
    // Workaround: if there are files, send as POST with _method=PUT and forceFormData=true.
    if (hasFiles) {
        // Build FormData and POST with method override to avoid multipart PUT parsing issues
        const url = route('diaries.update', props.diary.id);
        const fd = new FormData();
        fd.append('_method', 'PUT');
        // include main fields expected by quick-path: content and date
        fd.append('content', form.content || '');
        if (form.date) fd.append('date', form.date);
        // If there are actual File objects to send (unlikely here because files are uploaded immediately), append them
        try {
            // if form.rawFiles exists (not used by default), append
            if (form.rawFiles && Array.isArray(form.rawFiles)) {
                form.rawFiles.forEach((f, i) => fd.append('files[' + i + ']', f));
            }
        } catch (e) {}

        // set submitting state and send via axios
        isSubmitting.value = true;
        try {
            showToast('送信中...', 'info', 1000);
        } catch (e) {}
        axios
            .post(url, fd, { headers: { 'Content-Type': 'multipart/form-data' } })
            .then((res) => {
                try {
                    showToast('更新しました', 'success', 1500);
                } catch (e) {}
                setTimeout(() => {
                    try {
                        router.get(route('calendar.index'));
                    } catch (e) {
                        router.get(route('events.index'));
                    }
                }, 300);
            })
            .catch((err) => {
                if (err.response && err.response.status === 422 && err.response.data && err.response.data.errors) {
                    try {
                        const { showValidationErrors } = useToasts();
                        showValidationErrors(err.response.data.errors, 6000);
                    } catch (e) {
                        showToast('更新に失敗しました', 'error', 4000);
                    }
                } else {
                    showToast('更新に失敗しました', 'error', 4000);
                }
            })
            .finally(() => {
                isSubmitting.value = false;
            });
    } else {
        // no files: use normal Inertia form.put to preserve Inertia behaviour
        form.put(route('diaries.update', props.diary.id), {
            forceFormData: false,
            onStart: () => {
                console.debug('Diary Edit: onStart');
                isSubmitting.value = true;
                try {
                    showToast('送信中...', 'info', 1000);
                } catch (e) {}
            },
            onFinish: () => {
                console.debug('Diary Edit: onFinish');
                isSubmitting.value = false;
            },
            onSuccess: () => {
                console.debug('Diary Edit: onSuccess');
                try {
                    showToast('更新しました', 'success', 1500);
                } catch (e) {}
                setTimeout(() => {
                    try {
                        router.get(route('calendar.index'));
                    } catch (e) {
                        router.get(route('events.index'));
                    }
                }, 300);
            },
            onError: (errors) => {
                console.debug('Diary Edit: onError', errors);
                try {
                    const { showValidationErrors } = useToasts();
                    showValidationErrors(errors, 6000);
                } catch (e) {
                    try {
                        showToast('更新に失敗しました', 'error', 4000);
                    } catch (ee) {}
                } finally {
                    isSubmitting.value = false;
                }
            },
        });
    }
};

const back = () => {
    try {
        const qp = new URLSearchParams(window.location.search || '');
        const returnTo = qp.get('return_to');
        if (returnTo) {
            window.location.href = decodeURIComponent(returnTo);
            return;
        }
    } catch (e) {
        // ignore
    }
    try {
        if (window.history && window.history.length > 1) {
            window.history.back();
            return;
        }
    } catch (e) {
        // ignore
    }
    try {
        router.get(route('diaries.show', props.diary.id));
        return;
    } catch (e) {
        router.get(route('diaries.index'));
    }
};
</script>

<template>
    <AppLayout title="日報編集">
        <div class="rounded bg-white p-6 shadow">
            <h1 class="mb-4 text-2xl font-bold">日報編集 ({{ props.diary.date }})</h1>
            <form @submit.prevent="submit">
                <div class="mb-4">
                    <label class="mb-1 block text-sm font-medium text-gray-700">内容</label>
                    <QuillEditor
                        theme="snow"
                        :toolbar="simpleToolbar"
                        style="min-height: 180px; height: 180px; background: #fff"
                        v-model="content"
                        @ready="handleEditorReady"
                    />
                </div>
                <div class="mb-4">
                    <label class="mb-1 block text-sm font-medium text-gray-700">添付ファイル</label>
                    <input
                        type="file"
                        multiple
                        @change="(e) => Array.from(e.target.files).forEach((f) => processAndInsertFile(f))"
                        class="w-full rounded border p-2"
                    />
                    <div class="mt-2 text-sm text-gray-600">
                        添付済み: <span v-if="form.files && form.files.length">{{ form.files.length }} 個</span><span v-else>0 個</span>
                    </div>
                </div>
                <div class="flex space-x-4">
                    <button :disabled="isSubmitting" type="submit" class="rounded bg-blue-600 px-4 py-2 text-white disabled:opacity-60">
                        <span v-if="isSubmitting">送信中...</span>
                        <span v-else>更新</span>
                    </button>
                    <button @click.prevent="back" class="rounded bg-gray-200 px-4 py-2 text-gray-700">戻る</button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
