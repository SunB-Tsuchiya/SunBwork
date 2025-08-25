<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
// import Calendar from '@/Components/Calendar.vue'; // ←追加
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
});

// ユーザー情報はinertiaのpropsから取得する
import { usePage } from '@inertiajs/vue3';
const page = usePage();
const user = page.props.user;
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
        <!-- プロフィール情報表示 -->
        <div class="mb-6 overflow-hidden bg-white p-6 shadow-xl sm:rounded-lg">
            <h3 class="mb-4 text-lg font-medium text-gray-900">プロフィール情報</h3>
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
                        {{ user?.company?.name || '未設定' }}
                        <span v-if="user?.department?.name"> - {{ user.department?.name }}</span>
                    </p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">担当</label>
                    <p class="mt-1 text-sm text-gray-900">{{ user?.assignment?.name || '未設定' }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">チーム</label>
                    <p class="mt-1 text-sm text-gray-900">
                        {{ user?.current_team?.name || '未設定' }}
                    </p>
                </div>
            </div>
        </div>
        <!--AI用メモ：ここまでを各ページのコンテンツとする-->

        <!--AI用メモ：ここからAppLayoutで管理-->
    </AppLayout>
</template>
