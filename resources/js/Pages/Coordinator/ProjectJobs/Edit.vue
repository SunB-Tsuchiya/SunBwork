<template>
    <AppLayout title="案件編集">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">【進行管理】{{ $page.props.auth.user.name || 'ユーザー' }}さんのページ</h2>
        </template>
        <div class="mx-auto max-w-2xl rounded bg-white p-6 shadow">
            <h1 class="mb-6 text-2xl font-bold">案件編集</h1>
            <form @submit.prevent="submit">
                <div class="mb-4">
                    <label class="mb-1 block font-semibold">伝票番号</label>
                    <input v-model="form.jobcode" type="text" class="w-full rounded border px-3 py-2" required />
                </div>
                <div class="mb-4">
                    <label class="mb-1 block font-semibold">案件タイトル</label>
                    <input v-model="form.title" type="text" class="w-full rounded border px-3 py-2" required />
                </div>
                <div class="mb-4">
                    <label class="mb-1 block font-semibold">担当ユーザー</label>
                    <input v-model="form.user_name" type="text" class="w-full rounded border bg-gray-100 px-3 py-2" readonly />
                </div>
                <div class="mb-4">
                    <label class="mb-1 block font-semibold">クライアント</label>
                    <div class="flex items-center gap-2">
                        ID:<input v-model="form.client_id" type="number" class="w-16 rounded border bg-gray-100 px-3 py-2" readonly />
                        <input v-model="form.client_name" type="text" class="w-60 rounded border bg-gray-100 px-3 py-2" readonly />
                    </div>
                </div>
                <div class="mb-4">
                    <label class="mb-1 block font-semibold">詳細</label>
                    <textarea v-model="form.detail" class="w-full rounded border px-3 py-2" rows="3"></textarea>
                </div>

                <!-- スケジュール設定 -->
                <div class="mb-4">
                    <h3 class="mb-1 font-semibold">スケジュール設定</h3>
                    <div class="flex items-center gap-4">
                        <div
                            :class="[
                                'status-box w-32 rounded px-4 py-2',
                                form.schedule ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500',
                            ]"
                        >
                            {{ form.schedule ? '決定済み' : '未設定' }}
                        </div>
                        <button type="button" class="rounded bg-blue-100 px-4 py-2 text-blue-700" @click="goSchedule">スケジュール設定</button>
                    </div>
                </div>
                <!-- メンバー選定 -->
                <div class="mb-4">
                    <h3 class="mb-1 font-semibold">メンバー選定</h3>
                    <div class="flex items-center gap-4">
                        <div
                            class="status-box w-32 rounded px-4 py-2"
                            :class="form.teammember ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500'"
                        >
                            {{ form.teammember ? '決定済み' : '未設定' }}
                        </div>
                        <button type="button" class="rounded bg-green-100 px-4 py-2 text-green-700" @click="goProjectTeammember">
                            チームメンバー設定
                        </button>
                    </div>
                </div>
                <div class="mt-6 flex gap-4">
                    <button type="submit" class="rounded bg-blue-600 px-6 py-2 text-white hover:bg-blue-700">更新</button>
                    <Link :href="route('coordinator.project_jobs.index')" class="rounded bg-gray-200 px-4 py-2">一覧へ戻る</Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
const props = defineProps({ job: Object });
const page = usePage();
// user_idからユーザー名を取得
const userName = computed(() => {
    if (props.job.user && props.job.user.name) return props.job.user.name;
    if (page.props.user && page.props.user.id === props.job.user_id) return page.props.user.name;
    return '';
});
function decodeField(val, fallback = '') {
    if (val === null || val === undefined) return fallback;
    // If it's already an object (from model casting), return it
    if (typeof val === 'object') return val;
    // If it's a string, it might be JSON or plain text
    if (typeof val === 'string') {
        // Try parsing JSON; if it parses to an object, return it
        try {
            const parsed = JSON.parse(val);
            if (parsed && typeof parsed === 'object') return parsed;
        } catch (e) {
            // not JSON, return the raw string
            return val;
        }
        // If parse succeeded but wasn't an object, fall back to raw string
        return val;
    }
    return fallback;
}
// prepare decoded fields
const decodedDetail = decodeField(props.job.detail, '');
const decodedTeammember = decodeField(props.job.teammember, null);
const decodedSchedule = decodeField(props.job.schedule, null);
const form = useForm({
    jobcode: props.job.jobcode || '',
    title: props.job.title || props.job.name || '',
    user_id: props.job.user_id || '',
    user_name: userName.value,
    client_id: props.job.client_id || '',
    client_name: props.job.client?.name || '',
    // support both { "text": "..." } JSON and plain text
    detail:
        decodedDetail && typeof decodedDetail === 'object' && 'text' in decodedDetail
            ? decodedDetail.text
            : typeof decodedDetail === 'string'
              ? decodedDetail
              : '',
    teammember: decodedTeammember || null,
    schedule: decodedSchedule || null,
});
function submit() {
    form.put(route('coordinator.project_jobs.update', { projectJob: props.job.id }));
}
function goSchedule() {
    router.visit(route('coordinator.project_jobs.schedule', { projectJob: props.job.id }));
}
function goProjectTeammember() {
    // If this job already has teammember info, pass selected_user_ids so the Create page
    // pre-selects existing members. Support several shapes for form.teammember.
    try {
        const selected = form.teammember;
        const pid = props.job.id;
        const base = route('coordinator.project_team_members.create');
        let ids = [];
        if (Array.isArray(selected)) {
            ids = selected
                .map((s) => {
                    if (!s) return null;
                    if (typeof s === 'object') return s.user ? s.user.id || s.id || null : s.id || null;
                    return s;
                })
                .filter(Boolean);
        } else if (selected && typeof selected === 'object') {
            // common shapes: { users: [{ user: { id } }, ... ] } or { user: { id } }
            if (Array.isArray(selected.users)) {
                ids = selected.users.map((u) => (u && u.user ? u.user.id || u.id || null : u.id || null)).filter(Boolean);
            } else if (selected.user && selected.user.id) {
                ids = [selected.user.id];
            }
        }

        if (ids.length) {
            const url = `${base}?project_job_id=${encodeURIComponent(pid)}&selected_user_ids=${encodeURIComponent(ids.join(','))}`;
            router.visit(url);
            return;
        }
    } catch (e) {
        // fall through to default navigation
    }

    router.visit(route('coordinator.project_team_members.create', { projectJob: props.job.id }));
}
</script>

<style scoped></style>
