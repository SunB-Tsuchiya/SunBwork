<script setup>
import UserTable from '@/Components/UserTable.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    team: { type: Object, required: true },
});

const page = usePage();
const currentTeam = ref(props.team || {});

const goBack = () => router.visit(route('leader.teams.index'));
const goEdit = () => router.visit(route('leader.teams.edit', { team: currentTeam.value.id }));

const departments = computed(() => page.props.departments || []);
const assignments = computed(() => page.props.assignments || []);

const formatDate = (iso) => {
    if (!iso) return '';
    const d = new Date(iso);
    if (isNaN(d)) return iso;
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}年${pad(d.getMonth() + 1)}月${pad(d.getDate())}日 ${pad(d.getHours())}時${pad(d.getMinutes())}分${pad(d.getSeconds())}秒`;
};

const leaderName = computed(() => {
    const teamObj = currentTeam.value || {};
    const lid = teamObj.leader_id || null;
    if (!lid) return '未設定';
    const users = Array.isArray(teamObj.users) ? teamObj.users : [];
    const found = users.find((u) => String(u.id) === String(lid));
    if (found) return found.name || `ID:${found.id}`;
    return '未設定';
});
</script>

<template>
    <AppLayout :title="`チーム：${currentTeam.name || ''}`">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">チーム詳細</h2>
        </template>

        <div class="mx-auto max-w-4xl rounded bg-white p-6 shadow">
            <div class="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <div class="text-sm text-gray-500">チーム名</div>
                    <div class="text-lg font-medium">{{ currentTeam.name }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">会社</div>
                    <div class="text-lg">{{ currentTeam.company?.name || '未設定' }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">部署</div>
                    <div class="text-lg">{{ currentTeam.department?.name || '未設定' }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">リーダー</div>
                    <div class="text-lg">{{ leaderName }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">作成日</div>
                    <div class="text-lg">{{ formatDate(currentTeam.created_at) }}</div>
                </div>
            </div>

            <div class="mt-6">
                <h3 class="text-sm font-medium text-gray-700">メンバー</h3>
                <UserTable :users="currentTeam.users || []" :departments="departments" :assignments="assignments" :show-actions="false" />
            </div>

            <div class="mt-6 flex gap-2">
                <button @click="goBack" class="rounded border px-4 py-2 text-sm">一覧へ戻る</button>
                <button @click="goEdit" class="rounded bg-orange-500 px-4 py-2 text-sm text-white hover:bg-orange-600">編集</button>
            </div>
        </div>
    </AppLayout>
</template>
