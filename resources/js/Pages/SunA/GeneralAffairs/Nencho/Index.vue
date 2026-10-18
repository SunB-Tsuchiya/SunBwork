<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import ClerkNavigationTabs from '@/Components/Tabs/ClerkNavigationTabs.vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps({
    abilities: { type: Object, default: () => ({}) },
});

// 年末調整の項目（メニュー）。href があるものだけリンクにする。準備中は今後の追加予定の表示
const items = computed(() => [
    { key: 'guide', title: '設計ガイド', description: '年末調整フォームの概要・流れ・決定事項・今後の予定を確認できます。', href: route('clerk.general_affairs.nencho.guide') },
    // 受付・回収は権限「年末調整を管理する」が必要
    props.abilities.manageNencho
        ? { key: 'campaigns', title: '受付', description: '会社ごと・年度ごとの受付（開始日・締切・案内文）を作成し、対象者を管理します。', href: route('clerk.general_affairs.nencho.campaigns.index') }
        : { key: 'campaigns', title: '受付', description: '「年末調整を管理する」権限が必要です。管理者にご相談ください。', href: null, locked: true },
    { key: 'status', title: '回収状況', description: '従業員の入力・提出の状況を確認し、差戻しや代理入力を行います。', href: null },
]);
</script>

<template>
    <AppLayout title="年末調整">
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('clerk.general_affairs.applications.index')" class="whitespace-nowrap rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300">
                    ← 申請一覧に戻る
                </Link>
                <h2 class="text-base font-semibold text-gray-800 sm:text-xl">年末調整</h2>
            </div>
        </template>
        <template #tabs>
            <ClerkNavigationTabs active="applications" />
        </template>

        <div class="rounded bg-white p-6 shadow">
            <p class="mb-5 text-sm text-gray-600">年末調整に関する項目です。順次、項目を追加していきます。</p>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <template v-for="item in items" :key="item.key">
                    <Link v-if="item.href" :href="item.href" class="block rounded-lg border border-purple-200 bg-purple-50 p-4 transition hover:bg-purple-100">
                        <h4 class="font-semibold text-purple-900">{{ item.title }}</h4>
                        <p class="mt-2 text-sm text-gray-600">{{ item.description }}</p>
                    </Link>
                    <div v-else class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-4">
                        <h4 class="font-semibold text-gray-500">{{ item.title }} <span class="ml-1 rounded-full bg-gray-200 px-2 py-0.5 text-xs font-medium text-gray-600">{{ item.locked ? '権限なし' : '準備中' }}</span></h4>
                        <p class="mt-2 text-sm text-gray-500">{{ item.description }}</p>
                    </div>
                </template>
            </div>
        </div>
    </AppLayout>
</template>
