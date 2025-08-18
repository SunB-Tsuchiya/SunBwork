<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const props = defineProps({
  rooms: Array,
  auth: Object,
  success: String,
});
console.log('ルーム情報：',props.rooms);

const userId = props.auth?.user?.id;

function createRoom() {
  router.visit('/chat/rooms/create');
}

function getRoomName(room) {
  if (room.type === 'private') {
    // nameがnullなら自分以外のユーザー名
    if (!room.name) {
      const other = room.users.find(u => u.id !== userId);
      return other ? other.name : '(相手なし)';
    }
    return room.name;
  }
  return room.name;
}
function getMemberNames(room) {
  // 自分を先頭に、その後他のメンバー名を「・」区切り
  if (!room.users) return '';
  const self = room.users.find(u => u.id === userId);
  const others = room.users.filter(u => u.id !== userId);
  const names = self ? [self.name, ...others.map(u => u.name)] : others.map(u => u.name);
  return names.join('・');
}
</script>
<template>
  <AppLayout title="チャットルーム一覧">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">チャットルーム一覧</h2>
    </template>
    <div class="py-6">
      <div class="max-w-4xl mx-auto bg-white shadow rounded p-6">
        <div v-if="success" class="mb-4 p-3 bg-green-100 text-green-800 border border-green-300 rounded">
          {{ success }}
        </div>
        <button class="bg-blue-600 text-white px-4 py-2 rounded mb-4" @click="createRoom">
          新しいチャットルームを作成
        </button>
        <table class="min-w-full divide-y divide-gray-200 mb-4">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">相手名・ルーム名</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">メンバー</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">種別</th>
              <th class="px-4 py-2"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="room in rooms" :key="room.id" class="hover:bg-gray-50">
              <td class="px-4 py-2">{{ getRoomName(room) }}</td>
              <td class="px-4 py-2">{{ getMemberNames(room) }}</td>
              <td class="px-4 py-2">{{ room.type === 'group' ? 'グループ' : 'パーソナル' }}</td>
              <td class="px-4 py-2">
                <button class="bg-blue-500 text-white px-3 py-1 rounded" @click="router.visit(`/chat/rooms/${room.id}`)">
                  ルームへ
                </button>
              </td>
            </tr>
          </tbody>
        </table>
        <div class="mt-6">
          <a href="/bot/chat" class="text-purple-600 hover:underline">
            AIチャットはこちら
          </a>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
