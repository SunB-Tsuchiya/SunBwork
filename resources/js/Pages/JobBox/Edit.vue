<template>
    <AppLayout :title="`ジョブメッセージ編集 - ${projectJob.title}`">
        <template #header>
            <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">ジョブメッセージ編集</h2>
        </template>

        <div class="mx-auto max-w-2xl rounded bg-white px-4 py-6 sm:p-6 shadow">
            <h1 class="mb-4 text-xl font-bold">
                メッセージ #{{ message.id }} を編集
            </h1>

            <form @submit.prevent="submit">
                <div class="mb-4">
                    <label class="mb-1 block font-semibold text-gray-700">件名</label>
                    <input
                        v-model="form.subject"
                        type="text"
                        maxlength="255"
                        class="w-full rounded border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-400"
                    />
                    <p v-if="form.errors.subject" class="mt-1 text-sm text-red-600">{{ form.errors.subject }}</p>
                </div>

                <div class="mb-6">
                    <label class="mb-1 block font-semibold text-gray-700">本文</label>
                    <textarea
                        v-model="form.body"
                        rows="8"
                        class="w-full rounded border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-400"
                    ></textarea>
                    <p v-if="form.errors.body" class="mt-1 text-sm text-red-600">{{ form.errors.body }}</p>
                </div>

                <div class="flex gap-2">
                    <Link
                        :href="route('coordinator.project_jobs.jobbox.show', { projectJob: projectJob.id, message: message.id })"
                        class="rounded bg-gray-200 px-4 py-2 text-gray-800 hover:bg-gray-300"
                    >キャンセル</Link>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded bg-yellow-500 px-4 py-2 text-white hover:bg-yellow-600 disabled:opacity-60"
                    >保存</button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

const props = defineProps({ projectJob: Object, message: Object });

const form = useForm({
    subject: props.message.subject ?? '',
    body: props.message.body ?? '',
});

function submit() {
    form.put(route('coordinator.project_jobs.jobbox.update', {
        projectJob: props.projectJob.id,
        message: props.message.id,
    }));
}
</script>
