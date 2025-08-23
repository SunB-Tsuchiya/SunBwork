<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full bg-white p-8 rounded shadow">
      <h1 class="text-2xl font-bold text-red-600 mb-4">アクセス権限がありません (403)</h1>
      <p class="text-sm text-gray-700 mb-4">この操作を行う権限がありません。自社のリソースにのみアクセスできます。必要な場合はシステム管理者にお問い合わせください。</p>
      <div class="flex justify-end">
        <Link href="/" class="px-4 py-2 bg-blue-600 text-white rounded">ホームへ戻る</Link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
</script>
