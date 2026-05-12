<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import ClerkNavigationTabs from '@/Components/Tabs/ClerkNavigationTabs.vue';
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    users: Array,
});

const form = ref({
    target_type: 'all',
    title: '',
    content: '',
    user_ids: [],
});

const errors = ref({});
const showModal = ref(false);
const employmentFilter = ref('all');

// モーダル内絞り込み
const filteredUsers = computed(() => {
    if (employmentFilter.value === 'all') return props.users;
    if (employmentFilter.value === 'employees') {
        return props.users.filter(u => ['regular', 'contract'].includes(u.employment_type));
    }
    if (employmentFilter.value === 'dispatch') {
        return props.users.filter(u => ['dispatch', 'outsource'].includes(u.employment_type));
    }
    return props.users;
});

const employmentLabel = (type) => ({
    regular: '正社員',
    contract: '契約社員',
    dispatch: '派遣',
    outsource: '業務委託',
}[type] ?? type);

// 個別選択 → モーダルを表示
watch(() => form.value.target_type, (v) => {
    if (v !== 'individual') {
        form.value.user_ids = [];
    }
});

const openModal = () => {
    showModal.value = true;
};
const closeModal = () => {
    showModal.value = false;
};

const toggleUser = (id) => {
    const idx = form.value.user_ids.indexOf(id);
    if (idx === -1) form.value.user_ids.push(id);
    else form.value.user_ids.splice(idx, 1);
};

const selectAll = () => {
    form.value.user_ids = filteredUsers.value.map(u => u.id);
};
const clearAll = () => {
    form.value.user_ids = [];
};

const submit = () => {
    errors.value = {};
    router.post(route('clerk.announcements.store'), form.value, {
        onError: (e) => { errors.value = e; },
    });
};
</script>

<template>
    <AppLayout title="お知らせ作成">
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">お知らせ作成</h2>
        </template>

        <div class="rounded bg-white px-4 py-6 sm:p-6 shadow">
            <ClerkNavigationTabs active="announcements" />

            <form @submit.prevent="submit" class="mx-auto max-w-2xl space-y-6">
                <!-- 宛先 -->
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">宛先</label>
                    <div class="flex gap-4">
                        <label class="flex cursor-pointer items-center gap-1.5">
                            <input type="radio" v-model="form.target_type" value="all" class="text-purple-600" />
                            <span class="text-sm">全員</span>
                        </label>
                        <label class="flex cursor-pointer items-center gap-1.5">
                            <input type="radio" v-model="form.target_type" value="employees_only" class="text-purple-600" />
                            <span class="text-sm">社員のみ</span>
                        </label>
                        <label class="flex cursor-pointer items-center gap-1.5">
                            <input type="radio" v-model="form.target_type" value="individual" class="text-purple-600" />
                            <span class="text-sm">個別選択</span>
                        </label>
                    </div>
                    <p v-if="errors.target_type" class="mt-1 text-xs text-red-500">{{ errors.target_type }}</p>

                    <!-- 個別選択時のユーザー選択ボタン -->
                    <div v-if="form.target_type === 'individual'" class="mt-3">
                        <button
                            type="button"
                            @click="openModal"
                            class="rounded border border-purple-400 px-3 py-1.5 text-sm text-purple-700 hover:bg-purple-50"
                        >
                            ユーザーを選択する
                        </button>
                        <span v-if="form.user_ids.length > 0" class="ml-2 text-sm text-gray-600">
                            {{ form.user_ids.length }}人選択中
                        </span>
                        <p v-if="errors.user_ids" class="mt-1 text-xs text-red-500">{{ errors.user_ids }}</p>
                    </div>
                </div>

                <!-- タイトル -->
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">タイトル</label>
                    <input
                        v-model="form.title"
                        type="text"
                        class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-purple-400 focus:outline-none focus:ring-1 focus:ring-purple-400"
                        placeholder="お知らせのタイトル"
                    />
                    <p v-if="errors.title" class="mt-1 text-xs text-red-500">{{ errors.title }}</p>
                </div>

                <!-- 内容 -->
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">内容</label>
                    <textarea
                        v-model="form.content"
                        rows="8"
                        class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-purple-400 focus:outline-none focus:ring-1 focus:ring-purple-400"
                        placeholder="お知らせの内容を入力してください"
                    ></textarea>
                    <p v-if="errors.content" class="mt-1 text-xs text-red-500">{{ errors.content }}</p>
                </div>

                <!-- 送信ボタン -->
                <div class="flex justify-end gap-3">
                    <a :href="route('clerk.announcements.index')" class="rounded border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
                        キャンセル
                    </a>
                    <button
                        type="submit"
                        class="rounded bg-purple-600 px-6 py-2 text-sm font-medium text-white hover:bg-purple-700 disabled:opacity-50"
                    >
                        送信する
                    </button>
                </div>
            </form>
        </div>

        <!-- ユーザー選択モーダル -->
        <Teleport to="body">
            <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
                <div class="flex max-h-[85vh] w-full max-w-2xl flex-col overflow-hidden rounded-lg bg-white shadow-xl">
                    <!-- ヘッダー -->
                    <div class="flex items-center justify-between border-b px-6 py-4">
                        <h3 class="text-lg font-semibold text-gray-800">送信先ユーザーを選択</h3>
                        <button @click="closeModal" class="text-gray-400 hover:text-gray-600">✕</button>
                    </div>

                    <!-- 絞り込み -->
                    <div class="flex items-center gap-3 border-b bg-gray-50 px-6 py-3">
                        <span class="text-sm text-gray-600">絞り込み:</span>
                        <label class="flex cursor-pointer items-center gap-1 text-sm">
                            <input type="radio" v-model="employmentFilter" value="all" class="text-purple-600" />
                            全員
                        </label>
                        <label class="flex cursor-pointer items-center gap-1 text-sm">
                            <input type="radio" v-model="employmentFilter" value="employees" class="text-purple-600" />
                            正社員・契約社員
                        </label>
                        <label class="flex cursor-pointer items-center gap-1 text-sm">
                            <input type="radio" v-model="employmentFilter" value="dispatch" class="text-purple-600" />
                            派遣・業務委託
                        </label>
                        <div class="ml-auto flex gap-2">
                            <button @click="selectAll" class="text-xs text-purple-600 hover:underline">全選択</button>
                            <button @click="clearAll" class="text-xs text-gray-500 hover:underline">解除</button>
                        </div>
                    </div>

                    <!-- ユーザー一覧 -->
                    <div class="flex-1 overflow-y-auto">
                        <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="sticky top-0 bg-gray-50">
                                <tr>
                                    <th class="w-10 px-4 py-2"></th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">名前</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">担当</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">雇用形態</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr
                                    v-for="u in filteredUsers"
                                    :key="u.id"
                                    class="cursor-pointer hover:bg-purple-50"
                                    :class="form.user_ids.includes(u.id) ? 'bg-purple-50' : ''"
                                    @click="toggleUser(u.id)"
                                >
                                    <td class="px-4 py-2 text-center">
                                        <input
                                            type="checkbox"
                                            :checked="form.user_ids.includes(u.id)"
                                            @click.stop="toggleUser(u.id)"
                                            class="rounded text-purple-600"
                                        />
                                    </td>
                                    <td class="px-4 py-2 text-sm font-medium text-gray-800">{{ u.name }}</td>
                                    <td class="px-4 py-2 text-sm text-gray-600">{{ u.assignment_name }}</td>
                                    <td class="px-4 py-2 text-sm text-gray-600">{{ employmentLabel(u.employment_type) }}</td>
                                </tr>
                            </tbody>
                        </table>
                        </div>
                        <p v-if="filteredUsers.length === 0" class="py-6 text-center text-sm text-gray-500">
                            該当するユーザーがいません
                        </p>
                    </div>

                    <!-- フッター -->
                    <div class="flex items-center justify-between border-t px-6 py-4">
                        <span class="text-sm text-gray-600">{{ form.user_ids.length }}人選択中</span>
                        <button
                            @click="closeModal"
                            class="rounded bg-purple-600 px-5 py-2 text-sm font-medium text-white hover:bg-purple-700"
                        >
                            確定
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
