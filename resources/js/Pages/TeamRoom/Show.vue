<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { ref, watch, nextTick, computed } from 'vue';
import { route } from 'ziggy-js';
import TeamScheduleCalendar from '@/Components/TeamRoom/TeamScheduleCalendar.vue';
import TeamBoard from '@/Components/TeamRoom/TeamBoard.vue';
import TeamMinutesList from '@/Components/TeamRoom/TeamMinutesList.vue';
import TeamMemoBoard from '@/Components/TeamRoom/TeamMemoBoard.vue';

const props = defineProps({
    team:          { type: Object, required: true },
    leader:        { type: Object, default: null },
    board:         { type: Object, default: null },
    recentMinutes: { type: Array, default: () => [] },
    activeTab:     { type: String, default: 'overview' },
    // minutesタブ用（minutes.index から渡される場合）
    minutes:       { type: Array, default: null },
    dutyTables:    { type: Array, default: () => [] },
});

const tabs = [
    { key: 'overview',  label: '概要・メンバー' },
    { key: 'schedule',  label: 'スケジュール' },
    { key: 'board',     label: 'プロジェクトボード' },
    { key: 'minutes',   label: '会議記録' },
    { key: 'duty',      label: '係・当番' },
    { key: 'memo',      label: 'メモ・連絡' },
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

// リーダー・サブリーダーを上位ロールから除外して重複表示を防ぐ
const filteredSubLeaders = computed(() =>
    (props.team.sub_leaders ?? []).filter(sl => sl.id !== props.leader?.id)
);
const filteredMembers = computed(() => {
    const excludeIds = new Set(
        [props.leader?.id, ...(props.team.sub_leaders ?? []).map(sl => sl.id)].filter(Boolean)
    );
    return (props.team.members ?? []).filter(m => !excludeIds.has(m.id));
});

// ボードの状態（Show.vueで管理してコンポーネントに渡す）
const board = ref(props.board);

function confirmDelete(dt) {
    if (!confirm(`「${dt.title}」を削除しますか？`)) return;
    router.delete(route('team-rooms.duty-tables.destroy', { team: props.team.id, dutyTable: dt.id }));
}

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
                        <div v-if="filteredSubLeaders.length > 0" class="flex items-start gap-2 text-sm">
                            <span class="w-24 shrink-0 text-xs font-semibold text-yellow-700">サブリーダー</span>
                            <div class="flex flex-wrap gap-2">
                                <span
                                    v-for="sl in filteredSubLeaders"
                                    :key="sl.id"
                                    class="flex items-center gap-1.5 rounded-full border border-yellow-200 bg-yellow-50 px-3 py-1 text-sm font-medium text-gray-800"
                                >
                                    <span class="inline-block h-2 w-2 rounded-full bg-yellow-400"></span>
                                    {{ sl.name }}
                                </span>
                            </div>
                        </div>

                        <!-- メンバー -->
                        <div v-if="filteredMembers.length > 0" class="flex items-start gap-2 text-sm">
                            <span class="w-24 shrink-0 text-xs font-semibold text-blue-700">メンバー</span>
                            <div class="flex flex-wrap gap-2">
                                <span
                                    v-for="m in filteredMembers"
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

                        <p v-if="!leader && filteredSubLeaders.length === 0 && filteredMembers.length === 0" class="text-sm text-gray-400">
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

            <!-- ── 係・当番 ── -->
            <section v-show="activeTab === 'duty'" class="py-5">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="font-semibold text-gray-800">係・当番表</h3>
                    <Link
                        :href="route('team-rooms.duty-tables.create', { team: team.id })"
                        class="rounded bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700"
                    >＋ 新規登録</Link>
                </div>

                <div v-if="dutyTables.length === 0" class="py-10 text-center text-sm text-gray-400">
                    係・当番表が登録されていません
                </div>

                <div v-else class="space-y-6">
                    <div
                        v-for="dt in dutyTables"
                        :key="dt.id"
                        class="rounded border border-gray-200 bg-white shadow-sm"
                    >
                        <!-- タイトル行 -->
                        <div class="flex items-center justify-between border-b border-gray-100 bg-gray-50 px-4 py-2">
                            <div>
                                <span class="font-semibold text-gray-800 text-sm">{{ dt.title }}</span>
                                <span v-if="dt.description" class="ml-3 text-xs text-gray-500">{{ dt.description }}</span>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <Link
                                    :href="route('team-rooms.duty-tables.create', { team: team.id })"
                                    class="rounded border border-indigo-300 px-2 py-1 text-xs font-medium text-indigo-600 hover:bg-indigo-50"
                                >再読み込み</Link>
                                <Link
                                    :href="route('team-rooms.duty-tables.destroy', { team: team.id, dutyTable: dt.id })"
                                    method="delete"
                                    as="button"
                                    class="rounded border border-red-300 px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50"
                                    @click.prevent="confirmDelete(dt)"
                                >削除</Link>
                            </div>
                        </div>
                        <!-- テーブル本体 -->
                        <div class="overflow-x-auto p-3 duty-table-content" v-html="dt.html_content"></div>
                    </div>
                </div>
            </section>

            <!-- ── メモ・連絡 ── -->
            <section v-show="activeTab === 'memo'" class="py-5">
                <TeamMemoBoard
                    :team-id="team.id"
                    :auth-user-id="authUser?.id"
                    :is-super-admin="authUser?.user_role === 'superadmin'"
                />
            </section>

        </div>
    </AppLayout>
</template>

<style>
.duty-table-content table {
    border-collapse: collapse;
    width: 100%;
    font-size: 0.85rem;
}
.duty-table-content th,
.duty-table-content td {
    border: 1px solid #d1d5db;
    padding: 6px 10px;
    text-align: left;
    white-space: nowrap;
}
.duty-table-content th {
    background-color: #f3f4f6;
    font-weight: 600;
}
.duty-table-content tr:nth-child(even) td {
    background-color: #f9fafb;
}
</style>
