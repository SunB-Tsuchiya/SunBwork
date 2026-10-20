<script setup>
import { ref, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import ClerkNavigationTabs from '@/Components/Tabs/ClerkNavigationTabs.vue';

const props = defineProps({
    routeBase:     { type: String, required: true },
    month:         { type: String, required: true },
    months:        { type: Array, required: true },
    status:        { type: String, default: null },
    statusLabels:  { type: Object, required: true },
    statuses:      { type: Array, required: true },
    statusSummary: { type: Object, default: () => ({}) },
    groups:        { type: Array, default: () => [] },
    grandTotal:    { type: Number, default: 0 },
    deadlineEnforced: { type: Boolean, default: true },
    deadlineRequests: { type: Array, default: () => [] },
    deadlines:     { type: Array, default: () => [] },
    applicants:    { type: Array, default: () => [] },
    pastMonths:    { type: Array, default: () => [] },
});

const statusColor = {
    submitted: 'bg-blue-100 text-blue-700',
    returned: 'bg-red-100 text-red-700',
    approved: 'bg-green-100 text-green-700',
    billed: 'bg-purple-100 text-purple-700',
};

function monthLabel(ym) {
    const [y, m] = ym.split('-');
    return `${y}年${parseInt(m)}月分`;
}
function go(params) {
    router.get(route(props.routeBase + 'index'), { month: props.month, status: props.status, ...params }, { preserveScroll: true });
}
const selectedMonth = ref(props.month);
const totalCount = computed(() => Object.values(props.statusSummary).reduce((s, v) => s + v.count, 0));
const summaryUrl = computed(() => route(props.routeBase + 'summary', { month: props.month }));

// ─── 期限（当月末日）の解除 ───
const grant = ref({ user_id: '', billing_month: '', reason: '' });
const grantErrors = ref({});
const granting = ref(false);
function submitGrant() {
    grantErrors.value = {};
    granting.value = true;
    router.post(route(props.routeBase + 'deadlines.store'), grant.value, {
        preserveScroll: true,
        onSuccess: () => { grant.value = { user_id: '', billing_month: '', reason: '' }; },
        onError: (e) => { grantErrors.value = e; },
        onFinish: () => { granting.value = false; },
    });
}
// 依頼の許可・却下（コメントは任意）
const comments = ref({});
function decide(d, action) {
    const label = action === 'approve' ? '許可' : '却下';
    if (!confirm(`${d.user_name} さんの ${monthLabel(d.billing_month)}の期限後の申請を、${label}します。よろしいですか？`)) return;
    router.post(route(props.routeBase + 'deadlines.' + action, { extension: d.id }), { comment: comments.value[d.id] ?? '' }, { preserveScroll: true });
}
function revoke(d) {
    if (!confirm(`${d.user_name} さんの ${monthLabel(d.billing_month)}の期限の解除を、取り消します。よろしいですか？`)) return;
    router.delete(route(props.routeBase + 'deadlines.destroy', { extension: d.id }), { preserveScroll: true });
}

function quickApprove(row) {
    if (!confirm(`${row.user_name} さんの交通費（${row.total_amount.toLocaleString()} 円）を受理します。よろしいですか？`)) return;
    router.post(route(props.routeBase + 'approve', { expense: row.id }), {}, { preserveScroll: true });
}
</script>

<template>
    <AppLayout title="交通費精算">
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('clerk.applications.index')"
                    class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300 whitespace-nowrap">← 申請に戻る</Link>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">交通費精算</h2>
            </div>
        </template>

        <template #headerExtras>
            <div class="flex flex-wrap gap-2">
                <Link :href="route(routeBase + 'fares.index')"
                    class="rounded bg-white px-4 py-2 text-sm font-medium text-purple-800 ring-1 ring-purple-300 hover:bg-purple-50">運賃マスタ</Link>
                <a :href="summaryUrl"
                    class="rounded bg-purple-100 px-4 py-2 text-sm font-medium text-purple-800 hover:bg-purple-200">
                    月次集計をCSVで保存
                </a>
            </div>
        </template>

        <template #tabs>
            <ClerkNavigationTabs active="applications" />
        </template>

        <div class="space-y-6">
            <!-- 期限後の申請の依頼（申請者から）。許可・却下するだけ -->
            <div v-if="deadlineRequests.length" class="rounded border-2 border-amber-300 bg-amber-50 p-5 shadow">
                <h3 class="mb-1 text-base font-semibold text-amber-900">期限後の申請の依頼が {{ deadlineRequests.length }}件 あります</h3>
                <p class="mb-3 text-xs text-amber-800">交通費は、当月末日を過ぎると申請できません。申請者から、期限後の申請の依頼が来ています。許可すると、その人は、その月を入力・申請できます（本人に、お知らせで通知されます）。</p>
                <div class="space-y-3">
                    <div v-for="d in deadlineRequests" :key="d.id" class="rounded bg-white p-3">
                        <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                            <span class="font-semibold text-gray-900">{{ d.user_name }}</span>
                            <span class="text-sm text-gray-700">{{ monthLabel(d.billing_month) }}</span>
                            <span class="text-xs text-gray-500">依頼 {{ d.requested_at }}</span>
                        </div>
                        <p class="mt-1 whitespace-pre-wrap text-sm text-gray-700">理由: {{ d.reason }}</p>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <input type="text" v-model="comments[d.id]" maxlength="255" class="w-64 rounded border border-gray-300 px-2 py-1.5 text-sm focus:border-purple-400 focus:outline-none" />
                            <button type="button" @click="decide(d, 'approve')" class="rounded bg-purple-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-purple-700">許可</button>
                            <button type="button" @click="decide(d, 'reject')" class="rounded border border-red-300 bg-white px-4 py-1.5 text-sm text-red-700 hover:bg-red-50">却下</button>
                        </div>
                        <p class="mt-0.5 text-xs text-gray-400">コメント（任意。本人に伝わります）　例：　今回は許可します。次回からは、期限内にお願いします。</p>
                    </div>
                </div>
            </div>

            <div class="rounded bg-white p-6 shadow">
                <!-- 対象月 -->
                <div class="mb-4 flex flex-wrap items-center gap-3">
                    <label class="text-sm font-medium text-gray-700">対象月</label>
                    <select v-model="selectedMonth" @change="go({ month: selectedMonth })"
                        class="rounded border border-gray-300 px-3 py-1.5 text-sm focus:border-purple-400 focus:outline-none">
                        <option v-for="m in months" :key="m" :value="m">{{ monthLabel(m) }}</option>
                    </select>
                    <span class="text-sm text-gray-500">申請された交通費の一覧です（下書きは表示されません）。</span>
                </div>

                <!-- 状態ごとの件数・金額（クリックで絞り込み） -->
                <div class="mb-6 flex flex-wrap gap-2">
                    <button type="button" @click="go({ status: null })"
                        class="rounded border px-3 py-2 text-left text-sm"
                        :class="!status ? 'border-purple-400 bg-purple-50' : 'border-gray-200 hover:bg-gray-50'">
                        <div class="text-xs text-gray-500">すべて</div>
                        <div class="font-semibold text-gray-800">{{ totalCount }} 件</div>
                    </button>
                    <button v-for="st in statuses" :key="st" type="button" @click="go({ status: st })"
                        class="rounded border px-3 py-2 text-left text-sm"
                        :class="status === st ? 'border-purple-400 bg-purple-50' : 'border-gray-200 hover:bg-gray-50'">
                        <div class="text-xs"><span class="rounded px-1.5 py-0.5" :class="statusColor[st]">{{ statusLabels[st] }}</span></div>
                        <div class="mt-1 font-semibold text-gray-800">{{ statusSummary[st]?.count ?? 0 }} 件
                            <span class="ml-1 text-xs font-normal text-gray-500">{{ (statusSummary[st]?.amount ?? 0).toLocaleString() }} 円</span>
                        </div>
                    </button>
                </div>

                <div v-if="!groups.length" class="py-12 text-center text-gray-400">
                    {{ monthLabel(month) }}の申請はありません。
                </div>

                <!-- 請求先部門ごと -->
                <div v-else class="space-y-5">
                    <div v-for="g in groups" :key="g.department_name" class="rounded border border-gray-200">
                        <div class="flex items-center justify-between rounded-t bg-gray-50 px-4 py-2.5">
                            <h3 class="font-semibold text-gray-800">{{ g.department_name }}</h3>
                            <span class="text-sm text-gray-600">部門合計: <strong>{{ g.total_amount.toLocaleString() }} 円</strong></span>
                        </div>
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 text-xs text-gray-500">
                                    <th class="px-4 py-2 text-left">氏名</th>
                                    <th class="px-4 py-2 text-center">状態</th>
                                    <th class="px-4 py-2 text-right">件数</th>
                                    <th class="px-4 py-2 text-right">合計金額</th>
                                    <th class="px-4 py-2 text-left">申請日時</th>
                                    <th class="px-4 py-2 text-center">操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in g.rows" :key="row.id" class="border-b border-gray-100 last:border-0">
                                    <td class="px-4 py-2.5 text-gray-800">
                                        {{ row.user_name }}
                                        <span class="ml-1 text-xs text-gray-400">No.{{ row.id }}</span>
                                        <span v-if="row.issues.diff" class="ml-2 rounded bg-red-100 px-1.5 py-0.5 text-xs font-medium text-red-700" title="運賃マスタの運賃と違う行">運賃相違 {{ row.issues.diff }}</span>
                                        <span v-if="row.issues.warn" class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-800" title="重複・休日・金額などの注意がある行">注意 {{ row.issues.warn }}</span>
                                        <span v-if="row.issues.unverified" class="ml-1 rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600" title="運賃マスタが未検査・登録なしの行">未検査 {{ row.issues.unverified }}</span>
                                    </td>
                                    <td class="px-4 py-2.5 text-center">
                                        <span class="rounded px-2 py-0.5 text-xs font-medium" :class="statusColor[row.status]">{{ statusLabels[row.status] }}</span>
                                    </td>
                                    <td class="px-4 py-2.5 text-right text-gray-600">{{ row.item_count }} 行</td>
                                    <td class="px-4 py-2.5 text-right font-medium text-gray-900">{{ row.total_amount.toLocaleString() }} 円</td>
                                    <td class="px-4 py-2.5 text-gray-600">{{ row.submitted_at }}</td>
                                    <td class="px-4 py-2.5 text-center">
                                        <div class="flex justify-center gap-1">
                                            <Link :href="route(routeBase + 'show', { expense: row.id })"
                                                class="rounded bg-purple-50 px-3 py-1 text-xs font-medium text-purple-700 hover:bg-purple-100">詳細</Link>
                                            <button v-if="row.status === 'submitted'" type="button" @click="quickApprove(row)"
                                                class="rounded bg-purple-600 px-3 py-1 text-xs font-medium text-white hover:bg-purple-700">受理</button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-right text-sm text-gray-700">全体の合計: <strong class="text-base">{{ grandTotal.toLocaleString() }} 円</strong></div>
                </div>
            </div>

            <!-- 期限後の申請の履歴（許可・却下）と、依頼なしの直接の許可 -->
            <div v-if="deadlineEnforced" class="rounded bg-white p-6 shadow">
                <h3 class="mb-1 text-base font-semibold text-gray-800">期限後の申請の履歴</h3>
                <p class="mb-3 text-xs text-gray-500">交通費は、当月末日を過ぎると申請できません（差戻しされたものは、直して再申請できます）。申請者が依頼して、ここで許可すると、その人だけ、その月を入力・申請できます。</p>

                <div v-if="deadlines.length" class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-xs text-gray-500">
                                <th class="px-2 py-2 text-left">人</th><th class="px-2 py-2 text-left">月</th><th class="px-2 py-2 text-left">結果</th>
                                <th class="px-2 py-2 text-left">理由・コメント</th><th class="px-2 py-2 text-left">決めた人・日時</th><th class="px-2 py-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="d in deadlines" :key="d.id" class="border-b border-gray-100 align-top">
                                <td class="px-2 py-1.5 text-gray-800">{{ d.user_name }}</td>
                                <td class="px-2 py-1.5 text-gray-700">{{ monthLabel(d.billing_month) }}</td>
                                <td class="px-2 py-1.5"><span class="rounded px-2 py-0.5 text-xs font-medium" :class="d.status === 'granted' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'">{{ d.status === 'granted' ? '許可' : '却下' }}</span></td>
                                <td class="px-2 py-1.5 text-xs text-gray-600">
                                    <div v-if="d.reason">理由: {{ d.reason }}</div>
                                    <div v-if="d.decision_comment">コメント: {{ d.decision_comment }}</div>
                                </td>
                                <td class="px-2 py-1.5 text-xs text-gray-500">{{ d.granted_by }}（{{ d.created_at }}）</td>
                                <td class="px-2 py-1.5 text-right"><button v-if="d.status === 'granted'" type="button" @click="revoke(d)" class="rounded px-2 py-1 text-xs text-red-600 hover:bg-red-50">取り消す</button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="text-xs text-gray-400">許可・却下の履歴は、まだありません。</p>

                <details class="mt-4 rounded border border-gray-200 p-3">
                    <summary class="cursor-pointer text-sm text-gray-700">依頼なしで、直接許可する</summary>
                    <p class="mt-2 text-xs text-gray-500">申請者から依頼が来ていなくても、人と月を選んで、直接、期限を解除できます。</p>
                    <div class="mt-2 flex flex-wrap items-end gap-3">
                        <div>
                            <label class="mb-1 block text-xs text-gray-600">許可する人</label>
                            <select v-model="grant.user_id" class="w-48 rounded border border-gray-300 px-2 py-1.5 text-sm focus:border-purple-400 focus:outline-none">
                                <option value="">選んでください</option>
                                <option v-for="a in applicants" :key="a.id" :value="a.id">{{ a.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs text-gray-600">対象の月（期限を過ぎた月）</label>
                            <select v-model="grant.billing_month" class="w-40 rounded border border-gray-300 px-2 py-1.5 text-sm focus:border-purple-400 focus:outline-none">
                                <option value="">選んでください</option>
                                <option v-for="m in pastMonths" :key="m" :value="m">{{ monthLabel(m) }}</option>
                            </select>
                        </div>
                        <div class="min-w-[14rem] flex-1">
                            <label class="mb-1 block text-xs text-gray-600">理由（記録に残ります）</label>
                            <input type="text" v-model="grant.reason" maxlength="255" class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm focus:border-purple-400 focus:outline-none" />
                        </div>
                        <button type="button" :disabled="granting" @click="submitGrant" class="rounded bg-purple-600 px-5 py-1.5 text-sm font-medium text-white hover:bg-purple-700 disabled:opacity-50">許可する</button>
                    </div>
                    <div v-if="Object.keys(grantErrors).length" class="mt-2 text-sm text-red-600"><p v-for="(m, k) in grantErrors" :key="k">{{ m }}</p></div>
                </details>
            </div>
        </div>
    </AppLayout>
</template>
