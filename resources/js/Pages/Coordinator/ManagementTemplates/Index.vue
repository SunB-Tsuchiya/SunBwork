<template>
  <AppLayout title="管理シートテンプレート">
    <template #header>
      <h2 class="text-base font-semibold leading-tight text-gray-800 sm:text-xl">管理シートテンプレート</h2>
    </template>

    <div class="rounded bg-white px-4 py-6 shadow sm:p-6">
      <div class="mb-4 flex flex-wrap items-center gap-3">
        <Link
          :href="route('coordinator.management_templates.create')"
          class="rounded bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700"
        >
          新規作成
        </Link>
      </div>

      <div v-if="templates.length" class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">テンプレート名</th>
              <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">説明</th>
              <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">作成者</th>
              <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">共有</th>
              <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">更新日</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 bg-white">
            <tr v-for="template in templates" :key="template.id" class="hover:bg-gray-50">
              <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ template.name }}</td>
              <td class="px-4 py-3 text-sm text-gray-500">{{ template.description || '—' }}</td>
              <td class="px-4 py-3 text-sm text-gray-500">{{ template.creator_name || '—' }}</td>
              <td class="px-4 py-3">
                <span
                  class="inline-block rounded px-2 py-0.5 text-xs"
                  :class="template.is_shared ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                >
                  {{ template.is_shared ? '共有' : '非公開' }}
                </span>
              </td>
              <td class="px-4 py-3 text-sm text-gray-500">{{ template.updated_at }}</td>
              <td class="px-4 py-3 text-right text-sm whitespace-nowrap">
                <template v-if="canEdit(template)">
                  <Link
                    :href="route('coordinator.management_templates.edit', { template: template.id })"
                    class="mr-3 text-blue-500 hover:underline"
                  >
                    編集
                  </Link>
                  <button
                    type="button"
                    class="text-red-400 hover:text-red-600 hover:underline"
                    @click="destroyTemplate(template)"
                  >
                    削除
                  </button>
                </template>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-else class="py-8 text-center text-gray-400">管理シートテンプレートがありません。</div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';

defineProps({
  templates: { type: Array, default: () => [] },
});

const page = usePage();
const authUserId = computed(() => page.props.auth?.user?.id);
const isAdmin = computed(() => ['admin', 'superadmin'].includes(page.props.auth?.user?.user_role));
const canEdit = (template) => template.created_by === authUserId.value || isAdmin.value;

function destroyTemplate(template) {
  if (!confirm(`テンプレート「${template.name}」を削除しますか？`)) return;
  router.delete(route('coordinator.management_templates.destroy', { template: template.id }));
}
</script>
