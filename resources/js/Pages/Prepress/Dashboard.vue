<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const user = page.props.user;
</script>

<template>
    <AppLayout title="Prepress Dashboard" :user="user">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                【製版】{{ user?.name || 'ユーザー' }}さんのページ
            </h2>
        </template>

        <div class="space-y-6">
            <!-- プロフィールカード -->
            <div class="overflow-hidden rounded-xl border border-green-100 bg-white shadow-sm">
                <div class="border-b border-green-100 bg-green-50 px-6 py-4">
                    <h3 class="flex items-center gap-2 font-semibold text-green-800">
                        <span class="text-lg">🖨️</span>
                        製版 プロフィール
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
                        <p class="text-xs font-medium text-gray-500">部署</p>
                        <p class="mt-1 text-sm text-gray-900">{{ user?.department?.name || '未設定' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500">権限レベル</p>
                        <p class="mt-1">
                            <span class="inline-flex items-center rounded-full bg-green-100 px-3 py-1 text-sm font-semibold text-green-800">
                                Prepress（製版）
                            </span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- クイックリンク -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <Link
                    :href="route('prepress.board')"
                    class="flex items-center gap-4 rounded-xl border border-green-200 bg-white p-5 shadow-sm transition hover:border-green-400 hover:shadow-md"
                >
                    <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-green-100 text-2xl">📋</div>
                    <div>
                        <p class="font-semibold text-green-800">伝票ボード</p>
                        <p class="text-sm text-gray-500">予定・作業中・完了をボードで管理</p>
                    </div>
                </Link>
                <Link
                    :href="route('prepress.tickets.index')"
                    class="flex items-center gap-4 rounded-xl border border-green-200 bg-white p-5 shadow-sm transition hover:border-green-400 hover:shadow-md"
                >
                    <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-green-100 text-2xl">📄</div>
                    <div>
                        <p class="font-semibold text-green-800">伝票一覧</p>
                        <p class="text-sm text-gray-500">伝票の検索・絞り込み・新規登録</p>
                    </div>
                </Link>
            </div>

            <!-- 準備中メッセージ -->
            <div class="rounded-xl border border-dashed border-green-300 bg-green-50 p-8 text-center">
                <div class="mb-3 text-4xl">🚧</div>
                <p class="font-semibold text-green-800">製版専用機能を順次追加予定です</p>
                <p class="mt-1 text-sm text-green-600">伝票ボード・伝票一覧から作業を管理できます。今後さらに機能が追加されます。</p>
            </div>
        </div>
    </AppLayout>
</template>
