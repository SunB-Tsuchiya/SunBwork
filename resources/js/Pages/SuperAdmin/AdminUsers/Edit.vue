<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const props = defineProps({
    user:         Object,
    companies:    { type: Array, default: () => [] },
    adminTitles:  { type: Array, default: () => [] },
    leaderTitles: { type: Array, default: () => [] },
});

const form = useForm({
    name:              props.user.name,
    email:             props.user.email,
    company_id:        props.user.company_id ? String(props.user.company_id) : '',
    department_id:     props.user.department_id ?? '',
    assignment_id:     props.user.assignment_id ?? '',
    position_title_id: props.user.position_title_id ?? '',
    user_role:         props.user.user_role,
});

const availablePositionTitles = computed(() => {
    if (form.user_role === 'admin') return props.adminTitles;
    if (form.user_role === 'leader') return props.leaderTitles;
    return [];
});

watch(() => form.user_role, () => { form.position_title_id = ''; });

const selectedCompanyDepartments = computed(() => {
    if (!form.company_id) return [];
    const company = props.companies.find((c) => String(c.id) === String(form.company_id));
    return company ? company.departments : [];
});

const availableAssignments = computed(() => {
    if (!form.department_id) return [];
    const department = selectedCompanyDepartments.value.find((d) => d.id == form.department_id);
    if (!department || !department.assignments) return [];
    return department.assignments.filter((a) => a.active);
});

const onCompanyChange = () => {
    form.department_id = '';
    form.assignment_id = '';
};
const onDepartmentChange = () => {
    form.assignment_id = '';
};

watch(
    () => form.department_id,
    (val) => {
        if (val) form.department_id = parseInt(val);
    },
    { immediate: true },
);

const submit = () => {
    form.put(route('superadmin.adminusers.update', { adminuser: props.user.id }), {
        onError: (errors) => {
            const messages = Object.entries(errors)
                .map(([field, msg]) => `[${field}] ${msg}`)
                .join('\n');
            alert('更新できませんでした:\n' + messages);
        },
    });
};
</script>

<template>
    <AppLayout title="Adminユーザー編集">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Adminユーザー編集</h2>
                <Link :href="route('superadmin.adminusers.index')" class="text-gray-600 hover:text-gray-900">
                    ← Adminユーザー一覧に戻る
                </Link>
            </div>
        </template>

        <div class="rounded bg-white p-6 shadow">
            <form @submit.prevent="submit">
                <div>
                    <InputLabel for="name" value="名前" />
                    <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" required autofocus />
                    <InputError class="mt-2" :message="form.errors.name" />
                </div>

                <div class="mt-4">
                    <InputLabel for="email" value="メールアドレス" />
                    <TextInput id="email" v-model="form.email" type="email" class="mt-1 block w-full" required />
                    <InputError class="mt-2" :message="form.errors.email" />
                </div>

                <div class="mt-4">
                    <InputLabel for="company" value="会社" />
                    <select
                        id="company"
                        v-model="form.company_id"
                        @change="onCompanyChange"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        required
                    >
                        <option value="">-- 会社を選択してください --</option>
                        <option v-for="company in companies" :key="company.id" :value="String(company.id)">
                            {{ company.name }}
                        </option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.company_id" />
                </div>

                <div class="mt-4">
                    <InputLabel for="department" value="部署" />
                    <select
                        id="department"
                        v-model="form.department_id"
                        @change="onDepartmentChange"
                        :disabled="!form.company_id"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option value="">-- なし（未設定） --</option>
                        <option v-for="department in selectedCompanyDepartments" :key="department.id" :value="department.id">
                            {{ department.name }}
                        </option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.department_id" />
                </div>

                <div class="mt-4">
                    <InputLabel for="assignment_id" value="担当" />
                    <select
                        id="assignment_id"
                        v-model="form.assignment_id"
                        :disabled="!form.department_id"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option value="">-- なし（未設定） --</option>
                        <option v-for="assignment in availableAssignments" :key="assignment.id" :value="assignment.id">
                            {{ assignment.name }}
                        </option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.assignment_id" />
                </div>

                <div class="mt-4">
                    <InputLabel for="user_role" value="権限レベル" />
                    <select
                        id="user_role"
                        v-model="form.user_role"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        required
                    >
                        <option value="admin">管理者 (admin)</option>
                        <option value="leader">リーダー (leader)</option>
                        <option value="coordinator">コーディネーター (coordinator)</option>
                        <option value="user">一般ユーザー (user)</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.user_role" />
                </div>

                <div v-if="availablePositionTitles.length > 0" class="mt-4">
                    <InputLabel for="position_title_id" value="役職称号" />
                    <select
                        id="position_title_id"
                        v-model="form.position_title_id"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option value="">-- なし --</option>
                        <option v-for="title in availablePositionTitles" :key="title.id" :value="title.id">
                            {{ title.name }}
                        </option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.position_title_id" />
                </div>

                <div class="mt-6 flex items-center justify-end space-x-3">
                    <Link :href="route('superadmin.adminusers.index')">
                        <SecondaryButton type="button">キャンセル</SecondaryButton>
                    </Link>
                    <PrimaryButton
                        class="bg-red-600 hover:bg-red-700"
                        :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing"
                    >
                        更新する
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
