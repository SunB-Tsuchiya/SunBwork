<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import axios from 'axios';
import { usePage } from '@inertiajs/vue3';
import { ref, reactive, computed, onMounted } from 'vue';

const props = defineProps({ setting: Object, model_options_list: { type: Array, default: () => [] }, hard_max: { type: Number, default: 2000 }, model_presets: { type: Array, default: () => [] }, instructions_presets: { type: Array, default: () => [] }, system_prompts: { type: Array, default: () => [] } });
const page = usePage();
const user = page.props.user;

const form = reactive({
  model: props.setting?.model || '',
  max_tokens: props.setting?.max_tokens || Math.min(2048, props.hard_max),
  default_instructions: props.setting?.default_instructions || '',
  system_prompt: props.setting?.system_prompt || ''
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
  setTimeout(() => { toast.value = ''; }, 2500);
};

onMounted(() => {
  // debug: print props and presets so you can inspect in browser console
  console.debug('AiSettingsEdit props:', props);
  console.debug('model_presets (from server):', presets);
  console.debug('instructions_presets:', instructionsPresets);
  console.debug('system_prompts:', systemPrompts);
});

function previewPreset(p) {
  selectedPreset.value = p;
}

// load available preset icons via Vite so we get correct dev/prod URLs
const presetIcons = import.meta.glob('../Assets/ai-presets/*.svg', { eager: true, as: 'url' });

const presetIconUrl = (p) => {
  if (!p || !p.icon) return '';
  const key = `../Assets/ai-presets/${p.icon}`;
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
  const allowedKeys = new Set(['temperature','top_p','frequency_penalty','presence_penalty','stop']);
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
    if (!Array.isArray(v) || !v.every(s => typeof s === 'string')) errors.push('stop は文字列配列である必要があります');
  }
  // unknown keys warning (not fatal)
  const unknown = Object.keys(obj || {}).filter(k => !allowedKeys.has(k));
  return { errors, unknown };
}

const submit = async () => {
  const payload = { ...form };
  let parsed = {};
  try {
    console.debug('Payload before submit:', payload);
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
    console.debug('Submitting AI setting, computed id:', settingId);
    if (settingId && Number.isFinite(settingId) && settingId > 0) {
      const url = route('admin.ai.update', settingId);
      console.debug('PUT to', url);
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
      const url = route('admin.ai.store');
      console.debug('POST to', url);
      await axios.post(url, payload);
    }
    window.location.href = route('admin.ai.index');
  } catch (err) {
    console.error('保存に失敗しました', err);
    alert('保存に失敗しました');
  }
};
</script>

<template>
  <AppLayout title="AI設定編集" :user="user">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">AI設定編集</h2>
    </template>

    <main>
      <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
          <div class="bg-white shadow-xl sm:rounded-lg p-6">
            <form @submit.prevent="submit" class="space-y-4">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700">Model Preset</label>
                  <div class="mt-1">
                    <div class="flex flex-wrap gap-2">
                      <div v-for="p in presets" :key="p.name" class="relative">
                        <button type="button" @click="previewPreset(p)" :class="['flex items-center gap-2 px-3 py-2 border rounded text-sm w-full text-left', isSelected(p) ? 'bg-blue-50 border-blue-400' : 'bg-white hover:bg-gray-50']">
                          <img v-if="p.icon" :src="presetIconUrl(p)" alt="icon" class="w-6 h-6" />
                          <span class="font-medium">{{ p.name }}</span>
                          <span class="text-xs text-gray-500"> - {{ p.description.split('。')[0] }}</span>
                        </button>
                        <div v-if="isSelected(p)" class="absolute top-1 right-1 bg-blue-600 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs">
                          ✓
                        </div>
                      </div>
                    </div>
                    <div v-if="!presets || !presets.length" class="mt-2 text-sm text-gray-500">プリセットが登録されていません（config/ai_presets.php を確認してください）</div>
                    <div v-if="selectedPreset" class="mt-2 p-2 border rounded bg-gray-50 text-sm">
                      <div class="flex items-center gap-2"><div class="text-xl">{{ selectedPreset.icon }}</div><div class="font-semibold">プレビュー: {{ selectedPreset.name }}</div></div>
                      <div class="text-xs text-gray-600">{{ selectedPreset.description }}</div>
                      <div class="text-xs text-gray-700 mt-2">Model: {{ selectedPreset.model }} · Max tokens: {{ Math.min(selectedPreset.max_tokens, hard_max) }}</div>
                      <div class="mt-2">
                        <button type="button" @click="applySelectedPreset" class="bg-green-600 text-white px-2 py-1 rounded text-sm">適用</button>
                      </div>
                    </div>
                  </div>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700">Model</label>
                  <select v-model="form.model" class="mt-1 input w-full">
                    <option value="">-- 選択してください --</option>
                    <option v-for="m in model_options_list" :key="m" :value="m">{{ m }}</option>
                  </select>
                  <p v-if="!model_options_list.length" class="text-xs text-gray-500 mt-1">利用可能モデル一覧が取得できませんでした。手動で入力してください。</p>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700">Max tokens</label>
                  <input v-model.number="form.max_tokens" type="number" class="mt-1 input w-full" />
                  <p class="text-xs text-gray-500 mt-1">システムハード上限: {{ hard_max }} トークン</p>
                  <p v-if="form.max_tokens > hard_max" class="text-xs text-red-600 mt-1">警告: 入力値はシステム上限を超えています。保存時に上限へ切り詰められます。</p>
                </div>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700">Default instructions</label>
                <div class="flex gap-2 items-center mt-1">
                                <select v-model="selectedInstructionsPreset" @blur="applyInstructionsPreset" @change="applyInstructionsPreset" class="input">
                    <option :value="null">-- プリセットを選択 --</option>
                    <option v-for="ip in instructionsPresets" :key="ip.name" :value="ip">{{ ip.name }}</option>
                  </select>
                  <button type="button" @click="applyInstructionsPreset" class="bg-green-600 text-white px-2 py-1 rounded text-sm">適用</button>
                </div>
                <textarea v-model="form.default_instructions" rows="4" class="mt-2 textarea w-full"></textarea>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700">System prompt</label>
                <div class="flex gap-2 items-center mt-1">
                  <select v-model="selectedSystemPrompt" @blur="applySystemPromptPreset" @change="applySystemPromptPreset" class="input">
                    <option :value="null">-- プリセットを選択 --</option>
                    <option v-for="sp in systemPrompts" :key="sp.name" :value="sp">{{ sp.name }}</option>
                  </select>
                  <button type="button" @click="applySystemPromptPreset" class="bg-green-600 text-white px-2 py-1 rounded text-sm">適用</button>
                </div>
                <textarea v-model="form.system_prompt" rows="4" class="mt-2 textarea w-full"></textarea>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700">Model options (JSON)</label>
                <textarea v-model="modelOptionsText" rows="4" class="mt-1 textarea w-full"></textarea>
              </div>

              <div class="flex justify-end">
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">保存</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </main>
  <div v-if="toast" class="fixed bottom-6 right-6 bg-black text-white px-4 py-2 rounded shadow">{{ toast }}</div>
  </AppLayout>
</template>

