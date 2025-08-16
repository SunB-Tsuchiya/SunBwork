<template>
  <AppLayout title="案件一覧">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        【進行管理】{{ $page.props.auth.user.name || 'ユーザー' }}さんのページ
      </h2>
    </template>
    <div class="max-w-4xl mx-auto p-6 bg-white rounded shadow">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">案件一覧</h1>
        <Link :href="route('coordinator.project_jobs.create')" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">新規作成</Link>
      </div>
      <div v-if="jobs.length === 0" class="text-gray-500 py-8">登録された案件はありません</div>
      <table v-else class="min-w-full border">
        <thead>
          <tr class="bg-gray-100">
            <th class="px-4 py-2 border">案件名</th>
            <th class="px-4 py-2 border">クライアント名</th>
            <th class="px-4 py-2 border">操作</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="job in jobs" :key="job.id" class="hover:bg-gray-50">
            <td class="px-4 py-2 border">{{ job.name }}</td>
            <td class="px-4 py-2 border">{{ job.client?.name || '-' }}</td>
            <td class="px-4 py-2 border flex gap-2">
              <Link :href="route('coordinator.project_jobs.show', { projectJob: job.id })" class="bg-gray-200 px-2 py-1 rounded">詳細</Link>
              <Link :href="route('coordinator.project_jobs.edit', { projectJob: job.id })" class="bg-yellow-200 px-2 py-1 rounded">編集</Link>
              <button @click="destroy(job.id)" class="bg-red-200 px-2 py-1 rounded">削除</button>
              <button class="bg-green-200 px-2 py-1 rounded">完了</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
const props = defineProps({ jobs: Array, registerFlags: Array, jobid: [Number, String] });
const jobs = props.jobs || [];
const registerFlags = props.registerFlags || [];
// jobid（直前登録ID）があればそれを優先、なければ最新ID
const latestJobId = props.jobid || (jobs.length ? jobs[jobs.length - 1].id : null);
const page = usePage();
onMounted(() => {
  if (page.props.reload) {
    location.reload();
    return;
  }
  // 新規登録直後、teammember/schedule未設定なら案内
  if (registerFlags.length && latestJobId) {
    if (registerFlags.includes('teammember') && registerFlags.includes('schedule')) {
      if (confirm('プロジェクト登録が完了しました。続いてメンバーを登録しますか？')) {
  router.visit(route('coordinator.project_team_members.create', { projectJob: latestJobId }));
      }
    } else if (registerFlags.includes('schedule')) {
      if (confirm('メンバー登録が完了しました。続いてスケジュールを登録しますか？')) {
        router.visit(route('coordinator.project_schedules.index', { projectJob: latestJobId }));
      }
    }
  }
});
function destroy(id) {
  if (confirm('本当に削除しますか？')) {
    router.delete(route('coordinator.project_jobs.destroy', { projectJob: id }));
  }
}
</script>

<style scoped>
/* 必要に応じてスタイル追加 */
</style>
