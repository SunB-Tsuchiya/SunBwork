<template>
    <div class="space-y-4">
        <!-- 部署フィルターボタン -->
        <div class="flex flex-wrap gap-2">
            <button
                v-for="dept in deptTabs"
                :key="dept.id"
                type="button"
                class="rounded-full border px-3 py-1 text-sm font-medium transition-colors"
                :class="activeDept === dept.id
                    ? 'border-blue-500 bg-blue-500 text-white'
                    : 'border-gray-300 bg-white text-gray-600 hover:bg-gray-50'"
                @click="activeDept = dept.id"
            >
                {{ dept.name }}
            </button>
        </div>

        <!-- ユーザーカード一覧 -->
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            <div
                v-for="u in filteredUsers"
                :key="u.id"
                class="cursor-pointer rounded-lg border border-gray-200 bg-white p-3 shadow-sm transition-shadow hover:shadow-md"
                @click="openModal(u)"
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-gray-800">{{ u.name }}</p>
                        <p class="text-xs text-gray-400">{{ u.department }}</p>
                    </div>
                    <!-- ステータスバッジ -->
                    <span
                        class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium text-white"
                        :class="getStatus(u.status).color"
                    >{{ getStatus(u.status).label }}</span>
                </div>

                <!-- ひとこと -->
                <p v-if="u.comment" class="mt-2 line-clamp-2 text-xs text-gray-500">{{ u.comment }}</p>

                <!-- 最終更新時間 -->
                <p class="mt-2 text-right text-[10px] text-gray-300">
                    {{ formatTime(u.updated_at) }}
                </p>
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
import { getStatus } from './statusConfig.js';

const props = defineProps({
    departments: { type: Array, default: () => [] },
});

const authUser    = inject('authUser', null);
const users       = ref([]);
const statuses    = ref(null);

const DEPT_KEY = 'iruka_active_dept';
const savedDept = localStorage.getItem(DEPT_KEY);
const activeDept = ref(savedDept !== null ? (savedDept === 'null' ? null : Number(savedDept)) : null);

watch(activeDept, (val) => {
    localStorage.setItem(DEPT_KEY, val === null ? 'null' : String(val));
});
const showModal   = ref(false);
const modalTarget = ref(null);
let pollTimer = null;

const deptTabs = computed(() => [
    { id: null, name: '全部署' },
    ...props.departments,
]);

const filteredUsers = computed(() => {
    if (activeDept.value === null) return users.value;
    return users.value.filter(u => u.department_id === activeDept.value);
});

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
    try {
        await window.axios.post(`/presence/${userId}`, { status, comment });
        showModal.value = false;
        await fetchPresence();
        window.dispatchEvent(new CustomEvent('iruka:refresh'));
    } catch (_) {}
}

async function handleClear() {
    try {
        await window.axios.post('/presence/self/clear');
        showModal.value = false;
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
