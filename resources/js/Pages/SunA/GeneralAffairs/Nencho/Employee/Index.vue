<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import UserNavigationTabs from '@/Components/Tabs/UserNavigationTabs.vue';
import { Link, router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

defineProps({
    items: { type: Array, default: () => [] },
    statuses: { type: Object, default: () => ({}) },
});

const badge = (s) => ({ draft: 'bg-amber-100 text-amber-800', submitted: 'bg-green-100 text-green-700', returned: 'bg-red-100 text-red-700' }[s] ?? 'bg-gray-100 text-gray-600');

function start(item) {
    router.post(route('user.general_affairs.nencho.start', { campaign: item.campaign_id }));
}
</script>

<template>
    <AppLayout title="年末調整">
        <template #header>
            <h2 class="text-base font-semibold text-gray-800 sm:text-xl">年末調整</h2>
        </template>
        <template #tabs>
            <UserNavigationTabs active="nencho" />
        </template>

        <div class="space-y-4">
            <div v-for="item in items" :key="item.campaign_id" class="rounded bg-white p-6 shadow">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800">{{ item.title }}</h3>
                        <p class="text-sm text-gray-500">{{ item.company_name }}　受付期間：{{ item.opens_on }} ～ {{ item.due_on }}</p>
                    </div>
                    <span v-if="item.status" class="rounded-full px-3 py-1 text-xs font-medium" :class="badge(item.status)">{{ statuses[item.status] ?? item.status }}</span>
                    <span v-else class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600">未入力</span>
                </div>
                <p v-if="item.message" class="mb-4 whitespace-pre-wrap rounded bg-blue-50 p-3 text-sm text-gray-700">{{ item.message }}</p>
                <p v-if="item.return_comment" class="mb-3 whitespace-pre-wrap rounded border border-red-200 bg-red-50 p-3 text-sm text-red-800">総務からの差戻し：{{ item.return_comment }}</p>
                <p v-if="item.submitted_at" class="mb-3 text-xs text-gray-500">提出日時：{{ item.submitted_at }}</p>

                <div class="flex flex-wrap items-center gap-3">
                    <button v-if="!item.submission_id && item.is_open" class="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" @click="start(item)">入力を始める</button>
                    <Link v-else-if="item.submission_id" :href="route('user.general_affairs.nencho.show', { submission: item.submission_id })" class="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                        {{ item.status === 'returned' ? '差戻しの内容を確認して修正する' : item.is_open && item.status !== 'submitted' ? '続きから入力する' : '内容を見る' }}
                    </Link>
                    <span v-if="!item.submission_id && !item.is_open" class="text-sm text-gray-500">現在は入力できません（受付期間外です）。</span>
                </div>
            </div>
            <div v-if="!items.length" class="rounded bg-white p-8 text-center text-sm text-gray-500 shadow">対象の受付はありません。</div>
        </div>
    </AppLayout>
</template>
