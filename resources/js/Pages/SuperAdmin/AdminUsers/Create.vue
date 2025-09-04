<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import SuperAdminNavigationTabs from '@/Components/Tabs/SuperAdminNavigationTabs.vue';
import TextInput from '@/Components/TextInput.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

// props: companies（親から渡す）
const props = defineProps({
    companies: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    company_id: '',
    department_id: '',
    assignment_id: '', // assignment_idで統一
    user_role: 'admin',
    terms: false,
});

// 日本語バリデーションメッセージ
const validationMessages = {
    name: {
        required: '名前は必須です。',
        max: '名前は255文字以内で入力してください。',
    },
    email: {
        required: 'メールアドレスは必須です。',
        email: 'メールアドレスの形式が正しくありません。',
        max: 'メールアドレスは255文字以内で入力してください。',
    },
    password: {
        required: 'パスワードは必須です。',
        min: 'パスワードは8文字以上で入力してください。',
    },
    password_confirmation: {
        required: 'パスワード確認は必須です。',
        confirmed: 'パスワードが一致しません。',
    },
    company_id: {
        required: '会社を選択してください。',
    },
    department_id: {
        required: '部署を選択してください。',
    },
    assignment_id: {
        required: '担当を選択してください。',
    },
    user_role: {
        required: '権限レベルを選択してください。',
    },
};

const errors = ref({});

function validateField(field) {
    switch (field) {
        case 'name':
            if (!form.name) errors.value.name = validationMessages.name.required;
            else if (form.name.length > 255) errors.value.name = validationMessages.name.max;
            else delete errors.value.name;
            break;
        case 'email':
            if (!form.email) errors.value.email = validationMessages.email.required;
            else if (!/^\S+@\S+\.\S+$/.test(form.email)) errors.value.email = validationMessages.email.email;
            else if (form.email.length > 255) errors.value.email = validationMessages.email.max;
            else delete errors.value.email;
            break;
        case 'password':
            if (!form.password) errors.value.password = validationMessages.password.required;
            else if (form.password.length < 8) errors.value.password = validationMessages.password.min;
            else delete errors.value.password;
            break;
        case 'password_confirmation':
            if (!form.password_confirmation) errors.value.password_confirmation = validationMessages.password_confirmation.required;
            else if (form.password !== form.password_confirmation)
                errors.value.password_confirmation = validationMessages.password_confirmation.confirmed;
            else delete errors.value.password_confirmation;
            break;
        case 'company_id':
            if (!form.company_id) errors.value.company_id = validationMessages.company_id.required;
            else delete errors.value.company_id;
            break;
        case 'department_id':
            if (!form.department_id) errors.value.department_id = validationMessages.department_id.required;
            else delete errors.value.department_id;
            break;
        case 'assignment_id':
            if (!form.assignment_id) errors.value.assignment_id = validationMessages.assignment_id.required;
            else delete errors.value.assignment_id;
            break;
        case 'user_role':
            if (!form.user_role) errors.value.user_role = validationMessages.user_role.required;
            else delete errors.value.user_role;
            break;
    }
}

// 各フィールドをwatchしてリアルタイムバリデーション
watch(
    () => form.name,
    () => validateField('name'),
);
watch(
    () => form.email,
    () => validateField('email'),
);
watch(
    () => form.password,
    () => validateField('password'),
);
watch(
    () => form.password_confirmation,
    () => validateField('password_confirmation'),
);
watch(
    () => form.company_id,
    () => validateField('company_id'),
);
watch(
    () => form.department_id,
    () => validateField('department_id'),
);
watch(
    () => form.assignment_id,
    () => validateField('assignment_id'),
);
watch(
    () => form.user_role,
    () => validateField('user_role'),
);

// 送信前に全項目バリデーション
function validateAll() {
    ['name', 'email', 'password', 'password_confirmation', 'company_id', 'department_id', 'assignment_id', 'user_role'].forEach(validateField);
    return Object.keys(errors.value).length === 0;
}

const selectedCompanyDepartments = computed(() => {
    if (!form.company_id) return [];
    // idを文字列で比較
    const company = props.companies.find((c) => String(c.id) === String(form.company_id));
    return company ? company.departments : [];
});

const availableAssignments = computed(() => {
    if (!form.department_id) return [];
    const department = selectedCompanyDepartments.value.find((d) => d.id == form.department_id);
    if (!department || !department.assignments) return [];
    return department.assignments.filter((assignment) => assignment.active);
});

// この画面は superadmin が admin を作るための画面なので選べる権限は admin のみ
const userAssignmentOptions = [{ value: 'admin', label: '管理者', description: '全ての機能にアクセス可能' }];

const onCompanyChange = () => {
    form.department_id = '';
    form.assignment_id = '';
};
const onDepartmentChange = () => {
    form.assignment_id = '';
};

watch(
    () => form.department_id,
    (newDepartmentId) => {
        if (newDepartmentId) {
            form.department_id = parseInt(newDepartmentId);
        }
    },
    { immediate: true },
);

const submit = () => {
    // client-side guard: inform non-superadmin users that only superadmin may create admin accounts
    const page = usePage();
    const currentUser = page.props.user || null;
    if (form.user_role === 'admin' && !(currentUser && currentUser.user_role === 'superadmin')) {
        alert('管理者アカウントの作成は superadmin のみ許可されています。');
        return;
    }
    if (!validateAll()) {
        // 日本語エラーをalertとconsoleに表示
        const messages = Object.values(errors.value).join('\n');
        alert('登録できませんでした:\n' + messages);
        console.error('登録バリデーションエラー:', messages);
        return;
    }
    form.post(route('superadmin.adminusers.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
        onError: (errors) => {
            // サーバー側エラー詳細をすべて表示
            const messages = Object.entries(errors)
                .map(([field, msg]) => {
                    return `[${field}] ${msg}`;
                })
                .join('\n');
            alert('登録できませんでした:\n' + messages);
            console.error('登録バリデーションエラー:', errors);
        },
    });
};
</script>

<template>
    <AppLayout title="新規Adminユーザー登録">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">新規Adminユーザー登録</h2>
                <Link :href="route('superadmin.adminusers.index')" class="text-gray-600 hover:text-gray-900"> ← Adminユーザー一覧に戻る </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <!-- ナビゲーションタブ -->
                <SuperAdminNavigationTabs active="users" />

                <div class="mb-8 overflow-hidden bg-white p-6 shadow-xl sm:rounded-lg">
                    <!-- <h3 class="text-lg font-medium text-blue-900 mb-2">CSV一括登録</h3>
                    <p class="text-sm text-blue-700 mb-4">
                        CSVファイルを使用して複数のユーザーを一度に登録できます。
                    </p>
                    <Link 
                        :href="route('superadmin.adminusers.csv.upload')" 
                        class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 active:bg-blue-900 focus:outline-none focus:border-blue-900 focus:ring ring-blue-300 disabled:opacity-25 transition ease-in-out duration-150"
                    >
                        📄 CSVファイルをアップロード
                    </Link> -->

                    <!-- <div class="relative mb-8">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-gray-300"></div>
                    </div>
                    <div class="relative flex justify-center text-sm">
                        <span class="px-2 bg-white text-gray-500">または個別に登録</span>
                    </div>
                </div> -->

                    <!-- Register.vueと同じフォーム構成 -->
                    <form @submit.prevent="submit">
                        <div>
                            <InputLabel for="name" value="名前" />
                            <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" required autofocus autocomplete="name" />
                            <InputError class="mt-2" :message="errors.name || form.errors.name" />
                        </div>

                        <div class="mt-4">
                            <InputLabel for="email" value="メールアドレス" />
                            <TextInput id="email" v-model="form.email" type="email" class="mt-1 block w-full" required autocomplete="username" />
                            <InputError class="mt-2" :message="errors.email || form.errors.email" />
                        </div>

                        <div class="mt-4">
                            <InputLabel for="password" value="パスワード" />
                            <TextInput
                                id="password"
                                v-model="form.password"
                                type="password"
                                class="mt-1 block w-full"
                                required
                                autocomplete="new-password"
                            />
                            <InputError class="mt-2" :message="errors.password || form.errors.password" />
                        </div>

                        <div class="mt-4">
                            <InputLabel for="password_confirmation" value="パスワード確認" />
                            <TextInput
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                type="password"
                                class="mt-1 block w-full"
                                required
                                autocomplete="new-password"
                            />
                            <InputError class="mt-2" :message="errors.password_confirmation || form.errors.password_confirmation" />
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
                            <InputError class="mt-2" :message="errors.company_id || form.errors.company_id" />
                            <div v-if="!companies || companies.length === 0" class="mt-2 text-red-600">
                                会社データがありません。管理者にご連絡ください。
                            </div>
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
                                <option value="--">-- 部署を選択してください --</option>
                                <option v-for="department in selectedCompanyDepartments" :key="department.id" :value="department.id">
                                    {{ department.name }}
                                </option>
                            </select>
                            <InputError class="mt-2" :message="errors.department_id || form.errors.department_id" />
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
                                <option value="--">-- 担当を選択してください --</option>
                                <option v-for="assignment in availableAssignments" :key="assignment.id" :value="assignment.id">
                                    {{ assignment.name }}
                                </option>
                            </select>
                            <InputError class="mt-2" :message="errors.assignment_id || form.errors.assignment_id" />
                        </div>

                        <div class="mt-4">
                            <InputLabel for="user_role" value="権限レベル" />
                            <select
                                id="user_role"
                                v-model="form.user_role"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                required
                            >
                                <option value="" disabled>権限レベルを選択してください</option>
                                <option v-for="option in userAssignmentOptions" :key="option.value" :value="option.value">
                                    {{ option.label }} - {{ option.description }}
                                </option>
                            </select>
                            <InputError class="mt-2" :message="errors.user_role || form.errors.user_role" />
                        </div>

                        <!-- 利用規約チェックは管理画面では不要なら省略 -->

                        <div class="mt-6 flex items-center justify-end space-x-3">
                            <Link :href="route('superadmin.adminusers.index')">
                                <SecondaryButton type="button"> キャンセル </SecondaryButton>
                            </Link>
                            <PrimaryButton class="bg-red-600 hover:bg-red-700" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                ユーザーを登録
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
