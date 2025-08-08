<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Welcome from '@/Components/Welcome.vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    user: {
        type: Object,
        required: true,
    },
});

// デバッグ用にpropsをログ出力
console.log('Leader Dashboard props:', props);
</script>

<template>
    <AppLayout title="Leader Dashboard">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                【リーダー】{{ user?.name || 'ユーザー' }}さんのページ
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- ナビゲーションタブ -->
                <div class="mb-6">
                    <nav class="flex space-x-8" aria-label="Tabs">
                        <!-- 管理者の場合のみ表示 -->
                        <Link v-if="user?.user_role === 'admin'" :href="route('admin.dashboard')" class="text-red-600 hover:text-red-800 px-3 py-2 font-medium text-sm rounded-md border border-red-200 hover:bg-red-50">
                            管理者モードに戻る
                        </Link>
                        <a href="#" class="bg-orange-100 text-orange-700 px-3 py-2 font-medium text-sm rounded-md">
                            リーダーダッシュボード
                        </a>
                        <Link :href="route('user.dashboard')" class="text-blue-600 hover:text-blue-800 px-3 py-2 font-medium text-sm rounded-md border border-blue-200 hover:bg-blue-50">
                            ユーザーモードに切り替え
                        </Link>
                        <Link :href="route('profile.show')" class="text-gray-600 hover:text-gray-800 px-3 py-2 font-medium text-sm rounded-md">
                            プロフィール編集
                        </Link>
                    </nav>
                </div>

                <!-- プロフィール情報表示 -->
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6 mb-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">オーナープロフィール情報</h3>
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
                        <div>
                            <label class="block text-sm font-medium text-gray-700">権限レベル</label>
                            <p class="mt-1 text-sm text-orange-600 font-semibold">{{ user?.user_role || '未設定' }}</p>
                        </div>
                    </div>
                </div>

                <!-- オーナー専用機能 -->
                <div class="bg-orange-50 overflow-hidden shadow-xl sm:rounded-lg p-6 mb-6">
                    <h3 class="text-lg font-medium text-orange-900 mb-4">オーナー専用機能</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-white p-4 rounded-lg border border-orange-200">
                            <h4 class="font-medium text-orange-900">コンテンツ管理</h4>
                            <p class="text-sm text-orange-700 mt-2">サイトコンテンツの作成、編集、公開</p>
                        </div>
                        <div class="bg-white p-4 rounded-lg border border-orange-200">
                            <h4 class="font-medium text-orange-900">ユーザー確認</h4>
                            <p class="text-sm text-orange-700 mt-2">一般ユーザーの活動確認</p>
                        </div>
                        <div class="bg-white p-4 rounded-lg border border-orange-200">
                            <h4 class="font-medium text-orange-900">統計情報</h4>
                            <p class="text-sm text-orange-700 mt-2">アクセス統計とユーザー分析</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                    <Welcome />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
