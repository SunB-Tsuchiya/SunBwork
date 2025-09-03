<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const page = usePage();
const props = page.props;

const companies = ref(props.companies || []);
const departments = ref(props.departments || []);
const users = ref(props.users || []); // all users for company (used to derive department members)
const leaders = ref(props.leaders || []); // limited set for leader select

const form = useForm({ company_id: '', department_id: '', name: '', description: '', leader_id: '', member_ids: [] });

const availableDepartments = computed(() => {
    if (!form.company_id) return [];
    return departments.value.filter((d) => d.company_id === Number(form.company_id));
});

const departmentMembers = computed(() => {
    // show only users who belong to the selected department and exclude superadmin/admin/leader
    if (!form.department_id) return [];
    return users.value.filter((u) => {
        return Number(u.department_id) === Number(form.department_id) && !['superadmin', 'admin', 'leader'].includes((u.user_role || '').toString());
    });
});

onMounted(() => {
    // preselect company if only one available
    if (companies.value.length === 1) form.company_id = companies.value[0].id;
});

const submit = () => {
    form.post(route('admin.units.store'));
};
</script>

<template>
    <AppLayout title="ユニットチーム作成">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">新規ユニットチーム作成</h2>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
                <div class="bg-white p-6 shadow sm:rounded-lg">
                    <form @submit.prevent="submit" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">会社</label>
                            <select v-model="form.company_id" class="input mt-1 w-full">
                                <option value="">-- 選択 --</option>
                                <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">部署</label>
                            <select v-model="form.department_id" class="input mt-1 w-full">
                                <option value="">-- 選択 --</option>
                                <option v-for="d in availableDepartments" :key="d.id" :value="d.id">{{ d.name }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">ユニット名</label>
                            <input v-model="form.name" class="input mt-1 w-full" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">説明</label>
                            <textarea v-model="form.description" class="textarea mt-1 w-full"></textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">リーダー</label>
                            <select v-model="form.leader_id" class="input mt-1 w-full">
                                <option value="">-- 選択 --</option>
                                <option v-for="u in leaders" :key="u.id" :value="u.id">{{ u.name }} ({{ u.user_role }})</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">メンバー（複数選択可）</label>
                            <div class="mt-2 grid gap-2">
                                <div v-if="!form.department_id" class="text-sm text-gray-500">部署を選択してください</div>
                                <label v-for="u in departmentMembers" :key="u.id" class="inline-flex items-center space-x-2">
                                    <input type="checkbox" :value="u.id" v-model="form.member_ids" class="form-checkbox" />
                                    <span class="text-sm">{{ u.name }} ({{ u.user_role }})</span>
                                </label>
                                <div v-if="departmentMembers.length === 0 && form.department_id" class="text-sm text-gray-500">
                                    選択された部署に該当するメンバーはありません
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-white">作成</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
