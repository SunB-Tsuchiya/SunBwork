<template>
    <div class="p-8">
        <h1 class="mb-4 text-2xl font-bold">API/認証デバッグ</h1>
        <button @click="runAllChecks" class="mb-4 rounded bg-green-600 px-4 py-2 text-white">全基本チェック実行</button>
        <div class="mb-4">
            <button @click="checkApiUser" class="mr-2 rounded bg-blue-600 px-4 py-2 text-white">/api/user を確認</button>
            <button @click="checkMarkAsRead" class="rounded bg-purple-600 px-4 py-2 text-white">/api/chat/messages/1/read を確認</button>
        </div>
        <div v-if="userResult !== null" class="mt-2">
            <div class="font-bold">/api/user レスポンス:</div>
            <pre class="rounded bg-gray-100 p-2">{{ userResult }}</pre>
        </div>
        <div v-if="markAsReadResult !== null" class="mt-2">
            <div class="font-bold">/api/chat/messages/1/read レスポンス:</div>
            <pre class="rounded bg-gray-100 p-2">{{ markAsReadResult }}</pre>
        </div>
    </div>
</template>

<script setup>
import axios from 'axios';
import { ref } from 'vue';

const userResult = ref(null);
const markAsReadResult = ref(null);

// --- 基本チェック関数 ---
function runAllChecks() {
    // 1. axios baseURL チェック
    // debug logging removed
    // 2. withCredentials チェック
    // debug logging removed
    // 3. Cookie チェック
    // debug logging removed
    // 4. XSRF-TOKEN チェック
    const xsrf = document.cookie.split('; ').find((row) => row.startsWith('XSRF-TOKEN='));
    // debug logging removed
    // 5. laravel_session チェック
    const session = document.cookie.split('; ').find((row) => row.startsWith('laravel_session='));
    // debug logging removed
    // 6. /api/user テスト
    checkApiUser();
    // 7. /api/chat/messages/1/read テスト
    checkMarkAsRead();
}

// /api/user のテスト
async function checkApiUser() {
    // request started (debug logging removed)
    userResult.value = 'Loading...';
    try {
        const res = await axios.get('/api/user');
        userResult.value = JSON.stringify(res.data, null, 2);
        // response received
    } catch (e) {
        userResult.value = e.response ? JSON.stringify(e.response.data, null, 2) : e.message;
        // error received
    }
}

// /api/chat/messages/1/read のテスト
async function checkMarkAsRead() {
    // request started (debug logging removed)
    markAsReadResult.value = 'Loading...';
    try {
        const res = await axios.post('/api/chat/messages/1/read');
        markAsReadResult.value = JSON.stringify(res.data, null, 2);
        // response received
    } catch (e) {
        markAsReadResult.value = e.response ? JSON.stringify(e.response.data, null, 2) : e.message;
        // error received
    }
}
</script>

<style scoped>
pre {
    white-space: pre-wrap;
    word-break: break-all;
}
</style>
