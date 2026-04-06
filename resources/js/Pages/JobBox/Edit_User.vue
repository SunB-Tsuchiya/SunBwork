<template>
    <AppLayout title="ジョブ編集">
        <div class="mx-auto max-w-2xl rounded bg-white p-6 shadow">
            <h1 class="mb-4 text-2xl font-bold">ジョブ編集（ユーザー）</h1>
            <div>
                <AssignmentFormUser
                    mode="user"
                    :projectJob="projectJob"
                    :members="members"
                    :assignments="assignments"
                    :editMode="true"
                    :defaultUserId="defaultUserId"
                    :user-clients="userClients"
                    :user-projects="userProjects"
                    :other-client-id="otherClientId"
                    :other-project-id="otherProjectId"
                    :event="event"
                />

                <!-- イベント時間編集 -->
                <div v-if="event" class="mt-6 border-t pt-4">
                    <h2 class="mb-3 text-base font-semibold text-gray-700">カレンダー登録時間の編集</h2>
                    <form @submit.prevent="submitEventTime" class="space-y-3">
                        <!-- 日付 -->
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">作業日 <span class="text-red-500">*</span></label>
                            <input
                                v-model="timeForm.date"
                                type="date"
                                required
                                class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
                            />
                        </div>
                        <!-- 開始時間 -->
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">開始時間 <span class="text-red-500">*</span></label>
                            <div class="flex items-center gap-2">
                                <select v-model="timeForm.startHour" class="rounded border border-gray-300 px-2 py-2 text-sm">
                                    <option v-for="h in hours" :key="h" :value="h">{{ String(h).padStart(2, '0') }}</option>
                                </select>
                                <span class="text-gray-600">:</span>
                                <select v-model="timeForm.startMinute" class="rounded border border-gray-300 px-2 py-2 text-sm">
                                    <option v-for="m in minutes" :key="m" :value="m">{{ String(m).padStart(2, '0') }}</option>
                                </select>
                            </div>
                        </div>
                        <!-- 終了時間 -->
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">終了時間 <span class="text-red-500">*</span></label>
                            <div class="flex items-center gap-2">
                                <select v-model="timeForm.endHour" class="rounded border border-gray-300 px-2 py-2 text-sm">
                                    <option v-for="h in hours" :key="h" :value="h">{{ String(h).padStart(2, '0') }}</option>
                                </select>
                                <span class="text-gray-600">:</span>
                                <select v-model="timeForm.endMinute" class="rounded border border-gray-300 px-2 py-2 text-sm">
                                    <option v-for="m in minutes" :key="m" :value="m">{{ String(m).padStart(2, '0') }}</option>
                                </select>
                            </div>
                        </div>
                        <p v-if="timeError" class="text-sm text-red-500">{{ timeError }}</p>
                        <button
                            type="submit"
                            :disabled="!!timeError || timeSaving"
                            class="rounded bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60"
                        >{{ timeSaving ? '保存中...' : '時間を更新する' }}</button>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import AssignmentFormUser from '@/Pages/Coordinator/ProjectJobs/JobAssign/AssignmentForm.vue';
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { route } from 'ziggy-js';

const page = usePage();
const assignment = page.props.projectJobAssignment || null;
const projectJob = ref(
    assignment && assignment.project_job ? assignment.project_job : assignment && assignment.projectJob ? assignment.projectJob : null,
);
const members = page.props.members || [];
const assignments = assignment ? [assignment] : [];
const userClients = page.props.userClients || [];
const userProjects = page.props.userProjects || [];
const otherClientId = page.props.otherClientId ?? null;
const otherProjectId = page.props.otherProjectId ?? null;
const defaultUserId = page.props.auth && page.props.auth.user ? page.props.auth.user.id : null;
const event = page.props.event || null;

// ── イベント時間編集 ─────────────────────────────────────
const hours = Array.from({ length: 24 }, (_, i) => i);
const minutes = [0, 5, 10, 15, 20, 25, 30, 35, 40, 45, 50, 55];

function parseDateTime(str) {
    if (!str) return null;
    const d = new Date(str);
    return { date: d.toISOString().slice(0, 10), hour: d.getHours(), minute: Math.floor(d.getMinutes() / 5) * 5 };
}

const startParsed = event ? parseDateTime(event.start) : null;
const endParsed   = event ? parseDateTime(event.end)   : null;

const timeForm = ref({
    date:        startParsed?.date        ?? new Date().toISOString().slice(0, 10),
    startHour:   startParsed?.hour        ?? 9,
    startMinute: startParsed?.minute      ?? 0,
    endHour:     endParsed?.hour          ?? 10,
    endMinute:   endParsed?.minute        ?? 0,
});

const timeSaving = ref(false);

const timeError = computed(() => {
    const s = timeForm.value.startHour * 60 + timeForm.value.startMinute;
    const e = timeForm.value.endHour   * 60 + timeForm.value.endMinute;
    if (e <= s) return '終了時間は開始時間より後にしてください。';
    return '';
});

function submitEventTime() {
    if (timeError.value || !event) return;
    timeSaving.value = true;
    router.put(
        route('events.update', { event: event.id }),
        {
            date:        timeForm.value.date,
            title:       event.title       ?? '',
            description: event.description ?? '',
            startHour:   timeForm.value.startHour,
            startMinute: timeForm.value.startMinute,
            endHour:     timeForm.value.endHour,
            endMinute:   timeForm.value.endMinute,
        },
        { onFinish: () => { timeSaving.value = false; } }
    );
}
</script>

<style scoped></style>

