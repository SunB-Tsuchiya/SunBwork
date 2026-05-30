<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import * as pdfjsLib from 'pdfjs-dist';
pdfjsLib.GlobalWorkerOptions.workerSrc = new URL('pdfjs-dist/build/pdf.worker.mjs', import.meta.url).toString();

const props = defineProps({
    announcement: Object,
    recipients: Array,
});

const targetLabel = (type) => ({ all: '全員', employees_only: '社員のみ', individual: '個別選択' }[type] ?? type);
const employmentLabel = (type) => ({ regular: '正社員', contract: '契約社員', dispatch: '派遣', outsource: '業務委託' }[type] ?? type);

const readRate = computed(() => {
    if (!props.announcement.recipients_count) return 0;
    return Math.round(props.announcement.read_count / props.announcement.recipients_count * 100);
});

// PDF を canvas で描画して data URL に変換
const pdfImages = ref({}); // { [att.id]: dataUrl }
const lightboxUrl = ref(null);

onMounted(async () => {
    for (const att of (props.announcement.attachments ?? [])) {
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

function destroy() {
    if (!confirm('このお知らせを削除しますか？この操作は取り消せません。')) return;
    router.delete(route('clerk.announcements.destroy', { announcement: props.announcement.id }));
}
</script>

<template>
    <AppLayout title="お知らせ詳細（送信側）">
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('clerk.announcements.index')"
                    class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300 whitespace-nowrap">
                    ← 一覧に戻る
                </Link>
                <h2 class="text-xl font-semibold text-gray-800">お知らせ詳細</h2>
            </div>
        </template>

        <template #headerExtras>
            <div class="flex items-center gap-2">
                <Link :href="route('clerk.announcements.edit', { announcement: announcement.id })"
                    class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    編集
                </Link>
                <button @click="destroy"
                    class="rounded bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">
                    削除
                </button>
            </div>
        </template>

        <div class="space-y-4">
            <!-- お知らせ内容 -->
            <div class="rounded bg-white px-5 py-6 shadow">
                <div class="mb-4 rounded-lg border border-purple-100 bg-purple-50 p-5">
                    <div class="mb-1 flex flex-wrap items-center gap-3">
                        <span class="rounded bg-purple-200 px-2 py-0.5 text-xs font-medium text-purple-800">
                            {{ targetLabel(announcement.target_type) }}
                        </span>
                        <span class="text-xs text-gray-500">{{ announcement.created_at }}</span>
                    </div>
                    <h3 class="mb-3 text-lg font-bold text-gray-900">{{ announcement.title }}</h3>
                    <p class="whitespace-pre-wrap text-sm text-gray-700 leading-relaxed">{{ announcement.content }}</p>
                </div>

                <!-- 添付ファイル（インライン描画） -->
                <div v-if="announcement.attachments && announcement.attachments.length > 0" class="space-y-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">添付ファイル</p>
                    <div v-for="att in announcement.attachments" :key="att.id"
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
                        <!-- PDF 描画中 / その他 -->
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

            <!-- 既読進捗 + 受信者一覧 -->
            <div class="rounded bg-white px-5 py-6 shadow">
                <div class="mb-4 flex items-center gap-4">
                    <div class="text-sm text-gray-700">
                        既読: <span class="font-bold text-green-600">{{ announcement.read_count }}</span>
                        / {{ announcement.recipients_count }}人
                    </div>
                    <div class="flex-1 max-w-xs">
                        <div class="h-2 w-full rounded-full bg-gray-200">
                            <div class="h-2 rounded-full bg-green-500 transition-all" :style="{ width: readRate + '%' }"></div>
                        </div>
                    </div>
                    <span class="text-sm font-medium text-gray-600">{{ readRate }}%</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">名前</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">担当</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">雇用形態</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">既読状況</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">既読日時</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr v-for="r in recipients" :key="r.id" class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ r.name }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ r.assignment_name }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ employmentLabel(r.employment_type) }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="r.is_read ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'">
                                        <span class="inline-block h-1.5 w-1.5 rounded-full" :class="r.is_read ? 'bg-green-500' : 'bg-gray-400'"></span>
                                        {{ r.is_read ? '既読' : '未読' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ r.read_at ?? '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
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
