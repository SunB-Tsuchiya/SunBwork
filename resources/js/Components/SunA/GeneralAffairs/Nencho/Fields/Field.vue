<script setup>
// ラベル + 入力欄 + エラー表示の共通部品（年末調整フォーム）
defineProps({
    label: { type: String, required: true },
    required: { type: Boolean, default: false },
    error: { type: String, default: '' },
    hint: { type: String, default: '' },
    // 記入例（入力しても消えず、いつも見える）。「例：」は自動で付ける
    example: { type: String, default: '' },
    for: { type: String, default: null },
});
</script>

<template>
    <div>
        <label :for="$props.for" class="mb-1 block text-sm font-medium text-gray-700">{{ label }}<span v-if="required" class="text-red-600"> *</span></label>
        <slot />
        <p v-if="example" class="mt-1 text-xs text-gray-600">例：{{ example.replace(/^例[：:]/, '') }}</p>
        <p v-if="hint" class="mt-1 text-xs text-gray-500">{{ hint }}</p>
        <p v-if="error" class="mt-1 text-xs text-red-600">{{ error }}</p>
    </div>
</template>
