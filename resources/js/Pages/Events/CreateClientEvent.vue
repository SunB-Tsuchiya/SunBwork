<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps({
    eventItemTypes: { type: Array,  default: () => [] },
    clients:        { type: Array,  default: () => [] },
    projects:       { type: Array,  default: () => [] },
    event:          { type: Object, default: null },
    date:           { type: String, default: '' },
    startHour:      { type: String, default: '09' },
    startMinute:    { type: String, default: '00' },
    endHour:        { type: String, default: '10' },
    endMinute:      { type: String, default: '00' },
});

const isEdit = computed(() => !!props.event);

// ---- 種類 ----
const selectedTypeId = ref(props.event?.event_item_type_id ?? props.eventItemTypes[0]?.id ?? null);
const selectedType = computed(() => props.eventItemTypes.find((t) => t.id === selectedTypeId.value));
const showClientFields = computed(() => ['client_visit', 'customer_visit'].includes(selectedType.value?.slug));
const showDestination   = computed(() => selectedType.value?.slug === 'outing');

// ---- クライアント・プロジェクト ----
const selectedClientId  = ref(props.event?.project_job?.client_id ?? null);
const selectedProjectId = ref(props.event?.project_job_id ?? null);

const filteredProjects = computed(() => {
    if (!selectedClientId.value) return [];
    return props.projects.filter((p) => String(p.client_id) === String(selectedClientId.value));
});

watch(selectedClientId, () => {
    selectedProjectId.value = null;
    autoUpdateTitle();
});
watch(selectedProjectId, () => autoUpdateTitle());
watch(selectedTypeId, () => {
    if (!showClientFields.value) {
        selectedClientId.value = null;
        selectedProjectId.value = null;
    }
    autoUpdateTitle();
});

// ---- タイトル自動生成 ----
const titleManuallyEdited = ref(false);

const autoTitle = computed(() => {
    const typeName    = selectedType.value?.name ?? '';
    const clientName  = props.clients.find((c) => c.id === selectedClientId.value)?.name ?? '';
    const projectName = filteredProjects.value.find((p) => p.id === selectedProjectId.value)?.title ?? '';
    return [typeName, clientName, projectName].filter(Boolean).join('_');
});

function autoUpdateTitle() {
    if (!titleManuallyEdited.value) {
        form.title = autoTitle.value;
    }
}

// 分オプション（5分刻み）
const minuteOptions = ['00', '05', '10', '15', '20', '25', '30', '35', '40', '45', '50', '55'];
function minsOptions(currentVal) {
    if (!currentVal || minuteOptions.includes(currentVal)) return minuteOptions;
    return [...minuteOptions, currentVal].sort((a, b) => Number(a) - Number(b));
}

const form = useForm({
    event_item_type_id:      selectedTypeId.value,
    title:                   props.event?.title ?? autoTitle.value,
    description:             props.event?.description ?? '',
    date:                    props.date,
    startHour:               props.startHour,
    startMinute:             props.startMinute,
    endHour:                 props.endHour,
    endMinute:               props.endMinute,
    project_job_id:          selectedProjectId.value,
    destination:             props.event?.destination ?? '',
    interrupted_event_ids:   [],
    own_interruption_minutes: 0,
});

// form と reactive ref を同期
watch(selectedTypeId,    (v) => { form.event_item_type_id = v; });
watch(selectedProjectId, (v) => { form.project_job_id = v; });
watch(() => form.title,  () => { titleManuallyEdited.value = true; });

// 開始時刻変更で終了時刻を追従
watch(
    () => [form.startHour, form.startMinute],
    ([h, m], [oldH, oldM]) => {
        if (form.endHour === oldH && form.endMinute === oldM) {
            form.endHour   = h;
            form.endMinute = m;
        }
    },
);

const errorMessage = ref('');

function setCurrentTime(target) {
    const now = new Date();
    const jstParts = new Intl.DateTimeFormat('en-US', {
        timeZone: 'Asia/Tokyo',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    }).formatToParts(now);
    const h = String(Number(jstParts.find((p) => p.type === 'hour').value)).padStart(2, '0');
    const m = String(Number(jstParts.find((p) => p.type === 'minute').value)).padStart(2, '0');
    if (target === 'start') {
        form.startHour   = h;
        form.startMinute = m;
    } else {
        form.endHour   = h;
        form.endMinute = m;
    }
}

function sendForm() {
    const routeName = isEdit.value ? 'events.client-event.update' : 'events.client-event.store';
    if (isEdit.value) {
        form.put(route(routeName, { event: props.event.id }), {
            onError: () => { errorMessage.value = '保存に失敗しました。'; },
        });
    } else {
        form.post(route(routeName), {
            onError: () => { errorMessage.value = '保存に失敗しました。'; },
        });
    }
}

const submit = () => {
    errorMessage.value = '';
    const newStart = new Date(`${form.date}T${form.startHour}:${form.startMinute}:00`);
    const newEnd   = new Date(`${form.date}T${form.endHour}:${form.endMinute}:00`);
    if (isNaN(newStart.getTime()) || isNaN(newEnd.getTime())) {
        errorMessage.value = '開始/終了時刻が無効です。'; return;
    }
    if (newEnd <= newStart) {
        errorMessage.value = '終了時刻は開始時刻より後にしてください。'; return;
    }
    if (newEnd - newStart < 15 * 60 * 1000) {
        errorMessage.value = '予定の最小長は15分です。'; return;
    }

    // 重複チェック（既存と同じロジック）
    const evUrl = route('events.index') + `?date=${encodeURIComponent(form.date)}`;
    fetch(evUrl, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
    })
        .then((res) => { if (!res.ok) throw new Error(`HTTP ${res.status}`); return res.json(); })
        .then((events) => {
            const newDuration = newEnd - newStart;
            const excludeId   = props.event?.id ?? null;
            const overlapping = events.filter((ev) => {
                if (excludeId && ev.id === excludeId) return false;
                return newStart < new Date(ev.end) && newEnd > new Date(ev.start);
            });

            if (overlapping.length === 0) {
                form.interrupted_event_ids    = [];
                form.own_interruption_minutes = 0;
                sendForm(); return;
            }

            let interruptedIds = [];
            let ownOverlapMins = 0;
            const lines = overlapping.map((ev) => {
                const evStart    = new Date(ev.start);
                const evEnd      = new Date(ev.end);
                const evDuration = evEnd - evStart;
                const overlapStart = newStart > evStart ? newStart : evStart;
                const overlapEnd   = newEnd   < evEnd   ? newEnd   : evEnd;
                const overlapMins  = Math.max(0, Math.round((overlapEnd - overlapStart) / 60000));
                const sStr = evStart.toLocaleTimeString('ja-JP', { hour: '2-digit', minute: '2-digit' });
                const eStr = evEnd.toLocaleTimeString('ja-JP',   { hour: '2-digit', minute: '2-digit' });
                if (newDuration >= evDuration) {
                    ownOverlapMins += overlapMins;
                    return `「${ev.title}」(${sStr}〜${eStr}) → ${overlapMins}分間重複（今回の予定から差し引き）`;
                } else {
                    interruptedIds.push(ev.id);
                    return `「${ev.title}」(${sStr}〜${eStr}) → ${overlapMins}分間重複（既存の予定から差し引き）`;
                }
            });

            let msg = '以下の予定と時間が重複しています。登録しますか？\n\n';
            msg += lines.join('\n');
            msg += '\n\n【OK】を押すと、時間の長い方の予定から重複時間が差し引かれます。';
            if (!confirm(msg)) return;

            form.interrupted_event_ids    = interruptedIds;
            form.own_interruption_minutes = ownOverlapMins;
            sendForm();
        })
        .catch(() => {
            form.interrupted_event_ids    = [];
            form.own_interruption_minutes = 0;
            sendForm();
        });
};
</script>

<template>
    <AppLayout :title="isEdit ? '案件打合せ・外出 編集' : '案件打合せ・外出'">
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('calendar.index')" class="rounded bg-gray-100 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-200">
                    ← 戻る
                </Link>
                <h2 class="text-xl font-semibold text-gray-800">{{ isEdit ? '案件打合せ・外出 編集' : '案件打合せ・外出' }}</h2>
            </div>
        </template>

        <div class="mx-auto max-w-3xl rounded bg-white p-6 shadow">
            <form @submit.prevent="submit" class="space-y-5">

                <div v-if="errorMessage" class="rounded border-l-4 border-red-500 bg-red-50 p-3 text-red-700 text-sm">
                    {{ errorMessage }}
                </div>

                <!-- 種類 -->
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">種類 <span class="text-red-500">*</span></label>
                    <div class="flex flex-wrap gap-3">
                        <label
                            v-for="t in eventItemTypes"
                            :key="t.id"
                            class="flex items-center gap-1.5 cursor-pointer"
                        >
                            <input type="radio" :value="t.id" v-model="selectedTypeId" class="accent-emerald-600" />
                            <span class="text-sm">{{ t.name }}</span>
                        </label>
                    </div>
                </div>

                <!-- クライアント（来社応対・顧客訪問のみ） -->
                <template v-if="showClientFields">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">クライアント</label>
                        <select v-model="selectedClientId" class="w-full rounded border p-2 text-sm">
                            <option :value="null">— 未選択 —</option>
                            <option v-for="c in clients" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>

                    <div v-if="selectedClientId">
                        <label class="mb-1 block text-sm font-medium text-gray-700">プロジェクト</label>
                        <select v-model="selectedProjectId" class="w-full rounded border p-2 text-sm">
                            <option :value="null">— 未選択 —</option>
                            <option v-for="p in filteredProjects" :key="p.id" :value="p.id">{{ p.title }}</option>
                        </select>
                    </div>
                </template>

                <!-- タイトル -->
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">タイトル <span class="text-red-500">*</span></label>
                    <input
                        v-model="form.title"
                        type="text"
                        class="w-full rounded border p-2 text-sm"
                        required
                        placeholder="自動生成されます（手動で変更可）"
                    />
                    <p v-if="form.errors.title" class="mt-1 text-xs text-red-500">{{ form.errors.title }}</p>
                </div>

                <!-- 外出先（外出のみ） -->
                <div v-if="showDestination">
                    <label class="mb-1 block text-sm font-medium text-gray-700">外出先</label>
                    <input v-model="form.destination" type="text" class="w-full rounded border p-2 text-sm" placeholder="外出先を入力" />
                </div>

                <!-- 概要 -->
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">概要</label>
                    <textarea v-model="form.description" rows="4" class="w-full rounded border p-2 text-sm"></textarea>
                </div>

                <!-- 作業日 -->
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">作業日 <span class="text-red-500">*</span></label>
                    <div class="mt-1">
                        <input v-model="form.date" type="date" class="rounded border px-3 py-2" required />
                    </div>
                </div>

                <!-- 時間 -->
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">時間</label>
                    <div class="mt-1 flex items-end gap-4">
                        <div class="flex flex-col">
                            <label class="text-xs text-gray-600">開始</label>
                            <div class="flex items-center gap-2">
                                <select v-model="form.startHour" class="w-20 rounded border px-3 py-2">
                                    <option v-for="h in Array.from({length:24},(_,i)=>String(i).padStart(2,'0'))" :key="h" :value="h">{{ h }}</option>
                                </select>
                                <select v-model="form.startMinute" class="w-20 rounded border px-3 py-2">
                                    <option v-for="m in minsOptions(form.startMinute)" :key="m" :value="m">{{ m }}</option>
                                </select>
                                <button type="button" @click="setCurrentTime('start')" class="rounded border border-gray-300 bg-gray-50 px-2 py-1 text-xs text-gray-600 hover:bg-gray-100">現在時刻</button>
                            </div>
                        </div>
                        <div class="flex flex-col">
                            <label class="text-xs text-gray-600">終了</label>
                            <div class="flex items-center gap-2">
                                <select v-model="form.endHour" class="w-20 rounded border px-3 py-2">
                                    <option v-for="h in Array.from({length:24},(_,i)=>String(i).padStart(2,'0'))" :key="h" :value="h">{{ h }}</option>
                                </select>
                                <select v-model="form.endMinute" class="w-20 rounded border px-3 py-2">
                                    <option v-for="m in minsOptions(form.endMinute)" :key="m" :value="m">{{ m }}</option>
                                </select>
                                <button type="button" @click="setCurrentTime('end')" class="rounded border border-gray-300 bg-gray-50 px-2 py-1 text-xs text-gray-600 hover:bg-gray-100">現在時刻</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 送信ボタン -->
                <div class="flex gap-3 pt-2">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded bg-emerald-600 px-5 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50"
                    >
                        {{ form.processing ? '保存中...' : (isEdit ? '更新する' : '登録する') }}
                    </button>
                    <Link :href="route('calendar.index')" class="rounded border px-5 py-2 text-sm text-gray-600 hover:bg-gray-50">
                        キャンセル
                    </Link>
                </div>

            </form>
        </div>
    </AppLayout>
</template>
