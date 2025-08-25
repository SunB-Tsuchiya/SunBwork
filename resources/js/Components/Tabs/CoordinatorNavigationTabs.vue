<script setup>
import { Link } from '@inertiajs/vue3';
const props = defineProps({
  active: { type: String, default: '' }
});
</script>

<template>
  <div class="mb-6">
    <nav class="flex space-x-8" aria-label="Tabs">
      <Link :href="route('coordinator.project_jobs.index')"
        :class="[
          'px-3 py-2 font-medium text-sm rounded-md',
          active === 'projects' ? 'bg-green-100 text-green-700' : 'text-gray-600 hover:text-gray-800 border border-gray-200 hover:bg-green-50'
        ]">
        案件一覧
      </Link>
      <Link :href="route('profile.show')"
        class="text-gray-600 hover:text-gray-800 px-3 py-2 font-medium text-sm rounded-md">
        プロフィール編集
      </Link>
    </nav>
  </div>
</template>
