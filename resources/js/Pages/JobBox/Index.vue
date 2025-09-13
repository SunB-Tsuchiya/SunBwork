<template>
    <AppLayout :title="`JobBox - ${props.projectJob?.name || ''}`">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">JobBox — ジョブ関連メッセージ</h2>
        </template>

        <div class="mx-auto max-w-6xl rounded bg-white p-6 shadow">
            <h1 class="mb-4 text-2xl font-bold">JobBox：{{ props.projectJob?.name || '' }}</h1>
            <div class="mb-4 flex items-center gap-2">
                <input
                    v-model="page.props.q_model"
                    @keyup.enter="search"
                    placeholder="タイトル/詳細/担当で検索"
                    class="w-72 rounded border px-3 py-2 text-sm"
                />
                <button class="rounded bg-blue-600 px-3 py-2 text-white" @click.prevent="search">検索</button>
                <button class="ml-2 rounded border px-3 py-2" @click.prevent="clearSearch">クリア</button>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full border">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="border px-4 py-2">送受信</th>
                            <th class="border px-4 py-2">希望日</th>
                            <th class="border px-4 py-2">タイトル</th>
                            <th class="border px-4 py-2">担当</th>
                            <th class="border px-4 py-2">終了希望日 / 時刻</th>
                            <th class="border px-4 py-2">見積時間</th>
                            <th class="border px-4 py-2">依頼</th>
                            <th class="border px-4 py-2">ステータス</th>
                            <th class="border px-4 py-2">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="m in props.messages?.data || []" :key="m.id" class="hover:bg-gray-50">
                            <td class="border px-4 py-2">
                                <span class="inline-flex items-center gap-2">
                                    <span
                                        :class="
                                            page.props.auth.user &&
                                            page.props.auth.user.id &&
                                            m.sender &&
                                            m.sender.id &&
                                            page.props.auth.user.id === m.sender.id
                                                ? 'bg-blue-500'
                                                : 'bg-gray-400'
                                        "
                                        class="inline-block h-3 w-3 rounded-full"
                                        :title="
                                            page.props.auth.user &&
                                            page.props.auth.user.id &&
                                            m.sender &&
                                            m.sender.id &&
                                            page.props.auth.user.id === m.sender.id
                                                ? '送信'
                                                : '受信'
                                        "
                                    ></span>
                                    <span class="text-sm text-gray-700">{{
                                        page.props.auth.user &&
                                        page.props.auth.user.id &&
                                        m.sender &&
                                        m.sender.id &&
                                        page.props.auth.user.id === m.sender.id
                                            ? '送信'
                                            : '受信'
                                    }}</span>
                                </span>
                            </td>
                            <!-- m is JobAssignmentMessage; load related assignment via m.project_job_assignment? -->
                            <td class="border px-4 py-2">{{ m.project_job_assignment?.desired_start_date || '-' }}</td>
                            <td class="border px-4 py-2">{{ m.subject || (m.body && m.body.slice(0, 80)) }}</td>
                            <td class="border px-4 py-2">{{ m.sender?.name || m.project_job_assignment?.user?.name || '-' }}</td>
                            <td class="border px-4 py-2">{{ m.project_job_assignment?.desired_end_date || '-' }}</td>
                            <td class="border px-4 py-2">
                                {{
                                    m.project_job_assignment?.estimated_hours
                                        ? Number.isInteger(m.project_job_assignment.estimated_hours)
                                            ? m.project_job_assignment.estimated_hours + 'h'
                                            : m.project_job_assignment.estimated_hours + 'h'
                                        : '-'
                                }}
                            </td>
                            <td class="border px-4 py-2">
                                <template v-if="page.props.auth.user && page.props.auth.user.isCoordinator">
                                    <button class="rounded bg-blue-500 px-3 py-1 text-white" @click.prevent="sendFromMessage(m)">発信</button>
                                </template>
                                <template v-else>
                                    <span class="text-sm text-gray-500">権限なし</span>
                                </template>
                            </td>
                            <td class="border px-4 py-2">{{ m.read_at ? '既読' : '未読' }}</td>
                            <td class="border px-4 py-2">
                                <Link :href="getMessageLink(m)" class="rounded bg-blue-600 px-3 py-1 text-white">詳細</Link>
                                <template v-if="page.props.auth.user && page.props.auth.user.isCoordinator">
                                    <button type="button" class="ml-2 rounded bg-red-500 px-3 py-1 text-white" @click.prevent="deleteMessage(m)">
                                        削除
                                    </button>
                                </template>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex items-center justify-between">
                <div class="text-sm text-gray-600">全 {{ props.messages?.total || 0 }} 件</div>
                <div class="flex items-center space-x-2">
                    <button
                        :disabled="!props.messages?.prev_page_url"
                        @click.prevent="goto(props.messages?.prev_page_url)"
                        class="rounded border px-3 py-1 disabled:opacity-50"
                    >
                        前へ
                    </button>
                    <div class="text-sm">{{ props.messages?.current_page || 0 }} / {{ props.messages?.last_page || 0 }}</div>
                    <button
                        :disabled="!props.messages?.next_page_url"
                        @click.prevent="goto(props.messages?.next_page_url)"
                        class="rounded border px-3 py-1 disabled:opacity-50"
                    >
                        次へ
                    </button>
                </div>
            </div>

            <div class="mt-4">
                <Link :href="getBackLink()" class="rounded bg-gray-200 px-4 py-2">戻る</Link>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
const props = defineProps({ projectJob: Object, messages: Object });
const page = usePage();
page.props.q_model = page.props.q || '';

function deleteMessage(m) {
    if (!confirm('このメッセージを本当に削除しますか？この操作は取り消せません。')) return;
    router.delete(route('coordinator.project_jobs.jobbox.destroy', { projectJob: props.projectJob?.id, message: m.id }), {
        onSuccess: () => {
            router.reload();
        },
        onError: (errors) => {
            console.error('deleteMessage error', errors);
            alert('削除に失敗しました。');
        },
    });
}

function sendFromMessage(m) {
    // reuse sendRequest semantics: post a JobBox message already exists, so this may trigger the Messages flow
    if (!confirm('このジョブ情報を発信しますか？')) return;
    const to = m.project_job_assignment?.user_id ? [m.project_job_assignment.user_id] : [];
    const payload = {
        project_job_assignment_id: m.project_job_assignment?.id || null,
        to: to,
        subject: m.subject || `ジョブ割り当ての依頼: ${m.project_job_assignment?.title || ''}`,
        body: m.body || '',
        attachments: [],
    };
    router.post(route('coordinator.project_jobs.jobbox.store', { projectJob: props.projectJob?.id }), payload, {
        onSuccess: () => {
            alert('発信しました。');
            router.reload();
        },
        onError: (errors) => {
            console.error('sendFromMessage error', errors);
            alert('発信に失敗しました。');
        },
    });
}

function formatDate(d) {
    if (!d) return '-';
    return String(d).split('T')[0];
}

function goto(url) {
    if (!url) return;
    router.visit(url, { preserveState: false });
}

function search() {
    const pjId = props.projectJob?.id;
    if (!pjId) {
        // no project context yet; avoid calling Ziggy with missing params
        return;
    }
    const r = page.props.auth.user && page.props.auth.user.isCoordinator ? 'coordinator.project_jobs.jobbox.index' : 'project_jobs.jobbox.index';
    router.get(route(r, { projectJob: pjId }), { q: page.props.q_model }, { preserveState: false });
}

function clearSearch() {
    page.props.q_model = '';
    search();
}

// build a safe back link target
function getBackLink() {
    const pjId = props.projectJob?.id;
    try {
        if (!pjId) return '#';
        if (page.props.auth.user && page.props.auth.user.isCoordinator) {
            return route('coordinator.project_jobs.show', { projectJob: pjId });
        }
        return route('project_jobs.show', { projectJob: pjId });
    } catch (err) {
        console.debug('getBackLink failed', err);
        return '#';
    }
}

// helper to build message detail link safely (avoid Ziggy errors when projectJob is undefined)
function getMessageLink(m) {
    const pjId = props.projectJob?.id;
    try {
        if (!pjId) return '#';
        if (page.props.auth.user && page.props.auth.user.isCoordinator) {
            return route('coordinator.project_jobs.jobbox.show', { projectJob: pjId, message: m.id });
        }
        return route('project_jobs.jobbox.show', { projectJob: pjId, message: m.id });
    } catch (err) {
        console.debug('getMessageLink failed', err);
        return '#';
    }
}
</script>

<style scoped></style>
