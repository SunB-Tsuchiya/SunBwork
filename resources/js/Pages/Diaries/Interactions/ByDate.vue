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

const props = defineProps({
    departments: Array,
    date: String,
    meta: Object,
    filters: Object,
    routePrefix: { type: String, default: 'diaries' },
    pageTitle: { type: String, default: '日報（日付別）' },
    headerTitle: { type: String, default: '日報（日付別）' },
});
const selectedDate = ref(props.date || null);

const groupedByDate = computed(() => {
    const map = {};
    (props.departments || []).forEach((group) => {
        (group.diaries || []).forEach((d) => {
            const date = d.date || '不明';
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
</script>

<template>
    <AppLayout :title="props.pageTitle">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ props.headerTitle }}</h2>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div v-for="(list, date) in groupedByDate" :key="date" class="mb-8">
                    <div class="mb-2">
                        <h3 class="flex items-center gap-2 text-lg font-bold">
                            <span>{{ formatDate(date) }}</span>
                            <Link
                                :href="route(`${props.routePrefix}.diaries.index`, { date: date })"
                                class="inline-flex items-center rounded border bg-white px-2 py-1 text-xs hover:bg-gray-50"
                                aria-label="一覧を見る"
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
                                @click.prevent="() => Inertia.post(route(`${props.routePrefix}.diaries.mark_read_all`), { date: date })"
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
                        :filters="props.filters"
                        :fullContent="props.date === date"
                    />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
