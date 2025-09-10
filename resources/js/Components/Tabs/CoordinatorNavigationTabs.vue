<script setup>
import { Link } from '@inertiajs/vue3';
const props = defineProps({
    active: { type: String, default: '' },
});
</script>

<template>
    <div class="mb-6">
        <nav class="flex space-x-8" aria-label="Tabs">
            <Link
                :href="route('coordinator.project_jobs.index')"
                :class="[
                    'rounded-md px-3 py-2 text-sm font-medium',
                    active === 'projects'
                        ? 'bg-green-100 text-green-700'
                        : 'border border-gray-200 text-gray-600 hover:bg-green-50 hover:text-gray-800',
                ]"
            >
                案件一覧
            </Link>
            <Link
                :href="route('coordinator.work_items.index')"
                :class="[
                    'rounded-md px-3 py-2 text-sm font-medium',
                    active === 'workitems'
                        ? 'bg-green-100 text-green-700'
                        : 'border border-gray-200 text-gray-600 hover:bg-green-50 hover:text-gray-800',
                ]"
            >
                Work Items
            </Link>
            <Link
                :href="route('coordinator.project_jobs.index')"
                :class="[
                    'rounded-md px-3 py-2 text-sm font-medium',
                    active === 'projects'
                        ? 'bg-green-100 text-green-700'
                        : 'border border-gray-200 text-gray-600 hover:bg-green-50 hover:text-gray-800',
                ]"
            >
                作業例作成
            </Link>
            <Link :href="route('profile.show')" class="rounded-md px-3 py-2 text-sm font-medium text-gray-600 hover:text-gray-800">
                プロフィール編集
            </Link>
        </nav>
    </div>
</template>
