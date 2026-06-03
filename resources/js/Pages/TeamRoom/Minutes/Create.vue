<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import { route } from 'ziggy-js';
import { QuillEditor } from '@vueup/vue-quill';
import '@vueup/vue-quill/dist/vue-quill.snow.css';
import axios from 'axios';

const props = defineProps({
    team:    { type: Object, required: true },
    members: { type: Array, default: () => [] },
});

const today = new Date().toISOString().slice(0, 10);

const form = useForm({
    title:        '',
    content:      '',
    held_at:      today,
    attendee_ids: [],
});

let editorInstance = null;
const MAX_UPLOAD_SIZE = 20 * 1024 * 1024;

const toolbarOptions = [
    [{ header: [1, 2, 3, false] }],
    ['bold', 'italic', 'underline', 'strike'],
    [{ list: 'ordered' }, { list: 'bullet' }],
    ['blockquote', 'code-block'],
    [{ align: [] }],
    ['clean'],
];

function handleEditorReady(editor) {
    editorInstance = editor;
    const root = editor.root;
    root.addEventListener('drop', async (e) => {
        if (!e.dataTransfer) return;
        e.preventDefault();
        e.stopPropagation();
        for (const file of Array.from(e.dataTransfer.files)) {
            await uploadAndInsert(file);
        }
    }, true);
    root.addEventListener('paste', async (e) => {
        const items = Array.from(e.clipboardData?.items || []);
        const files = items.filter(it => it.kind === 'file').map(it => it.getAsFile()).filter(Boolean);
        if (files.length) {
            e.preventDefault();
            e.stopPropagation();
            for (const f of files) await uploadAndInsert(f);
        }
    }, true);
}

async function uploadAndInsert(file) {
    if (file.size > MAX_UPLOAD_SIZE) { alert('ファイルが大きすぎます'); return; }
    const fd = new FormData();
    fd.append('file', file);
    try {
        const res = await axios.post('/api/uploads', fd);
        const att = res.data;
        const idx = editorInstance.getSelection()?.index ?? editorInstance.getLength();
        if (att.status === 'ready' && att.url) {
            if (att.mime?.startsWith('image/')) {
                editorInstance.insertEmbed(idx, 'image', att.url);
            } else {
                editorInstance.insertText(idx, att.original_name, { link: att.url });
            }
        } else {
            const placeholder = `[[attachment:${att.id}:${att.original_name}]]`;
            editorInstance.insertText(idx, placeholder);
        }
    } catch { alert('アップロードに失敗しました'); }
}

function onEditorInput(val) {
    form.content = typeof val === 'string' ? val : '';
}

function toggleAttendee(id) {
    const idx = form.attendee_ids.indexOf(id);
    if (idx >= 0) {
        form.attendee_ids.splice(idx, 1);
    } else {
        form.attendee_ids.push(id);
    }
}

function submit() {
    form.post(route('team-rooms.minutes.store', { team: props.team.id }));
}
</script>

<template>
    <AppLayout title="会議記録 作成">
        <template #header>
            <div class="flex items-center gap-3">
                <Link
                    :href="route('team-rooms.show', { team: team.id }) + '?tab=minutes'"
                    class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300 whitespace-nowrap"
                >← チームルームに戻る</Link>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">会議記録 作成 — {{ team.name }}</h2>
            </div>
        </template>

        <div class="mx-auto max-w-3xl rounded bg-white p-6 shadow">
            <form @submit.prevent="submit" class="space-y-5">

                <!-- タイトル -->
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">タイトル <span class="text-red-500">*</span></label>
                    <input v-model="form.title" type="text" maxlength="255" required
                        class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none" />
                    <p v-if="form.errors.title" class="mt-1 text-xs text-red-500">{{ form.errors.title }}</p>
                </div>

                <!-- 開催日 -->
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">開催日 <span class="text-red-500">*</span></label>
                    <input v-model="form.held_at" type="date" required
                        class="rounded border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none" />
                    <p v-if="form.errors.held_at" class="mt-1 text-xs text-red-500">{{ form.errors.held_at }}</p>
                </div>

                <!-- 本文 (Quill) -->
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">内容</label>
                    <div class="rounded border border-gray-300">
                        <QuillEditor
                            :toolbar="toolbarOptions"
                            theme="snow"
                            content-type="html"
                            @ready="handleEditorReady"
                            @update:content="onEditorInput"
                            style="min-height: 200px;"
                        />
                    </div>
                </div>

                <!-- 参加者 -->
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700">参加者</label>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                        <label
                            v-for="member in members"
                            :key="member.id"
                            class="flex cursor-pointer items-center gap-2 rounded border border-gray-200 px-3 py-2 text-sm hover:bg-gray-50"
                            :class="{ 'border-indigo-400 bg-indigo-50': form.attendee_ids.includes(member.id) }"
                        >
                            <input
                                type="checkbox"
                                :checked="form.attendee_ids.includes(member.id)"
                                @change="toggleAttendee(member.id)"
                                class="rounded"
                            />
                            {{ member.name }}
                        </label>
                    </div>
                </div>

                <!-- ボタン -->
                <div class="flex justify-end gap-3 pt-2">
                    <Link
                        :href="route('team-rooms.show', { team: team.id }) + '?tab=minutes'"
                        class="rounded bg-gray-200 px-5 py-2 text-sm font-medium text-gray-700 hover:bg-gray-300"
                    >キャンセル</Link>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded bg-indigo-600 px-5 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-60"
                    >{{ form.processing ? '保存中...' : '保存' }}</button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
