<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { ref, watch } from 'vue';
import axios from 'axios';
import { onMounted, onUnmounted } from 'vue';
import { usePage } from '@inertiajs/vue3';


const page = usePage();
const user = page.props.user; // これを追加
let echoChannel = null;
const props = defineProps({
  users: Array
});
const selectedUser = ref(null);
const messages = ref([]);
const newMessage = ref('');
const loading = ref(false);
const errorMsg = ref('');

const userId = user.id;

function selectUser(user) {
  selectedUser.value = user;
  errorMsg.value = '';

  // チャンネル購読の再設定
  if (echoChannel) {
    echoChannel.stopListening('ChatMessageSent');
    echoChannel = null;
  }
  if (user && user.id !== 'ai' && userId) {
      echoChannel = window.Echo.private('chat.' + userId)
        .listen('ChatMessageSent', (e) => {
          // console.log('受信イベント:', e); // ★ここを追加
          if (
            (e.from_user_id === user.id && e.to_user_id === Number(userId)) ||
            (e.from_user_id === Number(userId) && e.to_user_id === user.id)
          ) {
            messages.value.push(e);
          }
        });
  }
}
// ページ離脱時にチャンネル購読解除
onUnmounted(() => {
  if (echoChannel) {
    echoChannel.stopListening('ChatMessageSent');
    echoChannel = null;
  }
});

// 相手選択時に履歴取得 or AI履歴初期化
watch(selectedUser, async (user) => {
  if (!user) return;
  errorMsg.value = '';
  if (user.id === 'ai') {
    messages.value = [];
    return;
  }
  loading.value = true;
  try {
    const res = await axios.get(`/chat/messages/${user.id}`);
    if (res.data.error) {
      errorMsg.value = res.data.error;
      messages.value = [];
    } else {
      messages.value = res.data;
    }
  } catch (e) {
    errorMsg.value = '履歴取得エラー: ' + (e?.response?.data?.error || e.message);
    messages.value = [];
  } finally {
    loading.value = false;
  }
});

async function sendMessage() {
  if (!newMessage.value.trim() || !selectedUser.value) return;
  errorMsg.value = '';
  const payload = {
    to_user_id: selectedUser.value.id,
    body: newMessage.value
  };
  try {
    const res = await axios.post('/chat/messages', payload);
    if (res.data.error) {
      errorMsg.value = res.data.error;
    } else {
      // ここを削除：重複防止
      // messages.value.push(res.data);
      newMessage.value = '';
    }
  } catch (e) {
    errorMsg.value = '送信エラー: ' + (e?.response?.data?.error || e.message);
  }
}

// AIチャット送信
async function sendAIMessage() {
  if (!newMessage.value.trim()) return;
  errorMsg.value = '';
  const userMsg = { role: 'user', content: newMessage.value };
  messages.value.push(userMsg);
  const apiKey = import.meta.env.VITE_OPENAI_API_KEY || '';
  try {
    const res = await axios.post('https://api.openai.com/v1/chat/completions', {
      model: 'gpt-4o-mini',
      messages: [
        ...messages.value.map(m => ({ role: m.role, content: m.content }))
      ],
      max_tokens: 512
    }, {
      headers: { 'Authorization': `Bearer ${apiKey}` }
    });
    const aiMsg = res.data.choices[0].message;
    messages.value.push({ role: 'assistant', content: aiMsg.content });
  } catch (e) {
    errorMsg.value = 'AI応答エラー: ' + (e?.response?.data?.error || e.message);
    messages.value.push({ role: 'assistant', content: 'AI応答エラー' });
  }
  newMessage.value = '';
}
</script>
<template>
  <AppLayout title="チャット">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">チャット</h2>
    </template>
    <div class="py-6">
      <div class="max-w-4xl mx-auto bg-white shadow rounded p-6">
        <div class="mb-4">
          <h3 class="text-lg font-medium text-gray-900 mb-2">チャット相手を選択</h3>
          <ul>
            <li class="mb-2">
              <button @click="selectUser({ id: 'ai', name: 'AI', user_role: 'bot' })" class="text-purple-600 hover:underline">
                AIとチャット <span class="text-xs text-gray-500">(gpt-4o-mini)</span>
              </button>
            </li>
            <li v-for="user in users" :key="user.id" class="mb-2">
              <button @click="selectUser(user)" class="text-blue-600 hover:underline">
                {{ user.name }} <span class="text-xs text-gray-500">({{ user.user_role }})</span>
              </button>
            </li>
          </ul>
        </div>
        <div v-if="selectedUser" class="mt-6">
          <div class="font-bold mb-2 text-lg">
            <span v-if="selectedUser.id === 'ai'">AI (gpt-4o-mini) とのチャット</span>
            <span v-else>{{ selectedUser.name }}さんとのチャット</span>
          </div>
          <div v-if="errorMsg" class="mb-2 text-red-600 bg-red-50 border border-red-200 rounded px-3 py-2">{{ errorMsg }}</div>
          <div v-if="selectedUser.id === 'ai'">
            <div class="border rounded p-4 bg-gray-50 min-h-[200px] max-h-[300px] overflow-y-auto mb-2 flex flex-col gap-2">
              <div v-for="(msg, i) in messages" :key="i" class="flex" :class="msg.role === 'assistant' ? 'justify-start' : 'justify-end'">
                <div :class="msg.role === 'assistant' ? 'bg-purple-100 text-purple-900' : 'bg-blue-100 text-blue-900'" class="rounded-lg px-3 py-2 max-w-[70%] shadow-sm">
                  <span class="block text-xs font-semibold mb-1" v-if="msg.role === 'assistant'">AI</span>
                  <span class="block text-xs font-semibold mb-1" v-else>自分</span>
                  <span class="whitespace-pre-line">{{ msg.content }}</span>
                </div>
              </div>
              <div v-if="messages.length === 0" class="text-gray-400">メッセージはありません</div>
            </div>
            <form class="flex gap-2 mt-2" @submit.prevent="sendAIMessage">
              <input v-model="newMessage" type="text" class="flex-1 border rounded px-2 py-2 focus:outline-none focus:ring-2 focus:ring-purple-300" placeholder="AIに質問..." autocomplete="off" />
              <button type="submit" class="bg-purple-600 text-white px-4 py-2 rounded shadow hover:bg-purple-700 transition" :disabled="!newMessage.trim()">送信</button>
            </form>
          </div>
          <div v-else>
            <div class="border rounded p-4 bg-gray-50 min-h-[200px] max-h-[300px] overflow-y-auto flex flex-col gap-2">
              <div v-if="loading" class="text-gray-400">読み込み中...</div>
              <template v-else>
                <div v-for="msg in messages" :key="msg.id" class="flex" :class="msg.from_user_id === selectedUser.id ? 'justify-start' : 'justify-end'">
                  <div :class="msg.from_user_id === selectedUser.id ? 'bg-gray-100 text-gray-900' : 'bg-blue-100 text-blue-900'" class="rounded-lg px-3 py-2 max-w-[70%] shadow-sm">
                    <span class="block text-xs font-semibold mb-1" v-if="msg.from_user_id === selectedUser.id">{{ selectedUser.name }}</span>
                    <span class="block text-xs font-semibold mb-1" v-else>自分</span>
                    <span class="whitespace-pre-line">{{ msg.body }}</span>
                  </div>
                </div>
                <div v-if="messages.length === 0" class="text-gray-400">メッセージはありません</div>
              </template>
            </div>
            <form class="flex gap-2 mt-2" @submit.prevent="sendMessage">
              <input v-model="newMessage" type="text" class="flex-1 border rounded px-2 py-2 focus:outline-none focus:ring-2 focus:ring-blue-300" placeholder="メッセージを入力..." autocomplete="off" />
              <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700 transition" :disabled="!newMessage.trim()">送信</button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
