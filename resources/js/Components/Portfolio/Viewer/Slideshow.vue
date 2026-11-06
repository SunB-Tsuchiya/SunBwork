<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useSwipe } from '@vueuse/core';
import ImageLightbox from '@/Components/Portfolio/ImageLightbox.vue';
import PdfSpread from '@/Components/Portfolio/Viewer/PdfSpread.vue';
import VideoStage from '@/Components/Portfolio/Viewer/VideoStage.vue';

// 全画面スライドショー。画像はフィット表示、PDF は見開きでページめくり、動画はポスターから再生。
// 操作: スワイプ / ← → キー / Esc。PDF は矢印・スワイプでページをめくり、端まで来たら次/前の作品へ進む。
const props = defineProps({
    items: { type: Array, required: true },
    start: { type: Number, default: 0 },
    title: { type: String, default: '' },
    accent: { type: String, default: '#2563eb' },
});
const emit = defineEmits(['close']);

const index = ref(Math.min(props.start, props.items.length - 1));
const direction = ref('next'); // 切り替えアニメの向き
const stage = ref(null); // PDF のとき PdfSpread のインスタンス
const lightbox = ref(null); // { srcs, start }
const stageArea = ref(null);

const item = computed(() => props.items[index.value]);
const fileUrl = (it, kind = 'file') => route('portfolio.api.files', { item: it.id, kind });
const pagesOf = (it) => Array.from({ length: it.page_count ?? 0 }, (_, i) => route('portfolio.api.pages', { item: it.id, page: i + 1 }));
const pages = computed(() => (item.value.type === 'pdf' ? pagesOf(item.value) : []));

// ---- 作品の移動 ----
function goTo(i, dir) {
    if (i < 0 || i >= props.items.length || i === index.value) return;
    direction.value = dir ?? (i > index.value ? 'next' : 'prev');
    index.value = i;
}
function next() {
    // PDF はまずページをめくる。端まで来ていたら次の作品へ
    if (item.value.type === 'pdf' && stage.value?.next()) return;
    goTo(index.value + 1, 'next');
}
function prev() {
    if (item.value.type === 'pdf' && stage.value?.prev()) return;
    goTo(index.value - 1, 'prev');
}

// ---- スワイプ（タッチ）----
const { direction: swipeDir, lengthX, lengthY } = useSwipe(stageArea, {
    threshold: 50,
    onSwipeEnd() {
        // 縦スクロール寄りの動きは無視する
        if (Math.abs(lengthX.value) < Math.abs(lengthY.value)) return;
        if (swipeDir.value === 'left') next();
        else if (swipeDir.value === 'right') prev();
    },
});

// ---- キー操作 ----
function onKey(e) {
    if (lightbox.value) return; // 拡大表示中は拡大側が処理する
    if (e.key === 'ArrowRight') next();
    else if (e.key === 'ArrowLeft') prev();
    else if (e.key === 'Escape') {
        if (document.fullscreenElement) document.exitFullscreen?.();
        else emit('close');
    }
}

// ---- 全画面（タブレットで見せるとき用）----
const isFullscreen = ref(false);
function onFsChange() {
    isFullscreen.value = !!document.fullscreenElement;
}
function toggleFullscreen() {
    if (document.fullscreenElement) document.exitFullscreen?.();
    else document.documentElement.requestFullscreen?.().catch(() => {});
}

// ---- 先読み（前後の作品の画像を先に読み込んで、切り替えを速くする）----
const preloaded = new Set();
function preload(it) {
    if (!it) return;
    const urls = it.type === 'image' ? [fileUrl(it)]
        : it.type === 'pdf' ? pagesOf(it).slice(0, 3)
        : it.has_poster ? [fileUrl(it, 'poster')] : [];
    urls.forEach((u) => {
        if (preloaded.has(u)) return;
        preloaded.add(u);
        const img = new Image();
        img.src = u;
    });
}
watch(index, (i) => { preload(props.items[i + 1]); preload(props.items[i - 1]); }, { immediate: true });

function openLightbox(srcs, start = 0) {
    lightbox.value = { srcs, start };
}

onMounted(() => {
    window.addEventListener('keydown', onKey);
    document.addEventListener('fullscreenchange', onFsChange);
    document.body.style.overflow = 'hidden'; // 背景（トップ画面）をスクロールさせない
});
onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKey);
    document.removeEventListener('fullscreenchange', onFsChange);
    document.body.style.overflow = '';
    if (document.fullscreenElement) document.exitFullscreen?.();
});
</script>

<template>
    <div class="fixed inset-0 z-50 flex flex-col bg-[#f7f7f5] text-gray-900 select-none">
        <!-- 上部バー -->
        <header class="flex items-center justify-between gap-3 px-4 py-3 sm:px-6">
            <div class="min-w-0">
                <div class="overflow-hidden text-ellipsis whitespace-nowrap text-sm font-medium tracking-wide text-gray-700">{{ title }}</div>
                <div class="text-xs text-gray-400">
                    {{ index + 1 }} / {{ items.length }}
                    <template v-if="item.type === 'pdf' && stage"> ・ p.{{ stage.label }}</template>
                </div>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <button type="button" class="rounded-full border border-gray-300 bg-white px-3 py-1.5 text-xs text-gray-600 hover:bg-gray-100" @click="toggleFullscreen">
                    {{ isFullscreen ? '全画面を終了' : '全画面' }}
                </button>
                <button type="button" class="rounded-full px-4 py-1.5 text-xs font-medium text-white hover:opacity-90" :style="{ backgroundColor: accent }" @click="emit('close')">✕ 閉じる</button>
            </div>
        </header>

        <!-- 作品の表示エリア（スワイプはここで受ける） -->
        <main ref="stageArea" class="relative min-h-0 flex-1 px-4 sm:px-16" style="touch-action: pan-y">
            <Transition :name="`slide-${direction}`" mode="out-in">
                <div :key="item.id" class="h-full w-full py-2">
                    <!-- 画像: 画面にフィット。クリックで拡大 -->
                    <button
                        v-if="item.type === 'image'"
                        type="button"
                        class="flex h-full w-full cursor-zoom-in items-center justify-center"
                        @click="openLightbox([fileUrl(item)])"
                    >
                        <img :src="fileUrl(item)" :alt="item.title" class="max-h-full max-w-full rounded-lg object-contain shadow-xl" draggable="false" />
                    </button>

                    <!-- PDF: 見開き＋ページめくり -->
                    <PdfSpread v-else-if="item.type === 'pdf'" ref="stage" :pages="pages" :title="item.title" @zoom="(i) => openLightbox(pages, i)" />

                    <!-- 動画 -->
                    <VideoStage
                        v-else-if="item.type === 'video'"
                        :poster-url="item.has_poster ? fileUrl(item, 'poster') : null"
                        :file-url="fileUrl(item)"
                        :title="item.title"
                        :accent="accent"
                    />
                </div>
            </Transition>

            <!-- 前後ボタン（マウス・タッチ両対応。スワイプでも操作できる） -->
            <button
                v-if="index > 0 || (item.type === 'pdf')"
                type="button"
                class="absolute left-2 top-1/2 hidden h-12 w-12 -translate-y-1/2 items-center justify-center rounded-full bg-white/80 text-2xl text-gray-500 shadow hover:bg-white hover:text-gray-900 sm:flex"
                aria-label="前へ"
                @click="prev"
            >‹</button>
            <button
                v-if="index < items.length - 1 || (item.type === 'pdf')"
                type="button"
                class="absolute right-2 top-1/2 hidden h-12 w-12 -translate-y-1/2 items-center justify-center rounded-full bg-white/80 text-2xl text-gray-500 shadow hover:bg-white hover:text-gray-900 sm:flex"
                aria-label="次へ"
                @click="next"
            >›</button>
        </main>

        <!-- キャプション（タイトル・説明）と進行インジケーター -->
        <footer class="px-4 pb-4 pt-2 sm:px-6">
            <div class="mx-auto max-w-3xl text-center">
                <h2 class="text-lg font-medium tracking-wide text-gray-900 sm:text-xl">{{ item.title }}</h2>
                <p v-if="item.description" class="mx-auto mt-1 max-h-20 max-w-2xl overflow-y-auto whitespace-pre-line text-sm leading-relaxed text-gray-500">{{ item.description }}</p>
            </div>
            <div class="mx-auto mt-3 flex max-w-xl items-center gap-1">
                <button
                    v-for="(it, i) in items"
                    :key="it.id"
                    type="button"
                    class="h-1.5 flex-1 rounded-full transition-colors"
                    :style="{ backgroundColor: i === index ? accent : '#d6d3d1' }"
                    :aria-label="`${i + 1} 番目の作品へ`"
                    @click="goTo(i)"
                />
            </div>
        </footer>

        <ImageLightbox v-if="lightbox" :srcs="lightbox.srcs" :start="lightbox.start" :alt="item.title" @close="lightbox = null" />
    </div>
</template>

<style scoped>
.slide-next-enter-active,
.slide-next-leave-active,
.slide-prev-enter-active,
.slide-prev-leave-active {
    transition: opacity 0.22s ease, transform 0.22s ease;
}
.slide-next-enter-from { opacity: 0; transform: translateX(40px); }
.slide-next-leave-to { opacity: 0; transform: translateX(-40px); }
.slide-prev-enter-from { opacity: 0; transform: translateX(-40px); }
.slide-prev-leave-to { opacity: 0; transform: translateX(40px); }
</style>
