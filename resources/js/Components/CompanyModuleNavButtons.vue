<script setup>
import { computed } from 'vue'
import { companyModules } from '@/CompanyModules/index.js'

const props = defineProps({
  auth: { type: Object, required: true },
  roleNavClass: { type: Function, required: true },
  group: { type: String, default: null }, // 'beforeUser' | 'afterUser' | null (all)
})

const emit = defineEmits(['navigate'])

const extraRoles = computed(() => {
  const mod = companyModules[props.auth.companyType]
  if (!mod || !mod.extraRoles) return []
  return mod.extraRoles.filter((r) => {
    if (props.group && r.group !== props.group) return false
    return r.visibilityCheck ? r.visibilityCheck(props.auth) : true
  })
})
</script>

<template>
  <template v-for="r in extraRoles" :key="r.role">
    <button type="button" @click="emit('navigate', r.role)" :class="roleNavClass(r.role)">
      {{ r.label }}
    </button>
  </template>
</template>
