<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import DialogModal from '@/Components/DialogModal.vue';

// ユーザー削除処理
function deleteUser(id) {
	if (confirm('本当にこのアカウントを削除しますか？')) {
		router.delete(route('superadmin.users.destroy', id), {
			onSuccess: () => {
				router.visit(route('superadmin.users.index'));
			}
		});
	}
}

const showSearchModal = ref(false);
const page = usePage();
const myuser = computed(() => page.props.user);
const userCompanyId = computed(() => {
	return myuser.value && myuser.value.company_id ? String(myuser.value.company_id) : '';
});

const selectedDepartmentId = ref('');
const selectedAssignmentId = ref('');

const props = defineProps({
	users: {
		type: Array,
		required: true,
	},
	assignments: {
		type: Array,
		default: () => [],
	},
	departments: {
		type: Array,
		default: () => [],
	},
	companies: {
		type: Array,
		default: () => [],
	}
});

const filteredDepartments = computed(() => {
	return props.departments.filter(dep => String(dep.company_id) === userCompanyId.value);
});

const filteredAssignments = computed(() => {
	if (!selectedDepartmentId.value) return [];
	return props.assignments.filter(a => String(a.department_id) === String(selectedDepartmentId.value));
});

function onDepartmentChange() {
	selectedAssignmentId.value = '';
}

const filteredUsers = computed(() => {
	let result = props.users;
	if (selectedDepartmentId.value) {
		result = result.filter(u => String(u.department_id) === String(selectedDepartmentId.value));
	}
	if (selectedAssignmentId.value) {
		result = result.filter(u => String(u.assignment_id) === String(selectedAssignmentId.value));
	}
	return result;
});

function openSearchModal() { showSearchModal.value = true; }
function closeSearchModal() { showSearchModal.value = false; }
function resetSearch() { selectedDepartmentId.value = ''; selectedAssignmentId.value = ''; }
function clearSearch() { resetSearch(); showSearchModal.value = false; }
function doSearch() { showSearchModal.value = false; }

const getCompanyName = (company_id) => {
	if (!props.companies || !Array.isArray(props.companies)) return '';
	const company = props.companies.find(c => String(c.id) === String(company_id));
	return company ? (company.name || company.company_name || `会社 #${company.id}`) : '';
};

const getDepartmentName = (department_id) => {
	if (!props.departments) return '';
	const department = props.departments.find(d => d.id === department_id);
	return department ? department.name : '';
};

const getAssignmentName = (assignment_id) => {
	const assignment = props.assignments.find(r => r.id === assignment_id);
	return assignment ? assignment.name : '';
};

</script>

<template>
	<AppLayout title="ユーザー管理">
		<template #header>
			<div class="flex items-center justify-between">
				<h2 class="font-semibold text-xl text-gray-800 leading-tight">ユーザー管理</h2>
				<template v-if="myuser?.user_role === 'superadmin'">
					<Link :href="route('superadmin.users.create')" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">新規ユーザー登録</Link>
				</template>
			</div>
		</template>

		<div class="py-12">
			<div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
				<div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
					<div class="p-6">
						<div class="flex items-center justify-between mb-4">
							<h3 class="text-lg font-medium text-gray-900">登録アカウント一覧</h3>
							<div class="text-sm text-gray-500">総数: {{ users.length }}人</div>
							<div class="flex items-center space-x-2">
								<button @click="openSearchModal" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">絞り込み</button>
								<button @click="clearSearch" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded">クリア</button>
							</div>
						</div>

						<div class="overflow-x-auto">
							<DialogModal :show="showSearchModal" @close="closeSearchModal">
								<template #title>ユーザー検索</template>
								<template #content>
									<div class="mb-4">
										<label class="block mb-1 font-semibold">会社</label>
										<select class="w-full border rounded px-3 py-2" disabled>
											<option value="">-- 会社を選択 --</option>
											<option v-for="company in props.companies" :key="company.id" :value="String(company.id)" :selected="String(company.id) === userCompanyId">
												{{ company.name || company.company_name || ('会社 #' + company.id) }}
											</option>
										</select>
									</div>
								</template>
								<template #footer>
									<button class="bg-gray-300 px-4 py-2 rounded mr-2" @click="closeSearchModal">閉じる</button>
									<button class="bg-blue-600 text-white px-4 py-2 rounded" @click="doSearch">絞り込み</button>
								</template>
							</DialogModal>

							<table class="min-w-full divide-y divide-gray-200">
								<thead class="bg-gray-50">
									<tr>
										<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
										<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">名前</th>
										<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">会社</th>
										<th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
									</tr>
								</thead>
								<tbody class="bg-white divide-y divide-gray-200">
									<tr v-for="user in filteredUsers" :key="user.id" class="hover:bg-gray-50">
										<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ user.id }}</td>
										<td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ user.name }}</td>
										<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ getCompanyName(user.company_id) }}</td>
										<td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
											<div class="flex justify-end space-x-2">
												<Link :href="route('superadmin.users.show', user.id)" class="text-blue-600 hover:text-blue-900">詳細</Link>
												<Link :href="route('superadmin.users.edit', user.id)" class="text-yellow-600 hover:text-yellow-900">編集</Link>
												<button @click="deleteUser(user.id)" class="text-red-600 hover:text-red-900">削除</button>
											</div>
										</td>
									</tr>
								</tbody>
							</table>
						</div>
					</div>
				</div>
			</div>
		</div>
	</AppLayout>
</template>

