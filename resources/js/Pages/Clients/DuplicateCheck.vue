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
                <p>このページでは、名前や伝票番号が似ているクライアントの<strong>疑わしい重複ペア</strong>を確認・統合できます。</p>
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
