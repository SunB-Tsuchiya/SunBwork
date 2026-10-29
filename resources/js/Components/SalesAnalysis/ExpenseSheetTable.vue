<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { useSalesChart } from '@/Composables/useSalesChart';

// 経費「部署シート」表（Phase23）。Excelの部署シート（科目→会社名→4月〜3月→累計、小計・合計）を
// 1画面で再現する。科目は初期状態で閉じ、科目の合計行だけで部署全体を見渡せる。
// 支払先の並びはExcel登場順（既定）と金額順を切り替えられる。

const emit = defineEmits(['vendor']);

const props = defineProps({
    sheet: { type: Object, required: true },
    // 部署・年度が変わったとき開閉状態を捨てるためのキー（部署切替で変える）
    resetKey: { type: String, default: '' },
});

const { pctClass, fiscalPeriodLabel } = useSalesChart();

// 表示モード: 金額 / 前年差（当年−比較年度）/ 前年比%（比較年度に対する増減率）
const mode = ref('amount');
const expanded = ref(new Set());
const sortMode = ref('excel'); // 'excel' | 'amount'
const heatmap = ref(true);

const categoryNames = computed(() => props.sheet.categories.map((c) => c.category));
const allExpanded = computed(() => categoryNames.value.length > 0 && categoryNames.value.every((n) => expanded.value.has(n)));

watch(() => props.resetKey, () => {
    expanded.value = new Set();
});
// 年度切替で科目が入れ替わったとき、存在しない科目の開閉状態だけを捨てる
watch(categoryNames, (names) => {
    expanded.value = new Set([...expanded.value].filter((n) => names.includes(n)));
});

// 注目ポイントなどからの「この行へ移動」: 科目を開き、該当行へスクロールして数秒だけ強調する
const rootRef = ref(null);
const highlightKey = ref(null);
const rowKey = (category, vendor = null) => JSON.stringify([category, vendor]);
let highlightTimer = null;
const focusRow = async (category, vendor = null) => {
    if (!categoryNames.value.includes(category)) return;
    expanded.value = new Set([...expanded.value, category]);
    await nextTick();
    const key = rowKey(category, vendor);
    const el = [...(rootRef.value?.querySelectorAll('[data-row]') ?? [])].find((e) => e.dataset.row === key)
        ?? [...(rootRef.value?.querySelectorAll('[data-row]') ?? [])].find((e) => e.dataset.row === rowKey(category));
    if (!el) return;
    highlightKey.value = el.dataset.row;
    el.scrollIntoView({ block: 'center', behavior: 'smooth' });
    clearTimeout(highlightTimer);
    highlightTimer = setTimeout(() => { highlightKey.value = null; }, 3000);
};
defineExpose({ focusRow });
const rowHighlight = (category, vendor = null) => (highlightKey.value === rowKey(category, vendor) ? '[&>td]:!bg-amber-100' : '');

const toggle = (name) => {
    const next = new Set(expanded.value);
    if (next.has(name)) next.delete(name);
    else next.add(name);
    expanded.value = next;
};
const toggleAll = () => {
    expanded.value = allExpanded.value ? new Set() : new Set(categoryNames.value);
};

const fmt = (v) => (v > 0 ? Math.round(v).toLocaleString() : '—');
const fmtTotal = (v) => Math.round(v).toLocaleString();
const sum = (arr) => arr.reduce((a, b) => a + b, 0);

// 差分モードのセル表示。cur=当年、prev=比較年度の同じ月（または累計）。比較年度データが無ければ「—」
const diffCell = (cur, prev) => {
    if (!props.sheet.previous_has_data) return { text: '—', cls: 'text-gray-400' };
    if (mode.value === 'diff') {
        if (cur === 0 && prev === 0) return { text: '—', cls: 'text-gray-400' };
        const d = Math.round(cur - prev);
        return { text: `${d > 0 ? '+' : ''}${d.toLocaleString()}`, cls: pctClass(d) };
    }
    if (prev === 0) return cur > 0 ? { text: '新規', cls: 'text-gray-600' } : { text: '—', cls: 'text-gray-400' };
    const p = Math.round((cur - prev) / prev * 1000) / 10;
    return { text: `${p > 0 ? '+' : ''}${p}%`, cls: pctClass(p) };
};
// 月セル・累計セル共通: 金額モードは従来の表示、差分モードはdiffCell
const cellText = (cur, prev) => (mode.value === 'amount' ? fmt(cur) : diffCell(cur, prev).text);
const totalText = (cur, prev) => (mode.value === 'amount' ? fmtTotal(cur) : diffCell(cur, prev).text);
const cellCls = (cur, prev) => (mode.value === 'amount' ? '' : diffCell(cur, prev).cls);

// 支払先（グループ内）の並び。小計グループ自体は常にExcel順のまま
const orderedVendors = (group) => (sortMode.value === 'amount'
    ? [...group.vendors].sort((a, b) => b.total_amount - a.total_amount)
    : group.vendors);

// ヒートマップ: 支払先セル・科目セルそれぞれの最大値を基準に濃淡を付ける
const maxVendorCell = computed(() => Math.max(1, ...props.sheet.categories.flatMap((c) => c.groups.flatMap((g) => g.vendors.flatMap((v) => v.months)))));
const maxCategoryCell = computed(() => Math.max(1, ...props.sheet.categories.flatMap((c) => c.months)));
const heatStyle = (value, max) => {
    if (mode.value !== 'amount' || !heatmap.value || !(value > 0)) return {};
    const alpha = 0.06 + 0.5 * Math.sqrt(value / max);
    return { backgroundColor: `rgba(79, 70, 229, ${alpha.toFixed(3)})` };
};

const prevLabel = computed(() => fiscalPeriodLabel(props.sheet.previous_fiscal_year));
</script>

<template>
    <div ref="rootRef">
        <div class="mb-2 flex flex-wrap items-center gap-2 text-xs">
            <button type="button" class="rounded-md border border-gray-300 bg-white px-2 py-1 text-gray-700 hover:bg-gray-50" @click="toggleAll">
                {{ allExpanded ? '全部閉じる' : '全部開く' }}
            </button>
            <div class="flex items-center gap-1">
                <span class="text-gray-500">支払先の並び</span>
                <button
                    type="button"
                    class="rounded px-2 py-1"
                    :class="sortMode === 'excel' ? 'bg-gray-700 text-white' : 'border border-gray-300 text-gray-600 hover:bg-gray-100'"
                    @click="sortMode = 'excel'"
                >Excelの順</button>
                <button
                    type="button"
                    class="rounded px-2 py-1"
                    :class="sortMode === 'amount' ? 'bg-gray-700 text-white' : 'border border-gray-300 text-gray-600 hover:bg-gray-100'"
                    @click="sortMode = 'amount'"
                >金額の大きい順</button>
            </div>
            <div class="flex items-center gap-1">
                <span class="text-gray-500">表示</span>
                <button
                    v-for="m in [['amount', '金額'], ['diff', '前年差'], ['pct', '前年比%']]"
                    :key="m[0]"
                    type="button"
                    class="rounded px-2 py-1"
                    :class="mode === m[0] ? 'bg-gray-700 text-white' : 'border border-gray-300 text-gray-600 hover:bg-gray-100'"
                    @click="mode = m[0]"
                >{{ m[1] }}</button>
            </div>
            <label class="flex items-center gap-1 text-gray-600" :class="mode !== 'amount' ? 'opacity-40' : ''">
                <input v-model="heatmap" type="checkbox" class="rounded border-gray-300" :disabled="mode !== 'amount'" />
                金額の濃淡
            </label>
            <span class="text-gray-400">科目の行をクリックすると支払先が開きます</span>
        </div>

        <div class="max-h-[70vh] overflow-auto rounded border">
            <table class="min-w-full border-separate border-spacing-0 text-xs tabular-nums">
                <thead>
                    <tr class="text-gray-600">
                        <th class="sticky left-0 top-0 z-30 min-w-[220px] border-b bg-gray-100 px-2 py-1.5 text-left">科目 / 支払先</th>
                        <th
                            v-for="m in sheet.months"
                            :key="`${m.calendar_year}-${m.calendar_month}`"
                            class="sticky top-0 z-20 min-w-[72px] border-b bg-gray-100 px-2 py-1.5 text-right"
                        >{{ m.calendar_month }}月</th>
                        <th class="sticky top-0 z-20 min-w-[88px] border-b border-l bg-gray-100 px-2 py-1.5 text-right">{{ mode === 'amount' ? '累計' : '累計の差' }}</th>
                        <th class="sticky top-0 z-20 min-w-[56px] border-b bg-gray-100 px-2 py-1.5 text-right">構成比</th>
                        <th class="sticky top-0 z-20 min-w-[96px] border-b bg-gray-100 px-2 py-1.5 text-right" :title="`${prevLabel}との比較`">前年比</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="c in sheet.categories" :key="c.category">
                        <tr class="cursor-pointer font-semibold hover:bg-gray-50" :class="rowHighlight(c.category)" :data-row="rowKey(c.category)" @click="toggle(c.category)">
                            <td class="sticky left-0 z-10 border-b bg-gray-50 px-2 py-1.5">
                                <span class="mr-1 inline-block w-3 text-gray-400">{{ expanded.has(c.category) ? '▾' : '▸' }}</span>{{ c.category }}
                                <span v-if="c.is_shared_split" class="ml-1 rounded bg-purple-100 px-1 text-[10px] font-semibold text-purple-700">サンエー印刷と折半</span>
                            </td>
                            <td v-for="(v, i) in c.months" :key="i" class="border-b bg-gray-50 px-2 py-1.5 text-right" :class="cellCls(v, c.previous_months[i])" :style="heatStyle(v, maxCategoryCell)">{{ cellText(v, c.previous_months[i]) }}</td>
                            <td class="border-b border-l bg-gray-50 px-2 py-1.5 text-right" :class="cellCls(c.total_amount, sum(c.previous_months))">{{ totalText(c.total_amount, sum(c.previous_months)) }}</td>
                            <td class="border-b bg-gray-50 px-2 py-1.5 text-right">{{ c.share_pct !== null ? `${c.share_pct}%` : '—' }}</td>
                            <td class="border-b bg-gray-50 px-2 py-1.5 text-right" :class="pctClass(c.diff_pct)">
                                <template v-if="c.is_new">新規</template>
                                <template v-else-if="c.diff_pct !== null">{{ c.diff_pct > 0 ? '+' : '' }}{{ c.diff_pct }}%</template>
                                <template v-else>—</template>
                            </td>
                        </tr>

                        <template v-if="expanded.has(c.category)">
                            <template v-for="(g, gi) in c.groups" :key="gi">
                                <tr v-for="v in orderedVendors(g)" :key="v.vendor_name" class="hover:bg-indigo-50/40" :class="rowHighlight(c.category, v.vendor_name)" :data-row="rowKey(c.category, v.vendor_name)">
                                    <td class="sticky left-0 z-10 border-b bg-white py-1 pl-7 pr-2">
                                        <!-- 支払先名をクリックすると、その支払先のカルテ（全部署・全年度の横断）へ移動する。内訳未記入は支払先ではないのでリンクにしない -->
                                        <button
                                            v-if="v.vendor_name !== '（内訳未記入）'"
                                            type="button"
                                            class="text-left hover:text-indigo-700 hover:underline"
                                            title="この支払先のカルテを開く"
                                            @click.stop="emit('vendor', v.vendor_name)"
                                        >{{ v.vendor_name }}</button>
                                        <span v-else class="text-gray-500">{{ v.vendor_name }}</span>
                                    </td>
                                    <td v-for="(amt, i) in v.months" :key="i" class="border-b px-2 py-1 text-right" :class="cellCls(amt, v.previous_months[i])" :style="heatStyle(amt, maxVendorCell)">{{ cellText(amt, v.previous_months[i]) }}</td>
                                    <td class="border-b border-l px-2 py-1 text-right" :class="cellCls(v.total_amount, sum(v.previous_months))">{{ totalText(v.total_amount, sum(v.previous_months)) }}</td>
                                    <td class="border-b px-2 py-1 text-right text-gray-500">{{ c.total_amount > 0 ? `${Math.round(v.total_amount / c.total_amount * 1000) / 10}%` : '—' }}</td>
                                    <td class="border-b px-2 py-1 text-right" :class="pctClass(v.diff_pct)">
                                        <template v-if="v.is_new">新規</template>
                                        <template v-else-if="v.diff_pct !== null">{{ v.diff_pct > 0 ? '+' : '' }}{{ v.diff_pct }}%</template>
                                        <template v-else>—</template>
                                    </td>
                                </tr>
                                <tr v-if="g.subtotal_label" class="font-semibold">
                                    <td class="sticky left-0 z-10 border-b bg-gray-100 py-1 pl-7 pr-2">{{ g.subtotal_label }}</td>
                                    <td v-for="(amt, i) in g.subtotal_months" :key="i" class="border-b bg-gray-100 px-2 py-1 text-right">{{ mode === 'amount' ? fmt(amt) : '' }}</td>
                                    <td class="border-b border-l bg-gray-100 px-2 py-1 text-right">{{ mode === 'amount' ? fmtTotal(g.subtotal_amount) : '' }}</td>
                                    <td class="border-b bg-gray-100"></td>
                                    <td class="border-b bg-gray-100"></td>
                                </tr>
                            </template>
                        </template>
                    </template>

                    <tr class="font-bold">
                        <td class="sticky bottom-0 left-0 z-20 border-t-2 bg-gray-100 px-2 py-1.5">合計</td>
                        <td v-for="(v, i) in sheet.month_totals" :key="i" class="sticky bottom-0 z-10 border-t-2 bg-gray-100 px-2 py-1.5 text-right" :class="cellCls(v, sheet.previous_month_totals[i])">{{ cellText(v, sheet.previous_month_totals[i]) }}</td>
                        <td class="sticky bottom-0 z-10 border-l border-t-2 bg-gray-100 px-2 py-1.5 text-right" :class="cellCls(sheet.total_amount, sheet.previous_total_amount ?? 0)">{{ totalText(sheet.total_amount, sheet.previous_total_amount ?? 0) }}</td>
                        <td class="sticky bottom-0 z-10 border-t-2 bg-gray-100 px-2 py-1.5 text-right">100%</td>
                        <td class="sticky bottom-0 z-10 border-t-2 bg-gray-100 px-2 py-1.5 text-right text-gray-500">
                            <template v-if="sheet.previous_total_amount !== null">{{ prevLabel }}: {{ fmtTotal(sheet.previous_total_amount) }}</template>
                            <template v-else>—</template>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p v-if="!sheet.previous_has_data" class="mt-2 text-[11px] text-gray-400">{{ prevLabel }}のデータが無いため、比較は表示できません。</p>
        <p v-else-if="mode !== 'amount'" class="mt-2 text-[11px] text-gray-400">
            {{ prevLabel }}との比較です。小計行は、比較年度で無くなった支払先を正確に引けないため、空欄にしています。
        </p>
    </div>
</template>
