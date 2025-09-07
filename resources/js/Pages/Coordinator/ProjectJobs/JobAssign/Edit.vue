<template>
    <AppLayout :title="`ジョブ割り当て - ${projectJob.name}`">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">【進行管理】{{ $page.props.auth.user.name || 'ユーザー' }}さんのページ</h2>
        </template>

        <div class="mx-auto max-w-3xl rounded bg-white p-6 shadow">
            <h1 class="mb-4 text-2xl font-bold">ジョブ割り当て：{{ projectJob.name }}</h1>

            <form @submit.prevent="submit">
                <div v-for="(block, idx) in assignments" :key="idx" class="mb-4 rounded border p-4">
                    <label class="mb-1 block font-semibold">ジョブ名</label>
                    <input v-model="block.title" type="text" class="w-full rounded border px-3 py-2" required />

                    <label class="mb-1 mt-2 block font-semibold">内容</label>
                    <textarea v-model="block.detail" class="w-full rounded border px-3 py-2" rows="3"></textarea>

                    <label class="mb-1 mt-2 block font-semibold">難易度</label>
                    <select v-model="block.difficulty" class="w-full rounded border px-3 py-2">
                        <option value="light">軽い</option>
                        <option value="normal">普通</option>
                        <option value="heavy">重い</option>
                    </select>

                    <div class="mt-2">
                        <label class="mb-1 block font-semibold">割当希望日</label>
                        <input v-model="block.desired_start_date" type="date" class="w-full rounded border px-3 py-2" />

                        <div class="mt-2">
                            <label class="mb-1 block font-semibold">終了希望日, 希望時間</label>
                            <div class="flex items-center gap-3">
                                <input
                                    v-model="block.desired_end_date"
                                    :min="minEndDate(idx)"
                                    type="date"
                                    class="rounded border px-3 py-2"
                                    @change="onEndDateChange(idx)"
                                />

                                <select v-model="block.desired_time_hour" class="w-20 rounded border px-3 py-2" @change="onHourChange(idx)">
                                    <option v-for="h in availableHours(idx)" :key="h" :value="h">{{ h }}</option>
                                </select>
                                <select v-model="block.desired_time_min" class="w-20 rounded border px-3 py-2">
                                    <option v-for="m in availableMins(idx, block.desired_time_hour)" :key="m" :value="m">{{ m }}</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <label class="mb-1 mt-2 block font-semibold">見積時間</label>
                    <div class="flex items-center gap-2">
                        <select v-model="block.estimated_hours" class="w-40 rounded border px-3 py-2">
                            <option value="">未指定</option>
                            <option v-for="opt in estimatedOptions" :key="opt" :value="opt">{{ String(opt).replace('.0', '') }}h</option>
                        </select>
                        <span class="text-sm text-gray-500">(0.25刻み、例: 1.5 = 1時間30分)</span>
                    </div>

                    <label class="mb-1 mt-2 block font-semibold">割当ユーザー</label>
                    <select v-model="block.user_id" class="w-full rounded border px-3 py-2">
                        <option value="">未指定</option>
                        <option v-for="m in members" :key="m.id" :value="m.id">{{ m.id }}：{{ m.name }}</option>
                    </select>

                    <div class="mt-2 text-right">
                        <button type="button" class="rounded bg-red-200 px-3 py-1" @click="removeBlock(idx)">削除</button>
                    </div>
                </div>

                <div class="flex gap-2">
                    <button v-if="!props.editMode" type="button" class="rounded bg-blue-600 px-4 py-2 text-white" @click="addBlock">
                        ジョブブロックを追加
                    </button>
                    <button type="submit" class="rounded bg-green-600 px-4 py-2 text-white">保存して割り当て</button>
                    <Link
                        :href="route('coordinator.project_jobs.assignments.index', { projectJob: projectJob.id })"
                        class="rounded bg-gray-200 px-4 py-2"
                        >戻る</Link
                    >
                </div>
            </form>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ projectJob: Object, members: Array, assignments: Array, editMode: { type: Boolean, default: false } });
const page = usePage();

const hours = Array.from({ length: 17 }, (_, i) => String(6 + i).padStart(2, '0'));
const mins = ['00', '15', '30', '45'];
// estimated hours options: 0.25 to 8.0 in 0.25 steps
const estimatedOptions = Array.from({ length: 32 }, (_, i) => Number(((i + 1) * 0.25).toFixed(2)));

function normalizeAssignment(a) {
    return {
        id: a.id || null,
        title: a.title || '',
        detail: a.detail || '',
        difficulty: a.difficulty || 'normal',
        desired_start_date: a.desired_start_date || a.desired_date || '',
        desired_end_date: a.desired_end_date || '',
        desired_time_hour: a.desired_time ? a.desired_time.split(':')[0] || '09' : a.desired_time_hour || '09',
        desired_time_min: a.desired_time ? a.desired_time.split(':')[1] || '00' : a.desired_time_min || '00',
        estimated_hours: a.estimated_hours !== undefined && a.estimated_hours !== null ? a.estimated_hours : '',
        user_id: a.user_id || (a.user ? a.user.id : '') || '',
    };
}

const assignments = ref(props.assignments && props.assignments.length ? props.assignments.map(normalizeAssignment) : [normalizeAssignment({})]);

function addBlock() {
    assignments.value.push({
        title: '',
        detail: '',
        difficulty: 'normal',
        desired_date: '',
        desired_time_hour: '09',
        desired_time_min: '00',
        estimated_hours: '',
        user_id: '',
    });
}

function removeBlock(i) {
    assignments.value.splice(i, 1);
}

function todayDateStr() {
    const d = new Date();
    return d.toISOString().split('T')[0];
}

function minEndDate(idx) {
    const a = assignments.value[idx];
    return a.desired_start_date || todayDateStr();
}

function availableHours(idx) {
    // if end date is today, disallow hours earlier than now
    const a = assignments.value[idx];
    if (!a.desired_end_date) return hours;
    const today = todayDateStr();
    if (a.desired_end_date !== today) return hours;
    const now = new Date();
    const currentHour = String(now.getHours()).padStart(2, '0');
    return hours.filter((h) => h >= currentHour);
}

function availableMins(idx, hour) {
    const a = assignments.value[idx];
    if (!a.desired_end_date) return mins;
    const today = todayDateStr();
    if (a.desired_end_date !== today) return mins;
    const now = new Date();
    const currentHour = String(now.getHours()).padStart(2, '0');
    if (hour > currentHour) return mins;
    // same hour as now: allow only mins >= current minute rounded to next quarter
    const curMin = now.getMinutes();
    const nextQuarter = Math.ceil(curMin / 15) * 15;
    return mins.filter((m) => Number(m) >= nextQuarter);
}

function onEndDateChange(idx) {
    const a = assignments.value[idx];
    // if end date is before start date, clamp it
    if (a.desired_start_date && a.desired_end_date && a.desired_end_date < a.desired_start_date) {
        a.desired_end_date = a.desired_start_date;
    }
}

function onHourChange(idx) {
    const a = assignments.value[idx];
    // if selected hour is now greater than available hours, clamp minute options
    const avail = availableMins(idx, a.desired_time_hour);
    if (!avail.includes(a.desired_time_min)) {
        a.desired_time_min = avail.length ? avail[0] : '00';
    }
}

function submit() {
    if (props.editMode && assignments.value.length === 1 && assignments.value[0].id) {
        const a = assignments.value[0];
        const payload = {
            title: a.title,
            detail: a.detail,
            difficulty: a.difficulty,
            estimated_hours: a.estimated_hours || null,
            desired_start_date: a.desired_start_date || null,
            desired_end_date: a.desired_end_date || null,
            desired_time: String(a.desired_time_hour).padStart(2, '0') + ':' + String(a.desired_time_min).padStart(2, '0'),
            user_id: a.user_id || null,
        };
        router.put(route('coordinator.project_jobs.assignments.update', { projectJob: props.projectJob.id, assignment: a.id }), payload);
        return;
    }

    const payload = {
        assignments: assignments.value.map((a) => ({
            title: a.title,
            detail: a.detail,
            difficulty: a.difficulty,
            estimated_hours: a.estimated_hours || null,
            desired_start_date: a.desired_start_date || null,
            desired_end_date: a.desired_end_date || null,
            desired_time: String(a.desired_time_hour).padStart(2, '0') + ':' + String(a.desired_time_min).padStart(2, '0'),
            user_id: a.user_id || null,
        })),
    };
    router.post(route('coordinator.project_jobs.assignments.store', { projectJob: props.projectJob.id }), payload);
}
</script>

<style scoped>
/* small styles */
</style>
