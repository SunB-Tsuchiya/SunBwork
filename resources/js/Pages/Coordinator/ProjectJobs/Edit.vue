<template>
  <AppLayout title="案件編集">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        【進行管理】{{ $page.props.auth.user.name || 'ユーザー' }}さんのページ
      </h2>
    </template>
    <div class="max-w-2xl mx-auto p-6 bg-white rounded shadow">
      <h1 class="text-2xl font-bold mb-6">案件編集</h1>
      <form @submit.prevent="submit">
        <div class="mb-4">
          <label class="block mb-1 font-semibold">伝票番号</label>
          <input v-model="form.jobcode" type="text" class="w-full border rounded px-3 py-2" required />
        </div>
        <div class="mb-4">
          <label class="block mb-1 font-semibold">案件タイトル</label>
          <input v-model="form.name" type="text" class="w-full border rounded px-3 py-2" required />
        </div>
        <div class="mb-4">
          <label class="block mb-1 font-semibold">担当ユーザーID</label>
          <input v-model="form.user_id" type="number" class="w-full border rounded px-3 py-2 bg-gray-100" readonly />
        </div>
        <div class="mb-4">
          <label class="block mb-1 font-semibold">クライアントID</label>
          <input v-model="form.client_id" type="number" class="w-full border rounded px-3 py-2 bg-gray-100" readonly />
        </div>
        <div class="mb-4">
          <label class="block mb-1 font-semibold">詳細</label>
          <textarea v-model="form.detail" class="w-full border rounded px-3 py-2" rows="3"></textarea>
        </div>
        <div class="mt-6">
          <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">更新</button>
          <Link :href="route('coordinator.project_jobs.index')" class="ml-4 bg-gray-200 px-4 py-2 rounded">一覧へ戻る</Link>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
const props = defineProps({ job: Object });
const form = useForm({ ...props.job });
function submit() {
  form.put(route('coordinator.project_jobs.update', props.job.id));
}
</script>

<style scoped>
</style>
