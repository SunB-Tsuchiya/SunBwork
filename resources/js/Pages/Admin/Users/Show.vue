<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    user: Object,
});
</script>

<template>
    <AppLayout title="ユーザー詳細">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">ユーザー詳細</h2>
                <Link :href="route('admin.users.index')" class="rounded bg-gray-600 px-4 py-2 font-bold text-white hover:bg-gray-700">
                    一覧に戻る
                </Link>
            </div>
        </template>
        <main>
            <div class="py-2">
                <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
                    <div class="overflow-hidden bg-white p-6 shadow sm:rounded-lg">
                        <h3 class="mb-4 text-lg font-medium text-gray-900">ユーザー情報</h3>
                        <dl class="divide-y divide-gray-200">
                            <div class="flex justify-between py-4">
                                <dt class="text-sm font-medium text-gray-500">名前</dt>
                                <dd class="text-sm text-gray-900">{{ user.name }}</dd>
                            </div>
                            <div class="flex justify-between py-4">
                                <dt class="text-sm font-medium text-gray-500">メールアドレス</dt>
                                <dd class="text-sm text-gray-900">{{ user.email }}</dd>
                            </div>
                            <div class="flex justify-between py-4">
                                <dt class="text-sm font-medium text-gray-500">権限</dt>
                                <dd class="text-sm text-gray-900">{{ user.user_role }}</dd>
                            </div>
                            <div class="flex justify-between py-4">
                                <dt class="text-sm font-medium text-gray-500">登録日</dt>
                                <dd class="text-sm text-gray-900">{{ new Date(user.created_at).toLocaleDateString('ja-JP') }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </main>
    </AppLayout>
</template>
