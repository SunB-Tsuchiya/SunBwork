<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, usePage } from '@inertiajs/vue3';
const props = defineProps({ settings: Object });
const page = usePage();
const user = page.props.user;
</script>

<template>
  <AppLayout title="AI設定" :user="user">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">AI設定</h2>
    </template>

    <main>
      <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
          <div class="bg-white shadow-xl sm:rounded-lg p-6">
            <div class="flex items-center justify-between mb-4">
              <h1 class="text-2xl font-bold">AI設定</h1>
              <Link :href="route('superadmin.ai.create')" class="bg-blue-600 text-white px-3 py-2 rounded">新規作成</Link>
            </div>

            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Model</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Max tokens</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">更新</th>
                    <th class="px-6 py-3"></th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <tr v-for="s in settings.data" :key="s.id" class="hover:bg-gray-50">
                    <td class="px-6 py-4 text-sm text-gray-900">{{ s.id }}</td>
                    <td class="px-6 py-4 text-sm text-gray-900">{{ s.model }}</td>
                    <td class="px-6 py-4 text-sm text-gray-900">{{ s.max_tokens }}</td>
                    <td class="px-6 py-4 text-sm text-gray-900">{{ s.updated_at }}</td>
                    <td class="px-6 py-4 text-sm">
                      <Link :href="route('superadmin.ai.edit', s.id)" class="text-blue-600 hover:text-blue-900">編集</Link>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="mt-4 flex justify-end">
              <nav class="inline-flex -space-x-px" aria-label="Pagination">
                <ul class="inline-flex items-center space-x-1">
                  <li v-for="link in settings.links" :key="link.label">
                    <Link
                      v-if="link.url"
                      :href="link.url"
                      class="px-3 py-1 border rounded bg-white text-sm text-gray-700 hover:bg-gray-50"
                    >
                      <span v-html="link.label"></span>
                    </Link>
                    <span
                      v-else
                      class="px-3 py-1 border rounded bg-gray-200 text-sm text-gray-600"
                      v-html="link.label"
                    ></span>
                  </li>
                </ul>
              </nav>
            </div>
          </div>
        </div>
      </div>
    </main>
  </AppLayout>
</template>
