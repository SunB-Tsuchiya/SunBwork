<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
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

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    company_id: '',
    department_id: '',
    role: '',
    terms: false,
});

// 選択した会社の部署一覧を取得
const selectedCompanyDepartments = computed(() => {
    if (!form.company_id) return [];
    const company = props.companies.find(c => c.id == form.company_id);
    return company ? company.departments : [];
});

// 選択した部署に応じた役職一覧を取得
const availableRoles = computed(() => {
    if (!form.department_id) return [];
    
    const department = selectedCompanyDepartments.value.find(d => d.id == form.department_id);
    if (!department) return [];
    
    // 情報出版の場合
    if (department.name === '情報出版') {
        return [
            '管理者',
            '進行管理', 
            'オペレーター',
            '校正',
            '営業',
            'そのほか'
        ];
    } else {
        // 情報出版以外（出力、オンデマンド）
        return [
            '管理者',
            '進行管理',
            'オペレーター', 
            'そのほか'
        ];
    }
});

// 会社が変更された時に部署をリセット
const onCompanyChange = () => {
    form.department_id = '';
    form.role = '';
};

// 部署が変更された時に役職をリセット
const onDepartmentChange = () => {
    form.role = '';
};

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
                <InputLabel for="role" value="役職・担当" />
                <select
                    id="role"
                    v-model="form.role"
                    :disabled="!form.department_id"
                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                    required
                >
                    <option value="">-- 役職・担当を選択してください --</option>
                    <option v-for="role in availableRoles" :key="role" :value="role">
                        {{ role }}
                    </option>
                </select>
                <InputError class="mt-2" :message="form.errors.role" />
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
