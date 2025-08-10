<script setup>
import AppLayout from '@/layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { ref } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faCalendar } from '@fortawesome/free-solid-svg-icons'
import Calendar from '@/Components/Calendar.vue'

const props = defineProps({ diaries: Array })
const showCalendar = ref(false)

const deleteDiary = (id) => {
  if (confirm('この日報を削除してよろしいですか？')) {
    router.delete(route('diaries.destroy', id))
  }
}
function formatJapaneseDate(dateStr) {
  const d = new Date(dateStr)
  return `${d.getFullYear()}年${d.getMonth() + 1}月${d.getDate()}日`
}
function handleDateSelect(dateStr) {
  // 必要なら日付選択時の処理
  showCalendar.value = false
}
</script>

<template>
  <AppLayout title="日報一覧">
    <div class="max-w-3xl mx-auto p-6 bg-white shadow rounded">
      <div class="flex justify-between items-center mb-6 relative">
        <div class="flex items-center">
          <h1 class="text-2xl font-bold">日報一覧</h1>
          <button @click="showCalendar = true" class="ml-4 text-gray-600 hover:text-blue-600" ref="calendarBtn">
            <FontAwesomeIcon :icon="faCalendar" size="lg" />
          </button>
          <div v-if="showCalendar">
            <!-- オーバーレイ -->
            <div class="fixed inset-0 z-40 bg-transparent" @click="showCalendar = false"></div>
            <!-- カレンダー本体 -->
            <div class="calendar-popup absolute top-full left-auto ml-2 mt-2 z-50">
              <div class="bg-white rounded shadow-lg p-4 min-w-[300px]">
                <Calendar @date-select="handleDateSelect" />
                <button @click="showCalendar = false" class="mt-2 text-xs text-gray-500 hover:text-blue-600">閉じる</button>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="mb-4">
        <Link :href="route('diaries.create')" class="px-4 py-2 bg-green-600 text-white rounded">新しく日報を書く</Link>
      </div>
      <div v-if="props.diaries.length === 0" class="text-gray-500">日報はありません。</div>
      <div v-else>
        <div v-for="diary in props.diaries" :key="diary.id" class="mb-8 border-b pb-4">
          <div class="flex justify-between items-center mb-2">
            <div class="text-lg font-semibold">{{ formatJapaneseDate(diary.date) }}</div>
            <div class="flex space-x-2">
              <Link :href="route('diaries.edit', diary.id)" class="px-2 py-1 bg-blue-600 text-white rounded text-sm">編集</Link>
              <button @click="deleteDiary(diary.id)" class="px-2 py-1 bg-red-600 text-white rounded text-sm">削除</button>
            </div>
          </div>
          <div class="prose text-gray-800 mb-2">
            <p v-html="diary.content"></p>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
.calendar-popup {
  min-width: 320px;
}
</style>
