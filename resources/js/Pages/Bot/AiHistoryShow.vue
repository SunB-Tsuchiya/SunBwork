<template>
  <div class="p-6">
    <h1 class="text-xl font-semibold mb-4">会話履歴 #{{ conversation.id }}</h1>
    <div class="mb-4"><strong>システムプロンプト:</strong> {{ conversation.system_prompt }}</div>
    <div class="space-y-3">
      <div v-for="m in conversation.messages" :key="m.id" class="p-3 border rounded">
        <div class="text-xs text-gray-500">{{ m.role }} • {{ m.created_at }}</div>
        <div class="mt-1">{{ m.content }}</div>
      </div>
    </div>
  </div>
</template>
<script setup>
defineProps(['conversation']);
</script>
