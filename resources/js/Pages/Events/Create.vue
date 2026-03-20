<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { getCurrentInstance, nextTick, onMounted, ref, watch } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps({
    date:           { type: String, default: '' },
    startHour:      { type: String, default: null },
    startMinute:    { type: String, default: null },
    endHour:        { type: String, default: null },
    endMinute:      { type: String, default: null },
    eventItemTypes: { type: Array,  default: () => [] },
});

function formatJstDate(dateStr) {
    if (!dateStr) return '';
    try {
        const d = new Date(dateStr);
        d.setHours(d.getHours() + 9);
        const yyyy = d.getFullYear();
        const mm = String(d.getMonth() + 1).padStart(2, '0');
        const dd = String(d.getDate()).padStart(2, '0');
        return `${yyyy}-${mm}-${dd}`;
    } catch (e) {
        return String(dateStr).split('T')[0];
    }
}

const form = useForm({
    date:                props.date || '',
    event_item_type_id:  props.eventItemTypes[0]?.id ?? null,
    title:               '',
    description:         '',
    startHour:           props.startHour   ?? '09',
    startMinute:         props.startMinute ?? '00',
    endHour:             props.endHour     ?? '10',
    endMinute:           props.endMinute   ?? '00',
    files:               [],
});

let returnTo = '';
try {
    const paramsRt = new URLSearchParams(window.location.search);
    const rt = paramsRt.get('return_to');
    if (rt && rt !== 'undefined' && rt !== 'null') {
        try {
            returnTo = decodeURIComponent(String(rt));
        } catch (e) {
            returnTo = String(rt);
        }
    }
} catch (e) {
    returnTo = '';
}

const errorMessage = ref('');

onMounted(async () => {
    try {
        await nextTick();
    } catch (e) {
        console.error('[Create.vue] onMounted error', e);
    }
});

function handleSuccessRedirect() {
    errorMessage.value = '';
    if (returnTo && returnTo !== '') {
        try {
            window.location.href = returnTo;
            return;
        } catch (e) {
            // fallback to Inertia visit below
        }
        try {
            const vm = getCurrentInstance();
            vm?.proxy?.$inertia?.visit(returnTo);
            return;
        } catch (e2) {
            // fallback to window.location if Inertia fails
        }
        window.location.href = returnTo;
        return;
    }
    const target = route('calendar.index');
    const vm = getCurrentInstance();
    try {
        vm?.proxy?.$inertia?.visit(target);
    } catch (e) {
        window.location.href = target;
    }
}

function sendForm() {
    try {
        form.post(route('events.store'), {
            forceFormData: true,
            onSuccess: handleSuccessRedirect,
            onError: (errors) => {
                console.error('events.store failed', errors);
                errorMessage.value = '予定の保存に失敗しました。後でもう一度お試しください。';
            },
        });
    } catch (e) {
        console.error('[Create.vue] form.post threw', e);
        errorMessage.value = '予定の保存に失敗しました（クライアントエラー）。';
    }
}

const submit = () => {
    errorMessage.value = '';
    const newStart = new Date(`${form.date}T${form.startHour}:${form.startMinute}:00`);
    const newEnd = new Date(`${form.date}T${form.endHour}:${form.endMinute}:00`);
    if (isNaN(newStart.getTime()) || isNaN(newEnd.getTime())) {
        errorMessage.value = '開始/終了時刻が無効です。';
        return;
    }
    if (newEnd <= newStart) {
        errorMessage.value = '終了時刻は開始時刻より後にしてください。';
        return;
    }
    const minMs = 15 * 60 * 1000;
    if (newEnd - newStart < minMs) {
        errorMessage.value = '予定の最小長は15分です。';
        return;
    }

    const evUrl = `/events?date=${encodeURIComponent(form.date)}`;
    fetch(evUrl, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
    })
        .then((res) => {
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            return res.json();
        })
        .then((events) => {
            const overlap = events.some((ev) => {
                const evStart = new Date(ev.start);
                const evEnd = new Date(ev.end);
                return newStart < evEnd && newEnd > evStart;
            });
            if (overlap) {
                if (!confirm('同じ時間に予定があります。登録しますか？')) return;
            }
            sendForm();
        })
        .catch((err) => {
            console.error('Failed to fetch events for overlap check', err);
            sendForm();
        });
};

watch(
    () => [form.startHour, form.startMinute],
    ([h, m], [oldH, oldM]) => {
        if (form.endHour === oldH && form.endMinute === oldM) {
            form.endHour = h;
            form.endMinute = m;
        }
    },
);
</script>

<template>
    <AppLayout title="イベント作成">
        <div class="mx-auto max-w-3xl rounded bg-white p-6 shadow">
            <div class="mb-6 border-b pb-4">
                <h1 class="text-2xl font-bold">イベント作成</h1>
                <p class="text-sm text-gray-600">個人予定を登録します。{{ formatJstDate(form.date) ? `（${formatJstDate(form.date)}）` : '' }}</p>
            </div>
            <form @submit.prevent="submit">
                <div v-if="errorMessage" class="mb-4 rounded border-l-4 border-red-500 bg-red-50 p-3 text-red-700">
                    {{ errorMessage }}
                </div>

                <div class="mb-4">
                    <label class="mb-1 block text-sm font-medium text-gray-700">種類</label>
                    <select v-model="form.event_item_type_id" class="w-full rounded border p-2">
                        <option :value="null">— 未選択 —</option>
                        <option v-for="t in eventItemTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="mb-1 block text-sm font-medium text-gray-700">タイトル</label>
                    <input v-model="form.title" type="text" class="w-full rounded border p-2" required />
                </div>

                <div class="mb-4">
                    <label class="mb-1 block text-sm font-medium text-gray-700">詳細</label>
                    <textarea v-model="form.description" rows="8" class="w-full rounded border bg-white p-2"></textarea>
                </div>

                <div class="mb-4">
                    <div class="flex items-center gap-8">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">開始時刻</label>
                            <div class="flex gap-2">
                                <select v-model="form.startHour" class="w-20 rounded border p-1">
                                    <option v-for="h in 24" :key="h" :value="String(h - 1).padStart(2, '0')">
                                        {{ String(h - 1).padStart(2, '0') }}
                                    </option>
                                </select>
                                <select v-model="form.startMinute" class="w-20 rounded border p-1">
                                    <option v-for="m in [0, 15, 30, 45]" :key="m" :value="String(m).padStart(2, '0')">
                                        {{ String(m).padStart(2, '0') }}
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">終了時刻</label>
                            <div class="flex gap-2">
                                <select v-model="form.endHour" class="w-20 rounded border p-1">
                                    <option v-for="h in 24" :key="h" :value="String(h - 1).padStart(2, '0')">
                                        {{ String(h - 1).padStart(2, '0') }}
                                    </option>
                                </select>
                                <select v-model="form.endMinute" class="w-20 rounded border p-1">
                                    <option v-for="m in [0, 15, 30, 45]" :key="m" :value="String(m).padStart(2, '0')">
                                        {{ String(m).padStart(2, '0') }}
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex space-x-4">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="flex items-center gap-2 rounded bg-blue-600 px-4 py-2 text-white disabled:opacity-50"
                    >
                        <template v-if="form.processing"> 保存中… </template>
                        <template v-else>保存</template>
                    </button>
                    <Link :href="returnTo && returnTo !== '' ? returnTo : route('calendar.index')" class="rounded bg-gray-200 px-4 py-2 text-gray-700"
                        >キャンセル</Link
                    >
                </div>
            </form>
        </div>
    </AppLayout>
</template>

<style scoped>
/* minimal spacing tweaks */
</style>
