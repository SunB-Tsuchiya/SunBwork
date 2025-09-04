<script setup>
import Welcome from '@/Components/Welcome.vue';
import AppLayout from '@/layouts/AppLayout.vue';
// Leader tabs remain per-page

const props = defineProps({});

// ユーザー情報はinertiaのpropsから取得する
import { usePage } from '@inertiajs/vue3';
const page = usePage();
const user = page.props.user;

// デバッグ用にpropsをログ出力
console.log('Leader Dashboard user:', user);
</script>

<template>
    <AppLayout title="Leader Dashboard" :user="user">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">【リーダー】{{ user?.name || 'ユーザー' }}さんのページ</h2>
        </template>

        <!-- プロフィール情報表示 -->
        <div class="mb-6 overflow-hidden bg-white p-6 shadow-xl sm:rounded-lg">
            <h3 class="mb-4 text-lg font-medium text-gray-900">リーダープロフィール情報</h3>
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
                        <span v-if="user?.current_team?.department_name"> - {{ user.current_team.department_name }}</span>
                    </p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">担当</label>
                    <p class="mt-1 text-sm text-gray-900">{{ user?.role || user?.assignment?.name || '未設定' }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">権限レベル</label>
                    <p class="mt-1 text-sm font-semibold text-orange-600">{{ user?.user_role || '未設定' }}</p>
                </div>
            </div>
        </div>

        <!-- リーダー専用機能 -->
        <div class="mb-6 overflow-hidden bg-orange-50 p-6 shadow-xl sm:rounded-lg">
            <h3 class="mb-4 text-lg font-medium text-orange-900">リーダー専用機能</h3>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div class="rounded-lg border border-orange-200 bg-white p-4">
                    <h4 class="font-medium text-orange-900">コンテンツ管理</h4>
                    <p class="mt-2 text-sm text-orange-700">サイトコンテンツの作成、編集、公開</p>
                </div>
                <div class="rounded-lg border border-orange-200 bg-white p-4">
                    <h4 class="font-medium text-orange-900">ユーザー確認</h4>
                    <p class="mt-2 text-sm text-orange-700">一般ユーザーの活動確認</p>
                </div>
                <div class="rounded-lg border border-orange-200 bg-white p-4">
                    <h4 class="font-medium text-orange-900">統計情報</h4>
                    <p class="mt-2 text-sm text-orange-700">アクセス統計とユーザー分析</p>
                </div>
            </div>
        </div>

        <div class="overflow-hidden bg-white shadow-xl sm:rounded-lg">
            <Welcome />
        </div>
    </AppLayout>
</template>
