<script setup>
import { ref, watch, onMounted, onBeforeUnmount } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue'
import { useForm, Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import 'quill/dist/quill.snow.css';

import { quillEditor } from 'vue3-quill';

console.log('quill-editor: running');

const props = defineProps({
  date: String
})


const content = ref('');
const form = useForm({
  date: props.date,
  content,
  files: []
});

const defaultToolbar = [
  ['bold', 'italic', 'underline', 'strike'],
  [{ 'header': [1, 2, 3, false] }],
  [{ 'list': 'ordered'}, { 'list': 'bullet' }],
  [{ 'script': 'sub'}, { 'script': 'super' }],
  [{ 'size': ['small', false, 'large', 'huge'] }],
  [{ 'color': [] }, { 'background': [] }],
  [{ 'font': [] }],
  [{ 'align': [] }],
  // ['blockquote', 'code-block'],
  // ['link', 'image', 'video'],
  // ['clean']
];

const quillModules = { toolbar: defaultToolbar };

watch(() => form.content, (val) => {
  content.value = val;
});

onMounted(() => {
});


onBeforeUnmount(() => {
});

const submit = () => {
  const html = form.content?.trim() || '';
  if (html === '' || html === '<p><br></p>' || html === '<p></p>') {
    form.content = '';
  }
  form.post(route('diaries.store'), { forceFormData: true })
}

function onInput(val) {
  form.content = val;
}
function onFocus() {}
function onBlur() {}
function onChange(val) {
  form.content = val.html;
}
</script>

<template>
  <AppLayout title="日報作成">
    <div class="max-w-2xl mx-auto p-6 bg-white shadow rounded">
      <h1 class="text-2xl font-bold mb-4">日報作成 ({{ props.date }})</h1>
      <form @submit.prevent="submit">
        <div v-if="Object.keys(form.errors).length" class="mb-4 text-red-600">
          <ul>
          </ul>
        </div>
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">日付</label>
          <input type="date" v-model="form.date" class="w-full border rounded p-2" />
        </div>
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">内容</label>
          <div style="border:2px solid #eab308; padding:8px; margin:8px 0;">
            <quill-editor
              v-model="form.content"
              contentType="html"
              theme="snow"
              :modules="quillModules"
              style="min-height:180px;height:180px;background:#fff;border-radius:0.75rem;border:1.5px solid #d1d5db;box-shadow:0 2px 8px 0 #e5e7eb;padding:1rem;"
              placeholder="Insert Content here"
              @input="onInput"
              @update:content="onInput"
              @focus="onFocus"
              @blur="onBlur"
              @change="onChange"
            />
          </div>
        </div>
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">入力内容がここに出ます（HTML）</label>
          <div class="p-2 bg-gray-100 border rounded min-h-[40px]">{{ form.content }}</div>
        </div>
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">添付ファイル</label>
          <input type="file" multiple @change="e => form.files = Array.from(e.target.files)" class="w-full border rounded p-2" />
        </div>
        <div class="flex space-x-4">
          <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">保存</button>
          <Link :href="route('dashboard')" class="px-4 py-2 bg-gray-200 text-gray-700 rounded">キャンセル</Link>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<style scoped>
.editor-content {
  border-radius: 0.75rem;
  border: 1.5px solid #d1d5db;
  background: #fff;
  box-shadow: 0 2px 8px 0 #e5e7eb;
  padding: 1rem;
}
</style>
