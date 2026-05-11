<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { router, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { route } from 'ziggy-js';
import axios from 'axios';

const props = defineProps({
    ticket:   { type: Object, required: true },
    statuses: { type: Object, default: () => ({}) },
});

const page     = usePage();
const authUser = computed(() => page.props.auth?.user ?? null);
const isAdmin  = computed(() => ['admin', 'superadmin'].includes(authUser.value?.user_role));

// ローカルコピー（ステータス変更に反映）
const localTicket = ref({ ...props.ticket });

function formatDate(dateStr) {
    if (!dateStr) return '—';
    const p = String(dateStr).split('T')[0].split('-');
    return p.length === 3 ? `${p[0]}/${p[1]}/${p[2]}` : dateStr;
}

function statusBadgeClass(status) {
    switch (status) {
        case 'completed':   return 'bg-yellow-100 text-yellow-800';
        case 'in_progress': return 'bg-blue-100 text-blue-800';
        case 'submitting':  return 'bg-purple-100 text-purple-800';
        case 'pending':     return 'bg-red-100 text-red-800';
        default:            return 'bg-gray-100 text-gray-700';
    }
}

function statusLabel(status) {
    return props.statuses[status] ?? status;
}

// ── ステータス変更 ────────────────────────────────────────
const updatingStatus = ref(false);
async function changeStatus(newStatus) {
    if (updatingStatus.value) return;
    updatingStatus.value = true;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    try {
        const res = await fetch(route('prepress.tickets.updateStatus', { ticket: localTicket.value.id }), {
            method: 'PATCH',
            credentials: 'same-origin',
            headers: {
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json',
                Accept: 'application/json',
            },
            body: JSON.stringify({ status: newStatus }),
        });
        if (res.ok) {
            localTicket.value = { ...localTicket.value, status: newStatus };
        }
    } catch { /* ignore */ } finally {
        updatingStatus.value = false;
    }
}

// ── 削除 ─────────────────────────────────────────────────
const deleting = ref(false);
function deleteTicket() {
    if (!confirm(`「${localTicket.value.title}」を削除しますか？`)) return;
    deleting.value = true;
    router.delete(route('prepress.tickets.destroy', { ticket: localTicket.value.id }), {
        onFinish: () => { deleting.value = false; },
    });
}

// ── 画像アップロード ──────────────────────────────────────
const uploadingImage  = ref(false);
const uploadError     = ref('');
const pendingFile     = ref(null);
const pendingPreview  = ref(null);

function selectPendingFile(file) {
    if (!file) return;
    uploadError.value = '';
    pendingFile.value = file;
    if (file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf')) {
        pendingPreview.value = '__pdf__';
    } else {
        const reader = new FileReader();
        reader.onload = (e) => { pendingPreview.value = e.target.result; };
        reader.readAsDataURL(file);
    }
}

function cancelPendingFile() {
    pendingFile.value    = null;
    pendingPreview.value = null;
    uploadError.value    = '';
}

async function savePendingImage() {
    if (!pendingFile.value || uploadingImage.value) return;
    uploadingImage.value = true;
    uploadError.value    = '';
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const fd = new FormData();
    fd.append('image', pendingFile.value);
    try {
        const res = await fetch(route('prepress.tickets.updateImage', { ticket: localTicket.value.id }), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
            body: fd,
        });
        if (res.ok) {
            const data = await res.json();
            localTicket.value = {
                ...localTicket.value,
                image_path:        data.image_url ? data.image_url.replace('/storage/', '') : localTicket.value.image_path,
                image_url:         data.image_url,
                original_filename: data.original_filename,
            };
            cancelPendingFile();
        } else {
            uploadError.value = '保存に失敗しました。もう一度お試しください。';
        }
    } catch {
        uploadError.value = '通信エラーが発生しました。';
    } finally {
        uploadingImage.value = false;
    }
}
</script>

<template>
    <AppLayout title="伝票詳細">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">伝票詳細</h2>
        </template>

        <div class="mx-auto max-w-3xl space-y-4">

            <!-- メインカード -->
            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <!-- カードヘッダー -->
                <div class="flex items-start justify-between gap-3 border-b bg-gray-50 px-5 py-4">
                    <div class="min-w-0 flex-1">
                        <div class="mb-1 flex flex-wrap gap-1.5">
                            <span
                                :class="statusBadgeClass(localTicket.status)"
                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold"
                            >{{ statusLabel(localTicket.status) }}</span>
                            <span v-if="localTicket.jobcode" class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs text-gray-600">
                                # {{ localTicket.jobcode }}
                            </span>
                        </div>
                        <h1 class="text-base font-bold text-gray-900">{{ localTicket.title }}</h1>
                        <p class="mt-0.5 text-sm text-gray-500">作成者: {{ localTicket.user?.name ?? '—' }}</p>
                    </div>
                </div>

                <!-- ボタン類 -->
                <div class="flex flex-wrap items-center gap-2 border-t bg-gray-50 px-5 py-3">
                    <a
                        :href="route('prepress.tickets.index')"
                        class="inline-flex items-center gap-1.5 rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300"
                    >← 一覧に戻る</a>

                    <button
                        v-if="localTicket.status !== 'in_progress'"
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded bg-blue-500 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-600"
                        :disabled="updatingStatus"
                        @click="changeStatus('in_progress')"
                    >作業中にする</button>

                    <button
                        v-if="localTicket.status !== 'submitting'"
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded bg-purple-500 px-3 py-1.5 text-sm font-medium text-white hover:bg-purple-600"
                        :disabled="updatingStatus"
                        @click="changeStatus('submitting')"
                    >入稿予定にする</button>

                    <button
                        v-if="localTicket.status !== 'completed'"
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded bg-yellow-500 px-3 py-1.5 text-sm font-medium text-white hover:bg-yellow-600"
                        :disabled="updatingStatus"
                        @click="changeStatus('completed')"
                    >完了にする</button>

                    <button
                        v-if="localTicket.status !== 'pending'"
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded bg-gray-400 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-500"
                        :disabled="updatingStatus"
                        @click="changeStatus('pending')"
                    >準備に戻す</button>

                    <div class="ml-auto flex items-center gap-2">
                        <a
                            :href="route('prepress.tickets.edit', { ticket: localTicket.id })"
                            class="inline-flex items-center gap-1.5 rounded bg-indigo-500 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-600"
                        >編集</a>

                        <button
                            v-if="isAdmin"
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded bg-red-500 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-600"
                            :disabled="deleting"
                            @click="deleteTicket"
                        >削除</button>
                    </div>
                </div>
            </div>

            <!-- 詳細情報カード -->
            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="border-b bg-gray-50 px-5 py-3">
                    <h2 class="text-sm font-semibold text-gray-700">伝票詳細</h2>
                </div>
                <dl class="divide-y divide-gray-100 px-5 py-2 text-sm">
                    <div class="flex py-2">
                        <dt class="w-32 shrink-0 font-medium text-gray-500">クライアント</dt>
                        <dd class="text-gray-800">{{ localTicket.client_name || '—' }}</dd>
                    </div>
                    <div class="flex py-2">
                        <dt class="w-32 shrink-0 font-medium text-gray-500">案件名</dt>
                        <dd class="text-gray-800">{{ localTicket.project_name || '—' }}</dd>
                    </div>
                    <div class="flex py-2">
                        <dt class="w-32 shrink-0 font-medium text-gray-500">伝票番号</dt>
                        <dd class="text-gray-800">{{ localTicket.jobcode || '—' }}</dd>
                    </div>
                    <div class="flex py-2">
                        <dt class="w-32 shrink-0 font-medium text-gray-500">作成日</dt>
                        <dd class="text-gray-800">{{ formatDate(localTicket.created_at) }}</dd>
                    </div>
                    <div v-if="localTicket.memo" class="flex py-2">
                        <dt class="w-32 shrink-0 font-medium text-gray-500">メモ</dt>
                        <dd class="whitespace-pre-wrap text-gray-800">{{ localTicket.memo }}</dd>
                    </div>
                </dl>
            </div>

            <!-- 添付画像カード -->
            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b bg-gray-50 px-5 py-3">
                    <h2 class="text-sm font-semibold text-gray-700">添付画像（伝票画像）</h2>
                    <label
                        v-if="!pendingFile"
                        class="cursor-pointer rounded border border-green-700 px-3 py-1 text-xs font-medium text-green-700 hover:bg-green-50"
                        :class="{ 'opacity-50 pointer-events-none': uploadingImage }"
                    >
                        {{ localTicket.image_path ? '画像を変更' : '画像を登録' }}
                        <input
                            type="file"
                            accept="image/*,.pdf"
                            class="hidden"
                            :disabled="uploadingImage"
                            @change="e => selectPendingFile(e.target.files?.[0])"
                        />
                    </label>
                </div>

                <!-- ① 保存待ち -->
                <div v-if="pendingFile" class="px-5 py-4 space-y-3">
                    <p class="text-xs font-semibold text-blue-700">新しい画像を確認して「保存」してください</p>
                    <div
                        v-if="pendingPreview === '__pdf__'"
                        class="flex h-32 w-40 items-center justify-center rounded-lg border border-gray-200 bg-gray-50"
                    >
                        <div class="text-center">
                            <div class="text-3xl">📄</div>
                            <p class="mt-1 text-xs text-gray-500">PDF</p>
                            <p class="text-xs text-gray-400">保存時に変換</p>
                        </div>
                    </div>
                    <img
                        v-else-if="pendingPreview"
                        :src="pendingPreview"
                        alt="プレビュー"
                        class="max-h-60 rounded border border-gray-200 object-contain"
                    />
                    <p class="text-xs text-gray-500">{{ pendingFile.name }}</p>
                    <p v-if="uploadError" class="text-xs text-red-600">{{ uploadError }}</p>
                    <div class="flex gap-2">
                        <button
                            type="button"
                            class="rounded bg-green-700 px-4 py-1.5 text-sm font-semibold text-white hover:bg-green-800 disabled:opacity-50"
                            :disabled="uploadingImage"
                            @click="savePendingImage"
                        >{{ uploadingImage ? '変換・保存中...' : '保存' }}</button>
                        <button
                            type="button"
                            class="rounded border border-gray-300 px-4 py-1.5 text-sm text-gray-600 hover:bg-gray-50"
                            :disabled="uploadingImage"
                            @click="cancelPendingFile"
                        >キャンセル</button>
                    </div>
                </div>

                <!-- ② 既存画像 -->
                <div v-else-if="localTicket.image_path" class="px-5 py-4">
                    <img
                        :src="localTicket.image_url ?? ('/storage/' + localTicket.image_path)"
                        :alt="localTicket.original_filename ?? 'image'"
                        class="max-w-full rounded border border-gray-200"
                    />
                    <p v-if="localTicket.original_filename" class="mt-1 text-xs text-gray-400">{{ localTicket.original_filename }}</p>
                </div>

                <!-- ③ 未登録 -->
                <div v-else class="px-5 py-6 text-center text-sm text-gray-400">
                    <p>画像が登録されていません。</p>
                    <p class="mt-1 text-xs">上の「画像を登録」ボタンからファイルを選択してください。</p>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
