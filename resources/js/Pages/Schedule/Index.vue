<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import ScheduleCalendar from '@/Components/Schedule/ScheduleCalendar.vue';
import DepartmentWorkBoard from '@/Components/Schedule/DepartmentWorkBoard.vue';
import DepartmentWorkRecords from '@/Components/Schedule/DepartmentWorkRecords.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    initialDate:        { type: String, default: '' },
    overlays:           { type: Array,  default: () => [] },
    rooms:              { type: Array,  default: () => [] },
    eventItemTypes:     { type: Array,  default: () => [] },
    meetingDefinitions: { type: Array,  default: () => [] },
    companies:          { type: Array,  default: () => [] },
    departments:        { type: Array,  default: () => [] },
    worktypes:          { type: Array,  default: () => [] },
    dailyWorktypes:     { type: Array,  default: () => [] },
    defaultWorktype:    { type: Object, default: null },
    departmentWorkEnabled: { type: Boolean, default: false },
    showDepartmentWork: { type: Boolean, default: false },
});

// 一般会社（サンエー印刷等）は評価システム系の「部署の作業」を出さず、共有予定のみ表示する
const isGeneral = computed(() => usePage().props.auth?.companyType === 'general');
const workEnabled = computed(() => props.departmentWorkEnabled && !isGeneral.value);

const boardVisible = computed(() => workEnabled.value && props.showDepartmentWork);
const departmentTab = ref(new URLSearchParams(window.location.search).get('view') === 'records' ? 'records' : 'plans');
function selectDepartmentTab(value, today = false) {
    departmentTab.value = value;
    const url = new URL(window.location.href);
    url.searchParams.set('view', value);
    if (today) url.searchParams.delete('record_date');
    window.history.replaceState(window.history.state, '', url);
}
const pageTitle = computed(() => boardVisible.value ? '部署の作業' : '予定表');
</script>

<template>
    <AppLayout :title="pageTitle" :fluid="true">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ pageTitle }}</h2>
                <Link v-if="!isGeneral" :href="route('guide.schedule')" class="inline-flex items-center gap-1 rounded-lg border border-teal-200 bg-teal-50 px-3 py-1 text-xs font-medium text-teal-700 hover:bg-teal-100">
                    📖 使い方ガイド
                </Link>
            </div>
        </template>

        <div v-if="boardVisible">
            <div role="group" aria-label="部署の作業の表示" class="mb-3 flex flex-wrap gap-2 border-b border-gray-200">
                <button v-for="item in [['plans', '部署の作業予定'], ['records', 'みんなの作業記録']]" :key="item[0]" type="button" :aria-pressed="departmentTab === item[0]" class="rounded-t px-4 py-2 text-sm font-semibold" :class="departmentTab === item[0] ? 'border border-b-white bg-white text-blue-700' : 'text-gray-600 hover:bg-gray-100'" @click="selectDepartmentTab(item[0])">{{ item[1] }}</button>
            </div>
            <template v-if="departmentTab === 'plans'">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2 rounded border border-blue-100 bg-blue-50 px-3 py-2 text-sm">
                    <span>会議・仕事の配分・応援も、記録すると仕事の内容が伝わります。</span>
                    <button type="button" class="font-semibold text-blue-700 underline" @click="selectDepartmentTab('records', true)">今日の記録を確認</button>
                </div>
                <DepartmentWorkBoard :initial-date="initialDate" />
            </template>
            <DepartmentWorkRecords v-else />
        </div>
        <template v-else>
        <!-- 会議室予約テスト中バナー -->
        <div class="mb-2 flex items-center gap-2 rounded border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs text-amber-800">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 text-amber-500" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
            </svg>
            <span><strong>会議室予約はテスト機能です。</strong>会議室の予約は引き続き Outlook で行ってください。</span>
        </div>

        <div class="rounded-lg bg-white shadow overflow-hidden" style="min-height: calc(100vh - 150px)">
            <ScheduleCalendar
                :initial-date="initialDate"
                :initial-overlays="overlays"
                :event-item-types="eventItemTypes"
                :meeting-definitions="meetingDefinitions"
                :rooms="rooms"
                :companies="companies"
                :departments="departments"
                :worktypes="worktypes"
                :daily-worktypes="dailyWorktypes"
                :trial-evaluation-enabled="workEnabled"
                :default-worktype="defaultWorktype" />
        </div>
        </template>
    </AppLayout>
</template>
