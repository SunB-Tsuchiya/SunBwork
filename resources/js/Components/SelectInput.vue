<template>
  <select
    v-bind="$attrs"
    :value="modelValue"
    @input="$emit('update:modelValue', $event.target.value)"
    class="form-select"
  >
    <option value="">選択してください</option>
    <option v-for="option in options" :key="option.value" :value="option.value">
      {{ option.label }}
    </option>
  </select>
</template>

<script setup>
import { defineProps } from 'vue';
const props = defineProps({
  modelValue: [String, Number],
  options: {
    type: Array,
    default: () => []
  }
});
</script>

<style scoped>
.form-select {
  width: 100%;
  padding: 0.5rem;
  border-radius: 0.375rem;
  border: 1px solid #d1d5db;
}
</style>
