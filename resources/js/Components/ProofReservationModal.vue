<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    initialTitle: { type: String, default: '' },
    projectJobId: { type: Number, default: null },
});

const emit = defineEmits(['close']);
const page = usePage();
const hours = Array.from({ length: 24 }, (_, index) => index);
const minutes = [0, 15, 30, 45];
const submitting = ref(false);
const viewMode = ref('form');
const sentReservations = ref([]);
const loadingReservations = ref(false);
const listError = ref('');

const statusLabel = {
    reserved: '予約受付',
    in_progress: '校正中',
    completed: '完了',
    deleted: '削除',
};

const statusBadge = {
    reserved: 'bg-gray-100 text-gray-700',
    in_progress: 'bg-indigo-100 text-indigo-800',
    completed: 'bg-green-100 text-green-800',
    deleted: 'bg-red-100 text-red-700',
};

const emptyForm = () => ({
    title: '',
    requested_at_mode: 'datetime',
    requested_at_date: '',
    requested_at_hour: 9,
    requested_at_minute: 0,
    requested_at_text: '',
    deadline_mode: 'datetime',
    deadline_date: '',
    deadline_hour: 17,
    deadline_minute: 30,
    deadline_text: '',
    note: '',
});

const form = ref(emptyForm());

function localDateString(date = new Date()) {
    return date.toLocaleDateString('sv-SE', { timeZone: 'Asia/Tokyo' });
}

watch(() => props.show, (show) => {
    if (!show) return;

    const title = props.initialTitle
        ? (props.initialTitle.endsWith('_組版')
            ? props.initialTitle.replace(/_組版$/, '_校正')
            : `${props.initialTitle}_校正`)
        : '';

    form.value = {
        ...emptyForm(),
        title,
        requested_at_date: localDateString(),
        deadline_date: localDateString(),
    };
    viewMode.value = 'form';
    sentReservations.value = [];
    listError.value = '';
});

function toJstIso(prefix) {
    const date = form.value[`${prefix}_date`];
    if (!date) return null;
    const hour = String(form.value[`${prefix}_hour`]).padStart(2, '0');
    const minute = String(form.value[`${prefix}_minute`]).padStart(2, '0');
    return new Date(`${date}T${hour}:${minute}:00+09:00`).toISOString();
}

const canSubmit = computed(() => {
    if (!form.value.title.trim() || !props.projectJobId) return false;
    const requested = form.value.requested_at_mode === 'datetime'
        ? form.value.requested_at_date
        : form.value.requested_at_text.trim();
    const deadline = form.value.deadline_mode === 'datetime'
        ? form.value.deadline_date
        : form.value.deadline_text.trim();
    return Boolean(requested && deadline);
});

const validationErrors = computed(() => {
    const keys = [
        'title',
        'requested_at',
        'requested_at_text',
        'deadline_at',
        'deadline_text',
        'note',
    ];
    return keys.map((key) => page.props.errors?.[key]).filter(Boolean);
});

function reservationPayload(duplicateConfirmed = false) {
    return {
        title: form.value.title,
        requested_at_mode: form.value.requested_at_mode,
        requested_at: form.value.requested_at_mode === 'datetime' ? toJstIso('requested_at') : null,
        requested_at_text: form.value.requested_at_mode === 'text' ? form.value.requested_at_text : null,
        deadline_mode: form.value.deadline_mode,
        deadline_at: form.value.deadline_mode === 'datetime' ? toJstIso('deadline') : null,
        deadline_text: form.value.deadline_mode === 'text' ? form.value.deadline_text : null,
        note: form.value.note,
        duplicate_confirmed: duplicateConfirmed,
    };
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

async function fetchJson(url, options = {}) {
    const response = await fetch(url, {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
            ...(options.headers ?? {}),
        },
        ...options,
    });

    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }

    return response.json();
}

async function showSentReservations() {
    viewMode.value = 'list';
    loadingReservations.value = true;
    listError.value = '';
    try {
        const data = await fetchJson(route('coordinator.proof_reservations.sent', {
            projectJob: props.projectJobId,
        }));
        sentReservations.value = data.reservations ?? [];
    } catch (_) {
        listError.value = '送信予約一覧を取得できませんでした。';
    } finally {
        loadingReservations.value = false;
    }
}

function formatDateTime(value) {
    if (!value) return '—';
    return new Intl.DateTimeFormat('ja-JP', {
        timeZone: 'Asia/Tokyo',
        year: 'numeric',
        month: 'numeric',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    }).format(new Date(value));
}

function reservationDateValue(reservation, prefix) {
    return reservation[`${prefix}_mode`] === 'text'
        ? reservation[`${prefix}_text`] || '—'
        : formatDateTime(reservation[prefix]);
}

async function submit() {
    if (!canSubmit.value || submitting.value) return;
    submitting.value = true;

    let duplicateConfirmed = false;
    try {
        const result = await fetchJson(route('coordinator.proof_reservations.check_duplicate', {
            projectJob: props.projectJobId,
        }), {
            method: 'POST',
            body: JSON.stringify(reservationPayload(false)),
        });

        if (result.has_duplicates) {
            const reasons = result.duplicates
                .map((duplicate) => {
                    const matches = [
                        duplicate.title_match ? '同じタイトル' : null,
                        duplicate.date_match ? '同じ依頼予定日・締め切り日' : null,
                    ].filter(Boolean).join('、');
                    return `・${duplicate.title}（${matches}）`;
                })
                .join('\n');

            duplicateConfirmed = window.confirm(
                `重複している可能性がある校正予約があります。\n\n${reasons}\n\nそれでも送信しますか？`,
            );
            if (!duplicateConfirmed) {
                submitting.value = false;
                return;
            }
        }
    } catch (_) {
        window.alert('重複確認に失敗しました。時間をおいて再度お試しください。');
        submitting.value = false;
        return;
    }

    router.post(route('coordinator.proof_reservations.store', {
        projectJob: props.projectJobId,
    }), reservationPayload(duplicateConfirmed), {
        preserveScroll: true,
        onSuccess: () => emit('close'),
        onFinish: () => {
            submitting.value = false;
        },
    });
}
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40" @click="emit('close')" />

            <div class="relative z-10 max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-lg bg-white p-6 shadow-xl">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h3 class="text-lg font-semibold text-gray-800">
                        {{ viewMode === 'form' ? '校正予約を送る' : '送信予約一覧' }}
                    </h3>
                    <button
                        type="button"
                        class="rounded border border-pink-300 px-3 py-1.5 text-sm font-medium text-pink-700 hover:bg-pink-50"
                        @click="viewMode === 'form' ? showSentReservations() : viewMode = 'form'"
                    >{{ viewMode === 'form' ? '送信予約一覧' : '予約入力に戻る' }}</button>
                </div>

                <div v-if="viewMode === 'form'" class="space-y-5">
                    <div v-if="validationErrors.length" class="rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                        <p v-for="error in validationErrors" :key="error">{{ error }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">ジョブ名</label>
                        <input v-model="form.title" type="text" class="mt-1 w-full rounded border-gray-300 text-sm" />
                    </div>

                    <div v-for="field in [
                        { prefix: 'requested_at', label: '依頼予定' },
                        { prefix: 'deadline', label: '締め切り' },
                    ]" :key="field.prefix">
                        <div class="flex items-center justify-between gap-3">
                            <label class="block text-sm font-medium text-gray-700">
                                {{ field.label }} <span class="text-red-500">*</span>
                            </label>
                            <button
                                type="button"
                                class="rounded border border-pink-300 px-2.5 py-1 text-xs font-medium text-pink-700 hover:bg-pink-50"
                                @click="form[`${field.prefix}_mode`] = form[`${field.prefix}_mode`] === 'datetime' ? 'text' : 'datetime'"
                            >
                                {{ form[`${field.prefix}_mode`] === 'datetime' ? 'テキスト' : '日時入力' }}
                            </button>
                        </div>

                        <div v-if="form[`${field.prefix}_mode`] === 'datetime'" class="mt-1 flex flex-wrap items-center gap-2">
                            <input
                                v-model="form[`${field.prefix}_date`]"
                                type="date"
                                class="min-w-44 flex-1 rounded border-gray-300 text-sm"
                            />
                            <select v-model="form[`${field.prefix}_hour`]" class="rounded border-gray-300 text-sm">
                                <option v-for="hour in hours" :key="hour" :value="hour">{{ String(hour).padStart(2, '0') }}</option>
                            </select>
                            <span class="text-sm text-gray-500">時</span>
                            <select v-model="form[`${field.prefix}_minute`]" class="rounded border-gray-300 text-sm">
                                <option v-for="minute in minutes" :key="minute" :value="minute">{{ String(minute).padStart(2, '0') }}</option>
                            </select>
                            <span class="text-sm text-gray-500">分</span>
                        </div>
                        <input
                            v-else
                            v-model="form[`${field.prefix}_text`]"
                            type="text"
                            class="mt-1 w-full rounded border-gray-300 text-sm"
                            :placeholder="`${field.label}が未確定の場合の説明（例：先方確認後）`"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">備考（任意）</label>
                        <textarea v-model="form.note" rows="3" class="mt-1 w-full rounded border-gray-300 text-sm" />
                    </div>
                </div>

                <div v-else>
                    <p v-if="loadingReservations" class="py-8 text-center text-sm text-gray-500">読み込み中...</p>
                    <p v-else-if="listError" class="rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ listError }}</p>
                    <p v-else-if="sentReservations.length === 0" class="py-8 text-center text-sm text-gray-500">この案件の送信予約はありません。</p>
                    <div v-else class="max-h-[55vh] overflow-auto rounded border border-gray-200">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="sticky top-0 bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">タイトル</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">依頼予定</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">締め切り</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">ステータス</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                <tr v-for="reservation in sentReservations" :key="reservation.id">
                                    <td class="px-3 py-2 text-sm">
                                        <p class="font-medium text-gray-900">{{ reservation.title }}</p>
                                        <p class="text-xs text-gray-500">{{ reservation.requester_name ?? '—' }}</p>
                                    </td>
                                    <td class="px-3 py-2 text-sm text-gray-700">{{ reservationDateValue(reservation, 'requested_at') }}</td>
                                    <td class="px-3 py-2 text-sm text-gray-700">{{ reservationDateValue(reservation, 'deadline_at') }}</td>
                                    <td class="whitespace-nowrap px-3 py-2 text-sm">
                                        <span
                                            class="rounded-full px-2 py-1 text-xs font-medium"
                                            :class="statusBadge[reservation.status] ?? statusBadge.reserved"
                                        >{{ statusLabel[reservation.status] ?? reservation.status }}</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-if="viewMode === 'form'" class="mt-6 flex justify-end gap-3">
                    <button
                        type="button"
                        class="rounded border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        @click="emit('close')"
                    >キャンセル</button>
                    <button
                        type="button"
                        :disabled="!canSubmit || submitting"
                        class="rounded bg-pink-600 px-4 py-2 text-sm font-medium text-white hover:bg-pink-700 disabled:cursor-not-allowed disabled:opacity-50"
                        @click="submit"
                    >{{ submitting ? '送信中...' : '校正予約を送る' }}</button>
                </div>
                <div v-else class="mt-6 flex justify-end">
                    <button
                        type="button"
                        class="rounded border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        @click="emit('close')"
                    >閉じる</button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
