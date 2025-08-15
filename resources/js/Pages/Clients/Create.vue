<template>
  <div>
    <h1>クライアント新規作成</h1>
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
      <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded">登録</button>
    </form>
  </div>
</template>
<script setup>
import { useForm } from '@inertiajs/vue3';
const form = useForm({
  name: '',
  detail: '',
  fromSA: false,
});
function submit() {
  form.post(route('clients.store'));
}
</script>
