<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps({
    diaries: { type: Array, default: () => [] },
    showActions: { type: Boolean, default: true },
    routePrefix: { type: String, default: 'admin' },
    searchable: { type: Boolean, default: true },
    pageSize: { type: Number, default: 20 },
    serverMode: { type: Boolean, default: false },
    meta: { type: Object, default: null },
    filters: { type: Object, default: () => ({ q: '', days: 30, perPage: 30 }) },
    showCheckboxes: { type: Boolean, default: false },
    fullContent: { type: Boolean, default: false },
    showUnreadToggle: { type: Boolean, default: true },
    // control how many lines to show for description when not fullContent
    // null = default behavior (1 line)
    maxDescriptionLines: { type: Number, default: null },
    // when true, route names/paths for actions should use the centralized
    // diary interactions routes (diaryinteractions.*) instead of diaries.*
    useInteractionRoutes: { type: Boolean, default: false },
});

const emit = defineEmits(['update:selected', 'selection-change']);

// selected ids for checkbox column (array of ids)
const selected = ref([]);
// expanded rows for "もっと見る" (array of diary ids)
const expanded = ref([]);

// expose selected changes
watch(selected, (v) => {
    emit('update:selected', v.slice());
    emit('selection-change', v.slice());
});

const searchTerm = ref(props.filters && props.filters.q ? props.filters.q : '');
const internalPage = ref(1);
const unreadOnly = ref(false); // client-side toggle

const serverUnread = computed(() => {
    if (!props.serverMode) return false;
    return Boolean(props.filters && (props.filters.unread === 1 || props.filters.unread === '1' || props.filters.unread === true));
});

// compute correct named routes depending on routePrefix
const indexRouteName = computed(() => {
    const p = props.routePrefix || 'diaries';
    if (props.useInteractionRoutes) {
        return p === 'diaries' ? 'diaryinteractions.index' : `${p}.diaryinteractions.index`;
    }
    return p === 'diaries' ? 'diaries.index' : `${p}.diaries.index`;
});

const showRouteName = computed(() => {
    const p = props.routePrefix || 'diaries';
    if (props.useInteractionRoutes) {
        return p === 'diaries' ? 'diaryinteractions.show' : `${p}.diaryinteractions.show`;
    }
    return p === 'diaries' ? 'diaries.show' : `${p}.diaries.show`;
});

const getDept = (d) => {
    // prefer user->department.name if available (server returns diaries with nested user)
    if (d && d.user && d.user.department && d.user.department.name) return d.user.department.name;
    if (d && (d.department || d.department_name)) return d.department || d.department_name;
    return '';
};

function formatMD(dateStr) {
    if (!dateStr) return '';
    try {
        const dt = new Date(dateStr);
        if (!isNaN(dt.getTime())) return `${dt.getMonth() + 1}月${dt.getDate()}日`;
        // fallback: try YYYY-MM-DD or YYYY/MM/DD
        const m = dateStr.match(/(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})/);
        if (m) return `${Number(m[2])}月${Number(m[3])}日`;
        return dateStr;
    } catch (e) {
        return dateStr;
    }
}

const filtered = computed(() => {
    if (props.serverMode) {
        // In serverMode, the parent (ByDate.vue) is responsible for applying
        // unread/read filters. DiaryTable should render the diaries array as
        // provided by the server without re-applying unread filtering here.
        return props.diaries || [];
    }
    const q = (searchTerm.value || '').toLowerCase().trim();
    let list = props.diaries || [];
    if (q) {
        list = list.filter((d) => {
            return (
                String(d.id).includes(q) ||
                (d.name || '').toLowerCase().includes(q) ||
                (getDept(d) || '').toLowerCase().includes(q) ||
                (d.description || '').toLowerCase().includes(q)
            );
        });
    }
    if (unreadOnly.value) {
        list = list.filter((d) => !(Array.isArray(d.read_by) && d.read_by.length > 0));
    }
    return list;
});

// sorting state: key can be 'id', 'name', 'dept', 'read'
const sortKey = ref(null);
const sortDir = ref(1); // 1 = asc, -1 = desc

// expose simple getters for template convenience
const sortKeyRef = computed(() => sortKey.value);
const sortDirRef = computed(() => sortDir.value);

function setSort(key) {
    if (sortKey.value === key) {
        sortDir.value = -sortDir.value;
    } else {
        sortKey.value = key;
        sortDir.value = 1;
    }
}

const sorted = computed(() => {
    const list = (filtered.value || []).slice();
    if (!sortKey.value) return list;
    const k = sortKey.value;
    return list.sort((a, b) => {
        try {
            if (k === 'id') {
                return (Number(a.id) - Number(b.id)) * sortDir.value;
            }
            if (k === 'date') {
                const da = new Date(a.date);
                const db = new Date(b.date);
                if (isNaN(da.getTime()) || isNaN(db.getTime())) return 0;
                return (da.getTime() - db.getTime()) * sortDir.value;
            }
            if (k === 'name') {
                const na = (a.name || '').toLowerCase();
                const nb = (b.name || '').toLowerCase();
                return na === nb ? 0 : (na < nb ? -1 : 1) * sortDir.value;
            }
            if (k === 'dept') {
                const da = (getDept(a) || '').toLowerCase();
                const db = (getDept(b) || '').toLowerCase();
                return da === db ? 0 : (da < db ? -1 : 1) * sortDir.value;
            }
            if (k === 'read') {
                const ra = isReadByCurrentUser(a) ? 1 : 0;
                const rb = isReadByCurrentUser(b) ? 1 : 0;
                return (ra - rb) * sortDir.value;
            }
        } catch (e) {
            return 0;
        }
        return 0;
    });
});

// access Inertia page props to determine current user id
const page = usePage();
function getCurrentUserId() {
    const id = page.props && (page.props.auth?.user?.id || page.props.user?.id);
    return typeof id === 'undefined' ? null : id;
}

function isReadByCurrentUser(d) {
    try {
        const cur = getCurrentUserId();
        if (!cur) return false;
        if (!Array.isArray(d.read_by)) return false;
        // treat optimistic marker as read for current user
        if (d.read_by.includes('optimistic')) return true;
        const curStr = String(cur);
        return d.read_by.some((x) => String(x) === curStr);
    } catch (e) {
        return false;
    }
}

function descriptionClassFor() {
    // default to 1 line when not provided
    const n = typeof props.maxDescriptionLines === 'number' && props.maxDescriptionLines > 0 ? props.maxDescriptionLines : 1;
    if (n === 1) return 'line-clamp-1 whitespace-nowrap overflow-hidden';
    // for multi-line clamp, allow wrapping
    return `line-clamp-${n} break-words overflow-hidden`;
}

const totalPages = computed(() => {
    if (props.serverMode && props.meta) return Math.max(1, props.meta.last_page || 1);
    return Math.max(1, Math.ceil(filtered.value.length / Math.max(1, props.pageSize)));
});

const pagination = computed({
    get() {
        if (props.serverMode && props.meta) return props.meta.current_page || 1;
        return internalPage.value;
    },
    set(v) {
        if (props.serverMode) return; // no-op; server controls page
        internalPage.value = v;
    },
});

const paginated = computed(() => {
    if (props.serverMode) return sorted.value || [];
    const size = Math.max(1, props.pageSize);
    const start = (internalPage.value - 1) * size;
    // apply optimistic reads from sessionStorage
    const page = sorted.value.slice(start, start + size);
    try {
        const key = 'optimistic_reads';
        const cur = JSON.parse(sessionStorage.getItem(key) || '[]');
        if (Array.isArray(cur) && cur.length) {
            return page.map((d) => {
                if ((!Array.isArray(d.read_by) || d.read_by.length === 0) && cur.includes(d.id)) {
                    // shallow clone and set read_by to indicate optimistic read
                    return Object.assign({}, d, { read_by: ['optimistic'] });
                }
                return d;
            });
        }
    } catch (e) {
        // ignore
    }
    return page;
});

const allSelected = computed(() => {
    const page = paginated.value || [];
    if (!page.length) return false;
    return page.every((d) => selected.value.includes(d.id));
});

function toggleSelectAll() {
    const page = paginated.value || [];
    if (allSelected.value) {
        // remove page ids from selected
        selected.value = selected.value.filter((id) => !page.some((d) => d.id === id));
    } else {
        // add page ids
        const ids = page.map((d) => d.id);
        selected.value = Array.from(new Set(selected.value.concat(ids)));
    }
}

function prevPage() {
    if (props.serverMode) return; // navigation handled via links
    if (internalPage.value > 1) internalPage.value -= 1;
}

function nextPage() {
    if (props.serverMode) return;
    if (internalPage.value < totalPages.value) internalPage += 1;
}

function toggleExpand(id) {
    if (expanded.value.includes(id)) {
        expanded.value = expanded.value.filter((i) => i !== id);
    } else {
        expanded.value = [...expanded.value, id];
    }
}

function isExpanded(id) {
    return expanded.value.includes(id);
}

// measure is no longer done in JS; rely on Tailwind's line-clamp utility for truncation.
function cleanupOptimisticReads(diaries) {
    try {
        const key = 'optimistic_reads';
        const cur = JSON.parse(sessionStorage.getItem(key) || '[]');
        if (!Array.isArray(cur) || cur.length === 0) return;
        const serverReadIds = new Set((diaries || []).filter((d) => Array.isArray(d.read_by) && d.read_by.length > 0).map((d) => d.id));
        const remaining = cur.filter((id) => !serverReadIds.has(id));
        if (remaining.length !== cur.length) {
            sessionStorage.setItem(key, JSON.stringify(remaining));
        }
    } catch (e) {
        // ignore any storage errors
    }
}

onMounted(() => {
    // remove optimistic ids that are already confirmed read by server
    cleanupOptimisticReads(props.diaries);
});

watch(
    () => props.filters && props.filters.q,
    (v) => {
        if (!props.serverMode) return;
        searchTerm.value = v || '';
    },
);

// when diaries change, clean up optimistic reads
watch(
    () => props.diaries,
    (v) => {
        cleanupOptimisticReads(v);
    },
    { deep: true },
);
</script>

<template>
    <div>
        <div v-if="props.searchable" class="mb-3 flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <input v-model="searchTerm" type="search" placeholder="検索 (ID/名前/部署/内容)" class="w-80 rounded border px-3 py-2" />
            </div>

            <div class="text-sm text-gray-500">{{ filtered.length }} 件</div>
        </div>

        <div class="overflow-hidden bg-white shadow sm:rounded-lg">
            <!-- Use fixed table layout so column widths are stable and description wraps vertically -->
            <table class="w-full min-w-full table-fixed divide-y divide-gray-200">
                <thead>
                    <tr>
                        <th v-if="props.showCheckboxes" class="w-12 px-3 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                            <input type="checkbox" :checked="allSelected" @change.prevent="toggleSelectAll" />
                        </th>
                        <th class="w-20 px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                            <button class="inline-flex items-center text-xs font-medium" @click.prevent="setSort('date')">
                                <span>日付</span>
                                <span v-if="sortKey === 'date'" class="ml-1 text-xs" aria-hidden>
                                    {{ sortDir === 1 ? '▲' : '▼' }}
                                </span>
                            </button>
                        </th>
                        <th class="w-12 px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                            <button class="inline-flex items-center text-xs font-medium" @click.prevent="setSort('id')">
                                <span>ID</span>
                                <span v-if="sortKey === 'id'" class="ml-1 text-xs" aria-hidden>
                                    {{ sortDir === 1 ? '▲' : '▼' }}
                                </span>
                            </button>
                        </th>
                        <!-- 名前/部署をさらに狭く (レスポンシブ: sm:w-24, md:w-32) -->
                        <th class="w-24 px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 sm:w-24 md:w-32">
                            <button class="inline-flex items-center text-xs font-medium" @click.prevent="setSort('name')">
                                <span>名前</span>
                                <span v-if="sortKey === 'name'" class="ml-1 text-xs" aria-hidden>
                                    {{ sortDir === 1 ? '▲' : '▼' }}
                                </span>
                            </button>
                        </th>
                        <th class="w-24 px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 sm:w-24 md:w-32">
                            <button class="inline-flex items-center text-xs font-medium" @click.prevent="setSort('dept')">
                                <span>部署</span>
                                <span v-if="sortKey === 'dept'" class="ml-1 text-xs" aria-hidden>
                                    {{ sortDir === 1 ? '▲' : '▼' }}
                                </span>
                            </button>
                        </th>
                        <!-- 内容列は幅を固定しない（残りスペースを使用）して折り返す -->
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">内容</th>
                        <th class="w-20 px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                            <button class="inline-flex items-center text-xs font-medium" @click.prevent="setSort('read')">
                                <span>既読</span>
                                <span v-if="sortKey === 'read'" class="ml-1 text-xs" aria-hidden>
                                    {{ sortDir === 1 ? '▲' : '▼' }}
                                </span>
                            </button>
                        </th>
                        <th class="w-20 px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    <tr v-for="d in paginated" :key="d.id">
                        <td v-if="props.showCheckboxes" class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                            <input type="checkbox" :value="d.id" v-model="selected" />
                        </td>
                        <td class="px-3 py-4 text-sm text-gray-500">{{ formatMD(d.date) }}</td>
                        <td class="px-6 py-4 text-sm text-gray-500">{{ d.id }}</td>
                        <td class="truncate px-6 py-4 text-sm font-medium text-gray-900">{{ d.name }}</td>
                        <td class="truncate px-6 py-4 text-sm text-gray-500">{{ getDept(d) }}</td>
                        <!-- Allow single-line truncation on index view; fullContent shows full text -->
                        <td
                            :class="
                                props.fullContent
                                    ? 'whitespace-normal break-words px-4 py-4 text-sm text-gray-500'
                                    : 'overflow-hidden px-4 py-4 text-sm text-gray-500'
                            "
                        >
                            <div v-if="props.fullContent">
                                {{ d.content ?? d.description }}
                            </div>
                            <div v-else>
                                <!-- Use configured line-clamp for truncation; keep expand toggle for long content -->
                                <div :class="isExpanded(d.id) ? '' : descriptionClassFor()">{{ d.description }}</div>
                                <button
                                    v-if="(d.description || '').length > 200"
                                    @click.prevent="toggleExpand(d.id)"
                                    class="mt-1 text-xs text-blue-600"
                                >
                                    {{ isExpanded(d.id) ? '閉じる' : 'もっと見る' }}
                                </button>
                            </div>
                        </td>
                        <td class="px-4 py-4 text-sm text-gray-500">
                            <span v-if="isReadByCurrentUser(d)" class="font-semibold text-green-600">既読</span>
                            <span v-else class="font-semibold text-red-600">未読</span>
                        </td>
                        <td class="px-4 py-4 text-right">
                            <Link :href="route(showRouteName, d.id)" class="rounded bg-blue-500 px-3 py-1 text-xs text-white">詳細</Link>
                        </td>
                    </tr>
                    <tr v-if="filtered.length === 0">
                        <td :colspan="props.showCheckboxes ? 8 : 7" class="px-6 py-4 text-sm text-gray-500">日報はありません</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mt-3 flex items-center justify-between">
            <div class="text-sm text-gray-600">合計: {{ filtered.length }} 件</div>
            <div class="flex items-center space-x-2">
                <button @click="prevPage" class="rounded border px-3 py-1" :disabled="page <= 1">前</button>
                <div class="text-sm">{{ pagination }} / {{ totalPages }}</div>
                <button @click="nextPage" class="rounded border px-3 py-1" :disabled="pagination >= totalPages">次</button>
            </div>
        </div>
    </div>
</template>

<!-- Rely on Tailwind's line-clamp utility -->
