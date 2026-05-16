<template>
    <AppLayout title="在席ボード管理">
        <template #header>
            <div class="flex items-center gap-3">
                <Link
                    :href="route('dashboard')"
                    class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300"
                >← ダッシュボードに戻る</Link>
                <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">🐬 在席ボード管理</h2>
            </div>
        </template>

        <div class="mx-auto max-w-2xl rounded bg-white p-6 shadow">

            <!-- タブ切替 -->
            <div class="mb-6 flex gap-6 border-b border-gray-200">
                <button
                    type="button"
                    class="pb-3 text-sm font-medium border-b-2 transition-colors -mb-px"
                    :class="activeTab === 'users' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                    @click="activeTab = 'users'"
                >ユーザー設定</button>
                <button
                    type="button"
                    class="pb-3 text-sm font-medium border-b-2 transition-colors -mb-px"
                    :class="activeTab === 'statuses' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                    @click="activeTab = 'statuses'"
                >ステータス設定</button>
            </div>

            <!-- ===== ユーザー設定タブ ===== -->
            <div v-show="activeTab === 'users'">
                <p class="mb-4 text-sm text-gray-500">
                    表示順の変更・ボードへの表示/非表示を設定できます。非表示にしたユーザーは在席ボードに表示されません。
                </p>

                <!-- 部署フィルター（Admin のみ） -->
                <div v-if="isAdmin && departments.length > 0" class="mb-4 flex flex-wrap gap-2">
                    <button
                        type="button"
                        @click="selectedDept = null"
                        class="rounded-full px-3 py-1 text-xs font-medium transition-colors"
                        :class="selectedDept === null ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                    >全部署</button>
                    <button
                        v-for="d in departments"
                        :key="d.id"
                        type="button"
                        @click="selectedDept = d.id"
                        class="rounded-full px-3 py-1 text-xs font-medium transition-colors"
                        :class="selectedDept === d.id ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                    >{{ d.name }}</button>
                </div>

                <!-- ユーザーテーブル -->
                <div class="overflow-x-auto flex justify-center">
                    <table class="text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-left text-xs font-medium text-gray-500">
                                <th class="pb-2 pr-2 w-24">順序</th>
                                <th class="pb-2 pr-6 w-48">名前</th>
                                <th class="pb-2 w-20">ボード表示</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr
                                v-for="(item, visIdx) in visibleUsers"
                                :key="item.id"
                                :class="item.is_hidden ? 'bg-gray-50' : ''"
                            >
                                <td class="py-2 pr-2 w-24">
                                    <div class="flex items-center gap-1">
                                        <span class="w-5 text-xs text-gray-400">{{ visIdx + 1 }}</span>
                                        <button type="button" :disabled="visIdx === 0" class="rounded p-0.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700 disabled:opacity-30" @click="moveUserUp(visIdx)">▲</button>
                                        <button type="button" :disabled="visIdx === visibleUsers.length - 1" class="rounded p-0.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700 disabled:opacity-30" @click="moveUserDown(visIdx)">▼</button>
                                    </div>
                                </td>
                                <td class="py-2 pr-6 w-48 font-medium" :class="item.is_hidden ? 'line-through text-gray-400' : 'text-gray-800'">
                                    {{ item.name }}
                                </td>
                                <td class="py-2 w-20">
                                    <button type="button"
                                        class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200"
                                        :class="item.is_hidden ? 'bg-gray-300' : 'bg-blue-500'"
                                        @click="toggleUser(item.id)"
                                    >
                                        <span class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200" :class="item.is_hidden ? 'translate-x-0' : 'translate-x-4'" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-6 flex items-center justify-end gap-3">
                    <span v-if="savedUsers" class="mr-auto text-sm text-green-600">✓ 保存しました</span>
                    <Link :href="route('dashboard')" class="rounded bg-gray-100 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-200">キャンセル</Link>
                    <button type="button" class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50" :disabled="savingUsers" @click="saveUsers">
                        {{ savingUsers ? '保存中…' : '保存する' }}
                    </button>
                </div>
            </div>

            <!-- ===== ステータス設定タブ ===== -->
            <div v-show="activeTab === 'statuses'">
                <p class="mb-4 text-sm text-gray-500">
                    ステータスボタンの表示順・表示/非表示を設定できます。非表示にしたステータスはモーダルに表示されません。
                </p>

                <div class="overflow-x-auto flex justify-center">
                    <table class="text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-left text-xs font-medium text-gray-500">
                                <th class="pb-2 pr-2 w-24">順序</th>
                                <th class="pb-2 pr-6 w-48">ステータス</th>
                                <th class="pb-2 w-20">表示</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="(item, idx) in localStatuses" :key="item.slug" :class="item.is_active ? '' : 'bg-gray-50'">
                                <td class="py-2 pr-2 w-24">
                                    <div class="flex items-center gap-1">
                                        <span class="w-5 text-xs text-gray-400">{{ idx + 1 }}</span>
                                        <button type="button" :disabled="idx === 0" class="rounded p-0.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700 disabled:opacity-30" @click="moveStatusUp(idx)">▲</button>
                                        <button type="button" :disabled="idx === localStatuses.length - 1" class="rounded p-0.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700 disabled:opacity-30" @click="moveStatusDown(idx)">▼</button>
                                    </div>
                                </td>
                                <td class="py-2 pr-6 w-48">
                                    <div class="flex items-center gap-2">
                                        <span class="h-2.5 w-2.5 rounded-full shrink-0" :class="statusDef(item.slug).dot" />
                                        <span class="font-medium" :class="item.is_active ? 'text-gray-800' : 'line-through text-gray-400'">
                                            {{ statusDef(item.slug).label }}
                                        </span>
                                    </div>
                                </td>
                                <td class="py-2 w-20">
                                    <button type="button"
                                        class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200"
                                        :class="item.is_active ? 'bg-blue-500' : 'bg-gray-300'"
                                        @click="toggleStatus(idx)"
                                    >
                                        <span class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200" :class="item.is_active ? 'translate-x-4' : 'translate-x-0'" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-6 flex items-center justify-end gap-3">
                    <span v-if="savedStatuses" class="mr-auto text-sm text-green-600">✓ 保存しました</span>
                    <button type="button" class="rounded bg-gray-100 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-200" @click="activeTab = 'users'">キャンセル</button>
                    <button type="button" class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50" :disabled="savingStatuses" @click="saveStatuses">
                        {{ savingStatuses ? '保存中…' : '保存する' }}
                    </button>
                </div>
            </div>

        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import { getStatus } from '@/Components/Iruka/statusConfig.js';

const props = defineProps({
    users:        { type: Array,   default: () => [] },
    departments:  { type: Array,   default: () => [] },
    isAdmin:      { type: Boolean, default: false },
    statusOrders: { type: Array,   default: () => [] },
});

// ===== タブ =====
const activeTab = ref('users');

// ===== ユーザー設定 =====
const localUsers   = ref(props.users.map(u => ({ ...u })));
const selectedDept = ref(null);
const savingUsers  = ref(false);
const savedUsers   = ref(false);

const visibleUsers = computed(() => {
    if (!selectedDept.value) return localUsers.value;
    return localUsers.value.filter(u => u.department_id === selectedDept.value);
});

function toggleUser(userId) {
    const item = localUsers.value.find(u => u.id === userId);
    if (item) { item.is_hidden = !item.is_hidden; savedUsers.value = false; }
}

function moveUserUp(visIdx) {
    if (visIdx === 0) return;
    const vis = visibleUsers.value;
    const ia = localUsers.value.findIndex(u => u.id === vis[visIdx - 1].id);
    const ib = localUsers.value.findIndex(u => u.id === vis[visIdx].id);
    [localUsers.value[ia], localUsers.value[ib]] = [localUsers.value[ib], localUsers.value[ia]];
    savedUsers.value = false;
}

function moveUserDown(visIdx) {
    const vis = visibleUsers.value;
    if (visIdx >= vis.length - 1) return;
    const ia = localUsers.value.findIndex(u => u.id === vis[visIdx].id);
    const ib = localUsers.value.findIndex(u => u.id === vis[visIdx + 1].id);
    [localUsers.value[ia], localUsers.value[ib]] = [localUsers.value[ib], localUsers.value[ia]];
    savedUsers.value = false;
}

async function saveUsers() {
    savingUsers.value = true;
    savedUsers.value  = false;
    try {
        const items = localUsers.value.map((u, i) => ({ user_id: u.id, sort_order: i, is_hidden: u.is_hidden }));
        await window.axios.post(route('presence.board_settings.update'), { items });
        savedUsers.value = true;
        window.dispatchEvent(new CustomEvent('iruka:refresh'));
    } catch (_) {
        alert('保存に失敗しました。再度お試しください。');
    } finally {
        savingUsers.value = false;
    }
}

// ===== ステータス設定 =====
const localStatuses   = ref(props.statusOrders.map(s => ({ ...s })));
const savingStatuses  = ref(false);
const savedStatuses   = ref(false);

function statusDef(slug) {
    return getStatus(slug);
}

function toggleStatus(idx) {
    localStatuses.value[idx].is_active = !localStatuses.value[idx].is_active;
    savedStatuses.value = false;
}

function moveStatusUp(idx) {
    if (idx === 0) return;
    const arr = localStatuses.value;
    [arr[idx - 1], arr[idx]] = [arr[idx], arr[idx - 1]];
    savedStatuses.value = false;
}

function moveStatusDown(idx) {
    const arr = localStatuses.value;
    if (idx >= arr.length - 1) return;
    [arr[idx], arr[idx + 1]] = [arr[idx + 1], arr[idx]];
    savedStatuses.value = false;
}

async function saveStatuses() {
    savingStatuses.value = true;
    savedStatuses.value  = false;
    try {
        const items = localStatuses.value.map((s, i) => ({ slug: s.slug, sort_order: i, is_active: s.is_active }));
        await window.axios.post(route('presence.board_settings.statuses'), { items });
        savedStatuses.value = true;
        window.dispatchEvent(new CustomEvent('iruka:refresh'));
    } catch (_) {
        alert('保存に失敗しました。再度お試しください。');
    } finally {
        savingStatuses.value = false;
    }
}
</script>
