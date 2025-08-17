<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
const props = defineProps({
  jobs: Array
});
</script>
<template>
  <AppLayout title="ジョブ一覧">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">ジョブ一覧</h2>
    </template>
    <div class="py-6">
      <div class="max-w-4xl mx-auto bg-white shadow rounded p-6">
        <table class="min-w-full divide-y divide-gray-200">
          <thead>
            <tr>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">ジョブID</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">伝票No.</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">名前</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">クライアント名</th>
              <th class="px-4 py-2"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="job in jobs" :key="job.id">
              <td class="px-4 py-2">{{ job.id }}</td>
              <td class="px-4 py-2">{{ job.jobcode }}</td>
              <td class="px-4 py-2">{{ job.name }}</td>
              <td class="px-4 py-2">{{ job.client_name }}</td>
              <td class="px-4 py-2 text-right">
                <Link :href="route('user.assigned-projects.show', job.id)" class="text-blue-600 hover:underline">詳細</Link>
              </td>
            </tr>
            <tr v-if="!jobs || jobs.length === 0">
              <td colspan="5" class="text-center text-gray-400 py-4">割り当てられたジョブはありません</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>
