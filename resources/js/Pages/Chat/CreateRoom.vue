<script setup>
import { ref, computed, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';

// props定義
const props = defineProps({
  members: Array,
  departments: Array,
  assignments: Array,
  user: Object,
});
const currentUser = props.user;
const roomType = ref('private');
const name = ref('');
const selectedDepartmentId = ref('');
const selectedAssignmentId = ref('');
const selectedMemberIds = ref(currentUser ? [currentUser.id] : []);
const errors = ref({});

watch(roomType, (val) => {
  name.value = '';
  selectedMemberIds.value = currentUser ? [currentUser.id] : [];
});

// パーソナル時、相手選択で自動でnameにセット
watch([roomType, selectedMemberIds], ([type, ids]) => {
  if (type === 'private') {
    // 自分以外のID
    const otherId = ids.find(id => id !== currentUser.id);
    const other = props.members.find(m => m.id === otherId);
    name.value = other ? other.name : '';
  }
});

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
  // 自分を先頭に、その後他のメンバー
  const self = result.find(m => m.id === currentUser.id);
  const others = result.filter(m => m.id !== currentUser.id);
  return self ? [self, ...others] : others;
});
const getDepartmentName = (department_id) => {
  const department = props.departments.find(d => d.id === department_id);
  return department ? department.name : '';
};
const getAssignmentName = (assignment_id) => {
  const assignment = props.assignments.find(a => a.id === assignment_id);
  return assignment ? assignment.name : '';
};
const getAssignmentBadgeClass = (assignment) => {
  switch (assignment) {
    case '管理者': return 'bg-red-100 text-red-800';
    case 'リーダー': return 'bg-orange-100 text-orange-800';
    case '進行管理': return 'bg-green-100 text-blue-800';
    case 'ユーザー': return 'bg-blue-100 text-blue-800';
    default: return 'bg-gray-100 text-gray-800';
  }
};
function toggleMember(id) {
  const idx = selectedMemberIds.value.indexOf(id);
  if (idx === -1) {
    selectedMemberIds.value.push(id);
  } else {
    selectedMemberIds.value.splice(idx, 1);
  }
}
const allChecked = computed(() => filteredMembers.value.length > 0 && filteredMembers.value.every(m => selectedMemberIds.value.includes(m.id)));
const toggleAllMembers = () => {
  if (allChecked.value) {
    selectedMemberIds.value = currentUser ? [currentUser.id] : [];
  } else {
    selectedMemberIds.value = [currentUser.id, ...filteredMembers.value.filter(m => m.id !== currentUser.id).map(m => m.id)];
  }
};
const selectedMembers = computed(() => {
  // 自分を先頭、その後に他の選択メンバー
  const self = props.members.find(m => m.id === currentUser.id);
  const others = props.members.filter(m => selectedMemberIds.value.includes(m.id) && m.id !== currentUser.id);
  return self ? [self, ...others] : others;
});
function removeSelectedMember(id) {
  if (id === currentUser.id) return;
  selectedMemberIds.value = selectedMemberIds.value.filter(mid => mid !== id);
}
function validate() {
  errors.value = {};
  if (roomType.value === 'private') {
    if (selectedMemberIds.value.length < 2) errors.value.members = '相手を選択してください';
    if (!name.value.trim()) errors.value.name = '相手の名前を入力してください';
  } else {
    if (!name.value.trim()) errors.value.name = 'グループ名を入力してください';
    if (selectedMemberIds.value.length < 3) errors.value.members = '2人以上選択してください（自分含む）';
  }
  return Object.keys(errors.value).length === 0;
}
function createRoom() {
  if (!validate()) return;
  router.post('/chat/rooms', {
    name: name.value,
    type: roomType.value,
    user_ids: selectedMemberIds.value,
  }, {
  onSuccess: () => router.visit('/chat/rooms'),
    onError: (e) => { errors.value = e; },
  });
}
</script>
<template>
  <AppLayout title="チャットルーム作成">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">チャットルーム作成</h2>
    </template>
    <div class="py-6">
      <div class="max-w-3xl mx-auto bg-white shadow rounded p-6">
        <div class="mb-4">
          <label class="font-bold mr-4">ルーム種別:</label>
          <label class="mr-4"><input type="radio" value="private" v-model="roomType"> パーソナル</label>
          <label><input type="radio" value="group" v-model="roomType"> グループ</label>
        </div>
        <div class="mb-4">
          <label class="block font-bold mb-1">{{ roomType === 'private' ? '相手の名前' : 'グループ名' }}</label>
          <input v-model="name" class="border rounded px-3 py-2 w-full" :placeholder="roomType === 'private' ? '相手の名前' : 'グループ名'" :readonly="roomType === 'private'" :style="roomType === 'private' ? 'background:#f3f4f6;' : ''">
          <div v-if="errors.name" class="text-red-600 text-sm mt-1">{{ errors.name }}</div>
        </div>
        <div class="mb-4">
          <label class="block font-bold mb-1">メンバー選択</label>
          <div class="flex gap-4 mb-2">
            <select v-model="selectedDepartmentId" class="border rounded px-2 py-1">
              <option value="">-- 部署を選択 --</option>
              <option v-for="department in props.departments" :key="department.id" :value="String(department.id)">{{ department.name }}</option>
            </select>
            <select v-model="selectedAssignmentId" class="border rounded px-2 py-1" :disabled="!selectedDepartmentId">
              <option value="">-- 担当を選択 --</option>
              <option v-for="assignment in filteredAssignments" :key="assignment.id" :value="String(assignment.id)">{{ assignment.name }}</option>
            </select>
          </div>
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
              <tr v-for="member in filteredMembers" :key="member.id" class="hover:bg-gray-50"
                @click="toggleMember(member.id)"
                :class="{'bg-blue-50': selectedMemberIds.includes(member.id), 'cursor-pointer': true}">
                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500" @click.stop>
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
          <div v-if="errors.members" class="text-red-600 text-sm mt-1">{{ errors.members }}</div>
        </div>
        <div class="overflow-x-auto mt-8">
          <div class="text-lg font-bold mb-2 border-b pb-1">選択中のメンバー</div>
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
              <tr v-for="member in selectedMembers" :key="member.id" :class="member.id === currentUser.id ? 'bg-gray-200' : 'hover:bg-gray-50'">
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ member.id }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ member.name }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ getDepartmentName(member.department_id) }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  <span :class="getAssignmentBadgeClass(getAssignmentName(member.assignment_id))" class="inline-flex px-2 py-1 text-xs font-semibold rounded-full">
                    {{ getAssignmentName(member.assignment_id) }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                  <template v-if="member.id === currentUser.id">
                    <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded">ユーザー</span>
                  </template>
                  <template v-else>
                    <button @click="removeSelectedMember(member.id)" class="text-red-600 hover:text-red-900">削除</button>
                  </template>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="flex justify-end mt-6">
          <button class="bg-blue-600 text-white px-6 py-2 rounded" @click="createRoom">作成する</button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
