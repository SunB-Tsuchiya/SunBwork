<script setup>
import DiaryTable from '@/Components/Diaries/DiaryTable.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Inertia } from '@inertiajs/inertia';
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

function formatDate(d) {
    if (!d) return '不明';
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
    departments: Array,
    date: String,
    meta: Object,
    filters: Object,
    routePrefix: { type: String, default: 'diaries' },
    pageTitle: { type: String, default: '日報一覧' },
    headerTitle: { type: String, default: '日報一覧' },
});
const selectedDate = ref(props.date || null);

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
// days to show (default from props.filters.days or 30)
const selectedDays = ref(props.filters && props.filters.days ? Number(props.filters.days) : 30);

const groupedByDate = computed(() => {
    const map = {};
    (props.departments || []).forEach((group) => {
        (group.diaries || []).forEach((d) => {
            const raw = d.date || '不明';
            const date = viewMode.value === 'month' ? raw.slice(0, 7) : raw;
            if (!map[date]) map[date] = [];
            map[date].push(d);
        });
    });
    Object.keys(map).forEach((k) => {
        map[k].sort((a, b) => b.id - a.id);
    });
    const ordered = {};
    Object.keys(map)
        .sort((a, b) => (a < b ? 1 : a > b ? -1 : 0))
        .forEach((k) => {
            ordered[k] = map[k];
        });
    return ordered;
});

function routeForIndex(date) {
    // Build the correct route name for the interactions index depending on prefix
    const prefix = props.routePrefix || 'diaries';
    if (prefix === 'diaries') return 'diaryinteractions.interactions.index';
    // admin/leader routes use names like 'admin.diaryinteractions.index'
    return `${prefix}.diaryinteractions.index`;
}

// pagination helpers using props.meta provided by controller
const currentPage = computed(() => (props.meta && props.meta.current_page ? props.meta.current_page : 1));
const lastPage = computed(() => (props.meta && props.meta.last_page ? props.meta.last_page : 1));
const pageRange = computed(() => {
    const cur = Number(currentPage.value || 1);
    const last = Number(lastPage.value || 1);
    const range = [];
    const start = Math.max(1, cur - 2);
    const end = Math.min(last, cur + 2);
    for (let i = start; i <= end; i++) range.push(i);
    return range;
});

function pageRoute(n) {
    // build params from tableFilters (which excludes unread) and set page
    const params = Object.assign({}, tableFilters.value || {});
    params.page = n;
    return route(routeForIndex(), params);
}

function applyFilters() {
    const params = Object.assign({}, tableFilters.value || {});
    params.days = selectedDays.value;
    if (viewMode.value === 'month') params.group = 'month';
    else delete params.group;
    params.page = 1;
    Inertia.get(route(routeForIndex(), params));
}

function markReadAllRoute() {
    const prefix = props.routePrefix || 'diaries';
    if (prefix === 'diaries') return 'diaryinteractions.mark_read_all';
    return `${prefix}.diaryinteractions.mark_read_all`;
}
</script>

<template>
    <AppLayout :title="props.pageTitle">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ props.headerTitle }}</h2>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="mb-4 flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <label class="text-sm">表示:</label>
                        <select v-model="viewMode" class="rounded border px-2 py-1 text-sm">
                            <option value="day">日別表示</option>
                            <option value="month">月別に表示</option>
                        </select>

                        <label class="text-sm">期間:</label>
                        <select v-model.number="selectedDays" class="rounded border px-2 py-1 text-sm">
                            <option :value="7">7日分を表示</option>
                            <option :value="30">30日分を表示</option>
                            <option :value="90">90日分を表示</option>
                        </select>

                        <button @click.prevent="applyFilters" class="ml-2 rounded bg-blue-600 px-3 py-1 text-xs text-white">適用</button>
                    </div>
                </div>

                <div v-for="(list, date) in groupedByDate" :key="date" class="mb-8">
                    <div class="mb-2">
                        <h3 class="flex items-center gap-2 text-lg font-bold">
                            <span>{{ formatDate(date) }}</span>
                            <Link
                                :href="route(routeForIndex(date), { date: date })"
                                class="inline-flex items-center rounded border bg-white px-2 py-1 text-xs hover:bg-gray-50"
                                aria-label="日付別表示へ"
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
                                <span class="text-xs">一覧を見る</span>
                            </Link>

                            <button
                                v-if="props.date === date"
                                @click.prevent="() => Inertia.post(route(markReadAllRoute()), { date: date })"
                                class="ml-2 inline-flex items-center rounded bg-blue-600 px-2 py-1 text-xs text-white hover:bg-blue-700"
                            >
                                全部既読にする
                            </button>
                        </h3>
                    </div>
                    <DiaryTable
                        :diaries="list"
                        :routePrefix="props.routePrefix"
                        :serverMode="true"
                        :meta="props.meta"
                        :filters="tableFilters"
                        :maxDescriptionLines="1"
                        :showUnreadToggle="false"
                        :fullContent="props.date === date"
                        :useInteractionRoutes="true"
                    />
                </div>
                <!-- pagination -->
                <div class="mt-6 flex items-center justify-between">
                    <div>
                        <button
                            class="mr-2 rounded border px-3 py-1"
                            :disabled="currentPage <= 1"
                            @click.prevent="() => Inertia.get(pageRoute(Math.max(1, currentPage - 1)))"
                        >
                            前
                        </button>
                        <button
                            class="rounded border px-3 py-1"
                            :disabled="currentPage >= lastPage"
                            @click.prevent="() => Inertia.get(pageRoute(Math.min(lastPage, currentPage + 1)))"
                        >
                            次
                        </button>
                    </div>
                    <div class="text-sm text-gray-600">
                        ページ: <span class="font-medium">{{ currentPage }}</span> / {{ lastPage }}
                    </div>
                    <div class="space-x-1">
                        <template v-for="p in pageRange" :key="p">
                            <button
                                @click.prevent="() => Inertia.get(pageRoute(p))"
                                :class="['rounded px-2 py-1', p === currentPage ? 'bg-blue-600 text-white' : 'border']"
                            >
                                {{ p }}
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
