<template>
  <AppLayout title="クライアント詳細">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        【進行管理】{{ $page.props.auth.user.name || 'ユーザー' }}さんのページ
      </h2>
    </template>
    <div class="max-w-2xl mx-auto p-6 bg-white rounded shadow">
      <h1 class="text-2xl font-bold mb-6">クライアント詳細</h1>
      <div class="mb-4"><strong>ID:</strong> {{ client.id }}</div>
      <div class="mb-4"><strong>会社名:</strong> {{ client.name }}</div>
      <div class="mb-4"><strong>詳細:</strong> {{ client.detail }}</div>
      <div class="mb-4"><strong>独自案件:</strong> {{ client.fromSA ? '○' : '' }}</div>
      <div class="flex gap-4 mt-6">
  <Link :href="route('leader.clients.edit', client.id)" class="bg-yellow-200 px-4 py-2 rounded">編集</Link>
  <Link :href="route('leader.clients.index')" class="bg-gray-200 px-4 py-2 rounded">一覧へ戻る</Link>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
const props = defineProps({ client: Object });
</script>

<style scoped>
</style>
