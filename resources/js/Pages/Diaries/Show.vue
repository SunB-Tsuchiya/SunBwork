<script setup>
function formatJstDate(dateStr) {
  const d = new Date(dateStr);
  d.setHours(d.getHours() + 9);
  const yyyy = d.getFullYear();
  const mm = String(d.getMonth() + 1).padStart(2, '0');
  const dd = String(d.getDate()).padStart(2, '0');
  return `${yyyy}-${mm}-${dd}`;
}
import AppLayout from '@/layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const props = defineProps({
  diary: Object
})

const deleteDiary = () => {
  if (confirm('この日報を削除してよろしいですか？')) {
    router.delete(route('diaries.destroy', props.diary.id))
  }
}
</script>

<template>
  <AppLayout title="日報表示">
    <div class="max-w-2xl mx-auto p-6 bg-white shadow rounded">
  <h1 class="text-2xl font-bold mb-4">日報 {{ formatJstDate(props.diary.date) }}</h1>
      <div class="prose mb-6">
        <p v-html="props.diary.content"></p>
      </div>
      <div class="flex space-x-4">
  <Link :href="route('diaries.create')" class="px-4 py-2 bg-green-600 text-white rounded">新しく日報を書く</Link>
  <Link :href="route('diaries.edit', props.diary.id)" class="px-4 py-2 bg-blue-600 text-white rounded">編集</Link>
  <button @click="deleteDiary" class="px-4 py-2 bg-red-600 text-white rounded">削除</button>
  <Link :href="route('dashboard')" class="px-4 py-2 bg-gray-200 text-gray-700 rounded">戻る</Link>
      </div>
    </div>
  </AppLayout>
</template>
