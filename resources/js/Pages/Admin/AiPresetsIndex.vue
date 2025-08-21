<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import axios from 'axios';

const page = usePage();
const props = page.props;
const presets = ref(props.presets || { data: [] });

const form = ref({ name: '', type: 'model', data: {}, description: '', icon: '' });

const submit = async () => {
  try {
    await axios.post(route('admin.ai.presets.store'), form.value);
    window.location.reload();
  } catch (e) {
    console.error(e);
    alert('保存に失敗しました');
  }
};
</script>

<template>
  <AppLayout title="AIプリセット管理">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">AIプリセット管理</h2>
    </template>

    <main>
      <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
          <div class="bg-white shadow-xl sm:rounded-lg p-6">
            <div class="mb-4">
              <h3 class="font-semibold">新規プリセット</h3>
              <div class="grid grid-cols-1 md:grid-cols-3 gap-2 mt-2">
                <input v-model="form.name" placeholder="名前" class="input" />
                <select v-model="form.type" class="input">
                  <option value="model">model</option>
                  <option value="instruction">instruction</option>
                  <option value="system">system</option>
                </select>
                <input v-model="form.icon" placeholder="icon.svg" class="input" />
                <textarea v-model="form.description" placeholder="説明" class="textarea md:col-span-3"></textarea>
                <textarea v-model="form.data" placeholder='JSON data' class="textarea md:col-span-3"></textarea>
                <div>
                  <button @click.prevent="submit" class="bg-blue-600 text-white px-3 py-1 rounded">保存</button>
                </div>
              </div>
            </div>

            <div>
              <h3 class="font-semibold">既存プリセット</h3>
              <div class="mt-2 space-y-2">
                <div v-for="p in presets.data" :key="p.id" class="p-2 border rounded flex items-center justify-between">
                  <div>
                    <div class="font-medium">{{ p.name }} <span class="text-xs text-gray-500">({{ p.type }})</span></div>
                    <div class="text-xs text-gray-600">{{ p.description }}</div>
                  </div>
                  <div class="flex gap-2">
                    <form :action="route('admin.ai.presets.destroy', p.id)" method="post">
                      <input type="hidden" name="_method" value="delete" />
                      <button class="text-red-600">削除</button>
                    </form>
                  </div>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>
    </main>
  </AppLayout>
</template>
