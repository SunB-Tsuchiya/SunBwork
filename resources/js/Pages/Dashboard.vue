<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import Calendar from '@/Components/Calendar.vue';
import UserNavigationTabs from '@/Components/Tabs/UserNavigationTabs.vue';
// User tabs remain per-page

const props = defineProps({
    diaries: {
        type: Array,
        default: () => [],
    },
    events: {
        type: Array,
        default: () => [],
    },
    jobs: {
        type: Array,
        default: () => [],
    },
});

// ユーザー情報はinertiaのpropsから取得する
import { usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
const page = usePage();
const user = page.props.user;

const showProfile = ref(false);
</script>

<template>
    <AppLayout title="Dashboard" :user="user">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                <span v-if="user?.user_role === 'admin'"> 【管理者→ユーザーモード】{{ user?.name || 'ユーザー' }}さんのページ </span>
                <span v-else-if="user?.user_role === 'leader'"> 【リーダー→ユーザーモード】{{ user?.name || 'ユーザー' }}さんのページ </span>
                <span v-else-if="user?.user_role === 'coordinator'">【進行管理→ユーザーモード】{{ user?.name || 'ユーザー' }}さんのページ</span>
                <span v-else> {{ user?.name || 'ユーザー' }}さんのページ </span>
            </h2>
        </template>
        <template #tabs>
            <UserNavigationTabs active="profile" />
        </template>

        <!-- ナビゲーションタブ (ユーザーはページ内で管理) -->
        <!--AI用メモ：Applayoutで、ここにTabsの各タブメニューを入れる。までapplayoutで管理-->
        <!--AI用メモ：ここまでapplayoutで管理-->

        <!--AI用メモ：ここからを各ページのコンテンツとする-->
        <!-- プロフィール情報表示（トグル式） -->
        <div class="mb-6 rounded bg-white shadow">
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
        <div class="rounded bg-white p-6 shadow">
            <Calendar :diaries="diaries" :events="events" :jobs="jobs" diary-label="日報" />
        </div>
        <!--AI用メモ：ここまでを各ページのコンテンツとする-->

        <!--AI用メモ：ここからAppLayoutで管理-->
    </AppLayout>
</template>
