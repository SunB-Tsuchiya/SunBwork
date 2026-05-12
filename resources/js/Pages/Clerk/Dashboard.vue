<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const user = page.props.user;
</script>

<template>
    <AppLayout title="Clerk Dashboard" :user="user">
        <template #header>
            <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">
                【クラーク】{{ user?.name || 'ユーザー' }}さんのページ
            </h2>
        </template>

        <div class="space-y-6">
            <!-- プロフィールカード -->
            <div class="overflow-hidden rounded-xl border border-purple-100 bg-white shadow-sm">
                <div class="border-b border-purple-100 bg-purple-50 px-6 py-4">
                    <h3 class="flex items-center gap-2 font-semibold text-purple-800">
                        <span class="text-lg">🗃️</span>
                        クラーク プロフィール
                    </h3>
                </div>
                <div class="grid grid-cols-1 gap-x-8 gap-y-4 p-6 md:grid-cols-2">
                    <div>
                        <p class="text-xs font-medium text-gray-500">名前</p>
                        <p class="mt-1 text-sm text-gray-900">{{ user?.name || '未設定' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500">メールアドレス</p>
                        <p class="mt-1 text-sm text-gray-900">{{ user?.email || '未設定' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500">会社・部署</p>
                        <p class="mt-1 text-sm text-gray-900">
                            {{ user?.company?.name || '未設定' }}
                            <span v-if="user?.department?.name"> — {{ user.department.name }}</span>
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500">担当</p>
                        <p class="mt-1 text-sm text-gray-900">{{ user?.assignment?.name || '未設定' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500">権限レベル</p>
                        <p class="mt-1">
                            <span class="inline-flex items-center rounded-full bg-purple-100 px-3 py-1 text-sm font-semibold text-purple-700">
                                Clerk（経理・事務）
                            </span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- 準備中メッセージ -->
            <div class="rounded-xl border border-dashed border-purple-200 bg-purple-50 p-8 text-center">
                <div class="mb-3 text-4xl">🚧</div>
                <p class="font-semibold text-purple-700">Clerk 専用機能は準備中です</p>
                <p class="mt-1 text-sm text-purple-500">経理・事務向けの機能はこちらから利用できるようになります。</p>
                <div class="mt-4 flex flex-wrap justify-center gap-3">
                    <Link :href="route('coordinator.dashboard')" class="inline-flex items-center gap-1 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">
                        Coordinator エリアへ
                    </Link>
                    <Link :href="route('user.dashboard')" class="inline-flex items-center gap-1 rounded-lg bg-blue-500 px-4 py-2 text-sm font-medium text-white hover:bg-blue-600">
                        User エリアへ
                    </Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
