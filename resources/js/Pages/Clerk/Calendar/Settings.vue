<script setup>
import { Link } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AppLayout from '@/layouts/AppLayout.vue';
</script>

<template>
    <AppLayout title="カレンダー設定">
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('clerk.calendar')" class="whitespace-nowrap rounded bg-gray-200 px-3 py-1.5 text-sm hover:bg-gray-300">← カレンダーに戻る</Link>
                <h2 class="text-base font-semibold text-gray-800 sm:text-xl">カレンダー設定</h2>
            </div>
        </template>
        <div class="rounded bg-white p-6 shadow">
            <p class="mb-4 text-sm text-gray-600">会社で共有するカレンダーの設定です。</p>
            <Link :href="route('clerk.calendar.holidays.index')" class="block rounded border border-gray-200 p-4 hover:border-purple-400 hover:bg-purple-50">
                <h3 class="font-semibold text-purple-700">祝日設定 →</h3>
                <p class="mt-1 text-sm text-gray-600">年ごとに祝日を確認し、追加・修正・削除できます。</p>
            </Link>
            <Link :href="route('clerk.calendar.schedule_rules.index')" class="mt-3 block rounded border border-gray-200 p-4 hover:border-purple-400 hover:bg-purple-50">
                <h3 class="font-semibold text-purple-700">予定日設定 →</h3>
                <p class="mt-1 text-sm text-gray-600">月末・毎月の曜日・指定日などに、同じ内容の予定を自動登録します。</p>
            </Link>
        </div>
    </AppLayout>
</template>
