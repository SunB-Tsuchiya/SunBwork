<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import ClerkNavigationTabs from '@/Components/Tabs/ClerkNavigationTabs.vue';
import SubmissionSummary from '@/Components/SunA/GeneralAffairs/Nencho/SubmissionSummary.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps({
    campaign: { type: Object, required: true },
    submission: { type: Object, required: true },
    spouse: { type: Object, default: null },
    dependents: { type: Array, default: () => [] },
    insuranceItems: { type: Object, default: () => ({}) },
    events: { type: Array, default: () => [] },
    eventActions: { type: Object, default: () => ({}) },
    statuses: { type: Object, default: () => ({}) },
    steps: { type: Array, default: () => [] },
    canReturn: { type: Boolean, default: false },
    profileDiff: { type: Object, default: null },
    profileDiffStatus: { type: String, default: null },
    canApplySync: { type: Boolean, default: false },
    editLock: { type: Object, default: null },
    canProxyEdit: { type: Boolean, default: false },
    adminMemo: { type: String, default: null },
    proxyConfirmation: { type: Object, default: null },
    isSelf: { type: Boolean, default: false },
    options: { type: Object, default: () => ({}) },
});

const form = useForm({ comment: '', steps: [] });
// ---- 社員情報との照合: 差異のあるグループごとに、どちらが正しいか選ぶ ----
const syncForm = useForm({ decisions: {} });
const reopen = ref(false);
const differing = computed(() => Object.entries(props.profileDiff?.groups ?? {}).filter(([, g]) => g.differs));
const allDecided = computed(() => differing.value.length > 0 && differing.value.every(([k]) => syncForm.decisions[k]));
const showSyncForm = computed(() => props.canApplySync && differing.value.length > 0 && (['diff', 'unregistered'].includes(props.profileDiff?.status) || reopen.value));
function applySync() {
    const updates = differing.value.filter(([k]) => syncForm.decisions[k] === 'update').map(([, g]) => g.label);
    const msg = updates.length ? `社員情報を更新します（${updates.join('・')}）。よろしいですか？` : '社員情報はそのまま変更せず、確認済みにします。よろしいですか？';
    if (!confirm(msg)) return;
    syncForm.post(route('clerk.general_affairs.nencho.submissions.profile_sync', { submission: props.submission.id }), { preserveScroll: true, onSuccess: () => { reopen.value = false; syncForm.reset(); } });
}
const memoForm = useForm({ admin_memo: props.adminMemo ?? '' });
function saveMemo() {
    memoForm.post(route('clerk.general_affairs.nencho.submissions.memo', { submission: props.submission.id }), { preserveScroll: true });
}
const badge = { draft: 'bg-amber-100 text-amber-800', submitted: 'bg-green-100 text-green-700', returned: 'bg-red-100 text-red-700' };

function giveBack() {
    if (!confirm('差し戻します。従業員は、内容を修正してもう一度提出します（締切後でも修正できます）。よろしいですか？')) return;
    form.post(route('clerk.general_affairs.nencho.submissions.return', { submission: props.submission.id }), { preserveScroll: true });
}
</script>

<template>
    <AppLayout :title="`${submission.user_name ?? submission.name} さんの年末調整`">
        <template #header>
            <div class="flex flex-wrap items-center gap-3">
                <Link :href="route('clerk.general_affairs.nencho.campaigns.show', { campaign: campaign.id })" class="whitespace-nowrap rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300">
                    ← 回収状況に戻る
                </Link>
                <h2 class="text-base font-semibold text-gray-800 sm:text-xl">{{ submission.user_name ?? submission.name }} さんの年末調整</h2>
                <span class="rounded-full px-3 py-1 text-xs font-medium" :class="badge[submission.status]">{{ statuses[submission.status] ?? submission.status }}</span>
            </div>
        </template>
        <template #tabs>
            <ClerkNavigationTabs active="applications" />
        </template>

        <div class="space-y-6">
            <div class="rounded bg-white p-6 shadow">
                <p class="mb-4 text-sm text-gray-600">{{ campaign.company_name }}　{{ campaign.title }}　<span v-if="submission.submitted_at">提出日時：{{ String(submission.submitted_at).replace('T', ' ').slice(0, 16) }}</span>　入力者：{{ submission.entered_by_type === 'proxy' ? '総務の代理' : '本人' }}</p>
                <SubmissionSummary :submission="submission" :spouse="spouse" :dependents="dependents" :insurance-items="insuranceItems" :options="options" />
            </div>

            <!-- 社員情報との照合（提出済みの申告だけ） -->
            <div v-if="profileDiffStatus" class="rounded bg-white p-6 shadow">
                <h3 class="mb-3 text-base font-semibold text-gray-800">社員情報との照合</h3>
                <p v-if="profileDiffStatus === 'matched'" class="rounded bg-green-50 p-3 text-sm text-green-800">✓ 申告の本人情報（氏名・生年月日・住所・世帯主）は、社員情報と一致しています。</p>
                <p v-else-if="!profileDiff" class="rounded bg-amber-50 p-3 text-sm text-amber-800">社員情報との差異があります。内容を見るには、「社員情報を見る」権限が必要です。</p>
                <template v-else>
                    <div v-if="profileDiff.status === 'resolved'" class="mb-3 rounded bg-blue-50 p-3 text-sm text-blue-800">
                        差異を確認して対応済みです（{{ profileDiff.resolved?.resolved_by }}　{{ profileDiff.resolved?.resolved_at }}）。
                        <button v-if="canApplySync && !reopen" type="button" class="ml-2 text-xs underline hover:no-underline" @click="reopen = true">選び直す</button>
                    </div>
                    <p v-else-if="profileDiff.status === 'unregistered'" class="mb-3 rounded bg-amber-50 p-3 text-sm text-amber-800">⚠ この人の社員情報は、まだ登録されていません。申告の内容で登録できます。</p>
                    <p v-else-if="profileDiff.status === 'diff'" class="mb-3 rounded bg-red-50 p-3 text-sm text-red-700">⚠ 申告の内容と、社員情報に差異があります。どちらが正しいか、選んでください。</p>

                    <div v-if="differing.length" class="space-y-4">
                        <div v-for="[key, g] in differing" :key="key" class="rounded border border-gray-200">
                            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 bg-gray-50 px-3 py-2">
                                <p class="text-sm font-semibold text-gray-800">{{ g.label }}</p>
                                <div v-if="showSyncForm" class="flex flex-wrap gap-4 text-sm">
                                    <label class="flex items-center gap-1 text-gray-700"><input v-model="syncForm.decisions[key]" type="radio" :name="`d-${key}`" value="update" class="text-purple-600 focus:ring-purple-500" />申告が正しい（社員情報を更新）</label>
                                    <label class="flex items-center gap-1 text-gray-700"><input v-model="syncForm.decisions[key]" type="radio" :name="`d-${key}`" value="keep" class="text-purple-600 focus:ring-purple-500" />社員情報が正しい（変更しない）</label>
                                </div>
                            </div>
                            <table class="min-w-full text-sm">
                                <thead><tr class="text-left text-xs text-gray-500"><th class="px-3 py-2">項目</th><th class="px-3 py-2">社員情報（いま）</th><th class="px-3 py-2">申告（従業員の入力）</th></tr></thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="(f, fk) in g.fields" :key="fk" :class="f.differs ? 'bg-amber-50' : ''">
                                        <td class="whitespace-nowrap px-3 py-2 text-gray-600">{{ f.label }}</td>
                                        <td class="px-3 py-2" :class="f.differs ? 'font-medium text-gray-800' : 'text-gray-500'">{{ f.profile ?? '（未登録）' }}</td>
                                        <td class="px-3 py-2" :class="f.differs ? 'font-medium text-gray-800' : 'text-gray-500'">{{ f.submission ?? '（なし）' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p v-if="syncForm.errors.decisions" class="text-xs text-red-600">{{ syncForm.errors.decisions }}</p>
                        <div v-if="showSyncForm" class="flex items-center gap-3">
                            <button :disabled="!allDecided || syncForm.processing" class="rounded bg-purple-600 px-5 py-2 text-sm font-medium text-white hover:bg-purple-700 disabled:cursor-not-allowed disabled:opacity-50" @click="applySync">選んだ内容で反映する</button>
                            <span v-if="!allDecided" class="text-xs text-gray-500">すべての項目について、どちらが正しいか選ぶと、押せます。</span>
                        </div>
                        <p v-else-if="!canApplySync" class="text-xs text-gray-500">社員情報を更新するには、「社員情報を編集する」権限が必要です。</p>
                    </div>
                </template>
            </div>

            <div v-if="editLock" class="rounded border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
                いま <span class="font-semibold">{{ editLock.name }}さん（{{ editLock.mode_label }}）</span>が編集中です（{{ editLock.since }} から）。同時に編集すると、入力が上書きされることがあります。
            </div>

            <div v-if="canProxyEdit" class="flex flex-wrap items-center justify-between gap-3 rounded border border-purple-200 bg-purple-50 p-4">
                <p class="text-sm text-purple-900">この申告は、まだ提出されていません。総務が、同じフォームで代わりに入力できます（代理入力）。</p>
                <Link :href="route('clerk.general_affairs.nencho.proxy.start', { campaign: campaign.id, user: submission.user_id })" class="rounded bg-purple-600 px-4 py-2 text-sm font-medium text-white hover:bg-purple-700">代理で入力・修正する</Link>
            </div>

            <div v-if="proxyConfirmation" class="rounded border border-gray-200 bg-white p-6 shadow">
                <h3 class="mb-2 text-base font-semibold text-gray-800">代理入力の本人確認の記録</h3>
                <ul class="space-y-1 text-sm text-gray-700">
                    <li>紙の申告書を受け取った：{{ proxyConfirmation.paper ? '✓ はい' : '—' }}</li>
                    <li>本人に内容を確認した：{{ proxyConfirmation.person ? '✓ はい' : '—' }}</li>
                    <li v-if="proxyConfirmation.note">メモ：{{ proxyConfirmation.note }}</li>
                    <li class="text-xs text-gray-500">記録した人：{{ proxyConfirmation.confirmed_by }}　{{ proxyConfirmation.confirmed_at }}</li>
                </ul>
            </div>

            <div class="rounded border border-amber-200 bg-white p-6 shadow">
                <h3 class="mb-1 text-base font-semibold text-amber-900">管理者メモ（総務だけが見られます）</h3>
                <p class="mb-3 text-xs text-gray-500">従業員の画面には表示されません。確認のポイントや、連絡の記録などに使えます。</p>
                <form class="space-y-3" @submit.prevent="saveMemo">
                    <textarea v-model="memoForm.admin_memo" rows="3" maxlength="2000" class="w-full rounded border-gray-300 text-sm focus:border-amber-500 focus:ring-amber-500" placeholder="例：6/12 電話で住所の変更を確認済み。扶養親族の所得の証明待ち。"></textarea>
                    <p class="text-xs text-red-600">{{ memoForm.errors.admin_memo }}</p>
                    <button :disabled="memoForm.processing" class="rounded bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700 disabled:opacity-50">{{ memoForm.processing ? '保存中…' : 'メモを保存' }}</button>
                </form>
            </div>

            <div v-if="canReturn" class="rounded border border-red-200 bg-white p-6 shadow">
                <h3 class="mb-2 text-base font-semibold text-red-800">差し戻す</h3>
                <p class="mb-3 text-sm text-gray-600">修正してほしい点を、コメントで伝えてください。従業員の画面に表示され、締切後でも修正できます。<span v-if="isSelf" class="font-medium text-red-700">（これはあなた自身の提出物です。差し戻すと、あなたが修正できる状態になります。）</span></p>
                <form class="space-y-4" @submit.prevent="giveBack">
                    <div>
                        <label for="return-comment" class="mb-1 block text-sm font-medium text-gray-700">コメント <span class="text-red-600">*</span></label>
                        <textarea id="return-comment" v-model="form.comment" rows="4" maxlength="1000" class="w-full rounded border-gray-300 text-sm focus:border-red-500 focus:ring-red-500" placeholder="例：扶養親族の所得の見積額が未入力です。確認して入力してください。"></textarea>
                        <p class="mt-1 text-xs text-red-600">{{ form.errors.comment }}</p>
                    </div>
                    <fieldset>
                        <legend class="mb-1 text-sm font-medium text-gray-700">直してほしい工程（任意。選ぶと従業員の画面で ⚠ が付きます）</legend>
                        <div class="flex flex-wrap gap-4">
                            <label v-for="st in steps" :key="st.no" class="flex items-center gap-2 text-sm text-gray-700">
                                <input v-model="form.steps" type="checkbox" :value="st.no" class="rounded text-red-600 focus:ring-red-500" />{{ st.no }}. {{ st.label }}
                            </label>
                        </div>
                    </fieldset>
                    <button :disabled="form.processing" class="rounded bg-red-600 px-5 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50">{{ form.processing ? '処理中…' : '差し戻す' }}</button>
                </form>
            </div>

            <div class="rounded bg-white p-6 shadow">
                <h3 class="mb-3 text-base font-semibold text-gray-800">操作履歴</h3>
                <ul v-if="events.length" class="divide-y divide-gray-100 text-sm">
                    <li v-for="e in events" :key="e.id" class="py-2">
                        <span class="text-gray-500">{{ e.created_at }}</span>　<span class="font-medium text-gray-800">{{ e.actor_name }}</span>　{{ eventActions[e.action] ?? e.action }}
                        <span v-if="e.steps?.length" class="text-xs text-gray-500">（工程 {{ e.steps.join('・') }}）</span>
                        <p v-if="e.comment" class="mt-1 whitespace-pre-wrap text-gray-600">{{ e.comment }}</p>
                        <p v-if="e.confirmation" class="mt-1 text-xs text-gray-500">本人確認の記録：{{ e.confirmation.paper ? '紙を受け取った' : '' }}{{ e.confirmation.paper && e.confirmation.person ? '・' : '' }}{{ e.confirmation.person ? '本人に確認した' : '' }}{{ e.confirmation.note ? `（${e.confirmation.note}）` : '' }}</p>
                    </li>
                </ul>
                <p v-else class="text-sm text-gray-500">履歴はまだありません。</p>
            </div>
        </div>
    </AppLayout>
</template>
