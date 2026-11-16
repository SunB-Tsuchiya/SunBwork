<script setup>
import { computed, onMounted, onBeforeUnmount, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/layouts/AppLayout.vue';
import AdminNavigationTabs from '@/Components/Tabs/AdminNavigationTabs.vue';

const props = defineProps({ users: Array, version: String, noCompanySelected: Boolean });
const local = ref((props.users || []).map(user => ({ ...user })));
const saved = ref(JSON.stringify(local.value));
const version = ref(props.version);
const query = ref('');
const employment = ref('all');
const saving = ref(false);
const error = ref('');
const message = ref('');
const labels = { regular: '正社員', contract: '契約社員', dispatch: '派遣', outsource: '業務委託' };
const dirty = computed(() => JSON.stringify(local.value) !== saved.value);
const visible = computed(() => local.value.filter(user => (!query.value.trim() || user.name?.includes(query.value.trim()))
    && (employment.value === 'all' || employment.value === user.employment_type)));
async function save() {
    if (saving.value || !dirty.value) return;
    saving.value = true; error.value = ''; message.value = '';
    try {
        const { data } = await axios.post(route('admin.department_work_display.update'), {
            version: version.value,
            items: local.value.map(user => ({ user_id: user.id, show_schedule: user.show_schedule, show_records: user.show_records })),
        }, { headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '', Accept: 'application/json' } });
        local.value = data.users.map(user => ({ ...user }));
        version.value = data.version;
        saved.value = JSON.stringify(local.value);
        message.value = '保存しました。部署の作業ページを再読み込みすると反映されます。';
    } catch (e) {
        error.value = Object.values(e.response?.data?.errors || {}).flat()[0]
            || e.response?.data?.message || '保存できませんでした。変更内容は画面に残っています。';
    } finally { saving.value = false; }
}
function beforeUnload(event) { if (dirty.value) { event.preventDefault(); event.returnValue = ''; } }
let removeBefore;
onMounted(() => {
    window.addEventListener('beforeunload', beforeUnload);
    removeBefore = router.on('before', event => {
        if (dirty.value && !confirm('表示対象の変更が未保存です。保存せずに移動しますか？')) event.preventDefault();
    });
});
onBeforeUnmount(() => { window.removeEventListener('beforeunload', beforeUnload); removeBefore?.(); });
</script>

<template>
    <AppLayout title="部署の作業の表示対象">
        <template #header><h2 class="text-base font-semibold text-gray-800 sm:text-xl">部署の作業の表示対象</h2></template>
        <template #tabs><AdminNavigationTabs active="department_work_display" /></template>
        <div class="rounded bg-white p-4 shadow sm:p-6">
            <p v-if="noCompanySelected" class="rounded border border-amber-200 bg-amber-50 p-4 text-sm">会社をサン・ブレーンに切り替えてください。</p>
            <template v-else>
                <p class="text-sm text-gray-700">サン・ブレーン情報出版の一覧に表示する人を選びます。「作業予定」と「作業記録」は別々に設定できます。</p>
                <p class="mt-2 text-xs text-gray-500">非表示にしても、本人のマイジョブ入力・日報・ページ閲覧と、管理側の分析は変わりません。本人用の「自分の記録を確認」も利用できます。</p>
                <div class="my-4 flex flex-wrap items-center gap-3">
                    <input v-model="query" aria-label="名前で絞り込み" placeholder="名前で絞り込み" class="rounded border-gray-300 text-sm" />
                    <select v-model="employment" aria-label="雇用区分で絞り込み" class="rounded border-gray-300 text-sm">
                        <option value="all">すべての雇用区分</option>
                        <option v-for="(label, key) in labels" :key="key" :value="key">{{ label }}</option>
                    </select>
                    <span class="text-sm text-gray-500">{{ visible.length }}人 / {{ local.length }}人</span>
                </div>
                <p v-if="error" role="alert" class="mb-3 rounded border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ error }}</p>
                <p v-if="message && !dirty" role="status" class="mb-3 rounded bg-green-50 p-3 text-sm text-green-800">{{ message }}</p>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="border-y bg-gray-50 text-gray-600"><tr><th class="px-3 py-2">氏名</th><th class="px-3 py-2">雇用区分</th><th class="px-3 py-2">作業予定に表示</th><th class="px-3 py-2">作業記録に表示</th></tr></thead>
                        <tbody class="divide-y">
                            <tr v-for="user in visible" :key="user.id">
                                <td class="px-3 py-3 font-medium">{{ user.name }}</td>
                                <td class="px-3 py-3 text-xs text-gray-500">{{ labels[user.employment_type] || user.employment_type }}</td>
                                <td v-for="field in ['show_schedule', 'show_records']" :key="field" class="px-3 py-3">
                                    <label class="inline-flex cursor-pointer items-center gap-2">
                                        <input v-model="user[field]" :disabled="saving" type="checkbox" role="switch" :aria-label="`${user.name}を${field === 'show_schedule' ? '作業予定' : '作業記録'}に表示`" class="rounded border-gray-300 text-red-600 focus:ring-red-500" />
                                        <span :class="user[field] ? 'text-red-700' : 'text-gray-500'">{{ user[field] ? '表示' : '非表示' }}</span>
                                    </label>
                                </td>
                            </tr>
                            <tr v-if="!visible.length"><td colspan="4" class="p-6 text-center text-gray-500">該当する対象者はいません。</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="mt-4 flex items-center justify-end gap-3">
                    <span v-if="dirty" class="text-sm text-amber-700">未保存の変更があります</span>
                    <button type="button" :disabled="saving || !dirty" class="rounded bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-40" @click="save">{{ saving ? '保存中…' : 'まとめて保存' }}</button>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
