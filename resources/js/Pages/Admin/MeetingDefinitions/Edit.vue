<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps({
    availableMembers:  { type: Array,  default: () => [] },
    meetingDefinition: { type: Object, required: true },
});

const recurrenceOptions = [
    { value: 'weekly', label: '毎週' }, { value: 'biweekly', label: '隔週' }, { value: 'monthly', label: '毎月' },
];
const dayOfWeekOptions = [
    { value: 0, label: '日' }, { value: 1, label: '月' }, { value: 2, label: '火' },
    { value: 3, label: '水' }, { value: 4, label: '木' }, { value: 5, label: '金' }, { value: 6, label: '土' },
];
const minuteOptions = ['00', '05', '10', '15', '20', '25', '30', '35', '40', '45', '50', '55'];

const initMemberIds = props.meetingDefinition.members?.map((m) => m.id) ?? [];

const form = useForm({
    title:         props.meetingDefinition.title ?? '',
    description:   props.meetingDefinition.description ?? '',
    recurrence:    props.meetingDefinition.recurrence ?? 'weekly',
    day_of_week:   props.meetingDefinition.day_of_week ?? 1,
    week_of_month: props.meetingDefinition.week_of_month ?? null,
    start_time:    props.meetingDefinition.start_time?.slice(0, 5) ?? '10:00',
    end_time:      props.meetingDefinition.end_time?.slice(0, 5) ?? '11:00',
    members:       [...initMemberIds],
});

const startHour   = ref(form.start_time.split(':')[0]);
const startMinute = ref(form.start_time.split(':')[1]);
const endHour     = ref(form.end_time.split(':')[0]);
const endMinute   = ref(form.end_time.split(':')[1]);

function syncTimes() {
    form.start_time = `${startHour.value}:${startMinute.value}`;
    form.end_time   = `${endHour.value}:${endMinute.value}`;
}

const showMemberModal = ref(false);
const memberSearch    = ref('');
const filteredMembers = computed(() => {
    const q = memberSearch.value.toLowerCase();
    return q ? props.availableMembers.filter((u) => u.name.toLowerCase().includes(q)) : props.availableMembers;
});
const selectedMemberIds = ref([...initMemberIds]);

function toggleMember(id) {
    const idx = selectedMemberIds.value.indexOf(id);
    if (idx >= 0) selectedMemberIds.value.splice(idx, 1); else selectedMemberIds.value.push(id);
}
function selectAll() { selectedMemberIds.value = filteredMembers.value.map((u) => u.id); }
function clearAll()  { selectedMemberIds.value = []; }
function confirmMembers() { form.members = [...selectedMemberIds.value]; showMemberModal.value = false; }

const selectedMemberNames = computed(() =>
    props.availableMembers.filter((u) => form.members.includes(u.id)).map((u) => u.name),
);

const errorMessage = ref('');

function submit() {
    syncTimes();
    form.put(route('admin.meeting_definitions.update', { meeting_definition: props.meetingDefinition.id }), {
        onError: () => { errorMessage.value = '保存に失敗しました。'; },
    });
}
</script>

<template>
    <AppLayout title="会議設定 編集">
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('admin.meeting_definitions.index')" class="rounded bg-gray-100 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-200">
                    ← 戻る
                </Link>
                <h2 class="text-xl font-semibold text-gray-800">会議設定 編集</h2>
            </div>
        </template>

        <div class="mx-auto max-w-2xl rounded bg-white p-6 shadow">
            <form @submit.prevent="submit" class="space-y-5">
                <div v-if="errorMessage" class="rounded border-l-4 border-red-500 bg-red-50 p-3 text-red-700 text-sm">{{ errorMessage }}</div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">タイトル <span class="text-red-500">*</span></label>
                    <input v-model="form.title" type="text" class="w-full rounded border p-2 text-sm" required />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">概要</label>
                    <textarea v-model="form.description" rows="3" class="w-full rounded border p-2 text-sm"></textarea>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">繰り返し <span class="text-red-500">*</span></label>
                    <div class="flex gap-4">
                        <label v-for="opt in recurrenceOptions" :key="opt.value" class="flex items-center gap-1.5 cursor-pointer text-sm">
                            <input type="radio" :value="opt.value" v-model="form.recurrence" class="accent-red-600" />{{ opt.label }}
                        </label>
                    </div>
                </div>
                <!-- 週指定（毎月のみ） -->
                <div v-if="form.recurrence === 'monthly'">
                    <label class="mb-1 block text-sm font-medium text-gray-700">週指定 <span class="text-red-500">*</span></label>
                    <select v-model="form.week_of_month" class="rounded border p-2 text-sm">
                        <option :value="null">— 選択 —</option>
                        <option :value="1">第1週</option>
                        <option :value="2">第2週</option>
                        <option :value="3">第3週</option>
                        <option :value="4">第4週</option>
                        <option :value="5">第5週</option>
                    </select>
                    <p v-if="form.errors.week_of_month" class="mt-1 text-xs text-red-500">{{ form.errors.week_of_month }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">曜日 <span class="text-red-500">*</span></label>
                    <div class="flex gap-3">
                        <label v-for="opt in dayOfWeekOptions" :key="opt.value" class="flex items-center gap-1 cursor-pointer text-sm">
                            <input type="radio" :value="opt.value" v-model="form.day_of_week" class="accent-red-600" />{{ opt.label }}
                        </label>
                    </div>
                </div>
                <div class="flex flex-wrap gap-6">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">開始時刻</label>
                        <div class="flex items-center gap-1">
                            <select v-model="startHour" @change="syncTimes" class="w-20 rounded border p-2 text-sm">
                                <option v-for="h in Array.from({length:24},(_,i)=>String(i).padStart(2,'0'))" :key="h" :value="h">{{ h }}</option>
                            </select>
                            <span>:</span>
                            <select v-model="startMinute" @change="syncTimes" class="w-20 rounded border p-2 text-sm">
                                <option v-for="m in minuteOptions" :key="m" :value="m">{{ m }}</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">終了時刻</label>
                        <div class="flex items-center gap-1">
                            <select v-model="endHour" @change="syncTimes" class="w-20 rounded border p-2 text-sm">
                                <option v-for="h in Array.from({length:24},(_,i)=>String(i).padStart(2,'0'))" :key="h" :value="h">{{ h }}</option>
                            </select>
                            <span>:</span>
                            <select v-model="endMinute" @change="syncTimes" class="w-20 rounded border p-2 text-sm">
                                <option v-for="m in minuteOptions" :key="m" :value="m">{{ m }}</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">参加メンバー <span class="text-red-500">*</span></label>
                    <div class="mb-2 flex flex-wrap gap-1">
                        <span v-for="name in selectedMemberNames" :key="name" class="rounded bg-red-100 px-2 py-0.5 text-xs text-red-700">{{ name }}</span>
                        <span v-if="selectedMemberNames.length === 0" class="text-xs text-gray-400">未選択</span>
                    </div>
                    <button type="button" @click="showMemberModal = true" class="rounded border border-red-400 px-3 py-1.5 text-sm text-red-600 hover:bg-red-50">メンバーを選択</button>
                </div>
                <div class="flex gap-3 pt-2">
                    <button type="submit" :disabled="form.processing" class="rounded bg-red-600 px-5 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50">
                        {{ form.processing ? '保存中...' : '更新する' }}
                    </button>
                    <Link :href="route('admin.meeting_definitions.index')" class="rounded border px-5 py-2 text-sm text-gray-600 hover:bg-gray-50">キャンセル</Link>
                </div>
            </form>
        </div>

        <div v-if="showMemberModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
            <div class="w-full max-w-lg rounded-lg bg-white p-6 shadow-xl">
                <h3 class="mb-4 text-base font-semibold">メンバーを選択</h3>
                <input v-model="memberSearch" type="text" placeholder="名前で絞り込み..." class="mb-3 w-full rounded border p-2 text-sm" />
                <div class="mb-2 flex gap-3">
                    <button type="button" @click="selectAll" class="text-xs text-red-600 hover:underline">全選択</button>
                    <button type="button" @click="clearAll"  class="text-xs text-gray-500 hover:underline">クリア</button>
                </div>
                <div class="max-h-64 overflow-y-auto rounded border">
                    <table class="w-full text-sm">
                        <tbody>
                            <tr v-for="member in filteredMembers" :key="member.id" class="border-b hover:bg-gray-50 cursor-pointer" @click="toggleMember(member.id)">
                                <td class="py-2 pl-3"><input type="checkbox" :checked="selectedMemberIds.includes(member.id)" @click.stop="toggleMember(member.id)" class="accent-red-600" /></td>
                                <td class="py-2 pl-2">{{ member.name }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="mt-4 flex gap-3">
                    <button type="button" @click="confirmMembers" class="rounded bg-red-600 px-4 py-2 text-sm text-white hover:bg-red-700">確定（{{ selectedMemberIds.length }}名）</button>
                    <button type="button" @click="showMemberModal = false" class="rounded border px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">キャンセル</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
