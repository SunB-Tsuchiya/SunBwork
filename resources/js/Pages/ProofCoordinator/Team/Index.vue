<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import ProofCoordinatorNavigationTabs from '@/Components/Tabs/ProofCoordinatorNavigationTabs.vue';
import { useForm, router } from '@inertiajs/vue3';

defineProps({
    members:    { type: Array, default: () => [] },
    candidates: { type: Array, default: () => [] },
});

const form = useForm({ user_id: '' });

function addMember() {
    form.post(route('proof_coordinator.team.store'), {
        preserveScroll: true,
        onSuccess: () => { form.reset(); },
    });
}

function removeMember(memberId) {
    if (!confirm('このメンバーを校正チームから削除しますか？')) return;
    router.delete(route('proof_coordinator.team.destroy', { proofTeamMember: memberId }), {
        preserveScroll: true,
    });
}
</script>

<template>
    <AppLayout title="校正チーム管理">
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">校正チーム管理</h2>
        </template>

        <template #tabs>
            <ProofCoordinatorNavigationTabs active="team" />
        </template>

        <div class="space-y-6">

            <!-- メンバー追加 -->
            <div class="rounded bg-white p-6 shadow">
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500">メンバーを追加</h3>
                <form @submit.prevent="addMember" class="flex items-end gap-3">
                    <div class="flex-1">
                        <label for="user_id" class="mb-1 block text-sm font-medium text-gray-700">ユーザー</label>
                        <select
                            id="user_id"
                            v-model="form.user_id"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-pink-500 focus:ring-pink-500 sm:text-sm"
                            required
                        >
                            <option value="">-- ユーザーを選択してください --</option>
                            <option v-for="u in candidates" :key="u.id" :value="u.id">
                                {{ u.name }} （{{ u.email }}）
                            </option>
                        </select>
                        <p v-if="form.errors.user_id" class="mt-1 text-xs text-red-600">{{ form.errors.user_id }}</p>
                    </div>
                    <button
                        type="submit"
                        :disabled="form.processing || !form.user_id"
                        class="rounded bg-pink-600 px-4 py-2 text-sm font-medium text-white hover:bg-pink-700 disabled:opacity-50"
                    >
                        追加する
                    </button>
                </form>
                <p class="mt-2 text-xs text-gray-400">
                    校正チームに追加すると、校正員の割り当て時に担当コードに関わらず候補として表示されます。
                </p>
            </div>

            <!-- 現在のメンバー一覧 -->
            <div class="rounded bg-white p-6 shadow">
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500">
                    現在のメンバー（{{ members.length }}名）
                </h3>

                <p v-if="members.length === 0" class="text-sm text-gray-500">
                    校正チームにメンバーはいません。
                </p>

                <table v-else class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">名前</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">メールアドレス</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr v-for="m in members" :key="m.id" class="hover:bg-gray-50">
                            <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-900">
                                {{ m.user?.name }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500">
                                {{ m.user?.email }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm">
                                <button
                                    @click="removeMember(m.id)"
                                    class="rounded border border-red-200 bg-red-50 px-3 py-1 text-xs font-medium text-red-700 hover:bg-red-100"
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
