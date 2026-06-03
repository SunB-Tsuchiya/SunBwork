<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { ref, watch, nextTick, computed } from 'vue';
import { route } from 'ziggy-js';
import TeamScheduleCalendar from '@/Components/TeamRoom/TeamScheduleCalendar.vue';
import TeamBoard from '@/Components/TeamRoom/TeamBoard.vue';
import TeamMinutesList from '@/Components/TeamRoom/TeamMinutesList.vue';

const props = defineProps({
    team:          { type: Object, required: true },
    leader:        { type: Object, default: null },
    board:         { type: Object, default: null },
    recentMinutes: { type: Array, default: () => [] },
    activeTab:     { type: String, default: 'overview' },
    // minutesタブ用（minutes.index から渡される場合）
    minutes:       { type: Array, default: null },
});

const tabs = [
    { key: 'overview',  label: '概要・メンバー' },
    { key: 'schedule',  label: 'スケジュール' },
    { key: 'board',     label: 'プロジェクトボード' },
    { key: 'minutes',   label: '会議記録' },
];

const activeTab = ref(new URLSearchParams(window.location.search).get('tab') || props.activeTab);

const scheduleRef = ref(null);
watch(activeTab, (tab) => {
    if (tab === 'schedule') {
        nextTick(() => scheduleRef.value?.refreshCalendar?.());
    }
    const url = new URL(window.location.href);
    url.searchParams.set('tab', tab);
    window.history.replaceState({}, '', url.toString());
});

const page = usePage();
const authUser = computed(() => page.props.auth?.user);

const roleLabel = {
    superadmin:  'SuperAdmin',
    admin:       'Admin',
    leader:      'Leader',
    coordinator: 'Coordinator',
    clerk:       'Clerk',
    user:        'User',
};
const roleColor = {
    superadmin:  'bg-yellow-100 text-yellow-800 border-yellow-200',
    admin:       'bg-red-100 text-red-800 border-red-200',
    leader:      'bg-orange-100 text-orange-800 border-orange-200',
    coordinator: 'bg-green-100 text-green-800 border-green-200',
    clerk:       'bg-purple-100 text-purple-800 border-purple-200',
    user:        'bg-blue-100 text-blue-800 border-blue-200',
};

// ボードの状態（Show.vueで管理してコンポーネントに渡す）
const board = ref(props.board);

function onBoardCreated(newBoard) {
    board.value = newBoard;
}
function onBoardUpdated(updatedBoard) {
    board.value = updatedBoard;
}
</script>

<template>
    <AppLayout :title="team.name">
        <template #header>
            <div class="flex items-center gap-3">
                <Link
                    :href="route('team-rooms.index')"
                    class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300 whitespace-nowrap"
                >← 一覧に戻る</Link>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ team.name }}</h2>
            </div>
        </template>

        <!-- スティッキータブバー -->
        <div class="sticky top-0 z-20 rounded-t bg-white px-6 pt-4 pb-0 shadow-md">
            <div class="flex gap-1 border-b border-gray-200">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    type="button"
                    :class="[
                        'px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors',
                        activeTab === tab.key
                            ? 'border-indigo-500 text-indigo-700'
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                    ]"
                    @click="activeTab = tab.key"
                >{{ tab.label }}</button>
            </div>
        </div>

        <!-- タブコンテンツ -->
        <div :class="['rounded-b px-6 pb-6 shadow-md', activeTab !== 'board' ? 'bg-white' : '']">

            <!-- ── 概要・メンバー ── -->
            <section v-show="activeTab === 'overview'" class="py-5">
                <!-- チーム概要 -->
                <div class="mb-6">
                    <h3 class="mb-2 font-semibold text-gray-800">チーム概要</h3>
                    <dl class="space-y-2 text-sm">
                        <div class="flex gap-2">
                            <dt class="w-24 shrink-0 text-xs font-semibold text-gray-500">チーム名</dt>
                            <dd class="text-gray-800">{{ team.name }}</dd>
                        </div>
                        <div v-if="team.department" class="flex gap-2">
                            <dt class="w-24 shrink-0 text-xs font-semibold text-gray-500">部署</dt>
                            <dd class="text-gray-800">{{ team.department.name }}</dd>
                        </div>
                        <div v-if="team.description" class="flex gap-2">
                            <dt class="w-24 shrink-0 text-xs font-semibold text-gray-500">説明</dt>
                            <dd class="whitespace-pre-wrap text-gray-800">{{ team.description }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- メンバー一覧 -->
                <div>
                    <h3 class="mb-3 font-semibold text-gray-800">メンバー</h3>
                    <div class="space-y-2">
                        <!-- リーダー -->
                        <div v-if="leader" class="flex items-center gap-2 text-sm">
                            <span class="w-24 shrink-0 text-xs font-semibold text-orange-700">リーダー</span>
                            <span class="flex items-center gap-1.5 rounded-full border border-orange-200 bg-orange-50 px-3 py-1 text-sm font-medium text-gray-800">
                                <span class="inline-block h-2 w-2 rounded-full bg-orange-400"></span>
                                {{ leader.name }}
                            </span>
                        </div>

                        <!-- サブリーダー -->
                        <div v-if="team.sub_leaders && team.sub_leaders.length > 0" class="flex items-start gap-2 text-sm">
                            <span class="w-24 shrink-0 text-xs font-semibold text-yellow-700">サブリーダー</span>
                            <div class="flex flex-wrap gap-2">
                                <span
                                    v-for="sl in team.sub_leaders"
                                    :key="sl.id"
                                    class="flex items-center gap-1.5 rounded-full border border-yellow-200 bg-yellow-50 px-3 py-1 text-sm font-medium text-gray-800"
                                >
                                    <span class="inline-block h-2 w-2 rounded-full bg-yellow-400"></span>
                                    {{ sl.name }}
                                </span>
                            </div>
                        </div>

                        <!-- メンバー -->
                        <div v-if="team.members && team.members.length > 0" class="flex items-start gap-2 text-sm">
                            <span class="w-24 shrink-0 text-xs font-semibold text-blue-700">メンバー</span>
                            <div class="flex flex-wrap gap-2">
                                <span
                                    v-for="m in team.members"
                                    :key="m.id"
                                    :class="[
                                        'flex items-center gap-1.5 rounded-full border px-3 py-1 text-sm font-medium text-gray-800',
                                        roleColor[m.user_role] || 'bg-gray-50 border-gray-200',
                                    ]"
                                >
                                    <span class="inline-block h-2 w-2 rounded-full bg-blue-400"></span>
                                    {{ m.name }}
                                </span>
                            </div>
                        </div>

                        <p v-if="!leader && (!team.members || team.members.length === 0)" class="text-sm text-gray-400">
                            メンバー未登録
                        </p>
                    </div>
                </div>
            </section>

            <!-- ── スケジュール ── -->
            <section v-show="activeTab === 'schedule'" class="py-5">
                <TeamScheduleCalendar
                    ref="scheduleRef"
                    :team-id="team.id"
                    :auth-user-id="authUser?.id"
                />
            </section>

            <!-- ── プロジェクトボード ── -->
            <section v-show="activeTab === 'board'" class="py-5">
                <TeamBoard
                    :team="team"
                    :board="board"
                    @board-created="onBoardCreated"
                    @board-updated="onBoardUpdated"
                />
            </section>

            <!-- ── 会議記録 ── -->
            <section v-show="activeTab === 'minutes'" class="py-5">
                <TeamMinutesList
                    :team="team"
                    :minutes="minutes"
                    :recent-minutes="recentMinutes"
                    :auth-user-id="authUser?.id"
                    :team-leader-id="team.leader_id"
                />
            </section>

        </div>
    </AppLayout>
</template>
