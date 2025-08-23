<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import Welcome from '@/Components/Welcome.vue';
import { Link } from '@inertiajs/vue3';

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
    <AppLayout title="SuperAdmin Dashboard" :user="user">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                【スーパ管理者】{{ user?.name || 'ユーザー' }}さんのページ
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- ナビゲーションタブ -->
                <div class="mb-6">
                    <nav class="flex space-x-8" aria-label="Tabs">
                        <Link :href="route('superadmin.adminusers.index')" class="text-red-600 hover:text-red-800 px-3 py-2 font-medium text-sm rounded-md border border-red-200 hover:bg-red-50">
                            ユーザー管理
                        </Link>
                        <Link :href="route('superadmin.companies.index')" class="text-green-600 hover:text-green-800 px-3 py-2 font-medium text-sm rounded-md border border-green-200 hover:bg-green-50">
                            会社の追加と管理
                        </Link>
                        <Link :href="route('profile.show')" class="text-gray-600 hover:text-gray-800 px-3 py-2 font-medium text-sm rounded-md">
                            プロフィール編集
                        </Link>
                    </nav>
                </div>

                <!-- プロフィール情報表示 -->
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6 mb-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">スーパ管理者プロフィール情報</h3>
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
                            <p class="mt-1 text-sm text-red-600 font-semibold">{{ user?.user_role || '未設定' }}</p>
                        </div>
                    </div>
                </div>

                <!-- スーパ管理者専用機能 -->
                <div class="bg-yellow-50 overflow-hidden shadow-xl sm:rounded-lg p-6 mb-6">
                    <h3 class="text-lg font-medium text-yellow-900 mb-4">スーパ管理者専用機能</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-white p-4 rounded-lg border border-yellow-200">
                            <h4 class="font-medium text-yellow-900">全社権限の管理</h4>
                            <p class="text-sm text-yellow-700 mt-2">システム全体の最上位権限管理</p>
                        </div>
                        <div class="bg-white p-4 rounded-lg border border-yellow-200">
                            <h4 class="font-medium text-yellow-900">システム設定</h4>
                            <p class="text-sm text-yellow-700 mt-2">全体のシステム設定管理</p>
                        </div>
                        <div class="bg-white p-4 rounded-lg border border-yellow-200">
                            <h4 class="font-medium text-yellow-900">監査・ログ</h4>
                            <p class="text-sm text-yellow-700 mt-2">監査ログの確認とエクスポート</p>
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
