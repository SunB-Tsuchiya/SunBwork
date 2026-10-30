<script setup>
import axios from 'axios';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const date = ref('');
const dates = ref([]);
const people = ref([]);
const mine = ref(null);
const loading = ref(true);
const error = ref('');
const sort = ref('name');
const groups = [
    { key: 'job', label: 'ジョブ', color: '#6366f1' },
    { key: 'quick', label: '簡易作業', color: '#ec4899' },
    { key: 'meeting', label: '会議・外出', color: '#10b981' },
    { key: 'other', label: 'その他業務', color: '#64748b' },
];
const duration = (minutes) => `${Math.floor(minutes / 60)}時間${minutes % 60}分`;
const ordered = computed(() => [...people.value].sort((a, b) => {
    const value = (person) => sort.value === 'total' ? person.minutes : (person.breakdown[sort.value] || 0);
    return (sort.value === 'name' ? 0 : value(b) - value(a)) || a.name.localeCompare(b.name, 'ja') || a.id - b.id;
}));
const maximum = computed(() => Math.max(1, ...people.value.map((person) => person.minutes)));
const calendarLink = computed(() => route('calendar.index', { date: date.value }));
let controller;
let sequence = 0;

async function load(selected, bootstrap = false) {
    controller?.abort();
    controller = new AbortController();
    const current = ++sequence;
    loading.value = true;
    error.value = '';
    people.value = [];
    mine.value = null;
    try {
        let response = await axios.get(route('schedule.department_work.records'), { params: selected ? { date: selected } : {}, signal: controller.signal });
        if (current !== sequence) return;
        if (bootstrap) {
            const requested = new URLSearchParams(window.location.search).get('record_date');
            if (requested && response.data.dates.includes(requested) && requested !== response.data.date) {
                response = await axios.get(route('schedule.department_work.records'), { params: { date: requested }, signal: controller.signal });
            }
        }
        if (current !== sequence) return;
        date.value = response.data.date;
        dates.value = response.data.dates;
        people.value = response.data.people;
        mine.value = response.data.mine;
        const url = new URL(window.location.href);
        url.searchParams.set('record_date', date.value);
        window.history.replaceState(window.history.state, '', url);
    } catch (e) {
        if (current !== sequence || e.code === 'ERR_CANCELED') return;
        error.value = '作業記録を読み込めませんでした。日付を選び直すか、再試行してください。';
    } finally {
        if (current === sequence) loading.value = false;
    }
}
onMounted(() => load(null, true));
onBeforeUnmount(() => { ++sequence; controller?.abort(); });
</script>

<template>
    <section class="space-y-4" :aria-busy="loading">
        <div class="rounded-lg border bg-white p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h3 class="font-semibold">みんなの作業記録</h3>
                <label class="flex items-center gap-2 text-sm">対象日
                    <select :value="date" :disabled="loading || !dates.length" class="rounded border-gray-300 text-sm" @change="load($event.target.value)">
                        <option v-for="day in dates" :key="day" :value="day">{{ day }}</option>
                    </select>
                </label>
            </div>
            <p class="mt-2 text-xs text-gray-600">今日と直近4営業日を確認できます。過去日は土日・登録祝日を除きます。休日の今日も表示します。</p>
            <p class="mt-1 text-xs text-gray-600">終了した予定などの記録時間です。休憩と重複を除きます。時間の長さは成果や評価を示すものではありません。</p>
        </div>
        <div v-if="loading" role="status" class="flex items-center gap-2 rounded-lg border bg-white p-6 text-blue-700">
            <span class="h-5 w-5 animate-spin rounded-full border-2 border-blue-200 border-t-blue-600" aria-hidden="true" />作業記録を読み込んでいます…
        </div>
        <div v-else-if="error" role="alert" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            {{ error }} <button type="button" class="ml-2 underline" @click="load(date)">再試行</button>
            <button type="button" class="ml-2 underline" @click="load(null)">今日を表示</button>
        </div>
        <template v-else>
            <div v-if="mine" class="rounded-lg border border-blue-200 bg-blue-50 p-4">
                <h4 class="font-semibold">自分の記録を確認</h4>
                <div class="mt-2 flex flex-wrap gap-x-6 gap-y-2 text-sm">
                    <span>記録時間：{{ duration(mine.minutes) }}</span>
                    <span>作業種類が未指定：{{ duration(mine.unclassified_minutes) }}</span>
                    <span>勤務時間内の予定の空き：{{ mine.gap_minutes === null ? '判定できません' : duration(mine.gap_minutes) }}</span>
                </div>
                <p class="mt-2 text-xs text-gray-600">予定の空きは、勤怠の開始・終了と勤務・休憩設定を確認できる範囲で表示します。未就労という意味ではありません。日報の有無だけでは判定しません。</p>
                <p class="mt-1 text-xs text-gray-600">会議・仕事の配分・他の人の応援も、記録すると管理側の作業量確認に反映されます。作業種類が未指定でも記録時間は集計されます。</p>
                <a :href="calendarLink" class="mt-3 inline-block rounded bg-blue-600 px-3 py-2 text-sm text-white hover:bg-blue-700">この日の記録を確認・入力</a>
            </div>
            <div class="rounded-lg border bg-white p-4">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex flex-wrap gap-3 text-xs">
                        <span v-for="group in groups" :key="group.key" class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded" :style="{ backgroundColor: group.color }" />{{ group.label }}</span>
                    </div>
                    <label class="flex items-center gap-2 text-sm">並び替え
                        <select v-model="sort" class="rounded border-gray-300 text-sm">
                            <option value="name">名前順</option><option value="total">総時間が多い順</option>
                            <option v-for="group in groups" :key="group.key" :value="group.key">{{ group.label }}が多い順</option>
                        </select>
                    </label>
                </div>
                <p v-if="!ordered.length" class="py-6 text-center text-sm text-gray-500">表示対象の方はいません。</p>
                <ul v-else class="space-y-4">
                    <li v-for="person in ordered" :key="person.id" class="grid gap-2 sm:grid-cols-[10rem_1fr_6rem] sm:items-center">
                        <span class="truncate text-sm" :title="person.name">{{ person.name }}</span>
                        <div>
                            <div class="flex h-5 overflow-hidden rounded bg-gray-100">
                                <span v-for="group in groups" :key="group.key" :style="{ width: `${person.breakdown[group.key] / maximum * 100}%`, backgroundColor: group.color }" :title="`${group.label} ${duration(person.breakdown[group.key])}`" />
                            </div>
                            <p class="mt-1 text-xs text-gray-500">{{ groups.map((group) => `${group.label} ${duration(person.breakdown[group.key])}`).join(' ／ ') }}</p>
                        </div>
                        <span class="text-sm sm:text-right">{{ duration(person.minutes) }}</span>
                    </li>
                </ul>
            </div>
        </template>
    </section>
</template>
