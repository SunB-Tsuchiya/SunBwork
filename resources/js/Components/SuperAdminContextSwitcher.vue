<script setup>
import { computed, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import axios from 'axios'

const page = usePage()
const open = ref(false)
const switching = ref(false)

const auth = computed(() => page.props.auth)
const companies = computed(() => auth.value.switchableCompanies ?? [])
const currentContextId = computed(() => auth.value.superAdminContextId)

const currentLabel = computed(() => {
  if (!currentContextId.value) return 'グローバル管理'
  const c = companies.value.find((c) => c.id === currentContextId.value)
  return c ? c.name : 'グローバル管理'
})

async function switchContext(companyId) {
  if (switching.value) return

  open.value = false
  switching.value = true

  try {
    await axios.post(route('superadmin.switch_context'), { company_id: companyId })
    window.location.reload()
  } catch (_) {
    switching.value = false
  }
}
</script>

<template>
  <div class="relative">
    <button
      type="button"
      class="flex items-center gap-1 rounded-md border border-gray-200 bg-white px-2 py-1 text-xs font-medium text-gray-600 hover:bg-gray-50"
      :disabled="switching"
      @click="open = !open"
    >
      <!-- 地球アイコン -->
      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253" />
      </svg>
      <span class="max-w-[7rem] truncate">{{ currentLabel }}</span>
      <svg class="h-3 w-3 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
      </svg>
    </button>

    <!-- ドロップダウン -->
    <div
      v-if="open"
      class="absolute left-0 top-9 z-50 w-52 rounded-md border border-gray-200 bg-white shadow-lg"
      @click.stop
    >
      <!-- グローバル管理 -->
      <button
        type="button"
        class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm hover:bg-gray-50"
        :class="!currentContextId ? 'font-semibold text-gray-900' : 'text-gray-700'"
        :disabled="switching"
        @click="switchContext(null)"
      >
        <span>🌐</span>
        <span>グローバル管理</span>
      </button>

      <div class="my-1 border-t border-gray-100" />

      <!-- 各会社 -->
      <template v-for="company in companies" :key="company.id">
        <button
          type="button"
          class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm hover:bg-gray-50"
          :class="currentContextId === company.id ? 'font-semibold text-gray-900' : 'text-gray-700'"
          :disabled="switching"
          @click="switchContext(company.id)"
        >
          <span>👤</span>
          <span class="truncate">{{ company.name }}</span>
        </button>
      </template>
    </div>

    <!-- クリックアウトで閉じる -->
    <div v-if="open" class="fixed inset-0 z-40" @click="open = false" />
  </div>
</template>
