<script setup>
import { onMounted, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import axios from 'axios';
import SalesAnalysisNavigationTabs from '@/Components/SalesAnalysis/SalesAnalysisNavigationTabs.vue';

const props = defineProps({
    routePrefix: { type: String, required: true },
    departmentLabels: { type: Object, default: () => ({}) },
});

// 売上分析ルートは superadmin/admin/clerk の各ロールグループ内に複製登録されている
const rn = (name) => `${props.routePrefix}.sales_analysis.${name}`;

const loading = ref(false);
const errorMessage = ref('');
const statusMessage = ref('');

const candidates = ref([]);
const groups = ref([]);
const unassignedClients = ref([]);
const unassignedSearch = ref('');

const selectedNames = ref(new Set());
const newGroupName = ref('');
const previewResult = ref(null);
const previewing = ref(false);
const saving = ref(false);

const editingGroupId = ref(null);
const editingGroupName = ref('');

const yen = (v) => (v === null || v === undefined ? '—' : `¥${Number(v).toLocaleString()}`);

const fetchData = async () => {
    loading.value = true;
    errorMessage.value = '';
    try {
        const response = await axios.get(route(rn('api.client_groups')));
        candidates.value = response.data.candidates;
        groups.value = response.data.groups;
        unassignedClients.value = response.data.unassigned_clients;
    } catch (e) {
        errorMessage.value = 'データの取得に失敗しました。';
    } finally {
        loading.value = false;
    }
};

const filteredUnassigned = () => {
    if (!unassignedSearch.value.trim()) return unassignedClients.value;
    const kw = unassignedSearch.value.trim();
    return unassignedClients.value.filter((c) => c.client_name.includes(kw));
};

const toggleSelect = (name) => {
    const next = new Set(selectedNames.value);
    if (next.has(name)) next.delete(name); else next.add(name);
    selectedNames.value = next;
    previewResult.value = null;
};

const startFromCandidate = (candidate) => {
    selectedNames.value = new Set(candidate.client_names);
    newGroupName.value = candidate.client_names[0];
    previewResult.value = null;
    statusMessage.value = '';
};

const clearSelection = () => {
    selectedNames.value = new Set();
    newGroupName.value = '';
    previewResult.value = null;
};

const runPreview = async () => {
    if (selectedNames.value.size === 0) return;
    previewing.value = true;
    errorMessage.value = '';
    try {
        const response = await axios.post(route(rn('api.client_groups.preview')), {
            client_names: [...selectedNames.value],
        });
        previewResult.value = response.data;
    } catch (e) {
        errorMessage.value = 'プレビューの取得に失敗しました。';
    } finally {
        previewing.value = false;
    }
};

const confirmCreate = async () => {
    if (!newGroupName.value.trim() || selectedNames.value.size === 0) return;
    saving.value = true;
    errorMessage.value = '';
    try {
        await axios.post(route(rn('api.client_groups.store')), {
            name: newGroupName.value.trim(),
            client_names: [...selectedNames.value],
        });
        statusMessage.value = 'グループを作成しました。';
        clearSelection();
        await fetchData();
    } catch (e) {
        errorMessage.value = e.response?.data?.message ?? 'グループの作成に失敗しました。';
    } finally {
        saving.value = false;
    }
};

const addToExistingGroup = async (groupId, clientName) => {
    errorMessage.value = '';
    try {
        await axios.post(route(rn('api.client_groups.members.store'), { group: groupId }), { client_name: clientName });
        statusMessage.value = `「${clientName}」を追加しました。`;
        await fetchData();
    } catch (e) {
        errorMessage.value = e.response?.data?.message ?? '追加に失敗しました。';
    }
};

const removeMember = async (groupId, member) => {
    if (!window.confirm(`「${member.client_name}」をグループから外しますか？`)) return;
    errorMessage.value = '';
    try {
        await axios.delete(route(rn('api.client_groups.members.destroy'), { group: groupId, member: member.id }));
        statusMessage.value = 'グループから外しました。';
        await fetchData();
    } catch (e) {
        errorMessage.value = '操作に失敗しました。';
    }
};

const startEditGroup = (group) => {
    editingGroupId.value = group.id;
    editingGroupName.value = group.name;
};

const saveGroupName = async (groupId) => {
    if (!editingGroupName.value.trim()) return;
    errorMessage.value = '';
    try {
        await axios.patch(route(rn('api.client_groups.update'), { group: groupId }), { name: editingGroupName.value.trim() });
        editingGroupId.value = null;
        statusMessage.value = 'グループ名を変更しました。';
        await fetchData();
    } catch (e) {
        errorMessage.value = '変更に失敗しました。';
    }
};

const deleteGroup = async (group) => {
    if (!window.confirm(`グループ「${group.name}」を削除しますか？（メンバーは未所属に戻ります）`)) return;
    errorMessage.value = '';
    try {
        await axios.delete(route(rn('api.client_groups.destroy'), { group: group.id }));
        statusMessage.value = 'グループを削除しました。';
        await fetchData();
    } catch (e) {
        errorMessage.value = '削除に失敗しました。';
    }
};

onMounted(fetchData);
</script>

<template>
    <AppLayout title="得意先統合設定">
        <template #header>
            <div class="flex items-center gap-3">
                <Link
                    :href="route(rn('dashboard'))"
                    class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300 whitespace-nowrap"
                >← データ登録状況</Link>
                <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">得意先統合設定</h2>
            </div>
        </template>
        <template #tabs>
            <SalesAnalysisNavigationTabs :route-prefix="routePrefix" active="client_groups" />
        </template>

        <div class="space-y-6">
            <p v-if="errorMessage" class="rounded bg-red-50 p-3 text-sm text-red-700">{{ errorMessage }}</p>
            <p v-if="statusMessage" class="rounded bg-green-50 p-3 text-sm text-green-700">{{ statusMessage }}</p>
            <p v-if="loading" class="text-sm text-gray-500">読み込み中...</p>

            <!-- 正規化候補 -->
            <div class="rounded bg-white p-4 shadow">
                <h3 class="mb-3 text-sm font-semibold text-gray-900">正規化候補（表記ゆれの可能性がある未統合の名称）</h3>
                <p v-if="candidates.length === 0" class="text-xs text-gray-500">候補はありません。</p>
                <ul v-else class="space-y-2">
                    <li v-for="(c, idx) in candidates" :key="idx" class="rounded border border-gray-200 p-3 text-sm">
                        <span class="text-gray-700">{{ c.client_names.join(' / ') }}</span>
                        <button
                            type="button"
                            class="ml-3 rounded-md border border-indigo-300 bg-indigo-50 px-2 py-1 text-xs font-semibold text-indigo-700 hover:bg-indigo-100"
                            @click="startFromCandidate(c)"
                        >この名称でグループを作成</button>
                    </li>
                </ul>
            </div>

            <!-- 新規グループ作成フォーム（候補選択 or 下の一覧からのチェック選択） -->
            <div v-if="selectedNames.size > 0" class="rounded border-2 border-indigo-300 bg-indigo-50 p-4 shadow">
                <h3 class="mb-2 text-sm font-semibold text-gray-900">新規グループを作成</h3>
                <p class="mb-2 text-xs text-gray-600">選択中: {{ [...selectedNames].join(' / ') }}</p>
                <div class="flex flex-wrap items-center gap-2">
                    <input v-model="newGroupName" type="text" placeholder="グループ名（統合後の表示名）" class="w-64 rounded-md border-gray-300 text-sm shadow-sm" />
                    <button type="button" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50" :disabled="previewing" @click="runPreview">
                        {{ previewing ? 'プレビュー取得中...' : '統合プレビュー' }}
                    </button>
                    <button type="button" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-bold text-white hover:bg-indigo-700" :disabled="saving || !newGroupName.trim()" @click="confirmCreate">
                        {{ saving ? '保存中...' : '保存する' }}
                    </button>
                    <button type="button" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50" @click="clearSelection">キャンセル</button>
                </div>
                <div v-if="previewResult" class="mt-3 rounded bg-white p-3 text-xs">
                    <p>統合後の合計: <span class="font-bold">{{ yen(previewResult.total_amount) }}</span>（{{ previewResult.order_count }}件）</p>
                    <p class="text-gray-500">影響する部署: {{ previewResult.departments.map((d) => departmentLabels[d] ?? d).join('、') || 'なし' }}</p>
                    <ul class="mt-1 text-gray-500">
                        <li v-for="p in previewResult.per_name" :key="p.client_name">{{ p.client_name }}: {{ yen(p.amount) }}（{{ p.order_count }}件）</li>
                    </ul>
                </div>
            </div>

            <!-- 既存グループ -->
            <div class="rounded bg-white p-4 shadow">
                <h3 class="mb-3 text-sm font-semibold text-gray-900">既存グループ</h3>
                <p v-if="groups.length === 0" class="text-xs text-gray-500">グループはまだありません。</p>
                <div v-else class="space-y-3">
                    <div v-for="g in groups" :key="g.id" class="rounded border border-gray-200 p-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div v-if="editingGroupId === g.id" class="flex items-center gap-2">
                                <input v-model="editingGroupName" type="text" class="rounded-md border-gray-300 text-sm shadow-sm" />
                                <button type="button" class="rounded-md bg-indigo-600 px-2 py-1 text-xs font-bold text-white hover:bg-indigo-700" @click="saveGroupName(g.id)">保存</button>
                                <button type="button" class="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs text-gray-600 hover:bg-gray-50" @click="editingGroupId = null">取消</button>
                            </div>
                            <span v-else class="text-sm font-bold text-gray-900">{{ g.name }}（{{ g.members.length }}名称統合）</span>
                            <div v-if="editingGroupId !== g.id" class="flex gap-2">
                                <button type="button" class="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs text-gray-600 hover:bg-gray-50" @click="startEditGroup(g)">編集</button>
                                <button type="button" class="rounded-md border border-red-300 bg-white px-2 py-1 text-xs text-red-600 hover:bg-red-50" @click="deleteGroup(g)">削除</button>
                            </div>
                        </div>
                        <ul class="mt-2 space-y-1 text-xs text-gray-600">
                            <li v-for="m in g.members" :key="m.id" class="flex items-center justify-between">
                                <span>{{ m.client_name }}</span>
                                <button type="button" class="text-red-500 hover:underline" @click="removeMember(g.id, m)">外す</button>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- 未所属の得意先一覧 -->
            <div class="rounded bg-white p-4 shadow">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-900">未所属の得意先一覧</h3>
                    <input v-model="unassignedSearch" type="text" placeholder="得意先名で検索" class="w-48 rounded-md border-gray-300 text-sm shadow-sm" />
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs">
                        <thead>
                            <tr class="text-left text-gray-500">
                                <th class="py-1"></th>
                                <th class="py-1">得意先名</th>
                                <th class="py-1 text-right">直近取引額</th>
                                <th class="py-1 text-right">件数</th>
                                <th class="py-1">既存グループへ追加</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="c in filteredUnassigned()" :key="c.client_name" class="border-t">
                                <td class="py-1">
                                    <input type="checkbox" :checked="selectedNames.has(c.client_name)" @change="toggleSelect(c.client_name)" />
                                </td>
                                <td class="py-1">{{ c.client_name }}</td>
                                <td class="py-1 text-right">{{ yen(c.latest_amount) }}</td>
                                <td class="py-1 text-right">{{ c.order_count }}件</td>
                                <td class="py-1">
                                    <select
                                        v-if="groups.length > 0"
                                        class="rounded-md border-gray-300 text-xs shadow-sm"
                                        @change="(e) => { if (e.target.value) { addToExistingGroup(Number(e.target.value), c.client_name); e.target.value = ''; } }"
                                    >
                                        <option value="">選択...</option>
                                        <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
                                    </select>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
