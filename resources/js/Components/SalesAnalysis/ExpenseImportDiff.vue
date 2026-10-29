<script setup>
import { computed, ref, watch } from 'vue';
import { useSalesChart } from '@/Composables/useSalesChart';

// 経費の再取込時の差分（Phase24-6）。同じ会計年度の新しい版と、その直前の版を、部署→科目→支払先で突き合わせた結果。
// 金額が変わった支払先・増えた/消えた支払先・科目だけを出す。変わった月は支払先の行に「4月: 1,000→1,500」の形で添える。

const props = defineProps({
    diff: { type: Object, required: true },
    departmentLabels: { type: Object, default: () => ({}) },
});

const { pctClass, fiscalPeriodLabel } = useSalesChart();

const deptLabel = (k) => (k === 'shared' ? '共通費' : (props.departmentLabels[k] ?? k));
const yen = (v) => `¥${Math.round(v).toLocaleString()}`;
const signedYen = (v) => `${v > 0 ? '+' : v < 0 ? '−' : ''}¥${Math.abs(Math.round(v)).toLocaleString()}`;
const num = (v) => Math.round(v).toLocaleString();
const statusLabel = { added: '追加', removed: '削除', changed: '変更' };
const statusClass = {
    added: 'bg-blue-100 text-blue-700',
    removed: 'bg-red-100 text-red-700',
    changed: 'bg-amber-100 text-amber-700',
};

// 科目ごとの開閉（部署内で、科目名をキーにする）
const expanded = ref(new Set());
const key = (dept, category) => `${dept}\0${category}`;
const toggle = (dept, category) => {
    const next = new Set(expanded.value);
    const k = key(dept, category);
    if (next.has(k)) next.delete(k);
    else next.add(k);
    expanded.value = next;
};
const allKeys = computed(() => props.diff.departments?.flatMap((d) => d.categories.map((c) => key(d.department_key, c.category))) ?? []);
const allExpanded = computed(() => allKeys.value.length > 0 && allKeys.value.every((k) => expanded.value.has(k)));
const toggleAll = () => {
    expanded.value = allExpanded.value ? new Set() : new Set(allKeys.value);
};
watch(() => props.diff, () => {
    expanded.value = new Set();
});

const monthsText = (months) => months.map((m) => `${m.calendar_month}月: ${num(m.old)}→${num(m.new)}`).join('、');
</script>

<template>
    <div class="rounded-lg border border-gray-200 bg-white p-4">
        <div class="mb-2 flex flex-wrap items-baseline justify-between gap-2">
            <h4 class="text-sm font-semibold text-gray-900">
                {{ fiscalPeriodLabel(diff.fiscal_year) }}：v{{ diff.version }} と前の版（v{{ diff.previous_version }}）の差分
            </h4>
            <p class="text-xs text-gray-500">
                合計 {{ yen(diff.total_old) }} → {{ yen(diff.total_new) }}
                <span :class="pctClass(diff.diff)">（{{ signedYen(diff.diff) }}）</span>
            </p>
        </div>
        <p class="mb-3 text-[11px] text-gray-400">
            前の版: {{ diff.previous_original_filename }} ／ この版: {{ diff.original_filename }}。科目名・支払先名は名寄せの設定を反映して比べています（表記の違いだけでは差分になりません）。
        </p>

        <p v-if="!diff.has_changes" class="rounded bg-green-50 p-3 text-xs text-green-800">前の版と、金額・科目・支払先に違いはありません。</p>

        <template v-else>
            <div class="mb-3 flex flex-wrap gap-2 text-xs">
                <span class="rounded bg-amber-50 px-2 py-1 text-amber-800">金額が変わった支払先 {{ diff.summary.changed_vendors }}</span>
                <span class="rounded bg-blue-50 px-2 py-1 text-blue-800">追加された支払先 {{ diff.summary.added_vendors }}</span>
                <span class="rounded bg-red-50 px-2 py-1 text-red-800">消えた支払先 {{ diff.summary.removed_vendors }}</span>
                <span v-if="diff.summary.added_categories" class="rounded bg-blue-50 px-2 py-1 text-blue-800">追加された科目 {{ diff.summary.added_categories }}</span>
                <span v-if="diff.summary.removed_categories" class="rounded bg-red-50 px-2 py-1 text-red-800">消えた科目 {{ diff.summary.removed_categories }}</span>
                <button type="button" class="ml-auto text-indigo-600 hover:underline" @click="toggleAll">{{ allExpanded ? '科目をすべて閉じる' : '科目をすべて開く' }}</button>
            </div>

            <div v-for="d in diff.departments" :key="d.department_key" class="mb-3 rounded border">
                <div class="flex items-center justify-between bg-gray-50 px-3 py-1.5 text-xs">
                    <span class="font-semibold text-gray-800">{{ deptLabel(d.department_key) }}</span>
                    <span class="text-gray-500">
                        {{ yen(d.total_old) }} → {{ yen(d.total_new) }}
                        <span :class="pctClass(d.diff)">（{{ signedYen(d.diff) }}）</span>
                    </span>
                </div>

                <div v-for="c in d.categories" :key="c.category" class="border-t">
                    <button type="button" class="flex w-full items-center justify-between gap-2 px-3 py-1.5 text-left text-xs hover:bg-indigo-50" @click="toggle(d.department_key, c.category)">
                        <span>
                            <span class="mr-1 inline-block w-3 text-gray-400">{{ expanded.has(key(d.department_key, c.category)) ? '▾' : '▸' }}</span>
                            <span class="font-medium text-gray-800">{{ c.category }}</span>
                            <span class="ml-1 rounded px-1 text-[10px]" :class="statusClass[c.status]">{{ c.status === 'changed' ? '金額変更' : `科目${statusLabel[c.status]}` }}</span>
                            <span class="ml-1 text-[10px] text-gray-400">{{ c.vendors.length }}件</span>
                        </span>
                        <span class="shrink-0 tabular-nums text-gray-500">
                            {{ yen(c.total_old) }} → {{ yen(c.total_new) }}
                            <span :class="pctClass(c.diff)">（{{ signedYen(c.diff) }}）</span>
                        </span>
                    </button>

                    <table v-if="expanded.has(key(d.department_key, c.category))" class="min-w-full border-t bg-gray-50/50 text-xs tabular-nums">
                        <thead>
                            <tr class="text-left text-gray-500">
                                <th class="py-1 pl-8 pr-2">支払先</th>
                                <th class="px-2 py-1 text-right">前の版</th>
                                <th class="px-2 py-1 text-right">この版</th>
                                <th class="px-2 py-1 text-right">増減</th>
                                <th class="px-2 py-1">変わった月</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="v in c.vendors" :key="v.vendor_name" class="border-t">
                                <td class="py-1 pl-8 pr-2">
                                    <span class="mr-1 rounded px-1 text-[10px]" :class="statusClass[v.status]">{{ statusLabel[v.status] }}</span>{{ v.vendor_name }}
                                </td>
                                <td class="px-2 py-1 text-right text-gray-500">{{ v.status === 'added' ? '—' : yen(v.total_old) }}</td>
                                <td class="px-2 py-1 text-right">{{ v.status === 'removed' ? '—' : yen(v.total_new) }}</td>
                                <td class="px-2 py-1 text-right" :class="pctClass(v.diff)">{{ signedYen(v.diff) }}</td>
                                <td class="px-2 py-1 text-[11px] text-gray-500">{{ monthsText(v.months) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>
    </div>
</template>
