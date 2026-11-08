<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import axios from 'axios';
import Sortable from 'sortablejs';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import useToasts from '@/Composables/useToasts';
import ItemCreateModal from '@/Components/Portfolio/ItemCreateModal.vue';
import ItemEditModal from '@/Components/Portfolio/ItemEditModal.vue';

// ポートフォリオ管理画面（AppLayout・ヘッダーあり）。
// 2 つの使い方:
//   ・user エリア（readOnly=false）: 作る人（スイッチ ON の人）が、自分の作品を追加・整理・削除する編集画面。
//   ・admin エリア（readOnly=true）: 「メンバーのポートフォリオ」一覧（Overview.vue）から開く、1 人分のページ。閲覧するだけ。
// お客様に見せる閲覧ページは別タブの独立レイアウト（Phase 5）。
const props = defineProps({
    initial: { type: Object, required: true },
    routePrefix: { type: String, required: true },
    readOnly: { type: Boolean, default: false },
    // 自分のページを閲覧専用で開いたとき、自分の編集画面（user エリア）へ案内する
    canEditOwn: { type: Boolean, default: false },
    // このページのポートフォリオの持ち主（user エリアでは自分）
    ownerId: { type: Number, default: null },
    ownerName: { type: String, default: '' },
});

// useToasts が返すのは showToast（addToast は存在しない）
const { showToast } = useToasts();
const addToast = showToast;

const data = reactive({
    profile: { ...props.initial.profile },
    palette: props.initial.palette,
    collections: [...props.initial.collections],
    items: [...props.initial.items],
    tags: [...props.initial.tags],
    usage: { ...props.initial.usage },
});

// ---- 持ち主（閲覧専用モードでは、読み込みだけに owner_id を付ける。書き込みは常に本人分で、本人以外はサーバーが 403）----
const currentOwnerId = ref(props.ownerId);
const readParams = () => (props.readOnly && currentOwnerId.value ? { owner_id: currentOwnerId.value } : {});

// ---- 表示スコープ（すべて / 未分類 / コレクション）とタグ絞り込み ----
const scope = ref('all'); // 'all' | 'none' | コレクション ID
const tagFilter = ref('');
const editing = ref(null);
const creating = ref(false);
const saving = ref(false);

const visibleItems = computed(() =>
    data.items.filter((i) => {
        if (scope.value === 'none' && i.collection_id) return false;
        if (typeof scope.value === 'number' && i.collection_id !== scope.value) return false;
        if (tagFilter.value && !i.tags.includes(tagFilter.value)) return false;
        return true;
    }),
);
const currentCollection = computed(() =>
    typeof scope.value === 'number' ? data.collections.find((c) => c.id === scope.value) : null,
);
const countFor = (id) => data.items.filter((i) => (id === 'none' ? !i.collection_id : i.collection_id === id)).length;
const canReorder = computed(() => !props.readOnly && !tagFilter.value);

const fileUrl = (item, kind) => route('portfolio.api.files', { item: item.id, kind });
const thumbOf = (item) => (item.has_thumb ? fileUrl(item, 'thumb') : null);

// PDF のページ画像 URL（完成した PDF のみ）
const pageUrlsOf = (item) =>
    item?.type === 'pdf' && item.status === 'ready' && item.page_count
        ? Array.from({ length: item.page_count }, (_, i) => route('portfolio.api.pages', { item: item.id, page: i + 1 }))
        : [];
const formatDuration = (sec) => `${Math.floor(sec / 60)}:${String(sec % 60).padStart(2, '0')}`;
const STATUS_LABEL = { uploading: 'アップ中', processing: '変換中', failed: '失敗' };

const TYPE_LABEL = { image: '画像', pdf: 'PDF', video: '動画' };

function formatBytes(b) {
    if (b >= 1024 ** 3) return (b / 1024 ** 3).toFixed(1) + ' GB';
    if (b >= 1024 ** 2) return (b / 1024 ** 2).toFixed(1) + ' MB';
    return Math.max(1, Math.round(b / 1024)) + ' KB';
}
const usagePercent = computed(() => Math.min(100, (data.usage.used_bytes / data.usage.quota_bytes) * 100));

// ---- データ再取得 ----
async function reload() {
    const { data: fresh } = await axios.get(route('portfolio.api.data'), { params: readParams() });
    Object.assign(data, {
        profile: fresh.profile,
        collections: fresh.collections,
        items: fresh.items,
        tags: fresh.tags,
        usage: fresh.usage,
    });
    // 消えたコレクション・タグを選択していたら解除
    if (typeof scope.value === 'number' && !data.collections.some((c) => c.id === scope.value)) scope.value = 'all';
    if (tagFilter.value && !data.tags.includes(tagFilter.value)) tagFilter.value = '';
}

function errorText(e, fallback = '処理に失敗しました') {
    const d = e?.response?.data;
    return d?.errors ? Object.values(d.errors).flat()[0] : (d?.message || fallback);
}

async function run(fn, okMessage = null) {
    try {
        await fn();
        if (okMessage) addToast(okMessage, 'success');
        return true;
    } catch (e) {
        addToast(errorText(e), 'error');
        return false;
    }
}

// ---- コレクション（本人のみ）----
async function addCollection() {
    const name = window.prompt('コレクション名（例: カタログ、ポスター）');
    if (!name?.trim()) return;
    await run(async () => {
        const { data: res } = await axios.post(route('portfolio.api.collections.store'), { name: name.trim() });
        await reload();
        scope.value = res.id;
    }, 'コレクションを作成しました');
}
async function renameCollection(c) {
    const name = window.prompt('コレクション名', c.name);
    if (!name?.trim() || name.trim() === c.name) return;
    await run(async () => {
        await axios.put(route('portfolio.api.collections.update', { collection: c.id }), {
            name: name.trim(), description: c.description, cover_item_id: c.cover_item_id,
        });
        await reload();
    });
}
async function deleteCollection(c) {
    if (!window.confirm(`「${c.name}」を削除します。\n中の作品は消えず「未分類」に移ります。よろしいですか？`)) return;
    await run(async () => {
        await axios.delete(route('portfolio.api.collections.destroy', { collection: c.id }));
        await reload();
    }, 'コレクションを削除しました');
}

// ---- 作品 ----
async function onCreated() {
    creating.value = false;
    await run(reload, '作品を追加しました');
}
async function saveItem(payload) {
    saving.value = true;
    const ok = await run(async () => {
        await axios.put(route('portfolio.api.items.update', { item: editing.value.id }), payload);
        await reload();
    }, '保存しました');
    saving.value = false;
    if (ok) editing.value = null;
}
async function deleteItem() {
    const item = editing.value;
    if (!window.confirm(`「${item.title}」を削除します。ファイルも完全に削除され、元に戻せません。よろしいですか？`)) return;
    const ok = await run(async () => {
        await axios.delete(route('portfolio.api.items.destroy', { item: item.id }));
        await reload();
    }, '削除しました');
    if (ok) editing.value = null;
}
async function setCover() {
    const item = editing.value;
    const c = data.collections.find((x) => x.id === item.collection_id);
    if (!c) return;
    await run(async () => {
        await axios.put(route('portfolio.api.collections.update', { collection: c.id }), {
            name: c.name, description: c.description, cover_item_id: item.id,
        });
        await reload();
    }, '表紙に設定しました');
}
const isCover = (item) => data.collections.some((c) => c.cover_item_id === item.id);

// ---- 設定（アクセント色・表示名・肩書き。本人のみ）----
const showProfile = ref(false);
const profileSaving = ref(false);
async function saveProfile() {
    profileSaving.value = true;
    await run(async () => {
        await axios.put(route('portfolio.api.profile'), {
            accent_color: data.profile.accent_color,
            display_name: data.profile.display_name || null,
            headline: data.profile.headline || null,
        });
    }, '設定を保存しました');
    profileSaving.value = false;
}

// ---- ドラッグ並べ替え（本人のみ。SortableJS。DOM は Vue が管理するため、移動を戻してからデータを並べ替える）----
const gridEl = ref(null);
const sideEl = ref(null);
let gridSortable = null;
let sideSortable = null;

function revertDom(evt) {
    evt.item.remove();
    evt.from.insertBefore(evt.item, evt.from.children[evt.oldIndex] ?? null);
}

function setupSortables() {
    gridSortable?.destroy();
    sideSortable?.destroy();
    gridSortable = sideSortable = null;
    if (props.readOnly) return;

    if (gridEl.value && canReorder.value) {
        gridSortable = Sortable.create(gridEl.value, {
            animation: 150,
            draggable: '[data-item-id]',
            ghostClass: 'opacity-40',
            onEnd: async (evt) => {
                revertDom(evt);
                if (evt.oldIndex === evt.newIndex) return;
                const visible = [...visibleItems.value];
                const [moved] = visible.splice(evt.oldIndex, 1);
                visible.splice(evt.newIndex, 0, moved);
                // 表示中の作品が占めていた位置に、新しい順で並べ直す
                const visibleIds = new Set(visible.map((i) => i.id));
                let k = 0;
                data.items = data.items.map((i) => (visibleIds.has(i.id) ? visible[k++] : i));
                await run(() => axios.post(route('portfolio.api.items.reorder'), { ids: visible.map((i) => i.id) }));
            },
        });
    }
    if (sideEl.value) {
        sideSortable = Sortable.create(sideEl.value, {
            animation: 150,
            draggable: '[data-collection-id]',
            handle: '.drag-handle',
            onEnd: async (evt) => {
                revertDom(evt);
                if (evt.oldIndex === evt.newIndex) return;
                const list = [...data.collections];
                const [moved] = list.splice(evt.oldIndex, 1);
                list.splice(evt.newIndex, 0, moved);
                data.collections = list;
                await run(() => axios.post(route('portfolio.api.collections.reorder'), { ids: list.map((c) => c.id) }));
            },
        });
    }
}

onMounted(() => nextTick(setupSortables));
watch([canReorder, () => data.collections.length, () => visibleItems.value.length], () => nextTick(setupSortables));
onBeforeUnmount(() => {
    gridSortable?.destroy();
    sideSortable?.destroy();
});
</script>

<template>
    <AppLayout :title="readOnly ? 'ポートフォリオ（閲覧）' : 'ポートフォリオ管理'">
        <template #header>
            <div class="flex items-center gap-3">
                <!-- 閲覧専用（1 人分のページ）: 一覧（タブ1）へ戻る -->
                <Link v-if="readOnly" :href="route(`${routePrefix}.portfolio.manage`)"
                    class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300 whitespace-nowrap">← メンバー一覧へ</Link>
                <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">
                    {{ readOnly ? `${ownerName} さんのポートフォリオ` : 'ポートフォリオ管理' }}
                </h2>
            </div>
        </template>

        <div class="rounded bg-white p-4 shadow sm:p-6">
            <!-- 閲覧専用（1 人分のページ）: 説明。自分のページなら、自分の編集画面への入口も出す -->
            <div v-if="readOnly" class="mb-4 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm">
                <p class="text-xs text-amber-800">閲覧のみです。作品の追加・修正・削除はご本人が行います。問題のある作品があれば、本人に修正・削除を依頼してください。</p>
                <Link v-if="canEditOwn" :href="route('user.portfolio.manage')" class="inline-flex shrink-0 items-center rounded-md bg-red-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-red-700">編集する →</Link>
            </div>

            <!-- 上部バー -->
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div class="min-w-[12rem] text-xs text-gray-500">
                    <div class="mb-1">使用量 {{ formatBytes(data.usage.used_bytes) }} / {{ formatBytes(data.usage.quota_bytes) }}</div>
                    <div class="h-1.5 w-48 overflow-hidden rounded-full bg-gray-200">
                        <div class="h-full rounded-full" :style="{ width: usagePercent + '%', backgroundColor: data.profile.accent_color }" />
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-if="!readOnly"
                        type="button"
                        class="rounded-md px-4 py-1.5 text-sm font-medium text-white shadow-sm hover:opacity-90"
                        :style="{ backgroundColor: data.profile.accent_color }"
                        @click="creating = true"
                    >＋ 作品を追加</button>
                    <button v-if="!readOnly" type="button" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50" @click="showProfile = !showProfile">
                        表示設定（色・名前）
                    </button>
                    <!-- お客様に見せる閲覧ページ（別タブ・ヘッダーなしの全画面）。閲覧専用モードでは見ているメンバーの分を開く -->
                    <a
                        :href="route('portfolio.view', readOnly && currentOwnerId ? { owner_id: currentOwnerId } : {})"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center rounded-md bg-gray-900 px-3 py-1.5 text-sm text-white hover:bg-gray-700"
                    >客先で見せる ↗</a>
                </div>
            </div>

            <!-- 表示設定（本人のみ） -->
            <div v-if="!readOnly && showProfile" class="mb-5 rounded-lg border border-gray-200 bg-gray-50 p-4">
                <div class="mb-3 text-xs text-gray-600">アクセント色（閲覧ページのワンポイントカラー）</div>
                <div class="mb-4 flex flex-wrap gap-2">
                    <button
                        v-for="color in data.palette"
                        :key="color"
                        type="button"
                        class="h-8 w-8 rounded-full border-2 transition-transform hover:scale-110"
                        :class="data.profile.accent_color === color ? 'border-gray-900' : 'border-white shadow'"
                        :style="{ backgroundColor: color }"
                        :aria-label="color"
                        @click="data.profile.accent_color = color"
                    />
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="text-xs text-gray-600">
                        表示名（空欄なら「{{ data.profile.default_name }}」）
                        <input v-model="data.profile.display_name" type="text" maxlength="100" class="mt-1 block w-full rounded border-gray-300 text-sm" />
                    </label>
                    <label class="text-xs text-gray-600">
                        肩書き・ひとこと
                        <input v-model="data.profile.headline" type="text" maxlength="150" class="mt-1 block w-full rounded border-gray-300 text-sm" placeholder="例: 印刷物のデザイナー" />
                    </label>
                </div>
                <div class="mt-3 text-right">
                    <button type="button" :disabled="profileSaving" class="rounded bg-gray-900 px-4 py-1.5 text-sm text-white hover:bg-gray-700 disabled:opacity-40" @click="saveProfile">保存</button>
                </div>
            </div>

            <div class="flex flex-col gap-5 md:flex-row">
                <!-- 左: コレクション -->
                <aside class="w-full shrink-0 md:w-56">
                    <div class="mb-2 flex items-center justify-between">
                        <span class="text-xs font-semibold tracking-wide text-gray-500">コレクション</span>
                        <button v-if="!readOnly" type="button" class="text-xs hover:underline" :style="{ color: data.profile.accent_color }" @click="addCollection">＋ 追加</button>
                    </div>
                    <button
                        type="button"
                        class="mb-1 flex w-full items-center justify-between rounded-md px-3 py-1.5 text-left text-sm"
                        :class="scope === 'all' ? 'bg-gray-900 text-white' : 'text-gray-700 hover:bg-gray-100'"
                        @click="scope = 'all'"
                    >
                        <span>すべて</span><span class="text-xs opacity-70">{{ data.items.length }}</span>
                    </button>
                    <div ref="sideEl">
                        <div
                            v-for="c in data.collections"
                            :key="c.id"
                            :data-collection-id="c.id"
                            class="group mb-1 flex items-center rounded-md text-sm"
                            :class="scope === c.id ? 'bg-gray-900 text-white' : 'text-gray-700 hover:bg-gray-100'"
                        >
                            <span v-if="!readOnly" class="drag-handle cursor-grab px-1.5 py-1.5 text-xs opacity-40 hover:opacity-100" title="ドラッグで並べ替え">⋮⋮</span>
                            <button type="button" class="flex min-w-0 flex-1 items-center justify-between py-1.5 pr-2 text-left" :class="readOnly ? 'pl-3' : ''" @click="scope = c.id">
                                <span class="overflow-hidden text-ellipsis whitespace-nowrap">{{ c.name }}</span>
                                <span class="ml-1 text-xs opacity-70">{{ countFor(c.id) }}</span>
                            </button>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="mt-1 flex w-full items-center justify-between rounded-md px-3 py-1.5 text-left text-sm"
                        :class="scope === 'none' ? 'bg-gray-900 text-white' : 'text-gray-700 hover:bg-gray-100'"
                        @click="scope = 'none'"
                    >
                        <span>未分類</span><span class="text-xs opacity-70">{{ countFor('none') }}</span>
                    </button>

                    <div v-if="!readOnly && currentCollection" class="mt-3 flex gap-3 border-t border-gray-200 pt-2 text-xs">
                        <button type="button" class="text-gray-600 hover:underline" @click="renameCollection(currentCollection)">名前を変更</button>
                        <button type="button" class="text-red-600 hover:underline" @click="deleteCollection(currentCollection)">削除</button>
                    </div>
                </aside>

                <!-- 右: 作品 -->
                <section class="min-w-0 flex-1">
                    <!-- タグ絞り込み -->
                    <div v-if="data.tags.length" class="flex flex-wrap items-center gap-1.5 text-xs">
                        <span class="text-gray-500">タグ:</span>
                        <button
                            type="button"
                            class="rounded-full border px-2.5 py-0.5"
                            :class="!tagFilter ? 'border-gray-900 bg-gray-900 text-white' : 'border-gray-300 text-gray-600 hover:bg-gray-50'"
                            @click="tagFilter = ''"
                        >すべて</button>
                        <button
                            v-for="t in data.tags"
                            :key="t"
                            type="button"
                            class="rounded-full border px-2.5 py-0.5"
                            :class="tagFilter === t ? 'border-gray-900 bg-gray-900 text-white' : 'border-gray-300 text-gray-600 hover:bg-gray-50'"
                            @click="tagFilter = tagFilter === t ? '' : t"
                        >{{ t }}</button>
                    </div>

                    <p v-if="canReorder && visibleItems.length > 1" class="mt-3 text-xs text-gray-400">ドラッグで並べ替えできます</p>
                    <p v-else-if="!readOnly && tagFilter" class="mt-3 text-xs text-gray-400">タグで絞り込み中は並べ替えできません</p>

                    <!-- 空の状態: 次にやることを示す -->
                    <div v-if="!visibleItems.length" class="py-16 text-center">
                        <p v-if="readOnly" class="text-sm text-gray-400">表示できる作品がありません。</p>
                        <template v-else>
                            <p class="mb-3 text-sm text-gray-500">
                                {{ data.items.length ? 'この条件に合う作品がありません。' : 'まだ作品がありません。最初の作品を追加しましょう。' }}
                            </p>
                            <button
                                type="button"
                                class="rounded-md px-5 py-2 text-sm font-medium text-white shadow-sm hover:opacity-90"
                                :style="{ backgroundColor: data.profile.accent_color }"
                                @click="creating = true"
                            >＋ 作品を追加</button>
                        </template>
                    </div>

                    <div ref="gridEl" class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                        <button
                            v-for="item in visibleItems"
                            :key="item.id"
                            type="button"
                            :data-item-id="item.id"
                            class="group relative overflow-hidden rounded-lg border border-gray-200 bg-white text-left transition-shadow hover:shadow-md"
                            :class="canReorder ? 'cursor-grab' : ''"
                            @click="editing = item"
                        >
                            <div class="aspect-[4/3] bg-gray-100">
                                <img v-if="thumbOf(item)" :src="thumbOf(item)" :alt="item.title" class="h-full w-full object-cover" loading="lazy" draggable="false" />
                            </div>
                            <span class="absolute left-1.5 top-1.5 rounded bg-white/90 px-1.5 py-0.5 text-[10px] font-medium text-gray-700">{{ TYPE_LABEL[item.type] }}<template v-if="item.type === 'video' && item.duration_sec != null"> ▶ {{ formatDuration(item.duration_sec) }}</template></span>
                            <span
                                v-if="item.status !== 'ready'"
                                class="absolute inset-x-0 bottom-[3.2rem] bg-amber-500/90 py-0.5 text-center text-[11px] font-medium text-white"
                            >{{ STATUS_LABEL[item.status] ?? item.status }}</span>
                            <span
                                v-if="isCover(item)"
                                class="absolute right-1.5 top-1.5 rounded px-1.5 py-0.5 text-[10px] font-medium text-white"
                                :style="{ backgroundColor: data.profile.accent_color }"
                            >表紙</span>
                            <div class="px-2.5 py-2">
                                <div class="overflow-hidden text-ellipsis whitespace-nowrap text-sm font-medium text-gray-900">{{ item.title }}</div>
                                <div v-if="item.tags.length" class="mt-0.5 overflow-hidden text-ellipsis whitespace-nowrap text-[11px] text-gray-400">{{ item.tags.map((t) => '#' + t).join(' ') }}</div>
                            </div>
                        </button>
                    </div>
                </section>
            </div>
        </div>

        <ItemCreateModal
            v-if="creating && !readOnly"
            :collections="data.collections"
            :all-tags="data.tags"
            :default-collection-id="typeof scope === 'number' ? scope : null"
            :accent="data.profile.accent_color"
            @created="onCreated"
            @close="creating = false"
        />

        <ItemEditModal
            v-if="editing"
            :key="editing.id"
            :item="editing"
            :collections="data.collections"
            :all-tags="data.tags"
            :is-cover="isCover(editing)"
            :thumb-url="thumbOf(editing)"
            :file-url="editing.has_file ? fileUrl(editing, 'file') : null"
            :page-urls="pageUrlsOf(editing)"
            :poster-url="editing.has_poster ? fileUrl(editing, 'poster') : null"
            :saving="saving"
            :read-only="readOnly"
            @save="saveItem"
            @close="editing = null"
            @delete="deleteItem"
            @set-cover="setCover"
        />
    </AppLayout>
</template>
