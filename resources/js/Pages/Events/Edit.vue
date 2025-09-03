<script setup>
import { ref, onMounted } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useForm, Link, router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

const props = defineProps({ event: Object });


const content = ref(props.event.description || '');
const form = useForm({
  title: props.event.title || '',
  description: props.event.description || '',
  date: props.event.date || '',
  startHour: props.event.startHour || '09',
  startMinute: props.event.startMinute || '00',
  endHour: props.event.endHour || '10',
  endMinute: props.event.endMinute || '00',
});

onMounted(() => {
  content.value = props.event.description || '';
  form.date = props.event.date || '';
  console.log('[Edit.vue] onMounted date:', form.date);
  // 開始・終了時刻をprops.event.start/endから分解してセット
  if (props.event.start) {
    const startDate = new Date(props.event.start);
    form.startHour = String(startDate.getHours()).padStart(2, '0');
    form.startMinute = String(startDate.getMinutes()).padStart(2, '0');
  }
  if (props.event.end) {
    const endDate = new Date(props.event.end);
    form.endHour = String(endDate.getHours()).padStart(2, '0');
    form.endMinute = String(endDate.getMinutes()).padStart(2, '0');
  }
});

const submit = () => {
  form.description = content.value;
  // 時刻チェック
  const start = `${form.date} ${form.startHour}:${form.startMinute}`;
  const end = `${form.date} ${form.endHour}:${form.endMinute}`;
  if (end <= start) {
    alert('終了時刻は開始時刻より後にしてください。');
    return;
  }
  // 重複チェック
  fetch(`/events?date=${form.date}`)
    .then(res => res.json())
    .then(events => {
      const newStart = new Date(`${form.date}T${form.startHour}:${form.startMinute}:00`);
      const newEnd = new Date(`${form.date}T${form.endHour}:${form.endMinute}:00`);
      const overlap = events.some(ev => {
        if (ev.id === props.event.id) return false; // 自分自身は除外
        const evStart = new Date(ev.start);
        const evEnd = new Date(ev.end);
        return (newStart < evEnd && newEnd > evStart);
      });
      if (overlap) {
        if (!confirm('同じ時間に予定があります。登録しますか？')) {
          return;
        }
      }
      // always send JSON PUT (no files)
      form.put(route('events.update', props.event.id), {
        onSuccess: () => router.get(route('calendar.index'))
      });
    });
};
</script>

<template>
  <AppLayout title="イベント編集">
    <div class="max-w-2xl mx-auto p-6 bg-white shadow rounded">
      <h1 class="text-2xl font-bold mb-4">イベント編集 ({{ form.date }})</h1>
      <form @submit.prevent="submit">
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">タイトル</label>
          <input v-model="form.title" type="text" class="w-full border rounded p-2" required />
        </div>
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">内容</label>
          <textarea v-model="content" rows="6" class="w-full border rounded p-2" />
        </div>
        <div class="mb-4">
          <div class="flex gap-8 items-center">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">開始時刻</label>
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
              <label class="block text-sm font-medium text-gray-700 mb-1">終了時刻</label>
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
        <div class="flex space-x-4">
          <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">更新</button>
          <Link :href="route('calendar.index')" class="px-4 py-2 bg-gray-200 text-gray-700 rounded">キャンセル</Link>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
