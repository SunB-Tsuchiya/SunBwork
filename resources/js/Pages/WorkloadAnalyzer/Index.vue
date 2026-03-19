<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Inertia } from '@inertiajs/inertia';
import { Head, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
const props = defineProps({
    // 期待する props: companies: [{ id, name, departments: [{ id, name, teams: [{ id, name, members: [{ id, name }] }] }] }]
    companies: { type: Array, default: () => [] },
});
// read selected_ym and auth user from server-provided props (defensive)
const page = usePage();
// helper: current month in YYYY-MM
const currentMonth = (() => {
    const d = new Date();
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    return `${y}-${m}`;
})();

function getYmFromUrl() {
    if (typeof window === 'undefined') return null;
    try {
        const params = new URL(window.location.href).searchParams;
        const candidate = params.get('ym');
        if (candidate && /^\d{4}-\d{2}$/.test(candidate)) {
            return candidate;
        }
    } catch (e) {
        // ignore invalid URL parsing
    }
    return null;
}

// selectedYm defaults to server-provided selected_ym, query string, or current month
const selectedYm = ref(
    page && page.props && page.props.value && page.props.value.selected_ym ? page.props.value.selected_ym : getYmFromUrl() || currentMonth,
);

// keep selectedYm in sync if server props change (Inertia navigation)
watch(
    () => page.props && page.props.value && page.props.value.selected_ym,
    (v) => {
        selectedYm.value = v || getYmFromUrl() || currentMonth;
    },
);

const selectedYmLabel = computed(() => {
    const v = selectedYm.value || currentMonth;
    const [yy, mm] = String(v).split('-');
    if (!yy || !mm) return '作業量分析';
    return `${yy}年${parseInt(mm, 10)}月分`;
});

// client-side sort state: key and direction ('asc'|'desc')
const sortKey = ref('deviation');
const sortDir = ref('desc');

function toggleSort(key) {
    if (sortKey.value === key) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortKey.value = key;
        // default directions: deviation/overall -> desc, name/team -> asc
        if (key === 'deviation' || key === 'overall') sortDir.value = 'desc';
        else sortDir.value = 'asc';
    }
}

function sortRowsGeneric(rows) {
    const key = sortKey.value;
    const dir = sortDir.value === 'asc' ? 1 : -1;
    const copy = [...rows];
    copy.sort((a, b) => {
        if (key === 'deviation') {
            const va = a.deviation === null || typeof a.deviation === 'undefined' ? -Infinity : a.deviation;
            const vb = b.deviation === null || typeof b.deviation === 'undefined' ? -Infinity : b.deviation;
            return (va - vb) * dir;
        }
        if (key === 'overall') {
            const va = Number(a.overall || 0);
            const vb = Number(b.overall || 0);
            return (va - vb) * dir;
        }
        if (key === 'rank') {
            const va = Number(a.rank || 0);
            const vb = Number(b.rank || 0);
            return (va - vb) * dir;
        }
        // string keys: name, team
        const sa = (a[key] || '') + '';
        const sb = (b[key] || '') + '';
        return sa.localeCompare(sb) * dir;
    });
    return copy;
}

function getDeptRows(dept) {
    const base = flattenDepartmentRows(dept).map((r) => ({
        ...r,
        deviation: r.aggregates?.deviation_score ?? null,
        overall: r.aggregates?.points?.overall ?? 0,
    }));

    // compute dense rank by deviation desc (independent of current sort)
    const rankArr = [...base].sort((a, b) => {
        const da = a.deviation === null || typeof a.deviation === 'undefined' ? -Infinity : a.deviation;
        const db = b.deviation === null || typeof b.deviation === 'undefined' ? -Infinity : b.deviation;
        if (db === da) return (b.overall || 0) - (a.overall || 0);
        return db - da;
    });
    const rankMap = new Map();
    let currentRank = 0;
    let prevDev = null;
    for (let i = 0; i < rankArr.length; i++) {
        const it = rankArr[i];
        if (prevDev === null || it.deviation !== prevDev) {
            currentRank += 1;
            prevDev = it.deviation;
        }
        rankMap.set(it.id, currentRank);
    }

    // apply ranks
    base.forEach((it) => {
        it.rank = rankMap.get(it.id) || 0;
    });

    // then sort according to UI sort state
    const sorted = sortRowsGeneric(base);
    return sorted;
}

// determine role-based prefix for routes (fallback to leader)
// be defensive: page.props or page.props.value may be undefined during hydration
const authUser = page?.props?.value?.auth?.user || page?.props?.value?.user || {};
const userRole = authUser?.user_role || authUser?.role || (authUser?.roles && authUser.roles[0]) || '';
const rolePrefix = (() => {
    const r = String(userRole || '').toLowerCase();
    if (r.includes('super')) return '/superadmin';
    if (r.includes('admin')) return '/admin';
    // leader, coordinator, default -> leader
    return '/leader';
})();
const routeNamePrefix = (() => {
    const r = String(userRole || '').toLowerCase();
    if (r.includes('super')) return 'superadmin';
    if (r.includes('admin')) return 'admin';
    return 'leader';
})();

// build a small list of months around current month (last 12 months)
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
        const routeName = `${routeNamePrefix}.workload_analyzer.index`;
        Inertia.get(route(routeName, { ym }));
        return;
    } catch (e) {
        // fall back to a full reload if Ziggy route helper fails
    }
    const url = new URL(window.location.href);
    url.searchParams.set('ym', ym);
    window.location.href = url.toString();
}

// Flatten company structure into rows for table display
function flattenCompanyRows(company) {
    const rows = [];
    const seen = new Set();
    (company.departments || []).forEach((d) => {
        // department-level members
        (d.members || []).forEach((m) => {
            const key = `u:${m.id}`;
            if (seen.has(key)) return;
            seen.add(key);
            rows.push({ id: m.id, name: m.name, department: d.name, team: null, aggregates: m.aggregates || {} });
        });

        // team-level members
        (d.teams || []).forEach((t) => {
            (t.members || []).forEach((m) => {
                const key = `u:${m.id}`;
                if (seen.has(key)) return;
                seen.add(key);
                rows.push({ id: m.id, name: m.name, department: d.name, team: t.name, aggregates: m.aggregates || {} });
            });
        });
    });
    return rows;
}

// Flatten a single department into table rows (department column not needed)
function flattenDepartmentRows(dept) {
    const rows = [];
    const seen = new Set();
    // department-level members (not in a team)
    (dept.members || []).forEach((m) => {
        const key = `u:${m.id}`;
        if (seen.has(key)) return;
        seen.add(key);
        rows.push({ id: m.id, name: m.name, team: null, aggregates: m.aggregates || {} });
    });
    // team members
    (dept.teams || []).forEach((t) => {
        (t.members || []).forEach((m) => {
            const key = `u:${m.id}`;
            if (seen.has(key)) return;
            seen.add(key);
            rows.push({ id: m.id, name: m.name, team: t.name, aggregates: m.aggregates || {} });
        });
    });
    return rows;
}

function onMemberRowClick(row) {
    const ym = selectedYm.value || currentMonth;
    const routeParams = { user: row.id, ym };
    // try named route via Ziggy; ensures the clicked month is preserved in the query string
    try {
        const prefix = rolePrefix.replace(/^\//, '');
        try {
            Inertia.get(route(`${prefix}.workload_analyzer.show`, routeParams));
            return;
        } catch (e) {
            // fallback to classic navigation below
        }
    } catch (e) {
        // ignore and fall back
    }
    const base = rolePrefix || '/leader';
    const encodedYm = encodeURIComponent(ym);
    window.location.href = `${base}/workload-analyzer/${row.id}?ym=${encodedYm}`;
}

function formatHour(h) {
    if (typeof h === 'undefined' || h === null) return 0;
    const n = Number(h) || 0;
    // show with one decimal if needed
    return Number.isInteger(n) ? String(n) : String(Math.round(n * 10) / 10);
}

function diffMinutes(estimated, actual) {
    const e = Number(estimated) || 0;
    const a = Number(actual) || 0;
    const diffHours = e - a; // hours
    const diffMinutes = Math.round(diffHours * 60);
    return diffMinutes;
}
</script>

<template>
    <AppLayout title="作業量分析">
        <template #header>
            <div class="flex items-center gap-2.5">
                <h1 class="text-2xl font-semibold">作業量分析</h1>
                <h2 class="text-lg text-gray-600">{{ selectedYmLabel }}</h2>
            </div>
        </template>

        <!-- settings button slot -->
        <template #headerExtras>
            <div class="flex items-center">
                <a
                    href="/leader/workload-analyzer/settings"
                    class="ml-4 inline-flex items-center rounded bg-gray-100 px-3 py-1 text-sm text-gray-700 hover:bg-gray-200"
                >
                    <svg class="mr-2 h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden>
                        <path
                            d="M12 15.5a3.5 3.5 0 100-7 3.5 3.5 0 000 7z"
                            stroke="currentColor"
                            stroke-width="1.5"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                        <path
                            d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 01-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09a1.65 1.65 0 00-1-1.51 1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09a1.65 1.65 0 001.51-1 1.65 1.65 0 00-.33-1.82L3.31 4.91a2 2 0 012.83-2.83l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09c.2.73.76 1.32 1.51 1.51h.18c.7.13 1.33-.2 1.82-.33l.06-.06A2 2 0 0120.69 4.9l-.06.06c-.36.36-.55.86-.55 1.39v.11c0 .65.38 1.24 1 1.51H21a2 2 0 010 4h-.09c-.63.27-1 .86-1 1.51v.11c0 .53.19 1.03.55 1.39l.06.06a2 2 0 01-.33 2.83l-.06.06c-.36.36-.86.55-1.39.55h-.11c-.65 0-1.24-.38-1.51-1H15.5c-.53 0-1.03.19-1.39.55l-.06.06a2 2 0 01-2.83.33l-.06-.06c-.36-.36-.55-.86-.55-1.39v-.11c0-.65.38-1.24 1-1.51h.11c.7-.13 1.33.2 1.82.33l.06.06A1.65 1.65 0 0012 15.5z"
                            stroke="currentColor"
                            stroke-width="1"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                    </svg>
                    設定
                </a>
            </div>
        </template>

        <Head title="作業量分析" />

        <div class="rounded bg-white p-6 shadow">
            <p class="text-sm text-gray-500">自分の会社・部署・チームのメンバーごとの簡易分析ビュー（プレースホルダ）</p>

            <div class="mb-4 mt-4 flex items-center justify-between">
                <div>
                    <label class="mr-2 text-sm text-gray-600">年月:</label>
                    <select class="w-40 rounded border px-3 py-1 text-sm" v-model="selectedYm" @change="changeYm">
                        <option v-for="m in months" :key="m.value" :value="m.value">{{ m.label }}</option>
                    </select>
                </div>
                <div class="text-sm text-gray-500">表示中: {{ selectedYm }}</div>
            </div>

            <div class="space-y-8">
                <div v-for="company in companies" :key="`company-${company.id}`" class="rounded-lg border p-4">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="text-lg font-medium">{{ company.name }}</h2>
                        <div class="text-sm text-gray-500">会社</div>
                    </div>

                    <!-- Render each department as a subheading with its own table -->
                    <template v-if="(company.departments || []).length">
                        <div class="space-y-4">
                            <div v-for="dept in company.departments" :key="`dept-${dept.id}`">
                                <h3 class="text-sm font-medium text-gray-700">部署: {{ dept.name }}</h3>
                                <div class="mt-2 overflow-hidden bg-white shadow sm:rounded-lg">
                                    <table class="w-full min-w-full table-fixed divide-y divide-gray-200">
                                        <thead>
                                            <tr>
                                                <!-- widths: 順位 1/9, 名前 2/9, チーム 2/9, 偏差値 1/9, 総合ポイント 1/9, 内容 2/9 -->
                                                <th
                                                    style="width: 8%"
                                                    class="cursor-pointer px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                                                    @click="toggleSort('rank')"
                                                >
                                                    順位
                                                    <svg
                                                        class="ml-1 inline-block h-3 w-3 text-gray-400"
                                                        viewBox="0 0 24 24"
                                                        fill="none"
                                                        xmlns="http://www.w3.org/2000/svg"
                                                        aria-hidden
                                                    >
                                                        <path
                                                            d="M6 9l6 6 6-6"
                                                            stroke="currentColor"
                                                            stroke-width="2"
                                                            stroke-linecap="round"
                                                            stroke-linejoin="round"
                                                        />
                                                    </svg>
                                                </th>
                                                <th
                                                    style="width: 16%"
                                                    class="cursor-pointer px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                                                    @click="toggleSort('name')"
                                                >
                                                    名前
                                                    <span v-if="sortKey === 'name'">{{ sortDir === 'asc' ? '▲' : '▼' }}</span>
                                                </th>
                                                <th
                                                    style="width: 12%"
                                                    class="cursor-pointer px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                                                    @click="toggleSort('team')"
                                                >
                                                    チーム
                                                    <span v-if="sortKey === 'team'">{{ sortDir === 'asc' ? '▲' : '▼' }}</span>
                                                </th>
                                                <th
                                                    style="width: 14%"
                                                    class="cursor-pointer px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                                                    @click="toggleSort('deviation')"
                                                >
                                                    偏差値
                                                    <span v-if="sortKey === 'deviation'">{{ sortDir === 'asc' ? '▲' : '▼' }}</span>
                                                </th>
                                                <th
                                                    style="width: 14%"
                                                    class="cursor-pointer px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                                                    @click="toggleSort('overall')"
                                                >
                                                    総合ポイント
                                                    <span v-if="sortKey === 'overall'">{{ sortDir === 'asc' ? '▲' : '▼' }}</span>
                                                </th>
                                                <th
                                                    style="width: 44%"
                                                    class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                                                >
                                                    内容
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200 bg-white">
                                            <template v-for="row in getDeptRows(dept)" :key="`m-${row.id}-${row.team || 'dept'}`">
                                                <tr class="cursor-pointer transition-colors hover:bg-green-50" @click="onMemberRowClick(row)">
                                                    <td style="width: 8%" class="px-6 py-4 text-sm font-medium text-gray-900">
                                                        {{ row.rank }}
                                                    </td>
                                                    <td style="width: 16%" class="max-w-[160px] truncate px-6 py-4 text-sm font-medium text-gray-900">
                                                        {{ row.name }}
                                                    </td>
                                                    <td style="width: 12%" class="max-w-[120px] truncate px-6 py-4 text-sm text-gray-500">
                                                        {{ row.team || '' }}
                                                    </td>
                                                    <td style="width: 8%" class="px-6 py-4 text-sm text-gray-500">
                                                        {{ row.deviation ?? '-' }}
                                                    </td>
                                                    <td style="width: 14%" class="px-6 py-4 text-sm text-gray-500">{{ row.overall }}</td>
                                                    <td style="width: 44%" class="hidden px-6 py-4 text-sm text-gray-500 sm:table-cell">
                                                        <div class="text-xs text-gray-600">
                                                            <div>ステージ合計: {{ row.aggregates?.points?.stage ?? 0 }} ポイント</div>
                                                            <div>サイズ合計: {{ row.aggregates?.points?.size ?? 0 }} ポイント</div>
                                                            <div>種別合計: {{ row.aggregates?.points?.type ?? 0 }} ポイント</div>
                                                            <div>難易度合計: {{ row.aggregates?.points?.difficulty ?? 0 }} ポイント</div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </template>
                                            <tr v-if="flattenDepartmentRows(dept).length === 0">
                                                <td colspan="6" class="px-6 py-4 text-sm text-gray-500">メンバーが見つかりません</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </template>

                    <div v-if="!(company.departments || []).length" class="text-sm text-gray-500">部署データがありません。</div>
                </div>

                <div v-if="!companies.length" class="text-sm text-gray-500">会社データがありません。</div>
            </div>
        </div>
    </AppLayout>
</template>
