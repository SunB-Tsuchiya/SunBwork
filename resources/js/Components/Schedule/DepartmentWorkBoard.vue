<script setup>
import axios from 'axios';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { route } from 'ziggy-js';
import { usePage } from '@inertiajs/vue3';
import { workCategories, activityLabel } from '@/Constants/personalWorkActivities';
import { evColor } from '@/Composables/useEventTypeColors';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';

const props = defineProps({ initialDate: { type: String, default: '' } });
const emit = defineEmits(['date-change']);
const date = ref(props.initialDate || new Date().toLocaleDateString('sv-SE'));
const people = ref([]);
const loading = ref(false);
const error = ref('');
const categories = workCategories;
const duration = (minutes) => `${Math.floor(minutes / 60)}時間${minutes % 60 ? `${minutes % 60}分` : ''}`;
const categoryName = (value) => categories.find(([key]) => key === value)?.[1] || 'その他';
const slotName = (value) => ({ am: '午前', pm: '午後', full: '終日', hour: '時間指定' })[value] || '';
const kindName = (item) => ({ my_job: 'マイジョブ', assigned_job: '依頼ジョブ', event: 'その他の予定', quick_work: '簡易予定' })[item.kind] || (item.source === 'entry' ? '簡易予定' : '既存予定');
const itemTime = (item) => item.starts_at
    ? `${item.starts_at.slice(11)}–${item.ends_at?.slice(11) || ''}`
    : (item.plan_slot === 'hour' ? `${item.planned_start_time}–${item.planned_end_time}` : slotName(item.plan_slot));
const myId = Number(usePage().props.auth?.user?.id || 0);
const own = computed(() => people.value.find((person) => person.id === myId));
const selectedPersonId = ref(myId);
const selectedPerson = computed(() => people.value.find((person) => person.id === selectedPersonId.value) || own.value || people.value[0]);
const stageLabel = (value) => ({ unknown: '未登録・不明', light: '少なめ', medium: '半日程度', full: 'ほぼ埋まっている' })[value] || '';
const dayLabel = computed(() => new Date(`${date.value}T12:00:00`).toLocaleDateString('ja-JP', { year: 'numeric', month: 'long', day: 'numeric', weekday: 'short' }));
const startMinute = 6 * 60;
const endMinute = 23 * 60;
const timelineHours = Array.from({ length: 18 }, (_, index) => index + 6);
const now = ref(new Date());
let clockInterval;
const currentMinute = computed(() => now.value.getHours() * 60 + now.value.getMinutes());
const currentTimePosition = computed(() => {
    if (date.value !== now.value.toLocaleDateString('sv-SE') || currentMinute.value < startMinute || currentMinute.value >= endMinute) return null;
    return `${((currentMinute.value - startMinute) / (endMinute - startMinute)) * 100}%`;
});
const currentTimeLabel = computed(() => `${String(now.value.getHours()).padStart(2, '0')}:${String(now.value.getMinutes()).padStart(2, '0')}`);

function changeDay(offset) {
    const next = new Date(`${date.value}T12:00:00`);
    next.setDate(next.getDate() + offset);
    date.value = next.toLocaleDateString('sv-SE');
}

function minuteOf(item, boundary) {
    if (item.source === 'entry') return item[`timeline_${boundary}_minute`];
    const value = item[boundary === 'start' ? 'starts_at' : 'ends_at'];
    if (!value) return null;
    const [day, time] = value.split(' ');
    const [hour, minute] = time.split(':').map(Number);
    const dayOffset = Math.round((Date.parse(`${day}T12:00:00`) - Date.parse(`${date.value}T12:00:00`)) / 86400000);
    return dayOffset * 1440 + hour * 60 + minute;
}

const sortedSelectedItems = computed(() => [...(selectedPerson.value?.items || [])].sort((a, b) => {
    const startA = minuteOf(a, 'start');
    const startB = minuteOf(b, 'start');
    return (Number.isFinite(startA) ? startA : Infinity) - (Number.isFinite(startB) ? startB : Infinity);
}));

function timelineItems(person) {
    const items = (person.items || []).map((item) => ({ ...item, from: minuteOf(item, 'start'), to: minuteOf(item, 'end') }))
        .filter((item) => item.from !== null && item.to !== null && item.to > startMinute && item.from < endMinute)
        .sort((a, b) => a.from - b.from);
    const laneEnds = [];
    for (const item of items) {
        let lane = laneEnds.findIndex((end) => end <= item.from);
        if (lane === -1) lane = laneEnds.length;
        laneEnds[lane] = item.to;
        item.lane = lane;
    }
    return { items, height: Math.max(36, laneEnds.length * 32 + 8) };
}

function blockStyle(item) {
    const from = Math.max(startMinute, item.from);
    const to = Math.min(endMinute, item.to);
    const color = item.private ? { bg: '#f3f4f6', text: '#4b5563', border: '#d1d5db' } : evColor(item);
    return { left: `${((from - startMinute) / (endMinute - startMinute)) * 100}%`,
        width: `${Math.max(0.4, ((to - from) / (endMinute - startMinute)) * 100)}%`,
        top: `${4 + item.lane * 32}px`, height: '28px',
        backgroundColor: color.bg, color: color.text, borderColor: color.border };
}

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const { data } = await axios.get(route('schedule.department_work.index'), { params: { date: date.value } });
        people.value = data.people || [];
    } catch {
        error.value = '作業予定を読み込めませんでした。';
    } finally {
        loading.value = false;
    }
}

watch(date, () => { emit('date-change', date.value); load(); });
onMounted(() => {
    load();
    clockInterval = window.setInterval(() => { now.value = new Date(); }, 60_000);
});
onUnmounted(() => window.clearInterval(clockInterval));
</script>

<template>
    <div>
        <div class="overflow-hidden rounded-lg bg-white shadow">
            <div class="flex flex-wrap items-center gap-2 border-b px-4 py-3">
                <h3 class="mr-auto font-semibold">部署の作業予定</h3>
                <input v-model="date" type="date" aria-label="表示日" class="rounded border px-2 py-1 text-sm" />
                <button type="button" class="rounded border px-3 py-1 text-sm" @click="changeDay(-1)">◀ 前日</button>
                <span class="min-w-44 text-center text-sm font-medium">{{ dayLabel }}</span>
                <button type="button" class="rounded border px-3 py-1 text-sm" @click="changeDay(1)">翌日 ▶</button>
                <a :href="route('diaries.create', { date })" class="rounded bg-blue-600 px-3 py-1.5 text-sm text-white hover:bg-blue-700">日報作成</a>
            </div>
            <p v-if="error" class="px-4 py-2 text-sm text-red-700" role="alert">{{ error }}</p>
            <p class="px-4 py-2 text-xs text-gray-500">人ごとに時間を横並びで表示します。未登録は「暇」を意味しません。灰色の予定は件名を非公開にしています。</p>
            <TooltipProvider :delay-duration="250">
            <div class="overflow-x-auto border-t">
                <div class="min-w-[1100px]">
                    <div class="flex border-b bg-gray-50 text-xs text-gray-500">
                        <div class="w-48 shrink-0 border-r px-3 py-2">担当者・予定枠</div>
                        <div class="relative flex-1">
                            <span v-for="hour in timelineHours" :key="hour" class="absolute top-2" :style="{ left: `${((hour * 60 - startMinute) / (endMinute - startMinute)) * 100}%` }">{{ hour }}:00</span>
                            <span v-if="currentTimePosition" class="pointer-events-none absolute inset-y-0 z-20 border-l-2 border-red-500" :style="{ left: currentTimePosition }" :title="`現在 ${currentTimeLabel}`"><span class="absolute -top-0.5 left-1 whitespace-nowrap rounded bg-red-500 px-1 text-[10px] leading-4 text-white">{{ currentTimeLabel }}</span></span>
                        </div>
                    </div>
                    <div v-for="person in people" :key="person.id" class="flex border-b" :class="person.id === myId ? 'bg-blue-50/50' : 'bg-white'">
                        <button type="button" class="sticky left-0 z-10 w-48 shrink-0 border-r px-3 py-2 text-left" :class="person.id === myId ? 'bg-blue-50' : 'bg-white'" @click="selectedPersonId = person.id">
                            <span class="block truncate text-sm font-medium">{{ person.name }}<span v-if="person.id === myId" class="ml-1 text-xs text-blue-700">自分</span></span>
                            <span v-if="stageLabel(person.occupancy.stage)" class="text-xs text-gray-500">{{ stageLabel(person.occupancy.stage) }}<span v-if="person.occupancy.percent !== null"> {{ person.occupancy.percent }}%</span></span>
                        </button>
                        <div class="relative flex-1" :style="{ height: `${timelineItems(person).height}px` }">
                            <span v-for="hour in timelineHours" :key="hour" class="pointer-events-none absolute inset-y-0 border-l border-gray-100" :style="{ left: `${((hour * 60 - startMinute) / (endMinute - startMinute)) * 100}%` }"></span>
                            <span v-if="currentTimePosition" class="pointer-events-none absolute inset-y-0 z-20 border-l-2 border-red-500" :style="{ left: currentTimePosition }" :title="`現在 ${currentTimeLabel}`"></span>
                            <Tooltip v-for="item in timelineItems(person).items" :key="`${item.source}-${item.id}`">
                                <TooltipTrigger as-child>
                                    <button type="button"
                                        class="absolute z-10 overflow-hidden truncate rounded border px-2 text-left text-xs shadow-sm"
                                        :style="blockStyle(item)" :aria-label="`${kindName(item)}：${item.title} ${itemTime(item)}`"
                                        @click="selectedPersonId = person.id">{{ item.title }}</button>
                                </TooltipTrigger>
                                <TooltipContent class="max-w-80 whitespace-normal break-words bg-white text-gray-900 shadow-lg" side="top">
                                    <div class="space-y-1 text-xs">
                                        <div class="font-semibold">{{ kindName(item) }} · {{ itemTime(item) }}</div>
                                        <div class="font-medium">{{ item.title }}</div>
                                        <template v-if="!item.private">
                                            <div v-if="item.source === 'entry' || item.activity_type" class="text-gray-600">
                                                {{ categoryName(item.work_category) }}<span v-if="item.activity_type"> · {{ activityLabel(item.activity_type, item.work_category) }}</span>
                                            </div>
                                            <div v-if="item.result_note" class="border-t pt-1">{{ item.result_note }}</div>
                                            <div v-if="item.actual_quantity !== null || item.actual_minutes !== null" class="text-gray-600">
                                                <span v-if="item.actual_quantity !== null">{{ item.actual_quantity }}{{ item.actual_unit }}</span>
                                                <span v-if="item.actual_minutes !== null" class="ml-2">{{ duration(item.actual_minutes) }}</span>
                                            </div>
                                        </template>
                                    </div>
                                </TooltipContent>
                            </Tooltip>
                            <span v-if="!person.items.length" class="absolute left-3 top-4 text-xs text-gray-400">未登録・不明</span>
                        </div>
                    </div>
                </div>
            </div>
            </TooltipProvider>
            <div v-if="selectedPerson" class="p-4">
                <h4 class="font-semibold">{{ selectedPerson.name }}の予定・結果</h4>
                <div v-if="Object.keys(selectedPerson.results || {}).length" class="mt-2 flex flex-wrap gap-1 text-xs">
                    <span v-for="(result, category) in selectedPerson.results" :key="category" class="rounded bg-blue-50 px-2 py-1 text-blue-900">{{ categoryName(category) }} {{ result.count }}件<span v-for="(quantity, unit) in result.quantities" :key="unit" class="ml-1">・{{ quantity }}{{ unit }}</span></span>
                </div>
                <p v-if="!selectedPerson.items.length" class="mt-2 text-sm text-gray-500">予定はまだありません。</p>
                <ul v-else class="mt-2 divide-y text-sm">
                    <li v-for="item in sortedSelectedItems" :key="`${item.source}-${item.id}`" class="py-2">
                        <span class="mr-2 rounded bg-gray-100 px-2 py-0.5 text-xs">{{ kindName(item) }}</span>
                        <strong>{{ item.title }}</strong>
                        <span class="ml-2 text-gray-500">{{ itemTime(item) }}</span>
                        <p v-if="item.result_note" class="mt-1 text-gray-700">{{ item.result_note }}<span v-if="item.activity_type" class="ml-2 text-blue-700">{{ activityLabel(item.activity_type, item.work_category) }}</span><span v-if="item.actual_minutes !== null" class="ml-2 text-gray-500">{{ duration(item.actual_minutes) }}</span><span v-if="item.actual_quantity !== null" class="ml-2 text-gray-500">{{ item.actual_quantity }} {{ item.actual_unit }}</span></p>
                    </li>
                </ul>
            </div>
        </div>

    </div>
</template>
