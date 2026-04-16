<template>
    <AppLayout :title="job.title || '案件詳細'">
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('leader.project_jobs.index')" class="text-sm text-orange-600 hover:underline">← 案件総覧</Link>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">案件詳細</h2>
            </div>
        </template>

        <div class="space-y-4">
            <!-- 基本情報 -->
            <div class="overflow-hidden rounded-xl border border-orange-100 bg-white shadow-sm">
                <div class="border-b border-orange-100 bg-orange-50 px-6 py-4">
                    <h3 class="font-semibold text-orange-800">基本情報</h3>
                </div>
                <div class="grid grid-cols-1 gap-x-8 gap-y-4 p-6 md:grid-cols-2">
                    <div>
                        <p class="text-xs font-medium text-gray-500">案件名</p>
                        <p class="mt-1 text-sm text-gray-900">{{ job.title || '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500">ジョブコード</p>
                        <p class="mt-1 text-sm text-gray-900">{{ job.jobcode || '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500">クライアント</p>
                        <p class="mt-1 text-sm text-gray-900">{{ job.client?.name || '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500">担当Coordinator</p>
                        <p class="mt-1 text-sm text-gray-900">{{ job.user?.name || '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500">ステータス</p>
                        <p class="mt-1">
                            <span
                                :class="job.completed ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-700'"
                                class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                            >{{ job.completed ? '完了' : '進行中' }}</span>
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500">登録日</p>
                        <p class="mt-1 text-sm text-gray-900">{{ formatDate(job.created_at) }}</p>
                    </div>
                    <div v-if="job.detail" class="md:col-span-2">
                        <p class="text-xs font-medium text-gray-500">詳細</p>
                        <p class="mt-1 whitespace-pre-wrap text-sm text-gray-900">{{ job.detail }}</p>
                    </div>
                </div>
            </div>

            <!-- サブCoordinator -->
            <div v-if="job.coordinators && job.coordinators.length > 0" class="overflow-hidden rounded-xl border border-orange-100 bg-white shadow-sm">
                <div class="border-b border-orange-100 bg-orange-50 px-6 py-4">
                    <h3 class="font-semibold text-orange-800">サブCoordinator</h3>
                </div>
                <ul class="divide-y divide-gray-100 px-6 py-2">
                    <li v-for="co in job.coordinators" :key="co.id" class="py-2 text-sm text-gray-900">
                        {{ co.name }}
                    </li>
                </ul>
            </div>

            <!-- チームメンバー -->
            <div v-if="job.team_members && job.team_members.length > 0" class="overflow-hidden rounded-xl border border-orange-100 bg-white shadow-sm">
                <div class="border-b border-orange-100 bg-orange-50 px-6 py-4">
                    <h3 class="font-semibold text-orange-800">チームメンバー</h3>
                </div>
                <ul class="divide-y divide-gray-100 px-6 py-2">
                    <li v-for="tm in job.team_members" :key="tm.id" class="py-2 text-sm text-gray-900">
                        {{ tm.user?.name || '-' }}
                    </li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    job: Object,
});

function formatDate(dateStr) {
    if (!dateStr) return '-';
    try {
        return String(dateStr).split('T')[0].split(' ')[0];
    } catch {
        return String(dateStr);
    }
}
</script>
