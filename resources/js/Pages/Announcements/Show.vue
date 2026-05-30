<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import * as pdfjsLib from 'pdfjs-dist';
pdfjsLib.GlobalWorkerOptions.workerSrc = new URL('pdfjs-dist/build/pdf.worker.mjs', import.meta.url).toString();

const props = defineProps({
    recipient: Object,
});

const targetLabel = (type) => ({
    all: '全員', employees_only: '社員のみ', individual: '個別選択',
}[type] ?? type);

// PDF を canvas で描画して data URL に変換
const pdfImages = ref({});
const lightboxUrl = ref(null);

onMounted(async () => {
    for (const att of (props.recipient.attachments ?? [])) {
        if (att.mime === 'application/pdf') {
            try {
                const res = await fetch(att.url);
                const buf = await res.arrayBuffer();
                const pdf = await pdfjsLib.getDocument({ data: buf }).promise;
                const page = await pdf.getPage(1);
                const vp = page.getViewport({ scale: 2.0 });
                const canvas = document.createElement('canvas');
                canvas.width = vp.width; canvas.height = vp.height;
                await page.render({ canvasContext: canvas.getContext('2d'), viewport: vp }).promise;
                pdfImages.value[att.id] = canvas.toDataURL('image/jpeg', 0.92);
            } catch { /* 描画失敗時はアイコン表示 */ }
        }
    }
});
</script>

<template>
    <AppLayout title="お知らせ詳細">
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('announcements.index')"
                    class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300 whitespace-nowrap">
                    ← お知らせ一覧に戻る
                </Link>
                <h2 class="text-xl font-semibold text-gray-800">お知らせ詳細</h2>
            </div>
        </template>

        <div class="rounded bg-white px-4 py-6 sm:p-6 shadow">
            <div class="mx-auto max-w-2xl">
                <!-- メタ情報 -->
                <div class="mb-4 rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-600">
                    <div class="flex flex-wrap gap-x-6 gap-y-1">
                        <span><span class="font-medium">送信者:</span> {{ recipient.sender }}</span>
                        <span><span class="font-medium">宛先:</span> {{ targetLabel(recipient.target_type) }}</span>
                        <span><span class="font-medium">送信日時:</span> {{ recipient.created_at }}</span>
                        <span v-if="recipient.read_at">
                            <span class="font-medium">既読:</span> {{ recipient.read_at }}
                        </span>
                    </div>
                </div>

                <!-- タイトル -->
                <h3 class="mb-4 text-xl font-bold text-gray-900">{{ recipient.title }}</h3>

                <!-- 内容 -->
                <div class="whitespace-pre-wrap rounded border border-gray-200 bg-white p-4 text-sm text-gray-800 leading-relaxed">
                    {{ recipient.content }}
                </div>

                <!-- 添付ファイル（インライン描画） -->
                <div v-if="recipient.attachments && recipient.attachments.length > 0" class="mt-6 space-y-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">添付ファイル</p>
                    <div v-for="att in recipient.attachments" :key="att.id"
                        class="overflow-hidden rounded-lg border border-gray-200 bg-white">
                        <!-- 画像 -->
                        <img v-if="att.mime?.startsWith('image/')"
                            :src="att.url" :alt="att.original_name"
                            class="w-1/2 cursor-zoom-in object-contain hover:opacity-90"
                            @click="lightboxUrl = att.url" />
                        <!-- PDF: PDF.js 描画済み（50%幅、クリックで拡大） -->
                        <img v-else-if="att.mime === 'application/pdf' && pdfImages[att.id]"
                            :src="pdfImages[att.id]" :alt="att.original_name"
                            class="w-1/2 cursor-zoom-in object-contain hover:opacity-90"
                            @click="lightboxUrl = pdfImages[att.id]" />
                        <!-- PDF 読み込み中 / その他 -->
                        <div v-else class="flex flex-col items-center gap-2 p-6 text-gray-500">
                            <span v-if="att.mime === 'application/pdf'" class="text-xs text-gray-400">読み込み中...</span>
                            <span v-else class="text-4xl">📄</span>
                            <a :href="att.url" target="_blank" rel="noopener"
                                class="text-sm text-blue-600 hover:underline">{{ att.original_name }}</a>
                        </div>
                        <div class="border-t bg-gray-50 px-4 py-2 text-xs text-gray-500 flex items-center justify-between">
                            <span>{{ att.original_name }}</span>
                            <a :href="att.url" target="_blank" rel="noopener"
                                class="text-blue-600 hover:underline">ダウンロード</a>
                        </div>
                    </div>
                </div>
            </div>
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
