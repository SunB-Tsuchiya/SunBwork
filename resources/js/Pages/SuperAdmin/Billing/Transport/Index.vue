<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import SuperAdminNavigationTabs from '@/Components/Tabs/SuperAdminNavigationTabs.vue';
import ExpenseRow from '@/Components/Billing/Transport/ExpenseRow.vue';

const props = defineProps({
    authDepartmentName: { type: String, default: '' },
    departmentCodes:    { type: Object, required: true },
    purposes:           { type: Object, required: true },
    unbilledExpenses:   { type: Array,  default: () => [] },
    defaultPeriodStart: { type: String, default: '' },
    defaultPeriodEnd:   { type: String, default: '' },
    newBillingId:       { type: Number, default: null },
    newBillingTotal:    { type: Number, default: null },
});

// ─── 入力フォーム ───────────────────────────────────────────
const today = new Date();
const billingDate    = ref(today.toISOString().slice(0, 10));
const departmentCode = ref(0);
const billingYear    = computed(() => new Date(billingDate.value).getFullYear());

const departmentOptions = computed(() =>
    Object.entries(props.departmentCodes).map(([code, name]) => ({
        value: parseInt(code),
        label: `${code}：${name}`,
    }))
);

function newRow(index) {
    return {
        sort_order: index, occurrence_date: null, destination: '',
        purpose: 'round_trip', purpose_text: '', station_from: '',
        station_to: '', fare_type: 'ic', base_amount: 0, auto_double: false, amount: 0,
    };
}

const rows = ref([newRow(0)]);

function addRow() { rows.value.push(newRow(rows.value.length)); }
function removeRow(i) {
    if (rows.value.length === 1) return;
    rows.value.splice(i, 1);
    rows.value.forEach((r, idx) => { r.sort_order = idx; });
}

const totalAmount = computed(() =>
    rows.value.reduce((s, r) => s + (parseInt(r.amount) || 0), 0)
);

const submitting       = ref(false);
const errors           = ref({});
const editingExpenseId = ref(null);

function submit() {
    submitting.value = true;
    errors.value = {};

    const payload = {
        billing_date:    billingDate.value,
        department_code: departmentCode.value,
        items:           rows.value.map((r, i) => ({ ...r, sort_order: i })),
    };

    if (editingExpenseId.value) {
        router.put(
            route('superadmin.billing.transport.update', { expense: editingExpenseId.value }),
            payload,
            {
                onSuccess: () => { resetForm(); },
                onError:   (e) => { errors.value = e; },
                onFinish:  () => { submitting.value = false; },
            }
        );
    } else {
        router.post(route('superadmin.billing.transport.store'), payload, {
            onSuccess: () => { rows.value = [newRow(0)]; },
            onError:   (e) => { errors.value = e; },
            onFinish:  () => { submitting.value = false; },
        });
    }
}

function resetForm() {
    editingExpenseId.value = null;
    billingDate.value      = today.toISOString().slice(0, 10);
    departmentCode.value   = 0;
    rows.value             = [newRow(0)];
    errors.value           = {};
}

function startEditing(exp) {
    editingExpenseId.value = exp.id;
    billingDate.value      = String(exp.billing_date).slice(0, 10);
    departmentCode.value   = exp.department_code;
    rows.value = exp.items.map((item, i) => ({
        sort_order:      i,
        occurrence_date: item.occurrence_date ? String(item.occurrence_date).slice(0, 10) : null,
        destination:     item.destination  ?? '',
        purpose:         item.purpose,
        purpose_text:    item.purpose_text ?? '',
        station_from:    item.station_from ?? '',
        station_to:      item.station_to   ?? '',
        fare_type:       item.fare_type,
        base_amount:     item.amount,
        auto_double:     false,
        amount:          item.amount,
    }));
    errors.value = {};
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function cancelEdit() { resetForm(); }

function deleteExpense(id) {
    if (!confirm('この交通費申請を削除しますか？')) return;
    router.delete(route('superadmin.billing.transport.destroy', { expense: id }), {
        preserveScroll: true,
    });
}

// ─── 未請求データ ────────────────────────────────────────────
// 全明細を発生日昇順でフラット展開
const sortedItems = computed(() => {
    const all = props.unbilledExpenses.flatMap(exp =>
        exp.items.map(item => ({ ...item, _expense: exp }))
    );
    return all.sort((a, b) => {
        const ad = a.occurrence_date ? String(a.occurrence_date).slice(0, 10) : null;
        const bd = b.occurrence_date ? String(b.occurrence_date).slice(0, 10) : null;
        if (!ad && !bd) return (a.sort_order ?? 0) - (b.sort_order ?? 0);
        if (!ad) return 1;
        if (!bd) return -1;
        if (ad !== bd) return ad.localeCompare(bd);
        return (a.sort_order ?? 0) - (b.sort_order ?? 0);
    });
});

const unbilledTotal = computed(() =>
    props.unbilledExpenses.reduce((s, e) => s + (e.total_amount ?? 0), 0)
);

function fmtOccDate(str) {
    if (!str) return '';
    const [, m, d] = String(str).slice(0, 10).split('-');
    return `${parseInt(m)}月${parseInt(d)}日`;
}

function itemLabel(item) {
    let label = item.purpose === 'other'
        ? (item.purpose_text || 'その他')
        : (props.purposes[item.purpose] ?? item.purpose);
    if (item.fare_type === 'ic') label += '(IC)';
    return label;
}

// ─── 請求データ作成 ──────────────────────────────────────────
const billingPeriodStart = ref(props.defaultPeriodStart);
const billingPeriodEnd   = ref(props.defaultPeriodEnd);
const billingSubmitting  = ref(false);
const billingErrors      = ref({});

const previewExpenses = computed(() =>
    props.unbilledExpenses.filter(exp => {
        if (!billingPeriodStart.value || !billingPeriodEnd.value) return true;
        return exp.items.some(item => {
            if (!item.occurrence_date) return false;
            const od = String(item.occurrence_date).slice(0, 10);
            return od >= billingPeriodStart.value && od <= billingPeriodEnd.value;
        });
    })
);
const previewTotal = computed(() =>
    previewExpenses.value.reduce((s, e) => s + (e.total_amount ?? 0), 0)
);

function createBilling() {
    billingErrors.value = {};
    if (previewExpenses.value.length === 0) {
        billingErrors.value = { period: '指定期間内に未請求データがありません。期間の日付を確認してください。' };
        return;
    }
    billingSubmitting.value = true;
    router.post(route('superadmin.billing.transport.billing.store'), {
        period_start: billingPeriodStart.value,
        period_end:   billingPeriodEnd.value,
    }, {
        onError:  (e) => { billingErrors.value = e; },
        onFinish: () => { billingSubmitting.value = false; },
    });
}

function printForm() { window.print(); }
</script>

<template>
    <AppLayout title="交通費入力">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">交通費入力</h2>
        </template>

        <template #headerExtras>
            <a
                :href="route('superadmin.billing.transport.billed')"
                class="rounded bg-yellow-100 px-4 py-2 text-sm font-medium text-yellow-800 hover:bg-yellow-200"
            >
                請求済みを確認 →
            </a>
        </template>

        <template #tabs>
            <SuperAdminNavigationTabs active="billing_transport_input" />
        </template>

        <div class="space-y-6">

            <!-- ─── 入力フォーム ─── -->
            <div class="rounded bg-white p-6 shadow print:shadow-none">

                <!-- 編集モードバナー -->
                <div v-if="editingExpenseId"
                    class="mb-4 flex items-center justify-between rounded border border-amber-200 bg-amber-50 px-4 py-2 text-sm">
                    <span class="font-medium text-amber-800">編集中 — 「更新」ボタンで保存されます</span>
                    <button type="button" @click="cancelEdit"
                        class="text-xs text-amber-700 underline hover:no-underline">
                        キャンセル
                    </button>
                </div>

                <h3 class="mb-6 text-center text-xl font-bold text-gray-800">交通費金銭請求伝票</h3>

                <!-- ヘッダー -->
                <div class="mb-6 grid grid-cols-2 gap-4 text-sm">
                    <div class="space-y-3">
                        <div class="flex items-center gap-3">
                            <label class="w-24 shrink-0 font-medium text-gray-700">請求日</label>
                            <input type="date" v-model="billingDate"
                                class="rounded border border-gray-300 px-3 py-1.5 text-sm focus:border-blue-400 focus:outline-none" />
                        </div>
                        <div class="flex items-start gap-3">
                            <label class="w-24 shrink-0 pt-1.5 font-medium text-gray-700">部門コード</label>
                            <div>
                                <select v-model="departmentCode"
                                    class="rounded border border-gray-300 px-3 py-1.5 text-sm focus:border-blue-400 focus:outline-none">
                                    <option v-for="opt in departmentOptions" :key="opt.value" :value="opt.value">
                                        {{ opt.label }}
                                    </option>
                                </select>
                                <div class="mt-1 flex flex-wrap gap-2 text-xs text-gray-500">
                                    <span v-for="opt in departmentOptions" :key="opt.value">{{ opt.label }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="space-y-3">
                        <div class="flex items-center gap-3">
                            <label class="w-16 shrink-0 font-medium text-gray-700">所属</label>
                            <span class="text-gray-800">{{ authDepartmentName || '―' }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <label class="w-16 shrink-0 font-medium text-gray-700">氏名</label>
                            <span class="text-gray-800">{{ $page.props.auth.user.name }}</span>
                        </div>
                    </div>
                </div>

                <!-- 明細テーブル -->
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-sm">
                        <thead>
                            <tr class="border-b-2 border-gray-300 bg-gray-50 text-xs text-gray-600">
                                <th class="w-32 px-2 py-2 text-center">発生日</th>
                                <th class="w-48 px-2 py-2 text-left">行先</th>
                                <th class="px-2 py-2 text-left">用件</th>
                                <th class="px-2 py-2 text-left">区間</th>
                                <th class="w-24 px-2 py-2 text-center">IC/切符</th>
                                <th class="w-36 px-2 py-2 text-right">金額</th>
                                <th class="w-12 px-2 py-2 print:hidden"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <ExpenseRow
                                v-for="(row, i) in rows" :key="i"
                                v-model="rows[i]"
                                :purposes="purposes" :index="i" :billingYear="billingYear"
                                @remove="removeRow(i)"
                            />
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-gray-300">
                                <td colspan="5" class="px-2 py-2 text-right text-sm font-medium text-gray-700">計</td>
                                <td class="px-2 py-2 text-right font-bold text-gray-900">
                                    {{ totalAmount.toLocaleString() }} 円
                                </td>
                                <td class="print:hidden"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="mt-3 print:hidden">
                    <button type="button" @click="addRow"
                        class="rounded border border-blue-300 px-4 py-1.5 text-sm text-blue-600 hover:bg-blue-50">
                        ＋ 行を追加
                    </button>
                </div>

                <p class="mt-6 text-xs text-gray-500">※ 領収書がある場合は裏面に貼付けして提出してください。</p>

                <!-- 承認欄（印刷用） -->
                <div class="mt-6 hidden print:grid print:grid-cols-4 print:gap-4">
                    <div class="border border-gray-400 p-2 text-xs">
                        <div class="mb-4 text-gray-600">経理・受付処理</div>
                    </div>
                    <div></div>
                    <div class="col-span-2 grid grid-cols-3 gap-px border border-gray-400">
                        <div class="border-r border-gray-400 p-2 text-center text-xs text-gray-600">社長</div>
                        <div class="border-r border-gray-400 p-2 text-center text-xs text-gray-600">部門長</div>
                        <div class="p-2 text-center text-xs text-gray-600">申請者</div>
                    </div>
                </div>

                <!-- ボタン -->
                <div class="mt-6 flex flex-wrap items-center justify-between gap-3 print:hidden">
                    <button type="button" @click="printForm"
                        class="rounded bg-gray-100 px-4 py-2 text-sm text-gray-700 hover:bg-gray-200">
                        印刷
                    </button>
                    <div class="flex gap-3">
                        <button v-if="editingExpenseId" type="button" @click="cancelEdit"
                            class="rounded border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
                            キャンセル
                        </button>
                        <button type="button" @click="submit" :disabled="submitting"
                            :class="editingExpenseId ? 'bg-amber-500 hover:bg-amber-600' : 'bg-blue-600 hover:bg-blue-700'"
                            class="rounded px-6 py-2 text-sm font-medium text-white disabled:opacity-50">
                            {{ submitting
                                ? (editingExpenseId ? '更新中...' : '保存中...')
                                : (editingExpenseId ? '更新' : '保存') }}
                        </button>
                    </div>
                </div>

                <div v-if="Object.keys(errors).length"
                    class="mt-4 rounded bg-red-50 p-3 text-sm text-red-600 print:hidden">
                    <ul class="list-inside list-disc space-y-1">
                        <li v-for="(msg, key) in errors" :key="key">{{ msg }}</li>
                    </ul>
                </div>
            </div>

            <!-- ─── 未請求データ ─── -->
            <div class="rounded bg-white p-6 shadow print:hidden">
                <h3 class="mb-3 text-base font-semibold text-gray-800">未請求データ</h3>

                <div v-if="sortedItems.length === 0" class="py-6 text-center text-sm text-gray-400">
                    未請求の保存データはありません。
                </div>

                <div v-else class="mb-6 overflow-x-auto">
                    <table class="w-full border-collapse text-sm">
                        <thead>
                            <tr class="border-b-2 border-gray-800 bg-gray-50 text-xs text-gray-700">
                                <th class="w-20 border border-gray-400 px-2 py-1.5 text-center">発生日</th>
                                <th class="w-48 border border-gray-400 px-2 py-1.5 text-center">行先</th>
                                <th class="border border-gray-400 px-2 py-1.5 text-center">用件</th>
                                <th class="border border-gray-400 px-2 py-1.5 text-center" colspan="3">
                                    区&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;間
                                </th>
                                <th class="w-20 border border-gray-400 px-2 py-1.5 text-center">金額</th>
                                <th class="w-24 border border-gray-400 px-2 py-1.5 text-center">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in sortedItems" :key="item.id"
                                class="border-b border-gray-200 hover:bg-gray-50"
                                :class="{ 'bg-amber-50': editingExpenseId === item._expense.id }">
                                <td class="border border-gray-300 px-2 py-1 text-center text-xs text-gray-700">
                                    {{ fmtOccDate(item.occurrence_date) }}
                                </td>
                                <td class="border border-gray-300 px-2 py-1 text-xs text-gray-800">
                                    {{ item.destination }}
                                </td>
                                <td class="border border-gray-300 px-2 py-1 text-xs text-gray-700">
                                    {{ itemLabel(item) }}
                                </td>
                                <td class="border border-gray-300 px-2 py-1 text-xs text-gray-700" style="width:18%">
                                    {{ item.station_from }}
                                </td>
                                <td class="w-8 border border-gray-300 px-1 py-1 text-center text-xs text-gray-500">
                                    一
                                </td>
                                <td class="border border-gray-300 px-2 py-1 text-xs text-gray-700" style="width:18%">
                                    {{ item.station_to }}
                                </td>
                                <td class="border border-gray-300 px-2 py-1 text-right text-xs tabular-nums text-gray-800">
                                    {{ item.amount > 0 ? item.amount.toLocaleString() : '' }}
                                </td>
                                <td class="border border-gray-300 px-1 py-1 text-center">
                                    <div class="flex justify-center gap-1">
                                        <button type="button" @click="startEditing(item._expense)"
                                            class="rounded bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700 hover:bg-amber-100">
                                            編集
                                        </button>
                                        <button type="button" @click="deleteExpense(item._expense.id)"
                                            class="rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 hover:bg-red-50 hover:text-red-700">
                                            削除
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-gray-800 bg-gray-50">
                                <td colspan="6" class="border border-gray-400 px-2 py-1.5 text-right text-xs font-bold text-gray-800">
                                    計
                                </td>
                                <td class="border border-gray-400 px-2 py-1.5 text-right text-xs font-bold tabular-nums text-gray-900">
                                    {{ unbilledTotal.toLocaleString() }}
                                </td>
                                <td class="border border-gray-400"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- ─── 請求データを作成後のエクスポートバナー ─── -->
                <div v-if="newBillingId"
                    class="mb-6 rounded border border-green-300 bg-green-50 p-4">
                    <p class="mb-3 text-sm font-semibold text-green-800">
                        請求データを作成しました
                        <span v-if="newBillingTotal" class="ml-2 font-normal text-green-700">
                            （合計 {{ newBillingTotal.toLocaleString() }} 円）
                        </span>
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <a :href="route('superadmin.billing.transport.billing.excel', { billing: newBillingId })"
                            class="rounded bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">
                            Excelダウンロード
                        </a>
                        <a :href="route('superadmin.billing.transport.billing.pdf', { billing: newBillingId }) + '?mode=inline'"
                            target="_blank"
                            class="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                            印刷・PDF閲覧
                        </a>
                        <a :href="route('superadmin.billing.transport.billing.pdf', { billing: newBillingId })"
                            class="rounded border border-blue-400 bg-white px-4 py-2 text-sm font-medium text-blue-700 hover:bg-blue-50">
                            PDFとして保存
                        </a>
                    </div>
                </div>

                <!-- 請求データを作成 -->
                <div class="rounded border border-blue-200 bg-blue-50 p-4">
                    <h4 class="mb-3 text-sm font-semibold text-blue-800">請求データを作成</h4>
                    <div class="flex flex-wrap items-end gap-4">
                        <div>
                            <label class="mb-1 block text-xs text-gray-600">期間 開始</label>
                            <input type="date" v-model="billingPeriodStart"
                                class="rounded border border-gray-300 px-3 py-1.5 text-sm focus:border-blue-400 focus:outline-none" />
                        </div>
                        <span class="pb-1.5 text-gray-500">〜</span>
                        <div>
                            <label class="mb-1 block text-xs text-gray-600">期間 終了</label>
                            <input type="date" v-model="billingPeriodEnd"
                                class="rounded border border-gray-300 px-3 py-1.5 text-sm focus:border-blue-400 focus:outline-none" />
                        </div>
                        <div class="pb-1">
                            <p class="text-xs text-gray-600">
                                対象: <strong>{{ previewExpenses.length }} 件</strong> /
                                合計: <strong>{{ previewTotal.toLocaleString() }} 円</strong>
                            </p>
                        </div>
                    </div>

                    <div v-if="billingErrors.period" class="mt-2 text-xs text-red-600">
                        {{ billingErrors.period }}
                    </div>

                    <button
                        type="button"
                        @click="createBilling"
                        :disabled="billingSubmitting"
                        class="mt-3 rounded bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-40"
                    >
                        {{ billingSubmitting ? '作成中...' : '作成する' }}
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style>
@media print {
    nav, header, footer, .print\:hidden, [class*="print:hidden"] { display: none !important; }
    body { background: white; margin: 0; }
    table { border-collapse: collapse; width: 100%; }
    th, td { border: 1px solid #333; padding: 4px 6px; font-size: 11px; }
    thead tr { background: #f0f0f0 !important; }
    tfoot tr td { border-top: 2px solid #333; font-weight: bold; }
    .rounded, .shadow { box-shadow: none !important; border-radius: 0 !important; }
}
</style>
