<template>
  <div class="p-8">
    <h1 class="text-2xl font-bold mb-4">API/認証デバッグ</h1>
    <button @click="runAllChecks" class="bg-green-600 text-white px-4 py-2 rounded mb-4">全基本チェック実行</button>
    <div class="mb-4">
      <button @click="checkApiUser" class="bg-blue-600 text-white px-4 py-2 rounded mr-2">/api/user を確認</button>
      <button @click="checkMarkAsRead" class="bg-purple-600 text-white px-4 py-2 rounded">/api/chat/messages/1/read を確認</button>
    </div>
    <div v-if="userResult !== null" class="mt-2">
      <div class="font-bold">/api/user レスポンス:</div>
      <pre class="bg-gray-100 p-2 rounded">{{ userResult }}</pre>
    </div>
    <div v-if="markAsReadResult !== null" class="mt-2">
      <div class="font-bold">/api/chat/messages/1/read レスポンス:</div>
      <pre class="bg-gray-100 p-2 rounded">{{ markAsReadResult }}</pre>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import axios from 'axios';

const userResult = ref(null);
const markAsReadResult = ref(null);

// --- 基本チェック関数 ---
function runAllChecks() {
  // 1. axios baseURL チェック
  console.log('【axios baseURLのテスト】：', axios.defaults.baseURL);
  // 2. withCredentials チェック
  console.log('【withCredentialsのテスト】：', axios.defaults.withCredentials);
  // 3. Cookie チェック
  console.log('【Cookieのテスト】：', document.cookie);
  // 4. XSRF-TOKEN チェック
  const xsrf = document.cookie.split('; ').find(row => row.startsWith('XSRF-TOKEN='));
  console.log('【XSRF-TOKENのテスト】：', xsrf);
  // 5. laravel_session チェック
  const session = document.cookie.split('; ').find(row => row.startsWith('laravel_session='));
  console.log('【laravel_sessionのテスト】：', session);
  // 6. /api/user テスト
  checkApiUser();
  // 7. /api/chat/messages/1/read テスト
  checkMarkAsRead();
}

// /api/user のテスト
async function checkApiUser() {
  console.log('【/api/userのテスト】：リクエスト開始');
  userResult.value = 'Loading...';
  try {
    const res = await axios.get('/api/user');
    userResult.value = JSON.stringify(res.data, null, 2);
    console.log('【/api/userのテスト】：レスポンス', res.data);
  } catch (e) {
    userResult.value = e.response ? JSON.stringify(e.response.data, null, 2) : e.message;
    console.log('【/api/userのテスト】：エラー', e);
  }
}

// /api/chat/messages/1/read のテスト
async function checkMarkAsRead() {
  console.log('【/api/chat/messages/1/readのテスト】：リクエスト開始');
  markAsReadResult.value = 'Loading...';
  try {
    const res = await axios.post('/api/chat/messages/1/read');
    markAsReadResult.value = JSON.stringify(res.data, null, 2);
    console.log('【/api/chat/messages/1/readのテスト】：レスポンス', res.data);
  } catch (e) {
    markAsReadResult.value = e.response ? JSON.stringify(e.response.data, null, 2) : e.message;
    console.log('【/api/chat/messages/1/readのテスト】：エラー', e);
  }
}
</script>

<style scoped>
pre {
  white-space: pre-wrap;
  word-break: break-all;
}
</style>
