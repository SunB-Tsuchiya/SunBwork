<script setup>
import { ref, watch, onMounted } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { QuillEditor } from '@vueup/vue-quill';
import '@vueup/vue-quill/dist/vue-quill.snow.css';
import { defaultToolbar } from '@/config/quillToolbar';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps({ diary: Object });

const content = ref('');
const quillInstance = ref(null); // Quill のインスタンス

onMounted(() => {
  content.value = props.diary.content || '<p>初期値がありません</p>';
  console.log('onMounted で content を再設定:', content.value);
});

const onEditorReady = (quill) => {
  quillInstance.value = quill;
  console.log('QuillEditor が ready:', quill);

  // 初期値を直接設定
  quill.root.innerHTML = content.value;
  console.log('QuillEditor に innerHTML を設定:', quill.root.innerHTML);
};

const form = useForm({
  content: content.value,
  files: [],
});

watch(content, (val) => {
  console.log('watch: QuillEditor content changed:', val);
});

const submit = () => {
  form.content = content.value;
  const html = form.content?.trim() || '';
  console.log('submit 実行時の content:', html);

  if (html === '' || html === '<p><br></p>' || html === '<p></p>') {
    form.content = '';
    console.log('空のコンテンツとして処理されました');
  }

  form.put(route('diaries.update', props.diary.id), { forceFormData: true });
};
</script>

<template>
  <AppLayout title="日報編集">
    <div class="max-w-2xl mx-auto p-6 bg-white shadow rounded">
      <h1 class="text-2xl font-bold mb-4">日報編集 ({{ props.diary.date }})</h1>
      <form @submit.prevent="submit">
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">内容</label>
          <QuillEditor
            theme="snow"
            :toolbar="defaultToolbar"
            style="min-height:180px;height:180px;background:#fff"
            v-model="content"
            @ready="onEditorReady"
          />
        </div>
        <div class="flex space-x-4">
          <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">更新</button>
          <Link :href="route('diaries.show', props.diary.id)" class="px-4 py-2 bg-gray-200 text-gray-700 rounded">戻る</Link>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
