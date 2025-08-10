<script setup>
import { ref, onMounted } from 'vue';
import { QuillEditor } from '@vueup/vue-quill';
import '@vueup/vue-quill/dist/vue-quill.snow.css';

const props = defineProps({ diary: Object });
const content = ref('');
let editorInstance = null;

onMounted(() => {
  console.log('[onMounted] コンポーネントがマウントされました');
  console.log('[onMounted] props.diary:', props.diary);
  console.log('[onMounted] props.diary.content:', props.diary?.content);
});

function handleEditorReady(editor) {
  console.log('[QuillEditor] 初期化完了');
  editorInstance = editor;

  if (props.diary?.content) {
    const delta = editor.clipboard.convert(props.diary.content);
    console.log('[QuillEditor] HTML → Delta 変換完了:', delta);
    console.log('[QuillEditor] Delta ops:', delta.ops);

    // Delta を直接挿入
    editor.setContents(delta);
    console.log('[QuillEditor] Delta を setContents() で挿入');
  }
}
</script>

<template>
  <div class="p-6">
    <h2 class="text-xl font-bold mb-4">QuillEditor Inertia対応テスト</h2>
    <QuillEditor
      theme="snow"
      style="min-height:200px;background:#fff"
      v-model="content"
      @ready="handleEditorReady"
    />
  </div>
</template>
