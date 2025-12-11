<script setup>
import Calendar from '@/Components/Calendar.vue';
import DiaryTable from '@/Components/Diaries/DiaryTable.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { faCalendar } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { Inertia } from '@inertiajs/inertia';
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps({ diaries: Array, meta: Object, filters: Object });
const showCalendar = ref(false);

// days selector: default to 7 days for "my diaries"
const selectedDays = ref(props.filters && props.filters.days ? Number(props.filters.days) : 7);

const currentPage = computed(() => (props.meta && props.meta.current_page ? props.meta.current_page : 1));
const lastPage = computed(() => (props.meta && props.meta.last_page ? props.meta.last_page : 1));

function applyFilters() {
    const params = Object.assign({}, props.filters || {});
    params.days = selectedDays.value;
    params.page = 1;
    // Prefer Ziggy route helper to build the full URL with query params.
    try {
        Inertia.get(route('diaries.index', params));
        return;
    } catch (e) {
        // fallback to manual query string if Ziggy route isn't available
    }
    const qs = new URLSearchParams(params).toString();
    Inertia.get(`/diaries?${qs}`);
}

function pageRoute(n) {
    const params = Object.assign({}, props.filters || {});
    params.days = selectedDays.value;
    params.page = n;
    const qs = new URLSearchParams(params).toString();
    try {
        return route('diaries.index', params);
    } catch (e) {
        return `/diaries?${qs}`;
    }
}

const deleteDiary = (id) => {
    if (confirm('この日報を削除してよろしいですか？')) {
        Inertia.delete(route('diaries.destroy', id));
    }
};

function formatJapaneseDate(dateStr) {
    const d = new Date(dateStr);
    return `${d.getFullYear()}年${d.getMonth() + 1}月${d.getDate()}日`;
}

function handleDateSelect(dateStr) {
    showCalendar.value = false;
}

function goToPage(n) {
    // Use Inertia.get with pageRoute so current selectedDays is included
    Inertia.get(pageRoute(n));
}

const selectedPerPage = computed(() => (props.meta && props.meta.per_page ? Number(props.meta.per_page) : 20));
</script>

<template>
    <AppLayout title="日報一覧">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">日報一覧</h2>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="mb-4 flex items-center justify-between">
                    <div class="flex items-center">
                        <h1 class="text-2xl font-bold">日報一覧</h1>
                        <button @click="showCalendar = true" class="ml-4 text-gray-600 hover:text-blue-600" ref="calendarBtn">
                            <FontAwesomeIcon :icon="faCalendar" size="lg" />
                        </button>
                        <div v-if="showCalendar">
                            <!-- オーバーレイ -->
                            <div class="fixed inset-0 z-40 bg-transparent" @click="showCalendar = false"></div>
                            <!-- カレンダー本体 -->
                            <div class="calendar-popup absolute left-auto top-full z-50 ml-2 mt-2">
                                <div class="min-w-[300px] rounded bg-white p-4 shadow-lg">
                                    <Calendar @date-select="handleDateSelect" />
                                    <button @click="showCalendar = false" class="mt-2 text-xs text-gray-500 hover:text-blue-600">閉じる</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <Link :href="route('diaries.create')" class="rounded bg-green-600 px-4 py-2 text-white">新しく日報を書く</Link>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="mr-2 text-sm">期間:</label>
                    <select v-model.number="selectedDays" class="rounded border px-2 py-1 text-sm">
                        <option :value="7">7日分を表示</option>
                        <option :value="30">30日分を表示</option>
                        <option :value="90">90日分を表示</option>
                    </select>
                    <button class="ml-2 rounded bg-blue-600 px-3 py-1 text-xs text-white" @click.prevent="applyFilters">適用</button>
                </div>

                <!-- For personal diaries, show only date and content. Hide name/id/dept/read columns by configuring DiaryTable props. -->
                <DiaryTable
                    :diaries="props.diaries"
                    :routePrefix="'diaries'"
                    :serverMode="true"
                    :meta="props.meta"
                    :pageSize="selectedPerPage"
                    :filters="props.filters"
                    :maxDescriptionLines="2"
                    :showUnreadToggle="false"
                    :fullContent="false"
                    :useInteractionRoutes="false"
                    :showReadColumn="false"
                    :showCheckboxes="false"
                    :searchable="false"
                    :compact="true"
                    :hidePagination="true"
                />

                <!-- pagination -->
                <div class="mt-6 flex items-center justify-between">
                    <div>
                        <button
                            class="mr-2 rounded border px-3 py-1"
                            :disabled="currentPage <= 1"
                            @click.prevent="goToPage(Math.max(1, currentPage - 1))"
                        >
                            前
                        </button>
                        <button
                            class="rounded border px-3 py-1"
                            :disabled="currentPage >= lastPage"
                            @click.prevent="goToPage(Math.min(lastPage, currentPage + 1))"
                        >
                            次
                        </button>
                    </div>
                    <div class="text-sm text-gray-600">
                        ページ: <span class="font-medium">{{ currentPage }}</span> / {{ lastPage }}
                    </div>
                    <div class="text-sm text-gray-600">
                        合計:
                        <span class="font-medium">{{
                            props.meta && props.meta.total ? props.meta.total : props.diaries ? props.diaries.length : 0
                        }}</span>
                    </div>
                    <div class="space-x-1">
                        <template v-for="p in Array.from({ length: lastPage }, (_, i) => i + 1)" :key="p">
                            <button
                                @click.prevent="goToPage(p)"
                                :class="['rounded px-2 py-1', p === currentPage ? 'bg-blue-600 text-white' : 'border']"
                            >
                                {{ p }}
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.calendar-popup {
    min-width: 320px;
}
</style>
