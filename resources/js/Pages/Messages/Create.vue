<template>
    <AppLayout title="メール作成">
        <template #header>
            <h2 class="text-xl font-semibold">新規メール作成</h2>
        </template>

        <template #actions>
            <div class="flex items-center gap-2">
                <Link :href="route('messages.index')" class="text-sm text-gray-600 hover:text-gray-900">← 受信箱へ戻る</Link>
            </div>
        </template>

        <main>
            <div class="py-12">
                <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div class="bg-white p-6 shadow-xl sm:rounded-lg">
                        <form @submit.prevent="submit" class="mx-auto grid max-w-3xl grid-cols-1 gap-6">
                            <div v-if="prefillLoading" class="rounded-md border border-yellow-100 bg-yellow-50 p-2 text-sm text-yellow-700">
                                受信者情報を読み込み中... 少々お待ちください
                            </div>
                            <!-- トースト通知（右上）: prefillErrors はトーストで表示する -->
                            <!-- トースト本体はページ上部に固定でレンダリングします -->
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">To</label>
                                <div class="mt-1">
                                    <div class="mb-2 flex flex-wrap gap-2">
                                        <span
                                            v-for="u in to"
                                            :key="u.id"
                                            class="inline-flex items-center gap-2 rounded-full bg-gray-100 px-3 py-1 text-sm"
                                        >
                                            {{ u.name }} <button type="button" @click="removeSelected('to', u.id)" class="text-gray-500">✕</button>
                                        </span>
                                    </div>
                                    <input
                                        v-model="toQuery"
                                        @input="searchUsers('to')"
                                        placeholder="ユーザー名で検索"
                                        class="mt-1 w-full rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-400"
                                    />
                                    <ul
                                        v-if="toCandidates.length"
                                        class="mt-1 max-h-48 overflow-auto rounded-md border border-gray-100 bg-white shadow"
                                    >
                                        <li
                                            v-for="c in toCandidates"
                                            :key="c.id"
                                            class="cursor-pointer p-2 hover:bg-gray-50"
                                            @click="selectUser('to', c)"
                                        >
                                            {{ c.name }}
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">CC</label>
                                <div class="mt-1">
                                    <div class="mb-2 flex flex-wrap gap-2">
                                        <span
                                            v-for="u in cc"
                                            :key="u.id"
                                            class="inline-flex items-center gap-2 rounded-full bg-gray-100 px-3 py-1 text-sm"
                                        >
                                            {{ u.name }} <button type="button" @click="removeSelected('cc', u.id)" class="text-gray-500">✕</button>
                                        </span>
                                    </div>
                                    <input
                                        v-model="ccQuery"
                                        @input="searchUsers('cc')"
                                        placeholder="ユーザー名で検索"
                                        class="mt-1 w-full rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-400"
                                    />
                                    <ul
                                        v-if="ccCandidates.length"
                                        class="mt-1 max-h-48 overflow-auto rounded-md border border-gray-100 bg-white shadow"
                                    >
                                        <li
                                            v-for="c in ccCandidates"
                                            :key="c.id"
                                            class="cursor-pointer p-2 hover:bg-gray-50"
                                            @click="selectUser('cc', c)"
                                        >
                                            {{ c.name }}
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">BCC</label>
                                <div class="mt-1">
                                    <div class="mb-2 flex flex-wrap gap-2">
                                        <span
                                            v-for="u in bcc"
                                            :key="u.id"
                                            class="inline-flex items-center gap-2 rounded-full bg-gray-100 px-3 py-1 text-sm"
                                        >
                                            {{ u.name }} <button type="button" @click="removeSelected('bcc', u.id)" class="text-gray-500">✕</button>
                                        </span>
                                    </div>
                                    <input
                                        v-model="bccQuery"
                                        @input="searchUsers('bcc')"
                                        placeholder="ユーザー名で検索"
                                        class="mt-1 w-full rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-400"
                                    />
                                    <ul
                                        v-if="bccCandidates.length"
                                        class="mt-1 max-h-48 overflow-auto rounded-md border border-gray-100 bg-white shadow"
                                    >
                                        <li
                                            v-for="c in bccCandidates"
                                            :key="c.id"
                                            class="cursor-pointer p-2 hover:bg-gray-50"
                                            @click="selectUser('bcc', c)"
                                        >
                                            {{ c.name }}
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">件名</label>
                                <input
                                    v-model="subject"
                                    class="mt-1 w-full rounded-md border border-gray-200 px-3 py-2 text-lg text-sm font-medium text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-400"
                                />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">本文</label>
                                <div @drop.prevent="handleDrop" @dragover.prevent="handleDragOver" class="mt-1">
                                    <textarea
                                        ref="bodyEl"
                                        v-model="body"
                                        class="resize-vertical mt-1 h-56 w-full rounded-md border border-gray-200 p-3 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-400"
                                    />
                                    <div class="mt-2 text-xs text-gray-500">
                                        ここにファイルをドラッグ＆ドロップで添付できます（画像は自動で縮小して本文に挿入されます）。
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">添付ファイル</label>
                                <input type="file" multiple @change="onFiles" class="mt-1 w-full rounded-md border border-gray-200 px-3 py-2" />
                                <div class="mt-2 text-sm text-gray-600">
                                    添付済み: <span>{{ attachments && attachments.length ? attachments.length : 0 }} 個</span>
                                </div>
                                <table class="mt-3 w-full text-sm">
                                    <thead>
                                        <tr class="text-left text-xs text-gray-500">
                                            <th class="py-2">名前</th>
                                            <th class="py-2">サイズ</th>
                                            <th class="py-2">タイプ</th>
                                            <th class="py-2">状態</th>
                                            <th class="py-2"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="a in attachments" :key="a.id || a.tempId" class="border-t">
                                            <td class="py-2">{{ a.original_name }}</td>
                                            <td class="py-2 text-xs text-gray-500">{{ a.size ? (a.size / 1024).toFixed(1) + ' KB' : '-' }}</td>
                                            <td class="py-2 text-xs text-gray-500">{{ a.mime_type || '-' }}</td>
                                            <td class="py-2 text-xs text-gray-500">{{ a.status || '-' }}</td>
                                            <td class="py-2 text-right">
                                                <button type="button" @click="removeAttachment(a.id)" class="text-xs text-red-600">削除</button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-4 flex items-center justify-between">
                                <div>
                                    <Link :href="route('messages.index')" class="text-sm text-gray-600 hover:text-gray-900">← 一覧へ戻る</Link>
                                </div>
                                <div class="space-x-2 text-right">
                                    <button
                                        type="button"
                                        @click="saveDraft"
                                        class="inline-flex items-center gap-2 rounded-md bg-gray-200 px-4 py-2 text-gray-800"
                                    >
                                        保存
                                    </button>
                                    <button
                                        type="button"
                                        @click="confirmDiscard"
                                        class="inline-flex items-center gap-2 rounded-md border bg-white px-4 py-2 text-red-600"
                                    >
                                        破棄
                                    </button>
                                    <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-5 py-2 text-white shadow">
                                        送信
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
        <!-- Toast container (fixed top-right) -->
        <div class="fixed right-4 top-4 z-50" aria-live="polite">
            <div class="space-y-2">
                <div v-for="t in toasts" :key="t.id" :class="toastClass(t.type)" class="max-w-sm">
                    <div class="flex items-start justify-between gap-3 p-3">
                        <div class="text-left text-sm" v-html="t.message"></div>
                        <button @click="dismissToast(t.id)" class="ml-3 text-xs text-gray-500">✕</button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import { onMounted, ref } from 'vue';

const to = ref([]);
const cc = ref([]);
const bcc = ref([]);
const toQuery = ref('');
const ccQuery = ref('');
const bccQuery = ref('');
const toCandidates = ref([]);
const ccCandidates = ref([]);
const bccCandidates = ref([]);
const subject = ref('');
const body = ref('');
const attachments = ref([]);
const prefillLoading = ref(false);
const prefillErrors = ref([]);
// Toast state (top-right short messages)
const toasts = ref([]);

function nextToastId() {
    return Date.now().toString(36) + Math.random().toString(36).slice(2, 8);
}

function showToast(message, type = 'info', timeout = 4000) {
    const id = nextToastId();
    toasts.value.push({ id, message, type });
    if (timeout > 0) {
        setTimeout(() => dismissToast(id), timeout);
    }
}

function dismissToast(id) {
    toasts.value = toasts.value.filter((t) => t.id !== id);
}

function toastClass(type) {
    if (type === 'error') return 'rounded-md bg-red-50 border border-red-100 shadow';
    if (type === 'success') return 'rounded-md bg-green-50 border border-green-100 shadow';
    return 'rounded-md bg-white border border-gray-100 shadow';
}

// textarea ref for cursor/selection operations
const bodyEl = ref(null);

// config (match Diaries)
const MAX_UPLOAD_SIZE = 5 * 1024 * 1024; // 5MB
const ALLOWED_EXT = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'docx', 'xlsx', 'txt'];

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

// image resize (simple: use canvas)
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
    const MAX_IMAGE_WIDTH = 600;
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

async function fileToDataURL(file) {
    return new Promise((res, rej) => {
        const r = new FileReader();
        r.onload = () => res(r.result);
        r.onerror = rej;
        r.readAsDataURL(file);
    });
}

// process file: validate, resize image, upload, track attachment in table (do NOT insert into body)
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
        // track attachment in table (server returned id and status)
        attachments.value = [
            ...(attachments.value || []),
            {
                id: attach.id,
                original_name: attach.original_name,
                status: attach.status || 'uploading',
                size: attach.size || working.size,
                mime_type: attach.mime || working.type,
                url: attach.url || null,
            },
        ];

        // start polling for status (if not already ready)
        if (!attach.status || attach.status !== 'ready') {
            pollAttachmentAndReplace(attach.id);
        }
    } catch (e) {
        console.error('upload error', e);
        alert('ファイルのアップロードに失敗しました');
    }
}

async function pollAttachmentAndReplace(id) {
    const maxAttempts = 30;
    let attempt = 0;
    const interval = 2000;
    const timer = setInterval(async () => {
        attempt++;
        try {
            const r = await axios.get(`/api/uploads/status/${id}`);
            if (r.data && r.data.status === 'ready') {
                const url = r.data.url;
                attachments.value = (attachments.value || []).map((f) =>
                    f.id === id ? { ...f, status: 'ready', url, mime_type: r.data.mime || f.mime_type, size: r.data.size || f.size } : f,
                );
                clearInterval(timer);
            } else if (r.data && (r.data.status === 'failed' || r.data.status === 'rejected')) {
                alert(`アップロード処理に失敗しました: ${r.data.status}`);
                attachments.value = (attachments.value || []).filter((f) => f.id !== id);
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

async function removeAttachment(id) {
    try {
        // optimistically remove locally
        attachments.value = (attachments.value || []).filter((f) => f.id !== id && f.tempId !== id);
        // try server-side delete if endpoint exists
        try {
            await axios.delete(`/api/uploads/${id}`);
        } catch (e) {
            // ignore server delete failures
        }
    } catch (e) {}
}

// drag/drop handlers
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

// wire file input to processing
function onFiles(e) {
    const files = e.target.files;
    for (let i = 0; i < files.length; i++) {
        processAndInsertFile(files[i]);
    }
}

// keep user search functions (use route global in template via Ziggy)
async function searchUsers(kind) {
    const q = kind === 'to' ? toQuery.value : kind === 'cc' ? ccQuery.value : bccQuery.value;
    if (!q || q.length < 1) {
        if (kind === 'to') toCandidates.value = [];
        if (kind === 'cc') ccCandidates.value = [];
        if (kind === 'bcc') bccCandidates.value = [];
        return;
    }
    try {
        const res = await axios.get(route('users.search'), { params: { q } });
        if (kind === 'to') toCandidates.value = res.data;
        if (kind === 'cc') ccCandidates.value = res.data;
        if (kind === 'bcc') bccCandidates.value = res.data;
    } catch (e) {
        console.error(e);
    }
}

// fetch single user by id (tries route('users.show') then /api/users/:id)
async function fetchUserById(id) {
    if (!id) return null;
    try {
        const res = await axios.get(route('users.show', id));
        return res.data;
    } catch (e) {
        try {
            const res2 = await axios.get(`/api/users/${id}`);
            return res2.data;
        } catch (e2) {
            return null;
        }
    }
}

async function prefillFromQuery() {
    const batchSize = 6;
    function chunkArray(arr, size) {
        const out = [];
        for (let i = 0; i < arr.length; i += size) out.push(arr.slice(i, i + size));
        return out;
    }
    try {
        const params = new URLSearchParams(window.location.search);
        const toParam = params.get('to');
        const ccParam = params.get('cc');
        const bccParam = params.get('bcc');
        prefillLoading.value = false;
        const processIds = async (ids, targetArray) => {
            if (!ids.length) return;
            const chunks = chunkArray(ids, batchSize);
            for (const chunk of chunks) {
                const promises = chunk.map((id) => fetchUserById(id).catch(() => null));
                const users = await Promise.all(promises);
                for (let i = 0; i < chunk.length; i++) {
                    const u = users[i];
                    const id = chunk[i];
                    if (u) {
                        if (!targetArray.value.find((x) => x.id === u.id)) targetArray.value.push(u);
                    } else {
                        if (!prefillErrors.value.includes(id)) {
                            prefillErrors.value.push(id);
                            // show a short-lived toast for the failed id
                            showToast(`受信者情報の取得に失敗しました: ${id}`, 'error', 6000);
                        }
                    }
                }
            }
        };
        prefillLoading.value = true;
        if (toParam) {
            const ids = toParam
                .split(',')
                .map((s) => s.trim())
                .filter(Boolean);
            await processIds(ids, to);
        }
        if (ccParam) {
            const ids = ccParam
                .split(',')
                .map((s) => s.trim())
                .filter(Boolean);
            await processIds(ids, cc);
        }
        if (bccParam) {
            const ids = bccParam
                .split(',')
                .map((s) => s.trim())
                .filter(Boolean);
            await processIds(ids, bcc);
        }
    } catch (err) {
        // ignore
    } finally {
        prefillLoading.value = false;
    }
}

function clearPrefillErrors() {
    prefillErrors.value = [];
}

function selectUser(kind, user) {
    if (kind === 'to') {
        if (!to.value.find((u) => u.id === user.id)) to.value.push(user);
        toQuery.value = '';
        toCandidates.value = [];
    }
    if (kind === 'cc') {
        if (!cc.value.find((u) => u.id === user.id)) cc.value.push(user);
        ccQuery.value = '';
        ccCandidates.value = [];
    }
    if (kind === 'bcc') {
        if (!bcc.value.find((u) => u.id === user.id)) bcc.value.push(user);
        bccQuery.value = '';
        bccCandidates.value = [];
    }
}

function removeSelected(kind, id) {
    if (kind === 'to') to.value = to.value.filter((u) => u.id !== id);
    if (kind === 'cc') cc.value = cc.value.filter((u) => u.id !== id);
    if (kind === 'bcc') bcc.value = bcc.value.filter((u) => u.id !== id);
}

function submit() {
    const payload = {
        to: to.value.map((u) => u.id),
        cc: cc.value.map((u) => u.id),
        bcc: bcc.value.map((u) => u.id),
        subject: subject.value,
        body: body.value,
        attachments: attachments.value.map((a) => a.id),
    };
    router.post(route('messages.store'), payload);
}

function saveDraft() {
    const payload = {
        to: to.value.map((u) => u.id),
        cc: cc.value.map((u) => u.id),
        bcc: bcc.value.map((u) => u.id),
        subject: subject.value,
        body: body.value,
        attachments: attachments.value.map((a) => a.id),
        save_as: 'draft',
    };
    // use post to reuse same endpoint; server will handle save_as=draft
    router.post(route('messages.store'), payload, {
        onSuccess: () => showToast('下書きとして保存しました', 'success', 3000),
    });
}

function confirmDiscard() {
    if (!confirm('この下書きを破棄してよいですか？ この操作は取り消せません。')) return;
    // clear local form state
    to.value = [];
    cc.value = [];
    bcc.value = [];
    toQuery.value = '';
    ccQuery.value = '';
    bccQuery.value = '';
    subject.value = '';
    body.value = '';
    attachments.value = [];
    showToast('下書きを破棄しました', 'info', 3000);
}

onMounted(() => {
    // ensure bodyEl is focusable
    prefillFromQuery();
});
</script>
