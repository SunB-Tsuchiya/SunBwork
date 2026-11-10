<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import ReportShell from '@/Components/SunA/Sales/ReportShell.vue';
import { useSalesChart } from '@/Composables/useSalesChart';

// サンエー印刷 売上レポートの名寄せ設定（SUNASALES_PLAN1.md §17）。
// 表記ゆれのある得意先（例: 「株式会社A」と「A（株）」）を、1社として集計するための設定。設定すると、分析の数字が常に統合された状態になる。
// 売上の行そのものは変わらない。変更できるのは取込できる人だけ（サーバー側のルートでも同じ条件で保護している）。
const page = usePage();
const canEdit = computed(() => !!page.props.auth?.salesReportCanImport);
const { yen } = useSalesChart();

const groups = ref([]);
const candidates = ref([]);
const candidatesChecked = ref(0); // 候補を探した得意先の数
const loadError = ref(false);    // データ取得に失敗したか（「候補がない」と区別して表示する）
const events = ref([]);
const eventsTotal = ref(0);
const loading = ref(false);
const busy = ref(false);
const message = ref('');
const error = ref('');

// 候補ごとの入力: 統合に含める得意先（チェック）と、グループ名
const candidateState = ref({}); // index => { checked: { key: bool }, name: string }

const load = async () => {
    loading.value = true;
    try {
        const [data, ev] = await Promise.all([
            axios.get(route('suna.sales.report.api.client_groups.data')),
            axios.get(route('suna.sales.report.api.client_groups.events')),
        ]);
        groups.value = data.data.groups;
        candidates.value = data.data.candidates;
        candidatesChecked.value = data.data.candidates_checked ?? 0;
        loadError.value = false;
        events.value = ev.data.events;
        eventsTotal.value = ev.data.total;
        const state = {};
        data.data.candidates.forEach((c, i) => {
            state[i] = { checked: Object.fromEntries(c.clients.map((x) => [x.client_key, true])), name: c.clients[0].name };
        });
        candidateState.value = state;
    } catch (e) {
        loadError.value = true;
        error.value = 'データの取得に失敗しました。画面を再読み込みしてください。';
    } finally {
        loading.value = false;
    }
};

// 変更の実行（成功したら再読み込み。失敗の理由はサーバーのメッセージをそのまま出す）
const run = async (fn, okMessage) => {
    busy.value = true;
    message.value = '';
    error.value = '';
    try {
        await fn();
        message.value = okMessage;
        await load();
        return true;
    } catch (e) {
        error.value = e.response?.data?.message ?? '操作に失敗しました。';
        return false;
    } finally {
        busy.value = false;
    }
};

// --- 候補を統合する ---
const checkedKeys = (i) => Object.entries(candidateState.value[i].checked).filter(([, v]) => v).map(([k]) => k);
const mergeCandidate = (i) => {
    const keys = checkedKeys(i);
    const name = (candidateState.value[i].name ?? '').trim();
    if (keys.length < 2) { error.value = '統合するには、2社以上にチェックを入れてください。'; return; }
    if (!name) { error.value = 'グループ名を入力してください。'; return; }
    if (!window.confirm(`${keys.length}社を「${name}」として1社に統合します。分析の数字が、統合された状態になります。よろしいですか？`)) return;
    run(() => axios.post(route('suna.sales.report.api.client_groups.store'), { name, client_keys: keys }), `「${name}」に統合しました。`);
};

// --- 手動で作る ---
const manualKeyword = ref('');
const manualResults = ref([]);
const manualSelected = ref([]); // { client_key, name }
const manualName = ref('');
let manualTimer = null;
const searchManual = async () => {
    const res = await axios.get(route('suna.sales.report.api.client_groups.clients'), { params: { keyword: manualKeyword.value, limit: 20 } });
    manualResults.value = res.data.clients;
};
watch(manualKeyword, () => { clearTimeout(manualTimer); manualTimer = setTimeout(searchManual, 300); });
const toggleManual = (c) => {
    const i = manualSelected.value.findIndex((x) => x.client_key === c.client_key);
    if (i >= 0) manualSelected.value.splice(i, 1);
    else {
        manualSelected.value.push({ client_key: c.client_key, name: c.name });
        if (!manualName.value) manualName.value = c.name;
    }
};
const isManualSelected = (c) => manualSelected.value.some((x) => x.client_key === c.client_key);
const createManual = async () => {
    const name = manualName.value.trim();
    if (manualSelected.value.length < 2) { error.value = '統合するには、2社以上選んでください。'; return; }
    if (!name) { error.value = 'グループ名を入力してください。'; return; }
    if (!window.confirm(`${manualSelected.value.length}社を「${name}」として1社に統合します。よろしいですか？`)) return;
    const ok = await run(() => axios.post(route('suna.sales.report.api.client_groups.store'), { name, client_keys: manualSelected.value.map((x) => x.client_key) }), `「${name}」に統合しました。`);
    if (ok) { manualSelected.value = []; manualName.value = ''; manualKeyword.value = ''; manualResults.value = []; searchManual(); }
};

// --- 設定済みのグループ ---
const renaming = ref({}); // groupId => 編集中の名前
const startRename = (g) => { renaming.value = { ...renaming.value, [g.id]: g.name }; };
const saveRename = (g) => {
    const name = (renaming.value[g.id] ?? '').trim();
    if (!name) { error.value = 'グループ名を入力してください。'; return; }
    run(() => axios.patch(route('suna.sales.report.api.client_groups.update', { group: g.id }), { name }), 'グループ名を変更しました。').then(() => {
        const next = { ...renaming.value };
        delete next[g.id];
        renaming.value = next;
    });
};
const removeMember = (g, m) => {
    const last = g.members.length <= 2;
    const text = last ? `「${m.name}」を外すと、グループが1社になるため、「${g.name}」は自動で解散します。よろしいですか？` : `「${m.name}」を「${g.name}」から外します。よろしいですか？`;
    if (!window.confirm(text)) return;
    run(() => axios.post(route('suna.sales.report.api.client_groups.members.remove', { group: g.id }), { client_key: m.client_key }), '外しました。');
};
const dissolve = (g) => {
    if (!window.confirm(`「${g.name}」を解散します。${g.members.length}社が別々の得意先に戻り、分析の数字も元に戻ります。よろしいですか？`)) return;
    run(() => axios.delete(route('suna.sales.report.api.client_groups.destroy', { group: g.id })), `「${g.name}」を解散しました。`);
};

// メンバーの追加（グループごとに検索）
const adding = ref(null); // 追加中のグループ id
const addKeyword = ref('');
const addResults = ref([]);
let addTimer = null;
const searchAdd = async () => {
    const res = await axios.get(route('suna.sales.report.api.client_groups.clients'), { params: { keyword: addKeyword.value, limit: 15 } });
    addResults.value = res.data.clients;
};
watch(addKeyword, () => { clearTimeout(addTimer); addTimer = setTimeout(searchAdd, 300); });
const openAdd = (g) => { adding.value = adding.value === g.id ? null : g.id; addKeyword.value = ''; addResults.value = []; if (adding.value) searchAdd(); };
const addMember = (g, c) => {
    run(() => axios.post(route('suna.sales.report.api.client_groups.members.add', { group: g.id }), { client_keys: [c.client_key] }), `「${c.name}」を追加しました。`).then(() => searchAdd());
};

const ACTION_LABELS = { create: '統合', rename: '名前変更', add: '追加', remove: '解除', dissolve: '解散' };
const when = (iso) => (iso ? new Date(iso).toLocaleString('ja-JP', { dateStyle: 'short', timeStyle: 'short' }) : '');

onMounted(() => { load(); searchManual(); });
</script>

<template>
    <ReportShell title="名寄せ設定" active="client_groups">
        <div class="space-y-4">
            <div class="rounded bg-indigo-50 p-3 text-xs text-indigo-800">
                表記ゆれのある得意先（例: 「株式会社A」と「A（株）」）を、1社にまとめて集計する設定です。
                <span class="font-semibold">設定すると、分析（年次分析・同月比較・得意先分析）の数字が、常に統合された状態になります。</span>
                売上の元データは変わりません。戻したいときは、グループを解散してください。
                <span v-if="!canEdit" class="ml-1 text-amber-700">（変更できるのは、取込できる人だけです）</span>
            </div>

            <p v-if="message" class="rounded bg-green-50 p-2 text-xs text-green-700">{{ message }}</p>
            <p v-if="error" class="rounded bg-red-50 p-2 text-xs text-red-700">{{ error }}</p>
            <p v-if="loading" class="text-xs text-gray-500">読み込み中...</p>

            <!-- 統合の候補 -->
            <div class="rounded bg-white p-4 shadow">
                <h2 class="text-sm font-semibold text-gray-900">統合の候補（{{ candidates.length }}件）</h2>
                <p class="mb-2 text-[11px] text-gray-400">名前の前後の「株式会社」「（株）」「（学）」などを除くと同じ名前になる得意先です。同じ会社かどうかは、人が判断して統合してください。</p>
                <p v-if="loadError" class="text-xs text-red-600">データを取得できなかったため、候補を表示できません（「候補がない」のではありません）。画面を再読み込みしてください。</p>
                <p v-else-if="loading && !candidatesChecked" class="text-xs text-gray-500">候補を探しています...</p>
                <p v-else-if="!candidates.length" class="text-xs text-gray-500">
                    {{ candidatesChecked.toLocaleString() }}社の得意先を調べましたが、法人格を除いて同じ名前になる組み合わせは見つかりませんでした（正常です）。
                    候補に出ない表記ゆれは、下の「手動で統合する」で統合できます。
                </p>
                <div v-for="(c, i) in candidates" :key="i" class="mb-2 rounded border border-gray-200 p-3">
                    <ul class="space-y-1">
                        <li v-for="x in c.clients" :key="x.client_key" class="flex items-center gap-2 text-xs">
                            <input v-if="candidateState[i]" v-model="candidateState[i].checked[x.client_key]" type="checkbox" class="rounded border-gray-300" :disabled="!canEdit" />
                            <span>{{ x.name }}</span>
                            <span class="text-gray-400">累計 {{ yen(x.total_amount) }}（〜{{ x.last_year }}年）</span>
                        </li>
                    </ul>
                    <div v-if="canEdit && candidateState[i]" class="mt-2 flex flex-wrap items-center gap-2">
                        <label class="text-xs text-gray-500">統合後の名前</label>
                        <input v-model="candidateState[i].name" type="text" class="w-64 rounded-md border-gray-300 text-xs shadow-sm" />
                        <button type="button" class="rounded bg-indigo-600 px-3 py-1 text-xs font-bold text-white hover:bg-indigo-700 disabled:opacity-50" :disabled="busy" @click="mergeCandidate(i)">統合する</button>
                    </div>
                </div>
            </div>

            <!-- 設定済みのグループ -->
            <div class="rounded bg-white p-4 shadow">
                <h2 class="mb-2 text-sm font-semibold text-gray-900">設定済みのグループ（{{ groups.length }}件）</h2>
                <p v-if="!groups.length" class="text-xs text-gray-500">まだ統合したグループはありません。</p>
                <div v-for="g in groups" :key="g.id" class="mb-3 rounded border border-gray-200 p-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <template v-if="renaming[g.id] !== undefined">
                            <input v-model="renaming[g.id]" type="text" class="w-64 rounded-md border-gray-300 text-sm shadow-sm" />
                            <button type="button" class="rounded bg-indigo-600 px-2 py-1 text-xs font-bold text-white disabled:opacity-50" :disabled="busy" @click="saveRename(g)">保存</button>
                            <button type="button" class="text-xs text-gray-500 hover:underline" @click="delete renaming[g.id]">キャンセル</button>
                        </template>
                        <template v-else>
                            <h3 class="text-sm font-bold text-gray-900">{{ g.name }}</h3>
                            <button v-if="canEdit" type="button" class="text-xs text-indigo-600 hover:underline" @click="startRename(g)">名前を変更</button>
                        </template>
                        <span class="text-xs text-gray-400">{{ g.members.length }}社</span>
                        <span v-if="canEdit" class="ml-auto flex gap-3">
                            <button type="button" class="text-xs text-indigo-600 hover:underline" @click="openAdd(g)">{{ adding === g.id ? '追加を閉じる' : '得意先を追加' }}</button>
                            <button type="button" class="text-xs text-red-600 hover:underline disabled:opacity-50" :disabled="busy" @click="dissolve(g)">解散する</button>
                        </span>
                    </div>
                    <ul class="mt-2 space-y-1">
                        <li v-for="m in g.members" :key="m.client_key" class="flex items-center gap-2 text-xs">
                            <span>{{ m.name }}</span>
                            <span class="text-gray-400">累計 {{ yen(m.total_amount) }}（〜{{ m.last_year }}年）</span>
                            <button v-if="canEdit" type="button" class="ml-auto text-red-600 hover:underline disabled:opacity-50" :disabled="busy" @click="removeMember(g, m)">外す</button>
                        </li>
                    </ul>
                    <div v-if="adding === g.id" class="mt-2 rounded bg-gray-50 p-2">
                        <input v-model="addKeyword" type="text" placeholder="追加する得意先名の一部を入力" class="w-full max-w-sm rounded-md border-gray-300 text-xs shadow-sm" />
                        <ul class="mt-1 max-h-40 space-y-1 overflow-y-auto">
                            <li v-for="c in addResults" :key="c.client_key" class="flex items-center gap-2 text-xs">
                                <span>{{ c.name }}</span>
                                <span class="text-gray-400">{{ yen(c.total_amount) }}</span>
                                <button type="button" class="ml-auto rounded border border-indigo-300 px-2 py-0.5 text-indigo-600 hover:bg-indigo-50 disabled:opacity-50" :disabled="busy" @click="addMember(g, c)">追加</button>
                            </li>
                            <li v-if="!addResults.length" class="text-xs text-gray-400">該当する得意先がありません（すでに別のグループにいる得意先は出ません）。</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- 手動で作る -->
            <div v-if="canEdit" class="rounded bg-white p-4 shadow">
                <h2 class="mb-1 text-sm font-semibold text-gray-900">手動で統合する</h2>
                <p class="mb-2 text-[11px] text-gray-400">得意先を検索して、2社以上を選びます（候補に出ない表記ゆれもこちらで統合できます）。</p>
                <input v-model="manualKeyword" type="text" placeholder="得意先名の一部を入力（空欄なら売上の多い順）" class="w-full max-w-md rounded-md border-gray-300 text-xs shadow-sm" />
                <ul class="mt-2 grid max-h-48 gap-1 overflow-y-auto sm:grid-cols-2">
                    <li v-for="c in manualResults" :key="c.client_key">
                        <button
                            type="button"
                            class="flex w-full items-center justify-between gap-2 rounded border px-2 py-1 text-left text-xs hover:bg-gray-50"
                            :class="isManualSelected(c) ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200'"
                            @click="toggleManual(c)"
                        >
                            <span class="min-w-0 overflow-hidden text-ellipsis whitespace-nowrap">{{ c.name }}</span>
                            <span class="whitespace-nowrap text-gray-400">{{ yen(c.total_amount) }}</span>
                        </button>
                    </li>
                </ul>
                <div v-if="manualSelected.length" class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                    <span class="text-gray-500">選択中（{{ manualSelected.length }}社）:</span>
                    <span v-for="x in manualSelected" :key="x.client_key" class="rounded bg-indigo-100 px-2 py-0.5 text-indigo-700">{{ x.name }}</span>
                </div>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <label class="text-xs text-gray-500">統合後の名前</label>
                    <input v-model="manualName" type="text" class="w-64 rounded-md border-gray-300 text-xs shadow-sm" />
                    <button type="button" class="rounded bg-indigo-600 px-3 py-1 text-xs font-bold text-white hover:bg-indigo-700 disabled:opacity-50" :disabled="busy" @click="createManual">統合する</button>
                </div>
            </div>

            <!-- 変更の履歴 -->
            <div class="rounded bg-white p-4 shadow">
                <h2 class="mb-2 text-sm font-semibold text-gray-900">変更の履歴<span class="ml-2 text-xs font-normal text-gray-400">新しい順・直近100件（全{{ eventsTotal }}件）</span></h2>
                <p v-if="!events.length" class="text-xs text-gray-500">まだ変更はありません。</p>
                <div v-else class="max-h-72 overflow-auto rounded border">
                    <table class="min-w-full text-xs tabular-nums">
                        <thead class="sticky top-0 bg-gray-100 text-gray-600">
                            <tr>
                                <th class="px-2 py-1 text-left">日時</th>
                                <th class="px-2 py-1 text-left">操作した人</th>
                                <th class="px-2 py-1 text-left">操作</th>
                                <th class="px-2 py-1 text-left">グループ</th>
                                <th class="px-2 py-1 text-left">得意先</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="e in events" :key="e.id" class="border-t">
                                <td class="px-2 py-1">{{ when(e.created_at) }}</td>
                                <td class="px-2 py-1">{{ e.user_name }}</td>
                                <td class="px-2 py-1">{{ ACTION_LABELS[e.action] ?? e.action }}</td>
                                <td class="px-2 py-1">{{ e.group_name }}</td>
                                <td class="px-2 py-1">{{ e.client_name ?? '' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </ReportShell>
</template>
