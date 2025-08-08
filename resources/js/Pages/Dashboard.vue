<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Calendar from '@/Components/Calendar.vue'; // ←追加
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    user: {
        type: Object,
        required: true,
    },
});

// デバッグ用にpropsをログ出力
console.log('Dashboard props:', props);
</script>

<template>
    <AppLayout title="Dashboard">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                <template v-if="user?.user_role === 'admin'">
                    【管理者→ユーザーモード】{{ user?.name || 'ユーザー' }}さんのページ
                </template>
                <template v-else-if="user?.user_role === 'leader'">
                    【リーダー→ユーザーモード】{{ user?.name || 'ユーザー' }}さんのページ
                </template>
                <template v-else>
                    {{ user?.name || 'ユーザー' }}さんのページ
                </template>
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- ナビゲーションタブ -->
                <div class="mb-6">
                    <nav class="flex space-x-8" aria-label="Tabs">
                        <!-- 管理者の場合 -->
                        <Link v-if="user?.user_role === 'admin'" :href="route('admin.dashboard')" class="text-red-600 hover:text-red-800 px-3 py-2 font-medium text-sm rounded-md border border-red-200 hover:bg-red-50">
                            管理者モードに戻る
                        </Link>
                        <!-- リーダーまたは管理者の場合 -->
                        <Link v-if="user?.user_role === 'leader' || user?.user_role === 'admin'" :href="route('leader.dashboard')" class="text-orange-600 hover:text-orange-800 px-3 py-2 font-medium text-sm rounded-md border border-orange-200 hover:bg-orange-50">
                            リーダーモードに戻る
                        </Link>
                        <a href="#" class="bg-blue-100 text-blue-700 px-3 py-2 font-medium text-sm rounded-md">
                            ユーザーダッシュボード
                        </a>
                        <Link :href="route('profile.show')" class="text-gray-600 hover:text-gray-800 px-3 py-2 font-medium text-sm rounded-md">
                            プロフィール編集
                        </Link>
                    </nav>
                </div>

                <!-- プロフィール情報表示 -->
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6 mb-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">プロフィール情報</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
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
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                    <Calendar /> <!-- ←Welcomeの代わりにCalendarを表示 -->
                </div>
            </div>
        </div>
    </AppLayout>
</template>
