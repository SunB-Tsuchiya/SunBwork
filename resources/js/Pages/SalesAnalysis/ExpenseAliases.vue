<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import SalesAnalysisShell from '@/Components/SalesAnalysis/SalesAnalysisShell.vue';
import axios from 'axios';
import { useSalesChart } from '@/Composables/useSalesChart';

// 経費の名寄せ設定（Phase24）。経費Excelは年度ごとに科目名の表記が変わる
// （例: 外注加工→外注加工費、消耗品→消耗品費）ため、完全一致の比較だと前年が「新規」扱いになる。
// 別名を正式名へ読み替える対応表を、候補を承認しながら作る。取込済みのデータは書き換えない。
// 科目名（部署ごと）と支払先名（部署をまたぐ。Phase24-3）の両方を扱う。

const props = defineProps({
    routePrefix: { type: String, required: true },
    departmentLabels: { type: Object, default: () => ({}) },
    enabledDepartmentKeys: { type: Array, default: () => [] },
    hasCompanySelected: { type: Boolean, default: true },
    supportsExpenses: { type: Boolean, default: false },
});

const rn = (name) => `${props.routePrefix}.sales_analysis.${name}`;
const { fiscalPeriodNumber } = useSalesChart();

const departmentTabs = computed(() => [
    ...props.enabledDepartmentKeys.map((k) => ({ key: k, label: props.departmentLabels[k] ?? k })),
    { key: 'shared', label: '共通費' },
]);
const departmentKey = ref(props.enabledDepartmentKeys[0] ?? 'planning');
// 名寄せの対象: 科目名（category）／支払先名（vendor）
const kind = ref('category');
const isVendor = computed(() => kind.value === 'vendor');
const kindParams = () => (isVendor.value ? { kind: 'vendor' } : { kind: 'category', department_key: departmentKey.value });
// 支払先は名前が多い（数百）ので、手動統合の一覧を絞り込む
const unmappedFilter = ref('');

const loading = ref(false);
const saving = ref(false);
const errorMessage = ref('');
const notice = ref('');
const overview = ref({ groups: [], suggestions: [], unmapped: [] });

// 候補カードごとの正式名の選択（候補の既定は最新年度の名前）
const chosenCanonical = reactive({});
// 手動統合: チェックした科目名と、正式名にする名前
const manualSelected = ref([]);
const manualCanonical = ref('');
// 登録済みグループへ名前を追加するときの選択（正式名ごと）
const addTarget = reactive({});

const unitLabel = computed(() => (isVendor.value ? '支払先名' : '科目名'));
const filteredUnmapped = computed(() => {
    const f = unmappedFilter.value.trim();
    return f ? overview.value.unmapped.filter((u) => u.name.includes(f)) : overview.value.unmapped;
});

const yearsLabel = (years) => (years?.length ? `${years.map((y) => fiscalPeriodNumber(y)).join('・')}期` : '');

const applyOverview = (data) => {
    overview.value = { groups: data.groups, suggestions: data.suggestions, unmapped: data.unmapped };
    Object.keys(chosenCanonical).forEach((k) => delete chosenCanonical[k]);
    data.suggestions.forEach((s) => { chosenCanonical[s.canonical] = s.canonical; });
    manualSelected.value = [];
    manualCanonical.value = '';
};

const fetchOverview = async () => {
    if (!props.supportsExpenses) return;
    loading.value = true;
    errorMessage.value = '';
    try {
        const response = await axios.get(route(rn('api.expense_aliases.data')), { params: kindParams() });
        applyOverview(response.data);
    } catch (e) {
        errorMessage.value = 'データの取得に失敗しました。';
    } finally {
        loading.value = false;
    }
};

const post = async (routeName, payload, doneMessage) => {
    saving.value = true;
    errorMessage.value = '';
    notice.value = '';
    try {
        const response = await axios.post(route(rn(routeName)), { ...kindParams(), ...payload });
        applyOverview(response.data);
        notice.value = typeof doneMessage === 'function' ? doneMessage(response.data) : doneMessage;
    } catch (e) {
        errorMessage.value = e.response?.data?.message ?? '保存に失敗しました。';
    } finally {
        saving.value = false;
    }
};

// 候補を、選んだ正式名で承認する（候補の他の名前が別名になる）
const approveSuggestion = (s) => {
    const canonical = chosenCanonical[s.canonical] ?? s.canonical;
    const names = [s.canonical, ...s.aliases.map((a) => a.name)];
    return post('api.expense_aliases.save', { canonical, aliases: names.filter((n) => n !== canonical) }, `「${canonical}」に統合しました。`);
};

const approveAll = () => post('api.expense_aliases.approve', {}, (d) => `${d.approved_count}件の候補を統合しました。`);

const ungroup = (g) => post('api.expense_aliases.save', { canonical: g.canonical, aliases: [] }, `「${g.canonical}」の統合を解除しました。`);

const addToGroup = (g) => {
    const name = addTarget[g.canonical];
    if (!name) return;
    delete addTarget[g.canonical];
    return post('api.expense_aliases.save', { canonical: g.canonical, aliases: [...g.aliases.map((a) => a.name), name] }, `「${name}」を「${g.canonical}」に統合しました。`);
};

const removeAlias = (g, aliasName) => post(
    'api.expense_aliases.save',
    { canonical: g.canonical, aliases: g.aliases.map((a) => a.name).filter((n) => n !== aliasName) },
    `「${aliasName}」を統合から外しました。`,
);

const canMergeManual = computed(() => manualSelected.value.length >= 2 && manualSelected.value.includes(manualCanonical.value));
watch(manualSelected, (list) => {
    if (!list.includes(manualCanonical.value)) manualCanonical.value = list[0] ?? '';
});
const mergeManual = () => post(
    'api.expense_aliases.save',
    { canonical: manualCanonical.value, aliases: manualSelected.value.filter((n) => n !== manualCanonical.value) },
    `「${manualCanonical.value}」に統合しました。`,
);

watch([departmentKey, kind], () => {
    notice.value = '';
    unmappedFilter.value = '';
    fetchOverview();
});
onMounted(fetchOverview);
</script>

<template>
    <SalesAnalysisShell title="経費の名寄せ" :route-prefix="routePrefix" active="expense_aliases">
        <div v-if="!hasCompanySelected" class="rounded bg-white p-6 shadow">
            <p class="text-sm text-gray-500">会社が選択されていません。画面右上の会社切替から対象の会社を選択してください。</p>
        </div>

        <div v-else-if="!supportsExpenses" class="rounded bg-white p-6 shadow">
            <p class="text-sm text-gray-500">この会社では経費機能は利用できません。</p>
        </div>

        <div v-else class="space-y-4">
            <div class="rounded bg-white p-4 shadow">
                <p class="mb-2 text-xs text-gray-500">
                    経費Excelは年度ごとに科目名・支払先名の表記が変わります（例: 外注加工→外注加工費、西武プロセス→㈱西武プロセス）。
                    同じものをひとつにまとめると、部署シートや科目別比較、支払先カルテで、年度をまたいで正しく比べられます。
                    取り込んだデータは書き換えず、表示するときだけ読み替えます。
                </p>
                <div class="mb-2 flex gap-1 border-b text-sm">
                    <button
                        type="button"
                        class="-mb-px border-b-2 px-3 py-1.5"
                        :class="kind === 'category' ? 'border-indigo-600 font-semibold text-indigo-700' : 'border-transparent text-gray-500 hover:text-gray-700'"
                        @click="kind = 'category'"
                    >科目名</button>
                    <button
                        type="button"
                        class="-mb-px border-b-2 px-3 py-1.5"
                        :class="kind === 'vendor' ? 'border-indigo-600 font-semibold text-indigo-700' : 'border-transparent text-gray-500 hover:text-gray-700'"
                        @click="kind = 'vendor'"
                    >支払先名</button>
                </div>
                <div v-if="!isVendor" class="flex flex-wrap gap-1 rounded border bg-gray-50 p-1 text-xs">
                    <button
                        v-for="tab in departmentTabs"
                        :key="tab.key"
                        type="button"
                        class="rounded px-2 py-1"
                        :class="departmentKey === tab.key ? 'bg-gray-700 text-white' : 'text-gray-600 hover:bg-gray-100'"
                        @click="departmentKey = tab.key"
                    >{{ tab.label }}</button>
                </div>
                <p v-if="notice" class="mt-3 rounded bg-green-50 p-2 text-xs text-green-700">{{ notice }}</p>
                <p v-if="errorMessage" class="mt-3 rounded bg-red-50 p-2 text-xs text-red-700">{{ errorMessage }}</p>
                <p v-if="loading" class="mt-3 text-xs text-gray-500">読み込み中...</p>
            </div>

            <template v-if="!loading">
                <!-- 統合候補 -->
                <div class="rounded bg-white p-4 shadow">
                    <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold text-gray-900">統合の候補（{{ overview.suggestions.length }}件）</h3>
                        <button
                            v-if="overview.suggestions.length"
                            type="button"
                            :disabled="saving"
                            class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-indigo-700 disabled:opacity-50"
                            @click="approveAll"
                        >候補をすべて統合する</button>
                    </div>
                    <p class="mb-3 text-[11px] text-gray-400">
                        <template v-if="!isVendor">「費・料」の有無、「主」「主要」、「・」の並び順だけが違う科目を、機械的に候補にしています。意味が変わりそうなもの（例: 外注→外注加工費）は、下の「手動で統合」で選んでください。</template>
                        <template v-else>全角半角・空白、法人格の表記（株式会社・㈱・有限会社・㈲ など）、括弧内の注記（例:「（突発分）」）だけが違う支払先を、機械的に候補にしています。「突発分」のように別の行として見たいものは承認せず、社名変更のように名前が違うものは、下の「手動で統合」で選んでください。</template>
                    </p>
                    <p v-if="!overview.suggestions.length" class="text-xs text-gray-500">統合の候補はありません。</p>
                    <div class="grid gap-3 md:grid-cols-2">
                        <div v-for="s in overview.suggestions" :key="s.canonical" class="rounded border border-amber-200 bg-amber-50 p-3 text-xs">
                            <p class="mb-1 text-gray-600">正式名にする名前を選んで統合します</p>
                            <label v-for="n in [{ name: s.canonical, years: s.canonical_years }, ...s.aliases]" :key="n.name" class="flex items-center gap-2 py-0.5">
                                <input v-model="chosenCanonical[s.canonical]" type="radio" :value="n.name" :name="`cand-${s.canonical}`" />
                                <span class="font-medium text-gray-800">{{ n.name }}</span>
                                <span class="text-gray-400">{{ yearsLabel(n.years) }}</span>
                            </label>
                            <button
                                type="button"
                                :disabled="saving"
                                class="mt-2 rounded-md border border-indigo-300 bg-white px-2 py-1 text-indigo-700 hover:bg-indigo-50 disabled:opacity-50"
                                @click="approveSuggestion(s)"
                            >この内容で統合する</button>
                        </div>
                    </div>
                </div>

                <!-- 登録済み -->
                <div class="rounded bg-white p-4 shadow">
                    <h3 class="mb-2 text-sm font-semibold text-gray-900">登録済みの統合（{{ overview.groups.length }}件）</h3>
                    <p v-if="!overview.groups.length" class="text-xs text-gray-500">まだ統合は登録されていません。</p>
                    <div class="space-y-2">
                        <div v-for="g in overview.groups" :key="g.canonical" class="rounded border p-3 text-xs">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p>
                                    <span class="font-bold text-gray-900">{{ g.canonical }}</span>
                                    <span class="ml-1 text-gray-400">{{ yearsLabel(g.canonical_years) }}</span>
                                    <span class="ml-2 text-gray-500">に統合:</span>
                                </p>
                                <button type="button" :disabled="saving" class="text-red-500 hover:text-red-700 disabled:opacity-50" @click="ungroup(g)">この統合をすべて解除</button>
                            </div>
                            <div class="mt-1 flex flex-wrap gap-1">
                                <span v-for="a in g.aliases" :key="a.name" class="inline-flex items-center gap-1 rounded bg-gray-100 px-2 py-0.5 text-gray-700">
                                    {{ a.name }}<span class="text-gray-400">{{ yearsLabel(a.years) }}</span>
                                    <button type="button" :disabled="saving" class="text-gray-400 hover:text-red-600" title="この名前を統合から外す" @click="removeAlias(g, a.name)">×</button>
                                </span>
                            </div>
                            <div v-if="overview.unmapped.length" class="mt-2 flex items-center gap-1">
                                <select v-model="addTarget[g.canonical]" class="rounded-md border-gray-300 text-xs shadow-sm">
                                    <option :value="undefined">名前を追加する…</option>
                                    <option v-for="u in overview.unmapped" :key="u.name" :value="u.name">{{ u.name }}（{{ yearsLabel(u.years) }}）</option>
                                </select>
                                <button type="button" :disabled="saving || !addTarget[g.canonical]" class="rounded border border-gray-300 px-2 py-1 text-gray-600 hover:bg-gray-50 disabled:opacity-40" @click="addToGroup(g)">追加</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 手動 -->
                <div class="rounded bg-white p-4 shadow">
                    <h3 class="mb-2 text-sm font-semibold text-gray-900">手動で統合（どれにも属していない{{ unitLabel }}）</h3>
                    <p v-if="!overview.unmapped.length" class="text-xs text-gray-500">統合されていない{{ unitLabel }}はありません。</p>
                    <template v-else>
                        <p class="mb-2 text-[11px] text-gray-400">同じものを2つ以上チェックし、正式名にする名前を選んで統合します。チェックは絞り込みを変えても残ります。</p>
                        <input v-model="unmappedFilter" type="search" :placeholder="`${unitLabel}で絞り込み`" class="mb-2 w-full max-w-sm rounded-md border-gray-300 text-xs shadow-sm" />
                        <p v-if="!filteredUnmapped.length" class="text-xs text-gray-500">該当する名前はありません。</p>
                        <div class="grid max-h-96 gap-x-4 gap-y-1 overflow-auto text-xs sm:grid-cols-2 lg:grid-cols-3">
                            <label v-for="u in filteredUnmapped" :key="u.name" class="flex items-center gap-2">
                                <input v-model="manualSelected" type="checkbox" :value="u.name" class="rounded border-gray-300" />
                                <span class="text-gray-800">{{ u.name }}</span>
                                <span class="text-gray-400">{{ yearsLabel(u.years) }}</span>
                            </label>
                        </div>
                        <div v-if="manualSelected.length >= 2" class="mt-3 flex flex-wrap items-center gap-2 text-xs">
                            <span class="text-gray-600">正式名:</span>
                            <select v-model="manualCanonical" class="rounded-md border-gray-300 text-xs shadow-sm">
                                <option v-for="n in manualSelected" :key="n" :value="n">{{ n }}</option>
                            </select>
                            <button
                                type="button"
                                :disabled="saving || !canMergeManual"
                                class="rounded-md bg-indigo-600 px-3 py-1.5 font-bold text-white hover:bg-indigo-700 disabled:opacity-50"
                                @click="mergeManual"
                            >{{ manualSelected.length }}件を統合する</button>
                        </div>
                    </template>
                </div>
            </template>
        </div>
    </SalesAnalysisShell>
</template>
