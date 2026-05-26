<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    pairs: { type: Array, default: () => [] },
});

const page = usePage();
const routePrefix = computed(() => {
    try {
        const r = route().current() ?? '';
        if (r.startsWith('admin.')) return 'admin';
        if (r.startsWith('leader.')) return 'leader';
        if (r.startsWith('coordinator.')) return 'coordinator';
    } catch {}
    const role = page.props.auth?.user?.user_role ?? 'leader';
    if (['admin', 'superadmin'].includes(role)) return 'admin';
    if (['coordinator', 'clerk'].includes(role)) return 'coordinator';
    return 'leader';
});

const REASON_LABELS = {
    same_code:               { label: 'コード重複',   color: 'bg-red-100 text-red-700' },
    code_missing_name_match: { label: 'コード欠損',   color: 'bg-orange-100 text-orange-700' },
    fuzzy_name:              { label: '名前類似',     color: 'bg-yellow-100 text-yellow-700' },
};

// 各ペアの「残す」選択（デフォルト: 案件数が多い方、同数なら created_at が早い方）
function defaultKeep(pair) {
    const a = pair.client_a;
    const b = pair.client_b;
    if (a.project_jobs_count !== b.project_jobs_count) {
        return a.project_jobs_count > b.project_jobs_count ? 'a' : 'b';
    }
    if (a.created_at && b.created_at) {
        return a.created_at <= b.created_at ? 'a' : 'b';
    }
    return 'a';
}

// ペアごとの選択状態
const selections = ref(
    props.pairs.map(pair => ({
        checked: true,
        keep: defaultKeep(pair),
    }))
);

const checkedCount = computed(() => selections.value.filter(s => s.checked).length);

function selectAll()   { selections.value.forEach(s => { s.checked = true; }); }
function deselectAll() { selections.value.forEach(s => { s.checked = false; }); }

const guideOpen = ref(true);

const processing = ref(false);

function doMerge() {
    const merges = [];
    props.pairs.forEach((pair, i) => {
        const sel = selections.value[i];
        if (!sel.checked) return;
        const keepClient  = sel.keep === 'a' ? pair.client_a : pair.client_b;
        const dropClient  = sel.keep === 'a' ? pair.client_b : pair.client_a;
        merges.push({ source_id: dropClient.id, target_id: keepClient.id });
    });
    if (merges.length === 0) return;

    processing.value = true;
    router.post(
        route(`${routePrefix.value}.clients.batch_merge`),
        { merges },
        { onFinish: () => { processing.value = false; } },
    );
}

// ========== 任意統合 ==========
const manualOpen     = ref(true);
const manualQuery    = ref('');
const manualResults  = ref([]);
const manualSelected = ref([]);
const manualKeepId   = ref(null);
const manualSearching = ref(false);
const manualMerging   = ref(false);
const showDropdown    = ref(false);

let debounceTimer = null;

function onManualInput() {
    clearTimeout(debounceTimer);
    const q = manualQuery.value.trim();
    if (!q) {
        manualResults.value = [];
        showDropdown.value = false;
        return;
    }
    debounceTimer = setTimeout(() => fetchManualResults(q), 250);
}

async function fetchManualResults(q) {
    manualSearching.value = true;
    try {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
        const url = route(`${routePrefix.value}.clients.json`)
            + '?name=' + encodeURIComponent(q) + '&limit=10&include_dormant=1';
        const res = await fetch(url, {
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (!res.ok) throw new Error();
        const data = await res.json();
        manualResults.value = Array.isArray(data)
            ? data.filter(c => !manualSelected.value.some(s => s.id === c.id))
            : [];
        showDropdown.value = manualResults.value.length > 0;
    } catch {
        manualResults.value = [];
    } finally {
        manualSearching.value = false;
    }
}

function pickClient(client) {
    if (manualSelected.value.some(c => c.id === client.id)) return;
    manualSelected.value.push(client);
    if (!manualKeepId.value) manualKeepId.value = client.id;
    manualQuery.value = '';
    manualResults.value = [];
    showDropdown.value = false;
}

function removeManualClient(id) {
    manualSelected.value = manualSelected.value.filter(c => c.id !== id);
    if (manualKeepId.value === id) {
        manualKeepId.value = manualSelected.value[0]?.id ?? null;
    }
}

function hideDropdown() {
    setTimeout(() => { showDropdown.value = false; }, 150);
}

const manualCanMerge = computed(() => manualSelected.value.length >= 2 && manualKeepId.value !== null);

const manualKeepClient = computed(() => manualSelected.value.find(c => c.id === manualKeepId.value));

function doManualMerge() {
    if (!manualCanMerge.value || manualMerging.value) return;
    const keepName = manualKeepClient.value?.name ?? '';
    const dropNames = manualSelected.value
        .filter(c => c.id !== manualKeepId.value)
        .map(c => c.name).join('、');
    if (!confirm(
        `以下のクライアントを「${keepName}」に統合します。\n\n` +
        `削除されるクライアント: ${dropNames}\n\n` +
        `この操作は取り消せません。よろしいですか？`
    )) return;

    const merges = manualSelected.value
        .filter(c => c.id !== manualKeepId.value)
        .map(c => ({ source_id: c.id, target_id: manualKeepId.value }));

    manualMerging.value = true;
    router.post(
        route(`${routePrefix.value}.clients.batch_merge`),
        { merges },
        { onFinish: () => { manualMerging.value = false; } },
    );
}
</script>

<template>
    <AppLayout title="クライアント重複チェック">
        <template #header>
            <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">
                クライアント重複チェック
            </h2>
        </template>
        <template #headerExtras>
            <Link
                :href="route(`${routePrefix}.clients.index`)"
                class="rounded bg-gray-500 px-4 py-2 text-sm font-medium text-white hover:bg-gray-600"
            >← 一覧に戻る</Link>
        </template>

        <!-- 使い方ガイド -->
        <div class="mb-4 rounded border border-blue-200 bg-blue-50 shadow-sm overflow-hidden">
            <button
                type="button"
                class="flex w-full items-center justify-between px-4 py-3 text-left text-sm font-semibold text-blue-800 hover:bg-blue-100 transition-colors"
                @click="guideOpen = !guideOpen"
            >
                <span>使い方ガイド</span>
                <span class="text-blue-500 text-xs">{{ guideOpen ? '▲ 閉じる' : '▼ 開く' }}</span>
            </button>
            <div v-if="guideOpen" class="border-t border-blue-200 px-4 py-3 text-sm text-blue-900 space-y-2">
                <p>このページでは、名前や伝票番号が似ているクライアントの<strong>疑わしい重複ペア</strong>を確認・統合できます。また、任意のクライアントを選んで統合することも可能です。</p>
                <ol class="list-decimal list-inside space-y-1 text-blue-800">
                    <li>各ペアの左側チェックボックスで「統合するペア」を選択してください（デフォルト: 全て選択）。</li>
                    <li>ラジオボタンで<strong>「残すクライアント」</strong>を選択してください（デフォルト: 案件数が多い方）。</li>
                    <li>選択した内容を確認し、「選択した〇件を統合」ボタンを押してください。</li>
                </ol>
                <div class="mt-2 rounded bg-blue-100 px-3 py-2 text-xs text-blue-700 space-y-1">
                    <p><strong>統合の動作:</strong> 「残す」側に選ばれなかった方の案件がすべて移動され、そのクライアントは削除されます。</p>
                    <p><strong>重複の種類:</strong>
                        <span class="ml-1 inline-flex rounded-full bg-red-100 px-2 py-0.5 text-xs text-red-700">コード重複</span> 同じ伝票番号を持つ /
                        <span class="ml-1 inline-flex rounded-full bg-orange-100 px-2 py-0.5 text-xs text-orange-700">コード欠損</span> 片方にコードなし・同名 /
                        <span class="ml-1 inline-flex rounded-full bg-yellow-100 px-2 py-0.5 text-xs text-yellow-700">名前類似</span> 空白・カタカナ・全角半角の違いのみ
                    </p>
                    <p class="font-semibold text-blue-800">必ず内容を確認してから統合してください。この操作は取り消せません。</p>
                </div>
            </div>
        </div>

        <!-- ========== 任意統合セクション ========== -->
        <div class="mb-4 rounded border border-indigo-200 bg-indigo-50 shadow-sm overflow-hidden">
            <button
                type="button"
                class="flex w-full items-center justify-between px-4 py-3 text-left text-sm font-semibold text-indigo-800 hover:bg-indigo-100 transition-colors"
                @click="manualOpen = !manualOpen"
            >
                <span>任意のクライアントを選んで統合</span>
                <span class="text-indigo-500 text-xs">{{ manualOpen ? '▲ 閉じる' : '▼ 開く' }}</span>
            </button>

            <div v-if="manualOpen" class="border-t border-indigo-200 px-4 py-4 space-y-4">

                <!-- 検索ボックス -->
                <div class="relative">
                    <label class="mb-1 block text-xs font-medium text-indigo-700">クライアントを検索して追加</label>
                    <div class="flex items-center gap-2">
                        <div class="relative flex-1">
                            <input
                                v-model="manualQuery"
                                type="text"
                                placeholder="クライアント名で検索…"
                                class="w-full rounded border border-indigo-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300 bg-white"
                                @input="onManualInput"
                                @focus="onManualInput"
                                @blur="hideDropdown"
                            />
                            <!-- スピナー -->
                            <span v-if="manualSearching" class="absolute right-3 top-1/2 -translate-y-1/2">
                                <svg class="h-4 w-4 animate-spin text-indigo-400" viewBox="0 0 24 24" fill="none">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                                </svg>
                            </span>
                        </div>
                    </div>

                    <!-- 検索結果ドロップダウン -->
                    <div
                        v-if="showDropdown && manualResults.length > 0"
                        class="absolute z-20 mt-1 w-full rounded border border-gray-200 bg-white shadow-lg"
                    >
                        <button
                            v-for="client in manualResults"
                            :key="client.id"
                            type="button"
                            class="flex w-full items-center justify-between px-4 py-2.5 text-left text-sm hover:bg-indigo-50 transition-colors"
                            @mousedown.prevent="pickClient(client)"
                        >
                            <span class="font-medium text-gray-900">{{ client.name }}</span>
                            <span class="ml-4 flex items-center gap-3 text-xs text-gray-400 shrink-0">
                                <span v-if="client.client_code" class="font-mono">{{ client.client_code }}</span>
                                <span>案件{{ client.project_jobs_count ?? 0 }}件</span>
                                <span v-if="client.is_dormant" class="rounded-full bg-gray-200 px-1.5 py-0.5 text-gray-500">休眠</span>
                            </span>
                        </button>
                    </div>
                </div>

                <!-- 選択済みチップ -->
                <div v-if="manualSelected.length > 0" class="flex flex-wrap gap-2">
                    <span
                        v-for="client in manualSelected"
                        :key="client.id"
                        class="inline-flex items-center gap-1.5 rounded-full border border-indigo-300 bg-white px-3 py-1 text-sm text-indigo-800"
                    >
                        {{ client.name }}
                        <button
                            type="button"
                            class="flex h-4 w-4 items-center justify-center rounded-full text-indigo-400 hover:bg-indigo-200 hover:text-indigo-700 transition-colors"
                            @click="removeManualClient(client.id)"
                        >×</button>
                    </span>
                </div>

                <!-- 未選択時のヒント -->
                <p v-else class="text-xs text-indigo-500">上の検索ボックスでクライアントを検索し、2件以上追加してください。</p>

                <!-- 統合設定カード（2件以上選択時） -->
                <div v-if="manualSelected.length >= 2" class="rounded bg-white shadow overflow-hidden">
                    <!-- カードヘッダー -->
                    <div class="flex items-center gap-3 bg-gray-50 px-4 py-2.5 border-b border-gray-200">
                        <span class="rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-semibold text-indigo-700">任意統合</span>
                        <span class="text-xs text-gray-500">「残す」を選択したクライアントに、他の案件をすべて移して統合します</span>
                    </div>

                    <!-- クライアント行 -->
                    <div class="divide-y divide-gray-100">
                        <div
                            v-for="client in manualSelected"
                            :key="client.id"
                            class="flex items-center gap-4 px-5 py-3 cursor-pointer transition-colors"
                            :class="manualKeepId === client.id ? 'bg-green-50' : 'hover:bg-gray-50'"
                            @click="manualKeepId = client.id"
                        >
                            <!-- ラジオ + 残すラベル -->
                            <label class="flex items-center gap-2 cursor-pointer shrink-0" @click.stop>
                                <input
                                    type="radio"
                                    :value="client.id"
                                    v-model="manualKeepId"
                                    class="h-4 w-4 text-green-600"
                                />
                                <span
                                    class="text-xs font-semibold w-8"
                                    :class="manualKeepId === client.id ? 'text-green-700' : 'text-gray-400'"
                                >残す</span>
                            </label>

                            <!-- クライアント情報 -->
                            <div class="flex-1 min-w-0">
                                <span class="font-bold text-sm text-gray-900 break-all">{{ client.name }}</span>
                                <span v-if="client.is_dormant" class="ml-2 rounded-full bg-gray-200 px-1.5 py-0.5 text-xs text-gray-500">休眠</span>
                            </div>
                            <div class="shrink-0 flex items-center gap-4 text-xs text-gray-500">
                                <span>コード: <span class="font-mono">{{ client.client_code || '―' }}</span></span>
                                <span>案件: <strong class="text-gray-700">{{ client.project_jobs_count ?? 0 }}件</strong></span>
                            </div>

                            <!-- 削除ボタン -->
                            <button
                                type="button"
                                class="shrink-0 flex h-6 w-6 items-center justify-center rounded-full text-gray-400 hover:bg-red-100 hover:text-red-600 transition-colors"
                                @click.stop="removeManualClient(client.id)"
                                title="一覧から外す"
                            >×</button>
                        </div>
                    </div>

                    <!-- 統合ボタン -->
                    <div class="flex items-center justify-between gap-3 border-t border-gray-100 bg-gray-50 px-5 py-3">
                        <p class="text-xs text-gray-500">
                            残すクライアント:
                            <strong class="text-green-700">{{ manualKeepClient?.name ?? '（未選択）' }}</strong>
                        </p>
                        <button
                            type="button"
                            :disabled="!manualCanMerge || manualMerging"
                            class="rounded px-5 py-2 text-sm font-semibold text-white transition-colors"
                            :class="manualCanMerge && !manualMerging
                                ? 'bg-red-600 hover:bg-red-700'
                                : 'bg-gray-300 cursor-not-allowed'"
                            @click="doManualMerge"
                        >
                            {{ manualMerging ? '統合中…' : `${manualSelected.length - 1}件を統合` }}
                        </button>
                    </div>
                </div>

            </div>
        </div>

        <!-- 結果なし -->
        <div v-if="pairs.length === 0" class="rounded bg-white px-6 py-12 text-center shadow">
            <p class="text-2xl mb-2">✓</p>
            <p class="text-gray-600 font-medium">重複するクライアントは見つかりませんでした。</p>
        </div>

        <!-- ペア一覧 -->
        <template v-else>
            <!-- ヘッダーバー -->
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <span class="text-sm text-gray-600">
                        {{ pairs.length }}件の疑わしい重複が見つかりました
                    </span>
                    <button type="button" @click="selectAll"   class="rounded border px-3 py-1 text-xs text-gray-600 hover:bg-gray-100">全選択</button>
                    <button type="button" @click="deselectAll" class="rounded border px-3 py-1 text-xs text-gray-600 hover:bg-gray-100">全解除</button>
                </div>
                <button
                    type="button"
                    :disabled="checkedCount === 0 || processing"
                    class="rounded px-5 py-2 text-sm font-semibold text-white transition-colors"
                    :class="checkedCount > 0 && !processing
                        ? 'bg-red-600 hover:bg-red-700'
                        : 'bg-gray-300 cursor-not-allowed'"
                    @click="doMerge"
                >
                    {{ processing ? '統合中…' : `選択した ${checkedCount} 件を統合` }}
                </button>
            </div>

            <!-- ペアカード -->
            <div class="space-y-3">
                <div
                    v-for="(pair, i) in pairs"
                    :key="i"
                    class="rounded bg-white shadow overflow-hidden"
                    :class="selections[i].checked ? 'ring-2 ring-red-300' : 'opacity-60'"
                >
                    <!-- カードヘッダー -->
                    <div class="flex items-center gap-3 bg-gray-50 px-4 py-2.5 border-b border-gray-200">
                        <input
                            type="checkbox"
                            v-model="selections[i].checked"
                            class="h-4 w-4 rounded border-gray-300 text-red-600"
                        />
                        <span
                            class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                            :class="REASON_LABELS[pair.reason]?.color ?? 'bg-gray-100 text-gray-600'"
                        >{{ REASON_LABELS[pair.reason]?.label ?? pair.reason }}</span>
                        <span class="text-xs text-gray-400">ペア {{ i + 1 }}</span>
                    </div>

                    <!-- 2カラム比較 -->
                    <div class="grid grid-cols-2 divide-x divide-gray-100">
                        <!-- Client A -->
                        <div
                            class="p-4 cursor-pointer transition-colors"
                            :class="selections[i].keep === 'a' ? 'bg-green-50' : 'hover:bg-gray-50'"
                            @click="selections[i].keep = 'a'"
                        >
                            <label class="flex items-center gap-2 cursor-pointer mb-3">
                                <input
                                    type="radio"
                                    :name="`keep-${i}`"
                                    value="a"
                                    v-model="selections[i].keep"
                                    class="text-green-600"
                                    @click.stop
                                />
                                <span
                                    class="text-xs font-semibold"
                                    :class="selections[i].keep === 'a' ? 'text-green-700' : 'text-gray-400'"
                                >残す</span>
                            </label>
                            <p class="font-bold text-gray-900 text-sm mb-1 break-all">{{ pair.client_a.name }}</p>
                            <p class="text-xs text-gray-500">
                                コード: <span class="font-mono">{{ pair.client_a.client_code || '―' }}</span>
                            </p>
                            <p class="text-xs text-gray-500">
                                案件数: <strong class="text-gray-700">{{ pair.client_a.project_jobs_count }}件</strong>
                            </p>
                            <p class="text-xs text-gray-400">登録日: {{ pair.client_a.created_at || '―' }}</p>
                        </div>

                        <!-- Client B -->
                        <div
                            class="p-4 cursor-pointer transition-colors"
                            :class="selections[i].keep === 'b' ? 'bg-green-50' : 'hover:bg-gray-50'"
                            @click="selections[i].keep = 'b'"
                        >
                            <label class="flex items-center gap-2 cursor-pointer mb-3">
                                <input
                                    type="radio"
                                    :name="`keep-${i}`"
                                    value="b"
                                    v-model="selections[i].keep"
                                    class="text-green-600"
                                    @click.stop
                                />
                                <span
                                    class="text-xs font-semibold"
                                    :class="selections[i].keep === 'b' ? 'text-green-700' : 'text-gray-400'"
                                >残す</span>
                            </label>
                            <p class="font-bold text-gray-900 text-sm mb-1 break-all">{{ pair.client_b.name }}</p>
                            <p class="text-xs text-gray-500">
                                コード: <span class="font-mono">{{ pair.client_b.client_code || '―' }}</span>
                            </p>
                            <p class="text-xs text-gray-500">
                                案件数: <strong class="text-gray-700">{{ pair.client_b.project_jobs_count }}件</strong>
                            </p>
                            <p class="text-xs text-gray-400">登録日: {{ pair.client_b.created_at || '―' }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 下部統合ボタン（ペアが多いとき用） -->
            <div v-if="pairs.length > 3" class="mt-4 flex justify-end">
                <button
                    type="button"
                    :disabled="checkedCount === 0 || processing"
                    class="rounded px-5 py-2 text-sm font-semibold text-white transition-colors"
                    :class="checkedCount > 0 && !processing
                        ? 'bg-red-600 hover:bg-red-700'
                        : 'bg-gray-300 cursor-not-allowed'"
                    @click="doMerge"
                >
                    {{ processing ? '統合中…' : `選択した ${checkedCount} 件を統合` }}
                </button>
            </div>
        </template>
    </AppLayout>
</template>
