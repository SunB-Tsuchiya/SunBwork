<template>
  <AppLayout title="チームメンバー管理">
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">チームメンバー管理</h2>
      </div>
    </template>
    <div class="rounded bg-white p-6 shadow">
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-lg font-medium text-gray-900">登録チームメンバー一覧</h3>
              <div class="flex items-center space-x-2">
                <button @click="openSearchModal" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">絞り込み</button>
                <button @click="clearSearch" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded">クリア</button>
              </div>
            </div>
            <DialogModal :show="showSearchModal" @close="closeSearchModal">
              <template #title>メンバー検索</template>
              <template #content>
                <div class="mb-4">
                  <label class="block mb-1 font-semibold">部署</label>
                  <select v-model="selectedDepartmentId" class="w-full border rounded px-3 py-2">
                    <option value="">-- 部署を選択してください --</option>
                    <option v-for="department in departments" :key="department.id" :value="String(department.id)">{{ department.name }}</option>
                  </select>
                </div>
                <div class="mb-4">
                  <label class="block mb-1 font-semibold">担当</label>
                  <select v-model="selectedAssignmentId" class="w-full border rounded px-3 py-2" :disabled="!selectedDepartmentId">
                    <option value="">-- 担当を選択してください --</option>
                    <option v-for="assignment in filteredAssignments" :key="assignment.id" :value="String(assignment.id)">{{ assignment.name }}</option>
                  </select>
                </div>
              </template>
              <template #footer>
                <button class="bg-gray-300 px-4 py-2 rounded mr-2" @click="closeSearchModal">閉じる</button>
                <button class="bg-blue-600 text-white px-4 py-2 rounded" @click="doSearch">絞り込み</button>
              </template>
            </DialogModal>
            <div class="overflow-x-auto">
              <div class="text-lg font-bold mb-2 border-b pb-1">メンバー一覧</div>
              <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      <input type="checkbox" :checked="allChecked" @change="toggleAllMembers">
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">名前</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">部署</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">担当</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <tr v-for="member in filteredMembers" :key="member.id" class="hover:bg-gray-50">
                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">
                      <input type="checkbox" :value="member.id" v-model="selectedMemberIds">
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ member.id }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ member.name }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ getDepartmentName(member.department_id) }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      <span :class="getAssignmentBadgeClass(getAssignmentName(member.assignment_id))" class="inline-flex px-2 py-1 text-xs font-semibold rounded-full">
                        {{ getAssignmentName(member.assignment_id) }}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div class="overflow-x-auto mt-8">
              <div class="text-lg font-bold mb-2 border-b pb-1">選択中のチームメンバー</div>
              <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">名前</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">部署</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">担当</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <tr v-for="member in selectedMembers" :key="member.id" class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ member.id }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ member.name }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ getDepartmentName(member.department_id) }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      <span :class="getAssignmentBadgeClass(getAssignmentName(member.assignment_id))" class="inline-flex px-2 py-1 text-xs font-semibold rounded-full">
                        {{ getAssignmentName(member.assignment_id) }}
                      </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                      <button @click="removeSelectedMember(member.id)" class="text-red-600 hover:text-red-900">削除</button>
                    </td>
                  </tr>
                </tbody>
              </table>
              <div class="mt-4 flex justify-end">
                <button class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded" @click="registerMembers">メンバー登録</button>
              </div>
            </div>
    </div>
  </AppLayout>
</template>
<script setup>
import { router } from '@inertiajs/vue3';

function registerMembers() {
  // もともとCreate.vueから渡されたクエリやparamsを復元しつつ、選択メンバーを追加して戻る
  const params = { ...route().params, ...route().query };
  router.visit(route('coordinator.project_jobs.create'), {
    data: {
      ...params,
      selected_members: selectedMembers.value,
    },
    preserveState: true,
    preserveScroll: true,
  });
}
const selectedMemberIds = ref([]);
const allChecked = computed(() => filteredMembers.value.length > 0 && filteredMembers.value.every(m => selectedMemberIds.value.includes(m.id)));
const toggleAllMembers = () => {
  if (allChecked.value) {
    selectedMemberIds.value = [];
  } else {
    selectedMemberIds.value = filteredMembers.value.map(m => m.id);
  }
};
const selectedMembers = computed(() => {
  return props.members.filter(m => selectedMemberIds.value.includes(m.id));
});
function removeSelectedMember(id) {
  selectedMemberIds.value = selectedMemberIds.value.filter(mid => mid !== id);
}
import AppLayout from '@/layouts/AppLayout.vue';
import DialogModal from '@/Components/DialogModal.vue';
import { ref, computed } from 'vue';
const props = defineProps({
  members: Array,
  departments: Array,
  assignments: Array,
});
const showSearchModal = ref(false);
const selectedDepartmentId = ref('');
const selectedAssignmentId = ref('');
function openSearchModal() { showSearchModal.value = true; }
function closeSearchModal() { showSearchModal.value = false; }
function clearSearch() { selectedDepartmentId.value = ''; selectedAssignmentId.value = ''; showSearchModal.value = false; }
function doSearch() { showSearchModal.value = false; }
const filteredAssignments = computed(() => {
  if (!selectedDepartmentId.value) return [];
  return props.assignments.filter(a => String(a.department_id) === String(selectedDepartmentId.value));
});
const filteredMembers = computed(() => {
  let result = props.members;
  if (selectedDepartmentId.value) {
    result = result.filter(m => String(m.department_id) === String(selectedDepartmentId.value));
  }
  if (selectedAssignmentId.value) {
    result = result.filter(m => String(m.assignment_id) === String(selectedAssignmentId.value));
  }
  return result;
});
const getDepartmentName = (department_id) => {
  const department = props.departments.find(d => d.id === department_id);
  return department ? department.name : '';
};
const getAssignmentName = (assignment_id) => {
  const assignment = props.assignments.find(a => a.id === assignment_id);
  return assignment ? assignment.name : '';
};

// Admin/Users/Index.vueと同じバッジ色分け関数
const getAssignmentBadgeClass = (assignment) => {
  switch (assignment) {
    case '管理者':
      return 'bg-red-100 text-red-800';
    case 'リーダー':
      return 'bg-orange-100 text-orange-800';
    case '進行管理':
      return 'bg-green-100 text-blue-800';
    case 'ユーザー':
      return 'bg-blue-100 text-blue-800';
    default:
      return 'bg-gray-100 text-gray-800';
  }
};

</script>
