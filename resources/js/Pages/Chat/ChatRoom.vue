<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { ref, computed, onMounted, watch, nextTick } from 'vue';
import axios from 'axios';

// Inertiaからpropsでroom, auth, messagesを受け取る
const props = defineProps({
  room: Object,
  auth: Object,
  messages: Array,
});

const user = props.auth.user;
const messages = ref([]);
const newMessage = ref('');

// スクロール用ref
const messageArea = ref(null);
const lastMessageRef = ref(null);

// 最新メッセージが追加されたら下にスクロール
function scrollToLatest() {
  nextTick(() => {
    if (lastMessageRef.value && messageArea.value) {
      // IntersectionObserverで最新メッセージの先頭が見えるようにスクロール
      const lastEl = lastMessageRef.value;
      const area = messageArea.value;
      // 最新メッセージの高さがエリアの1/2以上なら、先頭が見えるようにスクロール
      const lastRect = lastEl.getBoundingClientRect();
      const areaRect = area.getBoundingClientRect();
      if (lastEl.offsetHeight > area.offsetHeight / 2) {
        // 最新メッセージの先頭が見えるように
        area.scrollTop = lastEl.offsetTop - area.offsetTop;
      } else {
        // 通常は一番下まで
        area.scrollTop = area.scrollHeight;
      }
    }
  });
}

onMounted(() => {
  scrollToLatest();
});

watch(messages, () => {
  scrollToLatest();
});

// モーダル表示状態
const showMembers = ref(false);

// メンバーリスト（自分が一番上、他はID順）
const sortedMembers = computed(() => {
  if (!props.room.users) return [];
  const self = props.room.users.find(u => u.id === user.id);
  const others = props.room.users.filter(u => u.id !== user.id).sort((a, b) => a.id - b.id);
  return self ? [self, ...others] : others;
});

function getAssignmentName(assignment_id) {
  // assignment_idがnameの場合もあるので両対応
  if (!assignment_id) return '';
  if (typeof assignment_id === 'string') return assignment_id;
  // assignment_idが数値の場合はroom.usersから取得
  const member = props.room.users.find(u => u.assignment_id === assignment_id);
  return member && member.assignment ? member.assignment : assignment_id;
}

function getRoomDisplayName() {
  if (props.room.type === 'private') {
    // nameがnullなら自分以外のユーザー名
    if (!props.room.name) {
      const other = props.room.users.find(u => u.id !== user.id);
      return other ? other.name : '(相手なし)';
    }
    return props.room.name;
  }
  return props.room.name;
}

// 1対1チャットの相手IDを取得
const otherUserId = computed(() => {
  if (!props.room.users) return null;
  const other = props.room.users.find(u => u.id !== user.id);
  return other ? other.id : null;
});

// メッセージ履歴取得
async function fetchMessages() {
  if (!otherUserId.value) return;
  try {
    const res = await axios.get(`/chat/messages/${otherUserId.value}`);
    if (Array.isArray(res.data)) {
      messages.value = res.data;
    }
  } catch (e) {
    messages.value = [];
  }
}

// 送信処理
async function sendMessage() {
  if (!newMessage.value.trim() || !otherUserId.value) return;
  try {
    const res = await axios.post('/chat/messages', {
      to_user_id: otherUserId.value,
      body: newMessage.value,
    });
    if (res.data && res.data.id) {
      messages.value.push({
        id: res.data.id,
        user_id: user.id,
        user_name: user.name,
        message: newMessage.value,
        created_at: res.data.created_at,
      });
      newMessage.value = '';
      scrollToLatest();
    }
  } catch (e) {
    alert('送信に失敗しました');
  }
}

onMounted(() => {
  fetchMessages();
  scrollToLatest();
});
</script>

<template>
  <AppLayout title="チャットルーム">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">チャットルーム</h2>
    </template>
    <div class="py-6">
      <div class="max-w-4xl mx-auto bg-white shadow rounded p-6">
        <div class="mb-4">
          <a href="/chat/rooms" class="text-blue-600 hover:underline">← ルーム一覧へ戻る</a>
        </div>
        <!-- ルーム名・相手名表示 -->
        <div class="flex items-center mb-4">
          <span class="text-lg font-bold">
            <template v-if="props.room.type === 'private'">
              {{ getRoomDisplayName() }}
            </template>
            <template v-else>
              {{ props.room.name }}
            </template>
          </span>
          <template v-if="props.room.type === 'group'">
            <button class="ml-4 px-3 py-1 bg-gray-200 rounded hover:bg-gray-300 text-sm" @click="showMembers = true">メンバー</button>
          </template>
        </div>
        <!-- メンバーモーダル -->
        <div v-if="showMembers" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40">
          <div class="bg-white rounded shadow-lg p-6 min-w-[300px]">
            <div class="flex justify-between items-center mb-4">
              <span class="font-bold text-lg">メンバー一覧</span>
              <button @click="showMembers = false" class="text-gray-500 hover:text-gray-800">×</button>
            </div>
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">担当</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">名前</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="member in sortedMembers" :key="member.id">
                  <td class="px-4 py-2">{{ getAssignmentName(member.assignment_id) }}</td>
                  <td class="px-4 py-2">{{ member.name }}</td>
                </tr>
              </tbody>
            </table>
            <div class="flex justify-end mt-4">
              <button @click="showMembers = false" class="px-4 py-2 bg-blue-600 text-white rounded">閉じる</button>
            </div>
          </div>
        </div>
        <div ref="messageArea" class="h-96 overflow-y-auto border rounded p-4 mb-4 bg-gray-50">
          <template v-if="messages.length === 0">
            <div class="text-gray-400 text-center my-20">メッセージを入力して会話を開始してください</div>
          </template>
          <template v-else>
            <div v-for="(msg, idx) in messages" :key="msg.id" :ref="idx === messages.length - 1 ? lastMessageRef : null" class="mb-2">
              <span class="font-bold" :class="{ 'text-blue-600': msg.user_id === user.id }">
                {{ msg.user_name }}
              </span>
              <span class="ml-2">{{ msg.message }}</span>
              <span class="ml-2 text-xs text-gray-400">{{ msg.created_at }}</span>
            </div>
          </template>
        </div>
        <form @submit.prevent="sendMessage" class="flex gap-2">
          <input v-model="newMessage" class="flex-1 border rounded px-3 py-2" placeholder="メッセージを入力..." />
          <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">送信</button>
        </form>
      </div>
    </div>
  </AppLayout>
</template>
