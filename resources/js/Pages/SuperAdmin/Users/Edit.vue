<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import SuperAdminNavigationTabs from '@/Components/Tabs/SuperAdminNavigationTabs.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const props = defineProps({
    user:           { type: Object, required: true },
    companies:      { type: Array,  default: () => [] },
    positionTitles: { type: Array,  default: () => [] },
});

const EMPLOYMENT_TYPE_OPTIONS = [
    { value: 'regular',   label: '正社員' },
    { value: 'contract',  label: '契約社員' },
    { value: 'dispatch',  label: '派遣社員' },
    { value: 'outsource', label: '業務委託' },
];

const form = useForm({
    name:                  props.user.name            ?? '',
    email:                 props.user.email           ?? '',
    password:              '',
    password_confirmation: '',
    company_id:            props.user.company_id    ? String(props.user.company_id) : '',
    department_id:         props.user.department_id ?? '',
    assignment_id:         props.user.assignment_id ?? '',
    position_title_id:     props.user.position_title_id ?? '',
    user_role:             props.user.user_role     ?? 'user',
    employment_type:       props.user.employment_type ?? 'regular',
});

const page = usePage();
const currentUser = computed(() => page.props.auth?.user ?? null);

const userRoleOptions = [
    { value: 'admin',             label: '管理者',             description: '全ての機能にアクセス可能' },
    { value: 'leader',            label: 'リーダー',           description: 'コンテンツ管理とユーザー機能にアクセス可能' },
    { value: 'coordinator',       label: '進行管理',           description: 'タスク管理とユーザー機能にアクセス可能' },
    { value: 'proof_coordinator', label: '校正コーディネーター', description: '校正依頼の受理・割り振り管理が可能' },
    { value: 'clerk',             label: 'クラーク（経理・事務）', description: '経理・事務機能にアクセス可能' },
    { value: 'user',              label: 'ユーザー',           description: '基本機能のみアクセス可能' },
];

const selectedCompanyDepartments = computed(() => {
    if (!form.company_id) return [];
    const company = props.companies.find(c => String(c.id) === String(form.company_id));
    return company ? company.departments : [];
});

const availableAssignments = computed(() => {
    if (!form.department_id) return [];
    const dept = selectedCompanyDepartments.value.find(d => d.id == form.department_id);
    if (!dept || !dept.assignments) return [];
    return dept.assignments.filter(a => a.active);
});

watch(() => form.company_id,    () => { form.department_id = ''; form.assignment_id = ''; });
watch(() => form.department_id, () => { form.assignment_id = ''; });

const submit = () => {
    form.put(route('superadmin.users.update', { user: props.user.id }));
};
</script>

<template>
    <AppLayout title="ユーザー編集">
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('superadmin.users.index')"
                    class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 whitespace-nowrap hover:bg-gray-300">
                    ← ユーザー一覧に戻る
                </Link>
                <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">ユーザー編集</h2>
            </div>
        </template>

        <template #tabs>
            <SuperAdminNavigationTabs active="all_users" />
        </template>

        <div class="mx-auto max-w-2xl rounded bg-white px-4 py-6 sm:p-6 shadow">
            <form @submit.prevent="submit">
                <!-- 名前 -->
                <div>
                    <InputLabel for="name" value="名前" />
                    <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" required autofocus />
                    <InputError class="mt-2" :message="form.errors.name" />
                </div>

                <!-- メールアドレス -->
                <div class="mt-4">
                    <InputLabel for="email" value="メールアドレス" />
                    <TextInput id="email" v-model="form.email" type="email" class="mt-1 block w-full" required />
                    <InputError class="mt-2" :message="form.errors.email" />
                </div>

                <!-- パスワード（任意） -->
                <div class="mt-4">
                    <InputLabel for="password" value="パスワード（変更する場合のみ入力）" />
                    <TextInput id="password" v-model="form.password" type="password" class="mt-1 block w-full"
                        placeholder="変更しない場合は空白のまま" />
                    <InputError class="mt-2" :message="form.errors.password" />
                </div>
                <div class="mt-4">
                    <InputLabel for="password_confirmation" value="パスワード確認" />
                    <TextInput id="password_confirmation" v-model="form.password_confirmation" type="password" class="mt-1 block w-full"
                        placeholder="変更しない場合は空白のまま" />
                    <InputError class="mt-2" :message="form.errors.password_confirmation" />
                </div>

                <!-- 会社 -->
                <div class="mt-4">
                    <InputLabel for="company" value="会社" />
                    <select id="company" v-model="form.company_id"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                        <option value="">-- 会社を選択してください --</option>
                        <option v-for="company in companies" :key="company.id" :value="String(company.id)">{{ company.name }}</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.company_id" />
                </div>

                <!-- 部署 -->
                <div class="mt-4">
                    <InputLabel for="department" value="部署" />
                    <select id="department" v-model="form.department_id" :disabled="!form.company_id"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                        <option value="">-- 部署を選択してください --</option>
                        <option v-for="dept in selectedCompanyDepartments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.department_id" />
                </div>

                <!-- 担当 -->
                <div class="mt-4">
                    <InputLabel for="assignment_id" value="担当" />
                    <select id="assignment_id" v-model="form.assignment_id" :disabled="!form.department_id"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                        <option value="">-- 担当を選択してください --</option>
                        <option v-for="a in availableAssignments" :key="a.id" :value="a.id">{{ a.name }}</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.assignment_id" />
                </div>

                <!-- 権限レベル -->
                <div class="mt-4">
                    <InputLabel for="user_role" value="権限レベル" />
                    <select id="user_role" v-model="form.user_role"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                        <option v-for="option in userRoleOptions" :key="option.value" :value="option.value">
                            {{ option.label }} - {{ option.description }}
                        </option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.user_role" />
                </div>

                <!-- 雇用形態 -->
                <div class="mt-4">
                    <InputLabel for="employment_type" value="雇用形態" />
                    <select id="employment_type" v-model="form.employment_type"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option v-for="opt in EMPLOYMENT_TYPE_OPTIONS" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.employment_type" />
                </div>

                <!-- 役職称号 -->
                <div v-if="positionTitles.length > 0" class="mt-4">
                    <InputLabel for="position_title_id" value="役職称号" />
                    <select id="position_title_id" v-model="form.position_title_id"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">-- なし --</option>
                        <option v-for="title in positionTitles" :key="title.id" :value="title.id">{{ title.name }}</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.position_title_id" />
                </div>

                <div class="mt-6 flex items-center justify-end space-x-3">
                    <Link :href="route('superadmin.users.index')">
                        <SecondaryButton type="button">キャンセル</SecondaryButton>
                    </Link>
                    <PrimaryButton class="bg-yellow-500 hover:bg-yellow-600"
                        :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                        更新する
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
