<template>
    <AppLayout :title="subjectText || 'メール詳細'">
        <template #header>
            <h2 class="text-xl font-semibold">メール詳細</h2>
        </template>

        <main>
            <div class="py-2">
                <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div class="bg-white p-6 shadow sm:rounded-lg">
                        <div class="mb-4 flex items-center justify-between">
                            <a :href="route('messages.index')" class="text-sm text-blue-600 underline">← 一覧に戻る</a>
                            <div>
                                <button
                                    v-if="
                                        currentUserId &&
                                        (currentUserId === (localMessage?.from_user_id ?? localMessage?.from_user?.id) ||
                                            (Array.isArray(localMessage?.recipients) &&
                                                localMessage.recipients.some((r) => (r.user_id ?? r.user?.id) === currentUserId)))
                                    "
                                    @click.prevent="onTrashClick"
                                    class="rounded bg-red-50 px-2 py-1 text-sm text-red-700 hover:bg-red-100"
                                >
                                    {{ isTrashedByCurrentUser ? '完全削除' : '削除' }}
                                </button>
                            </div>
                        </div>
                        <h3 class="text-lg font-semibold">{{ subjectText }}</h3>
                        <div class="mt-1 text-sm text-gray-500">差出人: {{ senderName }}</div>
                        <div class="mt-4 text-sm text-gray-700" v-html="sanitizedBody" @click="onBodyClick"></div>

                        <div class="mt-6">
                            <label class="block text-sm font-medium text-gray-700">添付ファイル</label>
                            <div v-if="attachmentsList && attachmentsList.length">
                                <ul class="mt-2 space-y-2">
                                    <li
                                        v-for="file in attachmentsList"
                                        :key="file.id || file.url"
                                        class="flex items-center justify-between rounded bg-gray-50 p-2"
                                    >
                                        <div class="flex items-center gap-3">
                                            <div v-if="ensureThumb(file)" class="flex-shrink-0">
                                                <img :src="ensureThumb(file)" alt="thumb" class="h-12 w-12 rounded object-cover" />
                                            </div>
                                            <div>
                                                <div class="text-sm font-medium text-gray-900">{{ file.original_name }}</div>
                                                <div class="text-xs text-gray-500">
                                                    {{ file.size || 0 ? (file.size / 1024).toFixed(1) + ' KB' : '-' }} • {{ file.mime_type || '-' }}
                                                </div>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <button
                                                v-if="file && (file.url || file.path || file.id)"
                                                type="button"
                                                @click.prevent="openAttachmentInModal(file)"
                                                class="text-blue-600 underline"
                                            >
                                                開く
                                            </button>
                                            <a
                                                v-if="file.url || file.path || file.id"
                                                :href="downloadHref(file)"
                                                :download="file.original_name"
                                                class="text-gray-600"
                                                >ダウンロード</a
                                            >
                                            <button
                                                v-if="$page.props.auth?.user?.id === file.user_id"
                                                type="button"
                                                @click.prevent="confirmDelete(file)"
                                                class="text-red-600"
                                            >
                                                削除
                                            </button>
                                            <span v-else-if="!(file.url || file.path || file.id)" class="text-sm text-gray-500">(利用不可)</span>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                            <div v-else class="mt-2 text-sm text-gray-500">添付ファイルなし</div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- dev-only JSON dump removed -->
        </main>
    </AppLayout>

    <!-- Preview Modal -->
    <div v-if="previewModal.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
        <div class="max-h-[90vh] w-full max-w-4xl overflow-auto rounded bg-white p-4">
            <div class="mb-2 flex items-center justify-between">
                <div class="text-sm font-medium">プレビュー: {{ previewModal.filename }}</div>
                <button type="button" @click="closePreviewModal" class="text-gray-600">閉じる</button>
            </div>
            <div class="border p-2">
                <template v-if="previewModal.mime && previewModal.mime.startsWith('image/')">
                    <img :src="previewModal.url" alt="preview" class="h-auto max-w-full" />
                </template>
                <template v-else-if="previewModal.mime && previewModal.mime === 'application/pdf'">
                    <iframe :src="previewModal.url" class="w-full" style="height: 70vh" frameborder="0"></iframe>
                </template>
                <template v-else>
                    <div class="text-sm">
                        プレビューできません。<a :href="previewModal.url" target="_blank" rel="noopener" class="text-blue-600 underline"
                            >新しいタブで開く</a
                        >
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ensureAttachmentUrl, ensureThumbUrl } from '@/Helpers/attachment';
import AppLayout from '@/layouts/AppLayout.vue';
import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import DOMPurify from 'dompurify';
import { computed, onMounted, ref, watch } from 'vue';
const props = defineProps({ message: Object });
const { message } = props;

const page = usePage();
const currentUserId = computed(() => {
    try {
        return page.props?.user?.id || page.props?.auth?.user?.id || null;
    } catch (e) {
        return null;
    }
});

function cloneDeep(obj) {
    try {
        // modern browsers
        return structuredClone(obj);
    } catch (e) {
        try {
            return JSON.parse(JSON.stringify(obj));
        } catch (e2) {
            return obj;
        }
    }
}

const localMessage = ref(message ? cloneDeep(message) : null);

onMounted(async () => {
    let msgId = (message && message.id) || (localMessage.value && localMessage.value.id);
    if (!msgId) {
        try {
            const m = window.location.pathname.match(/\/messages\/(\d+)/);
            if (m && m[1]) msgId = parseInt(m[1], 10);
        } catch (e) {}
    }

    if (msgId) {
        axios.post(route('messages.read', msgId)).catch(() => {});
        try {
            const res = await axios.get(`/api/debug/messages/${msgId}/payload`);
            if (res && res.data) {
                localMessage.value = cloneDeep(res.data);
                try {
                } catch (e) {}
            }
        } catch (e) {
            try {
                const r2 = await axios.get(`/api/debug/public/messages/${msgId}/payload`);
                if (r2 && r2.data) {
                    localMessage.value = cloneDeep(r2.data);
                    try {
                    } catch (e) {}
                }
            } catch (e2) {}
        }
    }

    startAttachmentPolling();
});

function startAttachmentPolling() {
    if (!localMessage.value || !Array.isArray(localMessage.value.attachments)) return;
    localMessage.value.attachments.forEach((f) => {
        if (!f) return;
        if ((f.status || '') !== 'ready' && f.id) pollAttachmentStatus(f.id, 0);
    });
}

watch(localMessage, (nv, ov) => {}, { deep: true });
// Watch the body field specifically to catch the exact moment it changes/clears
watch(
    () => localMessage.value?.body,
    (nv, ov) => {},
    { immediate: true },
);
// Watch the incoming prop 'message' to see if parent/Inertia overwrites it
watch(
    () => message?.body,
    (nv, ov) => {},
    { immediate: true },
);

async function pollAttachmentStatus(id, attempt) {
    const maxAttempts = 30;
    const interval = 2000;
    try {
        const r = await axios.get(`/api/uploads/status/${id}`);
        if (r && r.data) {
            const st = r.data.status;
            localMessage.value.attachments = (localMessage.value.attachments || []).map((f) => {
                if (f.id === id) {
                    const updated = { ...f, status: st || f.status, url: r.data.url || f.url, mime_type: r.data.mime || f.mime_type };
                    return updated;
                }
                return f;
            });

            if (st === 'ready') {
                const placeholder = `[[attachment:${id}:`;
                if (localMessage.value.body && localMessage.value.body.indexOf(placeholder) >= 0) {
                    const url = r.data.url;
                    const original = r.data.original_name || '';
                    if (r.data.mime && r.data.mime.startsWith('image/')) {
                        localMessage.value.body = localMessage.value.body.replace(
                            new RegExp(`\\[\\[attachment:${id}:[^\\]]*\\]\\]`, 'g'),
                            `![](${url})`,
                        );
                    } else {
                        // For non-image attachments, remove placeholder entirely so filename does not appear in the body.
                        localMessage.value.body = localMessage.value.body.replace(new RegExp(`\\[\\[attachment:${id}:[^\\]]*\\]\\]`, 'g'), ``);
                    }
                } else {
                    // placeholder not found debug suppressed
                }
                return;
            }
        }
    } catch (e) {}
    if (attempt < maxAttempts) setTimeout(() => pollAttachmentStatus(id, attempt + 1), interval);
}

function isAttachmentUrl(url) {
    try {
        if (!localMessage.value || !Array.isArray(localMessage.value.attachments)) return false;
        return localMessage.value.attachments.some((a) => {
            if (!a || !a.url || !url) return false;
            try {
                // compare normalized strings; allow querystring differences by startsWith
                const au = String(a.url);
                return url === au || url.startsWith(au) || au.startsWith(url);
            } catch (e) {
                return false;
            }
        });
    } catch (e) {
        return false;
    }
}

function formatMessageBody(body) {
    // If there's no body or it already contains obvious markup/newlines, leave it alone
    if (!body || typeof body !== 'string') return body || '';
    if (/\n|<br\s*\/?>|<p/i.test(body)) return body;

    // Known labels in the job-completion notification we want on their own lines
    const labels = ['プロジェクトジョブID:', '予定をセットしたユーザーID:', 'イベント名:', '開始:', '終了:', '詳細:'];

    // If none of the labels are present, don't modify the body
    const hasLabel = labels.some((l) => body.indexOf(l) >= 0);
    if (!hasLabel) return body;

    let out = String(body);
    // Insert HTML <br> before each label occurrence (but not at the very start)
    labels.forEach((label) => {
        const esc = label.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        out = out.replace(new RegExp('\\s*' + esc, 'g'), '<br>' + label);
    });
    // Remove a leading <br> if inserted
    out = out.replace(/^<br>/, '');
    return out;
}

const sanitizedBody = computed(() => sanitize(formatMessageBody(localMessage.value?.body)));

function extractAttachmentsFromBody(body) {
    if (!body || typeof body !== 'string') return [];
    const results = [];
    // markdown link: [name](url)
    const mdRe = /\[([^\]]+)\]\((https?:[^)]+)\)/g;
    let m;
    while ((m = mdRe.exec(body))) {
        results.push({ original_name: m[1], url: m[2] });
    }
    // bare URLs
    const urlRe = /(^|\s)(https?:\/\/[^\s<>"')]+)/g;
    while ((m = urlRe.exec(body))) {
        const url = m[2] || m[1];
        // avoid duplicating ones already captured
        if (!results.find((r) => r.url === url)) results.push({ original_name: url.split('/').pop() || url, url });
    }
    return results;
}

const attachmentsList = computed(() => {
    const explicit = localMessage.value && Array.isArray(localMessage.value.attachments) ? localMessage.value.attachments : null;
    if (explicit && explicit.length) {
        return explicit;
    }
    return extractAttachmentsFromBody(localMessage.value?.body || '');
});

const senderName = computed(() => {
    try {
        const fu = localMessage.value && localMessage.value.from_user;
        if (fu && typeof fu.name === 'string' && fu.name.trim()) return fu.name;
        // fallback: sometimes backend uses 'from' or 'from_email' or 'sender_name'
        if (localMessage.value && typeof localMessage.value.from === 'string' && localMessage.value.from.trim()) return localMessage.value.from;
        if (localMessage.value && typeof localMessage.value.from_email === 'string' && localMessage.value.from_email.trim())
            return localMessage.value.from_email;
        if (fu && typeof fu.email === 'string' && fu.email.trim()) return fu.email;
    } catch (e) {}
    return '(不明)';
});

const subjectText = computed(() => {
    try {
        // prefer explicit subject on the localMessage
        if (localMessage.value && typeof localMessage.value.subject === 'string' && localMessage.value.subject.trim())
            return localMessage.value.subject;
        // fallback to other possible fields that may contain subject-like text
        if (localMessage.value && typeof localMessage.value.title === 'string' && localMessage.value.title.trim()) return localMessage.value.title;
        if (localMessage.value && typeof localMessage.value.topic === 'string' && localMessage.value.topic.trim()) return localMessage.value.topic;
    } catch (e) {}
    return '(件名なし)';
});

function sanitize(html) {
    const src = html || '';
    try {
        const container = document.createElement('div');
        container.innerHTML = src;
        const walker = document.createTreeWalker(container, NodeFilter.SHOW_TEXT, null, false);
        const textNodes = [];
        while (walker.nextNode()) textNodes.push(walker.currentNode);
        textNodes.forEach((tn) => {
            const text = tn.nodeValue || '';
            let replaced = text;
            replaced = replaced.replace(
                /!\[([^\]]*)\]\(([^)]+)\)/g,
                (m, alt, url) => `<img src="${String(url).trim()}" alt="${alt || ''}" class="max-w-full h-auto rounded" />`,
            );
            replaced = replaced.replace(/\[([^\]]+)\]\(([^)]+)\)/g, (m, txt, url) => {
                const u = String(url).trim();
                if (isAttachmentUrl(u)) {
                    // do not render attachment links or filenames in the body
                    return ``;
                }
                return `<a href="${u}" target="_blank" rel="noopener noreferrer" class="text-blue-600 underline" download>${txt}</a>`;
            });
            replaced = replaced.replace(/(^|\s)(https?:\/\/[^\s<>]+)/g, (m, pre, url) => {
                const u = String(url).trim();
                if (isAttachmentUrl(u)) {
                    // do not render attachment links or filenames in the body
                    return `${pre}`;
                }
                return `${pre}<a href="${u}" target="_blank" rel="noopener noreferrer" class="text-blue-600 underline" download>${u}</a>`;
            });
            if (replaced !== text) {
                const frag = document.createRange().createContextualFragment(replaced);
                tn.parentNode.replaceChild(frag, tn);
            }
        });
        return DOMPurify.sanitize(container.innerHTML);
    } catch (e) {
        return DOMPurify.sanitize(src);
    }
}

// Modal preview state and helpers
const previewModal = ref({ open: false, url: null, mime: null, filename: null, isBlob: false });
let currentObjectUrl = null;

function revokeCurrentObjectUrl() {
    try {
        if (currentObjectUrl) {
            URL.revokeObjectURL(currentObjectUrl);
            currentObjectUrl = null;
        }
    } catch (e) {}
}

async function fetchBlobAndShow(urlCandidate, filename) {
    try {
        // normalize candidate to streamable URL
        const url =
            typeof urlCandidate === 'string' ? ensureAttachmentUrl(urlCandidate) || urlCandidate : ensureAttachmentUrl(urlCandidate) || urlCandidate;
        if (!url) return;
        // fetch as blob to avoid browser download forced by server headers
        const res = await axios.get(url, { responseType: 'blob', withCredentials: true });
        const blob = res.data;
        const mime = blob.type || res.headers['content-type'] || 'application/octet-stream';
        revokeCurrentObjectUrl();
        currentObjectUrl = URL.createObjectURL(blob);
        previewModal.value = { open: true, url: currentObjectUrl, mime, filename: filename || url.split('/').pop() || 'file', isBlob: true };
    } catch (e) {
        // fallback: open in new tab if fetch fails
        try {
            const url =
                typeof urlCandidate === 'string'
                    ? ensureAttachmentUrl(urlCandidate) || urlCandidate
                    : ensureAttachmentUrl(urlCandidate) || urlCandidate;
            if (url) window.open(url, '_blank', 'noopener');
        } catch (e2) {}
    }
}

function openAttachmentInModal(file) {
    if (!file) return;
    // normalize url from file meta if needed
    const candidate = file.url || file.path || file.attachment_id || file;
    const url = ensureAttachmentUrl(candidate) || file.url || null;
    if (!url) return;
    // if it's already a blob/data URL, open directly
    if (typeof url === 'string' && (url.startsWith('blob:') || url.startsWith('data:'))) {
        previewModal.value = {
            open: true,
            url: url,
            mime: file.mime_type || file.mime || '',
            filename: file.original_name || '',
            isBlob: url.startsWith('blob:'),
        };
        return;
    }
    // otherwise fetch as blob to avoid download headers
    fetchBlobAndShow(url, file.original_name || 'file');
}

function ensureThumb(file) {
    try {
        return ensureThumbUrl(file) || null;
    } catch (e) {
        return null;
    }
}

function downloadHref(file) {
    // prefer a streamable/download-safe url
    try {
        const candidate = file.url || file.path || file.attachment_id || file;
        return ensureAttachmentUrl(candidate) || file.url || '#';
    } catch (e) {
        return file.url || '#';
    }
}

async function confirmDelete(file) {
    try {
        if (!file) return;
        if (!confirm('添付ファイルを削除しますか？ この操作は取り消せません。')) return;
        // If we have an id, call id-based delete API, otherwise try path-based
        if (file.id) {
            await axios.delete(`/api/attachments/${file.id}`);
        } else if (file.path) {
            await axios.delete(`/api/attachments`, { data: { path: file.path } });
        } else {
            // fallback: try deleting by using url -> path
            const u = ensureAttachmentUrl(file) || file.url;
            await axios.delete(`/api/attachments`, { data: { url: u } });
        }
        // remove locally
        if (Array.isArray(localMessage.value?.attachments)) {
            localMessage.value.attachments = localMessage.value.attachments.filter((f) => f !== file && f.id !== file.id && f.path !== file.path);
        }
    } catch (e) {
        console.error('削除失敗', e);
        alert('削除に失敗しました');
    }
}

function onBodyClick(e) {
    try {
        // capture clicks on links inside the sanitized body and open modal
        const a = e.target.closest && e.target.closest('a');
        if (a && a.href) {
            e.preventDefault();
            const url = a.href;
            const filename = (a.textContent && a.textContent.trim()) || url.split('/').pop();
            fetchBlobAndShow(url, filename);
        }
    } catch (e) {}
}

function closePreviewModal() {
    previewModal.value.open = false;
    // revoke object URL to free memory
    revokeCurrentObjectUrl();
}

const isTrashedByCurrentUser = computed(() => {
    try {
        const uid = currentUserId.value;
        if (!uid || !localMessage.value) return false;
        if (Array.isArray(localMessage.value.recipients)) {
            return localMessage.value.recipients.some((r) => {
                const rid = r.user_id ?? r.user?.id ?? null;
                return rid === uid && r.deleted_at;
            });
        }
        return false;
    } catch (e) {
        return false;
    }
});

async function onTrashClick() {
    try {
        if (!localMessage.value || !localMessage.value.id) return;
        if (isTrashedByCurrentUser.value) {
            // permanent delete flow
            if (!confirm('このメッセージを完全に削除します。元に戻せません。よろしいですか？')) return;
            await axios.delete(route('messages.destroy', localMessage.value.id));
            try {
                router.get(route('messages.index'));
            } catch (e) {
                window.location = route('messages.index');
            }
            return;
        }

        // move to trash
        if (!confirm('このメッセージをゴミ箱に移動しますか？')) return;
        await axios.post(route('messages.trash', localMessage.value.id));
        try {
            router.get(route('messages.index'));
        } catch (e) {
            window.location = route('messages.index');
        }
    } catch (e) {
        console.error('trashMessage error', e);
        alert('削除に失敗しました。');
    }
}
</script>
