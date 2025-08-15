<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import AdminNavigationTabs from '@/Components/AdminNavigationTabs.vue';
import { router } from '@inertiajs/vue3';
// ユーザー削除処理
function deleteUser(id) {
    if (confirm('本当にこのユーザーを削除しますか？')) {
        router.delete(route('admin.users.destroy', id), {
            onSuccess: () => {
                router.visit(route('admin.users.index'));
            }
        });
    }
}

const props = defineProps({
    users: {
        type: Array,
        required: true,
    },
    assignments: {
        type: Array,
        default: () => [],
    },
});

// assignment_idから役職名を取得
const getAssignmentName = (assignment_id) => {
    const assignment = props.assignments.find(r => r.id === assignment_id);
    return assignment ? assignment.name : '';
};

const getAssignmentBadgeClass = (assignment) => {
    switch (assignment) {
        case 'admin':
            return 'bg-red-100 text-red-800';
        case 'leader':
            return 'bg-orange-100 text-orange-800';
        case 'user':
            return 'bg-green-100 text-blue-800';
        case 'user':
            return 'bg-blue-100 text-blue-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
};

const getAssignmentText = (assignment) => {
    switch (assignment) {
        case 'admin':
            return '管理者';
        case 'leader':
            return 'リーダー';
        case 'coordinator':
            return '進行管理';
        case 'user':
            return 'ユーザー';
        default:
            return assignment;
    }
};
</script>

<template>
    <AppLayout title="ユーザー管理">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    ユーザー管理
                </h2>
                <Link :href="route('admin.users.create')" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">
                    新規ユーザー登録
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- ナビゲーションタブ -->
                <AdminNavigationTabs active="users" />

                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-medium text-gray-900">登録ユーザー一覧</h3>
                            <div class="text-sm text-gray-500">
                                総数: {{ users.length }}人
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
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
                                            権限レベル
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            登録日
                                        </th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            操作
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="user in users" :key="user.id" class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            {{ user.name }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ user.email }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ getAssignmentName(user.assignment_id) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span :class="getAssignmentBadgeClass(user.user_role)" class="inline-flex px-2 py-1 text-xs font-semibold rounded-full">
                                                {{ getAssignmentText(user.user_role) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ new Date(user.created_at).toLocaleDateString('ja-JP') }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <div class="flex justify-end space-x-2">
                                                <Link :href="route('admin.users.show', user.id)" class="text-blue-600 hover:text-blue-900">
                                                    詳細
                                                </Link>
                                                <Link :href="route('admin.users.edit', user.id)" class="text-yellow-600 hover:text-yellow-900">
                                                    編集
                                                </Link>
                                                    <button @click="deleteUser(user.id)" class="text-red-600 hover:text-red-900">
                                                    削除
                                                </button>
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
