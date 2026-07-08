<script setup>
import { computed } from 'vue'
import { companyModules } from '@/CompanyModules/index.js'

const props = defineProps({
  auth: { type: Object, required: true },
  roleNavClass: { type: Function, required: true },
  group: { type: String, default: null }, // 'beforeUser' | 'afterUser' | null (all)
})

const emit = defineEmits(['navigate'])

const moduleKeys = computed(() => {
  const keys = []
  const companyType = props.auth.companyType

  if (companyType && companyModules[companyType]) {
    keys.push(companyType)
  }

  if (props.auth.user?.isSuperAdmin && !keys.includes('sunbrain')) {
    keys.push('sunbrain')
  }

  return keys
})

const extraRoles = computed(() => {
  const seen = new Set()

  return moduleKeys.value.flatMap((key) => companyModules[key]?.extraRoles ?? []).filter((r) => {
    if (seen.has(r.role)) return false
    if (props.group && r.group !== props.group) return false
    if (r.visibilityCheck && !r.visibilityCheck(props.auth)) return false

    seen.add(r.role)
    return true
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
