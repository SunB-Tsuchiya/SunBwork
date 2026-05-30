<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import SuperAdminNavigationTabs from '@/Components/Tabs/SuperAdminNavigationTabs.vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    user: Object,
});

const roleLabel = (r) => ({
    superadmin: 'SuperAdmin', admin: 'Admin', leader: 'Leader',
    coordinator: 'Coordinator', clerk: 'Clerk', user: 'User',
}[r] ?? r);
</script>

<template>
    <AppLayout title="ユーザー詳細">
        <template #tabs>
            <SuperAdminNavigationTabs active="all_users" />
        </template>

        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('superadmin.users.index')"
                    class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 whitespace-nowrap hover:bg-gray-300">
                    ← ユーザー一覧に戻る
                </Link>
                <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">ユーザー詳細</h2>
            </div>
        </template>

        <div class="mx-auto max-w-2xl rounded bg-white px-4 py-6 sm:p-6 shadow">
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
                    <dd class="text-sm text-gray-900">{{ roleLabel(user.user_role) }}</dd>
                </div>
                <div class="flex justify-between py-4">
                    <dt class="text-sm font-medium text-gray-500">登録日</dt>
                    <dd class="text-sm text-gray-900">{{ new Date(user.created_at).toLocaleDateString('ja-JP') }}</dd>
                </div>
            </dl>
            <div class="mt-6 flex justify-end gap-3">
                <Link :href="route('superadmin.users.edit', { user: user.id })"
                    class="rounded bg-yellow-500 px-4 py-2 text-sm font-medium text-white hover:bg-yellow-600">
                    編集
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
