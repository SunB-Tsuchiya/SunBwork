<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { Link } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/layouts/AppLayout.vue';
import AdminNavigationTabs from '@/Components/Tabs/AdminNavigationTabs.vue';

// 売上レポート（営業）の利用メンバー設定（サンエー印刷用。Admin エリア）。
// ⚠️ 画面の形は、ポートフォリオの利用メンバー設定・申請者管理のメンバー設定と同じ「標準形」
//    （CONSOLIDATED_01「ユーザー選択（権限付与）画面の標準形」）。
//    部署・区分・担当のチップ → 件数と「表示中をすべて ON/OFF」→ 表 → スイッチを動かして、まとめて「保存する」。
//    この機能だけの追加: ①役職のチップ（部長クラスを選びやすくする） ②スイッチが2つ（見る／取込）。
//    並び順は サーバー側（MemberList）で 雇用区分 → 役職 → 氏名。ここでは並べ替えない。
const props = defineProps({
    routePrefix:     { type: String, required: true },
    users:           { type: Array, default: () => [] },
    departments:     { type: Array, default: () => [] },
    assignmentNames: { type: Array, default: () => [] },
    positionNames:   { type: Array, default: () => [] },
});

const EMPLOYMENT_LABELS = { regular: '正社員', contract: '契約社員', dispatch: '派遣社員', outsource: '業務委託' };
const employmentLabel = (t) => EMPLOYMENT_LABELS[t] ?? t ?? '';

// 保存済みの状態（"閲覧:取込" の文字列で比べる）
const stateKey = (u) => `${u.enabled ? 1 : 0}:${u.can_import ? 1 : 0}`;
const local = ref(props.users.map((u) => ({ ...u })));
const saved = ref(new Map(props.users.map((u) => [u.id, stateKey(u)])));
const selectedDept = ref(null);
const selectedEmployment = ref(null);
const selectedAssignment = ref(null);
const selectedPosition = ref(null);
const saving = ref(false);
const justSaved = ref(false);
const errorMessage = ref('');

// 区分の絞り込み: 「正社員・契約社員」は 1 つにまとめる。派遣・業務委託は、該当者がいるときだけ出す
const STAFF_TYPES = ['regular', 'contract'];
const employmentOptions = computed(() => {
    const present = new Set(local.value.map((u) => u.employment_type));
    return [
        { key: 'staff', label: '正社員・契約社員', types: STAFF_TYPES },
        ...['dispatch', 'outsource'].filter((t) => present.has(t)).map((t) => ({ key: t, label: EMPLOYMENT_LABELS[t], types: [t] })),
    ];
});

const visibleUsers = computed(() => {
    const opt = employmentOptions.value.find((o) => o.key === selectedEmployment.value);
    return local.value.filter((u) =>
        (selectedDept.value === null || u.department_id === selectedDept.value)
        && (!opt || opt.types.includes(u.employment_type))
        && (selectedAssignment.value === null || u.assignment_name === selectedAssignment.value)
        && (selectedPosition.value === null || u.position_title === selectedPosition.value));
});

const viewCount = computed(() => local.value.filter((u) => u.enabled).length);
const importCount = computed(() => local.value.filter((u) => u.can_import).length);
const dirty = computed(() => local.value.some((u) => saved.value.get(u.id) !== stateKey(u)));
const changedCount = computed(() => local.value.filter((u) => saved.value.get(u.id) !== stateKey(u)).length);

// 見る: OFF にすると、取込も OFF になる（取込は、見られることが前提）
function toggleView(u) {
    u.enabled = !u.enabled;
    if (!u.enabled) u.can_import = false;
    justSaved.value = false;
}
// 取込: ON にすると、見るも ON になる
function toggleImport(u) {
    u.can_import = !u.can_import;
    if (u.can_import) u.enabled = true;
    justSaved.value = false;
}
/** 表示中の人を、まとめて「見る」ON / OFF（OFF のときは取込も OFF） */
function setVisibleView(value) {
    visibleUsers.value.forEach((u) => { u.enabled = value; if (!value) u.can_import = false; });
    justSaved.value = false;
}
/** 表示中の人を、まとめて「取込」ON / OFF（ON のときは見るも ON） */
function setVisibleImport(value) {
    visibleUsers.value.forEach((u) => { u.can_import = value; if (value) u.enabled = true; });
    justSaved.value = false;
}

async function save() {
    errorMessage.value = '';
    saving.value = true;
    try {
        await axios.put(route(`${props.routePrefix}.suna.sales.permissions.update`),
            { items: local.value.map((u) => ({ user_id: u.id, enabled: u.enabled, can_import: u.can_import })) },
            { headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '', Accept: 'application/json' } });
        saved.value = new Map(local.value.map((u) => [u.id, stateKey(u)]));
        justSaved.value = true;
    } catch (e) {
        errorMessage.value = e.response?.data?.message ?? '保存できませんでした。もう一度お試しください。';
    } finally {
        saving.value = false;
    }
}

// 保存していない変更があるまま、ページを離れようとしたら、確認する
function onBeforeUnload(e) { if (dirty.value) { e.preventDefault(); e.returnValue = ''; } }
onMounted(() => window.addEventListener('beforeunload', onBeforeUnload));
onBeforeUnmount(() => window.removeEventListener('beforeunload', onBeforeUnload));

const chip = (active) => ['rounded-full px-3 py-1 text-xs font-medium transition-colors', active ? 'bg-red-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'];
const switchClass = (on) => ['relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200', on ? 'bg-red-500' : 'bg-gray-300'];
</script>

<template>
    <AppLayout title="売上レポート利用設定">
        <template #header>
            <h2 class="text-base font-semibold leading-tight text-gray-800 sm:text-xl">売上レポート利用設定</h2>
        </template>
        <template #tabs>
            <AdminNavigationTabs active="sales_report_permissions" />
        </template>

        <div class="mx-auto max-w-5xl">
            <div class="rounded bg-white px-6 pb-6 pt-5 shadow-md">
            <p class="mb-4 text-sm text-gray-500">
                営業の「売上レポート」を、見られる人・取込できる人を決めます（部長クラスなど、許可した人だけが見られます。初めは全員 OFF です）。
                「見る」を ON にした人は、ロール行の「営業」から売上レポートを開けます。「取込」を ON にした人は、売上の Excel を取り込めます（取込は、見られることが前提です）。
                Admin（名前の横にバッジ）も、スイッチで決めます（自分にも許可を付けられます）。SuperAdmin は、開発・確認のため、設定に関わらず常に使えます。
                変更したら、「保存する」を押してください。
            </p>

            <!-- 部署の絞り込み -->
            <div v-if="departments.length" class="mb-2 flex flex-wrap gap-2">
                <button type="button" :class="chip(selectedDept === null)" @click="selectedDept = null">すべての部署</button>
                <button v-for="d in departments" :key="d.id" type="button" :class="chip(selectedDept === d.id)" @click="selectedDept = d.id">{{ d.name }}</button>
            </div>
            <!-- 役職の絞り込み（部長クラスを選びやすくする） -->
            <div v-if="positionNames.length" class="mb-2 flex flex-wrap items-center gap-2">
                <button type="button" :class="chip(selectedPosition === null)" @click="selectedPosition = null">すべての役職</button>
                <button v-for="p in positionNames" :key="p" type="button" :class="chip(selectedPosition === p)" @click="selectedPosition = p">{{ p }}</button>
            </div>
            <!-- 雇用形態の絞り込み -->
            <div class="mb-2 flex flex-wrap items-center gap-2">
                <button type="button" :class="chip(selectedEmployment === null)" @click="selectedEmployment = null">すべての区分</button>
                <button v-for="o in employmentOptions" :key="o.key" type="button" :class="chip(selectedEmployment === o.key)" @click="selectedEmployment = o.key">{{ o.label }}</button>
            </div>
            <!-- 担当（営業・制作など）の絞り込み -->
            <div v-if="assignmentNames.length" class="mb-3 flex flex-wrap items-center gap-2">
                <button type="button" :class="chip(selectedAssignment === null)" @click="selectedAssignment = null">すべての担当</button>
                <button v-for="a in assignmentNames" :key="a" type="button" :class="chip(selectedAssignment === a)" @click="selectedAssignment = a">{{ a }}</button>
            </div>

            <div class="mb-2 flex flex-wrap items-center gap-3 text-xs text-gray-500">
                <span>見る: <strong class="text-gray-800">{{ viewCount }}</strong>人・取込: <strong class="text-gray-800">{{ importCount }}</strong>人 / 全{{ local.length }}人（表示中: {{ visibleUsers.length }}人）</span>
                <span class="ml-auto flex flex-wrap gap-2">
                    <button type="button" class="rounded border border-gray-300 px-2 py-1 text-gray-600 hover:bg-gray-50" @click="setVisibleView(true)">表示中の「見る」を ON</button>
                    <button type="button" class="rounded border border-gray-300 px-2 py-1 text-gray-600 hover:bg-gray-50" @click="setVisibleView(false)">表示中の「見る」を OFF</button>
                    <button type="button" class="rounded border border-gray-300 px-2 py-1 text-gray-600 hover:bg-gray-50" @click="setVisibleImport(true)">表示中の「取込」を ON</button>
                    <button type="button" class="rounded border border-gray-300 px-2 py-1 text-gray-600 hover:bg-gray-50" @click="setVisibleImport(false)">表示中の「取込」を OFF</button>
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left text-xs font-medium text-gray-500">
                            <th class="pb-2 pr-3">名前</th>
                            <th class="pb-2 pr-3">部署</th>
                            <th class="pb-2 pr-3">役職</th>
                            <th class="pb-2 pr-3">担当</th>
                            <th class="pb-2 pr-3">区分</th>
                            <th class="pb-2 pr-2 text-center">見る</th>
                            <th class="pb-2 text-center">取込</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="u in visibleUsers" :key="u.id" :class="u.enabled ? '' : 'bg-gray-50'">
                            <td class="py-1.5 pr-3 font-medium" :class="u.enabled ? 'text-gray-800' : 'text-gray-500'">
                                {{ u.name }}
                                <span v-if="u.role_label" class="ml-1 rounded bg-red-100 px-1.5 py-0.5 align-middle text-[10px] font-medium text-red-700">{{ u.role_label }}</span>
                            </td>
                            <td class="py-1.5 pr-3 text-xs text-gray-500">{{ u.department }}</td>
                            <td class="py-1.5 pr-3 text-xs text-gray-500">{{ u.position_title ?? '―' }}</td>
                            <td class="py-1.5 pr-3 text-xs text-gray-500">{{ u.assignment_name ?? '―' }}</td>
                            <td class="py-1.5 pr-3 text-xs text-gray-500">{{ employmentLabel(u.employment_type) }}</td>
                            <td class="py-1.5 pr-2 text-center">
                                <button type="button" role="switch" :aria-checked="u.enabled" :class="switchClass(u.enabled)" @click="toggleView(u)">
                                    <span class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200" :class="u.enabled ? 'translate-x-4' : 'translate-x-0'" />
                                </button>
                            </td>
                            <td class="py-1.5 text-center">
                                <button type="button" role="switch" :aria-checked="u.can_import" :class="switchClass(u.can_import)" @click="toggleImport(u)">
                                    <span class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200" :class="u.can_import ? 'translate-x-4' : 'translate-x-0'" />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p v-if="!visibleUsers.length" class="py-6 text-center text-sm text-gray-400">該当する人がいません。</p>
            </div>

            <p v-if="errorMessage" class="mt-4 rounded bg-red-50 p-3 text-sm text-red-600">{{ errorMessage }}</p>
            <div class="mt-6 flex items-center justify-end gap-3">
                <span v-if="justSaved" class="mr-auto text-sm text-green-600">✓ 保存しました</span>
                <span v-else-if="dirty" class="mr-auto text-sm text-amber-700">未保存の変更があります（{{ changedCount }}人）</span>
                <Link :href="route('admin.dashboard')" class="rounded bg-gray-100 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-200">キャンセル</Link>
                <button type="button" class="rounded bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50" :disabled="saving || !dirty" @click="save">
                    {{ saving ? '保存中…' : '保存する' }}
                </button>
            </div>
            </div>
        </div>
    </AppLayout>
</template>
