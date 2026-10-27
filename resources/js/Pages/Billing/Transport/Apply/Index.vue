<script setup>
import { ref, computed, watch, nextTick, onMounted, onBeforeUnmount } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import UserNavigationTabs from '@/Components/Tabs/UserNavigationTabs.vue';
import ExpenseRow from '@/Components/Billing/Transport/ExpenseRow.vue';
import PasteImportModal from '@/Components/Billing/Transport/PasteImportModal.vue';
import MultiDateModal from '@/Components/Billing/Transport/MultiDateModal.vue';
import PresetManagerModal from '@/Components/Billing/Transport/PresetManagerModal.vue';
import FareHelpModal from '@/Components/Billing/Transport/FareHelpModal.vue';
import ViaHelpModal from '@/Components/Billing/Transport/ViaHelpModal.vue';
import UserPickerModal from '@/Components/Billing/Transport/UserPickerModal.vue';
import { shortDate } from '@/Components/Billing/Transport/transportNormalize';
import { parseQuick, applySuggestion } from '@/Components/Billing/Transport/transportInput';

const props = defineProps({
    routeBase:          { type: String, default: 'user.billing.transport.' },
    authDepartmentName: { type: String, default: '' },
    departments:        { type: Array, default: () => [] },
    purposes:           { type: Object, required: true },
    statusLabels:       { type: Object, required: true },
    actionLabels:       { type: Object, default: () => ({}) },
    month:              { type: String, required: true },
    months:             { type: Array, required: true },
    periodStart:        { type: String, required: true },
    periodEnd:          { type: String, required: true },
    rowsPerPage:        { type: Number, default: 25 },
    defaultDeptId:      { type: Number, default: null },
    expense:            { type: Object, default: null },
    canEdit:            { type: Boolean, default: true },   // この月を編集（保存・申請）できるか（期限の判定を含む）
    deadline:           { type: Object, default: () => ({ passed: false, extended: false, end: '', requestable: true }) },
    deadlineRequest:    { type: Object, default: null },   // この月の、期限後の申請の依頼（requested 依頼中 / rejected 見送り）
    previousItems:      { type: Array, default: () => [] },
    suggestions:        { type: Array, default: () => [] },
    colleagues:         { type: Array, default: () => [] },   // 同行者に選べる人（同じ会社の社員）
    history:            { type: Array, default: () => [] },
});

const statusColor = {
    draft: 'bg-gray-100 text-gray-700',
    submitted: 'bg-blue-100 text-blue-700',
    returned: 'bg-red-100 text-red-700',
    approved: 'bg-green-100 text-green-700',
    billed: 'bg-purple-100 text-purple-700',
};

const rt = (name, params) => route(props.routeBase + name, params);

function monthLabel(ym) {
    const [y, m] = ym.split('-');
    return `${y}年${parseInt(m)}月分`;
}
const periodLabel = computed(() => {
    const f = (s) => shortDate(s);
    return `${f(props.periodStart)}〜${f(props.periodEnd)}`;
});

// ─── 入力フォーム ───────────────────────────────────────────
// 自動保存で作られた／更新された申請（画面を書き換えずに JSON で保存するので、props とは別に持つ）
const savedExpense = ref(null);
const curId = computed(() => savedExpense.value?.id ?? props.expense?.id ?? null);
const status = computed(() => savedExpense.value?.status ?? props.expense?.status ?? null);
const editable = computed(() => props.canEdit);
// 期限を過ぎた月でも、下書き・差戻しは削除できる（残った下書きを片付けられる）
const canDelete = computed(() => !!curId.value && (editable.value || !!props.expense?.deletable));
// 期限（月末）を過ぎていて、編集できない（下書き・未入力の月）
// ─── 期限後の申請の依頼（総務・経理に依頼する。許可されると、この月を入力・申請できる） ───
const showRequest = ref(false);
const requestReason = ref('');
const requesting = ref(false);
function sendRequest() {
    errors.value = {};
    requesting.value = true;
    router.post(rt('deadline_requests.store'), { billing_month: props.month, reason: requestReason.value }, {
        preserveScroll: true,
        onSuccess: () => { showRequest.value = false; requestReason.value = ''; },
        onError: (e) => { errors.value = e; },
        onFinish: () => { requesting.value = false; },
    });
}
function cancelRequest() {
    if (!confirm('期限後の申請の依頼を、取り下げます。よろしいですか？')) return;
    router.delete(rt('deadline_requests.destroy', { extension: props.deadlineRequest.id }), { preserveScroll: true });
}

const lockedByDeadline = computed(() => props.deadline.passed && !props.deadline.extended && !editable.value && (!status.value || status.value === 'draft'));

function newRow() {
    return {
        occurrence_date: null, destination: '', purpose: 'round_trip', purpose_text: '',
        station_from: '', station_to: '', via_stations: [], companions: [],   // 経由地・同行者は、入れなくてもよい
        fare_type: 'ic', base_amount: 0, auto_double: true, amount: 0,   // 初期値は往復（×2 チェック済み）
    };
}

// ─── 自動保存（入力が止まって 1.5 秒で、下書きとして保存。申請はしない） ───────────────
const autosaveState = ref('clean');   // clean | dirty | saving | saved | error
const autosaveMessage = ref('');
const savedAt = ref('');
let autosaveTimer = null;
let inFlight = null;
let lastSaved = '';                    // 最後に保存できた状態（JSON）
let skipUnloadGuard = false;           // 出力（ダウンロード）などで、離脱の確認を出さない間

const chargeDeptId = ref(props.defaultDeptId);   // null = 共通
const applicantComment = ref('');                // 申請者のコメント（申請・再申請に添える。書きかけも自動保存）
const rows = ref([newRow()]);
const errors = ref({});
const submitting = ref(false);

function loadFromProps() {
    errors.value = {};
    clearTimeout(autosaveTimer);
    savedExpense.value = null;
    autosaveState.value = 'clean';
    if (props.expense) {
        chargeDeptId.value = props.expense.charge_department_id;
        applicantComment.value = props.expense.applicant_comment ?? '';
        rows.value = props.expense.items.length
            ? props.expense.items.map((it) => ({
                occurrence_date: it.occurrence_date, destination: it.destination ?? '', purpose: it.purpose,
                purpose_text: it.purpose_text ?? '', station_from: it.station_from ?? '', station_to: it.station_to ?? '',
                via_stations: it.via_stations ?? [], companions: it.companions ?? [],
                fare_type: it.fare_type, base_amount: it.base_amount ?? it.amount, auto_double: !!it.auto_double, amount: it.amount,   // 保存したときの状態（片道の金額・×2）をそのまま復元
            }))
            : [newRow()];
    } else {
        chargeDeptId.value = props.defaultDeptId;
        applicantComment.value = '';
        rows.value = [newRow()];
    }
    lastSaved = JSON.stringify(payload());   // 読み込んだ状態が「保存済み」の基準
}
// 中身が実際に変わったときだけ読み直す（登録など、同じ内容の再読み込みで、入力中の行が消えないように）
watch(() => JSON.stringify([props.month, props.expense]), loadFromProps, { immediate: true });


// ─── 入力補助（まとめて貼り付け・クイック入力・登録した行先・複数日・複製） ────────────
const showPaste = ref(false);
const showPresets = ref(false);
const showFareHelp = ref(false);
const showViaHelp = ref(false);
const companionTarget = ref(null);   // 同行者を選んでいる行の番号
function onCompanionsApply(ids) {
    const i = companionTarget.value;
    if (i !== null && rows.value[i]) {
        rows.value[i] = { ...rows.value[i], companions: ids.map((id) => ({ id, name: props.colleagues.find((u) => u.id === id)?.name ?? '' })) };
    }
    companionTarget.value = null;
}
const multiTarget = ref(null);    // 複数日に追加する行の番号
const quickText = ref('');
const quickMessage = ref('');

// 関数宣言にして、読み込み（loadFromProps）より前から使えるようにする
function isBlankRow(r) { return !r.destination && !r.station_from && !r.station_to && !(parseInt(r.amount) || 0) && !r.occurrence_date; }
const registered = computed(() => props.suggestions.filter((s) => s.registered));
// 行先の入力候補（行先ごとに先頭＝登録済み・回数の多いものを 1 つ）
const destinationOptions = computed(() => {
    const seen = new Set();
    return props.suggestions.filter((s) => (seen.has(s.destination) ? false : (seen.add(s.destination), true)));
});

/** 空の行を取り除いてから、新しい行を追加する（空の行が 1 つだけのときは、置き換える） */
function addRows(newRows) {
    const kept = rows.value.filter((r) => !isBlankRow(r));
    rows.value = [...kept, ...newRows];
    if (!rows.value.length) rows.value.push(newRow());
}

function focusRow(i, field = 'date') {
    nextTick(() => document.querySelector(`tr[data-row="${i}"] [data-field="${field}"]`)?.focus());
}
function onNext(i) {
    if (i + 1 >= rows.value.length) addRow();
    focusRow(i + 1);
}

function onPasteApply({ rows: pasted, mode }) {
    const mapped = pasted.map((r) => ({ ...newRow(), ...r }));
    if (mode === 'replace') rows.value = mapped.length ? mapped : [newRow()];
    else addRows(mapped);
    showPaste.value = false;
}

// 行先を選んだとき: 区間・金額が空の行なら、候補の内容をまとめて入れる
function onDestinationChange(i, value) {
    const r = rows.value[i];
    if (!value || r.station_from || r.station_to || (parseInt(r.amount) || 0)) return;
    const s = destinationOptions.value.find((x) => x.destination === value);
    if (s) rows.value[i] = applySuggestion(r, s);
}

// クイック入力: 「0901 国際」と入れると、合う行先の候補が出るので、選ぶ（先頭の日付は複数可: 「0901 0903 国際」）
const quickFocused = ref(false);
const quickActive = ref(0);
const quickParsed = computed(() => (quickText.value.trim() ? parseQuick(quickText.value, props.month, props.suggestions) : null));
// 行先のキーワードが無いとき（日付だけ、または空）は、登録済みの行先を候補に出す
const quickCandidates = computed(() => {
    const q = quickParsed.value;
    if (!q) return [];
    return q.keyword ? q.candidates : registered.value.slice(0, 8);
});
const quickOpen = computed(() => quickFocused.value && quickText.value.trim() !== '');
watch(quickText, () => { quickActive.value = 0; quickMessage.value = ''; });

function pickQuick(s) {
    const q = quickParsed.value;
    const dates = q?.dates?.length ? q.dates : [null];
    addRows(dates.map((d) => applySuggestion({ ...newRow(), occurrence_date: d }, s)));
    quickMessage.value = `追加しました: ${s.destination}（${dates.filter(Boolean).map(shortDate).join('、') || '日付なし'}）`;
    quickText.value = '';
}
function onQuickKey(e) {
    if (!quickOpen.value || !quickCandidates.value.length) return;
    if (e.key === 'ArrowDown') { e.preventDefault(); quickActive.value = (quickActive.value + 1) % quickCandidates.value.length; }
    else if (e.key === 'ArrowUp') { e.preventDefault(); quickActive.value = (quickActive.value - 1 + quickCandidates.value.length) % quickCandidates.value.length; }
}
function onQuickEnter() {
    quickMessage.value = '';
    if (!quickText.value.trim()) return;
    const q = quickParsed.value;
    if (!q.keyword) { quickMessage.value = '日付のあとに、行先の文字を入れてください。例：　0901 国際'; return; }
    if (!q.candidates.length) { quickMessage.value = `「${q.keyword}」に合う行先が、登録・履歴にありません。`; return; }
    pickQuick(q.candidates[quickActive.value] ?? q.candidates[0]);
}

function duplicateRow(i) {
    rows.value.splice(i + 1, 0, { ...rows.value[i], occurrence_date: null });
    focusRow(i + 1);
}
function onMultiApply(dates) {
    const i = multiTarget.value;
    const base = rows.value[i];
    const copies = dates.map((d) => ({ ...base, occurrence_date: d }));
    if (!base.occurrence_date) {
        rows.value.splice(i, 1, ...copies);                  // 日付が空の元の行は、最初の日にする
    } else {
        rows.value.splice(i + 1, 0, ...copies.filter((c) => c.occurrence_date !== base.occurrence_date));
    }
    multiTarget.value = null;
}
const multiDescription = computed(() => {
    const r = multiTarget.value !== null ? rows.value[multiTarget.value] : null;
    return r ? [r.destination, r.station_from && r.station_to ? `${r.station_from}－${r.station_to}` : '', r.amount ? `${Number(r.amount).toLocaleString()}円` : ''].filter(Boolean).join('　') : '';
});

function registerRow(i) {
    const r = rows.value[i];
    quickMessage.value = '';
    if (!r.destination?.trim()) { quickMessage.value = '登録するには、先に行先を入力してください。'; return; }
    router.post(rt('presets.store'), {
        destination: r.destination, purpose: r.purpose, purpose_text: r.purpose_text,
        station_from: r.station_from, station_to: r.station_to, via_stations: r.via_stations ?? [], fare_type: r.fare_type,
        base_amount: r.base_amount ?? r.amount, auto_double: r.auto_double,
    }, { preserveScroll: true, preserveState: true, onError: (e) => { errors.value = e; } });
}

function addRow() { rows.value.push(newRow()); }
function removeRow(i) {
    rows.value.splice(i, 1);
    if (rows.value.length === 0) rows.value.push(newRow());
}
function copyPrevious() {
    if (rows.value.some((r) => r.destination || r.amount) && !confirm('入力中の内容は前月の内容で置き換わります。よろしいですか？')) return;
    rows.value = props.previousItems.map((it) => ({
        ...newRow(), ...it, occurrence_date: null, base_amount: it.amount, auto_double: false,   // 前月の金額は往復ぶん込みなので、×2 は外す
        purpose_text: it.purpose_text ?? '', via_stations: it.via_stations ?? [], companions: [],   // 経由地は引き継ぐ。同行者は、その日ごとに違うので引き継がない
    }));
}

const totalAmount = computed(() => rows.value.reduce((s, r) => s + (parseInt(r.amount) || 0), 0));
const pageCount = computed(() => Math.max(1, Math.ceil(rows.value.length / props.rowsPerPage)));

function payload() {
    // 末尾の空の行は保存しない（行を追加しただけ、などで空の行ができるため）。途中の空の行は、行番号がずれないよう、そのまま送る
    let n = rows.value.length;
    while (n > 1 && isBlankRow(rows.value[n - 1])) n--;
    return {
        billing_month: props.month,
        charge_department_id: chargeDeptId.value,
        applicant_comment: applicantComment.value,
        items: rows.value.slice(0, n).map(({ companions, ...r }, i) => ({ ...r, companion_ids: (companions ?? []).map((c) => c.id), sort_order: i })),
    };
}
const snapshot = computed(() => JSON.stringify(payload()));
function rowError(i) { return errors.value[`items.${i}.occurrence_date`] || ''; }
const otherErrors = computed(() =>
    Object.entries(errors.value).filter(([k]) => !/^items\.\d+\.occurrence_date$/.test(k)).map(([, v]) => v)
);

function save(after) {
    submitting.value = true;
    errors.value = {};
    clearTimeout(autosaveTimer);
    const snap = JSON.stringify(payload());
    router.post(rt('store'), payload(), {
        preserveScroll: true,
        onSuccess: () => { lastSaved = snap; autosaveState.value = 'clean'; if (after) after(); },
        onError: (e) => { errors.value = e; },
        onFinish: () => { submitting.value = false; },
    });
}

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
/** 何か入力してあって、まだ保存できていない状態か */
const hasUnsaved = () => editable.value && !rows.value.every(isBlankRow) && snapshot.value !== lastSaved;

watch(snapshot, (v) => {
    if (!editable.value || submitting.value) return;
    if (v === lastSaved || rows.value.every(isBlankRow)) {
        // 保存済みの内容と同じになった（エラーのあと、元の値に直した場合など）: 保存は不要。エラー表示を消す
        clearTimeout(autosaveTimer);
        if (autosaveState.value === 'error') { errors.value = {}; autosaveState.value = 'clean'; }
        else if (autosaveState.value === 'dirty') autosaveState.value = 'clean';
        return;
    }
    autosaveState.value = 'dirty';
    clearTimeout(autosaveTimer);
    autosaveTimer = setTimeout(runAutosave, 1500);
});

/** 自動保存を実行する。保存できた（または保存の必要が無い）なら true */
async function runAutosave() {
    clearTimeout(autosaveTimer);
    if (inFlight) await inFlight.catch(() => {});
    if (!editable.value || !hasUnsaved()) return true;

    const body = payload();
    const snap = JSON.stringify(body);
    autosaveState.value = 'saving';
    inFlight = axios.post(rt('autosave'), body, { headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' } });
    try {
        const { data } = await inFlight;
        lastSaved = snap;
        savedExpense.value = { id: data.id, status: data.status };
        savedAt.value = data.saved_at;
        errors.value = {};
        autosaveState.value = snapshot.value === snap ? 'saved' : 'dirty';   // 保存中にさらに入力があれば、続けて保存される
        return true;
    } catch (e) {
        const res = e.response;
        const list = res?.data?.errors ? Object.fromEntries(Object.entries(res.data.errors).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v])) : {};
        errors.value = list;
        autosaveMessage.value = Object.values(list)[0] ?? res?.data?.message ?? '通信できませんでした。';
        autosaveState.value = 'error';
        return false;
    } finally {
        inFlight = null;
    }
}

// 出力（PDF・Excel）は、保存済みの内容で作られる。未保存なら、先に自動保存してから開く
async function openOutput(ev, name, inline = false) {
    if (!curId.value && !hasUnsaved()) return;
    ev.preventDefault();
    const w = inline ? window.open('', '_blank') : null;   // ポップアップを止められないよう、先に開いておく
    if (hasUnsaved() && !(await runAutosave()) && hasUnsaved()) {
        if (!confirm(`保存できていない入力があります（${autosaveMessage.value}）。保存済みの内容で出力しますか？`)) { w?.close(); return; }
    }
    if (!curId.value) { w?.close(); alert('まだ保存された内容がありません。'); return; }
    const url = rt(name, { expense: curId.value }) + (inline ? '?mode=inline' : '');
    if (inline) { w.location.href = url; return; }
    skipUnloadGuard = true;
    window.location.href = url;
    setTimeout(() => { skipUnloadGuard = false; }, 3000);
}

function onBeforeUnload(e) {
    if (skipUnloadGuard || !hasUnsaved()) return;
    e.preventDefault();
    e.returnValue = '';
}
let offBefore = null;
onMounted(() => {
    window.addEventListener('beforeunload', onBeforeUnload);
    // 画面内の移動（タブ・月の切り替えなど）でも、保存できていない入力があれば確認する（保存・申請などの POST は対象外）
    offBefore = router.on('before', (event) => {
        if (event.detail.visit.method !== 'get' || skipUnloadGuard || !hasUnsaved()) return;
        if (!confirm('保存されていない入力があります。このまま移動すると、入力した内容は失われます。移動しますか？')) { event.preventDefault(); return false; }
    });
});
onBeforeUnmount(() => {
    clearTimeout(autosaveTimer);
    window.removeEventListener('beforeunload', onBeforeUnload);
    if (offBefore) offBefore();
});

function saveDraft() { save(); }
function saveAndSubmit() {
    if (!confirm(`${monthLabel(props.month)}の交通費（合計 ${totalAmount.value.toLocaleString()} 円）を申請します。よろしいですか？`)) return;
    save(() => {
        if (!curId.value) return;
        router.post(rt('submit', { expense: curId.value }), { comment: applicantComment.value }, {
            preserveScroll: true,
            onError: (e) => { errors.value = e; },
        });
    });
}
function withdraw() {
    if (!confirm('申請を取り下げて、下書きに戻します。よろしいですか？')) return;
    router.post(rt('withdraw', { expense: curId.value }), {}, { preserveScroll: true });
}
function deleteDraft() {
    if (!confirm('この月の交通費（下書き）を削除します。よろしいですか？')) return;
    router.delete(rt('destroy', { expense: curId.value }), { preserveScroll: true });
}
async function changeMonth(e) {
    const month = e.target.value;
    if (hasUnsaved()) await runAutosave();   // 保存できなければ、移動の確認が出る
    router.get(rt('index'), { month });
}
</script>

<template>
    <AppLayout title="交通費入力">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">交通費入力</h2>
        </template>


        <template #tabs>
            <UserNavigationTabs active="applications" />
        </template>

        <div class="space-y-6">
            <div class="rounded bg-white p-6 shadow">

                <!-- 対象月と状態 -->
                <div class="mb-4 flex flex-wrap items-center gap-4">
                    <div class="flex items-center gap-2">
                        <label class="text-sm font-medium text-gray-700">対象月</label>
                        <select :value="month" @change="changeMonth"
                            class="rounded border border-gray-300 px-3 py-1.5 text-sm focus:border-blue-400 focus:outline-none">
                            <option v-for="m in months" :key="m" :value="m">{{ monthLabel(m) }}</option>
                        </select>
                    </div>
                    <span class="text-sm text-gray-600">対象期間: {{ periodLabel }}（月初〜月末）</span>
                    <span class="rounded px-2.5 py-1 text-xs font-medium"
                        :class="statusColor[status ?? 'draft']">
                        {{ status ? statusLabels[status] : '未入力' }}
                    </span>
                    <span v-if="curId" class="text-xs text-gray-500" title="申請書（PDF・Excel）の右上に、同じ番号が入ります">申請No.{{ curId }}</span>
                    <span v-if="expense?.submitted_at" class="text-xs text-gray-500">申請日時: {{ expense.submitted_at }}</span>
                </div>

                <!-- 期限（当月末日）: 過ぎていると、入力・申請できない（閲覧・出力・下書きの削除はできる） -->
                <div v-if="lockedByDeadline" class="mb-4 rounded border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
                    <p class="font-semibold">期限（{{ deadline.end }}）を過ぎているため、この月は、入力・申請できません。</p>
                    <p class="mt-1 text-xs">内容の確認と、PDF・Excel の出力、下書きの削除はできます。</p>

                    <!-- 依頼中 -->
                    <div v-if="deadlineRequest?.status === 'requested'" class="mt-3 rounded border border-blue-200 bg-white p-3 text-blue-900">
                        <p class="font-semibold">期限後の申請を、依頼中です（{{ deadlineRequest.requested_at }}）。総務・経理の確認をお待ちください。</p>
                        <p class="mt-1 whitespace-pre-wrap text-xs text-gray-700">理由: {{ deadlineRequest.reason }}</p>
                        <button type="button" @click="cancelRequest" class="mt-2 rounded border border-gray-300 px-3 py-1 text-xs text-gray-700 hover:bg-gray-50">依頼を取り下げる</button>
                    </div>

                    <!-- 先月より前の月は、依頼できない（支払いの対象外） -->
                    <div v-else-if="!deadline.requestable" class="mt-3 rounded border border-gray-300 bg-white p-3 text-xs text-gray-700">
                        期限後の申請を依頼できるのは、先月分までです。この月は、申請できません（支払いの対象外です）。
                    </div>

                    <!-- 依頼する（未依頼、または見送られたあと） -->
                    <div v-else class="mt-3">
                        <div v-if="deadlineRequest?.status === 'rejected'" class="mb-2 rounded border border-red-200 bg-white p-2 text-xs text-red-800">
                            前回の依頼は、見送られました。<span v-if="deadlineRequest.decision_comment">コメント: {{ deadlineRequest.decision_comment }}</span>
                        </div>
                        <p class="text-xs">やむを得ない場合は、総務・経理に、期限後の申請を依頼できます。許可されると、この月を入力・申請できます。</p>
                        <button v-if="!showRequest" type="button" @click="showRequest = true"
                            class="mt-2 rounded bg-amber-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-amber-700">期限後の申請を依頼する</button>
                        <div v-else class="mt-2 rounded border border-amber-200 bg-white p-3">
                            <label class="mb-1 block text-xs font-medium text-gray-700">依頼の理由（総務・経理に伝わります）</label>
                            <textarea v-model="requestReason" rows="2" maxlength="255" class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-amber-400 focus:outline-none"></textarea>
                            <p class="mt-1 text-xs text-gray-500">例：　出張の精算の出し忘れのため</p>
                            <div class="mt-2 flex gap-2">
                                <button type="button" :disabled="requesting" @click="sendRequest" class="rounded bg-amber-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-amber-700 disabled:opacity-50">依頼を送る</button>
                                <button type="button" @click="showRequest = false" class="rounded border border-gray-300 px-4 py-1.5 text-sm text-gray-600 hover:bg-gray-50">キャンセル</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-else-if="deadline.passed && deadline.extended" class="mb-4 rounded border border-green-300 bg-green-50 p-3 text-sm text-green-800">
                    期限（{{ deadline.end }}）を過ぎていますが、総務・経理が、期限を解除しています。入力・申請できます。
                </div>
                <div v-else-if="deadline.passed && status === 'returned'" class="mb-4 rounded border border-blue-200 bg-blue-50 p-3 text-xs text-blue-800">
                    期限（{{ deadline.end }}）を過ぎていますが、差戻しされたものは、直して再申請できます。
                </div>

                <!-- 差戻し -->
                <div v-if="status === 'returned'" class="mb-4 rounded border border-red-200 bg-red-50 p-3 text-sm text-red-800">
                    <p class="font-semibold">差戻しされました。修正して再度申請してください。</p>
                    <p v-if="expense.reviewer_name" class="mt-1 text-xs">担当: {{ expense.reviewer_name }}（{{ expense.reviewed_at }}）</p>
                    <p v-if="expense.review_comment" class="mt-1 whitespace-pre-wrap">{{ expense.review_comment }}</p>
                </div>
                <!-- 申請済み・受理・請求済み -->
                <div v-else-if="status && !editable" class="mb-4 rounded border border-blue-200 bg-blue-50 p-3 text-sm text-blue-800">
                    <template v-if="status === 'submitted'">申請済みです。総務の確認をお待ちください。印刷した用紙を提出してください。</template>
                    <template v-else-if="status === 'approved'">受理されました。</template>
                    <template v-else>請求済みです。</template>
                    <p v-if="expense.review_comment" class="mt-1 whitespace-pre-wrap">{{ expense.review_comment }}</p>
                </div>

                <h3 class="mb-6 text-center text-xl font-bold text-gray-800">交通費金銭請求伝票　（{{ monthLabel(month) }}）</h3>

                <!-- ヘッダー -->
                <div class="mb-6 grid grid-cols-1 gap-4 text-sm md:grid-cols-2">
                    <div class="flex items-start gap-3">
                        <label class="w-24 shrink-0 pt-1.5 font-medium text-gray-700">部門</label>
                        <div>
                            <select v-model="chargeDeptId" :disabled="!editable"
                                class="rounded border border-gray-300 px-3 py-1.5 text-sm focus:border-blue-400 focus:outline-none disabled:bg-gray-50">
                                <option :value="null">共通</option>
                                <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                            </select>
                            <p class="mt-1 text-xs text-gray-500">請求先の部門を選びます。わからないときは「共通」です。</p>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <div class="flex items-center gap-3">
                            <span class="w-16 shrink-0 font-medium text-gray-700">所属</span>
                            <span class="text-gray-800">{{ authDepartmentName || '―' }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="w-16 shrink-0 font-medium text-gray-700">氏名</span>
                            <span class="text-gray-800">{{ $page.props.auth.user.name }}</span>
                        </div>
                    </div>
                </div>

                <datalist id="transport-destinations">
                    <option v-for="d in destinationOptions" :key="d.destination" :value="d.destination"
                        :label="`${d.station_from}－${d.station_to}　${d.amount.toLocaleString()}円${d.registered ? '　★登録' : ''}`" />
                </datalist>

                <!-- 記載のしかたの注意（定期区間外の駅から） -->
                <div class="mb-3 flex flex-wrap items-center gap-2 rounded border-2 border-amber-400 bg-amber-50 px-4 py-3">
                    <span class="text-base font-bold text-amber-900">⚠ 定期区間外の駅から記載してください</span>
                    <button type="button" @click="showFareHelp = true" title="記載のしかたを見る" aria-label="記載のしかたを見る"
                        class="flex h-6 w-6 items-center justify-center rounded-full bg-amber-500 text-sm font-bold text-white hover:bg-amber-600">?</button>
                    <span class="text-xs text-amber-800">（「?」で、例を見られます）</span>
                    <button type="button" @click="showViaHelp = true"
                        class="ml-auto rounded border border-amber-400 bg-white px-3 py-1 text-xs font-medium text-amber-900 hover:bg-amber-100">経由地の入れ方（JR・メトロ・都営など、会社が変わるとき）</button>
                </div>

                <!-- 明細 -->
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-sm">
                        <thead>
                            <tr class="border-b-2 border-gray-300 bg-gray-50 text-xs text-gray-600">
                                <th class="w-8 px-1 py-2 text-center font-normal text-gray-400">行</th>
                                <th class="w-32 px-2 py-2 text-left">発生日</th>
                                <th class="w-44 px-2 py-2 text-left">行先</th>
                                <th class="px-2 py-2 text-left">用件</th>
                                <th class="px-2 py-2 text-left">区間</th>
                                <th class="w-24 px-2 py-2 text-center">IC/切符</th>
                                <th class="w-44 px-2 py-2 text-left">金額</th>
                                <th class="w-28 px-2 py-2"></th>
                            </tr>
                            <tr v-if="editable" class="text-xs text-gray-500">
                                <th></th>
                                <th class="px-2 pb-2 text-left font-normal">例：　0422　または　4/22</th>
                                <th class="px-2 pb-2 text-left font-normal">例：　小台工場</th>
                                <th class="px-2 pb-2 text-left font-normal">例：　打合せ（往復）</th>
                                <th class="px-2 pb-2 text-left font-normal">例：　田端 － 宮城都営住宅前</th>
                                <th></th>
                                <th class="px-2 pb-2 text-left font-normal">例：　210（円）</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <ExpenseRow v-for="(row, i) in rows" :key="i" v-model="rows[i]"
                                :purposes="purposes" :index="i" :month="month" :readonly="!editable"
                                :error="rowError(i)" @remove="removeRow(i)"
                                @duplicate="duplicateRow(i)" @multi="multiTarget = i" @register="registerRow(i)" @companions="companionTarget = i"
                                @next="onNext(i)" @destination-change="onDestinationChange(i, $event)" />
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-gray-300">
                                <td colspan="6" class="px-2 py-2 text-right text-sm font-medium text-gray-700">
                                    計（{{ rows.length }}行 / 用紙 {{ pageCount }}枚）
                                </td>
                                <td class="px-2 py-2 text-left font-bold text-gray-900">{{ totalAmount.toLocaleString() }} 円</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- 入力の操作（行の追加・クイック入力・貼り付け・登録した行先・前月コピー）をまとめる -->
                <div v-if="editable" class="mt-3 rounded border border-blue-100 bg-blue-50 p-3">
                    <div class="flex flex-wrap items-center gap-3">
                        <button type="button" @click="addRow"
                            class="rounded border border-blue-400 bg-white px-4 py-1.5 text-sm font-medium text-blue-700 hover:bg-blue-100">＋ 行を追加</button>
                        <div class="relative flex min-w-[16rem] flex-1 items-center gap-2">
                            <label class="shrink-0 text-sm font-medium text-gray-700">クイック入力</label>
                            <div class="relative w-full max-w-xs">
                                <input type="text" v-model="quickText" autocomplete="off" placeholder="例：0901 国際"
                                    @focus="quickFocused = true" @blur="quickFocused = false"
                                    @keydown="onQuickKey" @keydown.enter.prevent="onQuickEnter" @keydown.esc="quickFocused = false"
                                    class="w-full rounded border border-gray-300 px-3 py-1.5 text-sm focus:border-blue-400 focus:outline-none" />
                                <!-- 候補の窓: 選ぶと、その行先（区間・金額つき）が追加される -->
                                <div v-if="quickOpen" class="absolute left-0 top-full z-30 mt-1 w-[26rem] max-w-[90vw] overflow-hidden rounded border border-gray-300 bg-white shadow-lg">
                                    <p class="border-b bg-gray-50 px-3 py-1.5 text-xs text-gray-500">
                                        <template v-if="quickParsed?.keyword">候補をクリックして選びます（{{ quickParsed.dates.length ? quickParsed.dates.map(shortDate).join('、') + ' に追加' : '日付なしで追加' }}）</template>
                                        <template v-else>行先の文字を入れると絞り込めます。登録した行先から選べます</template>
                                    </p>
                                    <ul v-if="quickCandidates.length" class="max-h-64 overflow-y-auto">
                                        <li v-for="(s, i) in quickCandidates" :key="`${s.destination}|${s.station_from}|${s.station_to}|${s.purpose}`"
                                            @mousedown.prevent="pickQuick(s)" @mouseenter="quickActive = i"
                                            class="flex cursor-pointer items-center justify-between gap-3 px-3 py-2 text-sm"
                                            :class="i === quickActive ? 'bg-blue-50' : ''">
                                            <span class="min-w-0">
                                                <span class="font-medium text-gray-800">{{ s.destination }}</span>
                                                <span v-if="s.station_from || s.station_to" class="ml-2 text-xs text-gray-500">{{ s.station_from }}－{{ s.station_to }}</span>
                                                <span class="ml-2 rounded px-1 text-[10px]" :class="s.registered ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-500'">{{ s.registered ? '登録' : '履歴' }}</span>
                                            </span>
                                            <span class="shrink-0 text-xs text-gray-600">{{ (s.base_amount * (s.auto_double && (s.purpose === 'round_trip' || s.purpose === 'other') ? 2 : 1)).toLocaleString() }}円</span>
                                        </li>
                                    </ul>
                                    <p v-else class="px-3 py-3 text-sm text-gray-500">「{{ quickParsed?.keyword }}」に合う行先が、登録・履歴にありません。</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">
                        クイック入力の例：　「0901 国際」と入れると、「国際」に合う行先の候補が出るので、クリックして選びます。選ぶと、9月1日の明細として、区間・金額つきで追加されます。日付は複数できます（0901 0903 国際）。
                    </p>
                    <p v-if="quickMessage" class="mt-1 text-sm" :class="quickMessage.startsWith('追加しました') ? 'text-green-700' : 'text-red-600'">{{ quickMessage }}</p>

                    <div class="mt-3 flex flex-wrap items-center gap-3 border-t border-blue-100 pt-3">
                        <button type="button" @click="showPaste = true"
                            class="rounded border border-gray-300 bg-white px-4 py-1.5 text-sm text-gray-700 hover:bg-gray-50">まとめて貼り付け</button>
                        <button type="button" @click="showPresets = true"
                            class="rounded border border-gray-300 bg-white px-4 py-1.5 text-sm text-gray-700 hover:bg-gray-50">登録した行先（{{ registered.length }}件）</button>
                        <button type="button" @click="showPresets = true"
                            class="rounded border border-blue-400 bg-white px-4 py-1.5 text-sm font-medium text-blue-700 hover:bg-blue-50">＋ 行先の登録</button>
                        <button v-if="previousItems.length" type="button" @click="copyPrevious"
                            class="rounded border border-gray-300 bg-white px-4 py-1.5 text-sm text-gray-700 hover:bg-gray-50">前月の内容をコピー</button>
                        <span class="text-xs text-gray-500">発生日は {{ periodLabel }} の範囲で入力します。用紙は1枚に{{ rowsPerPage }}行です。</span>
                    </div>
                </div>

                <p class="mt-6 text-xs text-gray-500">※ 領収証がある場合は裏面に貼付して提出してください。</p>

                <!-- エラー -->
                <div v-if="otherErrors.length || Object.keys(errors).length" class="mt-4 rounded bg-red-50 p-3 text-sm text-red-600">
                    <ul class="list-inside list-disc space-y-1">
                        <li v-for="(msg, i) in otherErrors" :key="i">{{ msg }}</li>
                        <li v-if="!otherErrors.length">発生日に誤りがあります。赤い行を確認してください。</li>
                    </ul>
                </div>

                <!-- 申請者のコメント（任意）: 記載の理由・差戻しへの返事など。申請すると、履歴に残り、総務・経理の確認画面に出ます -->
                <div v-if="editable" class="mt-6 rounded border border-gray-200 bg-gray-50 p-3">
                    <label class="mb-1 block text-sm font-medium text-gray-700">
                        {{ status === 'returned' ? '差戻しへのコメント（任意）' : '申請者のコメント（任意）' }}
                    </label>
                    <textarea v-model="applicantComment" rows="3" maxlength="1000"
                        class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none"
                        :placeholder="status === 'returned'
                            ? '例：3行目：飲み会のあと、会社には戻らず直帰しました。この記載で正しいです。'
                            : '例：3行目：出先から、そのまま懇親会に行ったため、会社には戻っていません。'"></textarea>
                    <p class="mt-1 text-xs text-gray-500">
                        入れなくても、申請できます。行を指定するときは、「3行目：…」のように書くと、わかりやすくなります（書きかけは、自動で保存されます）。
                        <span class="float-right">{{ applicantComment.length }} / 1000</span>
                    </p>
                </div>

                <!-- 自動保存の状態 -->
                <div v-if="editable" class="mt-4 min-h-[1.25rem] text-right text-xs">
                    <span v-if="autosaveState === 'dirty'" class="text-gray-500">入力中…（入力が止まると、自動で保存されます）</span>
                    <span v-else-if="autosaveState === 'saving'" class="text-gray-500">保存中…</span>
                    <span v-else-if="autosaveState === 'saved'" class="text-green-700">自動保存しました（{{ savedAt }}）</span>
                    <span v-else-if="autosaveState === 'error'" class="text-red-600">自動保存できません: {{ autosaveMessage }}（直すと、自動で保存されます）</span>
                </div>

                <!-- ボタン -->
                <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
                    <div v-if="curId" class="flex flex-wrap gap-2">
                        <a :href="rt('pdf', { expense: curId }) + '?mode=inline'" target="_blank" @click="openOutput($event, 'pdf', true)"
                            class="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">印刷用PDFを開く</a>
                        <a :href="rt('pdf', { expense: curId })" @click="openOutput($event, 'pdf')"
                            class="rounded border border-blue-400 bg-white px-4 py-2 text-sm font-medium text-blue-700 hover:bg-blue-50">PDFを保存</a>
                        <a :href="rt('excel', { expense: curId })" @click="openOutput($event, 'excel')"
                            class="rounded border border-green-500 bg-white px-4 py-2 text-sm font-medium text-green-700 hover:bg-green-50">Excelを保存</a>
                        <span v-if="editable" class="self-center text-xs text-gray-500">※ 入力中の内容は、自動で保存してから出力します</span>
                    </div>
                    <div v-else class="text-xs text-gray-500">入力すると自動で保存され、印刷用PDF・Excelが出力できるようになります。</div>

                    <div class="flex flex-wrap gap-3">
                        <button v-if="canDelete" type="button" @click="deleteDraft"
                            class="rounded border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-red-50 hover:text-red-700">この月を削除</button>
                        <button v-if="status === 'submitted'" type="button" @click="withdraw"
                            class="rounded border border-amber-400 px-4 py-2 text-sm text-amber-700 hover:bg-amber-50">申請を取り下げる</button>
                        <template v-if="editable">
                            <button type="button" @click="saveDraft" :disabled="submitting"
                                class="rounded border border-blue-500 px-5 py-2 text-sm font-medium text-blue-700 hover:bg-blue-50 disabled:opacity-50">
                                {{ submitting ? '保存中...' : '下書き保存' }}
                            </button>
                            <button type="button" @click="saveAndSubmit" :disabled="submitting"
                                class="rounded bg-blue-600 px-6 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                                {{ status === 'returned' ? '修正して再申請' : '申請する' }}
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            <!-- この月の履歴（申請・受理・差戻しなど。差戻しのコメントも残る） -->
            <div v-if="expense?.events?.length" class="rounded bg-white p-6 shadow">
                <h3 class="mb-3 text-base font-semibold text-gray-800">{{ monthLabel(month) }}の履歴</h3>
                <ol class="space-y-2 text-sm">
                    <li v-for="ev in expense.events" :key="ev.id" class="border-l-2 border-gray-200 pl-3">
                        <span class="text-xs text-gray-500">{{ ev.created_at }}</span>
                        <span class="ml-2 font-medium text-gray-800">{{ actionLabels[ev.action] ?? ev.action }}</span>
                        <span v-if="ev.user_name" class="ml-2 text-gray-600">（{{ ev.user_name }}）</span>
                        <p v-if="ev.comment" class="mt-0.5 whitespace-pre-wrap text-gray-700">{{ ev.comment }}</p>
                    </li>
                </ol>
            </div>

            <!-- 申請履歴 -->
            <div v-if="history.length" class="rounded bg-white p-6 shadow">
                <h3 class="mb-3 text-base font-semibold text-gray-800">申請履歴</h3>
                <div class="flex flex-wrap gap-2">
                    <a v-for="h in history" :key="h.id"
                        :href="rt('index', { month: h.billing_month })"
                        class="rounded border px-3 py-2 text-sm hover:bg-gray-50"
                        :class="h.billing_month === month ? 'border-blue-400 bg-blue-50' : 'border-gray-200'">
                        <span class="font-medium text-gray-800">{{ monthLabel(h.billing_month) }}</span>
                        <span class="ml-2 rounded px-1.5 py-0.5 text-xs" :class="statusColor[h.status]">{{ statusLabels[h.status] }}</span>
                        <span class="ml-2 text-gray-600">{{ h.total_amount.toLocaleString() }}円</span>
                    </a>
                </div>
            </div>
        </div>

        <PasteImportModal v-if="showPaste" :month="month" :purposes="purposes"
            :has-rows="rows.some((r) => !isBlankRow(r))" @close="showPaste = false" @apply="onPasteApply" />
        <MultiDateModal v-if="multiTarget !== null" :month="month" :description="multiDescription"
            @close="multiTarget = null" @apply="onMultiApply" />
        <FareHelpModal v-if="showFareHelp" @close="showFareHelp = false" />
        <ViaHelpModal v-if="showViaHelp" @close="showViaHelp = false" />
        <UserPickerModal v-if="companionTarget !== null" :users="colleagues" :selected="(rows[companionTarget]?.companions ?? []).map((c) => c.id)"
            title="同行者を選択" @close="companionTarget = null" @apply="onCompanionsApply" />
        <PresetManagerModal v-if="showPresets" :presets="registered" :purposes="purposes" :route-base="routeBase" @close="showPresets = false" />
    </AppLayout>
</template>
