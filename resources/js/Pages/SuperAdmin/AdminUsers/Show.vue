<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    user: Object,
});

const roleLabel = {
    superadmin: 'SuperAdmin',
    admin: 'Admin',
    leader: 'Leader',
    coordinator: 'Coordinator',
    user: 'User',
};
</script>

<template>
    <AppLayout title="Adminユーザー詳細">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Adminユーザー詳細</h2>
                <div class="flex gap-2">
                    <Link
                        :href="route('superadmin.adminusers.edit', { adminuser: user.id })"
                        class="rounded bg-indigo-600 px-4 py-2 text-sm font-bold text-white hover:bg-indigo-700"
                    >
                        編集
                    </Link>
                    <Link
                        :href="route('superadmin.adminusers.index')"
                        class="rounded bg-gray-600 px-4 py-2 text-sm font-bold text-white hover:bg-gray-700"
                    >
                        一覧に戻る
                    </Link>
                </div>
            </div>
        </template>

        <div class="rounded bg-white p-6 shadow">
            <div class="mx-auto max-w-lg">
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
                    <dd class="text-sm text-gray-900">{{ roleLabel[user.user_role] ?? user.user_role }}</dd>
                </div>
                <div class="flex justify-between py-4">
                    <dt class="text-sm font-medium text-gray-500">会社</dt>
                    <dd class="text-sm text-gray-900">{{ user.company?.name ?? '未設定' }}</dd>
                </div>
                <div class="flex justify-between py-4">
                    <dt class="text-sm font-medium text-gray-500">部署</dt>
                    <dd class="text-sm text-gray-900">{{ user.department?.name ?? '未設定' }}</dd>
                </div>
                <div class="flex justify-between py-4">
                    <dt class="text-sm font-medium text-gray-500">担当</dt>
                    <dd class="text-sm text-gray-900">{{ user.assignment?.name ?? '未設定' }}</dd>
                </div>
                <div class="flex justify-between py-4">
                    <dt class="text-sm font-medium text-gray-500">登録日</dt>
                    <dd class="text-sm text-gray-900">{{ new Date(user.created_at).toLocaleDateString('ja-JP') }}</dd>
                </div>
            </dl>
            </div>
        </div>
    </AppLayout>
</template>
