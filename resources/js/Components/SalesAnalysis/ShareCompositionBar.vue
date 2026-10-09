<script setup>
import { computed } from 'vue';

// 構成比（部分と全体）の可視化。円グラフは角度の比較が苦手で、得意先名・科目名など
// ラベルが長い日本語データとも相性が悪いため、横棒の積み上げ1本（Top N＋その他）で表す
// （dataviz skillの指針: 「part-to-whole rides on the stacked bar chart; donut stays
// deprioritized」に従った。2026-10-09、経費比較・売上分析各画面の「構成比」要望への対応）。
// 色は作図用に検証済みの固定順カラー（validate_palette.jsで隣接CVD分離を確認済み、
// light/dark双方でPASS）。既存UIのアクセント色（indigo等）とは独立した作図専用パレット。

const SERIES_COLORS = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4'];
const OTHER_COLOR = '#c3c2b7';

const props = defineProps({
    title: { type: String, default: '' },
    rows: { type: Array, default: () => [] }, // [{ label, amount }]。Top N件だけ渡されるケースを想定
    topN: { type: Number, default: 5 },
    formatValue: { type: Function, default: (v) => `¥${Number(v).toLocaleString()}` },
    // 真の合計金額（部署/会社の全件合計）。RankingPanelのTop N表示のように`rows`が全件ではない
    // 場合、「その他」をrows内の残りではなくこの値から逆算するために使う。省略時はrowsの合計を使う
    total: { type: Number, default: null },
});

const segments = computed(() => {
    const sorted = [...props.rows]
        .filter((r) => r.amount > 0)
        .sort((a, b) => b.amount - a.amount);

    const grandTotal = props.total ?? sorted.reduce((sum, r) => sum + r.amount, 0);
    if (grandTotal <= 0) return [];

    const top = sorted.slice(0, props.topN).map((r, i) => ({
        label: r.label,
        amount: r.amount,
        pct: (r.amount / grandTotal) * 100,
        color: SERIES_COLORS[i % SERIES_COLORS.length],
    }));

    const shownAmount = top.reduce((sum, r) => sum + r.amount, 0);
    const restAmount = grandTotal - shownAmount;
    if (restAmount > 0.5) {
        top.push({ label: 'その他', amount: restAmount, pct: (restAmount / grandTotal) * 100, color: OTHER_COLOR });
    }

    return top;
});
</script>

<template>
    <div v-if="segments.length">
        <p v-if="title" class="mb-2 text-xs font-semibold text-gray-700">{{ title }}</p>

        <!-- 積み上げ1本（構成比）。各セグメントの間に2pxのsurface gapを入れる -->
        <div class="flex h-6 w-full overflow-hidden rounded" role="img" :aria-label="title || '構成比'">
            <div
                v-for="(s, i) in segments"
                :key="s.label"
                class="h-full first:rounded-l last:rounded-r"
                :style="{ width: `${s.pct}%`, backgroundColor: s.color, marginRight: i < segments.length - 1 ? '2px' : 0 }"
                :title="`${s.label}: ${formatValue(s.amount)}（${s.pct.toFixed(1)}%）`"
            ></div>
        </div>

        <!-- 直接ラベル（凡例）。ホバーできない場合でも全件の数値を読めるようにする -->
        <ul class="mt-2 space-y-1 text-xs">
            <li v-for="s in segments" :key="s.label" class="flex items-center gap-2">
                <span class="h-2.5 w-2.5 flex-shrink-0 rounded-sm" :style="{ backgroundColor: s.color }"></span>
                <span class="min-w-0 flex-1 truncate text-gray-700">{{ s.label }}</span>
                <span class="flex-shrink-0 font-medium tabular-nums text-gray-900">{{ formatValue(s.amount) }}</span>
                <span class="w-12 flex-shrink-0 text-right tabular-nums text-gray-500">{{ s.pct.toFixed(1) }}%</span>
            </li>
        </ul>
    </div>
</template>
