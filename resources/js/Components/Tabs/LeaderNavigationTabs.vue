<script setup>
import { Link } from '@inertiajs/vue3';
const props = defineProps({
  active: { type: String, default: '' }
});
</script>

<template>
  <div class="mb-6">
    <nav class="flex space-x-8" aria-label="Tabs">
      <Link :href="route('leader.clients.index')"
        :class="[
          'px-3 py-2 font-medium text-sm rounded-md',
          active === 'clients' ? 'bg-orange-100 text-orange-700' : 'text-green-600 hover:text-green-800 border border-green-200 hover:bg-green-50'
        ]">
        クライアント管理
      </Link>
      <Link :href="route('profile.show')"
        :class="[
          'px-3 py-2 font-medium text-sm rounded-md',
          active === 'profile' ? 'bg-gray-100 text-gray-700' : 'text-gray-600 hover:text-gray-800'
        ]">
        プロフィール編集
      </Link>
    </nav>
  </div>
</template>
