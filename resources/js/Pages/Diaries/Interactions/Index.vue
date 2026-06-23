<script setup>
import DiaryTable from '@/Components/Diaries/DiaryTable.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Inertia } from '@inertiajs/inertia';
import { Link } from '@inertiajs/vue3';
import { computed, ref, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';

function formatDate(d) {
    if (!d) return '不明';
    // 月別グループキー "YYYY-MM" の場合は "YYYY年M月" として表示
    const monthOnly = d.match(/^(\d{4})-(\d{2})$/);
    if (monthOnly) {
        return `${monthOnly[1]}年${parseInt(monthOnly[2])}月`;
    }
    const dt = new Date(d);
    if (isNaN(dt.getTime())) return d;
    const y = dt.getFullYear();
    const m = String(dt.getMonth() + 1).padStart(2, '0');
    const da = String(dt.getDate()).padStart(2, '0');
    return `${y}/${m}/${da}`;
}

// This component unifies Admin and Leader index pages.
// Controllers should pass `routePrefix` ('admin' or 'leader') and optional `pageTitle`/`headerTitle`.
const props = defineProps({
    noCompanySelected: { type: Boolean, default: false },
    departments: Array,
    date: String,
    meta: Object,
    filters: Object,
    routePrefix: { type: String, default: 'diaries' },
    pageTitle: { type: String, default: '日報一覧' },
    headerTitle: { type: String, default: '日報一覧' },
});

// For index view we intentionally remove any `unread` flag before passing filters
// to DiaryTable so the index's table does not behave as an unread-only view.
const tableFilters = computed(() => {
    const f = props.filters || {};
    // shallow copy without unread
    const { unread, ...rest } = f;
    return rest;
});

// viewMode: 'day' or 'month' — default from query (props.filters.group) or 'day'
const viewMode = ref(props.filters && props.filters.group === 'month' ? 'month' : 'day');

// 部署フィルター（null = 全部署）
const selectedDept = ref(null);
// ページ遷移のたびにリセット
watch(() => props.filters, () => { selectedDept.value = null; });

// 年月セレクター
const _now = new Date();
const _currentYear = _now.getFullYear();
const _currentMonth = _now.getMonth() + 1;

function _buildPeriodValue(y, m) {
    return y && m ? `${y}-${String(m).padStart(2, '0')}` : 'all';
}

const selectedPeriod = ref(
    props.filters && props.filters.year && props.filters.month
        ? _buildPeriodValue(props.filters.year, props.filters.month)
        : props.filters && props.filters.period === 'all'
        ? 'all'
        : _buildPeriodValue(_currentYear, _currentMonth),
);

// 全期間 + 過去36か月を降順で生成
const periodOptions = (() => {
    const opts = [{ value: 'all', label: '全期間' }];
    let y = _currentYear,
        m = _currentMonth;
    for (let i = 0; i < 36; i++) {
        opts.push({ value: `${y}-${String(m).padStart(2, '0')}`, label: `${y}年${m}月` });
        m--;
        if (m < 1) {
            m = 12;
            y--;
        }
    }
    return opts;
})();

function getPeriodParams() {
    if (selectedPeriod.value !== 'all') {
        const [y, mo] = selectedPeriod.value.split('-');
        return { year: y, month: String(parseInt(mo)) };
    }
    return { period: 'all' };
}

function onPeriodChange() {
    const params = { ...getPeriodParams(), page: 1 };
    if (viewMode.value === 'month') params.group = 'month';
    if (searchQuery.value) params.q = searchQuery.value;
    Inertia.get(route(routeForIndex(), params));
}

// search query (shared across all date groups)
const searchQuery = ref(props.filters && props.filters.q ? props.filters.q : '');

// 全日記から一意な部署リストを算出
const allDepts = computed(() => {
    const set = new Set();
    (props.departments || []).forEach((group) => {
        (group.diaries || []).forEach((d) => {
            const dept = (d.department) || (d.user && d.user.department && d.user.department.name) || '';
            if (dept) set.add(dept);
        });
    });
    return Array.from(set).sort();
});

const deptCounts = computed(() => {
    const counts = {};
    (props.departments || []).forEach((group) => {
        (group.diaries || []).forEach((d) => {
            const dept = d.department || (d.user && d.user.department && d.user.department.name) || '未所属';
            counts[dept] = (counts[dept] || 0) + 1;
        });
    });
    return counts;
});

const totalDiaryCount = computed(() => Object.values(deptCounts.value).reduce((sum, count) => sum + count, 0));

const groupedByDepartment = computed(() => {
    const departments = {};
    (props.departments || []).forEach((group) => {
        (group.diaries || []).forEach((d) => {
            const deptName = d.department || (d.user && d.user.department && d.user.department.name) || '未所属';
            if (selectedDept.value) {
                if (deptName !== selectedDept.value) return;
            }
            const raw = d.date || '不明';
            const date = viewMode.value === 'month' ? raw.slice(0, 7) : raw;
            if (!departments[deptName]) departments[deptName] = {};
            if (!departments[deptName][date]) departments[deptName][date] = [];
            departments[deptName][date].push(d);
        });
    });

    return Object.keys(departments)
        .sort((a, b) => {
            if (a === '未所属') return 1;
            if (b === '未所属') return -1;
            return a.localeCompare(b, 'ja');
        })
        .map((department) => {
            const dateGroups = departments[department];
            const buckets = Object.keys(dateGroups)
                .sort((a, b) => (a < b ? 1 : a > b ? -1 : 0))
                .map((date) => {
                    const diaries = dateGroups[date].slice().sort((a, b) => {
                        const dateCompare = String(b.date || '').localeCompare(String(a.date || ''));
                        return dateCompare !== 0 ? dateCompare : Number(b.id || 0) - Number(a.id || 0);
                    });

                    return { date, diaries };
                });

            return {
                department,
                buckets,
                total: buckets.reduce((sum, bucket) => sum + bucket.diaries.length, 0),
            };
        });
});

const hasGroupedDiaries = computed(() => groupedByDepartment.value.some((group) => group.total > 0));

const dateLinkParams = (date) => {
    return { date };
};

const canOpenDateDetail = (date) => {
    return viewMode.value === 'day' && /^\d{4}-\d{2}-\d{2}$/.test(date);
};

function applyFilters() {
    const params = { ...getPeriodParams(), page: 1 };
    if (viewMode.value === 'month') params.group = 'month';
    else delete params.group;
    if (searchQuery.value) params.q = searchQuery.value;
    else delete params.q;
    Inertia.get(route(routeForIndex(), params));
}

function routeForIndex() {
    const prefix = props.routePrefix || 'diaries';
    if (prefix === 'diaries') return 'diaryinteractions.index';
    return `${prefix}.diaryinteractions.index`;
}

function markReadAllRoute() {
    const prefix = props.routePrefix || 'diaries';
    if (prefix === 'diaries') return 'diaryinteractions.mark_read_all';
    return `${prefix}.diaryinteractions.mark_read_all`;
}

// スクロール保存関数（SPA遷移・フルリロード両対応）
// - SPA遷移（Inertia）: onBeforeUnmount で呼ばれる
// - フルリロード（window.location.href）: pagehide イベントで呼ばれる
//   ※ DiaryTable の Inertia.get() は v0 が未初期化のため例外を投げ、
//      フォールバックの window.location.href でナビゲートされる。
//      フルリロード時は Vue が破棄されるため onBeforeUnmount は呼ばれない。
const saveScrollForReturn = () => {
    sessionStorage.setItem('diary_scroll_restore', String(window.scrollY));
};

// このページが表示された瞬間に URL を保存（onBeforeUnmount では URL が変わっている）
onMounted(() => {
    sessionStorage.setItem('diary_index_url', window.location.href);

    // フルリロードで離脱するときのために pagehide を登録
    window.addEventListener('pagehide', saveScrollForReturn);

    // 既読アクション後に戻った場合のスクロール位置復元
    const returnFlag = sessionStorage.getItem('diary_markread_return');
    const savedScroll = sessionStorage.getItem('diary_scroll_restore');
    if (returnFlag && savedScroll !== null) {
        sessionStorage.removeItem('diary_markread_return');
        sessionStorage.removeItem('diary_scroll_restore');
        nextTick(() => {
            requestAnimationFrame(() => {
                window.scrollTo({ top: parseInt(savedScroll, 10), behavior: 'instant' });
            });
        });
    }
});

// SPA 遷移で離脱するときのスクロール保存（フルリロード時は pagehide が担う）
onBeforeUnmount(() => {
    saveScrollForReturn();
    window.removeEventListener('pagehide', saveScrollForReturn);
});
</script>

<template>
    <AppLayout :title="props.pageTitle">
        <template #header>
            <div v-if="props.date" class="flex items-center gap-3">
                <Link
                    :href="route(routeForIndex())"
                    class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 whitespace-nowrap hover:bg-gray-300"
                >← 日報一覧に戻る</Link>
                <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">{{ props.headerTitle }}</h2>
            </div>
            <h2 v-else class="text-base sm:text-xl font-semibold leading-tight text-gray-800">{{ props.headerTitle }}</h2>
        </template>

        <!-- SuperAdmin グローバルモード警告 -->
        <div v-if="props.noCompanySelected" class="rounded border border-yellow-300 bg-yellow-50 p-6 shadow">
            <div class="flex items-start gap-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 h-5 w-5 shrink-0 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>
                <div>
                    <p class="font-semibold text-yellow-800">会社が選択されていません</p>
                    <p class="mt-1 text-sm text-yellow-700">右上の会社コンテキスト切り替えで表示したい会社を選択してから、このページを開いてください。</p>
                </div>
            </div>
        </div>

        <div v-else class="rounded bg-white px-4 py-6 sm:p-6 shadow">
            <!-- 部署フィルターボタン -->
            <div v-if="allDepts.length > 0" class="mb-4 flex flex-wrap gap-2">
                <button
                    @click="selectedDept = null"
                    :class="['rounded px-3 py-1 text-sm font-medium transition-colors', selectedDept === null ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200']"
                >全部署 ({{ totalDiaryCount }})</button>
                <button
                    v-for="dept in allDepts"
                    :key="dept"
                    @click="selectedDept = selectedDept === dept ? null : dept"
                    :class="['rounded px-3 py-1 text-sm font-medium transition-colors', selectedDept === dept ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200']"
                >{{ dept }} ({{ deptCounts[dept] || 0 }})</button>
            </div>

            <div v-if="selectedDept === null && allDepts.length > 1" class="mb-4 rounded border border-indigo-100 bg-indigo-50 px-3 py-2 text-sm text-indigo-900">
                <span class="font-semibold">全部署:</span>
                <span class="ml-2">
                    <template v-for="(dept, index) in allDepts" :key="dept">
                        <span>{{ dept }} {{ deptCounts[dept] || 0 }}件</span><span v-if="index < allDepts.length - 1"> / </span>
                    </template>
                </span>
            </div>

            <div class="mb-4 flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <input
                        v-model="searchQuery"
                        type="search"
                        placeholder="検索 (ID/名前/部署/内容)"
                        class="w-56 rounded border px-2 py-1 text-sm"
                        @keydown.enter.prevent="applyFilters"
                    />

                    <label class="text-sm">表示:</label>
                    <select v-model="viewMode" class="w-40 rounded border px-2 py-1 text-sm">
                        <option value="day">日別表示</option>
                        <option value="month">月別に表示</option>
                    </select>

                    <label class="text-sm">期間:</label>
                    <select v-model="selectedPeriod" class="rounded border px-2 py-1 text-sm" @change="onPeriodChange">
                        <option v-for="opt in periodOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                    </select>

                    <button @click.prevent="applyFilters" class="ml-2 rounded bg-indigo-600 px-3 py-1 text-xs text-white">適用</button>
                </div>
            </div>

            <div v-if="!hasGroupedDiaries" class="rounded border border-gray-200 bg-gray-50 px-4 py-6 text-sm text-gray-600">
                日報はありません
            </div>

            <div v-for="departmentGroup in groupedByDepartment" :key="departmentGroup.department" class="mb-10">
                <div class="mb-4 flex items-center gap-3 border-b border-gray-200 pb-2">
                    <h3 class="text-lg font-bold text-gray-800">{{ departmentGroup.department }}</h3>
                    <span class="rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">{{ departmentGroup.total }}件</span>
                </div>

                <div v-for="bucket in departmentGroup.buckets" :key="`${departmentGroup.department}-${bucket.date}`" class="mb-6">
                    <div class="mb-2">
                        <h4 class="flex items-center gap-2 text-base font-bold text-gray-700">
                            <span>{{ formatDate(bucket.date) }}</span>
                            <Link
                                v-if="canOpenDateDetail(bucket.date)"
                                :href="route(routeForIndex(), dateLinkParams(bucket.date))"
                                class="inline-flex items-center rounded border bg-white px-2 py-1 text-xs hover:bg-gray-50"
                                aria-label="全件表示"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke-width="2"
                                    stroke="currentColor"
                                    class="mr-1 h-4 w-4"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                                </svg>
                                <span class="text-xs">全件表示</span>
                            </Link>

                            <button
                                v-if="props.date === bucket.date"
                                @click.prevent="() => Inertia.post(route(markReadAllRoute()), { date: bucket.date })"
                                class="ml-2 inline-flex items-center rounded bg-indigo-600 px-2 py-1 text-xs text-white hover:bg-indigo-700"
                            >
                                全部既読にする
                            </button>
                        </h4>
                    </div>
                    <DiaryTable
                        :diaries="bucket.diaries"
                        :routePrefix="props.routePrefix"
                        :serverMode="true"
                        :meta="null"
                        :filters="tableFilters"
                        :searchable="false"
                        :maxDescriptionLines="1"
                        :showUnreadToggle="false"
                        :hidePagination="true"
                        :fullContent="props.date === bucket.date"
                        :useInteractionRoutes="true"
                        :showIdColumn="false"
                        :showDeptColumn="false"
                    />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
