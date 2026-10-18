<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import ClerkNavigationTabs from '@/Components/Tabs/ClerkNavigationTabs.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps({
    campaign: { type: Object, required: true },
    companyName: { type: String, default: '' },
    statuses: { type: Object, default: () => ({}) },
    targets: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({}) },
    statusLabels: { type: Object, default: () => ({}) },
    candidates: { type: Array, default: () => [] },
    employmentLabels: { type: Object, default: () => ({}) },
    noticeDrafts: { type: Object, default: () => ({}) },
    noticeKinds: { type: Object, default: () => ({}) },
    notices: { type: Array, default: () => [] },
    reminder: { type: Object, default: null },
    reminderDraft: { type: String, default: '' },
});

// 案内（お知らせ）の送信モーダル。下書きは編集してから送れる
const noticeOpen = ref(false);
const noticeForm = useForm({ kind: 'start', title: '', content: '' });
function openNotice(kind) {
    const d = props.noticeDrafts[kind];
    noticeForm.kind = kind;
    noticeForm.title = d.title;
    noticeForm.content = d.content;
    noticeForm.clearErrors();
    noticeOpen.value = true;
}
function sendNotice() {
    const n = props.noticeDrafts[noticeForm.kind].count;
    if (!confirm(`この内容で${n}名にお知らせを送信します。よろしいですか？`)) return;
    noticeForm.post(route('clerk.general_affairs.nencho.campaigns.notices.store', { campaign: props.campaign.id }), {
        preserveScroll: true,
        onSuccess: () => { noticeOpen.value = false; },
    });
}
const reminderForm = useForm({ content: props.reminder?.content ?? props.reminderDraft });
function setReminder() {
    reminderForm.post(route('clerk.general_affairs.nencho.campaigns.reminder', { campaign: props.campaign.id }), { preserveScroll: true });
}
function removeReminder() {
    if (!confirm('カレンダーのリマインダーを削除しますか？')) return;
    router.delete(route('clerk.general_affairs.nencho.campaigns.reminder.remove', { campaign: props.campaign.id }), { preserveScroll: true });
}

const busyId = ref(null);
const activeCount = computed(() => props.targets.filter((t) => !t.is_excluded).length);
const excludedCount = computed(() => props.targets.length - activeCount.value);
const addForm = useForm({ user_id: '' });
const statusBadge = { draft: 'bg-amber-100 text-amber-800', submitted: 'bg-green-100 text-green-700', returned: 'bg-red-100 text-red-700' };
const profileBadge = {
    matched: ['社員情報と一致', 'bg-gray-100 text-gray-600'],
    unregistered: ['社員情報が未登録', 'bg-amber-100 text-amber-800'],
    diff: ['社員情報に差異あり', 'bg-red-100 text-red-700'],
    resolved: ['差異を確認済み', 'bg-blue-100 text-blue-700'],
};
const filter = ref('all');
const shown = computed(() => props.targets.filter((t) => {
    if (filter.value === 'all') return true;
    if (filter.value === 'excluded') return t.is_excluded;
    if (t.is_excluded) return false;
    if (filter.value === 'profile_diff') return ['unregistered', 'diff'].includes(t.profile_status);
    return filter.value === 'not_started' ? !t.status : t.status === filter.value;
}));

function sync() {
    if (!confirm('対象者を取り込み直しますか？\n新しく対象に当てはまる人だけを追加します（除外した人や入力済みの人は変更しません）。')) return;
    router.post(route('clerk.general_affairs.nencho.campaigns.sync', { campaign: props.campaign.id }), {}, { preserveScroll: true });
}

function toggle(target) {
    let reason = null;
    if (!target.is_excluded) {
        reason = window.prompt(`${target.name}さんを対象から除外します。理由（任意）を入力してください。`, '');
        if (reason === null) return;
    }
    busyId.value = target.id;
    router.patch(
        route('clerk.general_affairs.nencho.campaigns.targets.toggle', { campaign: props.campaign.id, target: target.id }),
        { is_excluded: !target.is_excluded, excluded_reason: reason },
        { preserveScroll: true, onFinish: () => { busyId.value = null; } },
    );
}

function addTarget() {
    if (!addForm.user_id) return;
    addForm.post(route('clerk.general_affairs.nencho.campaigns.targets.store', { campaign: props.campaign.id }), {
        preserveScroll: true,
        onSuccess: () => addForm.reset(),
    });
}
</script>

<template>
    <AppLayout :title="campaign.title">
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('clerk.general_affairs.nencho.campaigns.index', { company: campaign.company_id })" class="whitespace-nowrap rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300">
                    ← 受付一覧に戻る
                </Link>
                <h2 class="text-base font-semibold text-gray-800 sm:text-xl">{{ campaign.title }}</h2>
            </div>
        </template>
        <template #tabs>
            <ClerkNavigationTabs active="applications" />
        </template>

        <div class="space-y-6">
            <div class="rounded bg-white p-6 shadow">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <h3 class="text-lg font-semibold text-gray-800">受付の内容</h3>
                    <Link :href="route('clerk.general_affairs.nencho.campaigns.edit', { campaign: campaign.id })" class="rounded bg-purple-600 px-4 py-2 text-sm font-medium text-white hover:bg-purple-700">編集</Link>
                </div>
                <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                    <div><dt class="text-gray-500">会社</dt><dd class="font-medium text-gray-800">{{ companyName }}</dd></div>
                    <div><dt class="text-gray-500">年度</dt><dd class="font-medium text-gray-800">{{ campaign.fiscal_year }}年分</dd></div>
                    <div><dt class="text-gray-500">状態</dt><dd class="font-medium text-gray-800">{{ statuses[campaign.status] ?? campaign.status }}</dd></div>
                    <div><dt class="text-gray-500">受付期間</dt><dd class="font-medium text-gray-800">{{ campaign.opens_on }} ～ {{ campaign.due_on }}</dd></div>
                    <div><dt class="text-gray-500">所轄税務署 / 市区町村</dt><dd class="font-medium text-gray-800">{{ campaign.tax_office_name || '未設定' }} / {{ campaign.municipality_name || '未設定' }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-gray-500">給与の支払者</dt><dd class="font-medium text-gray-800">{{ campaign.payer_name || '未設定' }}<span v-if="campaign.payer_corporate_number" class="ml-2 text-gray-500">（法人番号 {{ campaign.payer_corporate_number }}）</span></dd></div>
                    <div v-if="campaign.message" class="sm:col-span-2"><dt class="text-gray-500">案内文</dt><dd class="whitespace-pre-wrap text-gray-800">{{ campaign.message }}</dd></div>
                </dl>
            </div>

            <div class="rounded bg-white p-6 shadow">
                <h3 class="mb-1 text-lg font-semibold text-gray-800">従業員への案内</h3>
                <p class="mb-4 text-sm text-gray-600">既存の「お知らせ」で送ります。送る前に文面を編集できます。</p>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="rounded bg-purple-600 px-4 py-2 text-sm font-medium text-white hover:bg-purple-700" @click="openNotice('start')">
                        受付開始をお知らせ（{{ noticeDrafts.start?.count ?? 0 }}名）
                    </button>
                    <button type="button" :disabled="!noticeDrafts.reminder?.count" class="rounded border border-purple-300 bg-purple-50 px-4 py-2 text-sm font-medium text-purple-800 hover:bg-purple-100 disabled:opacity-50" @click="openNotice('reminder')">
                        未提出の方に催促（{{ noticeDrafts.reminder?.count ?? 0 }}名）
                    </button>
                </div>
                <div class="mt-4 rounded border border-gray-200 p-3 text-sm">
                    <p class="font-medium text-gray-800">カレンダーのリマインダー</p>
                    <p v-if="reminder" class="mt-1 text-gray-600">設定済み（表示期間 {{ reminder.starts_on }} ～ {{ reminder.ends_on }}）。文面は下で編集できます。</p>
                    <p v-else class="mt-1 text-gray-600">未設定です。設定すると、受付期間中、その会社のユーザーのカレンダー上部に下の文面が表示されます。</p>
                    <label for="r-content" class="mt-2 block text-xs font-medium text-gray-700">表示する文面</label>
                    <textarea id="r-content" v-model="reminderForm.content" rows="3" maxlength="500" class="w-full rounded border-gray-300 text-sm"></textarea>
                    <p class="text-xs text-red-600">{{ reminderForm.errors.content }}</p>
                    <div class="mt-2 flex gap-2">
                        <button type="button" :disabled="reminderForm.processing" class="rounded bg-purple-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-purple-700 disabled:opacity-50" @click="setReminder">{{ reminder ? '文面を保存' : 'リマインダーを設定' }}</button>
                        <button v-if="reminder" type="button" class="rounded bg-red-50 px-3 py-1.5 text-xs text-red-700 hover:bg-red-100" @click="removeReminder">削除</button>
                    </div>
                </div>
                <div class="mt-4">
                    <p class="mb-1 text-sm font-medium text-gray-800">送信履歴</p>
                    <p v-if="!notices.length" class="text-sm text-gray-500">まだ送信していません。</p>
                    <ul v-else class="divide-y divide-gray-100 text-sm">
                        <li v-for="n in notices" :key="n.id" class="py-2">
                            <span class="mr-2 rounded bg-purple-100 px-2 py-0.5 text-xs text-purple-800">{{ noticeKinds[n.kind] ?? n.kind }}</span>
                            <span class="text-gray-800">{{ n.title }}</span>
                            <p class="text-xs text-gray-500">{{ n.created_at }}　{{ n.sent_by_name }}　送信 {{ n.recipient_count }}名 / 既読 {{ n.read_count }}名</p>
                        </li>
                    </ul>
                </div>
            </div>

            <div v-if="noticeOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" role="dialog" aria-modal="true">
                <div class="max-h-full w-full max-w-xl overflow-y-auto rounded bg-white p-6 shadow-lg">
                    <h3 class="mb-3 text-lg font-semibold text-gray-800">{{ noticeKinds[noticeForm.kind] }}（{{ noticeDrafts[noticeForm.kind]?.count }}名に送信）</h3>
                    <label for="n-title" class="mb-1 block text-sm font-medium text-gray-700">タイトル</label>
                    <input id="n-title" v-model="noticeForm.title" type="text" maxlength="255" class="mb-1 w-full rounded border-gray-300 text-sm" />
                    <p class="mb-3 text-xs text-red-600">{{ noticeForm.errors.title }}</p>
                    <label for="n-content" class="mb-1 block text-sm font-medium text-gray-700">本文</label>
                    <textarea id="n-content" v-model="noticeForm.content" rows="10" maxlength="5000" class="mb-1 w-full rounded border-gray-300 text-sm"></textarea>
                    <p class="mb-3 text-xs text-red-600">{{ noticeForm.errors.content }}</p>
                    <div class="flex justify-end gap-2">
                        <button type="button" class="rounded bg-gray-200 px-4 py-2 text-sm hover:bg-gray-300" @click="noticeOpen = false">キャンセル</button>
                        <button type="button" :disabled="noticeForm.processing" class="rounded bg-purple-600 px-4 py-2 text-sm font-medium text-white hover:bg-purple-700 disabled:opacity-50" @click="sendNotice">送信する</button>
                    </div>
                </div>
            </div>

            <div class="rounded bg-white p-6 shadow">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800">対象者</h3>
                        <p class="text-sm text-gray-600">対象 {{ activeCount }}名<span v-if="excludedCount">（除外 {{ excludedCount }}名）</span>　社員・契約社員が自動で対象になります。</p>
                    </div>
                    <button class="rounded border border-purple-300 bg-purple-50 px-4 py-2 text-sm font-medium text-purple-800 hover:bg-purple-100" @click="sync">対象者を取り込み直す</button>
                </div>

                <div class="mb-4 flex flex-wrap gap-2 text-sm">
                    <button v-for="[key, label, count] in [['all', 'すべて', targets.length], ['not_started', '未入力', summary.not_started], ['draft', '入力中', summary.draft], ['submitted', '提出済み', summary.submitted], ['returned', '差戻し', summary.returned], ['profile_diff', '社員情報に差異', summary.profile_diff], ['excluded', '除外', excludedCount]]" :key="key" type="button"
                        :class="['rounded-full px-3 py-1 text-xs font-medium', filter === key ? 'bg-purple-600 text-white' : 'border border-purple-200 text-purple-700 hover:bg-purple-50']" @click="filter = key">
                        {{ label }} {{ count }}
                    </button>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-3 text-left">氏名</th>
                                <th class="px-3 py-3 text-left">部署</th>
                                <th class="px-3 py-3 text-left">雇用区分</th>
                                <th class="px-3 py-3 text-left">対象</th>
                                <th class="px-3 py-3 text-left">提出状況</th>
                                <th class="px-3 py-3 text-right">操作</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="t in shown" :key="t.id" :class="t.is_excluded ? 'bg-gray-50 text-gray-500' : 'hover:bg-gray-50'">
                                <td class="whitespace-nowrap px-3 py-3 font-medium">{{ t.name }}</td>
                                <td class="whitespace-nowrap px-3 py-3">{{ t.department ?? '—' }}</td>
                                <td class="whitespace-nowrap px-3 py-3">{{ employmentLabels[t.employment_type] ?? t.employment_type }}</td>
                                <td class="px-3 py-3">
                                    <span v-if="t.is_excluded" class="rounded-full bg-gray-200 px-2 py-1 text-xs font-medium text-gray-700">除外<span v-if="t.excluded_reason">：{{ t.excluded_reason }}</span></span>
                                    <span v-else class="rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-700">対象<span v-if="t.added_manually">（手動追加）</span></span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-3">
                                    <span v-if="t.is_excluded" class="text-xs text-gray-400">—</span>
                                    <template v-else>
                                        <span v-if="t.status" class="rounded-full px-2 py-1 text-xs font-medium" :class="statusBadge[t.status]">{{ statusLabels[t.status] ?? t.status }}</span>
                                        <span v-else class="rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-600">未入力</span>
 <span v-if="t.profile_status && t.profile_status !== 'matched'" class="ml-1 rounded-full px-2 py-0.5 text-xs font-medium" :class="profileBadge[t.profile_status][1]">{{ profileBadge[t.profile_status][0] }}</span>
                                        <span v-if="t.entered_by_type === 'proxy'" class="ml-1 rounded-full bg-purple-100 px-2 py-0.5 text-xs font-medium text-purple-700">代理入力</span>
                                        <span v-if="t.submitted_at" class="ml-2 text-xs text-gray-500">{{ t.submitted_at }}</span>
                                        <span v-else-if="t.status === 'draft'" class="ml-2 text-xs text-gray-500">工程 {{ t.current_step }}/7</span>
                                    </template>
                                </td>
                                <td class="whitespace-nowrap px-3 py-3 text-right">
                                    <Link v-if="!t.is_excluded && t.user_id && t.status !== 'submitted'" :href="route('clerk.general_affairs.nencho.proxy.start', { campaign: campaign.id, user: t.user_id })" class="mr-3 text-purple-700 hover:underline">代理入力</Link>
                                    <Link v-if="t.submission_id" :href="route('clerk.general_affairs.nencho.submissions.show', { submission: t.submission_id })" class="mr-3 text-purple-700 hover:underline">{{ t.status === 'submitted' ? '確認・差戻し' : '内容を見る' }}</Link>
                                    <button :disabled="busyId === t.id" class="text-purple-700 hover:underline disabled:opacity-50" @click="toggle(t)">{{ t.is_excluded ? '対象に戻す' : '除外する' }}</button>
                                </td>
                            </tr>
                            <tr v-if="!shown.length">
                                <td colspan="6" class="px-3 py-8 text-center text-gray-500">対象者がいません。「対象者を取り込み直す」を押してください。</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-6 border-t border-gray-200 pt-4">
                    <h4 class="mb-2 text-sm font-semibold text-gray-700">対象者を個別に追加</h4>
                    <p class="mb-2 text-xs text-gray-500">派遣社員・業務委託など、自動では対象にならない人を追加できます。</p>
                    <form class="flex flex-wrap items-center gap-3" @submit.prevent="addTarget">
                        <select v-model="addForm.user_id" class="min-w-60 rounded border-gray-300 text-sm focus:border-purple-500 focus:ring-purple-500">
                            <option value="">追加する人を選択</option>
                            <option v-for="c in candidates" :key="c.id" :value="c.id">{{ c.name }}（{{ employmentLabels[c.employment_type] ?? c.employment_type }}）</option>
                        </select>
                        <button :disabled="!addForm.user_id || addForm.processing" class="rounded bg-purple-600 px-4 py-2 text-sm font-medium text-white hover:bg-purple-700 disabled:opacity-50">追加</button>
                        <span v-if="!candidates.length" class="text-xs text-gray-500">追加できる人はいません。</span>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
