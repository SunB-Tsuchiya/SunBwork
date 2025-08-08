<script setup>
// 【重要】Laravel Fortify + Inertia.js + Vue.js による登録画面
// データ提供: app/Providers/FortifyServiceProvider.php
// 登録処理: app/Actions/Fortify/CreateNewUser.php
// 
// 機能:    
// - 会社・部署・役職の連動ドロップダウン
// - 権限レベル選択 (admin, leader, user)
// - リアクティブフォーム処理

import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import AuthenticationCard from '@/Components/AuthenticationCard.vue';
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    companies: Array,
});

// デバッグ用：取得したデータを確認
console.log('Companies data:', props.companies);
console.log('First company departments:', props.companies[0] ? props.companies[0].departments : 'No companies');
if (props.companies[0] && props.companies[0].departments[0]) {
    const firstDept = props.companies[0].departments[0];
    console.log('First department:', firstDept);
    console.log('First department keys:', Object.keys(firstDept));
    console.log('First department roles property:', firstDept.roles);
}

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    company_id: '',
    department_id: '',
    role_id: '', // role_idに変更
    user_role: 'user', // デフォルトは一般ユーザー
    terms: false,
});

// 選択した会社の部署一覧を取得
const selectedCompanyDepartments = computed(() => {
    if (!form.company_id) return [];
    const company = props.companies.find(c => c.id == form.company_id);
    console.log('Selected company:', company); // デバッグ用
    console.log('Company departments:', company ? company.departments : 'No company'); // デバッグ用
    return company ? company.departments : [];
});

// 選択した部署に応じた役職一覧を取得（rolesテーブルから）
const availableRoles = computed(() => {
    if (!form.department_id) return [];
    
    // 数値として比較（==で型変換も含める）
    const department = selectedCompanyDepartments.value.find(d => d.id == form.department_id);
    console.log('Selected department ID:', form.department_id); // デバッグ用
    console.log('Available departments:', selectedCompanyDepartments.value); // デバッグ用
    console.log('Found department:', department); // デバッグ用
    console.log('Department keys:', department ? Object.keys(department) : 'No department'); // デバッグ用
    console.log('Department roles property:', department ? department.roles : 'No department'); // デバッグ用
    
    if (!department) {
        console.log('Department not found'); // デバッグ用
        return [];
    }
    
    if (!department.roles) {
        console.log('No roles property found for department'); // デバッグ用
        return [];
    }
    
    // rolesテーブルのデータを使用
    const roles = department.roles.filter(role => role.active);
    console.log('Available roles:', roles); // デバッグ用
    return roles;
});

// 権限レベルの選択肢
const userRoleOptions = [
    { value: 'admin', label: '管理者', description: '全ての機能にアクセス可能' },
    { value: 'leader', label: 'リーダー', description: 'コンテンツ管理とユーザー機能にアクセス可能' },
    { value: 'user', label: 'ユーザー', description: '基本機能のみアクセス可能' }
];

// 会社が変更された時に部署と役職をリセット
const onCompanyChange = () => {
    form.department_id = '';
    form.role_id = '';
};

// 部署が変更された時に役職をリセット
const onDepartmentChange = () => {
    form.role_id = '';
};

// フォームの値が変更されたときの監視
watch(() => form.department_id, (newDepartmentId) => {
    console.log('Department changed to:', newDepartmentId, typeof newDepartmentId);
    if (newDepartmentId) {
        // 文字列の場合は数値に変換
        form.department_id = parseInt(newDepartmentId);
    }
}, { immediate: true });

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Register" />

    <AuthenticationCard>
        <template #logo>
            <AuthenticationCardLogo />
        </template>

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="name" value="Name" />
                <TextInput
                    id="name"
                    v-model="form.name"
                    type="text"
                    class="mt-1 block w-full"
                    required
                    autofocus
                    autocomplete="name"
                />
                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div class="mt-4">
                <InputLabel for="email" value="Email" />
                <TextInput
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="mt-1 block w-full"
                    required
                    autocomplete="username"
                />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="mt-4">
                <InputLabel for="password" value="Password" />
                <TextInput
                    id="password"
                    v-model="form.password"
                    type="password"
                    class="mt-1 block w-full"
                    required
                    autocomplete="new-password"
                />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="mt-4">
                <InputLabel for="password_confirmation" value="Confirm Password" />
                <TextInput
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    class="mt-1 block w-full"
                    required
                    autocomplete="new-password"
                />
                <InputError class="mt-2" :message="form.errors.password_confirmation" />
            </div>

            <div class="mt-4">
                <InputLabel for="company" value="会社" />
                <select
                    id="company"
                    v-model="form.company_id"
                    @change="onCompanyChange"
                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                    required
                >
                    <option value="">-- 会社を選択してください --</option>
                    <option v-for="company in companies" :key="company.id" :value="company.id">
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
                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                    required
                >
                    <option value="">-- 部署を選択してください --</option>
                    <option v-for="department in selectedCompanyDepartments" :key="department.id" :value="department.id">
                        {{ department.name }}
                    </option>
                </select>
                <InputError class="mt-2" :message="form.errors.department_id" />
            </div>

            <div class="mt-4">
                <InputLabel for="role_id" value="担当" />
                <select
                    id="role_id"
                    v-model="form.role_id"
                    :disabled="!form.department_id"
                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                    required
                >
                    <option value="">-- 担当を選択してください --</option>
                    <option v-for="role in availableRoles" :key="role.id" :value="role.id">
                        {{ role.name }}
                    </option>
                </select>
                <InputError class="mt-2" :message="form.errors.role_id" />
            </div>

            <div class="mt-4">
                <InputLabel for="user_role" value="権限レベル" />
                <select
                    id="user_role"
                    v-model="form.user_role"
                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                    required
                >
                    <option value="" disabled>権限レベルを選択してください</option>
                    <option
                        v-for="option in userRoleOptions"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }} - {{ option.description }}
                    </option>
                </select>
                <InputError class="mt-2" :message="form.errors.user_role" />
                
                <!-- 権限レベルの説明 -->
                <div class="mt-3 grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div class="bg-red-50 p-3 rounded-lg border border-red-200">
                        <h4 class="font-medium text-red-900">管理者</h4>
                        <p class="text-sm text-red-700 mt-1">全ての機能にアクセス可能。ユーザー管理、システム設定など。</p>
                    </div>
                    <div class="bg-orange-50 p-3 rounded-lg border border-orange-200">
                        <h4 class="font-medium text-orange-900">リーダー</h4>
                        <p class="text-sm text-orange-700 mt-1">コンテンツ管理とユーザー機能にアクセス可能。</p>
                    </div>
                    <div class="bg-blue-50 p-3 rounded-lg border border-blue-200">
                        <h4 class="font-medium text-blue-900">ユーザー</h4>
                        <p class="text-sm text-blue-700 mt-1">基本機能のみアクセス可能。</p>
                    </div>
                </div>
            </div>

            <div v-if="$page.props.jetstream.hasTermsAndPrivacyPolicyFeature" class="mt-4">
                <InputLabel for="terms">
                    <div class="flex items-center">
                        <Checkbox id="terms" v-model:checked="form.terms" name="terms" required />

                        <div class="ms-2">
                            I agree to the <a target="_blank" :href="route('terms.show')" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">Terms of Service</a> and <a target="_blank" :href="route('policy.show')" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">Privacy Policy</a>
                        </div>
                    </div>
                    <InputError class="mt-2" :message="form.errors.terms" />
                </InputLabel>
            </div>

            <div class="flex items-center justify-end mt-4">
                <Link :href="route('login')" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Already registered?
                </Link>

                <PrimaryButton class="ms-4" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    Register
                </PrimaryButton>
            </div>
        </form>
    </AuthenticationCard>
</template>
