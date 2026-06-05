<script setup>
import { reactive, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    department: { type: Object, required: true },
    configs:    { type: Object, default: () => ({}) },
    masters:    { type: Object, default: () => ({ types: [], stages: [], sizes: [] }) },
    saveRoute:  { type: String, required: true },
    backRoute:  { type: String, required: true },
});

const page = usePage();

const SLOTS = [
    { key: 'type',    defaultLabel: '作業種別',        masterKey: 'types',  legacySource: 'work_item_types' },
    { key: 'stage',   defaultLabel: 'ステージ（校数）', masterKey: 'stages', legacySource: 'stages' },
    { key: 'size',    defaultLabel: 'サイズ',          masterKey: 'sizes',  legacySource: 'sizes' },
    { key: 'amounts', defaultLabel: '数量',            masterKey: null,     legacySource: null },
];

// job_field_options のグループ一覧（会社スコープ）
const jobFieldOptions = computed(() => page.props.jobFieldOptions ?? []);
const jobFieldGroups = computed(() => {
    const groups = {};
    for (const item of jobFieldOptions.value) {
        const g = item.group_key || '（グループなし）';
        if (!groups[g]) groups[g] = [];
        groups[g].push(item);
    }
    return groups;
});
const jobFieldGroupKeys = computed(() => Object.keys(jobFieldGroups.value).sort());

function initSlot(slot) {
    const cfg = props.configs[slot.key];
    return {
        slot:             slot.key,
        label:            cfg?.label ?? slot.defaultLabel,
        enabled:          cfg ? cfg.enabled : true,
        source:           cfg?.source ?? null,         // null = 既存テーブル, 'job_field_options' = 新テーブル
        source_group:     cfg?.source_group ?? '',
        allowed_item_ids: cfg?.allowed_item_ids ?? null,
        useAll:           cfg == null || cfg.allowed_item_ids == null,
    };
}

const form = reactive(
    Object.fromEntries(SLOTS.map((s) => [s.key, initSlot(s)]))
);

// ── ソース切り替え ──────────────────────────────────────────────
function setSource(slotKey, value) {
    form[slotKey].source = value;
    form[slotKey].source_group = '';
    form[slotKey].allowed_item_ids = null;
    form[slotKey].useAll = true;
}

// ── 既存テーブル用のアイテム ────────────────────────────────────
function masterItems(masterKey) {
    if (!masterKey) return [];
    return props.masters[masterKey] ?? [];
}

function groupedItems(masterKey) {
    const items = masterItems(masterKey);
    const groups = {};
    for (const item of items) {
        const g = item.group ?? null;
        if (!groups[g]) groups[g] = { label: g ?? 'その他', items: [] };
        groups[g].items.push(item);
    }
    return Object.values(groups);
}

function isChecked(slotKey, itemId) {
    const f = form[slotKey];
    if (f.useAll) return true;
    return (f.allowed_item_ids ?? []).includes(itemId);
}

function toggleItem(slotKey, itemId, slot) {
    const f = form[slotKey];
    if (f.useAll) {
        f.useAll = false;
        f.allowed_item_ids = masterItems(slot.masterKey)
            .map((i) => i.id)
            .filter((id) => id !== itemId);
        return;
    }
    const ids = f.allowed_item_ids ?? [];
    if (ids.includes(itemId)) {
        f.allowed_item_ids = ids.filter((id) => id !== itemId);
    } else {
        f.allowed_item_ids = [...ids, itemId];
    }
    const all = masterItems(slot.masterKey).map((i) => i.id);
    if (f.allowed_item_ids.length === all.length) {
        f.useAll = true;
        f.allowed_item_ids = null;
    }
}

function setUseAll(slotKey, val) {
    form[slotKey].useAll = val;
    if (val) form[slotKey].allowed_item_ids = null;
}

const emit = defineEmits(['submit']);

function buildPayload() {
    return {
        slots: SLOTS.map((s) => {
            const f = form[s.key];
            return {
                slot:             s.key,
                label:            f.label || s.defaultLabel,
                enabled:          f.enabled,
                source:           f.source || null,
                source_group:     f.source === 'job_field_options' ? (f.source_group || '') : null,
                allowed_item_ids: (f.source !== 'job_field_options' && !f.useAll)
                    ? (f.allowed_item_ids ?? null)
                    : null,
            };
        }),
    };
}

function handleSubmit() {
    emit('submit', buildPayload());
}
</script>

<template>
    <form @submit.prevent="handleSubmit" class="space-y-6">
        <template v-for="slot in SLOTS" :key="slot.key">
            <div class="rounded border bg-white p-4 shadow-sm">
                <!-- スロットヘッダー -->
                <div class="mb-3 flex items-center gap-3">
                    <label class="relative inline-flex cursor-pointer items-center">
                        <input type="checkbox" v-model="form[slot.key].enabled" class="peer sr-only" />
                        <div class="peer h-5 w-9 rounded-full bg-gray-200 after:absolute after:left-[2px] after:top-[2px] after:h-4 after:w-4 after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:bg-green-500 peer-checked:after:translate-x-full"></div>
                    </label>
                    <span class="text-sm font-semibold text-gray-700">
                        {{ slot.defaultLabel }}（スロット）
                    </span>
                    <span v-if="!form[slot.key].enabled" class="text-xs text-gray-400">非表示</span>
                </div>

                <div v-if="form[slot.key].enabled" class="space-y-4">
                    <!-- ラベル名入力 -->
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">表示ラベル名</label>
                        <input
                            v-model="form[slot.key].label"
                            type="text"
                            maxlength="100"
                            :placeholder="slot.defaultLabel"
                            class="w-full max-w-xs rounded border px-3 py-1.5 text-sm focus:border-blue-400 focus:outline-none focus:ring-1 focus:ring-blue-400"
                        />
                    </div>

                    <!-- データソース選択（amounts は除く） -->
                    <template v-if="slot.masterKey">
                        <div>
                            <label class="mb-2 block text-xs font-medium text-gray-600">データソース</label>
                            <div class="flex gap-3">
                                <label class="flex cursor-pointer items-center gap-1.5 rounded border px-3 py-2 text-sm"
                                    :class="!form[slot.key].source || form[slot.key].source !== 'job_field_options'
                                        ? 'border-blue-400 bg-blue-50 text-blue-700 font-semibold'
                                        : 'border-gray-300 text-gray-600 hover:bg-gray-50'">
                                    <input
                                        type="radio"
                                        :name="`source_${slot.key}`"
                                        :value="null"
                                        :checked="form[slot.key].source !== 'job_field_options'"
                                        @change="setSource(slot.key, null)"
                                        class="sr-only"
                                    />
                                    既存マスタ（{{ slot.defaultLabel }}テーブル）
                                </label>
                                <label class="flex cursor-pointer items-center gap-1.5 rounded border px-3 py-2 text-sm"
                                    :class="form[slot.key].source === 'job_field_options'
                                        ? 'border-purple-400 bg-purple-50 text-purple-700 font-semibold'
                                        : 'border-gray-300 text-gray-600 hover:bg-gray-50'">
                                    <input
                                        type="radio"
                                        :name="`source_${slot.key}`"
                                        value="job_field_options"
                                        :checked="form[slot.key].source === 'job_field_options'"
                                        @change="setSource(slot.key, 'job_field_options')"
                                        class="sr-only"
                                    />
                                    カスタム項目（汎用プール）
                                </label>
                            </div>
                        </div>

                        <!-- 汎用プール：グループ選択 -->
                        <template v-if="form[slot.key].source === 'job_field_options'">
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-600">使用するグループ</label>
                                <select
                                    v-model="form[slot.key].source_group"
                                    class="w-full max-w-sm rounded border px-3 py-1.5 text-sm focus:border-purple-400 focus:outline-none"
                                >
                                    <option value="">— グループを選択 —</option>
                                    <option v-for="gk in jobFieldGroupKeys" :key="gk" :value="gk">
                                        {{ gk }}（{{ jobFieldGroups[gk].length }}件）
                                    </option>
                                </select>
                                <p v-if="jobFieldGroupKeys.length === 0" class="mt-1 text-xs text-gray-400">
                                    カスタム項目がありません。workload-setting の「カスタム項目」タブで追加してください。
                                </p>
                                <!-- プレビュー -->
                                <div v-if="form[slot.key].source_group && jobFieldGroups[form[slot.key].source_group]"
                                    class="mt-2 flex flex-wrap gap-1.5">
                                    <span
                                        v-for="item in jobFieldGroups[form[slot.key].source_group]"
                                        :key="item.id"
                                        class="rounded-full bg-purple-100 px-2.5 py-0.5 text-xs text-purple-700"
                                    >{{ item.name }}</span>
                                </div>
                            </div>
                        </template>

                        <!-- 既存マスタ：選択肢フィルタ -->
                        <template v-else>
                            <div>
                                <div class="text-xs font-medium text-gray-600">使用する選択肢</div>
                                <div class="mb-1 mt-1 flex gap-3 text-xs">
                                    <button
                                        type="button"
                                        :class="['rounded px-2 py-0.5 border text-xs', form[slot.key].useAll ? 'bg-blue-100 border-blue-400 text-blue-700 font-semibold' : 'border-gray-300 text-gray-500 hover:bg-gray-50']"
                                        @click="setUseAll(slot.key, true)"
                                    >すべて使用</button>
                                    <button
                                        type="button"
                                        :class="['rounded px-2 py-0.5 border text-xs', !form[slot.key].useAll ? 'bg-blue-100 border-blue-400 text-blue-700 font-semibold' : 'border-gray-300 text-gray-500 hover:bg-gray-50']"
                                        @click="setUseAll(slot.key, false)"
                                    >個別に選択</button>
                                </div>

                                <div v-if="!form[slot.key].useAll" class="rounded border border-gray-200 bg-gray-50 p-3">
                                    <template v-if="masterItems(slot.masterKey).length === 0">
                                        <p class="text-xs text-gray-400">（workload-setting に項目がありません）</p>
                                    </template>
                                    <template v-else>
                                        <div v-for="group in groupedItems(slot.masterKey)" :key="group.label" class="mb-3">
                                            <div v-if="group.label !== 'その他'" class="mb-1 text-xs font-semibold text-gray-500">
                                                {{ group.label }}
                                            </div>
                                            <div class="flex flex-wrap gap-x-4 gap-y-1">
                                                <label
                                                    v-for="item in group.items"
                                                    :key="item.id"
                                                    class="flex cursor-pointer items-center gap-1.5 text-sm"
                                                >
                                                    <input
                                                        type="checkbox"
                                                        :checked="isChecked(slot.key, item.id)"
                                                        @change="toggleItem(slot.key, item.id, slot)"
                                                        class="rounded border-gray-400 text-blue-600"
                                                    />
                                                    {{ item.name }}
                                                </label>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                                <p v-else class="text-xs text-gray-400">テーブルに登録されている全項目を使用します</p>
                            </div>
                        </template>
                    </template>
                </div>
            </div>
        </template>

        <div class="flex gap-3 pt-2">
            <button type="submit" class="rounded bg-red-600 px-5 py-2 text-sm font-bold text-white hover:bg-red-700">
                保存する
            </button>
            <a :href="backRoute" class="rounded bg-gray-100 px-5 py-2 text-sm font-medium text-gray-600 hover:bg-gray-200">
                キャンセル
            </a>
        </div>
    </form>
</template>
