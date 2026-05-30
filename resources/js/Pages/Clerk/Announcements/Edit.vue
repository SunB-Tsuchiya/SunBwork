<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import * as pdfjsLib from 'pdfjs-dist';
pdfjsLib.GlobalWorkerOptions.workerSrc = new URL('pdfjs-dist/build/pdf.worker.mjs', import.meta.url).toString();

const props = defineProps({
    announcement: Object,
});

const form = ref({
    title: props.announcement.title,
    content: props.announcement.content,
    attachments: [],
    remove_attachment_ids: [],
});

const errors = ref({});
const submitting = ref(false);
const existingAttachments = ref([...props.announcement.attachments]);
const newItems = ref([]);
const isDragging = ref(false);
const lightboxUrl = ref(null);

async function pdfRender(file, scale) {
    const buf = await file.arrayBuffer();
    const pdf = await pdfjsLib.getDocument({ data: buf }).promise;
    const page = await pdf.getPage(1);
    const vp = page.getViewport({ scale });
    const canvas = document.createElement('canvas');
    canvas.width = vp.width; canvas.height = vp.height;
    await page.render({ canvasContext: canvas.getContext('2d'), viewport: vp }).promise;
    return canvas.toDataURL('image/jpeg', 0.92);
}
async function addFiles(files) {
    for (const file of Array.from(files)) {
        form.value.attachments.push(file);
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = ev => newItems.value.push({ url: ev.target.result, fullUrl: ev.target.result, isImage: true, name: file.name });
            reader.readAsDataURL(file);
        } else if (file.type === 'application/pdf') {
            const idx = newItems.value.length;
            newItems.value.push({ url: null, fullUrl: null, isImage: false, isPdf: true, name: file.name, loading: true });
            Promise.all([pdfRender(file, 1.5), pdfRender(file, 2.5)]).then(([thumb, full]) => {
                newItems.value[idx] = { url: thumb, fullUrl: full, isImage: !!thumb, isPdf: true, name: file.name, loading: false };
            }).catch(() => {
                newItems.value[idx] = { url: null, fullUrl: null, isImage: false, isPdf: true, name: file.name, loading: false };
            });
        } else {
            newItems.value.push({ url: null, fullUrl: null, isImage: false, name: file.name });
        }
    }
}
function openLightbox(item) {
    const url = item.fullUrl ?? item.url ?? item;
    if (url) lightboxUrl.value = url;
}
function onFileInput(e) { addFiles(e.target.files); e.target.value = ''; }
function onDrop(e) { isDragging.value = false; addFiles(e.dataTransfer.files); }
function removeNewAttachment(idx) {
    form.value.attachments.splice(idx, 1);
    newItems.value.splice(idx, 1);
}
function removeExisting(att) {
    form.value.remove_attachment_ids.push(att.id);
    existingAttachments.value = existingAttachments.value.filter(a => a.id !== att.id);
}

function submit() {
    errors.value = {};
    submitting.value = true;
    const data = new FormData();
    data.append('_method', 'PUT');
    data.append('title', form.value.title);
    data.append('content', form.value.content);
    form.value.remove_attachment_ids.forEach(id => data.append('remove_attachment_ids[]', id));
    form.value.attachments.forEach(f => data.append('attachments[]', f));

    fetch(route('clerk.announcements.update', { announcement: props.announcement.id }), {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '' },
        body: data,
    }).then(async res => {
        if (res.redirected) { window.location.href = res.url; return; }
        if (!res.ok) {
            const json = await res.json().catch(() => ({}));
            errors.value = json.errors ?? {};
        }
    }).finally(() => { submitting.value = false; });
}
</script>

<template>
    <AppLayout title="お知らせ編集">
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('clerk.announcements.show', { announcement: announcement.id })"
                    class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300 whitespace-nowrap">
                    ← 詳細に戻る
                </Link>
                <h2 class="text-xl font-semibold text-gray-800">お知らせ編集</h2>
            </div>
        </template>

        <div class="rounded bg-white px-4 py-6 sm:p-6 shadow">
            <form @submit.prevent="submit" class="mx-auto max-w-2xl space-y-6">
                <p class="rounded bg-yellow-50 border border-yellow-200 px-4 py-2 text-sm text-yellow-800">
                    ※ 送信先（受信者）は変更できません。タイトル・本文・添付ファイルのみ編集できます。
                </p>

                <!-- タイトル -->
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">タイトル</label>
                    <input v-model="form.title" type="text"
                        class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-purple-400 focus:outline-none focus:ring-1 focus:ring-purple-400"
                        placeholder="お知らせのタイトル" />
                    <p v-if="errors.title" class="mt-1 text-xs text-red-500">{{ errors.title }}</p>
                </div>

                <!-- 内容 -->
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">内容</label>
                    <textarea v-model="form.content" rows="8"
                        class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-purple-400 focus:outline-none focus:ring-1 focus:ring-purple-400"></textarea>
                    <p v-if="errors.content" class="mt-1 text-xs text-red-500">{{ errors.content }}</p>
                </div>

                <!-- 既存の添付ファイル -->
                <div v-if="existingAttachments.length > 0">
                    <label class="mb-2 block text-sm font-medium text-gray-700">現在の添付ファイル</label>
                    <div class="flex flex-wrap gap-3">
                        <div v-for="att in existingAttachments" :key="att.id"
                            class="group relative rounded border border-gray-200 bg-white p-1 shadow-sm">
                            <img v-if="att.mime?.startsWith('image/')"
                                :src="att.thumb_url ?? att.url" :alt="att.original_name"
                                class="h-24 w-24 cursor-pointer rounded object-cover transition hover:opacity-80"
                                @click="openLightbox({ fullUrl: att.url, url: att.url })" />
                            <div v-else class="flex h-24 w-24 flex-col items-center justify-center gap-1 p-2 text-gray-500">
                                <span class="text-3xl">📄</span>
                                <span class="truncate w-full text-center text-xs">{{ att.original_name }}</span>
                            </div>
                            <button type="button" @click="removeExisting(att)"
                                class="absolute -right-2 -top-2 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-xs text-white opacity-0 shadow transition group-hover:opacity-100 hover:bg-red-600">
                                ✕
                            </button>
                            <button v-if="att.mime?.startsWith('image/')" type="button" @click="openLightbox({ fullUrl: att.url, url: att.url })"
                                class="absolute bottom-1 right-1 rounded bg-black/40 px-1 py-0.5 text-xs text-white opacity-0 transition group-hover:opacity-100">
                                🔍
                            </button>
                        </div>
                    </div>
                </div>

                <!-- 添付ファイルを追加（ドロップゾーン） -->
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700">添付ファイルを追加</label>
                    <div class="flex flex-col items-center justify-center rounded-lg border-2 border-dashed px-6 py-6 transition-colors cursor-pointer"
                        :class="isDragging ? 'border-purple-500 bg-purple-50' : 'border-gray-300 bg-gray-50 hover:border-purple-400'"
                        @dragover.prevent="isDragging = true"
                        @dragleave="isDragging = false"
                        @drop.prevent="onDrop"
                        @click="$refs.fileInput.click()">
                        <div class="mb-1 text-2xl text-gray-400">📎</div>
                        <p class="text-sm text-gray-600">ここにファイルをドロップ、またはクリックして選択</p>
                    </div>
                    <input ref="fileInput" type="file" multiple class="hidden" @change="onFileInput" />
                    <div v-if="newItems.length > 0" class="mt-3 flex flex-wrap gap-3">
                        <div v-for="(item, idx) in newItems" :key="idx"
                            class="group relative rounded border border-gray-200 bg-white p-1 shadow-sm">
                            <div v-if="item.loading" class="flex h-24 w-24 items-center justify-center rounded bg-gray-100">
                                <span class="text-xs text-gray-400">変換中...</span>
                            </div>
                            <img v-else-if="item.isImage" :src="item.url" :alt="item.name"
                                class="h-24 w-24 cursor-pointer rounded object-cover transition hover:opacity-80"
                                @click="openLightbox(item)" />
                            <div v-else class="flex h-24 w-24 flex-col items-center justify-center gap-1 p-2 text-gray-500">
                                <span class="text-3xl">{{ item.isPdf ? '📕' : '📄' }}</span>
                                <span class="truncate w-full text-center text-xs">{{ item.name }}</span>
                            </div>
                            <button type="button" @click="removeNewAttachment(idx)"
                                class="absolute -right-2 -top-2 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-xs text-white opacity-0 shadow transition group-hover:opacity-100 hover:bg-red-600">
                                ✕
                            </button>
                            <button v-if="item.isImage" type="button" @click="openLightbox(item)"
                                class="absolute bottom-1 right-1 rounded bg-black/40 px-1 py-0.5 text-xs text-white opacity-0 transition group-hover:opacity-100">
                                🔍
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ボタン -->
                <div class="flex justify-end gap-3">
                    <Link :href="route('clerk.announcements.show', { announcement: announcement.id })"
                        class="rounded border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
                        キャンセル
                    </Link>
                    <button type="submit" :disabled="submitting"
                        class="rounded bg-indigo-600 px-6 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                        更新する
                    </button>
                </div>
            </form>
        </div>
        <!-- ライトボックス -->
        <Teleport to="body">
            <div v-if="lightboxUrl" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/80"
                @click="lightboxUrl = null">
                <img :src="lightboxUrl" alt="拡大表示"
                    class="max-h-[90vh] max-w-[90vw] rounded-lg object-contain shadow-2xl" />
                <button class="absolute right-4 top-4 rounded-full bg-white/20 p-2 text-white hover:bg-white/40"
                    @click.stop="lightboxUrl = null">✕</button>
            </div>
        </Teleport>
    </AppLayout>
</template>
