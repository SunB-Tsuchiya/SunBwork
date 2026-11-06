<script setup>
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import Slideshow from '@/Components/Portfolio/Viewer/Slideshow.vue';

// お客様に見せる閲覧ページ。AppLayout は使わない独立レイアウト（別タブ・ヘッダーなし）。
// 白ベース＋モノトーン＋デザイナーが選んだアクセント色。管理用の操作は一切置かない。
// トップはコレクションの表紙一覧。表紙を押すと全画面スライドショーが開く。
const props = defineProps({
    profile: { type: Object, required: true }, // { accent_color, name, headline }
    collections: { type: Array, default: () => [] },
    items: { type: Array, default: () => [] },
});

const accent = computed(() => props.profile.accent_color || '#2563eb');
const tagFilter = ref('');
const slideshow = ref(null); // { title, items }

const allTags = computed(() => [...new Set(props.items.flatMap((i) => i.tags))].sort());
const filtered = computed(() => (tagFilter.value ? props.items.filter((i) => i.tags.includes(tagFilter.value)) : props.items));

const thumb = (it) => (it?.has_thumb ? route('portfolio.api.files', { item: it.id, kind: 'thumb' }) : null);

// 表紙カード: 各コレクション（絞り込み後に作品があるもの）＋ 未分類（その他）。2 枚以上あるときは先頭に「すべての作品」
const cards = computed(() => {
    const list = [];
    for (const c of props.collections) {
        const its = filtered.value.filter((i) => i.collection_id === c.id);
        if (!its.length) continue;
        const cover = its.find((i) => i.id === c.cover_item_id) ?? its[0];
        list.push({ key: `c${c.id}`, name: c.name, description: c.description, items: its, cover });
    }
    const rest = filtered.value.filter((i) => !i.collection_id);
    if (rest.length) list.push({ key: 'rest', name: props.collections.length ? 'その他' : 'すべての作品', description: null, items: rest, cover: rest[0] });
    if (list.length > 1) {
        list.unshift({ key: 'all', name: 'すべての作品', description: null, items: filtered.value, cover: filtered.value[0] });
    }
    return list;
});

const TYPE_LABEL = { image: '画像', pdf: 'PDF', video: '動画' };
function typeSummary(items) {
    const counts = {};
    items.forEach((i) => { counts[i.type] = (counts[i.type] ?? 0) + 1; });
    return Object.entries(counts).map(([t, n]) => `${TYPE_LABEL[t]} ${n}`).join(' ・ ');
}
</script>

<template>
    <Head :title="`${profile.name} ポートフォリオ`" />

    <div class="min-h-screen bg-white text-gray-900" :style="{ '--accent': accent }">
        <div class="mx-auto max-w-6xl px-5 pb-20 pt-12 sm:px-10 sm:pt-20">
            <!-- ヘッダー: 名前・肩書き -->
            <header class="mb-10 sm:mb-14">
                <div class="mb-5 h-1.5 w-14 rounded-full" :style="{ backgroundColor: accent }" />
                <h1 class="text-4xl font-light tracking-tight text-gray-900 sm:text-6xl">{{ profile.name }}</h1>
                <p v-if="profile.headline" class="mt-3 text-base tracking-wide text-gray-500 sm:text-lg">{{ profile.headline }}</p>
                <p class="mt-6 text-xs font-medium uppercase tracking-[0.3em] text-gray-400">Portfolio</p>
            </header>

            <!-- タグ絞り込み -->
            <div v-if="allTags.length" class="mb-8 flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    class="rounded-full border px-4 py-1.5 text-sm transition-colors"
                    :class="!tagFilter ? 'border-transparent text-white' : 'border-gray-300 text-gray-600 hover:bg-gray-50'"
                    :style="!tagFilter ? { backgroundColor: accent } : {}"
                    @click="tagFilter = ''"
                >すべて</button>
                <button
                    v-for="t in allTags"
                    :key="t"
                    type="button"
                    class="rounded-full border px-4 py-1.5 text-sm transition-colors"
                    :class="tagFilter === t ? 'border-transparent text-white' : 'border-gray-300 text-gray-600 hover:bg-gray-50'"
                    :style="tagFilter === t ? { backgroundColor: accent } : {}"
                    @click="tagFilter = tagFilter === t ? '' : t"
                >{{ t }}</button>
            </div>

            <!-- コレクション表紙一覧 -->
            <div v-if="cards.length" class="grid gap-x-8 gap-y-12 sm:grid-cols-2 lg:grid-cols-3">
                <button
                    v-for="(card, i) in cards"
                    :key="card.key"
                    type="button"
                    class="cover-card group text-left"
                    :style="{ animationDelay: `${Math.min(i, 8) * 70}ms` }"
                    @click="slideshow = { title: card.name, items: card.items }"
                >
                    <div class="relative aspect-[4/3] overflow-hidden rounded-2xl bg-gray-100 shadow-sm ring-1 ring-black/5 transition-all duration-300 group-hover:-translate-y-1 group-hover:shadow-xl">
                        <img
                            v-if="thumb(card.cover)"
                            :src="thumb(card.cover)"
                            :alt="card.name"
                            class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                            draggable="false"
                        />
                        <!-- アクセント色のワンポイント -->
                        <span class="absolute bottom-0 left-0 h-1 w-0 transition-all duration-500 group-hover:w-full" :style="{ backgroundColor: accent }" />
                    </div>
                    <div class="mt-4 flex items-baseline justify-between gap-3">
                        <h2 class="text-lg font-medium tracking-wide text-gray-900">{{ card.name }}</h2>
                        <span class="shrink-0 text-xs text-gray-400">{{ card.items.length }} 作品</span>
                    </div>
                    <p v-if="card.description" class="mt-1 text-sm leading-relaxed text-gray-500">{{ card.description }}</p>
                    <p class="mt-1 text-xs text-gray-400">{{ typeSummary(card.items) }}</p>
                </button>
            </div>

            <p v-else class="py-24 text-center text-gray-400">
                {{ items.length ? 'この条件に合う作品はありません。' : 'まだ作品がありません。' }}
            </p>
        </div>

        <Slideshow v-if="slideshow" :items="slideshow.items" :title="slideshow.title" :accent="accent" @close="slideshow = null" />
    </div>
</template>

<style scoped>
.cover-card {
    opacity: 0;
    animation: cover-in 0.6s ease forwards;
}
@keyframes cover-in {
    from {
        opacity: 0;
        transform: translateY(18px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
