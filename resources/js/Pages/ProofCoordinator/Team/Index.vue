<script setup>
import { ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import ProofCoordinatorNavigationTabs from '@/Components/Tabs/ProofCoordinatorNavigationTabs.vue';
import { useForm, router } from '@inertiajs/vue3';

const props = defineProps({
    members:    { type: Array, default: () => [] },
    candidates: { type: Array, default: () => [] },
});

const form = useForm({ user_id: '' });

// 並び替え用ローカルコピー
const sortedMembers = ref([...props.members]);
watch(() => props.members, (val) => { sortedMembers.value = [...val]; });

const sortMode = ref(false);
const saving   = ref(false);

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

function moveUp(index) {
    if (index <= 0) return;
    const arr = [...sortedMembers.value];
    [arr[index - 1], arr[index]] = [arr[index], arr[index - 1]];
    sortedMembers.value = arr;
    saveOrder();
}

function moveDown(index) {
    if (index >= sortedMembers.value.length - 1) return;
    const arr = [...sortedMembers.value];
    [arr[index], arr[index + 1]] = [arr[index + 1], arr[index]];
    sortedMembers.value = arr;
    saveOrder();
}

function saveOrder() {
    saving.value = true;
    router.post(
        route('proof_coordinator.team.reorder'),
        { ids: sortedMembers.value.map(m => m.id) },
        { preserveScroll: true, onFinish: () => { saving.value = false; } },
    );
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
            <div class="rounded bg-white px-4 py-6 sm:p-6 shadow">
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
            <div class="rounded bg-white px-4 py-6 sm:p-6 shadow">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">
                        現在のメンバー（{{ sortedMembers.length }}名）
                    </h3>
                    <button
                        v-if="sortedMembers.length > 1"
                        @click="sortMode = !sortMode"
                        :class="sortMode
                            ? 'bg-pink-600 text-white hover:bg-pink-700'
                            : 'border border-pink-300 text-pink-700 hover:bg-pink-50'"
                        class="rounded px-3 py-1.5 text-xs font-medium transition"
                    >
                        {{ sortMode ? '並べかえ終了' : '並べかえる' }}
                    </button>
                </div>

                <p v-if="saving" class="mb-2 text-xs text-gray-400">保存中…</p>

                <p v-if="sortedMembers.length === 0" class="text-sm text-gray-500">
                    校正チームにメンバーはいません。
                </p>

                <div v-else class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th v-if="sortMode" class="w-20 px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">順序</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">名前</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">メールアドレス</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr v-for="(m, index) in sortedMembers" :key="m.id" class="hover:bg-gray-50">
                            <!-- 並べかえボタン -->
                            <td v-if="sortMode" class="whitespace-nowrap px-4 py-3 text-sm">
                                <div class="flex gap-1">
                                    <button
                                        @click="moveUp(index)"
                                        :disabled="index === 0"
                                        class="rounded border border-gray-300 bg-white px-2 py-0.5 text-xs text-gray-600 hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-30"
                                        title="上へ"
                                    >▲</button>
                                    <button
                                        @click="moveDown(index)"
                                        :disabled="index === sortedMembers.length - 1"
                                        class="rounded border border-gray-300 bg-white px-2 py-0.5 text-xs text-gray-600 hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-30"
                                        title="下へ"
                                    >▼</button>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-900">
                                {{ m.user?.name }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500">
                                {{ m.user?.email }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm">
                                <button
                                    v-if="!sortMode"
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

                <p v-if="sortMode" class="mt-3 text-xs text-gray-400">
                    ▲▼ で順番を入れ替えられます。順番はカレンダー表示にも反映されます。
                </p>
            </div>

        </div>
    </AppLayout>
</template>

