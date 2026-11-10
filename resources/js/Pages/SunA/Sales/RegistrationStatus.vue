<script setup>
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import ReportShell from '@/Components/SunA/Sales/ReportShell.vue';

// 売上レポート（営業）のトップ「データ登録状況」。年（暦年。サンエー印刷は1月〜12月が1期）× 月の登録状況、
// 最終実績月、年ごとの合計、部・課別の件数と金額、直近の取込を見る。空欄の月は「未登録」、0円は登録済み。
const props = defineProps({
    status: { type: Object, required: true },
});

const page = usePage();
const canImport = computed(() => !!page.props.auth?.salesReportCanImport);

const yen = (v) => (v === null || v === undefined ? '—' : `¥${Math.round(v).toLocaleString()}`);
// 月のセルには金額を万円単位で出す（表を見渡しやすくするため。詳細は title に円で出す）
const man = (v) => `${Math.round(v / 10000).toLocaleString()}`;
const importedAt = (iso) => (iso ? new Date(iso).toLocaleString('ja-JP', { dateStyle: 'medium', timeStyle: 'short' }) : '');

const selectedYear = ref(props.status.years.length ? props.status.years[props.status.years.length - 1].year : null);
const selected = computed(() => props.status.years.find((y) => y.year === selectedYear.value) ?? null);

const lastText = computed(() => {
    const l = props.status.last_registered;
    return l ? `${l.year}年${l.month}月` : '—';
});
const latestYear = computed(() => props.status.years[props.status.years.length - 1] ?? null);

const importHref = () => {
    try { return route('suna.sales.report.import.create'); } catch { return null; }
};
const historyHref = () => {
    try { return route('suna.sales.report.history.index'); } catch { return null; }
};
</script>

<template>
    <ReportShell title="データ登録状況" active="index">
        <div v-if="!status.has_data" class="rounded bg-white p-6 shadow">
            <p class="text-sm text-gray-700">まだ売上データが登録されていません。</p>
            <p class="mt-1 text-xs text-gray-500">
                営業部の「得意先別」売上Excelを取り込むと、ここに、年ごと・月ごとの登録状況と、部・課別の集計が表示されます。
            </p>
            <Link v-if="canImport && importHref()" :href="importHref()" class="mt-4 inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-bold text-white hover:bg-indigo-700">Excel取込へ</Link>
            <p v-else-if="!canImport" class="mt-3 text-xs text-gray-400">取込は、取込を許可された人が行います（管理者にご相談ください）。</p>
        </div>

        <div v-else class="space-y-4">
            <!-- 概要 -->
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div class="rounded bg-white p-3 shadow">
                    <p class="text-xs text-gray-500">最終実績月</p>
                    <p class="text-lg font-bold text-gray-900">{{ lastText }}</p>
                    <p class="text-[11px] text-gray-400">空欄の月は「未登録」として扱います</p>
                </div>
                <div class="rounded bg-white p-3 shadow">
                    <p class="text-xs text-gray-500">登録されている年</p>
                    <p class="text-lg font-bold text-gray-900">{{ status.years.length }}年分</p>
                    <p class="text-[11px] text-gray-400">{{ status.years[0].year }}年〜{{ latestYear.year }}年（1月〜12月が1期）</p>
                </div>
                <div class="rounded bg-white p-3 shadow">
                    <p class="text-xs text-gray-500">{{ latestYear.year }}年の得意先数</p>
                    <p class="text-lg font-bold text-gray-900">{{ latestYear.client_count.toLocaleString() }}社</p>
                    <p class="text-[11px] text-gray-400">売上金額 {{ yen(latestYear.total_amount) }}</p>
                </div>
                <div class="rounded bg-white p-3 shadow">
                    <p class="text-xs text-gray-500">直近の取込</p>
                    <template v-if="status.last_import">
                        <p class="text-sm font-bold text-gray-900">{{ importedAt(status.last_import.imported_at) }}</p>
                        <p class="text-[11px] text-gray-400">{{ status.last_import.imported_by_name }}・{{ status.last_import.original_filename }}</p>
                    </template>
                    <p v-else class="text-sm text-gray-400">—</p>
                    <Link v-if="historyHref()" :href="historyHref()" class="text-[11px] text-indigo-600 hover:underline">取込履歴を見る</Link>
                </div>
            </div>

            <!-- 年 × 月の登録状況 -->
            <div class="rounded bg-white p-4 shadow">
                <h2 class="mb-1 text-sm font-semibold text-gray-900">年・月ごとの登録状況</h2>
                <p class="mb-2 text-[11px] text-gray-400">セルの数字は、その月の売上金額（万円）。「—」は未登録の月です。セルに合わせると、金額（円）・受注件数・得意先数が出ます。</p>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs tabular-nums">
                        <thead>
                            <tr class="text-gray-500">
                                <th class="px-2 py-1 text-left">年</th>
                                <th v-for="m in 12" :key="m" class="px-2 py-1 text-right">{{ m }}月</th>
                                <th class="px-2 py-1 text-right">得意先数</th>
                                <th class="px-2 py-1 text-right">売上金額（年計）</th>
                                <th class="px-2 py-1 text-right">受注件数</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="y in status.years"
                                :key="y.year"
                                class="cursor-pointer border-t hover:bg-indigo-50"
                                :class="selectedYear === y.year ? 'bg-indigo-50' : ''"
                                @click="selectedYear = y.year"
                            >
                                <td class="px-2 py-1.5 font-semibold text-gray-800">{{ y.year }}年</td>
                                <td
                                    v-for="m in y.months"
                                    :key="m.month"
                                    class="px-2 py-1.5 text-right"
                                    :class="m.registered ? 'text-gray-800' : 'text-gray-300'"
                                    :title="m.registered ? `${yen(m.amount)}／受注${m.order_count.toLocaleString()}件／${m.client_count}社` : '未登録'"
                                >{{ m.registered ? man(m.amount) : '—' }}</td>
                                <td class="px-2 py-1.5 text-right">{{ y.client_count.toLocaleString() }}</td>
                                <td class="px-2 py-1.5 text-right font-medium">{{ yen(y.total_amount) }}</td>
                                <td class="px-2 py-1.5 text-right">{{ y.total_orders.toLocaleString() }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="mt-1 text-[11px] text-gray-400">行をクリックすると、下の部・課別が、その年に切り替わります。</p>
            </div>

            <!-- 部・課別 -->
            <div v-if="selected" class="rounded bg-white p-4 shadow">
                <h2 class="mb-2 text-sm font-semibold text-gray-900">{{ selected.year }}年の部・課別</h2>
                <table class="min-w-full text-xs tabular-nums">
                    <thead>
                        <tr class="text-left text-gray-500">
                            <th class="py-1">担当部門</th>
                            <th class="py-1 text-right">得意先数</th>
                            <th class="py-1 text-right">売上金額</th>
                            <th class="py-1 text-right">構成比</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="d in selected.by_department" :key="`${d.department_key}-${d.section_key}`" class="border-t">
                            <td class="py-1.5" :class="d.section_key ? 'pl-5 text-gray-600' : 'font-medium text-gray-800'">{{ d.label }}</td>
                            <td class="py-1.5 text-right">{{ d.client_count.toLocaleString() }}</td>
                            <td class="py-1.5 text-right">{{ yen(d.amount) }}</td>
                            <td class="py-1.5 text-right text-gray-500">{{ selected.total_amount > 0 ? `${(d.amount / selected.total_amount * 100).toFixed(1)}%` : '—' }}</td>
                        </tr>
                    </tbody>
                </table>
                <p class="mt-2 text-[11px] text-gray-400">担当部門は年ごとに持ちます（担当替えがあった得意先は、その年の担当部門に入ります）。「直轄」は、課に属さない第三部の得意先です。</p>
            </div>
        </div>
    </ReportShell>
</template>
