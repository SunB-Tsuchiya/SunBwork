<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { Link } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/layouts/AppLayout.vue';
import AdminNavigationTabs from '@/Components/Tabs/AdminNavigationTabs.vue';

const props = defineProps({
    feature:           { type: Object, required: true },   // { key, name }
    users:             { type: Array, default: () => [] },
    departments:       { type: Array, default: () => [] },
    noCompanySelected: { type: Boolean, default: false },
});

const EMPLOYMENT_LABELS = { regular: '正社員', contract: '契約社員', dispatch: '派遣社員', outsource: '業務委託' };
const employmentLabel = (t) => EMPLOYMENT_LABELS[t] ?? t ?? '';

// 在席ボード管理と同じ形: スイッチを動かして、まとめて「保存する」
const local = ref(props.users.map((u) => ({ ...u })));
const saved = ref(new Map(props.users.map((u) => [u.id, u.enabled])));   // 保存済みの状態
const selectedDept = ref(null);
const selectedEmployment = ref(null);
const saving = ref(false);
const justSaved = ref(false);
const errorMessage = ref('');

// 区分の絞り込み: 「正社員・契約社員」は 1 つにまとめる（契約社員は、シニア社員の言い換え）。派遣・業務委託は、該当者がいるときだけ出す
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
        && (!opt || opt.types.includes(u.employment_type)));
});

const enabledCount = computed(() => local.value.filter((u) => u.enabled).length);
const dirty = computed(() => local.value.some((u) => saved.value.get(u.id) !== u.enabled));
const changedCount = computed(() => local.value.filter((u) => saved.value.get(u.id) !== u.enabled).length);

function toggle(u) { u.enabled = !u.enabled; justSaved.value = false; }
/** 表示中の人を、まとめて ON / OFF */
function setVisible(value) {
    visibleUsers.value.forEach((u) => { u.enabled = value; });
    justSaved.value = false;
}

const progressBadges = (u) => [
    u.progress?.returned ? { label: `差戻し中 ${u.progress.returned}`, cls: 'bg-red-100 text-red-700' } : null,
    u.progress?.submitted ? { label: `申請済み ${u.progress.submitted}`, cls: 'bg-blue-100 text-blue-700' } : null,
    u.progress?.draft ? { label: `下書き ${u.progress.draft}`, cls: 'bg-gray-100 text-gray-600' } : null,
].filter(Boolean);

async function save() {
    errorMessage.value = '';
    // OFF にする人に、申請の途中（下書き・申請済み・差戻し中）があれば、確認する（OFF にすると、その人は、画面に入れなくなる）
    const turningOff = local.value.filter((u) => saved.value.get(u.id) && !u.enabled && progressBadges(u).length);
    if (turningOff.length && !confirm(
        '次の人は、申請の途中のものがあります。OFF にすると、その人は、交通費の画面に入れなくなり、直したり、再申請したりできなくなります。\n\n'
        + turningOff.map((u) => `・${u.name}（${progressBadges(u).map((b) => b.label).join('、')}）`).join('\n')
        + '\n\nこのまま保存しますか？')) return;

    saving.value = true;
    try {
        await axios.post(route('admin.applicants.update', { feature: props.feature.key }),
            { items: local.value.map((u) => ({ user_id: u.id, enabled: u.enabled })) },
            { headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '', Accept: 'application/json' } });
        saved.value = new Map(local.value.map((u) => [u.id, u.enabled]));
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
</script>

<template>
    <AppLayout :title="`${feature.name}の申請者管理`">
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('admin.applicants.index')"
                    class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300 whitespace-nowrap">← 申請者管理に戻る</Link>
                <h2 class="text-base font-semibold leading-tight text-gray-800 sm:text-xl">{{ feature.name }}の申請者管理</h2>
            </div>
        </template>
        <template #tabs>
            <AdminNavigationTabs active="applicants" />
        </template>

        <div v-if="noCompanySelected" class="mx-auto max-w-2xl rounded border border-yellow-300 bg-yellow-50 p-6 shadow">
            <p class="font-semibold text-yellow-800">会社が選択されていません</p>
            <p class="mt-1 text-sm text-yellow-700">右上の会社コンテキスト切り替えで、設定したい会社を選択してから、このページを開いてください。</p>
        </div>

        <div v-else class="mx-auto max-w-3xl rounded bg-white p-6 shadow">
            <p class="mb-4 text-sm text-gray-500">
                スイッチを ON にした人に、ユーザー用のタブ「申請」が出て、{{ feature.name }}を申請できます。OFF の人は、画面に入れません。
                変更したら、「保存する」を押してください。
            </p>

            <!-- 部署の絞り込み -->
            <div v-if="departments.length" class="mb-2 flex flex-wrap gap-2">
                <button type="button" :class="chip(selectedDept === null)" @click="selectedDept = null">すべての部署</button>
                <button v-for="d in departments" :key="d.id" type="button" :class="chip(selectedDept === d.id)" @click="selectedDept = d.id">{{ d.name }}</button>
            </div>
            <!-- 雇用形態の絞り込み（派遣・契約の人を、まとめて確認しやすくする） -->
            <div class="mb-3 flex flex-wrap items-center gap-2">
                <button type="button" :class="chip(selectedEmployment === null)" @click="selectedEmployment = null">すべての区分</button>
                <button v-for="o in employmentOptions" :key="o.key" type="button" :class="chip(selectedEmployment === o.key)" @click="selectedEmployment = o.key">{{ o.label }}</button>
            </div>

            <div class="mb-2 flex flex-wrap items-center gap-3 text-xs text-gray-500">
                <span>使う人: <strong class="text-gray-800">{{ enabledCount }}</strong> / {{ local.length }}人（表示中: {{ visibleUsers.length }}人）</span>
                <span class="ml-auto flex gap-2">
                    <button type="button" class="rounded border border-gray-300 px-2 py-1 text-gray-600 hover:bg-gray-50" @click="setVisible(true)">表示中をすべて ON</button>
                    <button type="button" class="rounded border border-gray-300 px-2 py-1 text-gray-600 hover:bg-gray-50" @click="setVisible(false)">表示中をすべて OFF</button>
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left text-xs font-medium text-gray-500">
                            <th class="pb-2 pr-3">名前</th>
                            <th class="pb-2 pr-3">部署</th>
                            <th class="pb-2 pr-3">役職</th>
                            <th class="pb-2 pr-3">区分</th>
                            <th class="pb-2 pr-3">申請の状況</th>
                            <th class="pb-2 text-center">申請を使う</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="u in visibleUsers" :key="u.id" :class="u.enabled ? '' : 'bg-gray-50'">
                            <td class="py-1.5 pr-3 font-medium" :class="u.enabled ? 'text-gray-800' : 'text-gray-500'">{{ u.name }}</td>
                            <td class="py-1.5 pr-3 text-xs text-gray-500">{{ u.department }}</td>
                            <td class="py-1.5 pr-3 text-xs text-gray-500">{{ u.position_title ?? '―' }}</td>
                            <td class="py-1.5 pr-3 text-xs text-gray-500">{{ employmentLabel(u.employment_type) }}</td>
                            <td class="py-1.5 pr-3">
                                <span v-for="b in progressBadges(u)" :key="b.label" class="mr-1 rounded px-1.5 py-0.5 text-xs" :class="b.cls">{{ b.label }}</span>
                            </td>
                            <td class="py-1.5 text-center">
                                <button type="button" role="switch" :aria-checked="u.enabled"
                                    class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200"
                                    :class="u.enabled ? 'bg-red-500' : 'bg-gray-300'" @click="toggle(u)">
                                    <span class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200" :class="u.enabled ? 'translate-x-4' : 'translate-x-0'" />
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
                <Link :href="route('admin.applicants.index')" class="rounded bg-gray-100 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-200">キャンセル</Link>
                <button type="button" class="rounded bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50" :disabled="saving || !dirty" @click="save">
                    {{ saving ? '保存中…' : '保存する' }}
                </button>
            </div>
        </div>
    </AppLayout>
</template>
