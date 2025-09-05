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

const props = defineProps({ departments: Array, date: String });
const selectedDate = ref(props.date || null);

// compute grouped diaries by date (newest first). props.departments is an array of groups with diaries
const groupedByDate = computed(() => {
    const map = {};
    (props.departments || []).forEach((group) => {
        (group.diaries || []).forEach((d) => {
            const date = d.date || '不明';
            if (!map[date]) map[date] = [];
            // do not overwrite d.department here (group.department is a date when grouped by date)
            map[date].push(d);
        });
    });
    // sort each date's list by user id or any stable key
    Object.keys(map).forEach((k) => {
        map[k].sort((a, b) => b.id - a.id);
    });
    // return an ordered object with newest dates first
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
    <AppLayout title="リーダー 日報一覧">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">リーダー用 日報一覧</h2>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <!-- render diaries grouped by date -->
                <div v-for="(list, date) in groupedByDate" :key="date" class="mb-8">
                    <div class="mb-2">
                        <h3 class="flex items-center gap-2 text-lg font-bold">
                            <span>{{ formatDate(date) }}</span>
                            <Link
                                :href="route('leader.diaries.index', { date: date })"
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
                                @click.prevent="() => Inertia.post(route('leader.diaries.mark_read_all'), { date: date })"
                                class="ml-2 inline-flex items-center rounded bg-blue-600 px-2 py-1 text-xs text-white hover:bg-blue-700"
                            >
                                全部既読にする
                            </button>
                        </h3>
                    </div>
                    <DiaryTable
                        :diaries="list"
                        routePrefix="leader"
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
