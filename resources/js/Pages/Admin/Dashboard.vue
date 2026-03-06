<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
// tabs are rendered centrally in AppLayout.vue

const props = defineProps({
    available_teams: {
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
    <AppLayout title="Admin Dashboard" :user="user">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">【管理者】{{ user?.name || 'ユーザー' }}さんのページ</h2>
        </template>

        <!-- ナビゲーションタブ (中央化のため AppLayout.vue で表示) -->

        <!-- プロフィール情報表示 -->
        <div class="mb-6 overflow-hidden bg-white p-6 shadow-xl sm:rounded-lg">
            <h3 class="mb-4 text-lg font-medium text-gray-900">管理者プロフィール情報</h3>
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
                        {{ user?.current_team?.company_name || '未設定' }}
                        <span v-if="user?.current_team?.department_name"> - {{ user.current_team.department_name }}</span>
                    </p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">担当</label>
                    <p class="mt-1 text-sm text-gray-900">{{ user?.role || '未設定' }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">権限レベル</label>
                    <p class="mt-1 text-sm font-semibold text-red-600">{{ user?.user_role || '未設定' }}</p>
                </div>
            </div>
        </div>

        <!-- 管理者専用機能 -->
        <div class="mb-6 overflow-hidden bg-red-50 p-6 shadow-xl sm:rounded-lg">
            <h3 class="mb-4 text-lg font-medium text-red-900">管理者専用機能</h3>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div class="rounded-lg border border-red-200 bg-white p-4">
                    <h4 class="font-medium text-red-900">ユーザー管理</h4>
                    <p class="mt-2 text-sm text-red-700">全ユーザーの管理、作成、編集、削除</p>
                </div>
                <div class="rounded-lg border border-red-200 bg-white p-4">
                    <h4 class="font-medium text-red-900">システム設定</h4>
                    <p class="mt-2 text-sm text-red-700">システム全体の設定管理</p>
                </div>
                <div class="rounded-lg border border-red-200 bg-white p-4">
                    <h4 class="font-medium text-red-900">レポート</h4>
                    <p class="mt-2 text-sm text-red-700">システム使用状況の確認</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
