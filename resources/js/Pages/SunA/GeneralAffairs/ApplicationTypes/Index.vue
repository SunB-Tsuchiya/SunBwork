<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import ClerkNavigationTabs from '@/Components/Tabs/ClerkNavigationTabs.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { route } from 'ziggy-js';

defineProps({
    types: { type: Array, default: () => [] },
    features: { type: Object, default: () => ({}) },
});

const busyId = ref(null);

function toggle(type) {
    const action = type.is_active ? '停止' : '再開';
    if (!confirm(`「${type.name}」を${action}しますか？`)) return;
    busyId.value = type.id;
    router.patch(
        route('clerk.general_affairs.application_types.toggle', { applicationType: type.id }),
        { is_active: !type.is_active },
        { preserveScroll: true, onFinish: () => { busyId.value = null; } },
    );
}

function remove(type) {
    if (!confirm(`「${type.name}」を削除しますか？`)) return;
    busyId.value = type.id;
    router.delete(route('clerk.general_affairs.application_types.destroy', { applicationType: type.id }), {
        preserveScroll: true,
        onFinish: () => { busyId.value = null; },
    });
}
</script>

<template>
    <AppLayout title="申請の種類">
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('clerk.general_affairs.applications.index')" class="whitespace-nowrap rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300">
                    ← 申請一覧に戻る
                </Link>
                <h2 class="text-base font-semibold text-gray-800 sm:text-xl">申請の種類</h2>
            </div>
        </template>
        <template #tabs>
            <ClerkNavigationTabs active="applications" />
        </template>

        <div class="rounded bg-white p-6 shadow">
            <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-gray-600">申請一覧に並ぶ申請の種類を管理します。停止した種類は申請一覧に表示されません。</p>
                <Link :href="route('clerk.general_affairs.application_types.create')" class="rounded bg-purple-600 px-4 py-2 text-sm font-medium text-white hover:bg-purple-700">
                    申請の種類を追加
                </Link>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-3 text-left">並び順</th>
                            <th class="px-3 py-3 text-left">申請の名前</th>
                            <th class="px-3 py-3 text-left">説明</th>
                            <th class="px-3 py-3 text-left">連携する機能</th>
                            <th class="px-3 py-3 text-left">状態</th>
                            <th class="px-3 py-3 text-right">操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="type in types" :key="type.id" class="hover:bg-gray-50">
                            <td class="whitespace-nowrap px-3 py-3">{{ type.sort_order }}</td>
                            <td class="px-3 py-3 font-medium text-gray-800">{{ type.name }}</td>
                            <td class="min-w-60 whitespace-pre-wrap break-words px-3 py-3 text-gray-600">{{ type.description }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-gray-600">{{ features[type.feature_key] ?? '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-3">
                                <span class="rounded-full px-2 py-1 text-xs font-medium" :class="type.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'">
                                    {{ type.is_active ? '有効' : '停止中' }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-3 py-3 text-right">
                                <Link :href="route('clerk.general_affairs.application_types.edit', { applicationType: type.id })" class="mr-3 text-purple-700 hover:underline">編集</Link>
                                <button :disabled="busyId === type.id" class="mr-3 text-gray-600 hover:underline disabled:opacity-50" @click="toggle(type)">
                                    {{ type.is_active ? '停止' : '再開' }}
                                </button>
                                <button :disabled="busyId === type.id" class="text-red-600 hover:underline disabled:opacity-50" @click="remove(type)">削除</button>
                            </td>
                        </tr>
                        <tr v-if="!types.length">
                            <td colspan="6" class="px-3 py-8 text-center text-gray-500">申請の種類はまだありません。</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
