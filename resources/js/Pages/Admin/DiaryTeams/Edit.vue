<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import AdminNavigationTabs from '@/Components/Tabs/AdminNavigationTabs.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    diaryTeam:        { type: Object, required: true },
    leaderIds:        { type: Array, default: () => [] },
    memberIds:        { type: Array, default: () => [] },
    leaderCandidates: { type: Array, default: () => [] },
    memberCandidates: { type: Array, default: () => [] },
});

const form = useForm({
    name:        props.diaryTeam.name,
    description: props.diaryTeam.description ?? '',
    leader_ids:  [...props.leaderIds],
    member_ids:  [...props.memberIds],
});

const leaderSearch = ref('');
const memberSearch = ref('');

const roleLabel = {
    clerk:             '事務',
    coordinator:       'コーディネーター',
    proof_coordinator: '校正Co',
};

const filteredLeaders = computed(() => {
    const q = leaderSearch.value.toLowerCase();
    return props.leaderCandidates.filter(u => !q || u.name.toLowerCase().includes(q));
});

const filteredMembers = computed(() => {
    const q = memberSearch.value.toLowerCase();
    return props.memberCandidates.filter(u => !q || u.name.toLowerCase().includes(q));
});

function submit() {
    form.put(route('admin.diary_teams.update', props.diaryTeam.id));
}
</script>

<template>
    <AppLayout title="日報権限チーム編集">
        <template #header>
            <div class="flex items-center gap-3">
                <Link
                    :href="route('admin.diary_teams.index')"
                    class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300 whitespace-nowrap"
                >← 一覧に戻る</Link>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">日報権限チーム 編集</h2>
            </div>
        </template>

        <template #tabs>
            <AdminNavigationTabs active="diary_teams" />
        </template>

        <div class="rounded bg-white p-6 shadow">
            <form @submit.prevent="submit" class="space-y-6 max-w-3xl">

                <!-- チーム名 -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">チーム名 <span class="text-red-500">*</span></label>
                    <input
                        v-model="form.name"
                        type="text"
                        class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-red-400 focus:outline-none"
                    />
                    <p v-if="form.errors.name" class="mt-1 text-xs text-red-500">{{ form.errors.name }}</p>
                </div>

                <!-- 説明 -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">説明</label>
                    <textarea
                        v-model="form.description"
                        rows="3"
                        class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-red-400 focus:outline-none"
                    ></textarea>
                </div>

                <!-- リーダー選択 -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        日報マネージャー（Clerk / Coordinator / 校正Co から選択）
                    </label>
                    <input
                        v-model="leaderSearch"
                        type="text"
                        placeholder="名前で絞り込み"
                        class="mb-2 w-full rounded border border-gray-200 px-3 py-1.5 text-sm focus:border-red-300 focus:outline-none"
                    />
                    <div class="max-h-48 overflow-y-auto rounded border border-gray-200 bg-gray-50 p-2">
                        <label
                            v-for="user in filteredLeaders"
                            :key="user.id"
                            class="flex cursor-pointer items-center gap-2 rounded px-2 py-1 hover:bg-white"
                        >
                            <input
                                type="checkbox"
                                :value="user.id"
                                v-model="form.leader_ids"
                                class="rounded border-gray-300 text-red-600"
                            />
                            <span class="text-sm text-gray-800">{{ user.name }}</span>
                            <span class="text-xs text-gray-400">{{ roleLabel[user.user_role] ?? user.user_role }}</span>
                        </label>
                        <p v-if="filteredLeaders.length === 0" class="text-xs text-gray-400 px-2 py-1">該当なし</p>
                    </div>
                    <p v-if="form.errors.leader_ids" class="mt-1 text-xs text-red-500">{{ form.errors.leader_ids }}</p>
                </div>

                <!-- メンバー選択 -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        閲覧対象メンバー（このチームの日報を閲覧できる対象）
                    </label>
                    <input
                        v-model="memberSearch"
                        type="text"
                        placeholder="名前で絞り込み"
                        class="mb-2 w-full rounded border border-gray-200 px-3 py-1.5 text-sm focus:border-red-300 focus:outline-none"
                    />
                    <div class="max-h-48 overflow-y-auto rounded border border-gray-200 bg-gray-50 p-2">
                        <label
                            v-for="user in filteredMembers"
                            :key="user.id"
                            class="flex cursor-pointer items-center gap-2 rounded px-2 py-1 hover:bg-white"
                        >
                            <input
                                type="checkbox"
                                :value="user.id"
                                v-model="form.member_ids"
                                class="rounded border-gray-300 text-red-600"
                            />
                            <span class="text-sm text-gray-800">{{ user.name }}</span>
                        </label>
                        <p v-if="filteredMembers.length === 0" class="text-xs text-gray-400 px-2 py-1">該当なし</p>
                    </div>
                    <p v-if="form.errors.member_ids" class="mt-1 text-xs text-red-500">{{ form.errors.member_ids }}</p>
                </div>

                <!-- 送信 -->
                <div class="flex items-center gap-3">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded bg-red-600 px-5 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
                    >
                        更新
                    </button>
                    <Link :href="route('admin.diary_teams.index')" class="text-sm text-gray-500 hover:underline">
                        キャンセル
                    </Link>
                </div>

            </form>
        </div>
    </AppLayout>
</template>
