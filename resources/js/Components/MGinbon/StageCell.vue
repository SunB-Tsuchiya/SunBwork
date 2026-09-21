<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({ stage: { type: Object, default: null }, compact: { type: Boolean, default: false }, tone: { type: String, default: 'proof' } });

const label = (status) => ({ not_started: '未着手', assigned: '登録済', in_progress: '作業中', completed: '完了' })[status] ?? status;
const badge = (status) => status === 'completed' ? 'bg-green-100 text-green-800' : status === 'in_progress' ? 'bg-blue-100 text-blue-800' : status === 'assigned' ? 'bg-indigo-100 text-indigo-800' : 'bg-gray-100 text-gray-500';
const date = (value) => value ? value.slice(5, 10).replace('-', '/') : '';
</script>

<template>
  <div v-if="stage" class="min-w-[7.5rem] px-2 py-1.5 leading-tight" :class="{ 'bg-[#66ffcc]': tone === 'operation', 'bg-white': tone === 'proof', 'bg-[#fffbd1]': tone === 'output' }">
    <div v-if="!compact" class="truncate text-[10px] text-gray-500">{{ stage.name }}</div>
    <div class="mt-0.5 flex flex-wrap items-center gap-1">
      <span class="max-w-[7rem] truncate font-medium text-gray-900">{{ stage.actor || '—' }}</span>
      <span class="rounded px-1.5 py-0.5 text-[10px]" :class="badge(stage.status)">{{ label(stage.status) }}</span>
    </div>
    <div v-if="stage.completed_at || stage.assigned_at" class="mt-1 text-[10px] text-gray-500">
      {{ stage.completed_at ? `完了 ${date(stage.completed_at)}` : `登録 ${date(stage.assigned_at)}` }}
    </div>
    <Link v-if="stage.assignment_id" :href="route('user.myjobbox.show', { assignment: stage.assignment_id })" class="mt-1 inline-block text-[10px] font-medium text-blue-700 hover:underline">MyJob</Link>
  </div>
  <div v-else class="min-w-[7.5rem] px-2 py-1.5 text-center text-gray-400" :class="tone === 'operation' ? 'bg-[#66ffcc]' : 'bg-white'">—</div>
</template>
