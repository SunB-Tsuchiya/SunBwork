<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import Calendar from '@/Components/Calendar.vue';
import UserNavigationTabs from '@/Components/Tabs/UserNavigationTabs.vue';
import IrukaBoard from '@/Components/Iruka/IrukaBoard.vue';
// User tabs remain per-page

defineProps({
    diaries:        { type: Array,  default: () => [] },
    events:         { type: Array,  default: () => [] },
    jobs:           { type: Array,  default: () => [] },
    calendarView:   { type: String, default: 'timeGridWeek' },
    defaultWorktype:{ type: Object, default: null },
    worktypes:      { type: Array,  default: () => [] },
    dailyWorktypes: { type: Array,  default: () => [] },
    departments:    { type: Array,  default: () => [] },
});

import { usePage } from '@inertiajs/vue3';
import { nextTick, ref, watch } from 'vue';
const page = usePage();
const user = page.props.user;

const showProfile = ref(false);

const STORAGE_KEY = 'dashboard_tab';
const activeTab = ref(localStorage.getItem(STORAGE_KEY) ?? 'calendar');
function setTab(tab) {
    activeTab.value = tab;
    localStorage.setItem(STORAGE_KEY, tab);
}

// 在席ボード → カレンダーに切り替えたとき、FullCalendarのサイズを再計算させる
watch(activeTab, async (newTab) => {
    if (newTab === 'calendar') {
        await nextTick();
        window.dispatchEvent(new Event('resize'));
    }
});
</script>

<template>
    <AppLayout title="Dashboard" :user="user">
        <template #header>
            <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">
                <span v-if="user?.user_role === 'admin'"> 【管理者→ユーザーモード】{{ user?.name || 'ユーザー' }}さんのページ </span>
                <span v-else-if="user?.user_role === 'leader'"> 【リーダー→ユーザーモード】{{ user?.name || 'ユーザー' }}さんのページ </span>
                <span v-else-if="user?.user_role === 'coordinator'">【進行管理→ユーザーモード】{{ user?.name || 'ユーザー' }}さんのページ</span>
                <span v-else> {{ user?.name || 'ユーザー' }}さんのページ </span>
            </h2>
        </template>
        <template #tabs>
            <UserNavigationTabs active="dashboard" />
        </template>

        <!-- ナビゲーションタブ (ユーザーはページ内で管理) -->
        <!--AI用メモ：Applayoutで、ここにTabsの各タブメニューを入れる。までapplayoutで管理-->
        <!--AI用メモ：ここまでapplayoutで管理-->

        <!--AI用メモ：ここからを各ページのコンテンツとする-->

        <!-- タブ切替（カレンダー / イルカ） -->
        <div class="mb-4 flex rounded-lg border border-gray-200 bg-white p-1 shadow-sm w-fit">
            <button
                type="button"
                class="rounded-md px-4 py-1.5 text-sm font-medium transition-colors"
                :class="activeTab === 'calendar' ? 'bg-blue-500 text-white shadow' : 'text-gray-500 hover:text-gray-700'"
                @click="setTab('calendar')"
            >📅 カレンダー</button>
            <button
                type="button"
                class="rounded-md px-4 py-1.5 text-sm font-medium transition-colors"
                :class="activeTab === 'iruka' ? 'bg-blue-500 text-white shadow' : 'text-gray-500 hover:text-gray-700'"
                @click="setTab('iruka')"
            >🐬 在席ボード</button>
        </div>

        <!-- プロフィール情報表示（トグル式） -->
        <div v-show="activeTab === 'calendar'" class="mb-6 rounded bg-white shadow">
            <button
                @click="showProfile = !showProfile"
                class="flex w-full items-center justify-between px-6 py-4 text-left"
            >
                <h3 class="text-lg font-medium text-gray-900">プロフィール情報</h3>
                <svg
                    :class="showProfile ? 'rotate-180' : ''"
                    class="h-5 w-5 text-gray-500 transition-transform duration-200"
                    fill="none" viewBox="0 0 24 24" stroke="currentColor"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div v-show="showProfile" class="border-t border-gray-100 px-6 pb-6 pt-4">
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">名前</label>
                        <p class="mt-1 text-sm text-gray-900">{{ user?.name || '未設定' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">メールアドレス</label>
                        <p class="mt-1 text-sm text-gray-900">{{ user?.email || '未設定' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">会社・部署</label>
                        <p class="mt-1 text-sm text-gray-900">
                            {{ user?.current_team?.company_name || user?.company?.name || '未設定' }}
                            <span v-if="user?.current_team?.department_name || user?.department?.name">
                                - {{ user?.current_team?.department_name || user?.department?.name }}
                            </span>
                        </p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">担当</label>
                        <p class="mt-1 text-sm text-gray-900">{{ user?.assignment?.name || '未設定' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">チーム</label>
                        <p class="mt-1 text-sm text-gray-900">{{ user?.current_team?.name || '未設定' }}</p>
                    </div>
                </div>
            </div>
        </div>
        <!-- カレンダー -->
        <div v-show="activeTab === 'calendar'" class="rounded bg-white px-4 py-6 sm:p-6 shadow">
            <Calendar
                :diaries="diaries"
                :events="events"
                :jobs="jobs"
                :initial-view="calendarView"
                :default-worktype="defaultWorktype"
                :worktypes="worktypes"
                :daily-worktypes="dailyWorktypes"
                diary-label="日報"
            />
        </div>

        <!-- イルカ在席ボード -->
        <div v-show="activeTab === 'iruka'">
            <IrukaBoard :departments="$page.props.departments" />
        </div>
        <!--AI用メモ：ここまでを各ページのコンテンツとする-->

        <!--AI用メモ：ここからAppLayoutで管理-->
    </AppLayout>
</template>
