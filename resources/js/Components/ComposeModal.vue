<template>
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="fixed inset-0 bg-black/40" @click="close"></div>
        <div class="z-10 w-3/4 max-w-2xl overflow-auto rounded-lg bg-white shadow-lg">
            <div class="flex items-center justify-between border-b p-4">
                <h3 class="font-semibold">新規メール作成</h3>
                <button @click="close" class="text-gray-500">閉じる</button>
            </div>
            <div class="space-y-3 p-4">
                <div>
                    <label class="text-sm">宛先</label>
                    <div class="mt-1 flex flex-wrap gap-2">
                        <span v-for="u in to" :key="u.id" class="rounded bg-gray-200 px-2 py-1"
                            >{{ u.name }} <button @click="remove(u.id)" class="ml-2">✕</button></span
                        >
                    </div>
                    <input v-model="query" @input="searchByInput" placeholder="ID または 名前 で検索" class="mt-2 w-full rounded border px-3 py-2" />
                    <ul v-if="candidates.length" class="mt-2 max-h-48 overflow-auto border bg-white">
                        <li v-for="c in candidates" :key="c.id" class="flex justify-between p-2 hover:bg-gray-100">
                            <span
                                >{{ c.name }} <span class="text-xs text-gray-500">{{ c.company_name }}</span></span
                            >
                            <button @click="add(c)" class="text-blue-600">追加</button>
                        </li>
                    </ul>
                </div>

                <div>
                    <label class="text-sm">件名</label>
                    <input v-model="subject" class="w-full rounded border px-3 py-2" />
                </div>

                <div>
                    <label class="text-sm">本文</label>
                    <textarea v-model="body" class="h-40 w-full rounded border px-3 py-2"></textarea>
                </div>

                <div class="flex justify-end">
                    <button @click="send" class="rounded bg-blue-600 px-4 py-2 text-white">送信</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import axios from 'axios';
import { ref } from 'vue';
const props = defineProps({ show: Boolean });
const emit = defineEmits(['close', 'sent']);
const to = ref([]);
const query = ref('');
const candidates = ref([]);
const subject = ref('');
const body = ref('');

async function searchByInput() {
    if (!query.value || query.value.length < 1) {
        candidates.value = [];
        return;
    }
    try {
        const res = await axios.get(route('users.search'), { params: { q: query.value } });
        candidates.value = res.data;
    } catch (e) {
        console.error(e);
    }
}

function add(user) {
    if (!to.value.find((u) => u.id === user.id)) to.value.push(user);
    candidates.value = [];
    query.value = '';
}

function remove(id) {
    to.value = to.value.filter((u) => u.id !== id);
}

async function send() {
    const payload = {
        to: to.value.map((u) => u.id),
        cc: [],
        bcc: [],
        subject: subject.value,
        body: body.value,
        attachments: [],
    };
    try {
        await axios.post(route('messages.store'), payload);
        emit('sent');
        emit('close');
    } catch (e) {
        console.error(e);
    }
}
</script>
