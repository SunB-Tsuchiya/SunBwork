<template>
  <AppLayout title="案件編集">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        【進行管理】{{ $page.props.auth.user.name || 'ユーザー' }}さんのページ
      </h2>
    </template>
    <div class="max-w-2xl mx-auto p-6 bg-white rounded shadow">
      <h1 class="text-2xl font-bold mb-6">案件編集</h1>
      <form @submit.prevent="submit">
        <div class="mb-4">
          <label class="block mb-1 font-semibold">伝票番号</label>
          <input v-model="form.jobcode" type="text" class="w-full border rounded px-3 py-2" required />
        </div>
        <div class="mb-4">
          <label class="block mb-1 font-semibold">案件タイトル</label>
          <input v-model="form.name" type="text" class="w-full border rounded px-3 py-2" required />
        </div>
        <div class="mb-4">
          <label class="block mb-1 font-semibold">担当ユーザー</label>
          <input v-model="form.user_name" type="text" class="w-full border rounded px-3 py-2 bg-gray-100" readonly />
        </div>
        <div class="mb-4">
          <label class="block mb-1 font-semibold">クライアント</label>
          <div class="flex gap-2 items-center">
            ID:<input v-model="form.client_id" type="number" class="w-16 border rounded px-3 py-2 bg-gray-100" readonly />
            <input v-model="form.client_name" type="text" class="w-60 border rounded px-3 py-2 bg-gray-100" readonly />
          </div>
        </div>
        <div class="mb-4">
          <label class="block mb-1 font-semibold">詳細</label>
          <textarea v-model="form.detail" class="w-full border rounded px-3 py-2" rows="3"></textarea>
        </div>

        <!-- スケジュール設定 -->
        <div class="mb-4">
          <h3 class="font-semibold mb-1">スケジュール設定</h3>
          <div class="flex items-center gap-4">
            <div
              :class="[
                'status-box px-4 py-2 rounded w-32',
                form.schedule ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500'
              ]"
            >
              {{ form.schedule ? '決定済み' : '未設定' }}
            </div>
            <button type="button" class="bg-blue-100 text-blue-700 px-4 py-2 rounded" @click="goSchedule">スケジュール設定</button>
          </div>
        </div>
        <!-- メンバー選定 -->
        <div class="mb-4">
          <h3 class="font-semibold mb-1">メンバー選定</h3>
          <div class="flex items-center gap-4">
            <div
              class="status-box px-4 py-2 rounded w-32"
              :class="form.teammember ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500'"
            >
              {{ form.teammember ? '決定済み' : '未設定' }}
            </div>
            <button type="button" class="bg-green-100 text-green-700 px-4 py-2 rounded" @click="goProjectTeammember">チームメンバー設定</button>
          </div>
        </div>
        <div class="mt-6 flex gap-4">
          <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">更新</button>
          <Link :href="route('coordinator.project_jobs.index')" class="bg-gray-200 px-4 py-2 rounded">一覧へ戻る</Link>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, useForm, usePage, router } from '@inertiajs/vue3';
import { computed } from 'vue';
const props = defineProps({ job: Object });
const page = usePage();
// user_idからユーザー名を取得
const userName = computed(() => {
  if (props.job.user && props.job.user.name) return props.job.user.name;
  if (page.props.user && page.props.user.id === props.job.user_id) return page.props.user.name;
  return '';
});
function decodeField(val, fallback = '') {
  if (!val) return fallback;
  if (typeof val === 'object') return val;
  try {
    return JSON.parse(val);
  } catch {
    return fallback;
  }
}
const form = useForm({
  jobcode: props.job.jobcode || '',
  name: props.job.name || '',
  user_id: props.job.user_id || '',
  user_name: userName.value,
  client_id: props.job.client_id || '',
  client_name: props.job.client?.name || '',
  detail: decodeField(props.job.detail)?.text || '',
  teammember: decodeField(props.job.teammember) || null,
  schedule: decodeField(props.job.schedule) || null,
});
function submit() {
  form.put(route('coordinator.project_jobs.update', { projectJob: props.job.id }));
}
function goSchedule() {
  router.visit(route('coordinator.project_jobs.schedule', { projectJob: props.job.id }));
}
function goProjectTeammember() {
  router.visit(route('coordinator.project_team_members.create', { projectJob: props.job.id }));
}
</script>

<style scoped>
</style>
