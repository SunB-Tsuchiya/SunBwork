<template>
    <AppLayout title="メール作成">
        <template #header>
            <h2 class="text-xl font-semibold">新規メール作成</h2>
        </template>

        <main>
            <div class="py-12">
                <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div class="bg-white p-6 shadow sm:rounded-lg">
                        <form @submit.prevent="submit">
                            <div class="mb-3">
                                <label class="mb-1 block">To</label>
                                <div>
                                    <div class="mb-2 flex flex-wrap gap-2">
                                        <span v-for="u in to" :key="u.id" class="rounded bg-gray-200 px-2 py-1"
                                            >{{ u.name }} <button type="button" @click="removeSelected('to', u.id)">✕</button></span
                                        >
                                    </div>
                                    <input v-model="toQuery" @input="searchUsers('to')" placeholder="ユーザー名で検索" />
                                    <ul v-if="toCandidates.length" class="mt-1 max-h-48 overflow-auto border bg-white">
                                        <li
                                            v-for="c in toCandidates"
                                            :key="c.id"
                                            class="cursor-pointer p-1 hover:bg-gray-100"
                                            @click="selectUser('to', c)"
                                        >
                                            {{ c.name }}
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="mb-1 block">CC</label>
                                <div>
                                    <div class="mb-2 flex flex-wrap gap-2">
                                        <span v-for="u in cc" :key="u.id" class="rounded bg-gray-200 px-2 py-1"
                                            >{{ u.name }} <button type="button" @click="removeSelected('cc', u.id)">✕</button></span
                                        >
                                    </div>
                                    <input v-model="ccQuery" @input="searchUsers('cc')" placeholder="ユーザー名で検索" />
                                    <ul v-if="ccCandidates.length" class="mt-1 max-h-48 overflow-auto border bg-white">
                                        <li
                                            v-for="c in ccCandidates"
                                            :key="c.id"
                                            class="cursor-pointer p-1 hover:bg-gray-100"
                                            @click="selectUser('cc', c)"
                                        >
                                            {{ c.name }}
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="mb-1 block">BCC</label>
                                <div>
                                    <div class="mb-2 flex flex-wrap gap-2">
                                        <span v-for="u in bcc" :key="u.id" class="rounded bg-gray-200 px-2 py-1"
                                            >{{ u.name }} <button type="button" @click="removeSelected('bcc', u.id)">✕</button></span
                                        >
                                    </div>
                                    <input v-model="bccQuery" @input="searchUsers('bcc')" placeholder="ユーザー名で検索" />
                                    <ul v-if="bccCandidates.length" class="mt-1 max-h-48 overflow-auto border bg-white">
                                        <li
                                            v-for="c in bccCandidates"
                                            :key="c.id"
                                            class="cursor-pointer p-1 hover:bg-gray-100"
                                            @click="selectUser('bcc', c)"
                                        >
                                            {{ c.name }}
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <div>
                                <label>件名</label>
                                <input v-model="subject" />
                            </div>
                            <div>
                                <label>本文</label>
                                <textarea v-model="body"></textarea>
                            </div>
                            <div>
                                <label>添付</label>
                                <input type="file" multiple @change="onFiles" />
                                <ul>
                                    <li v-for="a in attachments" :key="a.id">{{ a.original_name }}</li>
                                </ul>
                            </div>
                            <div class="mt-4">
                                <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-white">送信</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </AppLayout>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { ref } from 'vue';

const to = ref([]);
const cc = ref([]);
const bcc = ref([]);
const toQuery = ref('');
const ccQuery = ref('');
const bccQuery = ref('');
const toCandidates = ref([]);
const ccCandidates = ref([]);
const bccCandidates = ref([]);
const subject = ref('');
const body = ref('');
const attachments = ref([]);

function onFiles(e) {
    const files = e.target.files;
    for (let i = 0; i < files.length; i++) {
        uploadFile(files[i]);
    }
}

async function searchUsers(kind) {
    const q = kind === 'to' ? toQuery.value : kind === 'cc' ? ccQuery.value : bccQuery.value;
    if (!q || q.length < 1) {
        if (kind === 'to') toCandidates.value = [];
        if (kind === 'cc') ccCandidates.value = [];
        if (kind === 'bcc') bccCandidates.value = [];
        return;
    }
    try {
        const res = await axios.get(route('users.search'), { params: { q } });
        if (kind === 'to') toCandidates.value = res.data;
        if (kind === 'cc') ccCandidates.value = res.data;
        if (kind === 'bcc') bccCandidates.value = res.data;
    } catch (e) {
        console.error(e);
    }
}

function selectUser(kind, user) {
    if (kind === 'to') {
        if (!to.value.find((u) => u.id === user.id)) to.value.push(user);
        toQuery.value = '';
        toCandidates.value = [];
    }
    if (kind === 'cc') {
        if (!cc.value.find((u) => u.id === user.id)) cc.value.push(user);
        ccQuery.value = '';
        ccCandidates.value = [];
    }
    if (kind === 'bcc') {
        if (!bcc.value.find((u) => u.id === user.id)) bcc.value.push(user);
        bccQuery.value = '';
        bccCandidates.value = [];
    }
}

function removeSelected(kind, id) {
    if (kind === 'to') to.value = to.value.filter((u) => u.id !== id);
    if (kind === 'cc') cc.value = cc.value.filter((u) => u.id !== id);
    if (kind === 'bcc') bcc.value = bcc.value.filter((u) => u.id !== id);
}

async function uploadFile(file) {
    const form = new FormData();
    form.append('file', file);
    try {
        const res = await axios.post('/api/uploads', form, { headers: { 'Content-Type': 'multipart/form-data' } });
        attachments.value.push(res.data);
    } catch (e) {
        console.error(e);
    }
}

function submit() {
    const payload = {
        to: to.value.map((u) => u.id),
        cc: cc.value.map((u) => u.id),
        bcc: bcc.value.map((u) => u.id),
        subject: subject.value,
        body: body.value,
        attachments: attachments.value.map((a) => a.id),
    };
    router.post(route('messages.store'), payload);
}
</script>
