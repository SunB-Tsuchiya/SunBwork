<script setup>
import { computed } from 'vue';
const props = defineProps({ clients: { type: Array, default: () => [] } });
const first = computed(() => props.clients.slice(0, 2).map(client => client.name).join('・'));
</script>
<template>
    <details v-if="clients.length > 2" class="mt-1 text-sm text-gray-500">
        <summary class="cursor-pointer">{{ first }}・ほか{{ clients.length - 2 }}社</summary>
        <ul class="mt-1 space-y-1"><li v-for="client in clients" :key="client.id">{{ client.name }}</li></ul>
    </details>
    <p v-else-if="clients.length" class="mt-1 text-sm text-gray-500">{{ first }}</p>
</template>
