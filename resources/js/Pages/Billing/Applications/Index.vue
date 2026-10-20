<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import ClerkNavigationTabs from '@/Components/Tabs/ClerkNavigationTabs.vue';
import { Link } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

defineProps({
    transportPending: { type: Number, default: 0 },
    fareUnverified: { type: Number, default: 0 },
    deadlinePending: { type: Number, default: 0 },
    showTypes: { type: Boolean, default: false },
    types: { type: Array, default: () => [] },
});

// 連携キー → 機能画面のルート名（サンエー印刷の申請の種類）
const FEATURE_ROUTES = { nencho: 'clerk.general_affairs.nencho.index' };
const featureHref = (type) => (FEATURE_ROUTES[type.feature_key] ? route(FEATURE_ROUTES[type.feature_key]) : null);
</script>

<template>
    <AppLayout title="申請">
        <template #header>
            <h2 class="text-base font-semibold text-gray-800 sm:text-xl">申請</h2>
        </template>
        <template #tabs>
            <ClerkNavigationTabs active="applications" />
        </template>

        <div class="rounded bg-white p-6 shadow">
            <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                <h3 class="text-lg font-semibold text-gray-800">申請一覧</h3>
                <Link v-if="showTypes" :href="route('clerk.general_affairs.application_types.index')" class="rounded bg-purple-600 px-4 py-2 text-sm font-medium text-white hover:bg-purple-700">
                    申請の種類を管理
                </Link>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <!-- 交通費（両社共通） -->
                <Link :href="route('clerk.billing.transport.index')" class="block rounded-lg border border-purple-200 bg-purple-50 p-4 transition hover:bg-purple-100">
                    <div class="flex items-start justify-between gap-2">
                        <h4 class="font-semibold text-purple-900">交通費</h4>
                        <span class="flex shrink-0 flex-col items-end gap-1">
                            <span v-if="transportPending > 0" class="rounded-full bg-red-500 px-2 py-0.5 text-xs font-medium text-white">申請済み {{ transportPending }}件</span>
                            <span v-if="deadlinePending > 0" class="rounded-full bg-amber-500 px-2 py-0.5 text-xs font-medium text-white">期限後の依頼 {{ deadlinePending }}件</span>
                        </span>
                    </div>
                    <p class="mt-2 whitespace-pre-wrap break-words text-sm text-gray-600">月ごとの交通費の申請を、受理・差戻し・請求済みにします。月次集計も出せます。</p>
                </Link>

                <!-- 運賃マスタ（検算用の、経路と運賃の台帳） -->
                <Link :href="route('clerk.billing.transport.fares.index')" class="block rounded-lg border border-purple-200 bg-purple-50 p-4 transition hover:bg-purple-100">
                    <div class="flex items-start justify-between gap-2">
                        <h4 class="font-semibold text-purple-900">運賃マスタ</h4>
                        <span v-if="fareUnverified > 0" class="shrink-0 rounded-full bg-amber-500 px-2 py-0.5 text-xs font-medium text-white">未検査 {{ fareUnverified }}件</span>
                    </div>
                    <p class="mt-2 whitespace-pre-wrap break-words text-sm text-gray-600">よく使う経路と運賃を登録すると、申請の詳細画面で、運賃を自動で検算します。申請された未登録の経路は、候補に溜まります。</p>
                </Link>

                <!-- 申請の種類（年末調整など。サンエー印刷の総務のみ） -->
                <template v-if="showTypes" v-for="type in types" :key="type.id">
                    <Link v-if="featureHref(type)" :href="featureHref(type)" class="block rounded-lg border border-purple-200 bg-purple-50 p-4 transition hover:bg-purple-100">
                        <h4 class="font-semibold text-purple-900">{{ type.name }}</h4>
                        <p v-if="type.description" class="mt-2 whitespace-pre-wrap break-words text-sm text-gray-600">{{ type.description }}</p>
                    </Link>
                    <div v-else class="rounded-lg border border-purple-200 bg-purple-50 p-4">
                        <h4 class="font-semibold text-purple-900">{{ type.name }}</h4>
                        <p v-if="type.description" class="mt-2 whitespace-pre-wrap break-words text-sm text-gray-600">{{ type.description }}</p>
                    </div>
                </template>
            </div>
        </div>
    </AppLayout>
</template>
