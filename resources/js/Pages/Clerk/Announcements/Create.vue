<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import RecipientPicker from '@/Components/Clerk/RecipientPicker.vue';
import SimpleRichText from '@/Components/Common/SimpleRichText.vue';
import * as pdfjsLib from 'pdfjs-dist';
pdfjsLib.GlobalWorkerOptions.workerSrc = new URL('pdfjs-dist/build/pdf.worker.mjs', import.meta.url).toString();

const props = defineProps({
    users: Array,
    showBlocks: { type: Boolean, default: false },
    companies: { type: Array, default: null },
});

const page = usePage();
const canCrossCompany = computed(() => page.props.auth?.featureFlags?.crossCompanyAnnouncement ?? false);

const selectedCompanyIds = ref([]);
const scopedUsers = computed(() => {
    if (!canCrossCompany.value || selectedCompanyIds.value.length === 0) return props.users;
    return props.users.filter(u => selectedCompanyIds.value.includes(u.company_id));
});

const form = ref({ target_type: 'all', target_block: 'all', title: '', content: '', user_ids: [], attachments: [] });
const errors = ref({});
const attachmentItems = ref([]);
const lightboxUrl = ref(null);
const isDragging = ref(false);
const submitting = ref(false);

async function pdfRender(file, scale) {
    const buf = await file.arrayBuffer();
    const pdf = await pdfjsLib.getDocument({ data: buf }).promise;
    const pg = await pdf.getPage(1);
    const vp = pg.getViewport({ scale });
    const canvas = document.createElement('canvas');
    canvas.width = vp.width; canvas.height = vp.height;
    await pg.render({ canvasContext: canvas.getContext('2d'), viewport: vp }).promise;
    return canvas.toDataURL('image/jpeg', 0.92);
}
async function pdfToThumb(file) { try { return await pdfRender(file, 1.5); } catch { return null; } }
async function pdfToFull(file)  { try { return await pdfRender(file, 2.5); } catch { return null; } }

function toggleCompany(id) {
    const idx = selectedCompanyIds.value.indexOf(id);
    if (idx === -1) selectedCompanyIds.value.push(id);
    else selectedCompanyIds.value.splice(idx, 1);
    form.value.user_ids = [];
}

async function addFiles(files) {
    for (const file of Array.from(files)) {
        form.value.attachments.push(file);
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = ev => attachmentItems.value.push({ url: ev.target.result, isImage: true, name: file.name });
            reader.readAsDataURL(file);
        } else if (file.type === 'application/pdf') {
            const idx = attachmentItems.value.length;
            attachmentItems.value.push({ url: null, fullUrl: null, isImage: false, isPdf: true, name: file.name, loading: true });
            Promise.all([pdfToThumb(file), pdfToFull(file)]).then(([thumb, full]) => {
                attachmentItems.value[idx] = { url: thumb, fullUrl: full, isImage: !!thumb, isPdf: true, name: file.name, loading: false };
            });
        } else {
            attachmentItems.value.push({ url: null, isImage: false, name: file.name });
        }
    }
}
function onFileInput(e) { addFiles(e.target.files); e.target.value = ''; }
function onDrop(e)      { isDragging.value = false; addFiles(e.dataTransfer.files); }
function removeAttachment(idx) { form.value.attachments.splice(idx, 1); attachmentItems.value.splice(idx, 1); }
function openLightbox(item) { const url = item.fullUrl ?? item.url; if (url) lightboxUrl.value = url; }

function buildFormData(isDraft) {
    const data = new FormData();
    data.append('target_type', form.value.target_type);
    data.append('target_block', form.value.target_block);
    data.append('title', form.value.title);
    data.append('content', form.value.content);
    data.append('is_draft', isDraft ? '1' : '0');
    if (canCrossCompany.value && selectedCompanyIds.value.length === 1) {
        data.append('target_company_id', selectedCompanyIds.value[0]);
    }
    form.value.user_ids.forEach(id => data.append('user_ids[]', id));
    form.value.attachments.forEach(f => data.append('attachments[]', f));
    return data;
}

function submit(isDraft) {
    errors.value = {};
    submitting.value = true;
    router.post(route('clerk.announcements.store'), buildFormData(isDraft), {
        forceFormData: true,
        onError: (e) => { errors.value = e; },
        onFinish: () => { submitting.value = false; },
    });
}
</script>

<template>
    <AppLayout title="お知らせ作成">
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('clerk.announcements.index')"
                    class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300 whitespace-nowrap">
                    ← 一覧に戻る
                </Link>
                <h2 class="text-xl font-semibold text-gray-800">お知らせ作成</h2>
            </div>
        </template>

        <div class="rounded bg-white px-4 py-6 sm:p-6 shadow">
            <form @submit.prevent class="mx-auto max-w-2xl space-y-6">

                <!-- 送信先の会社（cross-company 時のみ） -->
                <div v-if="canCrossCompany && companies">
                    <label class="mb-2 block text-sm font-medium text-gray-700">送信先の会社（複数選択可）</label>
                    <div class="flex flex-wrap gap-3">
                        <label v-for="c in companies" :key="c.id"
                            class="flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 transition"
                            :class="selectedCompanyIds.includes(c.id) ? 'border-purple-500 bg-purple-50 text-purple-800' : 'border-gray-200 bg-white text-gray-700 hover:border-purple-300'">
                            <input type="checkbox" :value="c.id" :checked="selectedCompanyIds.includes(c.id)"
                                @change="toggleCompany(c.id)" class="text-purple-600" />
                            <span class="text-sm font-medium">{{ c.name }}</span>
                        </label>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">
                        選択した会社のメンバーを宛先として指定できます。<span class="font-medium text-purple-700">未選択の場合は全会社のメンバーが対象になります。</span>
                    </p>
                </div>

                <!-- 宛先 -->
                <RecipientPicker
                    v-model:target-type="form.target_type"
                    v-model:block="form.target_block"
                    :show-blocks="showBlocks"
                    v-model:user-ids="form.user_ids"
                    :users="scopedUsers"
                    :companies="companies"
                    :show-company="canCrossCompany"
                    accent="purple"
                    :error="errors.target_type"
                    :user-error="errors.user_ids"
                />

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
                    <SimpleRichText v-model="form.content" placeholder="お知らせの内容を入力してください" />
                    <p v-if="errors.content" class="mt-1 text-xs text-red-500">{{ errors.content }}</p>
                </div>

                <!-- 添付ファイル -->
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700">添付ファイル（任意）</label>
                    <div class="flex flex-col items-center justify-center rounded-lg border-2 border-dashed px-6 py-8 transition-colors cursor-pointer"
                        :class="isDragging ? 'border-purple-500 bg-purple-50' : 'border-gray-300 bg-gray-50 hover:border-purple-400'"
                        @dragover.prevent="isDragging = true" @dragleave="isDragging = false"
                        @drop.prevent="onDrop" @click="$refs.fileInput.click()">
                        <div class="mb-2 text-3xl text-gray-400">📎</div>
                        <p class="text-sm text-gray-600">ここにファイルをドロップ、またはクリックして選択</p>
                        <p class="mt-1 text-xs text-gray-400">JPG / PNG / PDF など（最大 20MB）</p>
                    </div>
                    <input ref="fileInput" type="file" multiple class="hidden" @change="onFileInput" />
                    <div v-if="attachmentItems.length > 0" class="mt-4 flex flex-wrap gap-3">
                        <div v-for="(item, idx) in attachmentItems" :key="idx"
                            class="group relative rounded border border-gray-200 bg-white p-1 shadow-sm">
                            <div v-if="item.loading" class="flex h-24 w-24 items-center justify-center rounded bg-gray-100">
                                <span class="text-xs text-gray-400">変換中...</span>
                            </div>
                            <img v-else-if="item.isImage" :src="item.url" :alt="item.name"
                                class="h-24 w-24 cursor-pointer rounded object-cover transition hover:opacity-80" @click="openLightbox(item)" />
                            <div v-else class="flex h-24 w-24 flex-col items-center justify-center gap-1 text-gray-500">
                                <span class="text-3xl">{{ item.isPdf ? '📕' : '📄' }}</span>
                                <span class="max-w-full truncate px-1 text-center text-xs">{{ item.name }}</span>
                            </div>
                            <button type="button" @click="removeAttachment(idx)"
                                class="absolute -right-2 -top-2 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-xs text-white opacity-0 shadow transition group-hover:opacity-100 hover:bg-red-600">✕</button>
                            <button v-if="item.isImage" type="button" @click="openLightbox(item)"
                                class="absolute bottom-1 right-1 rounded bg-black/40 px-1 py-0.5 text-xs text-white opacity-0 transition group-hover:opacity-100">🔍</button>
                        </div>
                    </div>
                </div>

                <!-- ボタン -->
                <div class="flex justify-end gap-3">
                    <Link :href="route('clerk.announcements.index')"
                        class="rounded border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
                        キャンセル
                    </Link>
                    <button type="button" :disabled="submitting" @click="submit(true)"
                        class="rounded border border-yellow-400 bg-yellow-50 px-5 py-2 text-sm font-medium text-yellow-800 hover:bg-yellow-100 disabled:opacity-50">
                        下書き保存
                    </button>
                    <button type="button" :disabled="submitting" @click="submit(false)"
                        class="rounded bg-purple-600 px-6 py-2 text-sm font-medium text-white hover:bg-purple-700 disabled:opacity-50">
                        送信する
                    </button>
                </div>
            </form>
        </div>

        <!-- ライトボックス -->
        <Teleport to="body">
            <div v-if="lightboxUrl" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/80" @click="lightboxUrl = null">
                <img :src="lightboxUrl" alt="拡大表示" class="max-h-[90vh] max-w-[90vw] rounded-lg object-contain shadow-2xl" />
                <button class="absolute right-4 top-4 rounded-full bg-white/20 p-2 text-white hover:bg-white/40" @click.stop="lightboxUrl = null">✕</button>
            </div>
        </Teleport>
    </AppLayout>
</template>
