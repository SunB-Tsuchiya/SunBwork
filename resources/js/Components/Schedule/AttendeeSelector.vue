<script setup>
import { ref, watch, computed } from 'vue';
import axios from 'axios';

const props = defineProps({
    eventId:     { type: Number, default: null },
    attendees:   { type: Array,  default: () => [] }, // [{id, name}]
    selfId:      { type: Number, default: null },
    companies:   { type: Array,  default: () => [] }, // [{id, name}]
    departments: { type: Array,  default: () => [] }, // [{id, name, company_id}]
});

const emit = defineEmits(['change']);

const CSRF = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

// ── 現在の参加者ローカルコピー ────────────────────────────────────
const local = ref(props.attendees.map(a => ({ ...a })));
watch(() => props.attendees, (v) => { local.value = v.map(a => ({ ...a })); }, { deep: true });

// ── ピッカーモーダル状態 ─────────────────────────────────────────
const showPicker   = ref(false);
const allUsers     = ref([]);
const loadingUsers = ref(false);
const filterCoId   = ref('');   // 選択中の会社ID
const filterDeptId = ref('');   // 選択中の部署ID ('__all__' = 会社内全部署)
const pickerSel    = ref(new Set());
const applying     = ref(false);

// 選択会社の部署一覧
const companyDepts = computed(() => {
    if (!filterCoId.value) return [];
    return props.departments.filter(d => String(d.company_id) === String(filterCoId.value));
});

// 表示するユーザー（クライアント側フィルタリング）
const filteredUsers = computed(() => {
    if (!filterCoId.value) return [];   // 会社未選択は何も表示しない
    return allUsers.value.filter(u => {
        if (String(u.company_id) !== String(filterCoId.value)) return false;
        if (filterDeptId.value === '__all__') return true;
        if (filterDeptId.value) return String(u.department_id) === String(filterDeptId.value);
        return true;
    });
});

const selectedCount = computed(() => pickerSel.value.size);

const allChecked = computed(() =>
    filteredUsers.value.length > 0 &&
    filteredUsers.value.every(u => pickerSel.value.has(u.id))
);

watch(filterCoId, () => { filterDeptId.value = ''; });

async function openPicker() {
    filterCoId.value   = '';
    filterDeptId.value = '';
    showPicker.value   = true;

    if (!allUsers.value.length) {
        loadingUsers.value = true;
        try {
            const res = await axios.get(route('schedule.users.search'));
            allUsers.value = res.data;
        } finally {
            loadingUsers.value = false;
        }
    }

    // 現在の参加者のうちピッカー管理対象を初期選択
    const inPicker = new Set(allUsers.value.map(u => u.id));
    pickerSel.value = new Set(local.value.filter(a => inPicker.has(a.id)).map(a => a.id));
}

function toggleUser(id) {
    const s = new Set(pickerSel.value);
    if (s.has(id)) { s.delete(id); } else { s.add(id); }
    pickerSel.value = s;
}

function toggleAll() {
    const s = new Set(pickerSel.value);
    if (allChecked.value) {
        filteredUsers.value.forEach(u => s.delete(u.id));
    } else {
        filteredUsers.value.forEach(u => s.add(u.id));
    }
    pickerSel.value = s;
}

async function applySelection() {
    applying.value = true;

    const inPicker        = new Set(allUsers.value.map(u => u.id));
    const currentInPicker = new Set(local.value.filter(a => inPicker.has(a.id)).map(a => a.id));
    const toAdd    = [...pickerSel.value].filter(id => !currentInPicker.has(id));
    const toRemove = [...currentInPicker].filter(id => !pickerSel.value.has(id));

    try {
        if (props.eventId != null) {
            await Promise.all([
                ...toAdd.map(uid => axios.post(
                    route('schedule.attendees.store', { event: props.eventId }),
                    { user_id: uid },
                    { headers: { 'X-CSRF-TOKEN': CSRF() } }
                )),
                ...toRemove.map(uid => axios.delete(
                    route('schedule.attendees.destroy', { event: props.eventId, user: uid }),
                    { headers: { 'X-CSRF-TOKEN': CSRF() } }
                )),
            ]);
        }

        const userMap   = new Map(allUsers.value.map(u => [u.id, u]));
        const unmanaged = local.value.filter(a => !inPicker.has(a.id));
        const managed   = [...pickerSel.value].map(uid => {
            const existing = local.value.find(a => a.id === uid);
            if (existing) return existing;
            const u = userMap.get(uid);
            return u ? { id: u.id, name: u.name } : null;
        }).filter(Boolean);

        local.value = [...unmanaged, ...managed];
        emit('change', local.value);
        showPicker.value = false;
    } catch {
        alert('参加者の更新に失敗しました');
    } finally {
        applying.value = false;
    }
}

async function removeChip(id) {
    if (props.eventId != null) {
        try {
            await axios.delete(
                route('schedule.attendees.destroy', { event: props.eventId, user: id }),
                { headers: { 'X-CSRF-TOKEN': CSRF() } }
            );
        } catch { alert('削除に失敗しました'); return; }
    }
    local.value = local.value.filter(a => a.id !== id);
    emit('change', local.value);
}

const ROLE_BADGE = {
    superadmin:  { text: 'SA',       cls: 'bg-yellow-100 text-yellow-800' },
    admin:       { text: 'admin',    cls: 'bg-red-100 text-red-700' },
    leader:      { text: 'リーダー', cls: 'bg-orange-100 text-orange-700' },
    coordinator: { text: '進行',     cls: 'bg-green-100 text-green-700' },
    clerk:       { text: '事務',     cls: 'bg-purple-100 text-purple-700' },
};
function roleBadge(role) { return ROLE_BADGE[role] || null; }
function deptName(did) {
    return props.departments.find(d => String(d.id) === String(did))?.name ?? '';
}
</script>

<template>
    <div>
        <!-- 現在の参加者チップ -->
        <div class="mb-2 flex min-h-[28px] flex-wrap gap-1">
            <span v-for="a in local" :key="a.id"
                class="flex items-center gap-1 rounded-full bg-blue-100 px-2.5 py-0.5 text-xs text-blue-800">
                {{ a.name }}
                <button type="button"
                    class="ml-0.5 leading-none text-blue-500 hover:text-blue-700"
                    @click="removeChip(a.id)">×</button>
            </span>
            <span v-if="!local.length" class="py-0.5 text-xs text-gray-400">（なし）</span>
        </div>

        <!-- 参加者を追加ボタン -->
        <button type="button"
            class="rounded border border-blue-300 bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700 hover:bg-blue-100"
            @click="openPicker">
            参加者を追加 ＋
        </button>
    </div>

    <!-- ピッカーモーダル -->
    <Teleport to="body">
        <div v-if="showPicker" class="fixed inset-0 z-[60] flex items-center justify-center">
            <div class="absolute inset-0 bg-black/50" @click="showPicker = false" />
            <div class="relative z-10 flex w-full max-w-lg flex-col rounded-xl bg-white shadow-2xl"
                style="max-height: 82vh">

                <!-- ヘッダー -->
                <div class="flex items-center justify-between border-b px-5 py-3">
                    <h3 class="font-semibold text-gray-800">参加者を選択</h3>
                    <button type="button" class="text-lg leading-none text-gray-400 hover:text-gray-600"
                        @click="showPicker = false">✕</button>
                </div>

                <!-- 会社・部署フィルター -->
                <div class="border-b px-5 py-3 space-y-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">会社で絞り込み</label>
                        <select v-model="filterCoId"
                            class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm focus:border-blue-500 focus:outline-none">
                            <option value="">— 会社を選択 —</option>
                            <option v-for="c in companies" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
                        </select>
                    </div>
                    <div v-if="filterCoId">
                        <label class="mb-1 block text-xs font-medium text-gray-600">部署で絞り込み</label>
                        <select v-model="filterDeptId"
                            class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm focus:border-blue-500 focus:outline-none">
                            <option value="">— 部署を選択 —</option>
                            <option value="__all__">（会社内すべて）</option>
                            <option v-for="d in companyDepts" :key="d.id" :value="String(d.id)">{{ d.name }}</option>
                        </select>
                    </div>
                </div>

                <!-- ユーザー一覧 -->
                <div class="flex-1 overflow-y-auto">
                    <!-- 会社未選択 -->
                    <div v-if="!filterCoId" class="py-10 text-center text-sm text-gray-400">
                        会社を選択してください
                    </div>

                    <!-- ローディング -->
                    <div v-else-if="loadingUsers" class="py-10 text-center text-sm text-gray-400">読み込み中…</div>

                    <template v-else>
                        <div class="flex items-center justify-between border-b bg-gray-50 px-4 py-2 text-xs text-gray-500">
                            <span>{{ filteredUsers.length }}件</span>
                            <span class="font-medium text-blue-700">選択中 {{ selectedCount }}名</span>
                        </div>

                        <table class="min-w-full divide-y divide-gray-100 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="w-8 px-3 py-2">
                                        <input type="checkbox" :checked="allChecked"
                                            :disabled="filteredUsers.length === 0"
                                            @change="toggleAll" />
                                    </th>
                                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500">名前</th>
                                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500">部署</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50 bg-white">
                                <tr v-for="u in filteredUsers" :key="u.id"
                                    class="cursor-pointer hover:bg-blue-50"
                                    :class="{ 'bg-blue-50': pickerSel.has(u.id) }"
                                    @click="toggleUser(u.id)">
                                    <td class="px-3 py-2">
                                        <input type="checkbox" :checked="pickerSel.has(u.id)"
                                            @click.stop @change="toggleUser(u.id)" />
                                    </td>
                                    <td class="px-2 py-2 font-medium text-gray-900">
                                        <span v-if="roleBadge(u.user_role)"
                                            :class="['mr-1 inline-block rounded px-1 py-0.5 text-xs font-semibold', roleBadge(u.user_role).cls]">
                                            {{ roleBadge(u.user_role).text }}
                                        </span>
                                        {{ u.name }}
                                    </td>
                                    <td class="px-2 py-2 text-xs text-gray-400">{{ deptName(u.department_id) }}</td>
                                </tr>
                                <tr v-if="!filteredUsers.length">
                                    <td colspan="3" class="py-8 text-center text-sm text-gray-400">
                                        ユーザーが見つかりません
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </template>
                </div>

                <!-- フッター -->
                <div class="flex justify-end gap-2 border-t px-5 py-3">
                    <button type="button"
                        class="rounded border border-gray-300 px-4 py-2 text-sm hover:bg-gray-50"
                        @click="showPicker = false">キャンセル</button>
                    <button type="button"
                        class="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60"
                        :disabled="applying"
                        @click="applySelection">
                        {{ applying ? '処理中…' : '適用' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
