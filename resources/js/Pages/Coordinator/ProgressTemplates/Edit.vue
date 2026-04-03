<template>
  <AppLayout :title="isEdit ? 'テンプレート編集' : 'テンプレート新規作成'">
    <template #header>
      <h2 class="text-xl font-semibold leading-tight text-gray-800">
        {{ isEdit ? 'テンプレート編集' : 'テンプレート新規作成' }}
      </h2>
    </template>

    <div class="rounded bg-white p-6 shadow">

      <!-- ── メタ情報 ─────────────────────────────── -->
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
          <input id="is_shared" v-model="form.is_shared" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-indigo-600" />
          <label for="is_shared" class="text-sm text-gray-700">全Coordinatorに共有する</label>
        </div>
      </div>

      <!-- ── 列構成エディタ ─────────────────────────── -->
      <div class="mb-6">
        <h3 class="mb-3 font-semibold text-gray-700">列構成</h3>
        <ColumnTreeEditor
          :nodes="form.column_config"
          @change="(updated) => { form.column_config = updated; }"
        />
        <p v-if="errors.column_config" class="mt-1 text-xs text-red-500">{{ errors.column_config }}</p>
      </div>

      <!-- ── 保存ボタン ──────────────────────────────── -->
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
          :href="route('coordinator.progress_templates.index')"
          class="rounded border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50"
        >
          キャンセル
        </Link>
      </div>

    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import ColumnTreeEditor from '@/Components/ColumnTreeEditor.vue';

const props = defineProps({
  template: Object, // null のとき新規作成
});

const isEdit = computed(() => !!props.template);
const page = usePage();
const errors = computed(() => page.props.errors ?? {});
const processing = ref(false);

const form = ref({
  name: props.template?.name ?? '',
  description: props.template?.description ?? '',
  column_config: JSON.parse(JSON.stringify(props.template?.column_config ?? [])),
  is_shared: props.template?.is_shared ?? false,
});

function submit() {
  processing.value = true;
  if (isEdit.value) {
    router.put(
      route('coordinator.progress_templates.update', { template: props.template.id }),
      form.value,
      { onFinish: () => { processing.value = false; } }
    );
  } else {
    router.post(
      route('coordinator.progress_templates.store'),
      form.value,
      { onFinish: () => { processing.value = false; } }
    );
  }
}
</script>
