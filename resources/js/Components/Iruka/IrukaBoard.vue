<template>
    <div class="mx-auto max-w-2xl rounded-lg border border-gray-200 bg-white shadow-sm overflow-hidden">

        <!-- 部署タブ（複数部署のときのみ表示） -->
        <div v-if="deptTabs.length > 1" class="flex border-b border-gray-200 bg-gray-50 overflow-x-auto">
            <button
                v-for="dept in deptTabs"
                :key="dept.id"
                type="button"
                class="shrink-0 px-4 py-2.5 text-sm font-medium border-b-2 transition-colors whitespace-nowrap"
                :class="activeDept === dept.id
                    ? 'border-blue-500 text-blue-600 bg-white'
                    : 'border-transparent text-gray-500 hover:text-gray-700'"
                @click="activeDept = dept.id"
            >{{ dept.name }}</button>
        </div>

        <div class="divide-y divide-gray-100">
            <!-- 出社中グループ -->
            <template v-if="presentUsers.length > 0">
                <div class="bg-green-50 px-4 py-1.5">
                    <span class="text-xs font-medium text-green-600">出社中 {{ presentUsers.length }}人</span>
                </div>
                <div
                    v-for="u in presentUsers"
                    :key="u.id"
                    class="flex items-center gap-2 px-4 py-1.5 cursor-pointer hover:bg-blue-50 transition-colors"
                    @click="openModal(u)"
                >
                    <span class="w-28 shrink-0 font-bold text-cyan-700 text-sm truncate">{{ u.name }}</span>
                    <span
                        v-if="u.comment"
                        class="flex-1 min-w-0 rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-600 whitespace-pre-line"
                    >{{ u.comment }}</span>
                    <div v-else class="flex-1" />
                    <div class="flex items-center gap-1 shrink-0">
                        <span class="h-2 w-2 rounded-full shrink-0" :class="resolveStatusDisplay(u.status).dot" />
                        <span class="text-sm font-medium" :class="resolveStatusDisplay(u.status).textColor">
                            {{ resolveStatusDisplay(u.status).label }}
                        </span>
                    </div>
                    <span class="text-xs text-gray-300 w-12 text-right shrink-0">{{ formatTime(u.updated_at) }}</span>
                </div>
            </template>

            <!-- 退社・休暇グループ -->
            <template v-if="absentUsers.length > 0">
                <div class="bg-gray-50 px-4 py-1.5" :class="{ 'border-t border-gray-200': presentUsers.length > 0 }">
                    <span class="text-xs font-medium text-gray-400">退社・休暇 {{ absentUsers.length }}人</span>
                </div>
                <div
                    v-for="u in absentUsers"
                    :key="u.id"
                    class="flex items-center gap-2 px-4 py-1.5 cursor-pointer hover:bg-gray-50 transition-colors opacity-50"
                    @click="openModal(u)"
                >
                    <span class="w-28 shrink-0 font-bold text-gray-500 text-sm truncate">{{ u.name }}</span>
                    <span
                        v-if="u.comment"
                        class="flex-1 min-w-0 rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-500 whitespace-pre-line"
                    >{{ u.comment }}</span>
                    <div v-else class="flex-1" />
                    <div class="flex items-center gap-1 shrink-0">
                        <span class="h-2 w-2 rounded-full shrink-0" :class="resolveStatusDisplay(u.status).dot" />
                        <span class="text-sm font-medium" :class="resolveStatusDisplay(u.status).textColor">
                            {{ resolveStatusDisplay(u.status).label }}
                        </span>
                    </div>
                    <span class="text-xs text-gray-300 w-12 text-right shrink-0">{{ formatTime(u.updated_at) }}</span>
                </div>
            </template>

            <!-- メンバーなし -->
            <div v-if="filteredUsers.length === 0" class="py-8 text-center text-sm text-gray-400">
                表示するメンバーがいません
            </div>
        </div>

        <!-- ステータス更新モーダル -->
        <IrukaStatusModal
            v-if="modalTarget"
            :show="showModal"
            :target-user="modalTarget"
            :is-self="modalTarget.id === authUser?.id"
            :statuses="statuses"
            @close="showModal = false"
            @save="handleSave"
            @clear="handleClear"
        />
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted, inject } from 'vue';
import IrukaStatusModal from './IrukaStatusModal.vue';
import { getStatus, resolveStatus } from './statusConfig.js';

const props = defineProps({
    departments:   { type: Array,  default: () => [] },
    defaultDeptId: { type: Number, default: null },
});

const authUser = inject('authUser', null);
const users    = ref([]);
const statuses = ref(null);

// 退社・休暇グループに分類するスラッグ
const ABSENT_SLUGS = new Set(['left', 'paid_leave', 'special_leave', 'early_leave']);

// 部署タブ（「全部署」なし、各部署のみ）
const deptTabs = computed(() => props.departments);

// localStorage から復元。無効値は defaultDeptId → 最初の部署の順でフォールバック
const DEPT_KEY = 'iruka_active_dept';
function initDept() {
    const saved = localStorage.getItem(DEPT_KEY);
    if (saved && saved !== 'null') {
        const n = Number(saved);
        if (props.departments.some(d => d.id === n)) return n;
    }
    if (props.defaultDeptId && props.departments.some(d => d.id === props.defaultDeptId)) {
        return props.defaultDeptId;
    }
    return props.departments[0]?.id ?? null;
}
const activeDept = ref(initDept());

watch(activeDept, (val) => {
    localStorage.setItem(DEPT_KEY, val === null ? 'null' : String(val));
});

const showModal   = ref(false);
const modalTarget = ref(null);
let pollTimer = null;

// statusMap: slug → resolveStatus(orderRecord) のマップ（カスタム対応）
const statusMap = computed(() => {
    if (!statuses.value) return null;
    const map = {};
    statuses.value.forEach(s => { map[s.slug] = resolveStatus(s); });
    return map;
});

function resolveStatusDisplay(slug) {
    return statusMap.value?.[slug] ?? getStatus(slug);
}

const filteredUsers = computed(() => {
    if (!activeDept.value) return users.value;
    return users.value.filter(u => u.department_id === activeDept.value || !u.department_id);
});

const presentUsers = computed(() => filteredUsers.value.filter(u => !ABSENT_SLUGS.has(u.status)));
const absentUsers  = computed(() => filteredUsers.value.filter(u =>  ABSENT_SLUGS.has(u.status)));

onMounted(async () => {
    await Promise.all([fetchPresence(), fetchStatuses()]);
    pollTimer = setInterval(fetchPresence, 30000);
    window.addEventListener('iruka:refresh', fetchPresence);
});

onUnmounted(() => {
    clearInterval(pollTimer);
    window.removeEventListener('iruka:refresh', fetchPresence);
});

async function fetchPresence() {
    try {
        const res  = await window.axios.get('/presence');
        users.value = res.data;
    } catch (_) {}
}

async function fetchStatuses() {
    try {
        const res      = await window.axios.get('/presence/statuses');
        statuses.value = res.data;
    } catch (_) {}
}

function openModal(u) {
    modalTarget.value = { ...u };
    showModal.value   = true;
}

async function handleSave({ userId, status, comment }) {
    showModal.value = false; // 先に閉じる（iOS Safari 対策）
    try {
        await window.axios.post(`/presence/${userId}`, { status, comment });
        await fetchPresence();
        window.dispatchEvent(new CustomEvent('iruka:refresh'));
    } catch (_) {}
}

async function handleClear() {
    showModal.value = false; // 先に閉じる（iOS Safari 対策）
    try {
        await window.axios.post('/presence/self/clear');
        await fetchPresence();
        window.dispatchEvent(new CustomEvent('iruka:refresh'));
    } catch (_) {}
}

function formatTime(dt) {
    if (!dt) return '未更新';
    const d      = new Date(dt);
    const now    = new Date();
    const diffMs = now - d;
    const diffMin = Math.floor(diffMs / 60000);
    if (diffMin < 1)  return 'たった今';
    if (diffMin < 60) return `${diffMin}分前`;
    const diffH = Math.floor(diffMin / 60);
    if (diffH < 24)  return `${diffH}時間前`;
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    const hh = String(d.getHours()).padStart(2, '0');
    const mi = String(d.getMinutes()).padStart(2, '0');
    return `${mm}/${dd} ${hh}:${mi}`;
}
</script>
