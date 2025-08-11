<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

const props = defineProps({ event: Object });

function formatJstDateTime(dateStr) {
  if (!dateStr) return '';
  const d = new Date(dateStr);
  d.setHours(d.getHours() + 9);
  const yyyy = d.getFullYear();
  const mm = String(d.getMonth() + 1).padStart(2, '0');
  const dd = String(d.getDate()).padStart(2, '0');
  const hh = String(d.getHours()).padStart(2, '0');
  const min = String(d.getMinutes()).padStart(2, '0');
  return `${yyyy}-${mm}-${dd} ${hh}:${min}`;
}

console.log('[Show.vue] event:', props.event);
console.log('[Show.vue] event.description:', props.event.description);
</script>

<template>
  <AppLayout title="イベント表示">
    <div class="max-w-2xl mx-auto p-6 bg-white shadow rounded">
      <h1 class="text-2xl font-bold mb-4">イベント {{ props.event.title }}</h1>
      <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700">日時</label>
        <div class="mt-1 text-sm text-gray-900">
          開始: {{ formatJstDateTime(props.event.start) }}<br>
          終了: {{ formatJstDateTime(props.event.end) }}
        </div>
      </div>
      <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700">詳細</label>
        <div class="prose" v-html="props.event.description"></div>
      </div>
      <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700">添付ファイル</label>
        <div v-if="props.event.attachments && props.event.attachments.length">
          <ul>
            <li v-for="file in props.event.attachments" :key="file.id">
              <a :href="file.url" target="_blank" class="text-blue-600 underline">{{ file.original_name }}</a>
            </li>
          </ul>
        </div>
        <div v-else class="text-gray-500">添付ファイルなし</div>
      </div>
      <div class="flex space-x-4">
        <Link :href="route('events.edit', props.event.id)" class="px-4 py-2 bg-blue-600 text-white rounded">編集</Link>
        <Link :href="route('dashboard')" class="px-4 py-2 bg-gray-200 text-gray-700 rounded">戻る</Link>
      </div>
    </div>
  </AppLayout>
</template>
