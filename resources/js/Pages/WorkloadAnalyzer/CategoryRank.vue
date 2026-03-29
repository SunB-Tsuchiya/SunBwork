<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Inertia } from '@inertiajs/inertia';
import { Head, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    companies: { type: Array, default: () => [] },
    selected_ym: { type: String, default: '' },
});

const page = usePage();
const authUser = page.props?.auth?.user ?? page.props?.user ?? null;
const userRole = authUser?.user_role || '';
const rolePrefix = (() => {
    const r = String(userRole).toLowerCase();
    if (r.includes('super')) return '/superadmin';
    if (r.includes('admin')) return '/admin';
    return '/leader';
})();
const routeNamePrefix = (() => {
    const r = String(userRole).toLowerCase();
    if (r.includes('super')) return 'superadmin';
    if (r.includes('admin')) return 'admin';
    return 'leader';
})();

const currentMonth = (() => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
})();

const selectedYm = ref(props.selected_ym || currentMonth);

const months = [];
for (let i = 0; i < 12; i++) {
    const d = new Date();
    d.setMonth(d.getMonth() - i);
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    months.push({ value: `${y}-${m}`, label: `${y}年${m}月` });
}

function changeYm() {
    const ym = selectedYm.value || currentMonth;
    try {
        Inertia.get(route(`${routeNamePrefix}.workload_analyzer.category_rank`, { ym }));
        return;
    } catch (e) {}
    window.location.href = `${rolePrefix}/workload-analyzer/category-rank?ym=${ym}`;
}

function goToOverallRank() {
    const ym = selectedYm.value || currentMonth;
    try {
        Inertia.get(route(`${routeNamePrefix}.workload_analyzer.index`, { ym }));
        return;
    } catch (e) {}
    window.location.href = `${rolePrefix}/workload-analyzer?ym=${ym}`;
}

// カテゴリ定義
const categories = [
    {
        key: 'total_pages',
        label: '総ページ',
        getValue: (m) => (m.aggregates?.assigned?.pages || 0) + (m.aggregates?.self?.pages || 0),
        format: (v) => `${v} ページ`,
    },
    {
        key: 'stage',
        label: 'ステージ',
        getValue: (m) => m.aggregates?.points?.stage ?? 0,
        format: (v) => `${v} pt`,
    },
    {
        key: 'size',
        label: 'サイズ',
        getValue: (m) => m.aggregates?.points?.size ?? 0,
        format: (v) => `${v} pt`,
    },
    {
        key: 'type',
        label: '種別',
        getValue: (m) => m.aggregates?.points?.type ?? 0,
        format: (v) => `${v} pt`,
    },
    {
        key: 'difficulty',
        label: '難易度',
        getValue: (m) => m.aggregates?.points?.difficulty ?? 0,
        format: (v) => `${v} pt`,
    },
    {
        key: 'event',
        label: 'イベント',
        getValue: (m) => m.aggregates?.points?.event ?? 0,
        format: (v) => `${v} pt`,
    },
    {
        key: 'overtime',
        label: '残業',
        getValue: (m) => m.aggregates?.points?.overtime ?? 0,
        format: (v) => `${v} pt`,
    },
];

const selectedCategory = ref('total_pages');
const currentCategory = computed(() => categories.find((c) => c.key === selectedCategory.value) ?? categories[0]);

// 雇用形態フィルター
const employmentFilter = ref('all'); // 'all' | 'regular_contract' | 'dispatch_outsource'

const EMPLOYMENT_FILTER_OPTIONS = [
    { value: 'all',                label: 'すべて' },
    { value: 'regular_contract',   label: '正社員・契約社員' },
    { value: 'dispatch_outsource', label: '派遣・業務委託' },
];

function employmentBadgeClass(type) {
    switch (type) {
        case 'dispatch':  return 'bg-orange-100 text-orange-700';
        case 'outsource': return 'bg-purple-100 text-purple-700';
        case 'contract':  return 'bg-green-100 text-green-700';
        default:          return '';
    }
}

function matchesEmploymentFilter(m) {
    if (employmentFilter.value === 'all') return true;
    const t = m.employment_type || 'regular';
    if (employmentFilter.value === 'regular_contract') return t === 'regular' || t === 'contract';
    if (employmentFilter.value === 'dispatch_outsource') return t === 'dispatch' || t === 'outsource';
    return true;
}

// 部署ごとにメンバーを収集（重複排除）してカテゴリ値でランキング
function getDeptRanking(dept) {
    const seen = new Set();
    const members = [];
    (dept.members ?? []).forEach((m) => {
        if (!seen.has(m.id)) { seen.add(m.id); members.push(m); }
    });
    (dept.teams ?? []).forEach((t) => {
        (t.members ?? []).forEach((m) => {
            if (!seen.has(m.id)) { seen.add(m.id); members.push(m); }
        });
    });

    const getValue = currentCategory.value.getValue;
    const scored = members.filter(matchesEmploymentFilter).map((m) => ({ ...m, _score: getValue(m) }));
    scored.sort((a, b) => b._score - a._score);

    // 同値タイは同順位
    let rank = 0;
    let prevScore = null;
    let prevRank = 0;
    return scored.map((m, i) => {
        if (m._score !== prevScore) {
            rank = i + 1;
            prevRank = rank;
        } else {
            rank = prevRank;
        }
        prevScore = m._score;
        return { ...m, _rank: rank };
    });
}

// 全社横断フラット（全部署）でランキング
function getCompanyRanking(company) {
    const seen = new Set();
    const members = [];
    (company.departments ?? []).forEach((d) => {
        (d.members ?? []).forEach((m) => {
            if (!seen.has(m.id)) { seen.add(m.id); members.push({ ...m, _dept: d.name }); }
        });
        (d.teams ?? []).forEach((t) => {
            (t.members ?? []).forEach((m) => {
                if (!seen.has(m.id)) { seen.add(m.id); members.push({ ...m, _dept: d.name }); }
            });
        });
    });

    const getValue = currentCategory.value.getValue;
    const scored = members.map((m) => ({ ...m, _score: getValue(m) }));
    scored.sort((a, b) => b._score - a._score);

    let rank = 0;
    let prevScore = null;
    let prevRank = 0;
    return scored.map((m, i) => {
        if (m._score !== prevScore) {
            rank = i + 1;
            prevRank = rank;
        } else {
            rank = prevRank;
        }
        prevScore = m._score;
        return { ...m, _rank: rank };
    });
}

const rankBadgeClass = (rank) => {
    if (rank === 1) return 'bg-yellow-400 text-yellow-900';
    if (rank === 2) return 'bg-gray-300 text-gray-800';
    if (rank === 3) return 'bg-amber-600 text-white';
    return 'bg-gray-100 text-gray-600';
};
</script>

<template>
    <AppLayout title="作業量分析 カテゴリ別ランク">
        <Head title="作業量分析 カテゴリ別ランク" />

        <div class="rounded bg-white p-6 shadow">
            <h1 class="mb-4 text-xl font-semibold text-gray-800">総合ランキング</h1>

            <!-- ランキング種別トグル + 雇用形態フィルター -->
            <div class="mb-3 flex flex-wrap items-center gap-2">
                <button
                    @click="goToOverallRank"
                    class="rounded px-4 py-1.5 text-sm font-medium transition-colors bg-gray-100 text-gray-600 hover:bg-gray-200"
                >総合ランク</button>
                <button
                    class="rounded px-4 py-1.5 text-sm font-medium transition-colors bg-blue-600 text-white"
                >カテゴリ別ランク</button>

                <!-- 雇用形態フィルター -->
                <div class="ml-auto flex items-center gap-1.5">
                    <span class="text-xs text-gray-500">雇用形態:</span>
                    <button
                        v-for="opt in EMPLOYMENT_FILTER_OPTIONS"
                        :key="opt.value"
                        class="rounded-full border px-3 py-0.5 text-xs transition"
                        :class="employmentFilter === opt.value
                            ? 'border-orange-400 bg-orange-100 text-orange-700 font-semibold'
                            : 'border-gray-200 text-gray-500 hover:bg-gray-50'"
                        @click="employmentFilter = opt.value"
                    >
                        {{ opt.label }}
                    </button>
                </div>
            </div>

            <!-- 年月セレクタ -->
            <div class="mb-5 flex items-center gap-3">
                <label class="text-sm text-gray-600">年月:</label>
                <select class="w-40 rounded border px-3 py-1 text-sm" v-model="selectedYm" @change="changeYm">
                    <option v-for="m in months" :key="m.value" :value="m.value">{{ m.label }}</option>
                </select>
                <span class="text-sm text-gray-500">表示中: {{ selectedYm }}</span>
            </div>

            <!-- カテゴリ選択ボタン -->
            <div class="mb-6 flex flex-wrap gap-2">
                <button
                    v-for="cat in categories"
                    :key="cat.key"
                    @click="selectedCategory = cat.key"
                    :class="selectedCategory === cat.key
                        ? 'bg-indigo-600 text-white'
                        : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                    class="rounded px-3 py-1 text-sm font-medium transition-colors"
                >{{ cat.label }}</button>
            </div>

            <!-- 会社→部署ごとのランキングテーブル -->
            <div v-for="company in props.companies" :key="company.id" class="space-y-6">
                <div v-if="props.companies.length > 1" class="text-base font-semibold text-gray-700">
                    {{ company.name }}
                </div>

                <div v-for="dept in company.departments" :key="dept.id">
                    <h3 class="mb-2 text-sm font-semibold text-gray-600">
                        部署: {{ dept.name }}
                        <span class="ml-2 text-xs font-normal text-gray-400">{{ currentCategory.label }}順位</span>
                    </h3>
                    <div class="overflow-x-auto rounded border">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="w-14 px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">順位</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">名前</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">役割</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium uppercase tracking-wider text-gray-500">{{ currentCategory.label }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                <tr v-for="row in getDeptRanking(dept)" :key="row.id" class="hover:bg-gray-50">
                                    <td class="px-4 py-2">
                                        <span
                                            :class="rankBadgeClass(row._rank)"
                                            class="inline-flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold"
                                        >{{ row._rank }}</span>
                                    </td>
                                    <td class="px-4 py-2 font-medium text-gray-800">
                                        {{ row.name }}
                                        <span
                                            v-if="employmentBadgeClass(row.employment_type)"
                                            class="ml-1 inline-block rounded-full px-1.5 py-0 text-xs font-normal"
                                            :class="employmentBadgeClass(row.employment_type)"
                                        >{{ row.employment_type_label }}</span>
                                    </td>
                                    <td class="px-4 py-2 text-gray-500">{{ row.assignment_name || '—' }}</td>
                                    <td class="px-4 py-2 text-right font-semibold text-gray-700">
                                        {{ currentCategory.format(row._score) }}
                                    </td>
                                </tr>
                                <tr v-if="getDeptRanking(dept).length === 0">
                                    <td colspan="4" class="px-4 py-3 text-center text-xs text-gray-400">メンバーなし</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
