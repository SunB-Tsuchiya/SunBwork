<script setup>
import { computed, reactive, ref } from 'vue';
import ImageLightbox from '@/Components/Portfolio/ImageLightbox.vue';

// 作品のタイトル・説明・コレクション・タグの編集
const props = defineProps({
    item: { type: Object, required: true },
    collections: { type: Array, default: () => [] },
    allTags: { type: Array, default: () => [] },
    isCover: { type: Boolean, default: false },
    thumbUrl: { type: String, default: null },
    // 表示用の大きい画像（画像作品のみ）。読み込み中は thumbUrl を先に出す
    fileUrl: { type: String, default: null },
    // PDF のページ画像 URL（1 ページ目から順）
    pageUrls: { type: Array, default: () => [] },
    // 動画のポスター画像（再生前に表示）
    posterUrl: { type: String, default: null },
    saving: { type: Boolean, default: false },
    // 閲覧専用（Admin）。入力欄を無効にし、保存・削除・表紙設定は出さない
    readOnly: { type: Boolean, default: false },
});
const emit = defineEmits(['save', 'close', 'delete', 'set-cover']);

const form = reactive({
    title: props.item.title,
    description: props.item.description ?? '',
    collection_id: props.item.collection_id ?? '',
    tags: [...props.item.tags],
});
const tagInput = ref('');
const lightbox = ref(false);
const fullLoaded = ref(false);
const canZoom = computed(() => props.item.type === 'image' && !!props.fileUrl);
const isPdf = computed(() => props.item.type === 'pdf' && props.pageUrls.length > 0);
const pageIndex = ref(0);
const lightboxStart = ref(0);
function openLightbox(start = 0) {
    lightboxStart.value = start;
    lightbox.value = true;
}
const notReady = computed(() => props.item.status !== 'ready');
const STATUS_TEXT = { uploading: 'アップロードが完了していません', processing: 'ページの変換が完了していません', failed: '変換に失敗しました' };

const suggestions = computed(() =>
    props.allTags.filter((t) => !form.tags.includes(t) && t.includes(tagInput.value.trim())).slice(0, 6),
);

function addTag(name = tagInput.value) {
    const t = name.trim();
    if (t && !form.tags.includes(t) && form.tags.length < 20) form.tags.push(t);
    tagInput.value = '';
}
function removeTag(t) {
    form.tags = form.tags.filter((x) => x !== t);
}
function submit() {
    if (tagInput.value.trim()) addTag();
    emit('save', {
        title: form.title.trim(),
        description: form.description,
        collection_id: form.collection_id === '' ? null : Number(form.collection_id),
        tags: form.tags,
    });
}
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="emit('close')">
        <div class="flex max-h-full w-full max-w-5xl flex-col overflow-hidden rounded-xl bg-white shadow-xl md:flex-row">
            <!-- 左: 大きな画像（クリックで全画面・等倍に拡大） -->
            <div class="relative flex min-h-[16rem] flex-1 items-center justify-center bg-gray-100 p-3 md:min-h-[28rem]">
                <button
                    v-if="canZoom"
                    type="button"
                    class="group relative flex h-full w-full cursor-zoom-in items-center justify-center"
                    title="クリックで拡大"
                    @click="openLightbox(0)"
                >
                    <!-- 先にサムネイルをぼかして出し、大きい画像が読み込めたら切り替える -->
                    <img v-if="thumbUrl && !fullLoaded" :src="thumbUrl" alt="" class="absolute max-h-[70vh] max-w-full object-contain blur-sm" />
                    <img :src="fileUrl" :alt="item.title" class="relative max-h-[70vh] max-w-full object-contain" :class="fullLoaded ? '' : 'opacity-0'" @load="fullLoaded = true" />
                    <span class="absolute bottom-2 right-2 rounded-full bg-black/60 px-2.5 py-1 text-xs text-white opacity-90 group-hover:opacity-100">🔍 クリックで拡大</span>
                </button>
                <!-- PDF: ページ画像をめくって確認。クリックで全画面（← → でページ送り） -->
                <div v-else-if="isPdf" class="flex h-full w-full flex-col items-center gap-2">
                    <button type="button" class="group relative flex flex-1 cursor-zoom-in items-center justify-center" title="クリックで拡大" @click="openLightbox(pageIndex)">
                        <img :src="pageUrls[pageIndex]" :alt="`${item.title} ${pageIndex + 1}ページ`" class="max-h-[62vh] max-w-full bg-white object-contain shadow" />
                        <span class="absolute bottom-2 right-2 rounded-full bg-black/60 px-2.5 py-1 text-xs text-white opacity-90 group-hover:opacity-100">🔍 クリックで拡大</span>
                    </button>
                    <div class="flex items-center gap-3 text-sm text-gray-600">
                        <button type="button" :disabled="pageIndex === 0" class="rounded border border-gray-300 bg-white px-3 py-1 hover:bg-gray-50 disabled:opacity-30" @click="pageIndex--">‹ 前</button>
                        <span>{{ pageIndex + 1 }} / {{ pageUrls.length }} ページ</span>
                        <button type="button" :disabled="pageIndex === pageUrls.length - 1" class="rounded border border-gray-300 bg-white px-3 py-1 hover:bg-gray-50 disabled:opacity-30" @click="pageIndex++">次 ›</button>
                    </div>
                    <a v-if="fileUrl" :href="fileUrl" target="_blank" rel="noopener noreferrer" class="text-xs text-gray-500 underline hover:text-gray-800">元の PDF を開く</a>
                </div>
                <!-- 動画: ポスターを出して、押すと再生（必要になるまで本体は読み込まない） -->
                <video
                    v-else-if="item.type === 'video' && fileUrl && !notReady"
                    :src="fileUrl"
                    :poster="posterUrl || thumbUrl"
                    class="max-h-[70vh] max-w-full rounded bg-black"
                    controls
                    playsinline
                    preload="none"
                />
                <img v-else-if="thumbUrl" :src="thumbUrl" alt="" class="max-h-[70vh] max-w-full object-contain" />
                <p v-else class="text-sm text-gray-400">プレビューはありません</p>
            </div>

            <!-- 右: 入力欄 -->
            <div class="w-full shrink-0 overflow-y-auto p-5 md:w-96">
            <h3 class="mb-3 text-base font-semibold text-gray-900">{{ readOnly ? '作品の詳細（閲覧のみ）' : '作品の編集' }}</h3>
            <p v-if="notReady" class="mb-3 rounded bg-amber-50 px-3 py-2 text-xs text-amber-800">
                {{ STATUS_TEXT[item.status] ?? '処理が完了していません' }}。{{ readOnly ? '本人に再アップロードを依頼してください。' : '不要であれば削除して、もう一度追加してください。' }}
            </p>

            <label class="mb-3 block text-xs text-gray-600">
                タイトル（必須）
                <input v-model="form.title" type="text" maxlength="150" :disabled="readOnly" class="mt-1 block w-full rounded border-gray-300 text-sm disabled:bg-gray-50" />
            </label>
            <label class="mb-3 block text-xs text-gray-600">
                説明
                <textarea v-model="form.description" rows="3" maxlength="2000" class="mt-1 block w-full rounded border-gray-300 text-sm disabled:bg-gray-50" :disabled="readOnly" placeholder="コンセプトや工夫した点など" />
            </label>
            <label class="mb-3 block text-xs text-gray-600">
                コレクション
                <select v-model="form.collection_id" :disabled="readOnly" class="mt-1 block w-full rounded border-gray-300 text-sm disabled:bg-gray-50">
                    <option value="">未分類</option>
                    <option v-for="c in collections" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
            </label>

            <div class="mb-4 text-xs text-gray-600">
                タグ
                <div class="mt-1 flex flex-wrap items-center gap-1 rounded border border-gray-300 p-1.5">
                    <span v-for="t in form.tags" :key="t" class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-gray-800">
                        {{ t }}
                        <button v-if="!readOnly" type="button" class="text-gray-400 hover:text-gray-700" @click="removeTag(t)">×</button>
                    </span>
                    <input
                        v-if="!readOnly"
                        v-model="tagInput"
                        type="text"
                        maxlength="50"
                        class="min-w-[6rem] flex-1 border-0 p-0.5 text-sm focus:ring-0"
                        placeholder="入力して Enter"
                        @keydown.enter.prevent="addTag()"
                    />
                </div>
                <div v-if="!readOnly && suggestions.length" class="mt-1 flex flex-wrap gap-1">
                    <button v-for="s in suggestions" :key="s" type="button" class="rounded-full border border-gray-300 px-2 py-0.5 text-gray-600 hover:bg-gray-50" @click="addTag(s)">+ {{ s }}</button>
                </div>
            </div>

            <div v-if="readOnly" class="flex items-center justify-between gap-2">
                <span class="text-xs text-gray-500">修正・削除が必要な場合は、本人に依頼してください。</span>
                <button type="button" class="rounded px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-100" @click="emit('close')">閉じる</button>
            </div>
            <div v-else class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex gap-2">
                    <button type="button" class="rounded border border-red-300 px-3 py-1.5 text-sm text-red-600 hover:bg-red-50" @click="emit('delete')">削除</button>
                    <button
                        v-if="item.collection_id && !isCover"
                        type="button"
                        class="rounded border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50"
                        @click="emit('set-cover')"
                    >表紙にする</button>
                    <span v-else-if="isCover" class="self-center text-xs text-gray-500">現在の表紙</span>
                </div>
                <div class="flex gap-2">
                    <button type="button" class="rounded px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-100" @click="emit('close')">キャンセル</button>
                    <button type="button" :disabled="saving || !form.title.trim()" class="rounded bg-gray-900 px-4 py-1.5 text-sm text-white hover:bg-gray-700 disabled:opacity-40" @click="submit">保存</button>
                </div>
            </div>
            </div>
        </div>

        <ImageLightbox v-if="lightbox" :src="isPdf ? null : fileUrl" :srcs="isPdf ? pageUrls : []" :start="lightboxStart" :alt="item.title" @close="lightbox = false" />
    </div>
</template>
