<script setup>
import { Link } from '@inertiajs/vue3';
const props = defineProps({
  active: { type: String, default: '' }
});
</script>

<template>
  <div class="mb-6">
    <nav class="flex space-x-8" aria-label="Tabs">
      <Link :href="route('profile.show')" class="text-gray-600 hover:text-gray-800 px-3 py-2 font-medium text-sm rounded-md">
        プロフィール編集
      </Link>
      <Link :href="route('user.assigned-projects.index')" class="text-gray-600 hover:text-gray-800 px-3 py-2 font-medium text-sm rounded-md">
        ジョブ一覧
      </Link>
      <Link :href="route('diaries.index')" class="text-gray-600 hover:text-gray-800 px-3 py-2 font-medium text-sm rounded-md">
        日報一覧
      </Link>
      <Link :href="route('calendar.index')" class="text-green-600 hover:text-green-800 px-3 py-2 font-medium text-sm rounded-md">
        予定表
      </Link>
      <Link :href="route('chat.rooms.index')" class="text-purple-600 hover:text-purple-800 px-3 py-2 font-medium text-sm rounded-md">
        チャット
      </Link>
    </nav>
  </div>
</template>
