<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted } from 'vue';

const props = defineProps({
    grouped: Array,
});

const LS_KEY = 'announcements_yearMonth';

const searchInput   = ref('');
const appliedSearch = ref('');
const yearMonth     = ref('');

onMounted(() => {
    yearMonth.value = localStorage.getItem(LS_KEY) ?? '';
});

watch(yearMonth, (val) => {
    if (val) localStorage.setItem(LS_KEY, val);
    else localStorage.removeItem(LS_KEY);
});

const yearMonthOptions = computed(() => {
    const months = new Set();
    props.grouped.forEach(g => months.add(g.date.substring(0, 7)));
    return Array.from(months).sort().reverse();
});

const formatYM = (ym) => {
    const [y, m] = ym.split('/');
    return `${y}年${parseInt(m)}月`;
};

const filteredGrouped = computed(() => {
    return props.grouped
        .map(g => {
            if (yearMonth.value && !g.date.startsWith(yearMonth.value)) return null;
            const q = appliedSearch.value;
            const items = q
                ? g.items.filter(i =>
                    i.title.includes(q) ||
                    i.sender.includes(q) ||
                    (i.content ?? '').includes(q)
                )
                : g.items;
            return items.length ? { ...g, items } : null;
        })
        .filter(Boolean);
});

function doSearch() { appliedSearch.value = searchInput.value; }
function doClear()  { searchInput.value = ''; appliedSearch.value = ''; yearMonth.value = ''; }
</script>

<template>
    <AppLayout title="お知らせ">
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">お知らせ</h2>
        </template>

        <div class="rounded bg-white px-4 py-6 sm:p-6 shadow">

            <!-- 検索・絞り込み -->
            <div class="mb-6 space-y-2">
                <div class="flex max-w-xl gap-2">
                    <input
                        v-model="searchInput"
                        type="text"
                        placeholder="タイトル/差出人/内容で検索"
                        class="flex-1 min-w-0 rounded border border-gray-300 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none"
                        @keydown.enter="doSearch"
                    />
                    <button
                        @click="doSearch"
                        class="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                    >検索</button>
                    <button
                        @click="doClear"
                        class="rounded border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >クリア</button>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-sm text-gray-600">年月:</span>
                    <select
                        v-model="yearMonth"
                        class="rounded border border-gray-300 px-3 py-1.5 text-sm focus:border-blue-400 focus:outline-none"
                    >
                        <option value="">全期間</option>
                        <option v-for="ym in yearMonthOptions" :key="ym" :value="ym">{{ formatYM(ym) }}</option>
                    </select>
                </div>
            </div>

            <div v-if="filteredGrouped.length === 0" class="py-12 text-center text-gray-500">
                お知らせはありません
            </div>

            <div v-else class="space-y-6">
                <div v-for="group in filteredGrouped" :key="group.date">
                    <!-- 日付ヘッダー -->
                    <div class="mb-2 flex items-center gap-3">
                        <span class="text-sm font-semibold text-gray-700">{{ group.date }}</span>
                        <hr class="flex-1 border-gray-200" />
                    </div>

                    <!-- その日のお知らせ一覧 -->
                    <div class="divide-y divide-gray-100 overflow-hidden rounded border border-gray-200">
                        <Link
                            v-for="item in group.items"
                            :key="item.id"
                            :href="route('announcements.show', { recipient: item.id })"
                            class="flex items-center gap-3 px-4 py-3 transition hover:bg-gray-50"
                        >
                            <!-- 未読・既読マーク -->
                            <span
                                class="inline-flex h-2 w-2 flex-shrink-0 rounded-full"
                                :class="item.is_read ? 'bg-gray-300' : 'bg-red-500'"
                            ></span>

                            <div class="flex-1 min-w-0">
                                <p
                                    class="truncate text-sm"
                                    :class="item.is_read ? 'text-gray-600' : 'font-semibold text-gray-900'"
                                >
                                    {{ item.title }}
                                </p>
                                <p class="text-xs text-gray-400">{{ item.sender }} · {{ item.created_at }}</p>
                            </div>

                            <span
                                v-if="!item.is_read"
                                class="flex-shrink-0 rounded-full bg-red-100 px-2 py-0.5 text-xs text-red-600"
                            >
                                未読
                            </span>
                            <span
                                v-else
                                class="flex-shrink-0 rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500"
                            >
                                既読
                            </span>
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
