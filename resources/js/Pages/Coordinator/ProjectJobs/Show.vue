<template>
  <AppLayout title="案件詳細">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        【進行管理】{{ $page.props.auth.user.name || 'ユーザー' }}さんのページ
      </h2>
    </template>
    <div class="max-w-2xl mx-auto p-6 bg-white rounded shadow">
      <h1 class="text-2xl font-bold mb-6">案件詳細</h1>
      <div class="mb-4"><strong>伝票番号:</strong> {{ job.jobcode }}</div>
      <div class="mb-4"><strong>案件タイトル:</strong> {{ job.name }}</div>
      <div class="mb-4"><strong>担当ユーザーID:</strong> {{ job.user_id }}</div>
      <div class="mb-4"><strong>クライアントID:</strong> {{ job.client_id }}</div>
      <div class="mb-4"><strong>詳細:</strong> {{ job.detail }}</div>
      <div class="flex gap-4 mt-6">
        <Link :href="route('coordinator.project_jobs.edit', job.id)" class="bg-yellow-200 px-4 py-2 rounded">編集</Link>
        <Link :href="route('coordinator.project_jobs.index')" class="bg-gray-200 px-4 py-2 rounded">一覧へ戻る</Link>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
const props = defineProps({ job: Object });
</script>

<style scoped>
</style>
