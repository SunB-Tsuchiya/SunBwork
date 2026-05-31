<script setup>
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import AdminNavigationTabs from '@/Components/Tabs/AdminNavigationTabs.vue';
import { router, useForm } from '@inertiajs/vue3';

defineProps({
    departments: { type: Array, default: () => [] },
});

const showForm = ref(false);
const form = useForm({ name: '' });

function openForm() {
    form.reset();
    showForm.value = true;
}

function submit() {
    form.post(route('admin.departments.store'), {
        onSuccess: () => {
            showForm.value = false;
            form.reset();
        },
    });
}

async function handleDelete(dept) {
    if (!confirm(`「${dept.name}」を削除します。\n所属するチームも同時に削除されます。よろしいですか？`)) return;
    if (!confirm('本当に削除してよいですか？この操作は取り消せません。')) return;

    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    try {
        const res = await fetch(route('admin.departments.destroy', { department: dept.id }), {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf,
                Accept: 'application/json',
            },
            credentials: 'same-origin',
        });
        if (res.ok || res.status === 302) {
            router.visit(route('admin.departments.index'));
            return;
        }
        const data = await res.json().catch(() => ({}));
        alert(data.message || `削除に失敗しました (HTTP ${res.status})`);
    } catch {
        alert('削除に失敗しました。');
    }
}
</script>

<template>
    <AppLayout title="部署管理">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">部署管理</h2>
                <button
                    v-if="!showForm"
                    type="button"
                    class="rounded bg-red-600 px-4 py-2 text-sm font-bold text-white hover:bg-red-700"
                    @click="openForm"
                >
                    ＋ 新規作成
                </button>
            </div>
        </template>
        <template #tabs>
            <AdminNavigationTabs active="departments" />
        </template>

        <!-- 新規作成フォーム -->
        <div v-if="showForm" class="mb-4 rounded bg-white px-4 py-5 shadow">
            <p class="mb-3 text-sm font-medium text-gray-700">新しい部署を追加</p>
            <form @submit.prevent="submit" class="flex items-center gap-3">
                <input
                    v-model="form.name"
                    type="text"
                    maxlength="255"
                    placeholder="部署名を入力"
                    class="w-64 rounded border border-gray-300 px-3 py-2 text-sm focus:border-red-400 focus:outline-none focus:ring-1 focus:ring-red-400"
                    autofocus
                />
                <button
                    type="submit"
                    :disabled="form.processing || !form.name.trim()"
                    class="rounded bg-red-600 px-4 py-2 text-sm font-bold text-white hover:bg-red-700 disabled:opacity-50"
                >
                    {{ form.processing ? '作成中…' : '作成する' }}
                </button>
                <button
                    type="button"
                    class="rounded bg-gray-100 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-200"
                    @click="showForm = false"
                >
                    キャンセル
                </button>
            </form>
            <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
        </div>

        <div class="rounded bg-white px-4 py-6 sm:p-6 shadow">
            <div v-if="departments.length === 0" class="py-8 text-center text-gray-500">
                部署が登録されていません。
            </div>

            <div v-else class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">部署名</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">チーム</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr v-for="dept in departments" :key="dept.id" class="hover:bg-gray-50">
                            <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-900">{{ dept.name }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500">
                                <span v-if="dept.team_id" class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-700">
                                    チームあり
                                </span>
                                <span v-else class="inline-flex items-center rounded-full bg-yellow-100 px-2.5 py-0.5 text-xs font-medium text-yellow-700">
                                    チームなし
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-center">
                                <button
                                    v-if="dept.team_id"
                                    type="button"
                                    class="mr-2 rounded border border-gray-300 px-3 py-1 text-xs text-gray-700 hover:bg-gray-50"
                                    @click="router.visit(route('admin.teams.edit', { team: dept.team_id }))"
                                >
                                    チーム編集
                                </button>
                                <button
                                    type="button"
                                    class="rounded bg-red-600 px-3 py-1 text-xs font-bold text-white hover:bg-red-700"
                                    @click="handleDelete(dept)"
                                >
                                    削除
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
