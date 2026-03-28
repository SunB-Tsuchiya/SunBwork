<script setup>
import useToasts from '@/Composables/useToasts';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { QuillEditor } from '@vueup/vue-quill';
import axios from 'axios';
import 'quill/dist/quill.snow.css';
import { ref, watch } from 'vue';
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

const props = defineProps({
    date:              { type: String, default: '' },
    worktypes:         { type: Array,  default: () => [] },
    defaultWorktypeId: { type: Number, default: null },
});

function parseTime(t) {
    const parts = (t || '00:00').substring(0, 5).split(':');
    return { hour: parts[0] || '00', minute: parts[1] || '00' };
}

// ユーザー設定の基本勤務形態を優先し、なければ先頭を使う
const firstWt = (props.defaultWorktypeId
    ? props.worktypes.find((w) => w.id === props.defaultWorktypeId)
    : null) ?? props.worktypes[0] ?? null;
const defStart = parseTime(firstWt?.start_time ?? '08:00');
const defEnd   = parseTime(firstWt?.end_time   ?? '17:00');

const content = ref('');
const form = useForm({
    date:         props.date,
    work_style:   firstWt?.name ?? '',
    start_hour:   defStart.hour,
    start_minute: defStart.minute,
    end_hour:     defEnd.hour,
    end_minute:   defEnd.minute,
    start_time:   `${defStart.hour}:${defStart.minute}`,
    end_time:     `${defEnd.hour}:${defEnd.minute}`,
    content,
    files: [],
});

const hours   = Array.from({ length: 24 }, (_, i) => String(i).padStart(2, '0'));
const minutes = ['00', '15', '30', '45'];

function onWorktypeChange() {
    const wt = props.worktypes.find((w) => w.name === form.work_style);
    if (!wt) return;
    const s = parseTime(wt.start_time);
    const e = parseTime(wt.end_time);
    form.start_hour   = s.hour;
    form.start_minute = s.minute;
    form.end_hour     = e.hour;
    form.end_minute   = e.minute;
}

// 日付が変わったらサーバー側で既存日報の有無をチェック
// create() が既存日報を検出すれば edit へリダイレクト、なければ新日付でフォームを再表示
watch(
    () => form.date,
    (newDate, oldDate) => {
        if (!newDate || newDate === oldDate) return;
        router.visit(route('diaries.create') + '?date=' + newDate, {
            preserveScroll: true,
        });
    },
);

// UI state for tabs
// (tabs removed for Diaries.Create — moved to Events/Create)

// editor instance (for @ready)
let editorInstance = null;

// config
const MAX_UPLOAD_SIZE = 5 * 1024 * 1024; // 5MB per file (changeable)
const ALLOWED_EXT = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'docx', 'xlsx', 'txt'];
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
        alert(`ファイルが大きすぎます (最大 ${(MAX_UPLOAD_SIZE / 1024 / 1024).toFixed(1)}MB): ${file.name}`);
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
        const idx =
            (editorInstance && editorInstance.getSelection && editorInstance.getSelection()?.index) ||
            (editorInstance && editorInstance.getLength && editorInstance.getLength()) ||
            0;
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
                const url = r.data.url || r.data.public_url || null;
                const thumb = r.data.thumb_url || null;
                const preview = r.data.preview || null;
                // find placeholder text position
                const contents = editorInstance.getContents();
                const plain = editorInstance.getText();
                const idx = plain.indexOf(placeholder);
                if (idx >= 0) {
                    // delete placeholder length and insert link or image
                    editorInstance.deleteText(idx, placeholder.length);
                    if (r.data.mime && r.data.mime.startsWith('image/') && url) {
                        editorInstance.insertEmbed(idx, 'image', url);
                        editorInstance.setSelection(idx + 1);
                    } else if (url) {
                        editorInstance.insertText(idx, r.data.original_name || url, { link: url });
                        editorInstance.setSelection(idx + (r.data.original_name ? r.data.original_name.length : url.length || 0));
                    }
                }
                // update form.files entry with URL/thumbnail/preview when available
                form.files = (form.files || []).map((f) =>
                    f.id === id ? { ...f, status: 'ready', url, public_url: r.data.public_url || null, thumb_url: thumb, preview } : f,
                );
                clearInterval(timer);
            } else if (r.data && (r.data.status === 'failed' || r.data.status === 'rejected')) {
                alert(`アップロード処理に失敗しました: ${r.data.status}`);
                form.files = (form.files || []).filter((f) => f.id !== id);
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
                    try {
                        await processAndInsertFile(f);
                    } catch (err) {
                        console.error('paste file upload', err);
                    }
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

const { showToast, showValidationErrors } = useToasts();

const submit = () => {
    const html = form.content?.trim() || '';
    if (html === '' || html === '<p><br></p>' || html === '<p></p>') {
        form.content = '';
    }
    form.start_time = `${form.start_hour}:${form.start_minute}`;
    form.end_time = `${form.end_hour}:${form.end_minute}`;
    form.post(route('diaries.store'), {
        forceFormData: true,
        onStart: () => {
            try {
                showToast('送信中...', 'info', 1000);
            } catch (e) {}
        },
        onFinish: () => {},
        onSuccess: () => {
            try {
                showToast('保存しました', 'success', 1500);
            } catch (e) {}
        },
        onError: (errors) => {
            try {
                // show single combined validation message
                showValidationErrors(errors, 6000);
            } catch (e) {
                try {
                    showToast('保存に失敗しました', 'error', 4000);
                } catch (ee) {}
            }
        },
    });
};

function onInput(val) {
    // valがInputEventの場合はval.target.innerHTMLなどで取得
    if (typeof val === 'string') {
        form.content = val;
    } else if (val && val.target && val.target.innerHTML) {
        form.content = val.target.innerHTML;
    }
}
watch(
    () => form.content,
    (val) => {
        content.value = val;
    },
);

function stripHtml(html) {
    if (!html) return '';
    return html
        .replace(/<[^>]+>/g, '')
        .replace(/&nbsp;/g, ' ')
        .replace(/&amp;/g, '&');
}

// ===== 過去データから流用 =====
const showPastModal = ref(false);
const pastDateRange = ref('last');
const pastLoading = ref(false);
const pastError = ref('');
const pastRecords = ref([]);

const pastRangeOptions = [
    { value: 'last',  label: '前回' },
    { value: 'week',  label: '7日間' },
    { value: 'month', label: '30日間' },
];

function openPastModal() {
    showPastModal.value = true;
    if (pastRecords.value.length === 0) fetchPastDiaries();
}

function closePastModal() {
    showPastModal.value = false;
}

async function fetchPastDiaries() {
    pastLoading.value = true;
    pastError.value = '';
    try {
        const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const xsrfMatch = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
        const xsrf = xsrfMatch ? decodeURIComponent(xsrfMatch[1]) : null;
        const url = route('diaries.past_data') + '?date_range=' + pastDateRange.value;
        const res = await fetch(url, {
            credentials: 'same-origin',
            headers: {
                'X-CSRF-TOKEN': token,
                ...(xsrf ? { 'X-XSRF-TOKEN': xsrf } : {}),
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
        });
        if (!res.ok) throw new Error();
        const data = await res.json();
        pastRecords.value = data.records || [];
    } catch {
        pastError.value = 'データの取得に失敗しました。';
    } finally {
        pastLoading.value = false;
    }
}

watch(pastDateRange, () => {
    if (showPastModal.value) fetchPastDiaries();
});

function applyPastDiary(rec) {
    const html = rec.content ?? '';
    // form の値を直接セット（Quill の emit 経由に頼らない）
    form.content = html;
    content.value = html;
    // Quill の内部状態も直接更新
    if (editorInstance) {
        try {
            const delta = editorInstance.clipboard.convert(html);
            editorInstance.setContents(delta);
        } catch (e) {
            editorInstance.root.innerHTML = html;
        }
    }
    closePastModal();
}
</script>

<template>
    <AppLayout title="日報作成">
        <div class="rounded bg-white p-6 shadow">
            <div class="mb-4 flex items-center justify-between">
                <h1 class="text-2xl font-bold">日報作成 ({{ form.date }})</h1>
                <button type="button" @click="openPastModal" class="rounded bg-indigo-600 px-4 py-2 text-sm text-white hover:bg-indigo-700">過去データから流用</button>
            </div>

            <!-- single event form (tabs removed) -->
            <div>
                <form @submit.prevent="submit">
                    <div v-if="Object.keys(form.errors).length" class="mb-4 text-red-600">
                        <ul></ul>
                    </div>
                    <div class="mb-4 flex flex-wrap gap-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">日付</label>
                            <input type="date" v-model="form.date" class="rounded border p-2 text-sm" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">勤務形態</label>
                            <select v-model="form.work_style" class="rounded border p-2 text-sm" @change="onWorktypeChange">
                                <option v-for="wt in worktypes" :key="wt.id" :value="wt.name">{{ wt.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">始業時間</label>
                            <div class="flex items-center gap-1">
                                <select v-model="form.start_hour" class="w-16 rounded border p-2 text-sm">
                                    <option v-for="h in hours" :key="h" :value="h">{{ parseInt(h) }}時</option>
                                </select>
                                <select v-model="form.start_minute" class="w-16 rounded border p-2 text-sm">
                                    <option v-for="m in minutes" :key="m" :value="m">{{ m }}分</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">終業時間</label>
                            <div class="flex items-center gap-1">
                                <select v-model="form.end_hour" class="w-16 rounded border p-2 text-sm">
                                    <option v-for="h in hours" :key="h" :value="h">{{ parseInt(h) }}時</option>
                                </select>
                                <select v-model="form.end_minute" class="w-16 rounded border p-2 text-sm">
                                    <option v-for="m in minutes" :key="m" :value="m">{{ m }}分</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="mb-1 block text-sm font-medium text-gray-700">内容</label>
                        <div @drop.prevent="handleDrop" @dragover.prevent="handleDragOver" class="rounded border bg-white p-1">
                            <QuillEditor
                                theme="snow"
                                :toolbar="simpleToolbar"
                                style="min-height: 220px; height: 220px; background: #fff"
                                v-model="form.content"
                                @input="onInput"
                                @ready="handleEditorReady"
                            />
                            <div class="mt-1 text-xs text-gray-500">
                                ここにファイルをドラッグ＆ドロップで添付できます（画像は自動で縮小して本文に埋め込みます）。
                            </div>
                        </div>
                    </div>
                    <!-- <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">入力内容がここに出ます（HTML）</label>
            <div class="p-2 bg-gray-100 border rounded min-h-[40px]">{{ stripHtml(form.content) }}</div>
        </div> -->
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
                        <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-white">保存</button>
                        <Link :href="route('dashboard')" class="rounded bg-gray-200 px-4 py-2 text-gray-700">キャンセル</Link>
                    </div>
                </form>
            </div>

            <!-- job tab removed from Diaries.Create.vue -->
        </div>
    </AppLayout>

    <!-- 過去データから流用 モーダル -->
    <div v-if="showPastModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="closePastModal">
        <div class="relative mx-4 flex max-h-[85vh] w-full max-w-2xl flex-col rounded-lg bg-white shadow-xl">
            <!-- ヘッダー -->
            <div class="flex items-center justify-between border-b px-6 py-4">
                <h2 class="text-lg font-semibold text-gray-800">過去の日報から流用</h2>
                <button @click="closePastModal" class="text-gray-500 hover:text-gray-700">✕</button>
            </div>

            <!-- 期間選択 -->
            <div class="border-b px-6 py-3">
                <div class="flex gap-2">
                    <button
                        v-for="opt in pastRangeOptions"
                        :key="opt.value"
                        @click="pastDateRange = opt.value"
                        :class="pastDateRange === opt.value ? 'bg-blue-600 text-white' : 'border text-gray-700 hover:bg-gray-100'"
                        class="rounded px-4 py-1.5 text-sm"
                    >{{ opt.label }}</button>
                </div>
                <button
                    @click="fetchPastDiaries"
                    :disabled="pastLoading"
                    class="mt-3 rounded bg-blue-600 px-4 py-2 text-sm text-white hover:bg-blue-700 disabled:opacity-60"
                >{{ pastLoading ? '取得中...' : '検索' }}</button>
            </div>

            <!-- 結果 -->
            <div class="flex-1 overflow-y-auto px-6 py-4">
                <p v-if="pastError" class="mb-3 text-sm text-red-600">{{ pastError }}</p>
                <p v-if="!pastLoading && pastRecords.length === 0" class="py-8 text-center text-sm text-gray-400">該当する日報がありません。</p>
                <table v-if="pastRecords.length > 0" class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 w-28">日付</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">内容（プレビュー）</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr
                            v-for="rec in pastRecords"
                            :key="rec.id"
                            @click="applyPastDiary(rec)"
                            class="cursor-pointer hover:bg-blue-50"
                        >
                            <td class="px-3 py-2 text-gray-600">{{ rec.date }}</td>
                            <td class="px-3 py-2 text-gray-700">{{ rec.content_preview || '（内容なし）' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- フッター -->
            <div class="border-t px-6 py-3 text-right">
                <button @click="closePastModal" class="rounded bg-gray-200 px-4 py-2 text-sm text-gray-700 hover:bg-gray-300">閉じる</button>
            </div>
        </div>
    </div>
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
