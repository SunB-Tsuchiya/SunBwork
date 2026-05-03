<script setup>
import UserTable from '@/Components/UserTable.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    team: {
        type: Object,
        required: true,
    },
});

const page = usePage();
const currentTeam = ref(props.team || {});

// Debug: log incoming props and resolved team/users to browser console
// Debug logging removed

// table helpers
const departments = computed(() => page.props.departments || []);
const assignments = computed(() => page.props.assignments || []);

// 追加：ISO文字列を "YYYY年MM月DD日 HH時mm分ss秒" に整形するヘルパー
const formatDate = (iso) => {
    if (!iso) return '';
    const d = new Date(iso);
    if (isNaN(d)) return iso;
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}年${pad(d.getMonth() + 1)}月${pad(d.getDate())}日 ${pad(d.getHours())}時${pad(d.getMinutes())}分${pad(d.getSeconds())}秒`;
};

// compute leader display name from team.leader_id or leader_user relation if present
const leaderName = computed(() => {
    const teamObj = currentTeam.value || {};
    const lid = teamObj.leader_id || teamObj.leader_user_id || null;
    if (!lid) return '未設定';

    // if users are loaded, try to find leader by id
    const users = Array.isArray(teamObj.users) ? teamObj.users : [];
    const found = users.find((u) => String(u.id) === String(lid));
    if (found) return found.name || found.display_name || found.email || `ID:${found.id}`;

    // if there's an embedded leader object
    if (teamObj.leader_user && (teamObj.leader_user.name || teamObj.leader_user.display_name)) {
        return teamObj.leader_user.name || teamObj.leader_user.display_name;
    }

    // handle sentinel value
    if (String(lid) === 'superadmin') return 'Super Admin (全権限)';

    return '未設定';
});
</script>

<template>
    <AppLayout :title="`チーム：${currentTeam.name || ''}`">
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('admin.teams.index')" class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300">← チーム一覧に戻る</Link>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">チーム詳細</h2>
            </div>
        </template>
        <template #headerExtras>
            <Link
                v-if="currentTeam.team_type !== 'department'"
                :href="route('admin.teams.edit', { team: currentTeam.id })"
                class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
            >編集</Link>
        </template>

        <div class="rounded bg-white p-6 shadow">
                    <div class="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <div class="text-sm text-gray-500">ID</div>
                            <div class="text-lg font-medium">{{ currentTeam.id }}</div>
                        </div>
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
                            <div class="text-sm text-gray-500">種別</div>
                            <div class="text-lg">{{ currentTeam.team_type || '' }}</div>
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

        </div>
    </AppLayout>
</template>
