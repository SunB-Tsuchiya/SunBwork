<script setup>
import { computed, ref, watch } from 'vue';
import { useSalesChart } from '@/Composables/useSalesChart';

// 経費「部署シート」の注目ポイント（Phase24-2）。会議で見るべき所の入り口として、
//  ・大きな増減: 比較年度（同期間）比で±pct%以上、かつ増減額が○万円以上の科目・支払先
//  ・新規: 比較年度に無かった支払先
//  ・消えた可能性: 比較年度にあったのに、今年の実績月までは払いがない支払先
// を一覧にする。行をクリックすると、表の該当の科目・支払先へ移動する。

const props = defineProps({
    sheet: { type: Object, required: true },
});
const emit = defineEmits(['focus']);

const { pctClass, fiscalPeriodLabel } = useSalesChart();

const STORAGE_KEY = 'expenseHighlightThresholds';
const pctThreshold = ref(30);
const amountThresholdMan = ref(10); // 万円
const open = ref(true);
const showAll = ref({ changes: false, news: false, vanished: false });
const LIMIT = 8;

// 基準は画面で変えられる。端末ごとの好みなのでlocalStorageに残す（使えない環境でも画面は動く）
try {
    const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) ?? 'null');
    if (saved && Number.isFinite(saved.pct) && Number.isFinite(saved.man)) {
        pctThreshold.value = saved.pct;
        amountThresholdMan.value = saved.man;
    }
} catch (e) { /* 未使用 */ }
watch([pctThreshold, amountThresholdMan], ([pct, man]) => {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify({ pct: Number(pct), man: Number(man) }));
    } catch (e) { /* 未使用 */ }
});

const yen = (v) => `¥${Math.round(v).toLocaleString()}`;
const signedYen = (v) => `${v > 0 ? '+' : v < 0 ? '−' : ''}¥${Math.abs(Math.round(v)).toLocaleString()}`;
const existingCategories = computed(() => new Set(props.sheet.categories.map((c) => c.category)));
const prevLabel = computed(() => fiscalPeriodLabel(props.sheet.previous_fiscal_year));

const changes = computed(() => {
    if (!props.sheet.previous_has_data) return [];
    const pct = Math.max(0, Number(pctThreshold.value) || 0);
    const amount = Math.max(0, Number(amountThresholdMan.value) || 0) * 10000;
    const hit = (row) => !row.is_new && row.diff !== null && row.previous_total_amount > 0
        && Math.abs(row.diff) >= amount && row.diff_pct !== null && Math.abs(row.diff_pct) >= pct;

    const list = [];
    props.sheet.categories.forEach((c) => {
        if (hit(c)) list.push({ category: c.category, vendor: null, current: c.total_amount, previous: c.previous_total_amount, diff: c.diff, pct: c.diff_pct });
        c.groups.forEach((g) => g.vendors.forEach((v) => {
            if (hit(v)) list.push({ category: c.category, vendor: v.vendor_name, current: v.total_amount, previous: v.previous_total_amount, diff: v.diff, pct: v.diff_pct });
        }));
    });

    return list.sort((a, b) => Math.abs(b.diff) - Math.abs(a.diff));
});

const news = computed(() => {
    if (!props.sheet.previous_has_data) return [];
    const list = [];
    props.sheet.categories.forEach((c) => c.groups.forEach((g) => g.vendors.forEach((v) => {
        if (v.is_new && v.total_amount > 0) list.push({ category: c.category, vendor: v.vendor_name, current: v.total_amount, categoryNew: c.is_new });
    })));

    return list.sort((a, b) => b.current - a.current);
});

const vanished = computed(() => props.sheet.vanished_vendors ?? []);

const visible = (key, list) => (showAll.value[key] ? list : list.slice(0, LIMIT));
const total = computed(() => changes.value.length + news.value.length + vanished.value.length);

const go = (category, vendor) => {
    if (existingCategories.value.has(category)) emit('focus', { category, vendor });
};
const rowClass = (category) => (existingCategories.value.has(category) ? 'cursor-pointer hover:bg-indigo-50' : '');
</script>

<template>
    <div class="mb-4 rounded border border-amber-200 bg-amber-50/50">
        <button type="button" class="flex w-full items-center justify-between px-3 py-2 text-left" @click="open = !open">
            <span class="text-sm font-semibold text-gray-900">
                <span class="mr-1 inline-block w-3 text-gray-400">{{ open ? '▾' : '▸' }}</span>注目ポイント
                <span v-if="sheet.previous_has_data" class="ml-2 text-xs font-normal text-gray-500">{{ prevLabel }}との比較・{{ total }}件</span>
            </span>
        </button>

        <div v-if="open" class="border-t border-amber-200 px-3 pb-3 pt-2">
            <p v-if="!sheet.previous_has_data" class="text-xs text-gray-500">{{ prevLabel }}のデータが無いため、注目ポイントは表示できません。</p>
            <template v-else>
                <div class="mb-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-600">
                    <span>大きな増減の基準:</span>
                    <label class="flex items-center gap-1">
                        <input v-model.number="pctThreshold" type="number" min="0" step="5" class="w-16 rounded-md border-gray-300 px-1 py-0.5 text-xs shadow-sm" />% 以上
                    </label>
                    <label class="flex items-center gap-1">
                        かつ<input v-model.number="amountThresholdMan" type="number" min="0" step="5" class="w-16 rounded-md border-gray-300 px-1 py-0.5 text-xs shadow-sm" />万円 以上
                    </label>
                    <span class="text-gray-400">行をクリックすると、表の該当の行へ移動します</span>
                </div>

                <div class="grid gap-3 lg:grid-cols-3">
                    <!-- 大きな増減 -->
                    <div class="rounded bg-white p-2 text-xs shadow-sm">
                        <h4 class="mb-1 font-semibold text-gray-800">大きな増減（{{ changes.length }}件）</h4>
                        <p v-if="!changes.length" class="text-gray-400">基準に当てはまるものはありません。</p>
                        <ul class="divide-y">
                            <li v-for="(r, i) in visible('changes', changes)" :key="i" class="flex items-start justify-between gap-2 px-1 py-1" :class="rowClass(r.category)" @click="go(r.category, r.vendor)">
                                <span class="min-w-0">
                                    <span class="block truncate font-medium text-gray-800">{{ r.vendor ?? r.category }}</span>
                                    <span class="block truncate text-[10px] text-gray-400">{{ r.vendor ? r.category : '科目' }}・{{ yen(r.previous) }} → {{ yen(r.current) }}</span>
                                </span>
                                <span class="shrink-0 text-right" :class="pctClass(r.diff)">
                                    {{ signedYen(r.diff) }}
                                    <span class="block text-[10px]">{{ r.pct > 0 ? '+' : '' }}{{ r.pct }}%</span>
                                </span>
                            </li>
                        </ul>
                        <button v-if="changes.length > LIMIT" type="button" class="mt-1 text-[11px] text-indigo-600 hover:underline" @click="showAll.changes = !showAll.changes">
                            {{ showAll.changes ? '閉じる' : `すべて表示（${changes.length}件）` }}
                        </button>
                    </div>

                    <!-- 新規 -->
                    <div class="rounded bg-white p-2 text-xs shadow-sm">
                        <h4 class="mb-1 font-semibold text-gray-800">新規の支払先（{{ news.length }}件）</h4>
                        <p v-if="!news.length" class="text-gray-400">{{ prevLabel }}に無かった支払先はありません。</p>
                        <ul class="divide-y">
                            <li v-for="(r, i) in visible('news', news)" :key="i" class="flex items-start justify-between gap-2 px-1 py-1" :class="rowClass(r.category)" @click="go(r.category, r.vendor)">
                                <span class="min-w-0">
                                    <span class="block truncate font-medium text-gray-800">{{ r.vendor }}</span>
                                    <span class="block truncate text-[10px] text-gray-400">{{ r.category }}{{ r.categoryNew ? '（科目ごと新規）' : '' }}</span>
                                </span>
                                <span class="shrink-0 text-right text-gray-700">{{ yen(r.current) }}</span>
                            </li>
                        </ul>
                        <button v-if="news.length > LIMIT" type="button" class="mt-1 text-[11px] text-indigo-600 hover:underline" @click="showAll.news = !showAll.news">
                            {{ showAll.news ? '閉じる' : `すべて表示（${news.length}件）` }}
                        </button>
                    </div>

                    <!-- 消えた可能性 -->
                    <div class="rounded bg-white p-2 text-xs shadow-sm">
                        <h4 class="mb-1 font-semibold text-gray-800">消えた可能性の支払先（{{ vanished.length }}件）</h4>
                        <p class="mb-1 text-[10px] text-gray-400">
                            {{ prevLabel }}{{ sheet.compare_period === 'ytd' ? 'の同じ期間' : '' }}に払いがあったのに、今年はまだ払いがない支払先です。
                            毎月ではない支払先は、実績月が増えると復活することがあります。
                        </p>
                        <p v-if="!vanished.length" class="text-gray-400">該当はありません。</p>
                        <ul class="divide-y">
                            <li v-for="(r, i) in visible('vanished', vanished)" :key="i" class="flex items-start justify-between gap-2 px-1 py-1" :class="rowClass(r.category)" @click="go(r.category, null)">
                                <span class="min-w-0">
                                    <span class="block truncate font-medium text-gray-800">{{ r.vendor_name }}</span>
                                    <span class="block truncate text-[10px] text-gray-400">{{ r.category }}{{ r.category_vanished ? '（今年は科目なし）' : '' }}</span>
                                </span>
                                <span class="shrink-0 text-right text-gray-700">{{ yen(r.previous_total_amount) }}</span>
                            </li>
                        </ul>
                        <button v-if="vanished.length > LIMIT" type="button" class="mt-1 text-[11px] text-indigo-600 hover:underline" @click="showAll.vanished = !showAll.vanished">
                            {{ showAll.vanished ? '閉じる' : `すべて表示（${vanished.length}件）` }}
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </div>
</template>
