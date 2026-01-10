<template>
    <AppLayout :title="`MyJobBox - ${props.projectJob?.name || ''}`">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">MyJobBox — ジョブ関連メッセージ</h2>
        </template>

        <div class="mx-auto max-w-6xl rounded bg-white p-6 shadow">
            <h1 class="mb-4 text-2xl font-bold">MyJobBox：{{ props.projectJob?.name || '' }}</h1>
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
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
                <div class="mb-4 md:ml-4 md:mt-0">
                    <Link
                        :href="typeof route === 'function' ? route('project_jobs.assignments.create_user') : '/project_jobs/assignments/create-user'"
                        class="rounded bg-blue-600 px-4 py-2 text-white"
                        >新規ジョブ作成</Link
                    >
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full border">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="border px-4 py-2">送受信</th>
                            <th class="border px-4 py-2">相手</th>
                            <th class="cursor-pointer border px-4 py-2" @click.prevent="changeSort('desired_start_date')">
                                希望日 <span v-if="isSorted('desired_start_date')">{{ sortIcon() }}</span>
                            </th>
                            <th class="cursor-pointer border px-4 py-2" @click.prevent="changeSort('subject')">
                                タイトル <span v-if="isSorted('subject')">{{ sortIcon() }}</span>
                            </th>
                            <th class="border px-4 py-2">クライアント</th>
                            <th class="border px-4 py-2">既読</th>
                            <th class="border px-4 py-2">ステータス</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="m in displayMessages"
                            :key="m.id"
                            :class="['cursor-pointer hover:bg-gray-100', m.__is_new ? 'new-highlight' : '']"
                            @click.prevent="rowClick(m, $event)"
                            role="button"
                        >
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
                            <td class="border px-4 py-2 text-sm text-gray-700">{{ getCounterparty(m) }}</td>
                            <!-- m is JobAssignmentMessage; load related assignment via m.project_job_assignment? -->
                            <td class="border px-4 py-2">{{ m.project_job_assignment?.desired_start_date || '-' }}</td>
                            <td class="border px-4 py-2">{{ m.subject || (m.body && m.body.slice(0, 80)) }}</td>
                            <td class="border px-4 py-2">{{ getClientName(m) }}</td>
                            <td class="border px-4 py-2">
                                <template v-if="isUnread(m)">
                                    <span class="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800"
                                        >未読</span
                                    >
                                </template>
                                <template v-else>
                                    <span class="text-sm text-gray-600">既読</span>
                                </template>
                            </td>
                            <td class="border px-4 py-2">
                                <TooltipProvider :delay-duration="0">
                                    <Tooltip>
                                        <TooltipTrigger>
                                            <span
                                                :class="statusBadgeClass(getAssignmentStatus(m))"
                                                class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                            >
                                                <span v-html="statusIcon(getAssignmentStatus(m))" class="mr-1 inline-flex h-3 w-3"></span>
                                                {{ getAssignmentStatus(m) }}
                                            </span>
                                        </TooltipTrigger>
                                        <TooltipContent class="jobbox-tooltip max-w-xs">{{ statusTooltip(getAssignmentStatus(m)) }}</TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex items-center justify-between">
                <div class="text-sm text-gray-600">全 {{ props.messages?.total || localMessages.length || 0 }} 件</div>
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
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { reactive } from 'vue';
const props = defineProps({ projectJob: Object, messages: Object });
const page = usePage();
page.props.q_model = page.props.q || '';
// propagate server sort state into the component for UI
const currentSort = page.props.sort || null;
const currentDir = page.props.dir || 'desc';

const sortState = reactive({ sort: currentSort, dir: currentDir });

function isSorted(key) {
    return sortState.sort === key;
}

function sortIcon() {
    return sortState.dir === 'asc' ? '▲' : '▼';
}

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
    if (!confirm('このジョブ情報を発信しますか？')) return;
    const to = m.project_job_assignment?.user_id ? [m.project_job_assignment.user_id] : [];
    const payload = {
        project_job_assignment_id: m.project_job_assignment?.id || null,
        to: to,
        subject: m.subject || m.project_job_assignment?.title || null,
        body: m.body || null,
        attachments: [],
    };
    router.post(route('coordinator.project_jobs.jobbox.store', { projectJob: props.projectJob?.id }), payload, {
        onSuccess: () => {
            alert('発信しました。');
        },
    });
}
</script>
