<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import ProofCoordinatorNavigationTabs from '@/Components/Tabs/ProofCoordinatorNavigationTabs.vue';
import AssignmentForm from '@/Pages/Coordinator/ProjectJobs/JobAssign/AssignmentForm.vue';
import ProofTimelinePickerModal from '@/Components/ProofTimelinePickerModal.vue';
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';

function fmtDeadline(isoStr) {
    if (!isoStr) return '—';
    const fmt = new Intl.DateTimeFormat('ja-JP', {
        timeZone: 'Asia/Tokyo',
        year: 'numeric', month: 'numeric', day: 'numeric',
        hour: '2-digit', minute: '2-digit', hour12: false,
    });
    const p = Object.fromEntries(fmt.formatToParts(new Date(isoStr)).map(({ type, value }) => [type, value]));
    return `${p.year}年${p.month}月${p.day}日 ${p.hour}時${p.minute}分`;
}

const props = defineProps({
    proofRequest:        { type: Object, required: true },
    projectJob:          { type: Object, default: null },
    members:             { type: Array,  default: () => [] },
    assignments:         { type: Array,  default: () => [] },
    types:               { type: Array,  default: () => [] },
    sizes:               { type: Array,  default: () => [] },
    stages:              { type: Array,  default: () => [] },
    statuses:            { type: Array,  default: () => [] },
    difficulties:        { type: Array,  default: () => [] },
    companies:           { type: Array,  default: () => [] },
    user_role:           { type: String, default: '' },
    user_company_id:     { type: [Number, String], default: null },
    user_department_id:  { type: [Number, String], default: null },
    workEvents:          { type: Array,  default: () => [] },
    userHasSetSchedule:  { type: Boolean, default: false },
});

const updateUrl = route('proof_coordinator.assignments.assignment_update', { proofRequest: props.proofRequest.id });

// 作業時間のローカルコピー（インライン編集用）
const localEvents = ref(props.workEvents.map(e => ({ ...e, saving: false, saved: false, error: null })));

const HOURS   = Array.from({ length: 24 }, (_, i) => String(i).padStart(2, '0'));
const MINUTES = ['00', '15', '30', '45'];

// タイムラインピッカーモーダル用 state / handlers
const assignmentFormRef = ref(null);
const showPickerModal   = ref(false);
const pickerInitialUser = ref(null);

function openPicker() {
    const uid = assignmentFormRef.value?.getSelectedUserId() ?? null;
    pickerInitialUser.value = uid ? Number(uid) : null;
    showPickerModal.value = true;
}

function onPickerUserSelected(userId) {
    assignmentFormRef.value?.setSelectedUser(userId);
}

function onPickerConfirmed({ newSlots, updatedSlots }) {
    // 新規スロットをフォームに追加
    if (newSlots.length > 0 && !assignmentFormRef.value?.getSelectedUserId()) {
        assignmentFormRef.value?.setSelectedUser(newSlots[0].userId);
    }
    for (const slot of newSlots) {
        assignmentFormRef.value?.addExternalWorkSlot(slot);
    }

    // 登録済みイベントの更新を localEvents に反映して保存
    for (const updated of updatedSlots) {
        const ev = localEvents.value.find(e => e.id === updated.id);
        if (ev) {
            ev.date         = updated.date;
            ev.start_hour   = String(updated.startHour).padStart(2, '0');
            ev.start_minute = String(updated.startMinute).padStart(2, '0');
            ev.end_hour     = String(updated.endHour).padStart(2, '0');
            ev.end_minute   = String(updated.endMinute).padStart(2, '0');
            saveEvent(ev);
        }
    }

    showPickerModal.value = false;
}

async function saveEvent(ev) {
    ev.saving = true;
    ev.error  = null;
    try {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const res  = await fetch(route('events.update_from_calendar', { id: ev.id }), {
            method:      'PUT',
            credentials: 'same-origin',
            headers: {
                'Content-Type':     'application/json',
                'X-CSRF-TOKEN':     csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                date:        ev.date,
                startHour:   String(ev.start_hour).padStart(2, '0'),
                startMinute: String(ev.start_minute).padStart(2, '0'),
                endHour:     String(ev.end_hour).padStart(2, '0'),
                endMinute:   String(ev.end_minute).padStart(2, '0'),
            }),
        });
        if (res.ok) {
            ev.saved = true;
            setTimeout(() => { ev.saved = false; }, 2000);
        } else {
            ev.error = '保存に失敗しました';
        }
    } catch (_) {
        ev.error = '保存に失敗しました';
    } finally {
        ev.saving = false;
    }
}

function formatDuration(ev) {
    const sh = Number(ev.start_hour) * 60 + Number(ev.start_minute);
    const eh = Number(ev.end_hour)   * 60 + Number(ev.end_minute);
    const mins = Math.max(0, eh - sh);
    const h = Math.floor(mins / 60);
    const m = mins % 60;
    if (h > 0 && m > 0) return `${h}時間${m}分`;
    if (h > 0) return `${h}時間`;
    return `${m}分`;
}

async function deleteEvent(ev) {
    if (!confirm('この作業時間を削除しますか？\n（紐づく校正スケジュールも削除されます）')) return;
    ev.saving = true;
    ev.error  = null;
    try {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const res  = await fetch(
            route('proof_coordinator.assignments.event_destroy', {
                proofRequest: props.proofRequest.id,
                event:        ev.id,
            }),
            {
                method:      'DELETE',
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN':     csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            }
        );
        if (res.ok) {
            // ローカルリストから除去
            const idx = localEvents.value.findIndex(e => e.id === ev.id);
            if (idx !== -1) localEvents.value.splice(idx, 1);
        } else {
            ev.error = '削除に失敗しました';
        }
    } catch (_) {
        ev.error = '削除に失敗しました';
    } finally {
        ev.saving = false;
    }
}
</script>

<template>
    <AppLayout title="校正ジョブ編集">
        <template #header>
            <div class="flex items-center gap-3">
                <Link
                    :href="route('proof_coordinator.assignments.show', { proofRequest: proofRequest.id })"
                    class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300"
                >← 詳細に戻る</Link>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">校正ジョブ 編集</h2>
            </div>
        </template>

        <template #tabs>
            <ProofCoordinatorNavigationTabs active="assignments" />
        </template>

        <div class="mx-auto max-w-3xl space-y-4">

            <!-- ユーザーがスケジュール設定済み警告 -->
            <div v-if="userHasSetSchedule"
                 class="rounded border border-yellow-300 bg-yellow-50 p-4 text-sm text-yellow-800">
                <p class="font-semibold">⚠️ 校正担当者がすでに予定をセットしています。</p>
                <p class="mt-1">変更を保存すると、担当者の作業時間スロットも更新されます。担当者に変更を伝えてください。</p>
            </div>

            <!-- 校正依頼情報（読み取り専用） -->
            <div class="rounded border border-pink-100 bg-pink-50 p-4 text-sm">
                <p class="mb-1 font-semibold text-pink-700">校正依頼情報</p>
                <dl class="grid grid-cols-2 gap-x-6 gap-y-1 text-gray-700 sm:grid-cols-4">
                    <div>
                        <dt class="text-xs font-medium text-gray-500">依頼者</dt>
                        <dd>{{ proofRequest.requester?.name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500">関連案件</dt>
                        <dd>{{ proofRequest.project_job?.title ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500">校正締め切り</dt>
                        <dd :class="proofRequest.deadline && new Date(proofRequest.deadline) < new Date() ? 'font-bold text-red-600' : ''">
                            {{ fmtDeadline(proofRequest.deadline) }}
                        </dd>
                    </div>
                    <div v-if="proofRequest.note">
                        <dt class="text-xs font-medium text-gray-500">備考</dt>
                        <dd class="truncate">{{ proofRequest.note }}</dd>
                    </div>
                </dl>
            </div>

            <!-- 作業時間 編集セクション -->
            <div v-if="localEvents.length > 0" class="rounded bg-white p-6 shadow">
                <h3 class="mb-4 text-sm font-semibold text-gray-700">登録済み作業時間</h3>
                <div class="space-y-3">
                    <div
                        v-for="ev in localEvents"
                        :key="ev.id"
                        class="rounded border border-gray-200 p-4"
                    >
                        <div class="flex flex-wrap items-end gap-4">
                            <!-- 日付 -->
                            <div>
                                <label class="block text-xs text-gray-500">日付</label>
                                <input
                                    v-model="ev.date"
                                    type="date"
                                    class="mt-1 rounded border-gray-300 text-sm shadow-sm focus:border-pink-500 focus:ring-pink-500"
                                />
                            </div>
                            <!-- 開始時刻 -->
                            <div class="flex items-end gap-1">
                                <div>
                                    <label class="block text-xs text-gray-500">開始</label>
                                    <div class="mt-1 flex items-center gap-1">
                                        <select v-model="ev.start_hour"   class="rounded border-gray-300 text-sm">
                                            <option v-for="h in HOURS"   :key="h" :value="h">{{ h }}</option>
                                        </select>
                                        <span class="text-gray-400">:</span>
                                        <select v-model="ev.start_minute" class="rounded border-gray-300 text-sm">
                                            <option v-for="m in MINUTES" :key="m" :value="m">{{ m }}</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <!-- 終了時刻 -->
                            <div class="flex items-end gap-1">
                                <div>
                                    <label class="block text-xs text-gray-500">終了</label>
                                    <div class="mt-1 flex items-center gap-1">
                                        <select v-model="ev.end_hour"   class="rounded border-gray-300 text-sm">
                                            <option v-for="h in HOURS"   :key="h" :value="h">{{ h }}</option>
                                        </select>
                                        <span class="text-gray-400">:</span>
                                        <select v-model="ev.end_minute" class="rounded border-gray-300 text-sm">
                                            <option v-for="m in MINUTES" :key="m" :value="m">{{ m }}</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <!-- 時間計算表示 -->
                            <div class="text-sm text-gray-500">
                                {{ formatDuration(ev) }}
                            </div>
                            <!-- 保存・削除ボタン -->
                            <div class="ml-auto flex items-center gap-2">
                                <span v-if="ev.error"  class="text-xs text-red-600">{{ ev.error }}</span>
                                <span v-if="ev.saved"  class="text-xs text-green-600">保存しました</span>
                                <button
                                    type="button"
                                    @click="saveEvent(ev)"
                                    :disabled="ev.saving"
                                    class="rounded bg-pink-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-pink-700 disabled:opacity-50"
                                >
                                    {{ ev.saving ? '保存中…' : '保存' }}
                                </button>
                                <button
                                    type="button"
                                    @click="deleteEvent(ev)"
                                    :disabled="ev.saving"
                                    class="rounded bg-red-100 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-200 disabled:opacity-50"
                                >
                                    削除
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div v-else class="rounded border border-dashed border-gray-300 px-5 py-4 text-center text-sm text-gray-400">
                登録済みの作業時間はありません。
            </div>

            <!-- ジョブ割り当てフォーム -->
            <div class="rounded bg-white p-6 shadow">
                <p class="mb-4 text-sm text-gray-500">
                    ※ 担当者・作業詳細を編集してください。
                </p>

                <AssignmentForm
                    ref="assignmentFormRef"
                    mode="coordinator"
                    :projectJob="projectJob"
                    :members="members"
                    :assignments="assignments"
                    :editMode="true"
                    :hide-status="true"
                    :updateOverrideUrl="updateUrl"
                    :saveOnly="true"
                    :show-work-slots="true"
                    :backUrl="route('proof_coordinator.assignments.show', { proofRequest: proofRequest.id })"
                    @open-calendar="openPicker"
                />
            </div>

        </div>
        <ProofTimelinePickerModal
            :show="showPickerModal"
            :initialUserId="pickerInitialUser"
            :existingEvents="props.workEvents"
            @close="showPickerModal = false"
            @user-selected="onPickerUserSelected"
            @confirmed="onPickerConfirmed"
        />
    </AppLayout>
</template>
