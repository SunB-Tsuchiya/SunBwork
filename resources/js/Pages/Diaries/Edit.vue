<script setup>
import useToasts from '@/Composables/useToasts';
import { defaultToolbar } from '@/config/quillToolbar';
import AppLayout from '@/layouts/AppLayout.vue';
import { router, useForm } from '@inertiajs/vue3';
import { QuillEditor } from '@vueup/vue-quill';
import '@vueup/vue-quill/dist/vue-quill.snow.css';
import { onMounted, ref } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps({ diary: Object });
const content = ref('');
let editorInstance = null;

onMounted(() => {
    // mounted
});

function handleEditorReady(editor) {
    editorInstance = editor;
    const delta = editor.clipboard.convert(props.diary.content || '<p>初期値がありません</p>');
    editor.setContents(delta);
    try {
        const html = editor.root && editor.root.innerHTML ? editor.root.innerHTML : props.diary.content || '';
        content.value = html;
        if (typeof form !== 'undefined') {
            form.content = html;
        }
    } catch (e) {
        // ignore
    }
}

const { showToast } = useToasts();

const isSubmitting = ref(false);

const form = useForm({
    content: content.value,
    files: [],
});

const submit = () => {
    console.debug('Diary Edit: submit called');
    try {
        if (editorInstance && editorInstance.root && editorInstance.root.innerHTML !== undefined) {
            form.content = editorInstance.root.innerHTML;
        } else {
            form.content = content.value;
        }
    } catch (e) {
        form.content = content.value;
    }
    // If there are files, Inertia will send multipart/form-data.
    // Only force FormData when files exist to avoid surprising server parsing.
    const hasFiles = form.files && Array.isArray(form.files) && form.files.length > 0;
    console.debug('Diary Edit: submitting', { date: props.diary.date, contentLength: (form.content || '').length, hasFiles });
    form.put(route('diaries.update', props.diary.id), {
        forceFormData: hasFiles,
        onStart: () => {
            console.debug('Diary Edit: onStart');
            isSubmitting.value = true;
            try {
                showToast('送信中...', 'info', 1000);
            } catch (e) {}
        },
        onFinish: () => {
            console.debug('Diary Edit: onFinish');
            isSubmitting.value = false;
        },
        onSuccess: () => {
            console.debug('Diary Edit: onSuccess');
            try {
                showToast('更新しました', 'success', 1500);
            } catch (e) {
                // ignore
            }
            // 小さく待ってからカレンダーへ戻す
            setTimeout(() => {
                try {
                    router.get(route('calendar.index'));
                } catch (e) {
                    // fallback: go to events index
                    router.get(route('events.index'));
                }
            }, 300);
        },
        onError: (errors) => {
            console.debug('Diary Edit: onError', errors);
            try {
                // errors is typically an object mapping field -> [messages]
                console.error('diaries.update onError', errors, form.errors);
                let msg = '更新に失敗しました';
                if (errors && typeof errors === 'object') {
                    // collect first messages
                    const parts = [];
                    for (const k of Object.keys(errors)) {
                        const v = errors[k];
                        if (Array.isArray(v) && v.length) parts.push(v[0]);
                        else if (typeof v === 'string') parts.push(v);
                    }
                    if (parts.length) msg = parts.join(' / ');
                }
                showToast(msg, 'error', 6000);
            } catch (e) {
                try {
                    showToast('更新に失敗しました', 'error', 4000);
                } catch (ee) {}
            } finally {
                isSubmitting.value = false;
            }
        },
    });
};

const back = () => {
    try {
        const qp = new URLSearchParams(window.location.search || '');
        const returnTo = qp.get('return_to');
        if (returnTo) {
            window.location.href = decodeURIComponent(returnTo);
            return;
        }
    } catch (e) {
        // ignore
    }
    try {
        if (window.history && window.history.length > 1) {
            window.history.back();
            return;
        }
    } catch (e) {
        // ignore
    }
    try {
        router.get(route('diaries.show', props.diary.id));
        return;
    } catch (e) {
        router.get(route('diaries.index'));
    }
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
                    <button :disabled="isSubmitting" type="submit" class="rounded bg-blue-600 px-4 py-2 text-white disabled:opacity-60">
                        <span v-if="isSubmitting">送信中...</span>
                        <span v-else>更新</span>
                    </button>
                    <button @click.prevent="back" class="rounded bg-gray-200 px-4 py-2 text-gray-700">戻る</button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
