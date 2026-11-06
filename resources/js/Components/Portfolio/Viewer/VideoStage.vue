<script setup>
import { ref } from 'vue';

// 動画の表示。最初はポスター画像と再生ボタンだけを出し、押されてから本体を読み込む（通信量を抑える）。
defineProps({
    posterUrl: { type: String, default: null },
    fileUrl: { type: String, required: true },
    title: { type: String, default: '' },
    accent: { type: String, default: '#2563eb' },
});

const playing = ref(false);
</script>

<template>
    <div class="relative flex h-full w-full items-center justify-center">
        <video
            v-if="playing"
            :src="fileUrl"
            :poster="posterUrl"
            class="max-h-full max-w-full rounded-lg bg-black shadow-xl"
            controls
            autoplay
            playsinline
        />
        <button v-else type="button" class="group relative flex h-full w-full items-center justify-center" :aria-label="`${title} を再生`" @click="playing = true">
            <img v-if="posterUrl" :src="posterUrl" :alt="title" class="max-h-full max-w-full rounded-lg object-contain shadow-xl" draggable="false" />
            <span
                class="absolute flex h-20 w-20 items-center justify-center rounded-full text-white shadow-lg transition-transform group-hover:scale-110"
                :style="{ backgroundColor: accent }"
            >
                <svg viewBox="0 0 24 24" class="ml-1 h-9 w-9 fill-current"><path d="M8 5v14l11-7z" /></svg>
            </span>
        </button>
    </div>
</template>
