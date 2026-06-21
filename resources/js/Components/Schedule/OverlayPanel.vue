<script setup>
import { ref, computed, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
    overlays:    { type: Array, default: () => [] },
    companies:   { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
});

const emit = defineEmits(['add', 'remove']);

const CSRF = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

// ── ピッカーモーダル ──────────────────────────────────────────
const showPicker   = ref(false);
const filterCoId   = ref('');
const filterDeptId = ref('');
const allUsers     = ref([]);
const loadingUsers = ref(false);
const adding       = ref(false);

const companyDepts = computed(() =>
    !filterCoId.value
        ? []
        : props.departments.filter(d => String(d.company_id) === String(filterCoId.value))
);

const filteredUsers = computed(() => {
    if (!filterCoId.value) return [];
    return allUsers.value.filter(u => {
        if (String(u.company_id) !== String(filterCoId.value)) return false;
        if (!filterDeptId.value) return true;
        return String(u.department_id) === String(filterDeptId.value);
    });
});

watch(filterCoId, () => { filterDeptId.value = ''; });

function alreadyAdded(userId) {
    return props.overlays.some(o => String(o.target_user_id) === String(userId));
}

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
}

async function addUserOverlay(userId) {
    if (alreadyAdded(userId) || adding.value) return;
    adding.value = true;
    try {
        const res = await axios.post(
            route('schedule.overlays.store'),
            { target_user_id: userId },
            { headers: { 'X-CSRF-TOKEN': CSRF() } }
        );
        emit('add', res.data);
    } catch (e) {
        alert(e.response?.data?.message ?? '追加に失敗しました');
    } finally {
        adding.value = false;
    }
}

async function removeOverlay(overlay) {
    try {
        await axios.delete(
            route('schedule.overlays.destroy', { overlay: overlay.id }),
            { headers: { 'X-CSRF-TOKEN': CSRF() } }
        );
        emit('remove', overlay.id);
    } catch {
        alert('削除に失敗しました');
    }
}

function chipLabel(o) {
    if (o.target_user) return o.target_user.name;
    return '?';
}

const ROLE_BADGE = {
    superadmin:  { text: 'SA', cls: 'bg-yellow-100 text-yellow-800' },
    admin:       { text: 'AD', cls: 'bg-red-100 text-red-700' },
    leader:      { text: 'LR', cls: 'bg-orange-100 text-orange-700' },
    coordinator: { text: 'CO', cls: 'bg-green-100 text-green-700' },
    clerk:       { text: 'CL', cls: 'bg-purple-100 text-purple-700' },
};
function roleBadge(role) { return ROLE_BADGE[role] || null; }
function deptName(did) {
    return props.departments.find(d => String(d.id) === String(did))?.name ?? '';
}
</script>

<template>
    <!-- オーバーレイチップ一覧 + 追加ボタン -->
    <div class="flex flex-wrap items-center gap-1.5 border-t border-gray-100 pt-2">
        <span class="text-xs font-medium text-gray-500 shrink-0">他のメンバー:</span>

        <span v-for="o in overlays" :key="o.id"
            class="flex items-center gap-1 rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800">
            {{ chipLabel(o) }}
            <button type="button" class="leading-none opacity-60 hover:opacity-100" @click="removeOverlay(o)">×</button>
        </span>

        <span v-if="!overlays.length" class="text-xs text-gray-400">（追加なし）</span>

        <button type="button"
            class="rounded-full border border-gray-300 bg-white px-2.5 py-0.5 text-xs text-gray-600 hover:bg-gray-50"
            @click="openPicker">
            ＋ 追加
        </button>
    </div>

    <!-- ピッカーモーダル -->
    <Teleport to="body">
        <div v-if="showPicker" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40" @click="showPicker = false" />

            <div class="relative z-10 flex w-full max-w-lg flex-col rounded-xl bg-white shadow-2xl"
                style="max-height: 82vh">

                <div class="flex items-center justify-between border-b px-5 py-3">
                    <h3 class="font-semibold text-gray-800">他のメンバーの予定を表示</h3>
                    <button type="button" class="text-gray-400 hover:text-gray-600 text-lg leading-none"
                        @click="showPicker = false">✕</button>
                </div>

                <!-- フィルター -->
                <div class="space-y-2 border-b px-5 py-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">会社で絞り込み</label>
                        <select v-model="filterCoId"
                            class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm focus:border-blue-500 focus:outline-none">
                            <option value="">— 会社を選択 —</option>
                            <option v-for="c in companies" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
                        </select>
                    </div>
                    <div v-if="filterCoId">
                        <label class="mb-1 block text-xs font-medium text-gray-600">部署で絞り込み（任意）</label>
                        <select v-model="filterDeptId"
                            class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm focus:border-blue-500 focus:outline-none">
                            <option value="">— 全部署 —</option>
                            <option v-for="d in companyDepts" :key="d.id" :value="String(d.id)">{{ d.name }}</option>
                        </select>
                    </div>
                </div>

                <!-- ユーザー一覧 -->
                <div class="flex-1 overflow-y-auto">
                    <div v-if="!filterCoId" class="py-10 text-center text-sm text-gray-400">会社を選択してください</div>
                    <div v-else-if="loadingUsers" class="py-10 text-center text-sm text-gray-400">読み込み中…</div>
                    <template v-else>
                        <div class="border-b bg-gray-50 px-4 py-2 text-xs text-gray-500">{{ filteredUsers.length }}名</div>
                        <table class="min-w-full divide-y divide-gray-100 text-sm">
                            <tbody class="divide-y divide-gray-50 bg-white">
                                <tr v-for="u in filteredUsers" :key="u.id"
                                    class="hover:bg-blue-50"
                                    :class="alreadyAdded(u.id) ? 'opacity-40' : 'cursor-pointer'"
                                    @click="addUserOverlay(u.id)">
                                    <td class="px-4 py-2 font-medium text-gray-900">
                                        <span v-if="roleBadge(u.user_role)"
                                            :class="['mr-1 inline-block rounded px-1 py-0.5 text-xs font-semibold', roleBadge(u.user_role).cls]">
                                            {{ roleBadge(u.user_role).text }}
                                        </span>
                                        {{ u.name }}
                                    </td>
                                    <td class="px-2 py-2 text-xs text-gray-400">{{ deptName(u.department_id) }}</td>
                                    <td class="px-3 py-2 text-right">
                                        <span v-if="alreadyAdded(u.id)" class="text-xs text-gray-400">追加済み</span>
                                        <span v-else class="text-xs font-medium text-blue-600">＋ 追加</span>
                                    </td>
                                </tr>
                                <tr v-if="!filteredUsers.length">
                                    <td colspan="3" class="py-8 text-center text-sm text-gray-400">ユーザーが見つかりません</td>
                                </tr>
                            </tbody>
                        </table>
                    </template>
                </div>

                <div class="flex justify-end border-t px-5 py-3">
                    <button type="button"
                        class="rounded border border-gray-300 px-4 py-2 text-sm hover:bg-gray-50"
                        @click="showPicker = false">閉じる</button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
