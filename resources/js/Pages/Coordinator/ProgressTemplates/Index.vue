<template>
  <AppLayout title="進行管理テンプレート">
    <template #header>
      <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">進行管理テンプレート</h2>
    </template>

    <div class="rounded bg-white px-4 py-6 sm:p-6 shadow">

      <!-- ツールバー -->
      <div class="mb-4 flex items-center gap-3">
        <Link
          :href="route('coordinator.progress_templates.create')"
          class="rounded bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700"
        >
          新規作成
        </Link>
      </div>

      <!-- 一覧テーブル -->
      <div v-if="templates.length > 0" class="overflow-x-auto">
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
          <tr v-for="tmpl in templates" :key="tmpl.id" class="hover:bg-gray-50">
            <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ tmpl.name }}</td>
            <td class="px-4 py-3 text-sm text-gray-500">{{ tmpl.description ?? '—' }}</td>
            <td class="px-4 py-3 text-sm text-gray-500">{{ tmpl.creator_name ?? '—' }}</td>
            <td class="px-4 py-3">
              <span
                class="inline-block rounded px-2 py-0.5 text-xs"
                :class="tmpl.is_shared ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
              >
                {{ tmpl.is_shared ? '共有' : '非公開' }}
              </span>
            </td>
            <td class="px-4 py-3 text-sm text-gray-500">{{ tmpl.updated_at }}</td>
            <td class="px-4 py-3 text-right text-sm">
              <Link
                v-if="tmpl.created_by === authUserId || isAdmin"
                :href="route('coordinator.progress_templates.edit', { template: tmpl.id })"
                class="mr-3 text-blue-500 hover:underline"
              >
                編集
              </Link>
              <button
                v-if="tmpl.created_by === authUserId || isAdmin"
                type="button"
                class="text-red-400 hover:text-red-600 hover:underline"
                @click="destroyTemplate(tmpl)"
              >
                削除
              </button>
            </td>
          </tr>
        </tbody>
      </table>
      </div>

      <div v-else class="py-8 text-center text-gray-400">テンプレートがありません。</div>

    </div>
  </AppLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';

defineProps({
  templates: Array,
});

const page = usePage();
const authUserId = computed(() => page.props.auth?.user?.id);
const isAdmin = computed(() => ['admin', 'superadmin'].includes(page.props.auth?.user?.user_role));

function destroyTemplate(tmpl) {
  if (!confirm(`テンプレート「${tmpl.name}」を削除しますか？`)) return;
  router.delete(route('coordinator.progress_templates.destroy', { template: tmpl.id }));
}
</script>
