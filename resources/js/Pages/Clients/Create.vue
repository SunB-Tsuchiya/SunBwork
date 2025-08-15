<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { useForm } from '@inertiajs/vue3';

const form = useForm({
  name: '',
  detail: '',
  fromSA: false,
});

function submit() {
  form.post(route('leader.clients.store'));
}
</script>

<template>
  <AppLayout title="クライアント新規作成">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        リーダー{{ $page.props.auth.user.name || 'ユーザー' }}さんのページ
      </h2>
    </template>
    <div class="max-w-3xl mx-auto py-10 sm:px-6 lg:px-8">
      <h1 class="text-2xl font-bold mb-6">クライアント新規作成</h1>
      <form @submit.prevent="submit">
        <div class="mb-4">
          <label class="block mb-1">名前</label>
          <input v-model="form.name" type="text" required class="border rounded px-2 py-1 w-full" />
        </div>
        <div class="mb-4">
          <label class="block mb-1">詳細</label>
          <textarea v-model="form.detail" class="border rounded px-2 py-1 w-full"></textarea>
        </div>
        <div class="mb-4">
          <label class="inline-flex items-center">
            <input type="checkbox" v-model="form.fromSA" class="mr-2" /> 独自案件
          </label>
        </div>
        <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded">登録</button>
      </form>
    </div>
  </AppLayout>
</template>
