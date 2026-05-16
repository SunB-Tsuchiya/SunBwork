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

                <!-- 部署フィルター（Admin のみ・各部署のみ） -->
                <div v-if="isAdmin && departments.length > 0" class="mb-3 flex flex-wrap gap-2">
                    <button
                        v-for="d in departments"
                        :key="d.id"
                        type="button"
                        @click="selectedDept = d.id"
                        class="rounded-full px-3 py-1 text-xs font-medium transition-colors"
                        :class="selectedDept === d.id ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                    >{{ d.name }}</button>
                </div>

                <!-- ソートボタン -->
                <div class="mb-4 flex flex-wrap items-center gap-2">
                    <span class="text-xs text-gray-400 shrink-0">並び替え：</span>
                    <button
                        v-for="s in SORT_OPTIONS"
                        :key="s.key"
                        type="button"
                        class="rounded-full px-3 py-1 text-xs font-medium border transition-colors"
                        :class="currentSort === s.key
                            ? 'border-indigo-400 bg-indigo-50 text-indigo-700'
                            : 'border-gray-200 bg-white text-gray-500 hover:bg-gray-50'"
                        @click="applySort(s.key)"
                    >{{ s.label }}</button>
                </div>

                <!-- ユーザーテーブル -->
                <div class="overflow-x-auto">
                    <table class="text-sm w-full">
                        <thead>
                            <tr class="border-b border-gray-200 text-left text-xs font-medium text-gray-500">
                                <th class="pb-2 pr-2 w-20">順序</th>
                                <th class="pb-2 pr-3 w-32">名前</th>
                                <th class="pb-2 pr-3 w-24">役職</th>
                                <th class="pb-2 pr-3 w-24">雇用形態</th>
                                <th class="pb-2 w-16 text-center">ボード表示</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr
                                v-for="(item, visIdx) in visibleUsers"
                                :key="item.id"
                                :class="item.is_hidden ? 'bg-gray-50' : ''"
                            >
                                <td class="py-1.5 pr-2 w-20">
                                    <div class="flex items-center gap-1">
                                        <span class="w-5 text-xs text-gray-400">{{ visIdx + 1 }}</span>
                                        <button type="button" :disabled="visIdx === 0" class="rounded p-0.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700 disabled:opacity-30" @click="moveUserUp(visIdx)">▲</button>
                                        <button type="button" :disabled="visIdx === visibleUsers.length - 1" class="rounded p-0.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700 disabled:opacity-30" @click="moveUserDown(visIdx)">▼</button>
                                    </div>
                                </td>
                                <td class="py-1.5 pr-3 w-32 font-medium" :class="item.is_hidden ? 'line-through text-gray-400' : 'text-gray-800'">
                                    {{ item.name }}
                                </td>
                                <td class="py-1.5 pr-3 w-24 text-xs text-gray-500">
                                    {{ item.position_title ?? '―' }}
                                </td>
                                <td class="py-1.5 pr-3 w-24 text-xs text-gray-500">
                                    {{ employmentLabel(item.employment_type) }}
                                </td>
                                <td class="py-1.5 w-16 text-center">
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
                    ステータスボタンの表示順・表示/非表示・名前・色を設定できます。✏️ で編集、＋ で新規追加できます。
                </p>

                <div class="overflow-x-auto flex justify-center">
                    <table class="text-sm w-full max-w-lg">
                        <thead>
                            <tr class="border-b border-gray-200 text-left text-xs font-medium text-gray-500">
                                <th class="pb-2 pr-2 w-20">順序</th>
                                <th class="pb-2 pr-2">ステータス</th>
                                <th class="pb-2 w-16 text-center">表示</th>
                                <th class="pb-2 w-16 text-center">操作</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <template v-for="(item, idx) in localStatuses" :key="item.slug">
                                <!-- 通常行 -->
                                <tr v-if="editingIdx !== idx" :class="item.is_active ? '' : 'bg-gray-50'">
                                    <td class="py-2 pr-2 w-20">
                                        <div class="flex items-center gap-1">
                                            <span class="w-5 text-xs text-gray-400">{{ idx + 1 }}</span>
                                            <button type="button" :disabled="idx === 0" class="rounded p-0.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700 disabled:opacity-30" @click="moveStatusUp(idx)">▲</button>
                                            <button type="button" :disabled="idx === localStatuses.length - 1" class="rounded p-0.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700 disabled:opacity-30" @click="moveStatusDown(idx)">▼</button>
                                        </div>
                                    </td>
                                    <td class="py-2 pr-2">
                                        <div class="flex items-center gap-2">
                                            <span class="h-2.5 w-2.5 rounded-full shrink-0" :class="resolvedStatus(item).dot" />
                                            <span class="font-medium" :class="item.is_active ? 'text-gray-800' : 'line-through text-gray-400'">
                                                {{ resolvedStatus(item).label }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="py-2 w-16 text-center">
                                        <button type="button"
                                            class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200"
                                            :class="item.is_active ? 'bg-blue-500' : 'bg-gray-300'"
                                            @click="toggleStatus(idx)"
                                        >
                                            <span class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200" :class="item.is_active ? 'translate-x-4' : 'translate-x-0'" />
                                        </button>
                                    </td>
                                    <td class="py-2 w-16 text-center">
                                        <div class="flex items-center justify-center gap-1">
                                            <button type="button" class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-blue-600" title="編集" @click="startEdit(idx)">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                            </button>
                                            <button v-if="isCustom(item)" type="button" class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-red-500" title="削除" @click="deleteStatus(item, idx)">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                <!-- 編集行 -->
                                <tr v-else class="bg-blue-50">
                                    <td class="py-2 pr-2 w-20">
                                        <span class="ml-1 text-xs text-blue-400">{{ idx + 1 }}</span>
                                    </td>
                                    <td class="py-2 pr-2" colspan="3">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <input
                                                v-model="editLabel"
                                                type="text"
                                                maxlength="50"
                                                class="w-32 rounded border border-blue-300 px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-blue-400"
                                                placeholder="名前"
                                            />
                                            <!-- カラーピッカー -->
                                            <div class="flex flex-wrap gap-1">
                                                <button
                                                    v-for="c in COLOR_OPTIONS"
                                                    :key="c.key"
                                                    type="button"
                                                    :title="c.label"
                                                    class="h-5 w-5 rounded-full border-2 transition-transform hover:scale-110"
                                                    :class="[swatchBg(c.key), editColor === c.key ? 'border-gray-700 scale-110' : 'border-transparent']"
                                                    @click="editColor = c.key"
                                                />
                                            </div>
                                            <div class="flex gap-1">
                                                <button type="button" class="rounded bg-blue-500 px-2 py-1 text-xs font-medium text-white hover:bg-blue-600" @click="applyEdit(idx)">決定</button>
                                                <button type="button" class="rounded bg-gray-100 px-2 py-1 text-xs font-medium text-gray-600 hover:bg-gray-200" @click="cancelEdit">取消</button>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <!-- 新規追加フォーム -->
                <div class="mt-4 flex justify-center">
                    <div class="w-full max-w-lg">
                        <div v-if="!showAddForm">
                            <button type="button" class="flex items-center gap-1.5 rounded-lg border border-dashed border-gray-300 px-4 py-2 text-sm text-gray-500 hover:border-blue-400 hover:text-blue-600 w-full justify-center" @click="openAddForm">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                                ステータスを追加
                            </button>
                        </div>
                        <div v-else class="rounded-lg border border-blue-200 bg-blue-50 p-3">
                            <p class="mb-2 text-xs font-medium text-blue-700">新しいステータスを追加</p>
                            <div class="flex flex-wrap items-center gap-2">
                                <input
                                    v-model="newLabel"
                                    type="text"
                                    maxlength="50"
                                    class="w-36 rounded border border-blue-300 px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-400"
                                    placeholder="ステータス名"
                                />
                                <!-- カラーピッカー -->
                                <div class="flex flex-wrap gap-1">
                                    <button
                                        v-for="c in COLOR_OPTIONS"
                                        :key="c.key"
                                        type="button"
                                        :title="c.label"
                                        class="h-5 w-5 rounded-full border-2 transition-transform hover:scale-110"
                                        :class="[swatchBg(c.key), newColor === c.key ? 'border-gray-700 scale-110' : 'border-transparent']"
                                        @click="newColor = c.key"
                                    />
                                </div>
                                <!-- プレビュー -->
                                <span v-if="newLabel" class="rounded-lg px-2 py-1.5 text-xs font-medium" :class="[previewBtnBg, previewBtnText]">
                                    {{ newLabel }}
                                </span>
                            </div>
                            <div class="mt-2 flex gap-2">
                                <button type="button" class="rounded bg-blue-500 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-600 disabled:opacity-50" :disabled="!newLabel.trim() || addingStatus" @click="addStatus">
                                    {{ addingStatus ? '追加中…' : '追加する' }}
                                </button>
                                <button type="button" class="rounded bg-gray-100 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-200" @click="closeAddForm">キャンセル</button>
                            </div>
                        </div>
                    </div>
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
import { resolveStatus, COLOR_OPTIONS, STATUS_GROUPS } from '@/Components/Iruka/statusConfig.js';

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
// 部署フィルター：全部署なし。初期値は最初の部署
const selectedDept = ref(props.departments[0]?.id ?? null);
const savingUsers  = ref(false);
const savedUsers   = ref(false);
const currentSort  = ref(null); // null = 手動順

const SORT_OPTIONS = [
    { key: 'position',   label: '役職順' },
    { key: 'employment', label: '雇用形態順' },
    { key: 'name',       label: '名前順' },
];

const EMPLOYMENT_PRIORITY = {
    regular: 1, contract: 2, dispatch: 3, outsource: 4,
};
const EMPLOYMENT_LABELS = {
    regular: '正社員', contract: '契約社員', dispatch: '派遣社員', outsource: '業務委託',
};

function employmentLabel(type) {
    return EMPLOYMENT_LABELS[type] ?? type ?? '';
}

function applySort(key) {
    currentSort.value = key;
    savedUsers.value  = false;
    const arr = [...localUsers.value];
    if (key === 'position') {
        arr.sort((a, b) => {
            const pa = a.position_sort_order ?? 9999;
            const pb = b.position_sort_order ?? 9999;
            if (pa !== pb) return pa - pb;
            return a.name.localeCompare(b.name, 'ja');
        });
    } else if (key === 'employment') {
        arr.sort((a, b) => {
            const ea = EMPLOYMENT_PRIORITY[a.employment_type] ?? 99;
            const eb = EMPLOYMENT_PRIORITY[b.employment_type] ?? 99;
            if (ea !== eb) return ea - eb;
            return a.name.localeCompare(b.name, 'ja');
        });
    } else if (key === 'name') {
        arr.sort((a, b) => a.name.localeCompare(b.name, 'ja'));
    }
    localUsers.value = arr;
}

const visibleUsers = computed(() => {
    if (!selectedDept.value) return localUsers.value;
    // department_id が null のユーザー（部署未設定）は全タブに表示
    return localUsers.value.filter(u =>
        u.department_id === selectedDept.value || !u.department_id
    );
});

function toggleUser(userId) {
    const idx = localUsers.value.findIndex(u => u.id === userId);
    if (idx === -1) return;
    const item = localUsers.value[idx];
    item.is_hidden = !item.is_hidden;
    savedUsers.value = false;
    if (item.is_hidden) {
        // 非表示にしたら末尾へ自動移動
        localUsers.value.splice(idx, 1);
        localUsers.value.push(item);
    }
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
const localStatuses  = ref(props.statusOrders.map(s => ({ ...s })));
const savingStatuses = ref(false);
const savedStatuses  = ref(false);

// 行単位編集
const editingIdx = ref(null);
const editLabel  = ref('');
const editColor  = ref('gray');

// 新規追加フォーム
const showAddForm  = ref(false);
const newLabel     = ref('');
const newColor     = ref('blue');
const addingStatus = ref(false);

function resolvedStatus(item) {
    return resolveStatus(item);
}

function isCustom(item) {
    return item.slug.startsWith('cust_');
}

function swatchBg(colorKey) {
    return STATUS_GROUPS[colorKey]?.badge ?? 'bg-gray-400';
}

const previewBtnBg   = computed(() => STATUS_GROUPS[newColor.value]?.btnBg   ?? 'bg-gray-100');
const previewBtnText = computed(() => STATUS_GROUPS[newColor.value]?.btnText  ?? 'text-gray-600');

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

function startEdit(idx) {
    editingIdx.value = idx;
    const item = localStatuses.value[idx];
    editLabel.value = item.custom_label ?? resolveStatus(item).label;
    editColor.value = item.custom_color  ?? resolveStatus(item).group;
}

function applyEdit(idx) {
    const item = localStatuses.value[idx];
    const resolved = resolveStatus(item);
    item.custom_label = editLabel.value.trim() === resolved.label && !isCustom(item) ? null : editLabel.value.trim() || null;
    item.custom_color = editColor.value === resolved.group && !isCustom(item) ? null : editColor.value;
    editingIdx.value = null;
    savedStatuses.value = false;
}

function cancelEdit() {
    editingIdx.value = null;
}

function openAddForm() {
    showAddForm.value = true;
    newLabel.value = '';
    newColor.value = 'blue';
}

function closeAddForm() {
    showAddForm.value = false;
}

async function addStatus() {
    if (!newLabel.value.trim()) return;
    addingStatus.value = true;
    try {
        const res = await window.axios.post(route('presence.board_settings.statuses.create'), {
            custom_label: newLabel.value.trim(),
            custom_color: newColor.value,
        });
        localStatuses.value.push({
            id:           res.data.id,
            slug:         res.data.slug,
            sort_order:   res.data.sort_order,
            is_active:    res.data.is_active,
            custom_label: res.data.custom_label,
            custom_color: res.data.custom_color,
        });
        closeAddForm();
        window.dispatchEvent(new CustomEvent('iruka:refresh'));
    } catch (_) {
        alert('追加に失敗しました。');
    } finally {
        addingStatus.value = false;
    }
}

async function deleteStatus(item, idx) {
    if (!confirm(`「${resolveStatus(item).label}」を削除しますか？`)) return;
    try {
        await window.axios.delete(route('presence.board_settings.statuses.delete', { statusOrder: item.id }));
        localStatuses.value.splice(idx, 1);
        window.dispatchEvent(new CustomEvent('iruka:refresh'));
    } catch (_) {
        alert('削除に失敗しました。');
    }
}

async function saveStatuses() {
    savingStatuses.value = true;
    savedStatuses.value  = false;
    try {
        const items = localStatuses.value.map((s, i) => ({
            slug:         s.slug,
            sort_order:   i,
            is_active:    s.is_active,
            custom_label: s.custom_label ?? null,
            custom_color: s.custom_color ?? null,
        }));
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
