<script setup>
import { Link } from '@inertiajs/vue3';
const props = defineProps({
  active: { type: String, default: '' }
});
</script>

<template>
  <div class="mb-6">
    <nav class="flex space-x-8" aria-label="Tabs">
      <Link :href="route('admin.users.index')"
        :class="[
          'px-3 py-2 font-medium text-sm rounded-md',
          active === 'users' ? 'bg-red-100 text-red-700' : 'text-red-600 hover:text-red-800 border border-red-200 hover:bg-red-50'
        ]">
        ユーザー管理
      </Link>
      <Link :href="route('leader.dashboard')"
        class="text-orange-600 hover:text-orange-800 px-3 py-2 font-medium text-sm rounded-md border border-orange-200 hover:bg-orange-50">
        リーダーモードに切り替え
      </Link>
      <Link :href="route('user.dashboard')"
        class="text-blue-600 hover:text-blue-800 px-3 py-2 font-medium text-sm rounded-md border border-blue-200 hover:bg-blue-50">
        ユーザーモードに切り替え
      </Link>
    </nav>
  </div>
</template>
