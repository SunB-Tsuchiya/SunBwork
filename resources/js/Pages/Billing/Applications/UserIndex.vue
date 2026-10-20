<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import UserNavigationTabs from '@/Components/Tabs/UserNavigationTabs.vue';
import { Link } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

defineProps({
    month:        { type: String, required: true },
    current:      { type: Object, default: null },
    returned:     { type: Array, default: () => [] },
    statusLabels: { type: Object, required: true },
});

const statusColor = {
    draft: 'bg-gray-100 text-gray-700',
    submitted: 'bg-blue-100 text-blue-700',
    returned: 'bg-red-100 text-red-700',
    approved: 'bg-green-100 text-green-700',
    billed: 'bg-purple-100 text-purple-700',
};
const monthLabel = (ym) => { const [y, m] = ym.split('-'); return `${y}年${parseInt(m)}月分`; };
</script>

<template>
    <AppLayout title="申請">
        <template #header>
            <h2 class="text-base font-semibold text-gray-800 sm:text-xl">申請</h2>
        </template>
        <template #tabs>
            <UserNavigationTabs active="applications" />
        </template>

        <div class="rounded bg-white p-6 shadow">
            <h3 class="mb-5 text-lg font-semibold text-gray-800">申請一覧</h3>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <!-- 交通費 -->
                <Link :href="route('user.billing.transport.index')" class="block rounded-lg border border-blue-200 bg-blue-50 p-4 transition hover:bg-blue-100">
                    <div class="flex items-start justify-between gap-2">
                        <h4 class="font-semibold text-blue-900">交通費</h4>
                        <span v-if="returned.length" class="shrink-0 rounded-full bg-red-500 px-2 py-0.5 text-xs font-medium text-white">差戻し {{ returned.length }}件</span>
                    </div>
                    <p class="mt-2 whitespace-pre-wrap break-words text-sm text-gray-600">月ごとの交通費を入力して、申請します。入力は自動で保存されます。申請書（PDF・Excel）も出力できます。</p>
                    <p class="mt-2 text-xs text-gray-600">
                        {{ monthLabel(month) }}:
                        <span v-if="current" class="rounded px-1.5 py-0.5" :class="statusColor[current.status]">{{ statusLabels[current.status] }}</span>
                        <span v-else class="text-gray-500">未入力</span>
                    </p>
                    <p v-if="returned.length" class="mt-1 text-xs text-red-700">差戻し: {{ returned.map(monthLabel).join('、') }}（直して、再申請してください）</p>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
