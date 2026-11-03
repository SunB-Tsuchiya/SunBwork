<script setup>
import { ref } from 'vue';
import { route } from 'ziggy-js';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import UserNavigationTabs from '@/Components/Tabs/UserNavigationTabs.vue';
import QuickWorkEntryModal from '@/Components/Calendar/QuickWorkEntryModal.vue';

const props = defineProps({ rows: Array, months: Array, filters: Object, pagination: Object, createdKey: { type: String, default: null } });
const showCreate = ref(false);
const createDate = ref('');
function openCreate() {
    createDate.value = new Date().toLocaleDateString('sv-SE', { timeZone: 'Asia/Tokyo' });
    showCreate.value = true;
}
function onSaved(date, id) {
    showCreate.value = false;
    router.get(route('user.myjobbox.unified_preview'), { period: date.slice(0, 7), created_entry: id }, { preserveState: false });
}
const q = ref(props.filters.q);
const period = ref(props.filters.period);
const hideCompleted = ref(props.filters.hide_completed);
function search(page = 1) {
    router.get(route('user.myjobbox.unified_preview'), {
        q: q.value || undefined, period: period.value,
        hide_completed: hideCompleted.value ? 1 : 0, page,
    }, { preserveState: true, preserveScroll: true });
}
function clear() {
    q.value = '';
    search();
}
function calendar(date) {
    return route('calendar.index', { date, view: 'day' });
}
</script>

<template>
    <AppLayout title="統合BOX（試用）">
        <template #header><h2 class="text-base font-semibold text-gray-800 sm:text-xl">統合BOX（試用）</h2></template>
        <template #tabs><UserNavigationTabs active="myjob" /></template>
        <div class="rounded bg-white p-4 shadow sm:p-6">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-gray-600">本人の作業をまとめて確認する試用一覧です。</p>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" class="rounded bg-blue-600 px-4 py-2 text-sm text-white" @click="openCreate">マイジョブ作成</button>
                    <Link :href="route('user.myjobbox.index')" class="rounded border px-3 py-2 text-sm text-blue-700">従来のマイジョブBOXへ</Link>
                </div>
            </div>
            <p class="mb-4 text-xs text-gray-500">詳細入力・依頼からの登録・添付は「従来のマイジョブBOXへ」から利用できます。</p>
            <form class="flex flex-wrap items-center gap-3" @submit.prevent="search()">
                <label class="sr-only" for="unified-search">作業・案件・分類を検索</label>
                <input id="unified-search" v-model="q" maxlength="200" placeholder="作業名・案件名・分類で検索" class="w-full rounded border px-3 py-2 text-sm sm:w-72" />
                <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-sm text-white">検索</button>
                <button type="button" class="rounded border px-3 py-2 text-sm" @click="clear">クリア</button>
                <label class="flex items-center gap-2 text-sm">年月
                    <select v-model="period" class="rounded border px-3 py-2" @change="search()">
                        <option value="all">全期間</option>
                        <option v-for="month in [...new Set([...months, ...(period === 'all' ? [] : [period])])].sort().reverse()" :key="month" :value="month">{{ month }}</option>
                    </select>
                </label>
                <label class="flex items-center gap-2 text-sm"><input v-model="hideCompleted" type="checkbox" class="rounded" @change="search()" />完了を隠す</label>
            </form>
            <p class="mt-3 text-xs leading-relaxed text-gray-500">予定がある作業は作業月、未予定は締切日（なければ登録日）の月で表示します。古い未予定は「全期間」から探せます。簡易入力の「登録済み」は完了を意味しません。</p>
            <p class="mt-4 text-sm text-gray-600">{{ pagination.total }}件<span v-if="pagination.last > 1"> · {{ pagination.page }} / {{ pagination.last }}ページ</span></p>
            <div class="mt-2 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-y bg-gray-50 text-gray-600"><tr><th class="p-3">作業日・時刻</th><th class="p-3">作業名 / 案件・分類</th><th class="p-3">由来</th><th class="p-3">状態</th><th class="p-3">確認</th></tr></thead>
                    <tbody class="divide-y">
                        <tr v-for="row in rows" :key="row.key" :class="{ 'bg-blue-50': row.key === createdKey }">
                            <td class="p-3 align-top">
                                <span v-if="!row.slots.length" class="text-gray-500">未予定</span>
                                <div v-for="(slot, index) in row.slots.slice(0, 2)" :key="index" class="whitespace-nowrap">{{ slot.date }} {{ slot.start }}<span v-if="slot.end">〜{{ slot.endDate !== slot.date ? slot.endDate + ' ' : '' }}{{ slot.end }}</span></div>
                                <details v-if="row.slots.length > 2" class="mt-1"><summary class="cursor-pointer text-blue-700">ほか{{ row.slots.length - 2 }}件の予定</summary><div v-for="(slot, index) in row.slots.slice(2)" :key="index" class="mt-1 whitespace-nowrap">{{ slot.date }} {{ slot.start }}〜{{ slot.endDate !== slot.date ? slot.endDate + ' ' : '' }}{{ slot.end }}</div></details>
                            </td>
                            <td class="p-3 align-top"><div class="font-medium text-gray-900">{{ row.title || '（作業名なし）' }}<span v-if="row.key === createdKey" class="ml-2 text-xs font-normal text-blue-700">今回登録</span></div><div v-if="row.project" class="mt-1 text-gray-600">{{ row.project }}</div><div v-if="row.classification" class="mt-1 text-xs text-gray-500">{{ row.classification }}</div><div v-if="row.quantity" class="mt-1 text-xs text-gray-500">数量: {{ row.quantity }}</div><div v-if="!row.slots.length && row.fallbackDate" class="mt-1 text-xs text-gray-500">締切 / 登録日: {{ row.fallbackDate }}</div></td>
                            <td class="whitespace-nowrap p-3 align-top">{{ row.kind }}</td><td class="whitespace-nowrap p-3 align-top">{{ row.status }}</td>
                            <td class="p-3 align-top"><Link v-if="row.entryId" :href="route('user.myjobbox.entries.show', { entry: row.entryId, from: 'box', period: filters.period, q: filters.q || undefined, page: pagination.page, hide_completed: filters.hide_completed ? 1 : 0 })" class="whitespace-nowrap text-blue-700 underline">詳細</Link><Link v-if="row.assignmentId" :href="route('user.myjobbox.show', { assignment: row.assignmentId })" class="whitespace-nowrap text-blue-700 underline">詳細</Link><Link v-if="row.slots.length" :href="calendar(row.slots[0].date)" class="mt-1 block whitespace-nowrap text-blue-700 underline">カレンダー</Link></td>
                        </tr>
                        <tr v-if="!rows.length"><td colspan="5" class="p-8 text-center text-gray-500">該当する作業はありません。</td></tr>
                    </tbody>
                </table>
            </div>
            <div v-if="pagination.last > 1" class="mt-4 flex justify-end gap-3">
                <button :disabled="pagination.page <= 1" class="rounded border px-3 py-2 text-sm disabled:opacity-40" @click="search(pagination.page - 1)">前へ</button>
                <button :disabled="pagination.page >= pagination.last" class="rounded border px-3 py-2 text-sm disabled:opacity-40" @click="search(pagination.page + 1)">次へ</button>
            </div>
        </div>
        <QuickWorkEntryModal :show="showCreate" :date="createDate" entry-point="myjob" @close="showCreate = false" @saved="onSaved" />
    </AppLayout>
</template>
