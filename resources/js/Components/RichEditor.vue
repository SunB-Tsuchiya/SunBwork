<script setup>
import { shallowRef, onMounted, onBeforeUnmount } from 'vue';
import { Editor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';

const editor = shallowRef(null);

onMounted(() => {
  editor.value = new Editor({
    extensions: [StarterKit],
    content: '',
  });
});

onBeforeUnmount(() => {
  if (editor.value) editor.value.destroy();
});
</script>

<template>
  <div style="border:2px solid #eab308; padding:8px; margin:8px 0;">
    <div>RichEditorDebug.vue (シンプルtiptap)</div>
    <div v-if="editor" class="flex flex-wrap gap-2 mb-3">
      <button type="button" @click="editor.chain().focus().toggleBold().run()"
        :class="['editor-btn', editor.isActive('bold') ? 'active' : '']"
        title="太字"><span class="font-bold">𝐁</span></button>
      <button type="button" @click="editor.chain().focus().toggleItalic().run()"
        :class="['editor-btn', editor.isActive('italic') ? 'active' : '']"
        title="斜体"><span class="italic">𝐼</span></button>
      <button type="button" @click="editor.chain().focus().toggleStrike().run()"
        :class="['editor-btn', editor.isActive('strike') ? 'active' : '']"
        title="打ち消し"><span class="line-through">S</span></button>
      <button type="button" @click="editor.chain().focus().toggleBulletList().run()"
        :class="['editor-btn', editor.isActive('bulletList') ? 'active' : '']"
        title="箇条書き"><span>• List</span></button>
      <button type="button" @click="editor.chain().focus().toggleOrderedList().run()"
        :class="['editor-btn', editor.isActive('orderedList') ? 'active' : '']"
        title="番号付きリスト"><span>1. List</span></button>
      <button type="button" @click="editor.chain().focus().toggleHeading({ level: 1 }).run()"
        :class="['editor-btn', editor.isActive('heading', { level: 1 }) ? 'active' : '']"
        title="見出し1"><span>H1</span></button>
      <button type="button" @click="editor.chain().focus().toggleHeading({ level: 2 }).run()"
        :class="['editor-btn', editor.isActive('heading', { level: 2 }) ? 'active' : '']"
        title="見出し2"><span>H2</span></button>
      <button type="button" @click="editor.chain().focus().toggleBlockquote().run()"
        :class="['editor-btn', editor.isActive('blockquote') ? 'active' : '']"
        title="引用"><span>❝</span></button>
      <button type="button" @click="editor.chain().focus().toggleCodeBlock().run()"
        :class="['editor-btn', editor.isActive('codeBlock') ? 'active' : '']"
        title="コード"><span>⌨</span></button>
      <button type="button" @click="editor.chain().focus().setHorizontalRule().run()"
        :class="['editor-btn']" title="区切り"><span>―</span></button>
    </div>
    <EditorContent v-if="editor" :editor="editor" class="editor-content" />
  </div>
</template>

<style scoped>
.editor-content {
  border-radius: 0.75rem;
  border: 1.5px solid #d1d5db;
  background: #fff;
  box-shadow: 0 2px 8px 0 #e5e7eb;
  padding: 1rem;
  min-height: 240px !important;
}

:host {
  display: block;
  height: 240px;
}
</style>
