<script setup>
/**
 * Leader/MeetingDefinitions/Edit.vue
 */
import AppLayout from '@/layouts/AppLayout.vue';
import DialogModal from '@/Components/DialogModal.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps({
    availableMembers:  { type: Array,  default: () => [] },
    departments:       { type: Array,  default: () => [] },
    assignments:       { type: Array,  default: () => [] },
    meetingDefinition: { type: Object, required: true },
});

const recurrenceOptions = [
    { value: 'weekly',   label: '毎週' },
    { value: 'biweekly', label: '隔週' },
    { value: 'monthly',  label: '毎月' },
    { value: 'custom_dates', label: 'カレンダーから選ぶ' },
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
    custom_dates:  [...(props.meetingDefinition.custom_dates ?? [])],
    start_time:    props.meetingDefinition.start_time?.slice(0, 5) ?? '10:00',
    end_time:      props.meetingDefinition.end_time?.slice(0, 5) ?? '11:00',
    members:       [...initMemberIds],
});

const customDateInput = ref(new Date().toLocaleDateString('sv-SE'));

const startHour   = ref(form.start_time.split(':')[0]);
const startMinute = ref(form.start_time.split(':')[1]);
const endHour     = ref(form.end_time.split(':')[0]);
const endMinute   = ref(form.end_time.split(':')[1]);

function syncTimes() {
    form.start_time = `${startHour.value}:${startMinute.value}`;
    form.end_time   = `${endHour.value}:${endMinute.value}`;
}

function customDateSort(a, b) {
    return a.localeCompare(b);
}

function addCustomDate(dateValue = customDateInput.value) {
    if (!dateValue) return;
    if (!/^\d{4}-\d{2}-\d{2}$/.test(dateValue)) return;
    if (form.custom_dates.includes(dateValue)) return;
    form.custom_dates = [...form.custom_dates, dateValue].sort(customDateSort);
}

function removeCustomDate(dateValue) {
    form.custom_dates = form.custom_dates.filter((d) => d !== dateValue);
}

function ymdDayOfWeek(dateValue) {
    const parts = dateValue.split('-').map((v) => Number(v));
    return new Date(parts[0], parts[1] - 1, parts[2]).getDay();
}

// メンバー選択モーダル
const showMemberModal      = ref(false);
const selectedDepartmentId = ref('');
const selectedAssignmentId = ref('');
const selectedMemberIds    = ref([...initMemberIds]);

watch(selectedDepartmentId, () => { selectedAssignmentId.value = ''; });

const filteredAssignments = computed(() => {
    if (!selectedDepartmentId.value) return [];
    return props.assignments.filter((a) =>
        props.availableMembers.some(
            (m) => m.department_id == selectedDepartmentId.value && m.assignment_id == a.id,
        ),
    );
});

const filteredMembers = computed(() => {
    let list = props.availableMembers;
    if (selectedDepartmentId.value) list = list.filter((m) => m.department_id == selectedDepartmentId.value);
    if (selectedAssignmentId.value) list = list.filter((m) => m.assignment_id == selectedAssignmentId.value);
    return list;
});

const allChecked = computed(() =>
    filteredMembers.value.length > 0 &&
    filteredMembers.value.every((m) => selectedMemberIds.value.includes(m.id)),
);

const selectedMemberNames = computed(() =>
    props.availableMembers.filter((u) => form.members.includes(u.id)).map((u) => u.name),
);

function openMemberModal() {
    selectedMemberIds.value = [...form.members];
    showMemberModal.value   = true;
}

function closeMemberModal() {
    showMemberModal.value      = false;
    selectedDepartmentId.value = '';
    selectedAssignmentId.value = '';
}

function toggleMember(id) {
    const idx = selectedMemberIds.value.indexOf(id);
    if (idx >= 0) selectedMemberIds.value.splice(idx, 1);
    else selectedMemberIds.value.push(id);
}

function toggleAllMembers() {
    if (allChecked.value) {
        selectedMemberIds.value = selectedMemberIds.value.filter(
            (id) => !filteredMembers.value.some((m) => m.id === id),
        );
    } else {
        filteredMembers.value.forEach((m) => {
            if (!selectedMemberIds.value.includes(m.id)) selectedMemberIds.value.push(m.id);
        });
    }
}

function clearMemberFilters() {
    selectedDepartmentId.value = '';
    selectedAssignmentId.value = '';
}

function confirmMembers() {
    form.members = [...selectedMemberIds.value];
    closeMemberModal();
}

function getDepartmentName(deptId) {
    return props.departments.find((d) => d.id === deptId)?.name ?? '';
}

function getAssignmentName(assignId) {
    return props.assignments.find((a) => a.id === assignId)?.name ?? '';
}

function getAssignmentBadgeClass(name) {
    const map = {
        '進行': 'bg-blue-100 text-blue-800',
        '営業': 'bg-green-100 text-green-800',
        '校正': 'bg-yellow-100 text-yellow-800',
        'DTP':  'bg-purple-100 text-purple-800',
        '製版': 'bg-red-100 text-red-800',
        '印刷': 'bg-gray-100 text-gray-800',
    };
    return map[name] ?? 'bg-gray-100 text-gray-800';
}

const errorMessage = ref('');

function submit() {
    syncTimes();
    if (form.recurrence === 'custom_dates') {
        if (form.custom_dates.length > 0) {
            form.day_of_week = ymdDayOfWeek(form.custom_dates[0]);
        }
    }
    form.put(route('leader.meeting_definitions.update', { meeting_definition: props.meetingDefinition.id }), {
        onError: () => { errorMessage.value = '保存に失敗しました。'; },
    });
}
</script>

<template>
    <AppLayout title="会議設定 編集">
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('leader.meeting_definitions.index')" class="rounded bg-gray-100 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-200">
                    ← 戻る
                </Link>
                <h2 class="text-xl font-semibold text-gray-800">会議設定 編集</h2>
            </div>
        </template>

        <div class="mx-auto max-w-2xl rounded bg-white px-4 py-6 sm:p-6 shadow">
            <form @submit.prevent="submit" class="space-y-5">
                <div v-if="errorMessage" class="rounded border-l-4 border-red-500 bg-red-50 p-3 text-red-700 text-sm">{{ errorMessage }}</div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">タイトル <span class="text-red-500">*</span></label>
                    <input v-model="form.title" type="text" class="w-full rounded border p-2 text-sm" required />
                    <p v-if="form.errors.title" class="mt-1 text-xs text-red-500">{{ form.errors.title }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">概要</label>
                    <textarea v-model="form.description" rows="3" class="w-full rounded border p-2 text-sm"></textarea>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">繰り返し <span class="text-red-500">*</span></label>
                    <div class="flex gap-4">
                        <label v-for="opt in recurrenceOptions" :key="opt.value" class="flex items-center gap-1.5 cursor-pointer text-sm">
                            <input type="radio" :value="opt.value" v-model="form.recurrence" class="accent-orange-600" />{{ opt.label }}
                        </label>
                    </div>
                </div>
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
                <div v-if="form.recurrence !== 'custom_dates'">
                    <label class="mb-1 block text-sm font-medium text-gray-700">曜日 <span class="text-red-500">*</span></label>
                    <div class="flex gap-3">
                        <label v-for="opt in dayOfWeekOptions" :key="opt.value" class="flex items-center gap-1 cursor-pointer text-sm">
                            <input type="radio" :value="opt.value" v-model="form.day_of_week" class="accent-orange-600" />{{ opt.label }}
                        </label>
                    </div>
                    <p v-if="form.errors.day_of_week" class="mt-1 text-xs text-red-500">{{ form.errors.day_of_week }}</p>
                </div>
                <div v-else>
                    <label class="mb-1 block text-sm font-medium text-gray-700">開催日を選択 <span class="text-red-500">*</span></label>
                    <div class="flex flex-wrap items-center gap-2">
                        <input
                            v-model="customDateInput"
                            type="date"
                            class="rounded border p-2 text-sm"
                            @change="addCustomDate(customDateInput)"
                        />
                        <button type="button" class="rounded border border-orange-400 px-3 py-1.5 text-sm text-orange-600 hover:bg-orange-50" @click="addCustomDate(customDateInput)">
                            日付を追加
                        </button>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">カレンダーで選んだ日付を複数登録できます。</p>
                    <div class="mt-2 flex flex-wrap gap-1">
                        <span v-for="dateValue in form.custom_dates" :key="dateValue" class="inline-flex items-center gap-1 rounded bg-orange-100 px-2 py-0.5 text-xs text-orange-700">
                            {{ dateValue }}
                            <button type="button" class="font-semibold" @click="removeCustomDate(dateValue)">×</button>
                        </span>
                        <span v-if="form.custom_dates.length === 0" class="text-xs text-gray-400">未選択</span>
                    </div>
                    <p v-if="form.errors.custom_dates" class="mt-1 text-xs text-red-500">{{ form.errors.custom_dates }}</p>
                    <p v-if="form.errors['custom_dates.0']" class="mt-1 text-xs text-red-500">{{ form.errors['custom_dates.0'] }}</p>
                </div>
                <div class="flex flex-wrap gap-6">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">開始時刻</label>
                        <div class="flex items-center gap-1">
                            <select v-model="startHour" @change="syncTimes" class="w-20 rounded border p-2 text-sm">
                                <option v-for="h in Array.from({length:24},(_,i)=>String(i).padStart(2,'0'))" :key="h" :value="h">{{ h }}</option>
                            </select>
                            <span class="text-gray-500">:</span>
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
                            <span class="text-gray-500">:</span>
                            <select v-model="endMinute" @change="syncTimes" class="w-20 rounded border p-2 text-sm">
                                <option v-for="m in minuteOptions" :key="m" :value="m">{{ m }}</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">参加メンバー <span class="text-red-500">*</span></label>
                    <div class="mb-2 flex flex-wrap gap-1">
                        <span v-for="name in selectedMemberNames" :key="name" class="rounded bg-orange-100 px-2 py-0.5 text-xs text-orange-700">{{ name }}</span>
                        <span v-if="selectedMemberNames.length === 0" class="text-xs text-gray-400">未選択</span>
                    </div>
                    <button type="button" @click="openMemberModal" class="rounded border border-orange-400 px-3 py-1.5 text-sm text-orange-600 hover:bg-orange-50">メンバーを選択</button>
                    <p v-if="form.errors.members" class="mt-1 text-xs text-red-500">{{ form.errors.members }}</p>
                </div>
                <div class="flex gap-3 pt-2">
                    <button type="submit" :disabled="form.processing" class="rounded bg-orange-600 px-5 py-2 text-sm font-medium text-white hover:bg-orange-700 disabled:opacity-50">
                        {{ form.processing ? '保存中...' : '更新する' }}
                    </button>
                    <Link :href="route('leader.meeting_definitions.index')" class="rounded border px-5 py-2 text-sm text-gray-600 hover:bg-gray-50">キャンセル</Link>
                </div>
            </form>
        </div>

        <!-- メンバー選択モーダル -->
        <DialogModal :show="showMemberModal" @close="closeMemberModal">
            <template #title>メンバーを選択</template>
            <template #content>
                <div class="mb-4 flex items-center gap-4">
                    <div class="flex-1">
                        <label class="mb-1 block text-sm font-medium">部署</label>
                        <select v-model="selectedDepartmentId" class="w-full rounded border px-3 py-2 text-sm">
                            <option value="">-- 全部署 --</option>
                            <option v-for="dept in departments" :key="dept.id" :value="String(dept.id)">{{ dept.name }}</option>
                        </select>
                    </div>
                    <div class="flex-1">
                        <label class="mb-1 block text-sm font-medium">担当</label>
                        <select v-model="selectedAssignmentId" class="w-full rounded border px-3 py-2 text-sm" :disabled="!selectedDepartmentId">
                            <option value="">-- 全担当 --</option>
                            <option v-for="a in filteredAssignments" :key="a.id" :value="String(a.id)">{{ a.name }}</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="button" class="rounded bg-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-400" @click="clearMemberFilters">クリア</button>
                    </div>
                </div>
                <div class="max-h-96 overflow-y-auto">
                    <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="sticky top-0 bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                    <input type="checkbox" :checked="allChecked" @change="toggleAllMembers" />
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">名前</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">部署</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">担当</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr v-for="member in filteredMembers" :key="member.id"
                                class="cursor-pointer hover:bg-gray-50"
                                :class="{ 'bg-blue-50': selectedMemberIds.includes(member.id) }"
                                @click="toggleMember(member.id)">
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500" @click.stop>
                                    <input type="checkbox" :value="member.id" v-model="selectedMemberIds" />
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-900">{{ member.name }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500">{{ getDepartmentName(member.department_id) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500">
                                    <span :class="getAssignmentBadgeClass(getAssignmentName(member.assignment_id))" class="inline-flex rounded-full px-2 py-1 text-xs font-semibold">
                                        {{ getAssignmentName(member.assignment_id) }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    </div>
                    <div v-if="filteredMembers.length === 0" class="py-8 text-center text-gray-500">該当するメンバーがいません</div>
                </div>
                <div v-if="selectedMemberIds.length > 0" class="mt-4 rounded bg-blue-50 p-3">
                    <div class="text-sm font-medium text-blue-700">{{ selectedMemberIds.length }}人選択中</div>
                    <div class="mt-1 flex flex-wrap gap-1">
                        <span v-for="id in selectedMemberIds" :key="id" class="inline-flex rounded bg-blue-100 px-2 py-1 text-xs text-blue-700">
                            {{ availableMembers.find((m) => m.id === id)?.name }}
                        </span>
                    </div>
                </div>
            </template>
            <template #footer>
                <button type="button" class="mr-3 rounded border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50" @click="closeMemberModal">キャンセル</button>
                <button type="button" class="rounded bg-blue-600 px-4 py-2 text-sm text-white hover:bg-blue-700" @click="confirmMembers">確定（{{ selectedMemberIds.length }}名）</button>
            </template>
        </DialogModal>
    </AppLayout>
</template>
