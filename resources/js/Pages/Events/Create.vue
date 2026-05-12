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
    date:                  props.date || '',
    event_item_type_id:    props.eventItemTypes[0]?.id ?? null,
    title:                 '',
    description:           '',
    startHour:             props.startHour   ?? '09',
    startMinute:           props.startMinute ?? '00',
    endHour:               props.endHour     ?? '10',
    endMinute:             props.endMinute   ?? '00',
    files:                 [],
    interrupted_event_ids:    [],
    own_interruption_minutes: 0,
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

// 5分刈みの分リスト（現在値が5分刈み外の場合は動的に追加）
const baseMins = ['00', '05', '10', '15', '20', '25', '30', '35', '40', '45', '50', '55'];
function minsOptions(currentVal) {
    if (!currentVal || baseMins.includes(currentVal)) return baseMins;
    return [...baseMins, currentVal].sort((a, b) => Number(a) - Number(b));
}

// 現在時刻（JST）を分丸めなしでセット
function setCurrentTime(target) {
    const now = new Date();
    const jstParts = new Intl.DateTimeFormat('en-US', {
        timeZone: 'Asia/Tokyo',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    }).formatToParts(now);
    const h = jstParts.find((p) => p.type === 'hour').value.padStart(2, '0');
    const m = jstParts.find((p) => p.type === 'minute').value.padStart(2, '0');
    if (target === 'start') {
        form.startHour = h;
        form.startMinute = m;
    } else {
        form.endHour = h;
        form.endMinute = m;
    }
}

const submit = () => {    errorMessage.value = '';
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

    const newDuration = newEnd - newStart; // 新しいイベントの長さ（ミリ秒）

    const evUrl = route('events.index') + `?date=${encodeURIComponent(form.date)}`;
    fetch(evUrl, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
    })
        .then((res) => {
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            return res.json();
        })
        .then((events) => {
            // 重複する既存イベントを抽出
            const overlapping = events.filter((ev) => {
                const evStart = new Date(ev.start);
                const evEnd = new Date(ev.end);
                return newStart < evEnd && newEnd > evStart;
            });

            if (overlapping.length === 0) {
                form.interrupted_event_ids = [];
                form.own_interruption_minutes = 0;
                sendForm();
                return;
            }

            let confirmMsg = '';
            let interruptedIds = [];   // 既存イベントが長い（既存から差し引く）
            let ownOverlapMins = 0;    // 新しいイベントが長い（自分から差し引く）

            const lines = overlapping.map((ev) => {
                const evStart = new Date(ev.start);
                const evEnd = new Date(ev.end);
                const evDuration = evEnd - evStart;
                const overlapStart = newStart > evStart ? newStart : evStart;
                const overlapEnd = newEnd < evEnd ? newEnd : evEnd;
                const overlapMins = Math.max(0, Math.round((overlapEnd - overlapStart) / 60000));
                const startStr = evStart.toLocaleTimeString('ja-JP', { hour: '2-digit', minute: '2-digit' });
                const endStr = evEnd.toLocaleTimeString('ja-JP', { hour: '2-digit', minute: '2-digit' });

                if (newDuration >= evDuration) {
                    // 新しいイベントが長い（または同じ） → 新しいイベントが「差し込まれた側」
                    ownOverlapMins += overlapMins;
                    return `「${ev.title}」(${startStr}〜${endStr}) → ${overlapMins}分間重複（今回の予定から差し引き）`;
                } else {
                    // 既存イベントが長い → 既存イベントが「差し込まれた側」
                    interruptedIds.push(ev.id);
                    return `「${ev.title}」(${startStr}〜${endStr}) → ${overlapMins}分間重複（既存の予定から差し引き）`;
                }
            });

            confirmMsg = '以下の予定と時間が重複しています。登録しますか？\n\n';
            confirmMsg += lines.join('\n');
            confirmMsg += '\n\n【OK】を押すと、時間の長い方の予定から重複時間が差し引かれます。';

            if (!confirm(confirmMsg)) return;

            form.interrupted_event_ids = interruptedIds;
            form.own_interruption_minutes = ownOverlapMins;
            sendForm();
        })
        .catch((err) => {
            console.error('Failed to fetch events for overlap check', err);
            form.interrupted_event_ids = [];
            form.own_interruption_minutes = 0;
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
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="returnTo && returnTo !== '' ? returnTo : route('calendar.index')"
                    class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 whitespace-nowrap hover:bg-gray-300"
                >← 戻る</Link>
                <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">予定作成</h2>
            </div>
        </template>

        <div class="mx-auto max-w-2xl rounded bg-white px-4 py-6 sm:p-6 shadow">
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
                    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4 sm:gap-8">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">開始時刻</label>
                            <div class="flex items-center gap-2">
                                <select v-model="form.startHour" class="w-20 rounded border p-1">
                                    <option v-for="h in 24" :key="h" :value="String(h - 1).padStart(2, '0')">
                                        {{ String(h - 1).padStart(2, '0') }}
                                    </option>
                                </select>
                                <select v-model="form.startMinute" class="w-20 rounded border p-1">
                                    <option v-for="m in minsOptions(form.startMinute)" :key="m" :value="m">{{ m }}</option>
                                </select>
                                <button type="button" @click="setCurrentTime('start')" class="rounded border border-gray-300 bg-gray-50 px-2 py-1 text-xs text-gray-600 hover:bg-gray-100">現在時刻</button>
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">終了時刻</label>
                            <div class="flex items-center gap-2">
                                <select v-model="form.endHour" class="w-20 rounded border p-1">
                                    <option v-for="h in 24" :key="h" :value="String(h - 1).padStart(2, '0')">
                                        {{ String(h - 1).padStart(2, '0') }}
                                    </option>
                                </select>
                                <select v-model="form.endMinute" class="w-20 rounded border p-1">
                                    <option v-for="m in minsOptions(form.endMinute)" :key="m" :value="m">{{ m }}</option>
                                </select>
                                <button type="button" @click="setCurrentTime('end')" class="rounded border border-gray-300 bg-gray-50 px-2 py-1 text-xs text-gray-600 hover:bg-gray-100">現在時刻</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <Link :href="returnTo && returnTo !== '' ? returnTo : route('calendar.index')"
                        class="rounded bg-gray-200 px-4 py-2 text-sm font-medium text-gray-700 whitespace-nowrap hover:bg-gray-300"
                    >キャンセル</Link>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="flex items-center gap-2 rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                    >
                        <template v-if="form.processing">保存中…</template>
                        <template v-else>保存</template>
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

<style scoped>
/* minimal spacing tweaks */
</style>
