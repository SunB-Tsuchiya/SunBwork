<script setup>
import { defaultToolbar } from '@/config/quillToolbar';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { QuillEditor } from '@vueup/vue-quill';
import '@vueup/vue-quill/dist/vue-quill.snow.css';
import { onMounted, ref } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps({ diary: Object });
const content = ref('');
let editorInstance = null;

onMounted(() => {
    console.log('[onMounted] props.diary.content:', props.diary?.content);
});

function handleEditorReady(editor) {
    editorInstance = editor;
    // HTML→Delta変換して初期表示
    const delta = editor.clipboard.convert(props.diary.content || '<p>初期値がありません</p>');
    editor.setContents(delta);
    console.log('[handleEditorReady] setContents:', delta);
    // ensure the reactive `content` and the form field are in sync
    try {
        const html = editor.root && editor.root.innerHTML ? editor.root.innerHTML : props.diary.content || '';
        content.value = html;
        if (typeof form !== 'undefined') {
            form.content = html;
        }
        console.log('[handleEditorReady] synced content:', html);
    } catch (e) {
        console.log('[handleEditorReady] sync error', e);
    }
}

const form = useForm({
    content: content.value,
    files: [],
});

const submit = () => {
    // If editor instance exists, read its HTML to ensure latest contents are used.
    try {
        if (editorInstance && editorInstance.root && editorInstance.root.innerHTML !== undefined) {
            form.content = editorInstance.root.innerHTML;
        } else {
            form.content = content.value;
        }
    } catch (e) {
        form.content = content.value;
    }
    console.log('[submit] sending update', { diaryId: props.diary.id, formContentPreview: (form.content || '').slice(0, 120) });
    form.put(route('diaries.update', props.diary.id), {
        forceFormData: true,
        onStart: () => console.log('[submit] onStart'),
        onFinish: () => console.log('[submit] onFinish'),
        onSuccess: () => console.log('[submit] onSuccess'),
        onError: (errors) => console.log('[submit] onError', errors),
    });
};
</script>

<template>
    <AppLayout title="日報編集">
        <div class="mx-auto max-w-2xl rounded bg-white p-6 shadow">
            <h1 class="mb-4 text-2xl font-bold">日報編集 ({{ props.diary.date }})</h1>
            <form @submit.prevent="submit">
                <div class="mb-4">
                    <label class="mb-1 block text-sm font-medium text-gray-700">内容</label>
                    <QuillEditor
                        theme="snow"
                        :toolbar="defaultToolbar"
                        style="min-height: 180px; height: 180px; background: #fff"
                        v-model="content"
                        @ready="handleEditorReady"
                    />
                </div>
                <div class="flex space-x-4">
                    <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-white">更新</button>
                    <Link :href="route('diaries.show', props.diary.id)" class="rounded bg-gray-200 px-4 py-2 text-gray-700">戻る</Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
