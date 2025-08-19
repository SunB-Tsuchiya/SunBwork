<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { ref, computed, onMounted, watch, nextTick } from 'vue';
import axios from 'axios';
import { usePage } from '@inertiajs/vue3';

// Inertiaからpropsでroom, messagesを受け取る
const props = defineProps({
  room: { type: Object, default: () => ({ users: [] }) },
  messages: { type: Array, default: () => [] },
});

const page = usePage();
const user = page.props.user;
const messages = ref(Array.isArray(props.messages) ? [...props.messages] : []);
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


// フラッシュメッセージ用
const flashMessage = ref('');
const flashTimeout = ref(null);

import { onUnmounted } from 'vue';
const echoChannel = ref(null);

onMounted(() => {
  // 初期履歴をセット
  messages.value = props.messages ? [...props.messages] : [];
  fetchMessages();
  scrollToLatest();
  if (props.room && props.room.id) {
    console.log('[Echo] サブスクライブ: chatroom.' + props.room.id);
    echoChannel.value = window.Echo.private('chatroom.' + props.room.id)
      .listen('ChatMessageSent', (e) => {
        console.log('[Echo] ChatMessageSent受信:', e);
        console.log('[Echo] props.room.id:', props.room.id, 'e.chat_room_id:', e.chat_room_id);
        if (e.chat_room_id === props.room.id) {
          // すでに同じIDのメッセージが存在する場合は追加しない
          if (!messages.value.some(m => m.id === e.id)) {
            console.log('[Echo] ルームID一致: メッセージ追加前 messages.length=', messages.value.length);
            messages.value.push({
              id: e.id,
              user_id: e.user_id ?? e.from_user_id,
              user_name: e.user_name || (e.user ? e.user.name : ''),
              message: e.body || e.message,
              created_at: e.created_at,
            });
            // 新着メッセージ通知（自分以外の投稿のみ）
            if ((e.user_id ?? e.from_user_id) !== user.id) {
              const sender = e.user_name || (e.user ? e.user.name : '誰か');
              flashMessage.value = `${sender} さんから新着メッセージです`;
              if (flashTimeout.value) clearTimeout(flashTimeout.value);
              flashTimeout.value = setTimeout(() => {
                flashMessage.value = '';
              }, 5000);
            }
            console.log('[Echo] メッセージ追加後 messages.length=', messages.value.length);
            scrollToLatest();
          } else {
            console.log('[Echo] すでに同じIDのメッセージが存在するため追加しない');
          }
        } else {
          console.log('[Echo] ルームID不一致: 受信したが無視');
        }
      });
  }
});

onUnmounted(() => {
  if (echoChannel.value) {
    echoChannel.value.stopListening('ChatMessageSent');
    echoChannel.value = null;
  }
});

const showMembers = ref(false);

// メンバーリスト（自分が一番上、他はID順）
const sortedMembers = computed(() => {
  if (!props.room || !Array.isArray(props.room.users)) return [];
  const self = props.room.users.find(u => u.id === user.id);
  const others = props.room.users.filter(u => u.id !== user.id).sort((a, b) => a.id - b.id);
  return self ? [self, ...others] : others;
});

function getAssignmentName(assignment_id) {
  if (!assignment_id) return '';
  if (typeof assignment_id === 'string') return assignment_id;
  if (!props.room || !Array.isArray(props.room.users)) return assignment_id;
  const member = props.room.users.find(u => u.assignment_id === assignment_id);
  return member && member.assignment ? member.assignment : assignment_id;
}

function getRoomDisplayName() {
  if (!props.room) return '';
  if (props.room.type === 'private') {
    if (!props.room.name && Array.isArray(props.room.users)) {
      const other = props.room.users.find(u => u.id !== user.id);
      return other ? other.name : '(相手なし)';
    }
    return props.room.name;
  }
  return props.room.name;
}

// メッセージ履歴取得（ルームベース）
async function fetchMessages() {
  if (!props.room.id) return;
  try {
    const res = await axios.get(`/chat/rooms/${props.room.id}/messages`);
    if (Array.isArray(res.data)) {
      messages.value = res.data;
      // 未読メッセージを既読に
      markAllAsRead(res.data);
    }
  } catch (e) {
    console.log('line152error');
    messages.value = [];
  }
}

// 未読メッセージを既読にする
async function markAllAsRead(msgs) {
  if (!Array.isArray(msgs)) return;
  for (const msg of msgs) {
    // 自分以外のメッセージのみ既読APIを呼ぶ
    if (!msg.is_read && msg.user_id !== user.id) {
      await markAsRead(msg.id);
    }
  }
}

// 既読API呼び出し
async function markAsRead(messageId) {
  try {
    await axios.post(`/api/chat/messages/${messageId}/read`);
    // 成功時はローカルのis_readもtrueに
    const target = messages.value.find(m => m.id === messageId);
    if (target) target.is_read = true;
  } catch (e) {
    const target = messages.value.find(m => m.id === messageId);
    const userName = target ? target.user_name : '不明';
    const messageBody = target ? target.message : '';
    console.error(`markAsRead失敗: ユーザー=${userName}, メッセージ="${messageBody}", エラー=`, e);
  }
}

// 送信処理
async function sendMessage() {
  if (!newMessage.value.trim() || !props.room.id) return;
  try {
    const res = await axios.post(`/chat/rooms/${props.room.id}/messages`, {
      body: newMessage.value,
    });
    // 送信成功時は入力欄のみクリアし、messagesへのpushはEchoイベントに任せる
    if (res.data && res.data.id) {
      newMessage.value = '';
      // scrollToLatest()はEchoイベントで呼ばれるため不要
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
    <!-- フラッシュメッセージ -->
    <transition name="fade">
      <div v-if="flashMessage" class="fixed top-6 left-1/2 transform -translate-x-1/2 z-50 px-6 py-3 rounded shadow-lg text-lg border-2 border-purple-500 bg-white text-purple-800 font-semibold min-w-[280px] text-center">
        {{ flashMessage }}
      </div>
    </transition>
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
            <div v-for="(msg, idx) in messages" :key="msg.id" :ref="idx === messages.length - 1 ? lastMessageRef : null"
              class="mb-2 flex" :class="msg.user_id === user.id ? 'justify-start' : 'justify-end'">
              <div :class="msg.user_id === user.id ? 'bg-white' : 'bg-blue-50'" class="rounded px-3 py-2 max-w-[70%] flex flex-col relative">
                <span class="font-bold text-xs" :class="{ 'text-blue-600': msg.user_id === user.id }">
                  {{ msg.user_name }}
                  <span v-if="!msg.is_read && msg.user_id !== user.id" class="ml-2 px-2 py-0.5 bg-purple-100 text-purple-700 rounded text-xxs align-middle">未読</span>
                </span>
                <span class="break-words">{{ msg.message }}</span>
                <span class="text-xs text-gray-400 self-end">{{ msg.created_at }}</span>
              </div>
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
<style scoped>
.fade-enter-active, .fade-leave-active {
  transition: opacity 0.5s;
}
.fade-enter-from, .fade-leave-to {
  opacity: 0;
}
</style>