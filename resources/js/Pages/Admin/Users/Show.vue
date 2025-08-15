<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
  user: Object,
});
</script>

<template>
  <AppLayout title="ユーザー詳細">
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
          ユーザー詳細
        </h2>
        <Link :href="route('admin.users.index')" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
          一覧に戻る
        </Link>
      </div>
    </template>
    <div class="py-12">
      <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow overflow-hidden sm:rounded-lg p-6">
          <h3 class="text-lg font-medium text-gray-900 mb-4">ユーザー情報</h3>
          <dl class="divide-y divide-gray-200">
            <div class="py-4 flex justify-between">
              <dt class="text-sm font-medium text-gray-500">名前</dt>
              <dd class="text-sm text-gray-900">{{ user.name }}</dd>
            </div>
            <div class="py-4 flex justify-between">
              <dt class="text-sm font-medium text-gray-500">メールアドレス</dt>
              <dd class="text-sm text-gray-900">{{ user.email }}</dd>
            </div>
            <div class="py-4 flex justify-between">
              <dt class="text-sm font-medium text-gray-500">権限</dt>
              <dd class="text-sm text-gray-900">{{ user.user_role }}</dd>
            </div>
            <div class="py-4 flex justify-between">
              <dt class="text-sm font-medium text-gray-500">登録日</dt>
              <dd class="text-sm text-gray-900">{{ new Date(user.created_at).toLocaleDateString('ja-JP') }}</dd>
            </div>
          </dl>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
