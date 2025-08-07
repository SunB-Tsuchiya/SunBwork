<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    csvData: Array,
    errors: Array,
    warnings: Array,
    hasErrors: Boolean,
    hasWarnings: Boolean,
    company: Object,
    department: Object,
    company_id: String,
    department_id: String,
});

const form = useForm({
    users: props.csvData.filter((_, index) => !props.errors.some(error => error.includes(`行 ${index + 1}:`))),
    company_id: props.company_id,
    department_id: props.department_id,
});

const submit = () => {
    form.post(route('admin.users.csv.store'));
};

const getRoleBadgeClass = (role) => {
    switch (role) {
        case 'admin':
            return 'bg-red-100 text-red-800';
        case 'owner':
            return 'bg-orange-100 text-orange-800';
        case 'user':
            return 'bg-blue-100 text-blue-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
};

const getRoleLabel = (role) => {
    switch (role) {
        case 'admin':
            return '管理者';
        case 'owner':
            return 'オーナー';
        case 'user':
            return 'ユーザー';
        default:
            return role;
    }
};
</script>

<template>
    <AppLayout title="CSV登録確認">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    CSV登録確認
                </h2>
                <Link :href="route('admin.users.csv.upload')" class="text-gray-600 hover:text-gray-900">
                    ← CSVアップロードに戻る
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
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
                            CSV登録確認
                        </a>
                    </nav>
                </div>

                <!-- 登録先情報 -->
                <div class="mb-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <h3 class="text-lg font-medium text-blue-800 mb-3">📍 登録先情報</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <span class="text-sm font-medium text-blue-700">会社:</span>
                            <span class="ml-2 text-sm text-blue-800">{{ company.name }}</span>
                        </div>
                        <div>
                            <span class="text-sm font-medium text-blue-700">部署:</span>
                            <span class="ml-2 text-sm text-blue-800">{{ department.name }}</span>
                        </div>
                    </div>
                    <p class="mt-2 text-sm text-blue-600">
                        CSVのユーザーは全て上記の部署チームに追加されます
                    </p>
                </div>

                <!-- エラー表示 -->
                <div v-if="hasErrors" class="mb-6 bg-red-50 border border-red-200 rounded-lg p-4">
                    <h3 class="text-lg font-medium text-red-800 mb-3">⚠️ エラーが検出されました</h3>
                    <ul class="list-disc list-inside space-y-1">
                        <li v-for="error in errors" :key="error" class="text-sm text-red-700">
                            {{ error }}
                        </li>
                    </ul>
                    <p class="mt-3 text-sm text-red-600">
                        エラーがある行は登録されません。CSVファイルを修正してから再度アップロードしてください。
                    </p>
                </div>

                <!-- 自動修正警告表示 -->
                <div v-if="hasWarnings" class="mb-6 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <h3 class="text-lg font-medium text-yellow-800 mb-3">🔧 自動修正が行われました</h3>
                    <ul class="list-disc list-inside space-y-1">
                        <li v-for="warning in warnings" :key="warning" class="text-sm text-yellow-700">
                            {{ warning }}
                        </li>
                    </ul>
                    <p class="mt-3 text-sm text-yellow-600">
                        上記の項目が自動的に修正されました。内容を確認してから登録を実行してください。
                    </p>
                </div>

                <!-- 成功可能なデータの表示 -->
                <div v-if="form.users.length > 0" class="bg-white overflow-hidden shadow-xl sm:rounded-lg mb-6">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">
                            📋 登録予定のユーザー ({{ form.users.length }}件)
                        </h3>
                        
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            行番号
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            名前
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            メールアドレス
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            担当
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            システム権限
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="user in form.users" :key="user.line" class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ user.line }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">{{ user.name }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">{{ user.email }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">{{ user.role }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full" 
                                                  :class="getRoleBadgeClass(user.user_role)">
                                                {{ getRoleLabel(user.user_role) }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- 登録不可能なデータの表示 -->
                <div v-if="csvData.length > form.users.length" class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                    <h3 class="text-lg font-medium text-yellow-800 mb-3">
                        ⚠️ 登録できないデータ ({{ csvData.length - form.users.length }}件)
                    </h3>
                    <p class="text-sm text-yellow-700">
                        エラーがあるため、これらのデータは登録されません。CSVファイルを修正してから再度アップロードしてください。
                    </p>
                </div>

                <!-- アクションボタン -->
                <div class="flex items-center justify-between">
                    <Link 
                        :href="route('admin.users.csv.upload')" 
                        class="inline-flex items-center px-4 py-2 bg-gray-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-600 active:bg-gray-700 focus:outline-none focus:border-gray-700 focus:ring ring-gray-300 disabled:opacity-25 transition ease-in-out duration-150"
                    >
                        ← キャンセル
                    </Link>
                    
                    <div class="flex space-x-4">
                        <Link 
                            :href="route('admin.users.index')" 
                            class="inline-flex items-center px-4 py-2 bg-gray-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-600 active:bg-gray-700 focus:outline-none focus:border-gray-700 focus:ring ring-gray-300 disabled:opacity-25 transition ease-in-out duration-150"
                        >
                            ユーザー一覧に戻る
                        </Link>
                        
                        <form @submit.prevent="submit" class="inline">
                            <PrimaryButton 
                                v-if="form.users.length > 0"
                                :class="{ 'opacity-25': form.processing }" 
                                :disabled="form.processing"
                            >
                                <span v-if="form.processing">登録中...</span>
                                <span v-else>✅ {{ form.users.length }}件のユーザーを登録する</span>
                            </PrimaryButton>
                        </form>
                    </div>
                </div>

                <!-- データがない場合 -->
                <div v-if="form.users.length === 0" class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-6 text-center">
                        <div class="text-gray-400 text-6xl mb-4">📄</div>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">登録可能なデータがありません</h3>
                        <p class="text-gray-600 mb-4">
                            すべてのデータにエラーがあるため、登録できるユーザーがありません。
                        </p>
                        <Link 
                            :href="route('admin.users.csv.upload')" 
                            class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 active:bg-blue-900 focus:outline-none focus:border-blue-900 focus:ring ring-blue-300 disabled:opacity-25 transition ease-in-out duration-150"
                        >
                            CSVファイルを修正してアップロード
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
