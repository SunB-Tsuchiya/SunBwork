<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { computed } from 'vue';
const props = defineProps({
  job: Object,
  members: Array
});

// 担当バッジ色分け関数
function getAssignmentBadgeClass(assignment) {
  switch (assignment) {
    case '進行管理':
      return 'bg-green-100 text-green-800';
    case 'オペレーター':
      return 'bg-blue-100 text-blue-800';
    case '校正':
      return 'bg-yellow-100 text-yellow-800';
    default:
      return 'bg-gray-100 text-gray-800';
  }
}

// 並び順: 作成者→進行管理→オペレーター→校正→その他
const sortedMembers = computed(() => {
  if (!props.members) return [];
  const creatorId = props.job.user_id;
  const order = ['進行管理', 'オペレーター', '校正'];
  // 作成者を最上部
  const creator = props.members.find(m => m.id === creatorId);
  const others = props.members.filter(m => m.id !== creatorId);
  others.sort((a, b) => {
    const aIdx = order.indexOf(a.assignment);
    const bIdx = order.indexOf(b.assignment);
    if (aIdx === -1 && bIdx === -1) return 0;
    if (aIdx === -1) return 1;
    if (bIdx === -1) return -1;
    return aIdx - bIdx;
  });
  return creator ? [creator, ...others] : others;
});
</script>
<template>
  <AppLayout title="ジョブ詳細">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">ジョブ詳細</h2>
    </template>
    <div class="py-6 space-y-8">
      <!-- 1. ジョブ基本情報ブロック -->
      <div class="max-w-2xl mx-auto bg-white shadow rounded p-6">
        <!-- 1行目: ジョブID・伝票No.・クライアント名 横並び（モバイルは縦） -->
        <div class="flex flex-col md:flex-row md:items-center md:space-x-8 mb-4">
          <div class="mb-2 md:mb-0">
            <label class="block text-sm font-medium text-gray-700">ジョブID</label>
            <div class="mt-1 text-lg text-gray-900">{{ job.id }}</div>
          </div>
          <div class="mb-2 md:mb-0">
            <label class="block text-sm font-medium text-gray-700">伝票No.</label>
            <div class="mt-1 text-gray-900">{{ job.jobcode }}</div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">クライアント名</label>
            <div class="mt-1 text-gray-900">{{ job.client?.name || '-' }}</div>
          </div>
        </div>
        <!-- 2行目: 名前 -->
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700">名前</label>
          <div class="mt-1 text-gray-900">{{ job.name }}</div>
        </div>
        <!-- 3行目: 詳細 -->
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700">詳細</label>
          <div class="mt-1 text-gray-900 whitespace-pre-wrap">
            <span v-if="job.detail && typeof job.detail === 'object' && job.detail.text">
              {{ job.detail.text }}
            </span>
            <span v-else-if="job.detail && typeof job.detail === 'object'">
              {{ JSON.stringify(job.detail, null, 2) }}
            </span>
            <span v-else>{{ job.detail || '-' }}</span>
          </div>
        </div>
      </div>
      <!-- 2. チームメンバーブロック -->
      <div class="max-w-2xl mx-auto bg-white shadow rounded p-6">
        <div class="mb-4 text-lg font-bold border-b pb-1">チームメンバー</div>
        <table class="min-w-full divide-y divide-gray-200">
          <thead>
            <tr>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">名前</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">部署</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">担当</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="member in sortedMembers" :key="member.id"
                :class="[member.id === job.user_id ? 'bg-blue-50' : '','hover:bg-gray-50']">
              <td class="px-4 py-2">{{ member.id }}</td>
              <td class="px-4 py-2">{{ member.name }}</td>
              <td class="px-4 py-2">{{ member.department }}</td>
              <td class="px-4 py-2">
                <span :class="getAssignmentBadgeClass(member.assignment)" class="inline-flex px-2 py-1 text-xs font-semibold rounded-full">
                  {{ member.assignment }}
                </span>
                <span v-if="member.id === job.user_id" class="ml-2 text-xs text-blue-700 font-bold">リーダー</span>
              </td>
            </tr>
            <tr v-if="!sortedMembers || sortedMembers.length === 0">
              <td colspan="4" class="text-center text-gray-400 py-4">メンバーがいません</td>
            </tr>
          </tbody>
        </table>
      </div>
      <!-- 3. スケジュールチャートブロック（後で実装） -->
      <div class="max-w-2xl mx-auto bg-white shadow rounded p-6">
        <div class="mb-4 text-lg font-bold border-b pb-1">スケジュールチャート</div>
        <div class="text-gray-400">（後日実装予定）</div>
      </div>
    </div>
  </AppLayout>
</template>
