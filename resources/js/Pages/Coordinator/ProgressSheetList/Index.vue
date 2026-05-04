<script setup>
import { ref, computed, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import CoordinatorNavigationTabs from '@/Components/Tabs/CoordinatorNavigationTabs.vue';
import axios from 'axios';

const props = defineProps({
    sheets:         { type: Array, default: () => [] },
    favoriteSheets: { type: Array, default: () => [] },
    groupMode:      { type: String, default: 'date' },
    filters:        { type: Object, default: () => ({}) },
});

const searchQuery  = ref(props.filters.search  ?? '');
const selectedMonth = ref(props.filters.month  ?? '');
const showComplete  = ref(props.filters.show_complete ?? false);
const viewMode      = ref(props.groupMode);

// お気に入り状態をローカルで管理（Inertia リロードなし）
const localSheets         = ref(props.sheets.map(s => ({ ...s })));
const localFavoriteSheets = ref(props.favoriteSheets.map(s => ({ ...s })));

const viewModes = [
    { key: 'date',    label: '日付ごと' },
    { key: 'client',  label: 'クライアントごと' },
    { key: 'project', label: '案件ごと' },
];

// 月セレクタ用オプション（現在月から24ヶ月分）
const monthOptions = computed(() => {
    const opts = [];
    const now = new Date();
    for (let i = 0; i < 24; i++) {
        const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
        const val = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
        opts.push({ value: val, label: `${d.getFullYear()}年${d.getMonth() + 1}月` });
    }
    return opts;
});

function search() {
    router.get(
        route('coordinator.progress_sheet_list.index'),
        {
            search:        searchQuery.value,
            month:         selectedMonth.value,
            show_complete: showComplete.value ? '1' : '0',
        },
        { preserveState: false },
    );
}

function clearSearch() {
    searchQuery.value   = '';
    selectedMonth.value = '';
    showComplete.value  = false;
    search();
}

watch(viewMode, (val) => {
    router.get(
        route('coordinator.progress_sheet_list.index'),
        {
            search:        searchQuery.value,
            month:         selectedMonth.value,
            show_complete: showComplete.value ? '1' : '0',
            group_mode:    val,
        },
        { preserveState: false },
    );
});

// ソートモードに応じてシートをグループ化
function groupSheets(list) {
    if (viewMode.value === 'client') {
        const map = {};
        list.forEach(s => {
            const key = s.client_name || '-';
            if (!map[key]) map[key] = { key, label: key, items: [] };
            map[key].items.push(s);
        });
        return Object.values(map).sort((a, b) => a.key.localeCompare(b.key, 'ja'));
    }
    if (viewMode.value === 'project') {
        const map = {};
        list.forEach(s => {
            const key = s.project_job_id;
            const label = s.project_job_title || '-';
            if (!map[key]) map[key] = { key: String(key), label, items: [] };
            map[key].items.push(s);
        });
        return Object.values(map).sort((a, b) => a.label.localeCompare(b.label, 'ja'));
    }
    // date (default)
    const map = {};
    list.forEach(s => {
        const key = s.created_at ? s.created_at.slice(0, 7) : '-';
        const label = key !== '-'
            ? `${key.slice(0, 4)}年${parseInt(key.slice(5, 7))}月`
            : '日付不明';
        if (!map[key]) map[key] = { key, label, items: [] };
        map[key].items.push(s);
    });
    return Object.values(map).sort((a, b) => b.key.localeCompare(a.key));
}

const displayGroups = computed(() => groupSheets(localSheets.value));

// お気に入りトグル
async function toggleFavorite(sheet, isFavSection = false) {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    try {
        const res = await axios.post(
            route('coordinator.progress_sheet_list.favorite', { sheet: sheet.id }),
            {},
            { headers: { 'X-CSRF-TOKEN': csrf } },
        );
        const nowFav = res.data.is_favorite;

        // localSheets の is_favorite を更新
        const idx = localSheets.value.findIndex(s => s.id === sheet.id);
        if (idx !== -1) localSheets.value[idx].is_favorite = nowFav;

        // お気に入りセクションの更新
        if (nowFav) {
            // まだ入っていなければ追加
            if (!localFavoriteSheets.value.find(s => s.id === sheet.id)) {
                localFavoriteSheets.value.unshift({ ...sheet, is_favorite: true });
            }
        } else {
            localFavoriteSheets.value = localFavoriteSheets.value.filter(s => s.id !== sheet.id);
        }
    } catch (e) {
        console.error('お気に入り更新エラー', e);
    }
}

function openSheet(sheet) {
    router.get(route('coordinator.progress_sheets.show', { sheet: sheet.id }));
}
</script>

<template>
    <AppLayout title="進行表一覧">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">進行表一覧</h2>
        </template>

        <template #tabs>
            <CoordinatorNavigationTabs active="progress_sheet_list" />
        </template>

        <div class="space-y-6">

            <!-- 検索・フィルター -->
            <div class="rounded bg-white p-4 shadow">
                <div class="flex flex-col gap-3 md:flex-row md:items-center">
                    <input
                        v-model="searchQuery"
                        @keyup.enter="search"
                        placeholder="案件名・進行表名・クライアントで検索"
                        class="w-80 rounded border px-3 py-2 text-sm"
                    />
                    <button class="rounded bg-blue-600 px-3 py-2 text-sm text-white hover:bg-blue-700" @click.prevent="search">検索</button>
                    <button class="rounded border px-3 py-2 text-sm hover:bg-gray-50" @click.prevent="clearSearch">クリア</button>

                    <div class="flex items-center gap-2">
                        <label class="text-sm text-gray-700">年月:</label>
                        <select
                            v-model="selectedMonth"
                            @change="search"
                            class="rounded border px-3 py-2 text-sm"
                            style="width: 9.5em"
                        >
                            <option value="">全期間</option>
                            <option v-for="m in monthOptions" :key="m.value" :value="m.value">{{ m.label }}</option>
                        </select>
                    </div>

                    <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700 select-none">
                        <input type="checkbox" v-model="showComplete" @change="search" class="h-4 w-4 rounded border-gray-300" />
                        完了案件を含む
                    </label>
                </div>

                <!-- ソートモード -->
                <div class="mt-3 flex gap-1 rounded-lg border border-gray-200 bg-gray-50 p-1 w-fit">
                    <button
                        v-for="mode in viewModes"
                        :key="mode.key"
                        @click="viewMode = mode.key"
                        :class="viewMode === mode.key
                            ? 'bg-white text-green-700 font-semibold shadow-sm'
                            : 'text-gray-600 hover:text-gray-900'"
                        class="rounded px-4 py-1.5 text-sm transition-all"
                    >{{ mode.label }}</button>
                </div>
            </div>

            <!-- ★ お気に入り -->
            <div class="rounded bg-white shadow">
                <div class="flex items-center gap-2 border-b border-yellow-200 bg-yellow-50 px-4 py-3">
                    <span class="text-yellow-500 text-lg">★</span>
                    <span class="text-sm font-semibold text-yellow-800">お気に入り</span>
                    <span class="text-xs text-yellow-600">（完了案件含む・フィルター対象外）</span>
                </div>

                <div v-if="localFavoriteSheets.length === 0" class="px-4 py-6 text-center text-sm text-gray-400">
                    お気に入りの進行表はありません。
                </div>

                <table v-else class="w-full text-sm">
                    <thead class="border-b bg-gray-50 text-left text-xs text-gray-500">
                        <tr>
                            <th class="px-4 py-2 font-medium">作成日</th>
                            <th class="px-4 py-2 font-medium">案件名</th>
                            <th class="px-4 py-2 font-medium">進行表</th>
                            <th class="px-4 py-2 font-medium">クライアント</th>
                            <th class="px-4 py-2 font-medium">ステータス</th>
                            <th class="px-4 py-2 font-medium text-center w-16">★</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr
                            v-for="sheet in localFavoriteSheets"
                            :key="sheet.id"
                            class="cursor-pointer hover:bg-yellow-50 transition-colors"
                            @click="openSheet(sheet)"
                        >
                            <td class="px-4 py-2.5 text-gray-500 whitespace-nowrap">{{ sheet.created_at ?? '-' }}</td>
                            <td class="px-4 py-2.5 font-medium text-gray-800">{{ sheet.project_job_title }}</td>
                            <td class="px-4 py-2.5 text-blue-700 hover:underline">{{ sheet.name }}</td>
                            <td class="px-4 py-2.5 text-gray-600">{{ sheet.client_name }}</td>
                            <td class="px-4 py-2.5">
                                <span
                                    :class="sheet.project_job_completed
                                        ? 'bg-gray-100 text-gray-500'
                                        : 'bg-green-100 text-green-700'"
                                    class="rounded-full px-2 py-0.5 text-xs font-medium"
                                >{{ sheet.project_job_completed ? '完了' : '進行中' }}</span>
                            </td>
                            <td class="px-4 py-2.5 text-center" @click.stop>
                                <button
                                    @click="toggleFavorite(sheet, true)"
                                    class="text-lg leading-none transition-colors"
                                    :class="sheet.is_favorite ? 'text-yellow-400 hover:text-yellow-300' : 'text-gray-300 hover:text-yellow-400'"
                                    title="お気に入り解除"
                                >★</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- 一覧 -->
            <div class="rounded bg-white shadow">
                <div class="border-b px-4 py-3">
                    <span class="text-sm font-semibold text-gray-700">一覧</span>
                </div>

                <div v-if="localSheets.length === 0" class="px-4 py-8 text-center text-sm text-gray-400">
                    表示する進行表がありません。
                </div>

                <template v-else>
                    <template v-for="group in displayGroups" :key="group.key">
                        <!-- グループヘッダー -->
                        <div class="border-b border-gray-200 bg-gray-50 px-4 py-1.5">
                            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ group.label }}</span>
                            <span class="ml-2 text-xs text-gray-400">{{ group.items.length }}件</span>
                        </div>

                        <table class="w-full text-sm">
                            <thead class="border-b bg-white text-left text-xs text-gray-400">
                                <tr>
                                    <th class="px-4 py-1.5 font-medium">作成日</th>
                                    <th class="px-4 py-1.5 font-medium">案件名</th>
                                    <th class="px-4 py-1.5 font-medium">進行表</th>
                                    <th class="px-4 py-1.5 font-medium">クライアント</th>
                                    <th class="px-4 py-1.5 font-medium">ステータス</th>
                                    <th class="px-4 py-1.5 font-medium text-center w-16">★</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <tr
                                    v-for="sheet in group.items"
                                    :key="sheet.id"
                                    class="cursor-pointer hover:bg-green-50 transition-colors"
                                    @click="openSheet(sheet)"
                                >
                                    <td class="px-4 py-2.5 text-gray-500 whitespace-nowrap">{{ sheet.created_at ?? '-' }}</td>
                                    <td class="px-4 py-2.5 font-medium text-gray-800">{{ sheet.project_job_title }}</td>
                                    <td class="px-4 py-2.5 text-blue-700 hover:underline">{{ sheet.name }}</td>
                                    <td class="px-4 py-2.5 text-gray-600">{{ sheet.client_name }}</td>
                                    <td class="px-4 py-2.5">
                                        <span
                                            :class="sheet.project_job_completed
                                                ? 'bg-gray-100 text-gray-500'
                                                : 'bg-green-100 text-green-700'"
                                            class="rounded-full px-2 py-0.5 text-xs font-medium"
                                        >{{ sheet.project_job_completed ? '完了' : '進行中' }}</span>
                                    </td>
                                    <td class="px-4 py-2.5 text-center" @click.stop>
                                        <button
                                            @click="toggleFavorite(sheet)"
                                            class="text-lg leading-none transition-colors"
                                            :class="sheet.is_favorite ? 'text-yellow-400 hover:text-yellow-300' : 'text-gray-300 hover:text-yellow-400'"
                                            :title="sheet.is_favorite ? 'お気に入り解除' : 'お気に入りに追加'"
                                        >★</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </template>
                </template>
            </div>

        </div>
    </AppLayout>
</template>
