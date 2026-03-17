<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';

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
    users: props.csvData.filter((_, index) => !props.errors.some((error) => error.includes(`行 ${index + 1}:`))),
    company_id: props.company_id,
    department_id: props.department_id,
});

const submit = () => {
    // Debug logging removed
    form.post(route('admin.users.csv.store'));
};

const getRoleBadgeClass = (role) => {
    switch (role) {
        case 'admin':
            return 'bg-red-100 text-red-800';
        case 'leader':
            return 'bg-orange-100 text-orange-800';
        case 'user':
            return 'bg-blue-100 text-blue-800';
        case 'coordinator':
            return 'bg-green-100 text-green-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
};

const getRoleLabel = (role) => {
    switch (role) {
        case 'admin':
            return '管理者';
        case 'leader':
            return 'リーダー';
        case 'user':
            return 'ユーザー';
        case 'coordinator':
            return '進行管理';
        default:
            return role;
    }
};
</script>

<template>
    <AppLayout title="CSV登録確認">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">CSV登録確認</h2>
                <Link :href="route('admin.users.csv.upload')" class="text-gray-600 hover:text-gray-900"> ← CSVアップロードに戻る </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <!-- 登録先情報 -->
                <div class="mb-6 rounded-lg border border-blue-200 bg-blue-50 p-4">
                    <h3 class="mb-3 text-lg font-medium text-blue-800">📍 登録先情報</h3>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <span class="text-sm font-medium text-blue-700">会社:</span>
                            <span class="ml-2 text-sm text-blue-800">{{ company.name }}</span>
                        </div>
                        <div>
                            <span class="text-sm font-medium text-blue-700">部署:</span>
                            <span class="ml-2 text-sm text-blue-800">{{ department.name }}</span>
                        </div>
                    </div>
                    <p class="mt-2 text-sm text-blue-600">CSVのユーザーは全て上記の部署チームに追加されます</p>
                </div>

                <!-- エラー表示 -->
                <div v-if="hasErrors" class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4">
                    <h3 class="mb-3 text-lg font-medium text-red-800">⚠️ エラーが検出されました</h3>
                    <ul class="list-inside list-disc space-y-1">
                        <li v-for="error in errors" :key="error" class="text-sm text-red-700">
                            {{ error }}
                        </li>
                    </ul>
                    <p class="mt-3 text-sm text-red-600">エラーがある行は登録されません。CSVファイルを修正してから再度アップロードしてください。</p>
                </div>

                <!-- 自動修正警告表示 -->
                <div v-if="hasWarnings" class="mb-6 rounded-lg border border-yellow-200 bg-yellow-50 p-4">
                    <h3 class="mb-3 text-lg font-medium text-yellow-800">🔧 自動修正が行われました</h3>
                    <ul class="list-inside list-disc space-y-1">
                        <li v-for="warning in warnings" :key="warning" class="text-sm text-yellow-700">
                            {{ warning }}
                        </li>
                    </ul>
                    <p class="mt-3 text-sm text-yellow-600">上記の項目が自動的に修正されました。内容を確認してから登録を実行してください。</p>
                </div>

                <!-- 成功可能なデータの表示 -->
                <div v-if="form.users.length > 0" class="mb-6 overflow-hidden bg-white shadow-xl sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="mb-4 text-lg font-medium text-gray-900">📋 登録予定のユーザー ({{ form.users.length }}件)</h3>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">行番号</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">名前</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">メールアドレス</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">担当</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">システム権限</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white">
                                    <tr v-for="user in form.users" :key="user.line" class="hover:bg-gray-50">
                                        <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                            {{ user.line }}
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4">
                                            <div class="text-sm font-medium text-gray-900">{{ user.name }}</div>
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4">
                                            <div class="text-sm text-gray-900">{{ user.email }}</div>
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4">
                                            <div class="text-sm text-gray-900">{{ user.assignment }}</div>
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4">
                                            <span
                                                class="inline-flex rounded-full px-2 text-xs font-semibold leading-5"
                                                :class="getRoleBadgeClass(user.user_role)"
                                            >
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
                <div v-if="csvData.length > form.users.length" class="mb-6 rounded-lg border border-yellow-200 bg-yellow-50 p-4">
                    <h3 class="mb-3 text-lg font-medium text-yellow-800">⚠️ 登録できないデータ ({{ csvData.length - form.users.length }}件)</h3>
                    <p class="text-sm text-yellow-700">
                        エラーがあるため、これらのデータは登録されません。CSVファイルを修正してから再度アップロードしてください。
                    </p>
                </div>

                <!-- アクションボタン -->
                <div class="flex items-center justify-between">
                    <Link
                        :href="route('admin.users.csv.upload')"
                        class="inline-flex items-center rounded-md border border-transparent bg-gray-500 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white ring-gray-300 transition duration-150 ease-in-out hover:bg-gray-600 focus:border-gray-700 focus:outline-none focus:ring active:bg-gray-700 disabled:opacity-25"
                    >
                        ← キャンセル
                    </Link>

                    <div class="flex space-x-4">
                        <Link
                            :href="route('admin.users.index')"
                            class="inline-flex items-center rounded-md border border-transparent bg-gray-500 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white ring-gray-300 transition duration-150 ease-in-out hover:bg-gray-600 focus:border-gray-700 focus:outline-none focus:ring active:bg-gray-700 disabled:opacity-25"
                        >
                            ユーザー一覧に戻る
                        </Link>

                        <form @submit.prevent="submit" class="inline">
                            <PrimaryButton v-if="form.users.length > 0" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                <span v-if="form.processing">登録中...</span>
                                <span v-else>✅ {{ form.users.length }}件のユーザーを登録する</span>
                            </PrimaryButton>
                        </form>
                    </div>
                </div>

                <!-- データがない場合 -->
                <div v-if="form.users.length === 0" class="overflow-hidden bg-white shadow-xl sm:rounded-lg">
                    <div class="p-6 text-center">
                        <div class="mb-4 text-6xl text-gray-400">📄</div>
                        <h3 class="mb-2 text-lg font-medium text-gray-900">登録可能なデータがありません</h3>
                        <p class="mb-4 text-gray-600">すべてのデータにエラーがあるため、登録できるユーザーがありません。</p>
                        <form :action="route('admin.users.csv.upload')" method="get" class="inline">
                            <button
                                type="submit"
                                class="inline-flex items-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white ring-blue-300 transition duration-150 ease-in-out hover:bg-blue-700 focus:border-blue-900 focus:outline-none focus:ring active:bg-blue-900 disabled:opacity-25"
                            >
                                CSVファイルを修正してアップロード
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
