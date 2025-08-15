<template>
  <div>
    <h1>クライアント編集</h1>
    <form @submit.prevent="submit">
      <div>
        <label>名前</label>
        <input v-model="form.name" type="text" required class="border rounded px-2 py-1" />
      </div>
      <div>
        <label>詳細</label>
        <textarea v-model="form.detail" class="border rounded px-2 py-1"></textarea>
      </div>
      <div>
        <label>
          <input type="checkbox" v-model="form.fromSA" /> 独自案件
        </label>
      </div>
      <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded">更新</button>
    </form>
  </div>
</template>
<script setup>
import { useForm } from '@inertiajs/vue3';
const props = defineProps({ client: Object });
const form = useForm({
  name: props.client.name,
  detail: props.client.detail,
  fromSA: props.client.fromSA,
});
function submit() {
  form.put(route('clients.update', props.client.id));
}
</script>
