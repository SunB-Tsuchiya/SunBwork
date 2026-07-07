<script setup>
import { ref, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

const props = defineProps({
    team:           { type: Object, required: true },
    minutes:        { type: Array, default: null },
    recentMinutes:  { type: Array, default: () => [] },
    authUserId:     { type: Number, default: null },
    teamLeaderId:   { type: Number, default: null },
});

const displayMinutes = computed(() => props.minutes ?? props.recentMinutes);

// ────────────────── フィルター ──────────────────
const listSearch  = ref('');
const listYear    = ref('');
const listMonth   = ref('');
const listSortDir = ref('desc');

const availableYears = computed(() => {
    const years = new Set(displayMinutes.value.map(m => {
        const d = m.held_at ? String(m.held_at).slice(0, 4) : null;
        return d;
    }).filter(Boolean));
    return [...years].sort((a, b) => b - a);
});

const availableMonths = computed(() => {
    if (!listYear.value) return [];
    const months = new Set(displayMinutes.value
        .filter(m => m.held_at && String(m.held_at).startsWith(listYear.value))
        .map(m => String(m.held_at).slice(5, 7)));
    return [...months].sort();
});

const filteredMinutes = computed(() => {
    let list = displayMinutes.value;

    if (listSearch.value.trim()) {
        const q = listSearch.value.toLowerCase();
        const plainText = (html) => html ? html.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ') : '';
        list = list.filter(m =>
            (m.title || '').toLowerCase().includes(q) ||
            (m.user?.name || '').toLowerCase().includes(q) ||
            plainText(m.content).toLowerCase().includes(q),
        );
    }

    if (listYear.value) {
        list = list.filter(m => m.held_at && String(m.held_at).startsWith(listYear.value));
    }

    if (listMonth.value) {
        list = list.filter(m => m.held_at && String(m.held_at).slice(5, 7) === listMonth.value);
    }

    return [...list].sort((a, b) => {
        const av = a.held_at ?? '';
        const bv = b.held_at ?? '';
        if (av === bv) return 0;
        return (av < bv ? -1 : 1) * (listSortDir.value === 'asc' ? 1 : -1);
    });
});

function resetFilters() {
    listSearch.value  = '';
    listYear.value    = '';
    listMonth.value   = '';
    listSortDir.value = 'desc';
}

// ────────────────── 権限・操作 ──────────────────
function canDelete(minute) {
    return minute.user_id === props.authUserId || props.teamLeaderId === props.authUserId;
}

function goCreate() {
    router.get(route('team-rooms.minutes.create', { team: props.team.id }));
}

function deleteMinute(minute) {
    if (!confirm('この会議記録を削除しますか？')) return;
    router.delete(route('team-rooms.minutes.destroy', { team: props.team.id, minute: minute.id }));
}

function formatDate(d) {
    if (!d) return '';
    return String(d).slice(0, 10);
}
</script>

<template>
    <div>
        <div class="mb-4 flex items-center justify-between">
            <h3 class="font-semibold text-gray-800">会議記録</h3>
            <button
                type="button"
                class="rounded bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700"
                @click="goCreate"
            >新規作成</button>
        </div>

        <!-- フィルターバー -->
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <input
                v-model="listSearch"
                type="text"
                placeholder="キーワード検索..."
                class="rounded border border-gray-300 px-3 py-1.5 text-sm focus:border-indigo-400 focus:outline-none"
                style="min-width: 160px;"
            />
            <select
                v-model="listYear"
                class="rounded border border-gray-300 px-2 py-1.5 text-sm"
                @change="listMonth = ''"
            >
                <option value="">年（全て）</option>
                <option v-for="y in availableYears" :key="y" :value="y">{{ y }}年</option>
            </select>
            <select
                v-model="listMonth"
                class="rounded border border-gray-300 px-2 py-1.5 text-sm"
                :disabled="!listYear"
            >
                <option value="">月（全て）</option>
                <option v-for="m in availableMonths" :key="m" :value="m">{{ parseInt(m) }}月</option>
            </select>
            <button
                type="button"
                :class="['rounded border px-3 py-1.5 text-xs font-medium', listSortDir === 'asc' ? 'border-indigo-400 bg-indigo-50 text-indigo-700' : 'border-gray-300 text-gray-600 hover:bg-gray-50']"
                @click="listSortDir = 'asc'"
            >↑昇順</button>
            <button
                type="button"
                :class="['rounded border px-3 py-1.5 text-xs font-medium', listSortDir === 'desc' ? 'border-indigo-400 bg-indigo-50 text-indigo-700' : 'border-gray-300 text-gray-600 hover:bg-gray-50']"
                @click="listSortDir = 'desc'"
            >↓降順</button>
            <button
                v-if="listSearch || listYear || listMonth"
                type="button"
                class="rounded border border-gray-200 px-3 py-1.5 text-xs text-gray-500 hover:bg-gray-50"
                @click="resetFilters"
            >クリア</button>
        </div>

        <div v-if="filteredMinutes.length === 0" class="py-8 text-center text-sm text-gray-400">
            {{ displayMinutes.length === 0 ? '会議記録がありません' : '条件に一致する会議記録がありません' }}
        </div>

        <div v-else class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="whitespace-nowrap px-4 py-2 text-left text-xs font-medium text-gray-500">日付</th>
                    <th class="whitespace-nowrap px-4 py-2 text-left text-xs font-medium text-gray-500">会議名</th>
                    <th class="whitespace-nowrap px-4 py-2 text-left text-xs font-medium text-gray-500">作成者</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
                <tr
                    v-for="minute in filteredMinutes"
                    :key="minute.id"
                    class="cursor-pointer hover:bg-gray-50"
                    @click="router.get(route('team-rooms.minutes.show', { team: team.id, minute: minute.id }))"
                >
                    <td class="whitespace-nowrap px-4 py-2 text-gray-600">{{ formatDate(minute.held_at) }}</td>
                    <td class="whitespace-nowrap px-4 py-2 font-medium text-gray-800">{{ minute.title }}</td>
                    <td class="whitespace-nowrap px-4 py-2 text-gray-500">{{ minute.user?.name }}</td>
                    <td class="whitespace-nowrap px-4 py-2" @click.stop>
                        <div class="flex items-center justify-end gap-2">
                            <Link
                                :href="route('team-rooms.minutes.show', { team: team.id, minute: minute.id })"
                                class="rounded border border-indigo-300 px-3 py-1 text-xs font-medium text-indigo-600 hover:bg-indigo-50"
                            >詳細</Link>
                            <Link
                                :href="route('team-rooms.minutes.edit', { team: team.id, minute: minute.id })"
                                class="rounded border border-gray-300 px-3 py-1 text-xs font-medium text-gray-600 hover:bg-gray-50"
                            >編集</Link>
                            <button
                                v-if="canDelete(minute)"
                                type="button"
                                class="rounded border border-red-300 px-3 py-1 text-xs font-medium text-red-600 hover:bg-red-50"
                                @click.stop="deleteMinute(minute)"
                            >削除</button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
        </div>

        <!-- 最近5件表示時のリンク -->
        <div v-if="minutes === null && recentMinutes.length > 0" class="mt-4 text-right">
            <Link
                :href="route('team-rooms.minutes.index', { team: team.id })"
                class="text-sm text-indigo-600 hover:underline"
            >すべての会議記録を見る →</Link>
        </div>
    </div>
</template>
