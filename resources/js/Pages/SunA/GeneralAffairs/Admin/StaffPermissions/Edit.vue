<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import AdminNavigationTabs from '@/Components/Tabs/AdminNavigationTabs.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

const props = defineProps({
    staff: { type: Object, required: true },
    permissions: { type: Object, required: true },
    abilities: { type: Object, default: () => ({}) },
});

const form = useForm({ ...props.permissions });
const alwaysAllowed = (key) => props.staff.role !== 'clerk' && key !== 'can_view_my_number';

function submit() {
    form.put(route('admin.general_affairs.staff_permissions.update', { staff: props.staff.id }));
}
</script>

<template>
    <AppLayout title="社員情報の権限設定">
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('admin.general_affairs.staff_permissions.index')" class="whitespace-nowrap rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300">
                    ← 一覧に戻る
                </Link>
                <h2 class="text-base font-semibold text-gray-800 sm:text-xl">権限設定</h2>
            </div>
        </template>
        <template #tabs>
            <AdminNavigationTabs active="staff_permissions" />
        </template>

        <div class="rounded bg-white p-6 shadow">
            <div class="mx-auto max-w-lg">
                <div class="mb-6 rounded-md bg-gray-50 px-4 py-3">
                    <p class="text-sm font-medium text-gray-700">{{ staff.name }}</p>
                    <p class="text-xs text-gray-500">{{ staff.email }}</p>
                </div>
                <form @submit.prevent="submit">
                    <div class="divide-y divide-gray-200">
                        <label v-for="(label, key) in abilities" :key="key" class="flex items-center justify-between gap-4 py-4" :class="alwaysAllowed(key) ? 'opacity-60' : 'cursor-pointer'">
                            <span class="text-sm font-medium text-gray-700">
                                {{ label }}
                                <span v-if="alwaysAllowed(key)" class="ml-1 text-xs font-normal text-gray-500">（管理者は常にできます）</span>
                            </span>
                            <input v-model="form[key]" type="checkbox" class="h-5 w-5 rounded text-red-600 focus:ring-red-500" />
                        </label>
                    </div>
                    <p class="mt-4 text-xs text-gray-500">マイナンバーの全文の表示・登録・変更・削除は、「マイナンバーを見る」権限が必要で、すべて記録されます。</p>
                    <div class="mt-6 flex items-center gap-3">
                        <button :disabled="form.processing" class="rounded bg-red-600 px-5 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50">{{ form.processing ? '保存中…' : '保存する' }}</button>
                        <Link :href="route('admin.general_affairs.staff_permissions.index')" class="rounded bg-gray-200 px-4 py-2 text-sm hover:bg-gray-300">キャンセル</Link>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
