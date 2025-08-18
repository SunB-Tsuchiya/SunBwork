<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { ref } from 'vue';

const rooms = ref([
  // ここにAPIから取得したルーム一覧をセット
  // 例: { id: 1, name: '山本みつる' }, { id: 2, name: 'グループA' }
]);

function createRoom() {
  // 新規ルーム作成処理
  // 例: ルーム作成API呼び出し後、roomsに追加
}
</script>

<template>
  <AppLayout title="チャットルーム一覧">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">チャットルーム一覧</h2>
    </template>
    <div class="py-6">
      <div class="max-w-4xl mx-auto bg-white shadow rounded p-6">
        <button class="bg-blue-600 text-white px-4 py-2 rounded mb-4" @click="createRoom">
          新しいチャットルームを作成
        </button>
        <ul>
          <li v-for="room in rooms" :key="room.id" class="mb-2">
            <a :href="`/chat/rooms/${room.id}`" class="text-blue-600 hover:underline">
              {{ room.name }}
            </a>
          </li>
        </ul>
        <div class="mt-6">
          <a href="/bot/chat" class="text-purple-600 hover:underline">
            AIチャットはこちら
          </a>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
