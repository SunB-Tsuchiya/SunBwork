<script setup>
import { ref, computed } from 'vue';

// 同行者を選ぶ窓（お知らせの「ユーザーを選択する」と同じ見た目・操作）
const props = defineProps({
    users:    { type: Array, default: () => [] },     // 選べる人: { id, name, department, position_title, employment_type }
    selected: { type: Array, default: () => [] },     // 選択済みの user_id
    max:      { type: Number, default: 5 },
    title:    { type: String, default: '同行者を選択' },
});
const emit = defineEmits(['close', 'apply']);

const ids = ref([...props.selected]);
const filter = ref('all');
const keyword = ref('');
const sortAsc = ref(true);
const message = ref('');

const employmentLabel = (type) => ({ regular: '正社員', contract: '契約社員', dispatch: '派遣', outsource: '業務委託' }[type] ?? type);

const visible = computed(() => {
    let base = props.users;
    if (filter.value === 'employees') base = base.filter((u) => ['regular', 'contract'].includes(u.employment_type));
    else if (filter.value === 'dispatch') base = base.filter((u) => ['dispatch', 'outsource'].includes(u.employment_type));
    const k = keyword.value.trim();
    if (k) base = base.filter((u) => u.name.includes(k) || (u.department ?? '').includes(k));
    return [...base].sort((a, b) => (sortAsc.value ? 1 : -1) * a.name.localeCompare(b.name, 'ja'));
});

function toggle(id) {
    message.value = '';
    const i = ids.value.indexOf(id);
    if (i >= 0) { ids.value.splice(i, 1); return; }
    if (ids.value.length >= props.max) { message.value = `同行者は ${props.max} 人までです。`; return; }
    ids.value.push(id);
}
const selectedNames = computed(() => ids.value.map((id) => props.users.find((u) => u.id === id)?.name).filter(Boolean));
</script>

<template>
    <Teleport to="body">
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="emit('close')">
            <div class="flex max-h-[85vh] w-full max-w-2xl flex-col overflow-hidden rounded-lg bg-white shadow-xl">
                <div class="flex items-center justify-between border-b px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-800">{{ title }}</h3>
                    <button type="button" class="text-gray-400 hover:text-gray-600" @click="emit('close')">✕</button>
                </div>
                <div class="flex flex-wrap items-center gap-3 border-b bg-gray-50 px-6 py-3">
                    <span class="text-sm text-gray-600">絞り込み:</span>
                    <label class="flex cursor-pointer items-center gap-1 text-sm"><input type="radio" v-model="filter" value="all" /> 全員</label>
                    <label class="flex cursor-pointer items-center gap-1 text-sm"><input type="radio" v-model="filter" value="employees" /> 正社員・契約社員</label>
                    <label class="flex cursor-pointer items-center gap-1 text-sm"><input type="radio" v-model="filter" value="dispatch" /> 派遣・業務委託</label>
                    <input type="text" v-model="keyword" placeholder="名前・部署で検索"
                        class="ml-auto w-44 rounded border border-gray-300 px-2 py-1 text-sm focus:border-blue-400 focus:outline-none" />
                </div>
                <div class="flex-1 overflow-y-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="sticky top-0 bg-gray-50">
                            <tr>
                                <th class="w-10 px-4 py-2"></th>
                                <th class="cursor-pointer select-none px-4 py-2 text-left text-xs font-medium text-gray-500 hover:text-gray-700" @click="sortAsc = !sortAsc">
                                    名前 {{ sortAsc ? '▲' : '▼' }}
                                </th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">部署</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">雇用形態</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="u in visible" :key="u.id" class="cursor-pointer hover:bg-blue-50"
                                :class="ids.includes(u.id) ? 'bg-blue-50' : ''" @click="toggle(u.id)">
                                <td class="px-4 py-2 text-center">
                                    <input type="checkbox" :checked="ids.includes(u.id)" @click.stop="toggle(u.id)" class="rounded text-blue-600" />
                                </td>
                                <td class="px-4 py-2 text-sm font-medium text-gray-800">{{ u.name }}</td>
                                <td class="px-4 py-2 text-sm text-gray-600">{{ u.department }}</td>
                                <td class="px-4 py-2 text-sm text-gray-600">{{ employmentLabel(u.employment_type) }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="!visible.length" class="py-6 text-center text-sm text-gray-500">該当する人がいません</p>
                </div>
                <div class="flex items-center justify-between gap-3 border-t px-6 py-4">
                    <div class="min-w-0 text-sm text-gray-600">
                        <span>{{ ids.length }}人選択中</span>
                        <span v-if="selectedNames.length" class="ml-2 text-xs text-gray-500">（{{ selectedNames.join('、') }}）</span>
                        <p v-if="message" class="text-xs text-red-600">{{ message }}</p>
                    </div>
                    <div class="flex shrink-0 gap-2">
                        <button type="button" class="rounded border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" @click="ids = []">解除</button>
                        <button type="button" class="rounded bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700" @click="emit('apply', [...ids])">確定</button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
