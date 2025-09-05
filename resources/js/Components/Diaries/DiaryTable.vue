<script setup>
import { Link } from '@inertiajs/vue3';
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

const getDept = (d) => {
    // prefer user->department.name if available (server returns diaries with nested user)
    if (d && d.user && d.user.department && d.user.department.name) return d.user.department.name;
    if (d && (d.department || d.department_name)) return d.department || d.department_name;
    return '';
};

const filtered = computed(() => {
    if (props.serverMode) return props.diaries || [];
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

const totalPages = computed(() => {
    if (props.serverMode && props.meta) return Math.max(1, props.meta.last_page || 1);
    return Math.max(1, Math.ceil(filtered.value.length / Math.max(1, props.pageSize)));
});

const page = computed({
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
    if (props.serverMode) return props.diaries || [];
    const size = Math.max(1, props.pageSize);
    const start = (internalPage.value - 1) * size;
    return filtered.value.slice(start, start + size);
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
    if (internalPage.value < totalPages.value) internalPage.value += 1;
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
onMounted(() => {});

watch(
    () => props.filters && props.filters.q,
    (v) => {
        if (!props.serverMode) return;
        searchTerm.value = v || '';
    },
);

// debug: log props on mount and when key props change so we can inspect in browser console
onMounted(() => {
    try {
        console.log('[DiaryTable] mounted props:', {
            serverMode: props.serverMode,
            meta: props.meta,
            filters: props.filters,
            diariesSample: (props.diaries || []).slice(0, 5),
            showCheckboxes: props.showCheckboxes,
        });
    } catch (e) {
        console.log('[DiaryTable] mounted props error', e);
    }
});

watch(
    () => props.diaries,
    (v) => {
        console.log('[DiaryTable] props.diaries changed:', Array.isArray(v) ? v.length : v, v && v.slice ? v.slice(0, 5) : v);
    },
    { deep: true },
);

watch(
    () => props.meta,
    (v) => console.log('[DiaryTable] props.meta changed:', v),
);

watch(
    () => props.filters,
    (v) => console.log('[DiaryTable] props.filters changed:', v),
    { deep: true },
);
</script>

<template>
    <div>
        <div v-if="props.searchable" class="mb-3 flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <input v-model="searchTerm" type="search" placeholder="検索 (ID/名前/部署/内容)" class="w-80 rounded border px-3 py-2" />

                <!-- Client-side unread-only checkbox -->
                <label v-if="!props.serverMode" class="inline-flex items-center space-x-2 text-sm text-gray-700">
                    <input type="checkbox" v-model="unreadOnly" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                    <span>未読のみ</span>
                </label>

                <!-- Server-mode unread toggle: link that toggles unread param -->
                <div v-else class="text-sm">
                    <Link
                        :href="
                            route(`${routePrefix}.diaries.index`, {
                                q: props.filters && props.filters.q,
                                days: props.filters && props.filters.days,
                                perPage: props.filters && props.filters.perPage,
                                unread: serverUnread ? 0 : 1,
                            })
                        "
                        class="rounded border bg-white px-2 py-1 text-xs hover:bg-gray-50"
                    >
                        {{ serverUnread ? '全てを表示' : '未読のみ' }}
                    </Link>
                </div>
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
                        <th class="w-12 px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">ID</th>
                        <!-- 名前/部署をさらに狭く (レスポンシブ: sm:w-24, md:w-32) -->
                        <th class="w-24 px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 sm:w-24 md:w-32">名前</th>
                        <th class="w-24 px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 sm:w-24 md:w-32">部署</th>
                        <!-- 内容列は幅を固定しない（残りスペースを使用）して折り返す -->
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">内容</th>
                        <th class="w-20 px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">既読</th>
                        <th class="w-20 px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    <tr v-for="d in paginated" :key="d.id">
                        <td v-if="props.showCheckboxes" class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                            <input type="checkbox" :value="d.id" v-model="selected" />
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">{{ d.id }}</td>
                        <td class="truncate px-6 py-4 text-sm font-medium text-gray-900">{{ d.name }}</td>
                        <td class="truncate px-6 py-4 text-sm text-gray-500">{{ getDept(d) }}</td>
                        <!-- Allow description to wrap vertically; add max-height and a "もっと見る" toggle for long content -->
                        <td class="whitespace-normal break-words px-4 py-4 text-sm text-gray-500">
                            <div v-if="props.fullContent">
                                {{ d.content ?? d.description }}
                            </div>
                            <div v-else>
                                <div :class="isExpanded(d.id) ? '' : 'line-clamp-3'">{{ d.description }}</div>
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
                            <span v-if="Array.isArray(d.read_by) && d.read_by.length > 0" class="font-semibold text-green-600">既読</span>
                            <span v-else class="font-semibold text-red-600">未読</span>
                        </td>
                        <td class="px-4 py-4 text-right">
                            <Link :href="route(`${routePrefix}.diaries.show`, d.id)" class="rounded bg-blue-500 px-3 py-1 text-xs text-white"
                                >詳細</Link
                            >
                        </td>
                    </tr>
                    <tr v-if="filtered.length === 0">
                        <td :colspan="props.showCheckboxes ? 7 : 6" class="px-6 py-4 text-sm text-gray-500">日報はありません</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mt-3 flex items-center justify-between">
            <div class="text-sm text-gray-600">合計: {{ filtered.length }} 件</div>
            <div class="flex items-center space-x-2">
                <button @click="prevPage" class="rounded border px-3 py-1" :disabled="page <= 1">前</button>
                <div class="text-sm">{{ page }} / {{ totalPages }}</div>
                <button @click="nextPage" class="rounded border px-3 py-1" :disabled="page >= totalPages">次</button>
            </div>
        </div>
    </div>
</template>

<!-- Rely on Tailwind's line-clamp utility -->
