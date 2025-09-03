<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';

const page = usePage();
const props = page.props;
const requests = props.requests || {};

const form = useForm({ to_user_id: '', project_job_assignment_id: '', project_job_id: '', message: '' });

const submit = () => {
    // Build MessagesController compatible payload
    const toId = form.to_user_id || '';
    const payload = {
        to: toId ? [toId] : [],
        subject: `依頼: ${form.project_job_id || ''}`,
        body: form.message || '',
        attachments: [],
    };

    router.post(route('messages.store'), payload, {
        onSuccess: () => {
            form.reset('to_user_id', 'project_job_assignment_id', 'project_job_id', 'message');
            alert('送信しました（Messages 経由）。受信者に通知されます。');
        },
        onError: (errors) => {
            console.error('send message error', errors);
            alert('送信に失敗しました。詳細はコンソールを確認してください。');
        },
    });
};
</script>

<template>
    <Head title="依頼箱" />
    <div class="prose">
        <h1>依頼箱 (Inbox)</h1>
    </div>

    <div class="mt-6">
        <h2 class="font-semibold">新規依頼</h2>
        <form @submit.prevent="submit" class="space-y-2">
            <div>
                <label class="block text-sm">宛先ユーザーID</label>
                <input v-model="form.to_user_id" class="rounded border px-2 py-1" />
            </div>
            <div>
                <label class="block text-sm">メッセージ</label>
                <textarea v-model="form.message" class="rounded border px-2 py-1" />
            </div>
            <div>
                <button type="submit" class="rounded bg-green-600 px-3 py-1 text-white">送信</button>
            </div>
        </form>
    </div>

    <div class="mt-8">
        <h2 class="font-semibold">受信依頼</h2>
        <div class="mt-2 space-y-2">
            <div v-for="r in requests.data || []" :key="r.id" class="rounded border p-3">
                <div class="flex justify-between">
                    <div>
                        <div class="text-sm text-gray-600">from: {{ r.from_user?.name || r.from_user_id }}</div>
                        <div class="font-medium">{{ r.message || '(no message)' }}</div>
                        <div class="text-xs text-gray-500">status: {{ r.status }} · {{ r.created_at }}</div>
                    </div>
                    <div class="flex items-start gap-2">
                        <Link :href="route('job_requests.show', r.id)" class="text-blue-600">詳細</Link>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
