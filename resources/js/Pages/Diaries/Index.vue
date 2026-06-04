<script setup>
import Calendar from '@/Components/Calendar.vue';
import DiaryTable from '@/Components/Diaries/DiaryTable.vue';
import UserNavigationTabs from '@/Components/Tabs/UserNavigationTabs.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { faCalendar } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { Inertia } from '@inertiajs/inertia';
import { Link } from '@inertiajs/vue3';
import { computed, ref, onMounted } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps({ diaries: Array, meta: Object, filters: Object });
const showCalendar = ref(false);

// 年月セレクター: デフォルトは現在の月
const _now = new Date();
const _currentYear = _now.getFullYear();
const _currentMonth = _now.getMonth() + 1;

function _buildPeriodValue(y, m) {
    return y && m ? `${y}-${String(m).padStart(2, '0')}` : 'all';
}

const selectedPeriod = ref(
    props.filters && props.filters.year && props.filters.month
        ? _buildPeriodValue(props.filters.year, props.filters.month)
        : props.filters && props.filters.period === 'all'
        ? 'all'
        : _buildPeriodValue(_currentYear, _currentMonth),
);

// 全期間 + 過去36か月を降順で生成
const periodOptions = (() => {
    const opts = [{ value: 'all', label: '全期間' }];
    let y = _currentYear,
        m = _currentMonth;
    for (let i = 0; i < 36; i++) {
        opts.push({ value: `${y}-${String(m).padStart(2, '0')}`, label: `${y}年${m}月` });
        m--;
        if (m < 1) {
            m = 12;
            y--;
        }
    }
    return opts;
})();

function getPeriodParams() {
    if (selectedPeriod.value !== 'all') {
        const [y, mo] = selectedPeriod.value.split('-');
        return { year: y, month: String(parseInt(mo)) };
    }
    return { period: 'all' };
}

function onPeriodChange() {
    const params = { ...getPeriodParams(), page: 1 };
    try {
        Inertia.get(route('diaries.index', params));
        return;
    } catch (e) {
        // fallback
    }
    Inertia.get(`/diaries?${new URLSearchParams(params).toString()}`);
}

const currentPage = computed(() => (props.meta && props.meta.current_page ? props.meta.current_page : 1));
const lastPage = computed(() => (props.meta && props.meta.last_page ? props.meta.last_page : 1));

function pageRoute(n) {
    const params = { ...getPeriodParams(), page: n };
    try {
        return route('diaries.index', params);
    } catch (e) {
        return `/diaries?${new URLSearchParams(params).toString()}`;
    }
}

function goToPage(n) {
    // Use Inertia.get with pageRoute so current selectedDays is included
    Inertia.get(pageRoute(n));
}

const selectedPerPage = computed(() => (props.meta && props.meta.per_page ? Number(props.meta.per_page) : 20));

onMounted(() => {
    sessionStorage.setItem('diaries_index_url', window.location.href);
});
</script>

<template>
    <AppLayout title="日報一覧">
        <template #header>
            <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">日報一覧</h2>
        </template>

        <template #tabs>
            <UserNavigationTabs active="diaries" />
        </template>

        <div class="rounded bg-white px-4 py-6 sm:p-6 shadow">
            <div class="mb-4 flex items-center justify-between">
                <div class="flex items-center">
                    <button @click="showCalendar = true" class="text-gray-600 hover:text-blue-600" ref="calendarBtn">
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
                <label class="mr-2 text-sm">表示期間:</label>
                <select v-model="selectedPeriod" class="rounded border px-2 py-1 text-sm" @change="onPeriodChange">
                    <option v-for="opt in periodOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                </select>
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
                        <button @click.prevent="goToPage(p)" :class="['rounded px-2 py-1', p === currentPage ? 'bg-indigo-600 text-white' : 'border']">
                            {{ p }}
                        </button>
                    </template>
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
