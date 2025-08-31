<script setup>
import AssignedProjectCalendar from '@/Components/AssignedProjectCalendar.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import axios from 'axios';
import { computed, onMounted, ref } from 'vue';
import { route } from 'ziggy-js';
const props = defineProps({
    job: Object,
    members: Array,
    memos: { type: Array, default: () => [] },
});

// Normalize schedules from job relation if present
const schedules = computed(() => {
    try {
        if (!props.job) return [];
        // job.schedules may be provided by server; if not, return empty
        return Array.isArray(props.job.schedules) ? props.job.schedules : [];
    } catch (e) {
        return [];
    }
});

// memos: prefer server-provided props, otherwise fetch on mount
const fetchedMemos = ref([]);
onMounted(async () => {
    try {
        // if server already provided memos, skip
        if (props.memos && Array.isArray(props.memos) && props.memos.length > 0) return;
        if (!props.job || !props.job.id) return;
        const resp = await axios.get(route('coordinator.project_memos.index'), { params: { project_id: props.job.id } });
        if (resp && resp.data && Array.isArray(resp.data.memos)) {
            // normalize author if necessary
            fetchedMemos.value = resp.data.memos.map((m) => ({
                id: m.id,
                project_id: m.project_id,
                date: m.date,
                body: m.body,
                color: m.color ?? null,
                author: m.author ?? (m.user ? { id: m.user.id, name: m.user.name } : null),
            }));
        }
    } catch (e) {
        // ignore silently; calendar will show whatever props are available
        console.error('fetch project memos failed', e);
    }
});

// memos provided by server (may be a collection/proxy) or fetched as fallback
const memosList = computed(() => {
    try {
        if (props.memos && Array.isArray(props.memos) && props.memos.length > 0) return props.memos;
        if (props.job && props.job.memos && Array.isArray(props.job.memos) && props.job.memos.length > 0) return props.job.memos;
        if (fetchedMemos.value && Array.isArray(fetchedMemos.value) && fetchedMemos.value.length > 0) return fetchedMemos.value;
        return [];
    } catch (e) {
        return [];
    }
});

// 担当バッジ色分け関数
function getAssignmentBadgeClass(assignment) {
    switch (assignment) {
        case '進行管理':
            return 'bg-green-100 text-green-800';
        case 'オペレーター':
            return 'bg-blue-100 text-blue-800';
        case '校正':
            return 'bg-yellow-100 text-yellow-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

// 並び順: 作成者→進行管理→オペレーター→校正→その他
const sortedMembers = computed(() => {
    if (!props.members) return [];
    const creatorId = props.job.user_id;
    const order = ['進行管理', 'オペレーター', '校正'];
    // 作成者を最上部
    const creator = props.members.find((m) => m.id === creatorId);
    const others = props.members.filter((m) => m.id !== creatorId);
    others.sort((a, b) => {
        const aIdx = order.indexOf(a.assignment);
        const bIdx = order.indexOf(b.assignment);
        if (aIdx === -1 && bIdx === -1) return 0;
        if (aIdx === -1) return 1;
        if (bIdx === -1) return -1;
        return aIdx - bIdx;
    });
    return creator ? [creator, ...others] : others;
});

// UI: which section modal is open: null | 'details' | 'members'
const selectedSection = ref(null);
function openSection(name) {
    selectedSection.value = name;
}
function closeSection() {
    selectedSection.value = null;
}
</script>
<template>
    <AppLayout title="ジョブ詳細">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">ジョブ詳細</h2>
        </template>
        <div class="space-y-8 py-6">
            <!-- 3. スケジュールチャートブロック (ProjectCalendar を埋め込む) -->
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <h3 class="mb-4 text-lg font-semibold text-gray-800">
                    {{ job.name }} <span class="text-sm text-gray-500">- {{ job.client?.name || '-' }}</span>
                </h3>
                <div class="rounded bg-white p-4 shadow">
                    <!-- controls above calendar -->
                    <div class="mb-4 flex gap-2">
                        <button @click="openSection('details')" class="rounded bg-blue-600 px-3 py-1 text-white">詳細</button>
                        <button @click="openSection('members')" class="rounded bg-green-600 px-3 py-1 text-white">メンバー</button>
                    </div>
                    <div class="mb-4 border-b pb-1 text-lg font-bold">スケジュールチャート</div>
                    <!-- AssignedProjectCalendar: 読み取り専用カレンダー -->
                    <AssignedProjectCalendar :schedules="schedules" :project="{ id: job.id, name: job.name }" :comments="[]" :memos="memosList" />

                    <!-- memo list table (aligned with calendar width) -->
                    <div class="mt-6">
                        <h3 class="mb-4 text-lg font-semibold text-gray-800">メモ一覧</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead>
                                    <tr>
                                        <th class="w-1/5 px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">日付</th>
                                        <th class="w-1/5 px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">作成者</th>
                                        <th class="w-3/5 px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">メモ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(m, idx) in memosList"
                                        :key="m.id"
                                        :class="[idx % 2 === 0 ? 'bg-black bg-opacity-10' : '', 'hover:bg-gray-50']"
                                    >
                                        <td class="w-1/5 px-4 py-2 align-top">
                                            {{ m.date ? (m.date.split && m.date.split('T') ? m.date.split('T')[0] : m.date) : '-' }}
                                        </td>
                                        <td class="w-1/5 px-4 py-2 align-top">{{ (m.author && m.author.name) || (m.user && m.user.name) || '-' }}</td>
                                        <td class="w-3/5 whitespace-pre-wrap px-4 py-2 align-top">{{ m.body }}</td>
                                    </tr>
                                    <tr v-if="!memosList || memosList.length === 0">
                                        <td colspan="3" class="py-4 text-center text-gray-400">メモがありません</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Details modal -->
            <div
                v-if="selectedSection === 'details'"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50"
                @click="closeSection"
            >
                <div class="w-full max-w-2xl rounded bg-white p-6 shadow-lg" @click.stop>
                    <div class="mb-4 flex items-center justify-between">
                        <h4 class="text-lg font-semibold">ジョブ詳細</h4>
                        <button class="text-gray-600" @click="closeSection">閉じる</button>
                    </div>
                    <!-- job basic info (moved from inline) -->
                    <div class="mb-4">
                        <div class="mb-2">
                            <label class="block text-sm font-medium text-gray-700">ジョブID</label>
                            <div class="mt-1 text-lg text-gray-900">{{ job.id }}</div>
                        </div>
                        <div class="mb-2">
                            <label class="block text-sm font-medium text-gray-700">伝票No.</label>
                            <div class="mt-1 text-gray-900">{{ job.jobcode }}</div>
                        </div>
                        <div class="mb-2">
                            <label class="block text-sm font-medium text-gray-700">クライアント名</label>
                            <div class="mt-1 text-gray-900">{{ job.client?.name || '-' }}</div>
                        </div>
                        <div class="mb-2">
                            <label class="block text-sm font-medium text-gray-700">名前</label>
                            <div class="mt-1 text-gray-900">{{ job.name }}</div>
                        </div>
                        <div class="mb-2">
                            <label class="block text-sm font-medium text-gray-700">詳細</label>
                            <div class="mt-1 whitespace-pre-wrap text-gray-900">
                                <span v-if="job.detail && typeof job.detail === 'object' && job.detail.text">
                                    {{ job.detail.text }}
                                </span>
                                <span v-else-if="job.detail && typeof job.detail === 'object'">
                                    {{ JSON.stringify(job.detail, null, 2) }}
                                </span>
                                <span v-else>{{ job.detail || '-' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Members modal -->
            <div
                v-if="selectedSection === 'members'"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50"
                @click="closeSection"
            >
                <div class="w-full max-w-2xl rounded bg-white p-6 shadow-lg" @click.stop>
                    <div class="mb-4 flex items-center justify-between">
                        <h4 class="text-lg font-semibold">チームメンバー</h4>
                        <button class="text-gray-600" @click="closeSection">閉じる</button>
                    </div>
                    <div>
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">ID</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">名前</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">部署</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">担当</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="member in sortedMembers"
                                    :key="member.id"
                                    :class="[member.id === job.user_id ? 'bg-blue-50' : '', 'hover:bg-gray-50']"
                                >
                                    <td class="px-4 py-2">{{ member.id }}</td>
                                    <td class="px-4 py-2">{{ member.name }}</td>
                                    <td class="px-4 py-2">{{ member.department }}</td>
                                    <td class="px-4 py-2">
                                        <span
                                            :class="getAssignmentBadgeClass(member.assignment)"
                                            class="inline-flex rounded-full px-2 py-1 text-xs font-semibold"
                                        >
                                            {{ member.assignment }}
                                        </span>
                                        <span v-if="member.id === job.user_id" class="ml-2 text-xs font-bold text-blue-700">リーダー</span>
                                    </td>
                                </tr>
                                <tr v-if="!sortedMembers || sortedMembers.length === 0">
                                    <td colspan="4" class="py-4 text-center text-gray-400">メンバーがいません</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
