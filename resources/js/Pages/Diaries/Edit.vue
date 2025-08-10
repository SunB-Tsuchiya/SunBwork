<script setup>
import { QuillEditor } from '@vueup/vue-quill';
import '@vueup/vue-quill/dist/vue-quill.snow.css';
function formatJstDate(dateStr) {
  const d = new Date(dateStr);
  d.setHours(d.getHours() + 9);
  const yyyy = d.getFullYear();
  const mm = String(d.getMonth() + 1).padStart(2, '0');
  const dd = String(d.getDate()).padStart(2, '0');
  return `${yyyy}-${mm}-${dd}`;
}
import AppLayout from '@/layouts/AppLayout.vue'
import { useForm } from '@inertiajs/vue3'
import { Link, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const props = defineProps({ diary: Object })

const form = useForm({ content: props.diary.content })

const submit = () => {
  form.put(route('diaries.update', props.diary.id))
}
</script>

<template>
  <AppLayout title="日報編集">
    <div class="max-w-2xl mx-auto p-6 bg-white shadow rounded">
  <h1 class="text-2xl font-bold mb-4">日報編集 ({{ formatJstDate(props.diary.date) }})</h1>
      <form @submit.prevent="submit">
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">内容</label>
          <QuillEditor v-model="form.content" class="w-full" theme="snow" />
        </div>
        <div class="flex space-x-4">
          <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">更新</button>
          <Link :href="route('diaries.show', props.diary.id)" class="px-4 py-2 bg-gray-200 text-gray-700 rounded">戻る</Link>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
