<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import OcrModal from '@/Components/Prepress/OcrModal.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    statuses: { type: Object, default: () => ({}) },
    clients:  { type: Array,  default: () => [] },
    prefill:  { type: Object, default: () => ({}) },
});

const form = useForm({
    client_id:          props.prefill.client_id   ?? '',
    client_name:        props.prefill.client_name ?? '',
    jobcode:            props.prefill.jobcode     ?? '',
    title:              props.prefill.title       ?? '',
    memo:               '',
    status:             'pending',
    image:              null,
    project_job_id:     props.prefill.project_job_id ?? '',
    use_job_image:      !!(props.prefill.job_image_path),
    tmp_ocr_image_path: props.prefill.tmp_ocr_image_path ?? '',
});

// ── OCR ──────────────────────────────────────────────────
const isOcrLoading = ref(false);
const showOcrModal = ref(false);
const ocrResult    = ref({});

async function triggerOcr(file) {
    isOcrLoading.value = true;
    showOcrModal.value = false;
    const fd   = new FormData();
    fd.append('image', file);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    try {
        const res = await axios.post(route('prepress.ocr.analyze'), fd, {
            headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'multipart/form-data' },
        });
        ocrResult.value    = res.data;
        showOcrModal.value = true;
    } catch {
        // OCR失敗時は無視してフォームをそのまま使う
    } finally {
        isOcrLoading.value = false;
    }
}

function onOcrApply(result) {
    // OCRモーダルで確定した値をフォームに反映
    form.jobcode            = result.jobcode     || form.jobcode;
    form.title              = result.title       || form.title;
    form.client_id          = result.client_id   || '';
    form.client_name        = result.client_name || form.client_name;
    form.tmp_ocr_image_path = result.tmp_image_path || '';

    // サーバー側で変換済み画像を使うため、再アップロード不要
    if (result.tmp_image_path) {
        form.image       = null;
        form.use_job_image = false;
        previewUrl.value  = result.image_url || previewUrl.value;
        previewName.value = result.original_filename || previewName.value;
        isJobImage.value  = false;
    }
    showOcrModal.value = false;
}

// ── クライアント選択（coordinator スタイル）────────────────
const clientSuggestions  = ref([]);
const showClientSuggestions = ref(false);
const selectedSuggestionIndex = ref(-1);
let clientSearchTimer = null;

function onClientNameInput() {
    form.client_id = '';
    clearTimeout(clientSearchTimer);
    if (!form.client_name.trim()) {
        clientSuggestions.value = [];
        showClientSuggestions.value = false;
        return;
    }
    clientSearchTimer = setTimeout(async () => {
        const res = await axios.get(route('prepress.api.clients'), { params: { q: form.client_name } });
        clientSuggestions.value = res.data;
        showClientSuggestions.value = true;
        selectedSuggestionIndex.value = -1;
    }, 250);
}

function onClientNameKeydown(e) {
    if (!showClientSuggestions.value || clientSuggestions.value.length === 0) return;
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        selectedSuggestionIndex.value = Math.min(selectedSuggestionIndex.value + 1, clientSuggestions.value.length - 1);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        selectedSuggestionIndex.value = Math.max(selectedSuggestionIndex.value - 1, 0);
    } else if (e.key === 'Enter') {
        e.preventDefault();
        const idx = selectedSuggestionIndex.value;
        if (idx >= 0 && clientSuggestions.value[idx]) selectClientFromSuggestion(clientSuggestions.value[idx]);
    } else if (e.key === 'Escape') {
        showClientSuggestions.value = false;
    }
}

function onClientNameBlur() {
    setTimeout(() => { showClientSuggestions.value = false; }, 200);
}

function selectClientFromSuggestion(client) {
    form.client_id   = client.id;
    form.client_name = client.name;
    showClientSuggestions.value = false;
}

// クライアントID直接入力
let clientIdTimer = null;
function onClientIdChange() {
    form.client_name = '';
    clearTimeout(clientIdTimer);
    if (!form.client_id) return;
    clientIdTimer = setTimeout(async () => {
        const res = await axios.get(route('prepress.api.clients'), { params: { q: '' } });
        const found = res.data.find(c => c.id == form.client_id);
        if (found) form.client_name = found.name;
    }, 400);
}

// ── 画像プレビュー ──────────────────────────────────────
const previewUrl    = ref(props.prefill.ocr_image_url ?? props.prefill.job_image_url  ?? null);
const previewName   = ref(props.prefill.original_filename ?? props.prefill.job_original_filename ?? '');
const isJobImage    = ref(!!(props.prefill.job_image_path));
const isDragging    = ref(false);
const showLightbox  = ref(false);

function handleFileSelect(file) {
    if (!file) return;
    form.image = file;
    form.use_job_image = false;
    isJobImage.value = false;
    previewName.value = file.name;

    // PDFはブラウザの<img>で表示できないためプレースホルダーを使う
    if (file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf')) {
        previewUrl.value = '__pdf__';
    } else {
        const reader = new FileReader();
        reader.onload = (e) => { previewUrl.value = e.target.result; };
        reader.readAsDataURL(file);
    }

    // OCR解析をトリガー
    triggerOcr(file);
}

function onDropZoneDrop(e) {
    isDragging.value = false;
    const file = e.dataTransfer?.files?.[0];
    if (file) handleFileSelect(file);
}

function onFileInputChange(e) {
    const file = e.target.files?.[0];
    if (file) handleFileSelect(file);
}

function removeImage() {
    form.image              = null;
    form.use_job_image      = false;
    form.tmp_ocr_image_path = '';
    isJobImage.value        = false;
    previewUrl.value        = null;
    previewName.value       = '';
}

// ── 送信 ─────────────────────────────────────────────
function submit() {
    form.post(route('prepress.tickets.store'), {
        forceFormData: true,
        preserveScroll: true,
    });
}

// モバイル判定（スマホ時のみカメラボタンを表示）
const isMobile = computed(() => {
    if (typeof navigator === 'undefined') return false;
    return /Mobi|Android|iPhone|iPad/i.test(navigator.userAgent);
});
</script>

<template>
    <AppLayout title="伝票登録">
        <template #header>
            <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">伝票登録</h2>
        </template>

        <div class="mx-auto max-w-2xl">
            <form @submit.prevent="submit" enctype="multipart/form-data">
                <div class="rounded-xl bg-white p-6 shadow-sm space-y-5">

                    <!-- クライアント -->
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-gray-700">クライアント</label>
                        <div class="flex items-center gap-2">
                            <div class="flex items-center gap-1">
                                <label class="text-sm text-gray-500">ID:</label>
                                <input
                                    v-model="form.client_id"
                                    type="number"
                                    placeholder="ID"
                                    class="w-20 rounded border border-gray-300 px-2 py-2 text-sm focus:border-green-600 focus:outline-none"
                                    @input="onClientIdChange"
                                />
                            </div>
                            <div class="relative flex-1">
                                <input
                                    v-model="form.client_name"
                                    type="text"
                                    placeholder="名前を入力（オートコンプリート）"
                                    class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-600 focus:outline-none"
                                    @input="onClientNameInput"
                                    @keydown="onClientNameKeydown"
                                    @blur="onClientNameBlur"
                                />
                                <div
                                    v-if="showClientSuggestions && clientSuggestions.length > 0"
                                    class="absolute top-full z-50 mt-1 w-full overflow-y-auto rounded border border-gray-300 bg-white shadow-lg max-h-52"
                                >
                                    <div
                                        v-for="(client, idx) in clientSuggestions"
                                        :key="client.id"
                                        class="cursor-pointer px-3 py-2 text-sm hover:bg-blue-50"
                                        :class="{ 'bg-blue-100': idx === selectedSuggestionIndex }"
                                        @mousedown.prevent="selectClientFromSuggestion(client)"
                                    >
                                        <div class="flex items-center justify-between">
                                            <span class="font-medium">{{ client.name }}</span>
                                            <span class="text-xs text-gray-400">ID: {{ client.id }}</span>
                                        </div>
                                        <div v-if="client.is_dormant" class="text-xs text-red-500">※ 休眠中</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <p v-if="form.errors.client_name" class="mt-1 text-xs text-red-600">{{ form.errors.client_name }}</p>
                    </div>

                    <!-- 伝票番号 -->
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-gray-700">伝票番号</label>
                        <input
                            v-model="form.jobcode"
                            type="text"
                            placeholder="例：2024-001"
                            class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-600 focus:outline-none"
                        />
                        <p v-if="form.errors.jobcode" class="mt-1 text-xs text-red-600">{{ form.errors.jobcode }}</p>
                    </div>

                    <!-- 案件タイトル -->
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-gray-700">
                            案件タイトル <span class="text-red-500">*</span>
                        </label>
                        <input
                            v-model="form.title"
                            type="text"
                            placeholder="例：〇〇テキスト 初校 組版"
                            required
                            class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-600 focus:outline-none"
                        />
                        <p v-if="form.errors.title" class="mt-1 text-xs text-red-600">{{ form.errors.title }}</p>
                    </div>

                    <!-- ステータス -->
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-gray-700">ステータス</label>
                        <select
                            v-model="form.status"
                            class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-600 focus:outline-none"
                        >
                            <option v-for="(label, key) in statuses" :key="key" :value="key">{{ label }}</option>
                        </select>
                        <p v-if="form.errors.status" class="mt-1 text-xs text-red-600">{{ form.errors.status }}</p>
                    </div>

                    <!-- メモ -->
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-gray-700">メモ</label>
                        <textarea
                            v-model="form.memo"
                            rows="3"
                            placeholder="作業内容・注意事項など"
                            class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-600 focus:outline-none"
                        />
                        <p v-if="form.errors.memo" class="mt-1 text-xs text-red-600">{{ form.errors.memo }}</p>
                    </div>

                    <!-- 伝票画像 -->
                    <div>
                        <label class="mb-2 block text-sm font-semibold text-gray-700">
                            作業ファイル情報（伝票画像）
                            <span
                                v-if="isOcrLoading"
                                class="ml-2 inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-xs font-normal text-green-700"
                            >
                                <svg class="h-3 w-3 animate-spin" viewBox="0 0 24 24" fill="none">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                                </svg>
                                OCR解析中...
                            </span>
                        </label>

                        <!-- サムネイル表示 -->
                        <div v-if="previewUrl" class="mb-3">
                            <div class="relative inline-block">
                                <!-- PDF プレースホルダー -->
                                <div
                                    v-if="previewUrl === '__pdf__'"
                                    class="flex h-40 w-48 items-center justify-center rounded-lg border border-gray-200 bg-gray-50 shadow-sm"
                                >
                                    <div class="text-center">
                                        <div class="text-4xl">📄</div>
                                        <p class="mt-1 text-xs text-gray-500">PDF</p>
                                        <p class="text-xs text-gray-400">登録後に変換表示</p>
                                    </div>
                                </div>
                                <!-- 画像プレビュー -->
                                <img
                                    v-else
                                    :src="previewUrl"
                                    :alt="previewName"
                                    class="h-40 w-auto rounded-lg border border-gray-200 object-contain shadow-sm"
                                />
                                <button
                                    type="button"
                                    class="absolute -right-2 -top-2 flex h-6 w-6 items-center justify-center rounded-full bg-red-500 text-xs text-white shadow hover:bg-red-600"
                                    @click="removeImage"
                                >✕</button>
                            </div>
                            <div class="mt-2 flex items-center gap-2 flex-wrap">
                                <span class="text-xs text-gray-500 truncate max-w-xs">{{ previewName }}</span>
                                <span
                                    v-if="isJobImage"
                                    class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-700"
                                >案件から引き継ぎ</span>
                                <button
                                    v-if="previewUrl !== '__pdf__'"
                                    type="button"
                                    class="rounded border border-gray-300 px-2 py-0.5 text-xs text-gray-600 hover:bg-gray-50"
                                    @click="showLightbox = true"
                                >🔍 拡大</button>
                            </div>
                            <p class="mt-1 text-xs text-gray-400" v-if="isJobImage">
                                ※ 案件の画像を共有します。別の画像にする場合は上の「✕」で削除して新しく選択してください。
                            </p>
                        </div>

                        <!-- ドロップゾーン -->
                        <div
                            v-if="!previewUrl"
                            class="flex flex-col items-center justify-center rounded-lg border-2 border-dashed px-6 py-8 transition-colors"
                            :class="isDragging ? 'border-green-500 bg-green-50' : 'border-gray-300 bg-gray-50 hover:border-green-400'"
                            @dragover.prevent="isDragging = true"
                            @dragleave="isDragging = false"
                            @drop.prevent="onDropZoneDrop"
                        >
                            <div class="mb-2 text-3xl text-gray-400">📎</div>
                            <p class="text-sm text-gray-600">ここに画像をドロップ</p>
                            <p class="mt-1 text-xs text-gray-400">JPG / PNG / WEBP / HEIC / GIF / PDF 対応（最大 20MB）</p>
                        </div>

                        <!-- フォルダから選ぶ -->
                        <div class="mt-3 flex flex-wrap gap-2">
                            <label class="cursor-pointer rounded-lg border border-green-700 px-4 py-2 text-sm font-medium text-green-700 hover:bg-green-50">
                                📁 フォルダから選ぶ
                                <input
                                    type="file"
                                    accept="image/*,.pdf"
                                    class="hidden"
                                    @change="onFileInputChange"
                                />
                            </label>

                            <!-- スマホ時のみカメラボタンを表示 -->
                            <label v-if="isMobile" class="cursor-pointer rounded-lg border border-gray-400 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">
                                📷 カメラ画像を取り込む
                                <input
                                    type="file"
                                    accept="image/*"
                                    capture="environment"
                                    class="hidden"
                                    @change="onFileInputChange"
                                />
                            </label>
                        </div>

                        <p v-if="form.errors.image" class="mt-1 text-xs text-red-600">{{ form.errors.image }}</p>
                    </div>

                    <!-- 送信 -->
                    <div class="flex items-center justify-between border-t border-gray-100 pt-4">
                        <a :href="route('prepress.tickets.index')" class="text-sm text-gray-500 hover:text-gray-700">← 一覧に戻る</a>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-lg bg-green-700 px-6 py-2 text-sm font-semibold text-white hover:bg-green-800 disabled:opacity-60"
                        >
                            {{ form.processing ? '保存中...' : '作成' }}
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- 画像拡大ライトボックス -->
        <Teleport to="body">
            <div
                v-if="showLightbox && previewUrl"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/80"
                @click.self="showLightbox = false"
            >
                <div class="relative max-h-[90vh] max-w-[90vw]">
                    <img :src="previewUrl" :alt="previewName" class="max-h-[85vh] max-w-[88vw] rounded-lg object-contain" />
                    <button
                        type="button"
                        class="absolute -right-3 -top-3 flex h-8 w-8 items-center justify-center rounded-full bg-white text-gray-800 shadow-md hover:bg-gray-100"
                        @click="showLightbox = false"
                    >✕</button>
                </div>
            </div>
        </Teleport>

        <!-- OCRモーダル -->
        <OcrModal
            :show="showOcrModal"
            :ocr-result="ocrResult"
            @apply="onOcrApply"
            @close="showOcrModal = false"
        />
    </AppLayout>
</template>
