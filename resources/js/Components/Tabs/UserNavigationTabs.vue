<script setup>
import { Link } from '@inertiajs/vue3';
const props = defineProps({
    active: { type: String, default: '' },
});
</script>

<template>
    <div class="mb-6">
        <nav class="flex space-x-8" aria-label="Tabs">
            <!-- <Link :href="route('profile.show')" class="rounded-md px-3 py-2 text-sm font-medium text-gray-600 hover:text-gray-800">
                プロフィール編集
            </Link> -->
            <Link href="/myjobbox" class="rounded-md px-3 py-2 text-sm font-medium text-gray-600 hover:text-gray-800"> マイジョブ </Link>
            <Link href="/user/jobbox" class="rounded-md px-3 py-2 text-sm font-medium text-gray-600 hover:text-gray-800"> 依頼されたジョブ </Link>
            <Link :href="route('diaries.index')" class="rounded-md px-3 py-2 text-sm font-medium text-gray-600 hover:text-gray-800"> 日報一覧 </Link>
            <Link :href="route('calendar.index')" class="rounded-md px-3 py-2 text-sm font-medium text-green-600 hover:text-green-800"> 予定表 </Link>
            <Link :href="route('chat.rooms.index')" class="rounded-md px-3 py-2 text-sm font-medium text-purple-600 hover:text-purple-800">
                チャット
            </Link>
        </nav>
    </div>
</template>
