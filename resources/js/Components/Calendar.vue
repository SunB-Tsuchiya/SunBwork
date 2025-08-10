<template>
  <div class="calendar-container">
    <div class="flex gap-4 mb-4">
      <button @click="openEventModal" class="px-4 py-2 bg-blue-600 text-white rounded">予定作成</button>
      <button @click="goToDiaryCreate" class="px-4 py-2 bg-orange-500 text-white rounded">日報作成</button>
    </div>
    <FullCalendar
      :options="calendarOptions"
    />
    <!-- 予定作成モーダル -->
    <div v-if="showModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
      <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
        <h2 class="text-lg font-bold mb-4">イベント追加</h2>
        <form @submit.prevent="submitEvent">
          <!-- ...既存のイベントフォーム... -->
          <div class="mb-2">
            <label class="block text-sm font-medium">タイトル</label>
            <input v-model="form.title" type="text" class="border rounded w-full p-2" required />
          </div>
          <div class="mb-2">
            <label class="block text-sm font-medium">内容</label>
            <textarea v-model="form.description" class="border rounded w-full p-2" rows="2"></textarea>
          </div>
          <div class="mb-2">
            <div class="flex gap-6 items-center">
              <div>
                <label class="block text-sm font-medium mb-1">開始時刻</label>
                <div class="flex gap-2">
                  <select v-model="form.startHour" class="border rounded p-1 w-20">
                    <option v-for="h in 24" :key="h" :value="String(h-1).padStart(2, '0')">{{ String(h-1).padStart(2, '0') }}</option>
                  </select>
                  <select v-model="form.startMinute" class="border rounded p-1 w-20">
                    <option v-for="m in [0,15,30,45]" :key="m" :value="String(m).padStart(2, '0')">{{ String(m).padStart(2, '0') }}</option>
                  </select>
                </div>
              </div>
              <div>
                <label class="block text-sm font-medium mb-1">終了時刻</label>
                <div class="flex gap-2">
                  <select v-model="form.endHour" class="border rounded p-1 w-20">
                    <option v-for="h in 24" :key="h" :value="String(h-1).padStart(2, '0')">{{ String(h-1).padStart(2, '0') }}</option>
                  </select>
                  <select v-model="form.endMinute" class="border rounded p-1 w-20">
                    <option v-for="m in [0,15,30,45]" :key="m" :value="String(m).padStart(2, '0')">{{ String(m).padStart(2, '0') }}</option>
                  </select>
                </div>
              </div>
            </div>
          </div>
          <div class="flex justify-end gap-2 mt-4">
            <button type="button" @click="showModal=false" class="px-4 py-2 bg-gray-300 rounded">キャンセル</button>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">登録</button>
          </div>
        </form>
      </div>
    </div>
    <!-- 日付クリック時の選択モーダル -->
    <div v-if="showSelectModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
      <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-xs text-center">
        <h2 class="text-lg font-bold mb-4">{{ selectedDate }} の操作</h2>
        <div class="flex flex-col gap-4">
          <button @click="openEventModalFromSelect" class="px-4 py-2 bg-blue-600 text-white rounded">予定作成</button>
          <button @click="goToDiaryCreateFromSelect" class="px-4 py-2 bg-orange-500 text-white rounded">日報作成</button>
          <button @click="showSelectModal=false" class="px-4 py-2 bg-gray-300 rounded">キャンセル</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import FullCalendar from '@fullcalendar/vue3'
import dayGridPlugin from '@fullcalendar/daygrid'
import interactionPlugin from '@fullcalendar/interaction'
import timeGridPlugin from '@fullcalendar/timegrid'
import { router } from '@inertiajs/vue3'
import { route } from 'ziggy-js';
import { computed, ref } from 'vue'
import axios from 'axios'

const props = defineProps({
  diaries: {
    type: Array,
    default: () => []
  }
})


const showModal = ref(false)
const showSelectModal = ref(false)
const form = ref({
  title: '',
  description: '',
  startHour: '09',
  startMinute: '00',
  endHour: '10',
  endMinute: '00',
  date: '',
})

// カレンダーで選択中の日付（初期値は今日）
const today = new Date();
const yyyy = today.getFullYear();
const mm = String(today.getMonth() + 1).padStart(2, '0');
const dd = String(today.getDate()).padStart(2, '0');
const selectedDate = ref(`${yyyy}-${mm}-${dd}`);



function openEventModal() {
  // 選択中の日付をセット
  form.value.date = selectedDate.value;
  showModal.value = true;
}

function openEventModalFromSelect() {
  form.value.date = selectedDate.value;
  showModal.value = true;
  showSelectModal.value = false;
}

function goToDiaryCreateFromSelect() {
  router.get(route('diaries.create', { date: selectedDate.value }));
  showSelectModal.value = false;
}

// 日付クリック時の遷移処理を削除


function goToDiaryCreate() {
  // 選択中の日付で作成画面へ遷移
  router.get(route('diaries.create', { date: selectedDate.value }));
}



function handleDateSelect(selectionInfo) {
  // カレンダーで日付選択時に選択日を保持
  const dateStr = selectionInfo.startStr.split('T')[0];
  selectedDate.value = dateStr;
  // 予定・日報作成選択モーダルを表示
  showSelectModal.value = true;
}

// 日報がある日をイベントとして表示（タイトルは●アイコン）
const events = ref([
  // 日報（オレンジ）
  ...props.diaries.map(diary => {
    // UTC→JST(+9h)変換
    const d = new Date(diary.date);
    d.setHours(d.getHours() + 9);
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    return {
      title: '● 日報',
      start: `${yyyy}-${mm}-${dd}`,
      allDay: true,
      color: '#f59e42',
      diary_id: diary.id
    };
  }),
  // 予定（青）
  ...(props.events ?? []).map(event => {
    // startが「YYYY-MM-DD HH:mm:ss」形式の場合
    // day/weekビュー用にallDay: false, color:青
    return {
      title: event.title,
      start: event.start,
      end: event.end ?? undefined, // endがあれば
      allDay: false,
      color: '#2563eb',
    };
  })
]);

const calendarOptions = computed(() => ({
  plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
  initialView: 'dayGridMonth',
  events: events.value,
  locale: 'ja',
  headerToolbar: {
    left: 'prev,next today',
    center: 'title',
    right: 'dayGridMonth,timeGridWeek,timeGridDay'
  },
  selectable: true,
  slotMinTime: '06:00:00',
  slotMaxTime: '24:00:00',
  firstDay: 1,
  weekText: '週',
  dayHeaderFormat: { weekday: 'short' },
  slotDuration: '00:15:00',
  eventClick: function(info) {
    // 日報ラベルクリック時のみ遷移
    if (info.event.extendedProps.diary_id) {
      router.get(route('diaries.show', { diary: info.event.extendedProps.diary_id }));
    }
  },
  select: handleDateSelect,
}))

const submitEvent = async () => {
  const start = `${form.value.date} ${form.value.startHour}:${form.value.startMinute}:00`;
  const end = `${form.value.date} ${form.value.endHour}:${form.value.endMinute}:00`;
  console.log('送信start:', start, '送信end:', end);
  try {
    await axios.post('/events', {
      title: form.value.title,
      description: form.value.description,
      start,
      end,
    });
    showModal.value = false;
    // カレンダーに即時反映（青色ラベル）
    events.value.push({
      title: form.value.title,
      start,
      end,
      color: '#2563eb', // 青色
      allDay: false
    });
  } catch (e) {
    if (e.response && e.response.data && e.response.data.errors) {
      const messages = Object.values(e.response.data.errors).flat().join('\n');
      alert('登録に失敗しました:\n' + messages);
    } else {
      alert('登録に失敗しました');
    }
  }
}
</script>

<style scoped>
.calendar-container {
  padding: 1rem;
}

/* FullCalendarの基本スタイル */
.fc {
  font-family: inherit;
}

.fc-button {
  background-color: #3b82f6;
  border-color: #3b82f6;
  color: white;
}

.fc-button:hover {
  background-color: #2563eb;
  border-color: #2563eb;
}

.fc-daygrid-day {
  cursor: pointer;
}

.fc-daygrid-day:hover {
  background-color: #f3f4f6;
}
</style>