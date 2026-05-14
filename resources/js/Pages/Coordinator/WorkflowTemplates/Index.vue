<template>
    <AppLayout title="工程シートテンプレート">
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">工程シートテンプレート管理</h2>
        </template>

        <div class="rounded bg-white p-6 shadow">
            <div class="mb-4 flex items-center justify-between">
                <p class="text-sm text-gray-500">ステージ構成を保存して、新規シート作成時に再利用できます。</p>
                <button
                    type="button"
                    class="rounded bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700"
                    @click="openCreateModal"
                >+ 新規作成</button>
            </div>

            <div v-if="localTemplates.length === 0" class="text-sm text-gray-400">テンプレートはまだありません。</div>

            <div v-else class="space-y-3">
                <div
                    v-for="tpl in localTemplates"
                    :key="tpl.id"
                    class="rounded border border-gray-200 bg-gray-50 p-4"
                >
                    <div class="mb-2 flex items-center gap-3">
                        <h3 class="font-semibold text-gray-800">{{ tpl.name }}</h3>
                        <span class="text-xs text-gray-400">by {{ tpl.creator_name }}　{{ tpl.updated_at }}</span>
                        <div class="ml-auto flex gap-2">
                            <button
                                type="button"
                                class="rounded border border-indigo-300 px-3 py-1 text-xs text-indigo-600 hover:bg-indigo-50"
                                @click="openEditModal(tpl)"
                            >編集</button>
                            <button
                                type="button"
                                class="rounded border border-red-300 px-3 py-1 text-xs text-red-500 hover:bg-red-50"
                                @click="deleteTemplate(tpl)"
                            >削除</button>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <span
                            v-for="stage in (tpl.stage_config?.stages ?? [])"
                            :key="stage.key"
                            class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                            :class="stage.type === 'coordinator' ? 'bg-green-100 text-green-700' : 'bg-indigo-100 text-indigo-700'"
                        >{{ stage.label }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── 作成/編集モーダル ──────────────────────────────────── -->
        <div
            v-if="showModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
            @click.self="showModal = false"
        >
            <div class="w-full max-w-lg rounded-lg bg-white p-6 shadow-xl">
                <h3 class="mb-4 text-lg font-semibold text-gray-800">
                    {{ editingTemplate ? 'テンプレートを編集' : 'テンプレートを新規作成' }}
                </h3>
                <div class="mb-4">
                    <label class="mb-1 block text-sm font-medium text-gray-700">テンプレート名 <span class="text-red-500">*</span></label>
                    <input
                        v-model="form.name"
                        type="text"
                        class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none"
                        placeholder="例：標準4工程"
                    />
                </div>
                <div class="mb-4">
                    <label class="mb-2 block text-sm font-medium text-gray-700">ステージ構成</label>
                    <div class="space-y-2">
                        <div
                            v-for="(stage, idx) in form.stages"
                            :key="idx"
                            class="flex items-center gap-2 rounded border border-gray-200 bg-gray-50 px-3 py-2"
                        >
                            <span class="w-5 text-xs text-gray-400">{{ idx + 1 }}</span>
                            <input
                                v-model="stage.label"
                                type="text"
                                class="flex-1 rounded border border-gray-300 px-2 py-1 text-sm focus:outline-none"
                                placeholder="ステージ名"
                            />
                            <select v-model="stage.type" class="rounded border border-gray-300 px-2 py-1 text-sm">
                                <option value="worker">worker</option>
                                <option value="coordinator">coordinator</option>
                            </select>
                            <button type="button" class="text-xs text-red-400 hover:text-red-600" @click="form.stages.splice(idx, 1)">✕</button>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="mt-2 rounded border border-dashed border-gray-300 px-3 py-1.5 text-sm text-gray-500 hover:bg-gray-50"
                        @click="form.stages.push({ key: `s${Date.now()}`, label: '', type: 'worker' })"
                    >+ ステージを追加</button>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" class="rounded border border-gray-300 px-4 py-1.5 text-sm text-gray-600" @click="showModal = false">キャンセル</button>
                    <button
                        type="button"
                        class="rounded bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                        :disabled="!form.name.trim() || form.stages.length === 0"
                        @click="submitForm"
                    >{{ editingTemplate ? '更新' : '作成' }}</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import axios from 'axios';

const props = defineProps({
    templates: { type: Array, default: () => [] },
});

const localTemplates  = ref(props.templates.map((t) => ({ ...t })));
const showModal       = ref(false);
const editingTemplate = ref(null);

const form = ref({ name: '', stages: [] });

function openCreateModal() {
    editingTemplate.value = null;
    form.value = {
        name: '',
        stages: [
            { key: 'shinko',  label: '進行',  type: 'coordinator' },
            { key: 'kumihan', label: '組版',  type: 'worker' },
            { key: 'kosei',   label: '校正',  type: 'worker' },
            { key: 'kosei2',  label: '校正２',type: 'worker' },
        ],
    };
    showModal.value = true;
}

function openEditModal(tpl) {
    editingTemplate.value = tpl;
    form.value = {
        name:   tpl.name,
        stages: (tpl.stage_config?.stages ?? []).map((s) => ({ ...s })),
    };
    showModal.value = true;
}

async function submitForm() {
    const payload = {
        name:         form.value.name,
        stage_config: {
            stages: form.value.stages.map((s, i) => ({
                key:   s.key || `stage${i}`,
                label: s.label,
                type:  s.type,
            })),
        },
    };
    try {
        if (editingTemplate.value) {
            await axios.put(
                route('coordinator.workflow_templates.update', { template: editingTemplate.value.id }),
                payload
            );
            const tpl = localTemplates.value.find((t) => t.id === editingTemplate.value.id);
            if (tpl) { tpl.name = payload.name; tpl.stage_config = payload.stage_config; }
        } else {
            const res = await axios.post(route('coordinator.workflow_templates.store'), payload);
            localTemplates.value.unshift(res.data.template);
        }
        showModal.value = false;
    } catch (e) {
        alert('保存に失敗しました');
    }
}

async function deleteTemplate(tpl) {
    if (!confirm(`「${tpl.name}」を削除しますか？`)) return;
    try {
        await axios.delete(route('coordinator.workflow_templates.destroy', { template: tpl.id }));
        localTemplates.value = localTemplates.value.filter((t) => t.id !== tpl.id);
    } catch (e) {
        alert('削除に失敗しました');
    }
}
</script>
