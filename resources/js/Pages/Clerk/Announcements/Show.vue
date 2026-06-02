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

const isDraft = computed(() => props.announcement.status === 'draft');
const targetLabel = (t) => ({ all: '全員', employees_only: '社員のみ', individual: '個別選択' }[t] ?? t);
const employmentLabel = (t) => ({ regular: '正社員', contract: '契約社員', dispatch: '派遣', outsource: '業務委託' }[t] ?? t);

// ── 会社フィルター ──
const companies = computed(() => {
    const map = new Map();
    (props.recipients ?? []).forEach(r => {
        if (r.company_id && !map.has(r.company_id)) map.set(r.company_id, r.company_name || '');
    });
    return [...map.entries()].map(([id, name]) => ({ id, name }));
});
const isMultiCompany = computed(() => companies.value.length > 1);
const selectedCompany = ref(null); // null = 全員

function companyCount(id) {
    return (props.recipients ?? []).filter(r => r.company_id === id).length;
}

// ── ソート ──
const sortKey = ref('');
const sortDir = ref('asc');

function toggleSort(key) {
    if (sortKey.value === key) sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    else { sortKey.value = key; sortDir.value = 'asc'; }
}

function sortIcon(key) {
    if (sortKey.value !== key) return '↕';
    return sortDir.value === 'asc' ? '↑' : '↓';
}

// ── フィルター＋ソート済みリスト ──
const displayRecipients = computed(() => {
    let list = (props.recipients ?? []).filter(r =>
        selectedCompany.value === null || r.company_id === selectedCompany.value
    );
    if (sortKey.value) {
        list = [...list].sort((a, b) => {
            const av = a[sortKey.value] ?? '';
            const bv = b[sortKey.value] ?? '';
            const cmp = av < bv ? -1 : av > bv ? 1 : 0;
            return sortDir.value === 'asc' ? cmp : -cmp;
        });
    }
    return list;
});

const filteredReadCount = computed(() => displayRecipients.value.filter(r => r.is_read).length);
const readRate = computed(() => {
    const total = displayRecipients.value.length;
    return total ? Math.round(filteredReadCount.value / total * 100) : 0;
});

// colspan計算（空行メッセージ用）
const colCount = computed(() => 5 + (isMultiCompany.value ? 1 : 0) + (!isDraft.value ? 2 : 0));

// ── PDF プレビュー ──
const pdfImages = ref({});
const lightboxUrl = ref(null);

onMounted(async () => {
    for (const att of (props.announcement.attachments ?? [])) {
        if (att.mime === 'application/pdf') {
            try {
                const res = await fetch(att.url);
                const buf = await res.arrayBuffer();
                const pdf = await pdfjsLib.getDocument({ data: buf }).promise;
                const pg = await pdf.getPage(1);
                const vp = pg.getViewport({ scale: 2.0 });
                const canvas = document.createElement('canvas');
                canvas.width = vp.width; canvas.height = vp.height;
                await pg.render({ canvasContext: canvas.getContext('2d'), viewport: vp }).promise;
                pdfImages.value[att.id] = canvas.toDataURL('image/jpeg', 0.92);
            } catch { /* ignore */ }
        }
    }
});

function sendDraft() {
    if (!confirm('このお知らせを送信しますか？')) return;
    router.post(route('clerk.announcements.send', { announcement: props.announcement.id }));
}

function destroy() {
    if (!confirm('このお知らせを削除しますか？この操作は取り消せません。')) return;
    router.delete(route('clerk.announcements.destroy', { announcement: props.announcement.id }));
}
</script>

<template>
    <AppLayout :title="isDraft ? 'お知らせ詳細（下書き）' : 'お知らせ詳細（送信側）'">
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('clerk.announcements.index')"
                    class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300 whitespace-nowrap">
                    ← 一覧に戻る
                </Link>
                <h2 class="text-xl font-semibold text-gray-800">お知らせ詳細</h2>
                <span v-if="isDraft" class="rounded-full bg-yellow-100 px-2.5 py-0.5 text-xs font-semibold text-yellow-800">下書き</span>
            </div>
        </template>

        <template #headerExtras>
            <div class="flex items-center gap-2">
                <button v-if="isDraft" @click="sendDraft"
                    class="rounded bg-purple-600 px-4 py-2 text-sm font-medium text-white hover:bg-purple-700">
                    送信する
                </button>
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
                <div class="mb-4 rounded-lg border p-5"
                    :class="isDraft ? 'border-yellow-200 bg-yellow-50' : 'border-purple-100 bg-purple-50'">
                    <div class="mb-1 flex flex-wrap items-center gap-3">
                        <span class="rounded px-2 py-0.5 text-xs font-medium"
                            :class="isDraft ? 'bg-yellow-200 text-yellow-800' : 'bg-purple-200 text-purple-800'">
                            {{ targetLabel(announcement.target_type) }}
                        </span>
                        <span class="text-xs text-gray-500">{{ announcement.created_at }}</span>
                        <span v-if="announcement.sender_name" class="text-xs text-gray-500">送信者: {{ announcement.sender_name }}</span>
                    </div>
                    <h3 class="mb-3 text-lg font-bold text-gray-900">{{ announcement.title }}</h3>
                    <p class="whitespace-pre-wrap text-sm text-gray-700 leading-relaxed">{{ announcement.content }}</p>
                </div>

                <!-- 添付ファイル -->
                <div v-if="announcement.attachments && announcement.attachments.length > 0" class="space-y-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">添付ファイル</p>
                    <div v-for="att in announcement.attachments" :key="att.id"
                        class="overflow-hidden rounded-lg border border-gray-200 bg-white">
                        <img v-if="att.mime?.startsWith('image/')" :src="att.url" :alt="att.original_name"
                            class="w-1/2 cursor-zoom-in object-contain hover:opacity-90" @click="lightboxUrl = att.url" />
                        <img v-else-if="att.mime === 'application/pdf' && pdfImages[att.id]"
                            :src="pdfImages[att.id]" :alt="att.original_name"
                            class="w-1/2 cursor-zoom-in object-contain hover:opacity-90" @click="lightboxUrl = pdfImages[att.id]" />
                        <div v-else class="flex flex-col items-center gap-2 p-6 text-gray-500">
                            <span v-if="att.mime === 'application/pdf'" class="text-xs text-gray-400">読み込み中...</span>
                            <span v-else class="text-4xl">📄</span>
                            <a :href="att.url" target="_blank" rel="noopener" class="text-sm text-blue-600 hover:underline">{{ att.original_name }}</a>
                        </div>
                        <div class="border-t bg-gray-50 px-4 py-2 text-xs text-gray-500 flex items-center justify-between">
                            <span>{{ att.original_name }}</span>
                            <a :href="att.url" target="_blank" rel="noopener" class="text-blue-600 hover:underline">ダウンロード</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 受信者一覧 -->
            <div class="rounded bg-white px-5 py-6 shadow">
                <!-- 既読ヘッダー -->
                <div v-if="!isDraft" class="mb-4 flex flex-wrap items-center gap-4">
                    <div class="text-sm text-gray-700">
                        既読:
                        <span class="font-bold text-green-600">{{ filteredReadCount }}</span>
                        / {{ displayRecipients.length }}人
                        <span v-if="selectedCompany !== null" class="ml-1 text-xs text-gray-400">（絞り込み中）</span>
                    </div>
                    <div class="flex-1 max-w-xs">
                        <div class="h-2 w-full rounded-full bg-gray-200">
                            <div class="h-2 rounded-full bg-green-500 transition-all" :style="{ width: readRate + '%' }"></div>
                        </div>
                    </div>
                    <span class="text-sm font-medium text-gray-600">{{ readRate }}%</span>
                </div>
                <div v-else class="mb-4 text-sm text-gray-600">
                    送信予定の受信者: <span class="font-bold">{{ announcement.recipients_count }}</span>人
                </div>

                <!-- 会社フィルターボタン（複数会社のみ表示） -->
                <div v-if="isMultiCompany" class="mb-4 flex flex-wrap gap-2">
                    <button
                        @click="selectedCompany = null"
                        class="rounded-full px-4 py-1.5 text-sm font-medium transition-colors"
                        :class="selectedCompany === null
                            ? 'bg-gray-700 text-white'
                            : 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50'">
                        全員 ({{ (recipients ?? []).length }})
                    </button>
                    <button v-for="co in companies" :key="co.id"
                        @click="selectedCompany = co.id"
                        class="rounded-full px-4 py-1.5 text-sm font-medium transition-colors"
                        :class="selectedCompany === co.id
                            ? 'bg-blue-600 text-white'
                            : 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50'">
                        {{ co.name }} ({{ companyCount(co.id) }})
                    </button>
                </div>

                <!-- テーブル -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th @click="toggleSort('name')"
                                    class="cursor-pointer select-none px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 whitespace-nowrap hover:bg-gray-100">
                                    名前 <span class="ml-1 text-gray-400">{{ sortIcon('name') }}</span>
                                </th>
                                <th v-if="isMultiCompany" @click="toggleSort('company_name')"
                                    class="cursor-pointer select-none px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 whitespace-nowrap hover:bg-gray-100">
                                    会社 <span class="ml-1 text-gray-400">{{ sortIcon('company_name') }}</span>
                                </th>
                                <th @click="toggleSort('department_name')"
                                    class="cursor-pointer select-none px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 whitespace-nowrap hover:bg-gray-100">
                                    部署 <span class="ml-1 text-gray-400">{{ sortIcon('department_name') }}</span>
                                </th>
                                <th @click="toggleSort('assignment_name')"
                                    class="cursor-pointer select-none px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 whitespace-nowrap hover:bg-gray-100">
                                    担当 <span class="ml-1 text-gray-400">{{ sortIcon('assignment_name') }}</span>
                                </th>
                                <th @click="toggleSort('employment_type')"
                                    class="cursor-pointer select-none px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 whitespace-nowrap hover:bg-gray-100">
                                    雇用形態 <span class="ml-1 text-gray-400">{{ sortIcon('employment_type') }}</span>
                                </th>
                                <th v-if="!isDraft" @click="toggleSort('is_read')"
                                    class="cursor-pointer select-none px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 whitespace-nowrap hover:bg-gray-100">
                                    既読状況 <span class="ml-1 text-gray-400">{{ sortIcon('is_read') }}</span>
                                </th>
                                <th v-if="!isDraft" @click="toggleSort('read_at')"
                                    class="cursor-pointer select-none px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 whitespace-nowrap hover:bg-gray-100">
                                    既読日時 <span class="ml-1 text-gray-400">{{ sortIcon('read_at') }}</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr v-if="displayRecipients.length === 0">
                                <td :colspan="colCount" class="px-4 py-8 text-center text-sm text-gray-400">
                                    該当する受信者はいません
                                </td>
                            </tr>
                            <tr v-for="r in displayRecipients" :key="r.id" class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ r.name }}</td>
                                <td v-if="isMultiCompany" class="px-4 py-3 text-sm text-gray-600">{{ r.company_name }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ r.department_name }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ r.assignment_name }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ employmentLabel(r.employment_type) }}</td>
                                <td v-if="!isDraft" class="px-4 py-3">
                                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="r.is_read ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'">
                                        <span class="inline-block h-1.5 w-1.5 rounded-full"
                                            :class="r.is_read ? 'bg-green-500' : 'bg-gray-400'"></span>
                                        {{ r.is_read ? '既読' : '未読' }}
                                    </span>
                                </td>
                                <td v-if="!isDraft" class="px-4 py-3 text-sm text-gray-500">{{ r.read_at ?? '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
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
