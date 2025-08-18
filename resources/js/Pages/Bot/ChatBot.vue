<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { ref } from 'vue';

const messages = ref([
  // { id: 1, user: '自分', text: 'こんにちは' },
  // { id: 2, user: 'AI', text: 'こんにちは！ご用件は何ですか？' },
]);
const newMessage = ref('');
const loading = ref(false);

function sendMessage() {
  if (!newMessage.value.trim()) return;
  messages.value.push({ id: Date.now(), user: '自分', text: newMessage.value });
  loading.value = true;
  // ここでAI API呼び出し
  // 例: axios.post('/api/bot/chat', { message: newMessage.value })
  //   .then(res => messages.value.push({ id: Date.now()+1, user: 'AI', text: res.data.reply }))
  //   .finally(() => loading.value = false)
  setTimeout(() => {
    messages.value.push({ id: Date.now()+1, user: 'AI', text: '（AIの返答サンプル）' });
    loading.value = false;
  }, 1000);
  newMessage.value = '';
}
</script>

<template>
  <AppLayout title="AIチャット">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">AIチャット</h2>
    </template>
    <div class="py-6">
      <div class="max-w-2xl mx-auto bg-white shadow rounded p-6">
        <div class="mb-4">
          <a href="/chat" class="text-blue-600 hover:underline">← チャットルーム一覧へ戻る</a>
        </div>
        <div class="h-80 overflow-y-auto border rounded p-4 mb-4 bg-gray-50">
          <div v-for="msg in messages" :key="msg.id" class="mb-2">
            <span class="font-bold" :class="{ 'text-purple-600': msg.user === 'AI' }">
              {{ msg.user }}
            </span>
            <span class="ml-2">{{ msg.text }}</span>
          </div>
          <div v-if="loading" class="text-gray-400">AIが応答中...</div>
        </div>
        <form @submit.prevent="sendMessage" class="flex gap-2">
          <input v-model="newMessage" class="flex-1 border rounded px-3 py-2" placeholder="メッセージを入力..." />
          <button type="submit" class="bg-purple-600 text-white px-4 py-2 rounded" :disabled="loading">送信</button>
        </form>
      </div>
    </div>
  </AppLayout>
</template>
