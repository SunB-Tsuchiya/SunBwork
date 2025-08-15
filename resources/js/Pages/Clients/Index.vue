<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
const props = defineProps({ clients: Array });
</script>

<template>
  <AppLayout title="クライアント一覧">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        【進行管理】{{ $page.props.auth.user.name || 'ユーザー' }}さんのページ
      </h2>
    </template>
    <div class="max-w-3xl mx-auto py-10 sm:px-6 lg:px-8">
      <h1 class="text-2xl font-bold mb-6">クライアント一覧</h1>
      <template v-if="props.clients.length === 0">
        <p class="text-gray-500 py-8">クライアントはまだ登録されていません</p>
      </template>
      <template v-else>
        <table class="min-w-full">
          <thead>
            <tr>
              <th>ID</th>
              <th>名前</th>
              <th>詳細</th>
              <th>独自案件</th>
              <th>操作</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="client in props.clients" :key="client.id">
              <td>{{ client.id }}</td>
              <td>{{ client.name }}</td>
              <td>{{ client.detail }}</td>
              <td>{{ client.fromSA ? '○' : '' }}</td>
              <td>
                <Link :href="route('leader.clients.edit', client.id)">編集</Link>
              </td>
            </tr>
          </tbody>
        </table>
      </template>
      <Link :href="route('leader.clients.create')" class="mt-4 inline-block bg-blue-500 text-white px-4 py-2 rounded">新規作成</Link>
    </div>
  </AppLayout>
</template>
