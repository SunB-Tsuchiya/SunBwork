<template>
  <AppLayout :title="isEdit ? '管理シートテンプレート編集' : '管理シートテンプレート新規作成'">
    <template #header>
      <div class="flex items-center gap-3">
        <Link
          :href="route('coordinator.management_templates.index')"
          class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 whitespace-nowrap hover:bg-gray-300"
        >
          ← 一覧に戻る
        </Link>
        <h2 class="text-base font-semibold leading-tight text-gray-800 sm:text-xl">
          {{ isEdit ? '管理シートテンプレート編集' : '管理シートテンプレート新規作成' }}
        </h2>
      </div>
    </template>

    <div class="rounded bg-white px-4 py-6 shadow sm:p-6">
      <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
          <label class="block text-sm font-medium text-gray-700">テンプレート名 <span class="text-red-500">*</span></label>
          <input
            v-model="form.name"
            type="text"
            class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none"
          />
          <p v-if="errors.name" class="mt-1 text-xs text-red-500">{{ errors.name }}</p>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">説明</label>
          <input
            v-model="form.description"
            type="text"
            class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none"
          />
        </div>
        <div class="flex items-center gap-2">
          <input id="management_is_shared" v-model="form.is_shared" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-indigo-600" />
          <label for="management_is_shared" class="text-sm text-gray-700">全Coordinatorに共有する</label>
        </div>
      </div>

      <div class="mb-6 rounded border border-gray-200 p-4">
        <h3 class="mb-1 font-semibold text-gray-700">列・ステージ構成</h3>
        <p class="mb-3 text-xs text-gray-400">
          管理シートの横軸となる工程と担当セルを設定します。
        </p>
        <ColumnTreeEditor
          :nodes="form.column_config"
          @change="(updated) => { form.column_config = updated.slice(); }"
        />
        <p v-if="errors.column_config" class="mt-1 text-xs text-red-500">{{ errors.column_config }}</p>
      </div>

      <div class="flex items-center gap-3">
        <button
          type="button"
          class="rounded bg-indigo-600 px-5 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
          :disabled="processing"
          @click="submit"
        >
          {{ isEdit ? '更新' : '作成' }}
        </button>
        <Link
          :href="route('coordinator.management_templates.index')"
          class="rounded bg-gray-100 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-200"
        >
          テンプレート一覧に戻る
        </Link>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import ColumnTreeEditor from '@/Components/ColumnTreeEditor.vue';

const props = defineProps({
  template: Object,
  defaultColumnConfig: { type: Array, default: () => [] },
});

const page = usePage();
const isEdit = computed(() => !!props.template);
const errors = computed(() => page.props.errors ?? {});
const processing = ref(false);
const initialColumns = props.template?.column_config ?? props.defaultColumnConfig;

const form = ref({
  name: props.template?.name ?? '',
  description: props.template?.description ?? '',
  column_config: JSON.parse(JSON.stringify(initialColumns)),
  is_shared: props.template?.is_shared ?? false,
});

function submit() {
  processing.value = true;
  const options = { onFinish: () => { processing.value = false; } };

  if (isEdit.value) {
    router.put(
      route('coordinator.management_templates.update', { template: props.template.id }),
      form.value,
      options,
    );
    return;
  }

  router.post(route('coordinator.management_templates.store'), form.value, options);
}
</script>
