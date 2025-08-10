<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: '',
    user_role: 'user',
});

const submit = () => {
    form.post(route('admin.users.store'), {
        onFinish: () => {
            form.reset('password', 'password_confirmation');
        },
    });
};

const userRoleOptions = [
    { value: 'admin', label: '管理者', description: '全ての機能にアクセス可能' },
    { value: 'leader', label: 'オーナー', description: 'コンテンツ管理とユーザー機能にアクセス可能' },
    { value: 'user', label: 'ユーザー', description: '基本機能のみアクセス可能' }
];
</script>

<template>
    <AppLayout title="新規ユーザー登録">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    新規ユーザー登録
                </h2>
                <Link :href="route('admin.users.index')" class="text-gray-600 hover:text-gray-900">
                    ← ユーザー一覧に戻る
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <!-- ナビゲーションタブ -->
                <div class="mb-6">
                    <nav class="flex space-x-8" aria-label="Tabs">
                        <Link :href="route('admin.dashboard')" class="text-red-600 hover:text-red-800 px-3 py-2 font-medium text-sm rounded-md border border-red-200 hover:bg-red-50">
                            管理者ダッシュボード
                        </Link>
                        <Link :href="route('admin.users.index')" class="text-red-600 hover:text-red-800 px-3 py-2 font-medium text-sm rounded-md border border-red-200 hover:bg-red-50">
                            ユーザー管理
                        </Link>
                        <a href="#" class="bg-red-100 text-red-700 px-3 py-2 font-medium text-sm rounded-md">
                            新規ユーザー登録
                        </a>
                    </nav>
                </div>

                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-6">
                        <!-- CSV一括登録セクション -->
                        <div class="mb-8 p-4 bg-blue-50 rounded-lg border border-blue-200">
                            <h3 class="text-lg font-medium text-blue-900 mb-2">CSV一括登録</h3>
                            <p class="text-sm text-blue-700 mb-4">
                                CSVファイルを使用して複数のユーザーを一度に登録できます。
                            </p>
                            <Link 
                                :href="route('admin.users.csv.upload')" 
                                class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 active:bg-blue-900 focus:outline-none focus:border-blue-900 focus:ring ring-blue-300 disabled:opacity-25 transition ease-in-out duration-150"
                            >
                                📄 CSVファイルをアップロード
                            </Link>
                        </div>

                        <!-- または区切り線 -->
                        <div class="relative mb-8">
                            <div class="absolute inset-0 flex items-center">
                                <div class="w-full border-t border-gray-300"></div>
                            </div>
                            <div class="relative flex justify-center text-sm">
                                <span class="px-2 bg-white text-gray-500">または個別に登録</span>
                            </div>
                        </div>

                        <form @submit.prevent="submit">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- 基本情報 -->
                                <div class="col-span-2">
                                    <h3 class="text-lg font-medium text-gray-900 mb-4">基本情報</h3>
                                </div>

                                <!-- 名前 -->
                                <div>
                                    <InputLabel for="name" value="名前" />
                                    <TextInput
                                        id="name"
                                        type="text"
                                        class="mt-1 block w-full"
                                        v-model="form.name"
                                        required
                                        autofocus
                                        autocomplete="name"
                                    />
                                    <InputError class="mt-2" :message="form.errors.name" />
                                </div>

                                <!-- メールアドレス -->
                                <div>
                                    <InputLabel for="email" value="メールアドレス" />
                                    <TextInput
                                        id="email"
                                        type="email"
                                        class="mt-1 block w-full"
                                        v-model="form.email"
                                        required
                                        autocomplete="username"
                                    />
                                    <InputError class="mt-2" :message="form.errors.email" />
                                </div>

                                <!-- パスワード -->
                                <div>
                                    <InputLabel for="password" value="パスワード" />
                                    <TextInput
                                        id="password"
                                        type="password"
                                        class="mt-1 block w-full"
                                        v-model="form.password"
                                        required
                                        autocomplete="new-password"
                                    />
                                    <InputError class="mt-2" :message="form.errors.password" />
                                </div>

                                <!-- パスワード確認 -->
                                <div>
                                    <InputLabel for="password_confirmation" value="パスワード確認" />
                                    <TextInput
                                        id="password_confirmation"
                                        type="password"
                                        class="mt-1 block w-full"
                                        v-model="form.password_confirmation"
                                        required
                                        autocomplete="new-password"
                                    />
                                    <InputError class="mt-2" :message="form.errors.password_confirmation" />
                                </div>

                                <!-- 担当 -->
                                <div>
                                    <InputLabel for="role" value="担当" />
                                    <TextInput
                                        id="role"
                                        type="text"
                                        class="mt-1 block w-full"
                                        v-model="form.role"
                                        required
                                        placeholder="例: 進行管理"
                                    />
                                    <InputError class="mt-2" :message="form.errors.role" />
                                </div>

                                <!-- 権限レベル -->
                                <div class="col-span-2">
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
                                            <h4 class="font-medium text-orange-900">オーナー</h4>
                                            <p class="text-sm text-orange-700 mt-1">コンテンツ管理とユーザー機能にアクセス可能。</p>
                                        </div>
                                        <div class="bg-blue-50 p-3 rounded-lg border border-blue-200">
                                            <h4 class="font-medium text-blue-900">ユーザー</h4>
                                            <p class="text-sm text-blue-700 mt-1">基本機能のみアクセス可能。</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center justify-end mt-6 space-x-3">
                                <Link :href="route('admin.users.index')">
                                    <SecondaryButton type="button">
                                        キャンセル
                                    </SecondaryButton>
                                </Link>

                                <PrimaryButton class="bg-red-600 hover:bg-red-700" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                    ユーザーを登録
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
