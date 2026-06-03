<script setup>
import { ref, onMounted } from 'vue';
import FullCalendar from '@fullcalendar/vue3';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';
import jaLocale from '@fullcalendar/core/locales/ja';
import axios from 'axios';
import { route } from 'ziggy-js';

const props = defineProps({
    teamId:     { type: Number, required: true },
    authUserId: { type: Number, default: null },
});

const events = ref([]);
const showModal = ref(false);
const editingEvent = ref(null);
const form = ref({ title: '', description: '', starts_at: '', ends_at: '', all_day: false });
const saving = ref(false);

async function fetchEvents() {
    try {
        const res = await axios.get(route('team-rooms.events.index', { team: props.teamId }));
        events.value = res.data;
    } catch {
        //
    }
}

onMounted(fetchEvents);

function refreshCalendar() {
    calendarRef.value?.getApi()?.updateSize();
}

defineExpose({ refreshCalendar });

const calendarRef = ref(null);

const calendarOptions = {
    plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
    locale: jaLocale,
    initialView: 'dayGridMonth',
    headerToolbar: {
        left:   'prev,next today',
        center: 'title',
        right:  'dayGridMonth,timeGridWeek',
    },
    editable: false,
    selectable: true,
    events: events,
    dateClick(info) {
        openCreateModal(info.dateStr);
    },
    eventClick(info) {
        openEditModal(info.event);
    },
};

function openCreateModal(dateStr = '') {
    editingEvent.value = null;
    const today = dateStr || new Date().toISOString().slice(0, 10);
    form.value = {
        title:       '',
        description: '',
        starts_at:   today + 'T09:00',
        ends_at:     today + 'T10:00',
        all_day:     false,
    };
    showModal.value = true;
}

function openEditModal(event) {
    if (event.extendedProps.editable === false) return;
    editingEvent.value = event;
    const s = event.start ? formatDateTime(event.start) : '';
    const e = event.end   ? formatDateTime(event.end)   : '';
    form.value = {
        title:       event.title,
        description: event.extendedProps.description || '',
        starts_at:   s,
        ends_at:     e,
        all_day:     event.allDay,
    };
    showModal.value = true;
}

function formatDateTime(dt) {
    if (!dt) return '';
    const d = new Date(dt);
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

async function saveEvent() {
    if (! form.value.title.trim()) { alert('タイトルは必須です'); return; }
    saving.value = true;
    try {
        const payload = { ...form.value };
        if (editingEvent.value) {
            await axios.put(route('team-rooms.events.update', { team: props.teamId, event: editingEvent.value.id }), payload);
        } else {
            await axios.post(route('team-rooms.events.store', { team: props.teamId }), payload);
        }
        showModal.value = false;
        await fetchEvents();
    } catch (e) {
        alert('保存に失敗しました');
    } finally {
        saving.value = false;
    }
}

async function deleteEvent() {
    if (! editingEvent.value) return;
    if (! confirm('この予定を削除しますか？')) return;
    saving.value = true;
    try {
        await axios.delete(route('team-rooms.events.destroy', { team: props.teamId, event: editingEvent.value.id }));
        showModal.value = false;
        await fetchEvents();
    } catch {
        alert('削除に失敗しました');
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <div>
        <div class="mb-3 flex items-center gap-3">
            <h3 class="font-semibold text-gray-800">スケジュール</h3>
            <button
                type="button"
                class="rounded bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700"
                @click="openCreateModal()"
            >予定を追加</button>
        </div>

        <FullCalendar ref="calendarRef" :options="calendarOptions" />

        <!-- 予定作成・編集モーダル -->
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                <h4 class="mb-4 text-base font-semibold text-gray-800">
                    {{ editingEvent ? '予定を編集' : '予定を作成' }}
                </h4>

                <div class="space-y-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">タイトル <span class="text-red-500">*</span></label>
                        <input v-model="form.title" type="text" maxlength="255"
                            class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">説明</label>
                        <textarea v-model="form.description" rows="2" maxlength="2000"
                            class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none"></textarea>
                    </div>
                    <div class="flex items-center gap-2">
                        <input id="all_day" v-model="form.all_day" type="checkbox" class="rounded" />
                        <label for="all_day" class="text-sm text-gray-600">終日</label>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600">開始</label>
                            <input v-model="form.starts_at"
                                :type="form.all_day ? 'date' : 'datetime-local'"
                                class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600">終了</label>
                            <input v-model="form.ends_at"
                                :type="form.all_day ? 'date' : 'datetime-local'"
                                class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none" />
                        </div>
                    </div>
                </div>

                <div class="mt-5 flex justify-between">
                    <button v-if="editingEvent" type="button"
                        class="rounded bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700"
                        :disabled="saving" @click="deleteEvent"
                    >削除</button>
                    <div v-else></div>
                    <div class="flex gap-2">
                        <button type="button"
                            class="rounded bg-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-300"
                            @click="showModal = false"
                        >キャンセル</button>
                        <button type="button"
                            class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                            :disabled="saving" @click="saveEvent"
                        >{{ saving ? '保存中...' : '保存' }}</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
