<script setup>
import { ref, computed, nextTick } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import ClerkNavigationTabs from '@/Components/Tabs/ClerkNavigationTabs.vue';
import { shortDate } from '@/Components/Billing/Transport/transportNormalize';

const props = defineProps({
    routeBase:    { type: String, required: true },
    statusLabels: { type: Object, required: true },
    actionLabels: { type: Object, required: true },
    purposes:     { type: Object, default: () => ({}) },
    expense:      { type: Object, required: true },
});

const statusColor = {
    submitted: 'bg-blue-100 text-blue-700',
    returned: 'bg-red-100 text-red-700',
    approved: 'bg-green-100 text-green-700',
    billed: 'bg-purple-100 text-purple-700',
};

const comment = ref('');
const commentEl = ref(null);
const errors = ref({});
const busy = ref(false);

const monthLabel = computed(() => {
    const [y, m] = props.expense.billing_month.split('-');
    return `${y}年${parseInt(m)}月分`;
});
const pdfName = computed(() => props.routeBase + 'pdf');
const excelName = computed(() => props.routeBase + 'excel');

function act(action, confirmMessage) {
    errors.value = {};
    if (action === 'return' && !comment.value.trim()) {
        errors.value = { comment: '差戻しの理由（コメント）を入力してください。' };
        return;
    }
    let message = confirmMessage;
    if (action === 'approve' && sum.value.unchecked > 0) message = `確認済みにしていない行が ${sum.value.unchecked} 行あります。このまま受理しますか？`;
    if (action === 'approve' && sum.value.diff > 0) message = `運賃が台帳と違う行が ${sum.value.diff} 行あります。\n` + message;
    if (!confirm(message)) return;
    busy.value = true;
    router.post(route(props.routeBase + (action === 'return' ? 'return' : action), { expense: props.expense.id }),
        { comment: comment.value }, {
            preserveScroll: true,
            onSuccess: () => { comment.value = ''; },
            onError: (e) => { errors.value = e; },
            onFinish: () => { busy.value = false; },
        });
}

const btn = 'rounded px-5 py-2 text-sm font-medium disabled:opacity-50';

const sum = computed(() => props.expense.check_summary);
const rt = (name, params) => route(props.routeBase + name, params);
const yen = (n) => `${Number(n).toLocaleString()}円`;

// ─── 行ごとの確認チェック ───
function toggleChecked(it, ev) {
    router.post(rt('items.check', { expense: props.expense.id, item: it.id }), { checked: ev.target.checked }, { preserveScroll: true });
}
function checkAll() {
    router.post(rt('items.check_all', { expense: props.expense.id }), {}, { preserveScroll: true });
}
const canCheck = computed(() => ['submitted', 'approved'].includes(props.expense.status));

// ─── 運賃マスタへの登録（行から。確認なしで、申請の金額をその経路の運賃として登録する） ───
function registerFare(it) {
    router.post(rt('items.fare', { expense: props.expense.id, item: it.id }), { action: 'verify' }, { preserveScroll: true, onError: (e) => { errors.value = e; } });
}
// 検算の結果、台帳と一致した行（注意のないもの）を、ボタン 1 つで確認済みにする
const matchedUnchecked = computed(() => props.expense.items.filter((it) =>
    !it.check.checked && it.check.fare.state === 'match' && !it.check.flags.some((f) => f.level === 'warn')).length);
function checkMatched() {
    router.post(rt('items.check_matched', { expense: props.expense.id }), {}, { preserveScroll: true });
}

// ─── 差戻しのコメントに、行を指定して入れる ───
function insertText(text) {
    comment.value = (comment.value && !comment.value.endsWith('\n') ? comment.value + '\n' : comment.value) + text;
    nextTick(() => commentEl.value?.focus());
}
function insertRow(n) { insertText(`${n}行目：`); }
// ─── 同行者の突き合わせ ───
const VERDICTS = {
    match:       { label: '○ 同行者の申請と一致', cls: 'bg-green-100 text-green-800' },
    amount_diff: { label: '△ 金額が違います', cls: 'bg-red-100 text-red-800' },
    route_diff:  { label: '区間が違います（確認してください）', cls: 'bg-amber-100 text-amber-900' },
    candidate:   { label: '同じ日付で、行先が違う行があります（候補）', cls: 'bg-amber-100 text-amber-900' },
    none:        { label: '同行者の申請に、この日の行がありません', cls: 'bg-amber-100 text-amber-900' },
    no_expense:  { label: '同行者の、この月の申請がありません', cls: 'bg-amber-100 text-amber-900' },
    draft:       { label: '同行者の申請は、まだ下書きです', cls: 'bg-gray-100 text-gray-600' },
};
const verdictOf = (v) => VERDICTS[v] ?? { label: v, cls: 'bg-gray-100 text-gray-600' };
const hasReview = (it) => (it.companion_review?.companions?.length ?? 0) + (it.companion_review?.named_by?.length ?? 0) > 0;
const routeText = (r) => [r.station_from, ...(r.via_stations ?? []), r.station_to].filter(Boolean).join(' － ');
const reviewIssues = (it) => (it.companion_review?.companions ?? []).filter((c) => !['match', 'draft'].includes(c.verdict)).length;
const statusLabel = (s) => props.statusLabels[s] ?? s;
/** 運賃の相違・注意のある行を、コメントの文にする */
function rowReasons(it) {
    const out = [];
    const f = it.check.fare;
    if (f.state === 'diff') out.push(`運賃が台帳と違います（申請 ${yen(it.amount)}／台帳 ${yen(f.expected)}、差 ${f.diff > 0 ? '+' : ''}${f.diff}円）`);
    for (const fl of it.check.flags) if (fl.level === 'warn') out.push(fl.label);
    for (const c of it.companion_review?.companions ?? []) if (c.verdict === 'amount_diff') out.push(`同行者（${c.name}さん）の申請と、金額が違います`);
    return out;
}
const flaggedLines = computed(() => props.expense.items.map((it, i) => ({ n: i + 1, reasons: rowReasons(it) })).filter((x) => x.reasons.length));
function insertFlagged() {
    if (!flaggedLines.value.length) return;
    insertText(flaggedLines.value.map((x) => `${x.n}行目：${x.reasons.join('、')}。確認してください。`).join('\n'));
}
</script>

<template>
    <AppLayout title="交通費の確認" :fluid="true">
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route(routeBase + 'index', { month: expense.billing_month })"
                    class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300 whitespace-nowrap">← 一覧に戻る</Link>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">交通費の確認</h2>
            </div>
        </template>

        <template #tabs>
            <ClerkNavigationTabs active="applications" />
        </template>

        <div class="space-y-6">
            <div class="rounded bg-white p-6 shadow">
                <!-- 概要 -->
                <div class="mb-4 flex flex-wrap items-center gap-4">
                    <h3 class="text-lg font-semibold text-gray-900">{{ expense.user_name }} さん　{{ monthLabel }}</h3>
                    <span class="text-xs text-gray-500" title="申請書（PDF・Excel）の右上に、同じ番号が入っています">申請No.{{ expense.id }}</span>
                    <span class="rounded px-2.5 py-1 text-xs font-medium" :class="statusColor[expense.status]">{{ statusLabels[expense.status] }}</span>
                </div>
                <dl class="mb-6 grid grid-cols-1 gap-x-8 gap-y-2 text-sm md:grid-cols-2">
                    <div class="flex gap-3"><dt class="w-24 shrink-0 text-gray-500">会社</dt><dd class="text-gray-800">{{ expense.company_name || '―' }}</dd></div>
                    <div class="flex gap-3"><dt class="w-24 shrink-0 text-gray-500">所属</dt><dd class="text-gray-800">{{ expense.applicant_dept || '―' }}</dd></div>
                    <div class="flex gap-3"><dt class="w-24 shrink-0 text-gray-500">請求先部門</dt><dd class="text-gray-800">{{ expense.charge_dept || '共通' }}</dd></div>
                    <div class="flex gap-3"><dt class="w-24 shrink-0 text-gray-500">申請日時</dt><dd class="text-gray-800">{{ expense.submitted_at || '―' }}</dd></div>
                    <div v-if="expense.reviewed_at" class="flex gap-3"><dt class="w-24 shrink-0 text-gray-500">確認</dt><dd class="text-gray-800">{{ expense.reviewer_name }}（{{ expense.reviewed_at }}）</dd></div>
                    <div v-if="expense.billed_at" class="flex gap-3"><dt class="w-24 shrink-0 text-gray-500">請求済み</dt><dd class="text-gray-800">{{ expense.billed_at }}</dd></div>
                </dl>

                <!-- 検算の要約 -->
                <div class="mb-3 flex flex-wrap items-center gap-2 text-xs">
                    <span class="rounded px-2 py-1" :class="sum.diff ? 'bg-red-100 text-red-700 font-semibold' : 'bg-gray-100 text-gray-500'">運賃の相違 {{ sum.diff }}件</span>
                    <span class="rounded px-2 py-1" :class="sum.warn_rows ? 'bg-amber-100 text-amber-800 font-semibold' : 'bg-gray-100 text-gray-500'">注意の行 {{ sum.warn_rows }}行</span>
                    <span class="rounded px-2 py-1" :class="(sum.unverified + sum.none) ? 'bg-amber-50 text-amber-800' : 'bg-gray-100 text-gray-500'">未検査 {{ sum.unverified + sum.none }}行</span>
                    <span class="rounded bg-green-50 px-2 py-1 text-green-700">一致 {{ sum.match }}行</span>
                    <span class="rounded px-2 py-1" :class="sum.unchecked ? 'bg-blue-50 text-blue-700' : 'bg-green-100 text-green-700'">確認済み {{ sum.total - sum.unchecked }}／{{ sum.total }}行</span>
                    <Link :href="rt('fares.index')" class="ml-auto text-purple-700 underline hover:no-underline">運賃マスタを開く</Link>
                </div>

                <!-- 検算（台帳の運賃で自動計算した合計。電卓は要らない） -->
                <div class="mb-3 rounded border border-purple-200 bg-purple-50 p-3 text-sm">
                    <div class="mb-1 font-semibold text-purple-900">運賃の検算（登録した運賃で、自動で計算します）</div>
                    <p v-if="sum.computed_rows" class="text-gray-800">
                        登録のある {{ sum.computed_rows }}行：　台帳で計算した合計 <strong>{{ yen(sum.expected_total) }}</strong>　／　申請の合計 <strong>{{ yen(sum.claimed_total) }}</strong>
                        <span v-if="sum.claimed_total === sum.expected_total" class="ml-2 font-semibold text-green-700">○ 一致</span>
                        <span v-else class="ml-2 font-semibold text-red-700">△ 差 {{ sum.claimed_total - sum.expected_total > 0 ? '+' : '' }}{{ yen(sum.claimed_total - sum.expected_total) }}</span>
                    </p>
                    <p v-else class="text-gray-600">台帳に登録のある経路が、この申請にはまだありません。</p>
                    <p v-if="sum.uncomputed_rows" class="mt-1 text-amber-800">
                        未検査 {{ sum.uncomputed_rows }}行（申請 {{ yen(sum.uncomputed_amount) }}分）は、まだ計算できません。「この金額で登録」で登録すると、次から自動で検算できます。
                    </p>
                </div>

                <!-- 明細（読み取り専用） -->
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-sm">
                        <thead>
                            <tr class="border-b-2 border-gray-300 bg-gray-50 text-xs text-gray-600">
                                <th class="w-8 px-1 py-2 text-center font-normal text-gray-400">行</th>
                                <th class="w-10 px-1 py-2 text-center">確認</th>
                                <th class="whitespace-nowrap px-2 py-2 text-center">発生日</th>
                                <th class="min-w-[10rem] px-2 py-2 text-left">行先</th>
                                <th class="whitespace-nowrap px-2 py-2 text-left">用件</th>
                                <th class="whitespace-nowrap px-2 py-2 text-left">区間</th>
                                <th class="whitespace-nowrap px-2 py-2 text-center">IC/切符</th>
                                <th class="whitespace-nowrap px-2 py-2 text-right">金額</th>
                                <th class="whitespace-nowrap px-2 py-2 text-left">備考</th>
                                <th class="min-w-[20rem] px-2 py-2 text-left">運賃の照合・注意</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="(it, i) in expense.items" :key="it.id">
                            <tr class="border-b border-gray-100 align-top"
                                :class="it.check.fare.state === 'diff' ? 'bg-red-50' : (it.check.flags.some((f) => f.level === 'warn') || (it.companion_review?.companions ?? []).some((c) => c.verdict === 'amount_diff') ? 'bg-amber-50' : '')">
                                <td class="px-1 py-1.5 text-center text-xs text-gray-400">
                                    <button type="button" class="rounded px-1 hover:bg-purple-100 hover:text-purple-700" title="差戻しのコメントに「○行目：」を入れる" @click="insertRow(i + 1)">{{ i + 1 }}</button>
                                </td>
                                <td class="px-1 py-1.5 text-center">
                                    <input type="checkbox" :checked="it.check.checked" :disabled="!canCheck" @change="toggleChecked(it, $event)" />
                                </td>
                                <td class="whitespace-nowrap px-2 py-1.5 text-center text-gray-700">{{ shortDate(it.occurrence_date) }}</td>
                                <td class="px-2 py-1.5 text-gray-800">{{ it.destination }}</td>
                                <td class="whitespace-nowrap px-2 py-1.5 text-gray-700">{{ it.purpose_label }}</td>
                                <td class="whitespace-nowrap px-2 py-1.5 text-gray-700">
                                    {{ it.station_from }} － <template v-for="(v, k) in (it.via_stations ?? [])" :key="k"><span class="text-xs text-gray-500">{{ v }}</span> － </template>{{ it.station_to }}
                                    <div v-if="(it.via_stations ?? []).length" class="text-xs text-gray-400">経由 {{ it.via_stations.length }}か所</div>
                                </td>
                                <td class="whitespace-nowrap px-2 py-1.5 text-center">
                                    <span class="rounded px-1.5 py-0.5 text-xs" :class="it.fare_type === 'ic' ? 'bg-blue-100 text-blue-700' : 'bg-amber-100 text-amber-700'">{{ it.fare_type === 'ic' ? 'IC' : '切符' }}</span>
                                </td>
                                <!-- 金額: 右揃え（桁と「円」がそろう）。往復などのメモは、別の「備考」の列に -->
                                <td class="whitespace-nowrap px-2 py-1.5 text-right font-medium tabular-nums text-gray-900">{{ it.amount.toLocaleString() }}<span class="ml-0.5 text-xs font-normal text-gray-500">円</span></td>
                                <td class="whitespace-nowrap px-2 py-1.5 text-xs text-gray-500">
                                    <template v-if="it.base_amount">片道 {{ it.base_amount.toLocaleString() }}円 ×2</template>
                                </td>
                                <td class="px-2 py-1.5 text-xs">
                                    <!-- 運賃マスタとの照合 -->
                                    <div v-if="it.check.fare.state === 'match'" class="text-green-700">○ 台帳と一致（片道 {{ yen(it.check.fare.registered) }}<span v-if="it.check.fare.expected !== it.check.fare.registered"> → {{ yen(it.check.fare.expected) }}</span>）</div>
                                    <div v-if="it.check.fare.via_used && it.check.fare.legs?.length" class="mt-0.5 text-gray-500">
                                        区間ごとの合計:
                                        <span v-for="(lg, k) in it.check.fare.legs" :key="k" class="mr-1 whitespace-nowrap">{{ lg.from }}－{{ lg.to }} {{ lg.fare !== null ? yen(lg.fare) : '台帳に無し' }}</span>
                                    </div>
                                    <div v-else-if="it.check.fare.state === 'diff'" class="font-semibold text-red-700">
                                        △ 台帳 {{ yen(it.check.fare.expected) }}／申請 {{ yen(it.amount) }}（差 {{ it.check.fare.diff > 0 ? '+' : '' }}{{ it.check.fare.diff }}円）
                                    </div>
                                    <div v-else-if="it.check.fare.state !== 'skip'" class="text-amber-800">
                                        未検査<span class="text-gray-500">（台帳に登録がありません）</span>
                                        <button type="button" @click="registerFare(it)"
                                            class="ml-1 rounded border border-green-400 bg-white px-2 py-0.5 text-green-700 hover:bg-green-50">この金額で登録</button>
                                    </div>
                                    <div v-if="it.check.fare.recheck" class="mt-0.5"><span class="rounded bg-amber-200 px-1.5 py-0.5 text-amber-900">登録から1年以上</span></div>
                                    <!-- 入力の内容からの注意（重複・休日・金額など） -->
                                    <div v-for="(fl, k) in it.check.flags" :key="k" class="mt-1">
                                        <span class="rounded px-1.5 py-0.5" :class="fl.level === 'warn' ? 'bg-amber-100 text-amber-900' : 'bg-gray-100 text-gray-600'" :title="fl.detail">{{ fl.label }}</span>
                                        <span class="ml-1 text-gray-500">{{ fl.detail }}</span>
                                    </div>
                                </td>
                            </tr>
                            <!-- 同行者の突き合わせ（日付・行先が同じ行を、同行者の申請から探した結果） -->
                            <tr v-if="hasReview(it)" class="border-b border-gray-100 bg-gray-50/60">
                                <td></td>
                                <td colspan="9" class="px-2 pb-2 pt-0.5 text-xs">
                                    <div v-for="c in it.companion_review.companions" :key="'c' + c.user_id" class="mt-1">
                                        <span class="font-medium text-gray-700">同行者 {{ c.name }}さん</span>
                                        <span class="ml-1 rounded px-1.5 py-0.5" :class="verdictOf(c.verdict).cls">{{ verdictOf(c.verdict).label }}</span>
                                        <Link v-if="c.expense_id" :href="rt('show', { expense: c.expense_id })" class="ml-1 text-purple-700 hover:underline">申請を開く（{{ statusLabel(c.status) }}）</Link>
                                        <div v-for="r in c.rows" :key="r.id" class="ml-3 mt-0.5 text-gray-600">
                                            └ {{ r.destination }}／{{ routeText(r) }}／{{ r.fare_type === 'ic' ? 'IC' : '切符' }} {{ yen(r.amount) }}<span v-if="r.base_amount">（片道 {{ yen(r.base_amount) }} ×2）</span>
                                            <span v-if="r.fare_state === 'diff'" class="ml-1 text-red-700">（台帳 {{ yen(r.fare_expected) }} と違う）</span>
                                            <span v-else-if="r.fare_state === 'match'" class="ml-1 text-green-700">（台帳と一致）</span>
                                            <span v-if="r.verdict && r.verdict !== 'match' && c.rows.length > 1" class="ml-1 rounded px-1 py-0.5" :class="verdictOf(r.verdict).cls">{{ verdictOf(r.verdict).label }}</span>
                                        </div>
                                    </div>
                                    <div v-for="n in it.companion_review.named_by" :key="'n' + n.expense_id" class="mt-1">
                                        <span class="font-medium text-gray-700">{{ n.applicant_name }}さんの申請で、この人が同行者に選ばれています</span>
                                        <span class="ml-1 rounded px-1.5 py-0.5" :class="verdictOf(n.verdict).cls">{{ verdictOf(n.verdict).label }}</span>
                                        <Link :href="rt('show', { expense: n.expense_id })" class="ml-1 text-purple-700 hover:underline">申請を開く（{{ statusLabel(n.status) }}）</Link>
                                        <div class="ml-3 mt-0.5 text-gray-600">
                                            └ {{ n.row.destination }}／{{ routeText(n.row) }}／{{ n.row.fare_type === 'ic' ? 'IC' : '切符' }} {{ yen(n.row.amount) }}<span v-if="n.row.base_amount">（片道 {{ yen(n.row.base_amount) }} ×2）</span>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            </template>
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-gray-300">
                                <td colspan="7" class="px-2 py-2 text-right text-sm font-medium text-gray-700">合計（{{ expense.items.length }}行）</td>
                                <td class="whitespace-nowrap px-2 py-2 text-right font-bold tabular-nums text-gray-900">{{ expense.total_amount.toLocaleString() }}<span class="ml-0.5 text-xs font-normal text-gray-500">円</span></td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- 確認の操作（表を見終えたところで、まとめて押せるよう、表の下に置く） -->
                <div v-if="canCheck" class="mt-4 flex flex-wrap items-center gap-3 rounded border border-purple-200 bg-purple-50 p-3">
                    <span class="text-sm text-gray-700">確認済み <strong>{{ sum.total - sum.unchecked }}／{{ sum.total }}行</strong></span>
                    <button v-if="matchedUnchecked" type="button" @click="checkMatched"
                        class="rounded bg-purple-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-purple-700">台帳と一致した行を、まとめて確認済みにする（{{ matchedUnchecked }}行）</button>
                    <button v-if="sum.unchecked" type="button" @click="checkAll"
                        class="rounded border border-purple-300 bg-white px-4 py-1.5 text-sm text-purple-700 hover:bg-purple-50">すべて確認済みにする</button>
                    <span v-if="!sum.unchecked" class="text-sm font-medium text-green-700">すべての行を確認しました。</span>
                    <span v-else-if="sum.diff" class="text-xs text-red-700">運賃が台帳と違う行が {{ sum.diff }} 行あります。</span>
                </div>

                <!-- 出力 -->
                <div class="mt-5 flex flex-wrap gap-2">
                    <a :href="route(pdfName, { expense: expense.id }) + '?mode=inline'" target="_blank"
                        class="rounded bg-purple-600 px-4 py-2 text-sm font-medium text-white hover:bg-purple-700">印刷用PDFを開く</a>
                    <a :href="route(pdfName, { expense: expense.id })"
                        class="rounded border border-purple-400 bg-white px-4 py-2 text-sm font-medium text-purple-700 hover:bg-purple-50">PDFを保存</a>
                    <a :href="route(excelName, { expense: expense.id })"
                        class="rounded border border-green-500 bg-white px-4 py-2 text-sm font-medium text-green-700 hover:bg-green-50">Excelを保存</a>
                </div>
            </div>

            <!-- 操作 -->
            <div v-if="expense.can.approve || expense.can.bill || expense.can.unapprove || expense.can.unbill"
                class="rounded bg-white p-6 shadow">
                <h3 class="mb-3 text-base font-semibold text-gray-800">操作</h3>

                <div v-if="expense.can.approve || expense.can.return" class="mb-3">
                    <label class="mb-1 block text-xs text-gray-600">
                        コメント（差戻しのときは必須。受理のときは任意。申請者にメッセージで届きます）
                    </label>
                    <textarea ref="commentEl" v-model="comment" rows="3" maxlength="1000"
                        class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-purple-400 focus:outline-none"></textarea>
                    <div class="mt-1 flex flex-wrap items-center gap-2">
                        <button v-if="flaggedLines.length" type="button" @click="insertFlagged"
                            class="rounded border border-purple-300 bg-white px-3 py-1 text-xs text-purple-700 hover:bg-purple-50">相違・注意のある行を、コメントに入れる（{{ flaggedLines.length }}行）</button>
                        <span class="text-xs text-gray-500">表の行番号を押すと、「○行目：」が入ります。例：　3行目：金額が違うようです。確認して再申請してください。</span>
                    </div>
                </div>
                <div v-if="Object.keys(errors).length" class="mb-3 rounded bg-red-50 p-3 text-sm text-red-600">
                    <ul class="list-inside list-disc"><li v-for="(m, k) in errors" :key="k">{{ m }}</li></ul>
                </div>

                <div class="flex flex-wrap gap-3">
                    <button v-if="expense.can.approve" type="button" :disabled="busy" :class="btn + ' bg-purple-600 text-white hover:bg-purple-700'"
                        @click="act('approve', '受理します。よろしいですか？')">受理</button>
                    <button v-if="expense.can.return" type="button" :disabled="busy" :class="btn + ' border border-red-400 bg-white text-red-700 hover:bg-red-50'"
                        @click="act('return', '差戻しします。申請者に通知されます。よろしいですか？')">差戻し</button>
                    <button v-if="expense.can.bill" type="button" :disabled="busy" :class="btn + ' bg-purple-600 text-white hover:bg-purple-700'"
                        @click="act('bill', '請求済みにします。よろしいですか？')">請求済みにする</button>
                    <button v-if="expense.can.unapprove" type="button" :disabled="busy" :class="btn + ' border border-gray-300 bg-white text-gray-700 hover:bg-gray-50'"
                        @click="act('unapprove', '受理を取り消して、申請済みに戻します。よろしいですか？')">受理を取り消す</button>
                    <button v-if="expense.can.unbill" type="button" :disabled="busy" :class="btn + ' border border-gray-300 bg-white text-gray-700 hover:bg-gray-50'"
                        @click="act('unbill', '請求済みを取り消して、受理に戻します。よろしいですか？')">請求済みを取り消す</button>
                </div>
            </div>
            <div v-else-if="expense.status === 'returned'" class="rounded border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                差戻し済みです。申請者が修正して再申請するのを待っています。
            </div>

            <!-- 履歴 -->
            <div class="rounded bg-white p-6 shadow">
                <h3 class="mb-3 text-base font-semibold text-gray-800">履歴</h3>
                <p v-if="!expense.events.length" class="text-sm text-gray-400">履歴はありません。</p>
                <ol v-else class="space-y-2 text-sm">
                    <li v-for="ev in expense.events" :key="ev.id" class="border-l-2 border-purple-200 pl-3">
                        <span class="text-xs text-gray-500">{{ ev.created_at }}</span>
                        <span class="ml-2 font-medium text-gray-800">{{ actionLabels[ev.action] ?? ev.action }}</span>
                        <span v-if="ev.user_name" class="ml-2 text-gray-600">（{{ ev.user_name }}）</span>
                        <p v-if="ev.comment" class="mt-0.5 whitespace-pre-wrap text-gray-700">{{ ev.comment }}</p>
                    </li>
                </ol>
            </div>
        </div>
    </AppLayout>
</template>
