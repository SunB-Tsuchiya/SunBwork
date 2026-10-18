<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import ClerkNavigationTabs from '@/Components/Tabs/ClerkNavigationTabs.vue';
import { Link } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

defineProps({
    types: { type: Array, default: () => [] },
});

// 連携キー → 機能画面のルート名
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
                <Link :href="route('clerk.general_affairs.application_types.index')" class="rounded bg-purple-600 px-4 py-2 text-sm font-medium text-white hover:bg-purple-700">
                    申請の種類を管理
                </Link>
            </div>

            <div v-if="types.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <template v-for="type in types" :key="type.id">
                    <!-- 機能画面と連携している種類（年末調整など）はリンクにする -->
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
            <p v-else class="py-8 text-center text-sm text-gray-500">
                申請の種類はまだありません。「申請の種類を管理」から登録してください。
            </p>
        </div>
    </AppLayout>
</template>
