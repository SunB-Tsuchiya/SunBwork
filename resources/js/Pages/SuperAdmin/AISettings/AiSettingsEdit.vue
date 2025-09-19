<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { onMounted, reactive, ref, watch } from 'vue';

const props = defineProps({
    setting: Object,
    model_options_list: { type: Array, default: () => [] },
    hard_max: { type: Number, default: 2000 },
    model_presets: { type: Array, default: () => [] },
    instructions_presets: { type: Array, default: () => [] },
    system_prompts: { type: Array, default: () => [] },
});
const page = usePage();
const user = page.props.user;

const form = reactive({
    model: props.setting?.model || '',
    max_tokens: props.setting?.max_tokens || Math.min(2048, props.hard_max),
    default_instructions: props.setting?.default_instructions || '',
    system_prompt: props.setting?.system_prompt || '',
});
const modelOptionsText = ref(JSON.stringify(props.setting?.model_options || {}, null, 2));
const modelOptionsErrors = ref([]);
const presets = props.model_presets || [];
const selectedPreset = ref(null);
// instructions/system prompts presets
const instructionsPresets = props.instructions_presets || [];
const systemPrompts = props.system_prompts || [];
const selectedInstructionsPreset = ref(null);
const selectedSystemPrompt = ref(null);
const toast = ref('');
const showToast = (msg) => {
    toast.value = msg;
    setTimeout(() => {
        toast.value = '';
    }, 2500);
};

onMounted(() => {
    // debug: print props and presets so you can inspect in browser console
    // Debug logs removed for AiSettingsEdit
});

// When user selects a model in the dropdown, auto-apply matching preset (if any)
watch(
    () => form.model,
    (newModel) => {
        if (!newModel) {
            selectedPreset.value = null;
            return;
        }
        const matched = (presets || []).find((p) => p.model === newModel);
        if (matched) {
            selectedPreset.value = matched;
            form.max_tokens = Math.min(matched.max_tokens || form.max_tokens, props.hard_max);
            modelOptionsText.value = JSON.stringify(matched.model_options || {}, null, 2);
            showToast(`プリセット ${matched.name} を適用しました`);
        } else {
            // Clear any highlighted preset when model has no preset mapping
            selectedPreset.value = null;
        }
    },
);

function previewPreset(p) {
    selectedPreset.value = p;
}

// load available preset icons via Vite so we get correct dev/prod URLs
const presetIcons = import.meta.glob('../../Assets/ai-presets/*.svg', { eager: true, query: '?url', import: 'default' });

const presetIconUrl = (p) => {
    if (!p || !p.icon) return '';
    const key = `../../Assets/ai-presets/${p.icon}`;
    return presetIcons[key] || '';
};

const isSelected = (p) => selectedPreset.value && selectedPreset.value.name === p.name;

function applySelectedPreset() {
    const p = selectedPreset.value;
    if (!p) return;
    form.model = p.model || form.model;
    form.max_tokens = Math.min(p.max_tokens || form.max_tokens, props.hard_max);
    modelOptionsText.value = JSON.stringify(p.model_options || {}, null, 2);
}

function applyInstructionsPreset() {
    const p = selectedInstructionsPreset.value;
    if (!p) return;
    form.default_instructions = p.instructions || form.default_instructions;
    showToast('Default instructions を適用しました');
}

function applySystemPromptPreset() {
    const p = selectedSystemPrompt.value;
    if (!p) return;
    form.system_prompt = p.prompt || form.system_prompt;
    showToast('System prompt を適用しました');
}

function validateModelOptionsObject(obj) {
    const errors = [];
    const allowedKeys = new Set(['temperature', 'top_p', 'frequency_penalty', 'presence_penalty', 'stop']);
    // temperature
    if (Object.prototype.hasOwnProperty.call(obj, 'temperature')) {
        const v = obj.temperature;
        if (typeof v !== 'number' || v < 0 || v > 1) errors.push('temperature は数値 (0〜1) である必要があります');
    }
    if (Object.prototype.hasOwnProperty.call(obj, 'top_p')) {
        const v = obj.top_p;
        if (typeof v !== 'number' || v < 0 || v > 1) errors.push('top_p は数値 (0〜1) である必要があります');
    }
    if (Object.prototype.hasOwnProperty.call(obj, 'frequency_penalty')) {
        const v = obj.frequency_penalty;
        if (typeof v !== 'number' || v < -2 || v > 2) errors.push('frequency_penalty は数値 (-2〜2) である必要があります');
    }
    if (Object.prototype.hasOwnProperty.call(obj, 'presence_penalty')) {
        const v = obj.presence_penalty;
        if (typeof v !== 'number' || v < -2 || v > 2) errors.push('presence_penalty は数値 (-2〜2) である必要があります');
    }
    if (Object.prototype.hasOwnProperty.call(obj, 'stop')) {
        const v = obj.stop;
        if (!Array.isArray(v) || !v.every((s) => typeof s === 'string')) errors.push('stop は文字列配列である必要があります');
    }
    // unknown keys warning (not fatal)
    const unknown = Object.keys(obj || {}).filter((k) => !allowedKeys.has(k));
    return { errors, unknown };
}

const submit = async () => {
    const payload = { ...form };
    let parsed = {};
    try {
        // Payload before submit debug suppressed
        parsed = JSON.parse(modelOptionsText.value || '{}');
    } catch (e) {
        alert('model_options は有効なJSONである必要があります');
        return;
    }

    const { errors, unknown } = validateModelOptionsObject(parsed);
    modelOptionsErrors.value = errors;
    if (errors.length) {
        alert('model_options の検証エラー: \n' + errors.join('\n'));
        return;
    }
    if (unknown.length) {
        const ok = confirm('model_options に未知のキーが含まれています: ' + unknown.join(', ') + '\nこのまま保存してもよいですか？');
        if (!ok) return;
    }

    payload.model_options = parsed;
    try {
        const settingId = Number(props.setting?.id || 0);
        // Submitting AI setting debug suppressed
        if (settingId && Number.isFinite(settingId) && settingId > 0) {
            const url = route('superadmin.ai.update', settingId);
            // PUT to URL debug suppressed
            try {
                await axios.put(url, payload);
            } catch (err) {
                // fallback for environments that reject PUT to the route: try POST with _method override
                if (err.response && err.response.status === 405) {
                    console.warn('PUT rejected with 405, retrying with POST + _method=PUT');
                    const body = Object.assign({}, payload, { _method: 'PUT' });
                    await axios.post(url, body);
                } else {
                    throw err;
                }
            }
        } else {
            const url = route('superadmin.ai.store');
            // POST to URL debug suppressed
            await axios.post(url, payload);
        }
        window.location.href = route('superadmin.ai.index');
    } catch (err) {
        console.error('保存に失敗しました', err);
        alert('保存に失敗しました');
    }
};

function onModelChange(e) {
    const newModel = e.target.value;
    if (!newModel) {
        selectedPreset.value = null;
        return;
    }
    const matched = (presets || []).find((p) => p.model === newModel);
    if (matched) {
        selectedPreset.value = matched;
        form.max_tokens = Math.min(matched.max_tokens || form.max_tokens, props.hard_max);
        modelOptionsText.value = JSON.stringify(matched.model_options || {}, null, 2);
        showToast(`プリセット ${matched.name} を適用しました`);
    } else {
        selectedPreset.value = null;
    }
}
</script>

<template>
    <AppLayout title="AI設定編集" :user="user">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">AI設定編集</h2>
        </template>

        <main>
            <div class="py-12">
                <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div class="bg-white p-6 shadow-xl sm:rounded-lg">
                        <form @submit.prevent="submit" class="space-y-4">
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Model Preset</label>
                                    <div class="mt-1">
                                        <div class="flex flex-wrap gap-2">
                                            <div v-for="p in presets" :key="p.name" class="relative">
                                                <button
                                                    type="button"
                                                    @click="previewPreset(p)"
                                                    :class="[
                                                        'flex w-full items-center gap-2 rounded border px-3 py-2 text-left text-sm',
                                                        isSelected(p) ? 'border-blue-400 bg-blue-50' : 'bg-white hover:bg-gray-50',
                                                    ]"
                                                >
                                                    <img v-if="p.icon" :src="presetIconUrl(p)" alt="icon" class="h-6 w-6" />
                                                    <span class="font-medium">{{ p.name }}</span>
                                                    <span class="text-xs text-gray-500"> - {{ p.description.split('。')[0] }}</span>
                                                </button>
                                                <div
                                                    v-if="isSelected(p)"
                                                    class="absolute right-1 top-1 flex h-5 w-5 items-center justify-center rounded-full bg-blue-600 text-xs text-white"
                                                >
                                                    ✓
                                                </div>
                                            </div>
                                        </div>
                                        <div v-if="!presets || !presets.length" class="mt-2 text-sm text-gray-500">
                                            プリセットが登録されていません（config/ai_presets.php を確認してください）
                                        </div>
                                        <div v-if="selectedPreset" class="mt-2 rounded border bg-gray-50 p-2 text-sm">
                                            <div class="flex items-center gap-2">
                                                <div class="text-xl">{{ selectedPreset.icon }}</div>
                                                <div class="font-semibold">プレビュー: {{ selectedPreset.name }}</div>
                                            </div>
                                            <div class="text-xs text-gray-600">{{ selectedPreset.description }}</div>
                                            <div class="mt-2 text-xs text-gray-700">
                                                Model: {{ selectedPreset.model }} · Max tokens: {{ Math.min(selectedPreset.max_tokens, hard_max) }}
                                            </div>
                                            <div class="mt-2">
                                                <button
                                                    type="button"
                                                    @click="applySelectedPreset"
                                                    class="rounded bg-green-600 px-2 py-1 text-sm text-white"
                                                >
                                                    適用
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Model</label>
                                    <select v-model="form.model" @change="onModelChange" class="input mt-1 w-full">
                                        <option value="">-- 選択してください --</option>
                                        <option v-for="m in model_options_list" :key="m" :value="m">{{ m }}</option>
                                    </select>
                                    <p v-if="!model_options_list.length" class="mt-1 text-xs text-gray-500">
                                        利用可能モデル一覧が取得できませんでした。手動で入力してください。
                                    </p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Max tokens</label>
                                    <input v-model.number="form.max_tokens" type="number" class="input mt-1 w-full" />
                                    <p class="mt-1 text-xs text-gray-500">システムハード上限: {{ hard_max }} トークン</p>
                                    <p v-if="form.max_tokens > hard_max" class="mt-1 text-xs text-red-600">
                                        警告: 入力値はシステム上限を超えています。保存時に上限へ切り詰められます。
                                    </p>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Default instructions</label>
                                <div class="mt-1 flex items-center gap-2">
                                    <select
                                        v-model="selectedInstructionsPreset"
                                        @blur="applyInstructionsPreset"
                                        @change="applyInstructionsPreset"
                                        class="input"
                                    >
                                        <option :value="null">-- プリセットを選択 --</option>
                                        <option v-for="ip in instructionsPresets" :key="ip.name" :value="ip">{{ ip.name }}</option>
                                    </select>
                                    <button type="button" @click="applyInstructionsPreset" class="rounded bg-green-600 px-2 py-1 text-sm text-white">
                                        適用
                                    </button>
                                </div>
                                <textarea v-model="form.default_instructions" rows="4" class="textarea mt-2 w-full"></textarea>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">System prompt</label>
                                <div class="mt-1 flex items-center gap-2">
                                    <select
                                        v-model="selectedSystemPrompt"
                                        @blur="applySystemPromptPreset"
                                        @change="applySystemPromptPreset"
                                        class="input"
                                    >
                                        <option :value="null">-- プリセットを選択 --</option>
                                        <option v-for="sp in systemPrompts" :key="sp.name" :value="sp">{{ sp.name }}</option>
                                    </select>
                                    <button type="button" @click="applySystemPromptPreset" class="rounded bg-green-600 px-2 py-1 text-sm text-white">
                                        適用
                                    </button>
                                </div>
                                <textarea v-model="form.system_prompt" rows="4" class="textarea mt-2 w-full"></textarea>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Model options (JSON)</label>
                                <textarea v-model="modelOptionsText" rows="4" class="textarea mt-1 w-full"></textarea>
                            </div>

                            <div class="flex justify-end">
                                <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-white">保存</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
        <div v-if="toast" class="fixed bottom-6 right-6 rounded bg-black px-4 py-2 text-white shadow">{{ toast }}</div>
    </AppLayout>
</template>
