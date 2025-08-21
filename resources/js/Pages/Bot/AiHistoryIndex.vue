<template>
  <div class="p-6">
    <h1 class="text-xl font-semibold mb-4">AI 会話履歴</h1>
    <div>
      <table class="w-full table-auto border-collapse">
        <thead>
          <tr class="text-left border-b">
            <th class="py-2">ID</th>
            <th class="py-2">タイトル</th>
            <th class="py-2">メッセージ数</th>
            <th class="py-2">更新</th>
            <th class="py-2">操作</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="conv in conversations.data" :key="conv.id" class="border-b">
            <td class="py-2">{{ conv.id }}</td>
            <td class="py-2">{{ conv.title || '（無題）' }}</td>
            <td class="py-2">{{ conv.messages_count }}</td>
            <td class="py-2">{{ conv.updated_at }}</td>
            <td class="py-2">
              <a :href="`/bot/chat?load_conversation=${conv.id}`" class="bg-blue-600 text-white px-3 py-1 rounded inline-block">表示</a>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
<script setup>
defineProps(['conversations']);
</script>
