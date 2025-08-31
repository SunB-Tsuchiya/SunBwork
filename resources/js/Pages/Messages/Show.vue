<template>
    <AppLayout :title="message.subject || 'メール詳細'">
        <template #header>
            <h2 class="text-xl font-semibold">メール詳細</h2>
        </template>

        <main>
            <div class="py-12">
                <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div class="bg-white p-6 shadow sm:rounded-lg">
                        <h3 class="text-lg font-semibold">{{ message.subject }}</h3>
                        <div class="mt-1 text-sm text-gray-500">差出人: {{ message.from_user?.name }}</div>
                        <div class="mt-4 text-sm text-gray-700" v-html="sanitize(message.body)"></div>
                    </div>
                </div>
            </div>
        </main>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import axios from 'axios';
import { onMounted } from 'vue';
import DOMPurify from 'dompurify';
const props = defineProps({ message: Object });
const { message } = props;

onMounted(() => {
    // mark this message as read on open; ignore errors
    axios.post(route('messages.read', message.id)).catch(() => {});
});

function sanitize(html) {
    return DOMPurify.sanitize(html || '');
}
</script>
