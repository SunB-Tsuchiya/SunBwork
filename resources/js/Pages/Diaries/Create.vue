<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { useForm, Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const props = defineProps({
  date: String
})

const form = useForm({
  date: props.date,
  content: ''
})

const submit = () => {
  form.post(route('diaries.store'))
}
</script>

<template>
  <AppLayout title="日報作成">
    <div class="max-w-2xl mx-auto p-6 bg-white shadow rounded">
      <h1 class="text-2xl font-bold mb-4">日報作成 ({{ props.date }})</h1>
      <form @submit.prevent="submit">
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">日付</label>
          <input type="date" v-model="form.date" class="w-full border rounded p-2" />
        </div>
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">内容</label>
          <textarea v-model="form.content" class="w-full border rounded p-2 h-40"></textarea>
        </div>
        <div class="flex space-x-4">
          <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">保存</button>
          <Link :href="route('dashboard')" class="px-4 py-2 bg-gray-200 text-gray-700 rounded">キャンセル</Link>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
