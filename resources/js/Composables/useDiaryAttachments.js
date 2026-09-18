import axios from 'axios';
import { ref } from 'vue';

// 日報の添付ファイル（アップロード・ステージング・プレビュー・削除）を
// Create / Edit / Show の3画面で共通利用するためのコンポーザブル。
// 本文（Quillエディタ）には埋め込まず、独立した一覧として管理する。

const MAX_UPLOAD_SIZE = 5 * 1024 * 1024; // 5MB per file
const ALLOWED_EXT = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'docx', 'xlsx', 'txt'];
const MAX_IMAGE_WIDTH = 600; // px

function isAllowedFile(file) {
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
    return new File([blob], file.name, { type: blob.type });
}

function normalizeExisting(a) {
    return {
        id: a.id ?? a.attachment_id ?? null,
        original_name: a.original_name || a.name || '',
        mime: a.mime || a.mime_type || '',
        size: a.size || 0,
        status: a.status || 'ready',
        url: a.url || a.public_url || null,
    };
}

export function useDiaryAttachments(initial = []) {
    const attachments = ref((initial || []).map(normalizeExisting));

    const previewModal = ref({ open: false, url: null, mime: null, filename: null });
    let currentObjectUrl = null;

    function revokeCurrentObjectUrl() {
        try {
            if (currentObjectUrl) {
                URL.revokeObjectURL(currentObjectUrl);
                currentObjectUrl = null;
            }
        } catch (e) { /* ignore */ }
    }

    async function openPreview(item) {
        if (!item || !item.url) return;
        try {
            const res = await axios.get(item.url, { responseType: 'blob', withCredentials: true });
            const blob = res.data;
            const mime = blob.type || res.headers['content-type'] || item.mime || 'application/octet-stream';
            revokeCurrentObjectUrl();
            currentObjectUrl = URL.createObjectURL(blob);
            previewModal.value = { open: true, url: currentObjectUrl, mime, filename: item.original_name || 'file' };
        } catch (e) {
            try {
                window.open(item.url, '_blank', 'noopener');
            } catch (e2) { /* ignore */ }
        }
    }

    function closePreview() {
        previewModal.value.open = false;
        revokeCurrentObjectUrl();
    }

    function pollAttachmentStatus(id, attempt = 0) {
        const maxAttempts = 30; // 30 * 2s = 60s
        const interval = 2000;
        setTimeout(async () => {
            try {
                const r = await axios.get(`/api/uploads/status/${id}`);
                if (r.data && r.data.status === 'ready') {
                    const idx = attachments.value.findIndex((a) => a.id === id);
                    if (idx >= 0) {
                        attachments.value.splice(idx, 1, {
                            ...attachments.value[idx],
                            status: 'ready',
                            url: r.data.url || r.data.public_url || attachments.value[idx].url,
                            mime: r.data.mime || attachments.value[idx].mime,
                        });
                    }
                    return;
                }
                if (r.data && (r.data.status === 'failed' || r.data.status === 'rejected')) {
                    attachments.value = attachments.value.filter((a) => a.id !== id);
                    alert('アップロード処理に失敗しました');
                    return;
                }
            } catch (e) {
                // ignore and retry
            }
            if (attempt < maxAttempts) {
                pollAttachmentStatus(id, attempt + 1);
            }
        }, interval);
    }

    async function uploadAndStage(file) {
        if (!isAllowedFile(file)) {
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
            attachments.value = [
                ...attachments.value,
                {
                    id: attach.id,
                    original_name: attach.original_name,
                    mime: attach.mime,
                    size: attach.size,
                    status: attach.status,
                    url: attach.url || attach.public_url || null,
                },
            ];
            if (attach.status !== 'ready') {
                pollAttachmentStatus(attach.id);
            }
        } catch (e) {
            console.error('upload error', e);
            alert('ファイルのアップロードに失敗しました');
        }
    }

    async function removeAttachment(item) {
        if (!item) return;
        if (!confirm('添付ファイルを削除してよいですか？')) return;
        try {
            if (item.id) {
                await axios.delete(`/api/attachments/${item.id}`);
            }
        } catch (e) {
            console.warn('attachment delete API failed', e);
        }
        attachments.value = attachments.value.filter((a) => a.id !== item.id);
    }

    function attachmentIds() {
        return attachments.value.filter((a) => a.status === 'ready' && a.id).map((a) => a.id);
    }

    return {
        attachments,
        previewModal,
        openPreview,
        closePreview,
        uploadAndStage,
        removeAttachment,
        attachmentIds,
    };
}
