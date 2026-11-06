<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

// 画像の全画面表示。1 回クリックで等倍（細部の確認）、もう 1 回で全体表示に戻る。Esc で閉じる。
// 複数枚（PDF のページ）を渡すと、← → キーと左右ボタンでページを送れる。
const props = defineProps({
    src: { type: String, default: null },
    srcs: { type: Array, default: () => [] },
    start: { type: Number, default: 0 },
    alt: { type: String, default: '' },
});
const emit = defineEmits(['close']);

const list = computed(() => (props.srcs.length ? props.srcs : [props.src].filter(Boolean)));
const index = ref(Math.min(props.start, Math.max(0, list.value.length - 1)));
const zoomed = ref(false);
const many = computed(() => list.value.length > 1);

function go(delta) {
    const next = index.value + delta;
    if (next < 0 || next >= list.value.length) return;
    index.value = next;
    zoomed.value = false;
}

function onKey(e) {
    if (e.key === 'Escape') {
        // 等倍表示中は先に全体表示へ戻し、もう一度 Esc で閉じる
        if (zoomed.value) zoomed.value = false;
        else emit('close');
    } else if (e.key === 'ArrowLeft') go(-1);
    else if (e.key === 'ArrowRight') go(1);
}

onMounted(() => {
    window.addEventListener('keydown', onKey);
    // 背景がスクロールしないようにする
    document.body.style.overflow = 'hidden';
});
onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKey);
    document.body.style.overflow = '';
});
</script>

<template>
    <div class="fixed inset-0 z-[60] bg-black/90">
        <button
            type="button"
            class="absolute right-4 top-4 z-10 rounded-full bg-white/15 px-3 py-1.5 text-sm text-white hover:bg-white/30"
            @click="emit('close')"
        >✕ 閉じる</button>

        <template v-if="many">
            <button
                v-if="index > 0"
                type="button"
                class="absolute left-3 top-1/2 z-10 -translate-y-1/2 rounded-full bg-white/15 px-3 py-3 text-lg text-white hover:bg-white/30"
                aria-label="前のページ"
                @click="go(-1)"
            >‹</button>
            <button
                v-if="index < list.length - 1"
                type="button"
                class="absolute right-3 top-1/2 z-10 -translate-y-1/2 rounded-full bg-white/15 px-3 py-3 text-lg text-white hover:bg-white/30"
                aria-label="次のページ"
                @click="go(1)"
            >›</button>
        </template>

        <div class="absolute bottom-4 left-1/2 z-10 -translate-x-1/2 rounded-full bg-white/15 px-3 py-1 text-xs text-white">
            <span v-if="many">{{ index + 1 }} / {{ list.length }}　</span>{{ zoomed ? 'クリックで全体表示に戻る' : 'クリックで等倍に拡大' }}
        </div>

        <!-- 全体表示: 画面にフィット / 等倍: 実寸でスクロール -->
        <div class="h-full w-full" :class="zoomed ? 'overflow-auto' : 'flex items-center justify-center p-6'" @click.self="emit('close')">
            <img
                :src="list[index]"
                :alt="alt"
                draggable="false"
                :class="zoomed ? 'max-w-none cursor-zoom-out' : 'max-h-full max-w-full cursor-zoom-in object-contain'"
                @click.stop="zoomed = !zoomed"
            />
        </div>
    </div>
</template>
