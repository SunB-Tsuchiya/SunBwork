<script setup>
import { Link } from '@inertiajs/vue3';
const props = defineProps({
    active: { type: String, default: '' },
});
// User カラー: blue
const tab = (key) => [
    'rounded-md px-3 py-2 text-sm font-medium',
    props.active === key
        ? 'bg-blue-100 text-blue-700'
        : 'border border-blue-200 text-blue-600 hover:bg-blue-50 hover:text-blue-800',
];
</script>

<template>
    <div class="mb-6">
        <nav class="flex flex-wrap gap-2" aria-label="Tabs">
            <Link href="/myjobbox" :class="tab('myjob')">
                マイジョブ
            </Link>
            <Link href="/user/jobbox" :class="tab('jobbox')">
                依頼されたジョブ
            </Link>
            <Link :href="route('diaries.index')" :class="tab('diaries')">
                日報一覧
            </Link>
            <Link :href="route('calendar.index')" :class="tab('calendar')">
                予定表
            </Link>
            <Link :href="route('user.settings.index')" :class="tab('settings')">
                設定
            </Link>
        </nav>
    </div>
</template>
