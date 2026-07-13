<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import CoordinatorNavigationTabs from '@/Components/Tabs/CoordinatorNavigationTabs.vue';

// ─────────────────────────────────────────────────────────────────
//  Props
// ─────────────────────────────────────────────────────────────────
const props = defineProps({
    members:                      { type: Array,  default: () => [] },
    candidateUsers:               { type: Array,  default: () => [] },
    colorAssignments:             { type: Array,  default: () => [] },
    reservations:                 { type: Array,  default: () => [] },
    assignableUsers:              { type: Array,  default: () => [] },
    pendingRequestReservationIds: { type: Array,  default: () => [] },
    pendingRequests:              { type: Array,  default: () => [] },
    date:                         { type: String, default: '' },
});

const page = usePage();
const authUserId = computed(() => page.props.auth?.user?.id ?? null);

// ─────────────────────────────────────────────────────────────────
//  色パレット（プレプレスボードと同一トーン、専用テーブルで管理）
// ─────────────────────────────────────────────────────────────────
const COLOR_HEX = {
    indigo: '#6366f1', blue: '#3b82f6', teal: '#14b8a6', green: '#22c55e',
    yellow: '#eab308', orange: '#f97316', red: '#ef4444', pink: '#ec4899',
    purple: '#a855f7', gray: '#6b7280', cyan: '#06b6d4',
};

const localColorAssignments = ref(props.colorAssignments.map(a => ({ ...a })));

function colorKeyForUser(userId) {
    const a = localColorAssignments.value.find(a => a.user_id === userId);
    return a?.color_key ?? null;
}

function colorHexForUser(userId) {
    const key = colorKeyForUser(userId);
    return key ? (COLOR_HEX[key] ?? '#6b7280') : '#6b7280';
}

// ─────────────────────────────────────────────────────────────────
//  定数
// ─────────────────────────────────────────────────────────────────
const START_HOUR = 8;
const END_HOUR   = 19;
const TOTAL_MINS = (END_HOUR - START_HOUR) * 60;
const MEMBER_W   = 150;
const ROW_H      = 64;
const HEADER_H   = 40;
const SNAP       = 15;

// ─────────────────────────────────────────────────────────────────
//  State
// ─────────────────────────────────────────────────────────────────
const currentDate     = ref(props.date || new Date().toLocaleDateString('sv-SE'));
const localMembers    = ref(props.members.map(m => ({ ...m })));
const localCandidates = ref(props.candidateUsers.map(u => ({ ...u })));
const localReservations = ref(props.reservations.map(r => ({ ...r })));
const pendingConflictIds = ref([...props.pendingRequestReservationIds]);
const localPendingRequests = ref(props.pendingRequests.map(r => ({ ...r })));

// 二重予約リクエスト・通知
const notifications      = ref([]);
const showNotifDropdown  = ref(false);

const timelineAreaRef = ref(null);
const timelineW       = ref(1200);

const drag = ref(null);
const selecting = ref(null);

const showAddMemberPanel = ref(false);
const sortMode           = ref(false);
const showColorPanel     = ref(false);
const showJobListPanel   = ref(false);
const jobListLoading     = ref(false);
const allReservations    = ref([]);
const jobListRange       = ref('this_week'); // 'this_week' | 'all'

const showFormModal = ref(false);
const formMode      = ref('create'); // 'create' | 'edit' | 'request'
const creationVia   = ref('drag');   // 'drag' | 'button'（formMode==='create'のときのみ意味を持つ）
const formData      = ref({
    id: null, operator_user_id: null, reserved_by_user_id: null, job_name: '', memo: '',
    startMin: null, endMin: null,
    // 'request' モード専用（対象となる既存予約の情報）
    conflicting_reservation_id: null, conflicting_job_name: '', conflicting_reserved_by_name: '',
});

// ツールバー「予約作成」ボタン／リクエストモード用の時刻セレクター
const MINUTE_OPTIONS = [0, 15, 30, 45];
const btnStartHour   = ref(START_HOUR);
const btnStartMinute = ref(0);
const btnEndHour     = ref(START_HOUR + 1);
const btnEndMinute   = ref(0);

function usesTimeSelectors() {
    return (formMode.value === 'create' && creationVia.value === 'button') || formMode.value === 'request';
}

function setBtnTimeFromMinutes(startMin, endMin) {
    btnStartHour.value   = START_HOUR + Math.floor(startMin / 60);
    btnStartMinute.value = startMin % 60;
    btnEndHour.value     = START_HOUR + Math.floor(endMin / 60);
    btnEndMinute.value   = endMin % 60;
}

watch([btnStartHour, btnStartMinute], () => {
    if (usesTimeSelectors()) {
        formData.value.startMin = (btnStartHour.value - START_HOUR) * 60 + btnStartMinute.value;
    }
});
watch([btnEndHour, btnEndMinute], () => {
    if (usesTimeSelectors()) {
        formData.value.endMin = (btnEndHour.value - START_HOUR) * 60 + btnEndMinute.value;
    }
});

const showListDetailModal = ref(false);
const listDetailReservation = ref(null);

// ─────────────────────────────────────────────────────────────────
//  パネル開閉状態を localStorage に保存・復元
// ─────────────────────────────────────────────────────────────────
const LS_KEY_ADD_MEMBER = 'opcal_panel_add_member';
const LS_KEY_COLOR      = 'opcal_panel_color';
const LS_KEY_JOB_LIST   = 'opcal_panel_job_list';

showAddMemberPanel.value = localStorage.getItem(LS_KEY_ADD_MEMBER) === 'true';
showColorPanel.value     = localStorage.getItem(LS_KEY_COLOR) === 'true';
showJobListPanel.value   = localStorage.getItem(LS_KEY_JOB_LIST) === 'true';

watch(showAddMemberPanel, (val) => localStorage.setItem(LS_KEY_ADD_MEMBER, String(val)));
watch(showColorPanel, (val) => localStorage.setItem(LS_KEY_COLOR, String(val)));
watch(showJobListPanel, (val) => {
    localStorage.setItem(LS_KEY_JOB_LIST, String(val));
    if (val && allReservations.value.length === 0) loadAllReservations();
});

// ─────────────────────────────────────────────────────────────────
//  Computed
// ─────────────────────────────────────────────────────────────────
const hours = computed(() => Array.from({ length: END_HOUR - START_HOUR + 1 }, (_, i) => START_HOUR + i));
const filteredHoursForLines = computed(() => hours.value.filter(h => h > START_HOUR));
const filteredHoursFor30Mins = computed(() => hours.value.filter(h => h < END_HOUR));
const pxPerMin = computed(() => timelineW.value / TOTAL_MINS);

const displayDate = computed(() => {
    const d = new Date(currentDate.value + 'T00:00:00');
    const days = ['日', '月', '火', '水', '木', '金', '土'];
    return `${d.getFullYear()}年${d.getMonth() + 1}月${d.getDate()}日（${days[d.getDay()]}）`;
});

// 「+メンバー」で登録済みの行に加え、その日に予約が入っているが未登録のオペレーターも
// 行として表示する（メンバーから外れても予約ブロックが見えなくならないようにするため）
const displayMembers = computed(() => {
    const map = new Map(localMembers.value.map(m => [m.id, { ...m, registered: true }]));
    for (const r of localReservations.value) {
        if (!map.has(r.operator_user_id)) {
            map.set(r.operator_user_id, { id: r.operator_user_id, name: r.operator_name, registered: false });
        }
    }
    return Array.from(map.values());
});

function reservationsForMember(userId) {
    return localReservations.value.filter(r => r.operator_user_id === userId);
}

function pendingRequestsForMember(userId) {
    return localPendingRequests.value.filter(r => r.operator_user_id === userId);
}

// 分単位（START_HOUR起点）で重複する既存予約を返す（新規作成モーダル用）
function findConflicts(operatorUserId, startMin, endMin, excludeId = null) {
    return reservationsForMember(operatorUserId).filter(r => {
        if (r.id === excludeId) return false;
        const rStart = isoToMinutes(r.starts_at);
        const rEnd   = isoToMinutes(r.ends_at);
        return rStart < endMin && rEnd > startMin;
    });
}

const conflicts = computed(() => {
    if (formMode.value !== 'create' || !showFormModal.value) return [];
    const f = formData.value;
    if (f.operator_user_id == null || f.startMin == null || f.endMin == null) return [];
    return findConflicts(f.operator_user_id, f.startMin, f.endMin);
});

// ─────────────────────────────────────────────────────────────────
//  日付変換ユーティリティ
// ─────────────────────────────────────────────────────────────────
/** ISO文字列（タイムゾーン表記は問わない）→ JSTでの分（START_HOUR起点）*/
function isoToMinutes(isoStr) {
    if (!isoStr) return 0;
    // Asia/Tokyo に変換してから再パースすることで、ブラウザのタイムゾーンに依存せず正しいJST時刻を得る
    const d = new Date(new Date(isoStr).toLocaleString('ja-JP', { timeZone: 'Asia/Tokyo' }));
    const jstTotalMin = d.getHours() * 60 + d.getMinutes();
    return jstTotalMin - START_HOUR * 60;
}

/** START_HOUR起点の分 → JST文字列（オフセット変換をせず、そのままJST文字列として送信する） */
function minutesToIso(date, minsFromStart) {
    const totalMinFromMidnight = START_HOUR * 60 + minsFromStart;
    const h = Math.floor(totalMinFromMidnight / 60);
    const m = totalMinFromMidnight % 60;
    return `${date} ${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:00`;
}

function minsToTimeStr(mins) {
    const total = START_HOUR * 60 + mins;
    return `${String(Math.floor(total / 60)).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`;
}

function snapMins(mins) { return Math.round(mins / SNAP) * SNAP; }
function clampMins(mins) { return Math.max(0, Math.min(TOTAL_MINS, mins)); }

// ─────────────────────────────────────────────────────────────────
//  ブロックスタイル
// ─────────────────────────────────────────────────────────────────
function blockStyle(reservation) {
    const startMin = isoToMinutes(reservation.starts_at);
    const endMin   = isoToMinutes(reservation.ends_at);
    const left  = Math.max(0, startMin) / TOTAL_MINS * 100;
    const width = Math.max(0, Math.min(endMin, TOTAL_MINS) - Math.max(0, startMin)) / TOTAL_MINS * 100;
    return { left: left + '%', width: width + '%', top: '4px', height: (ROW_H - 8) + 'px' };
}

function blockBg(reservation) {
    return colorHexForUser(reservation.reserved_by_user_id);
}

function previewStyle(d) {
    if (!d) return {};
    const left  = Math.max(0, d.previewStartMin) / TOTAL_MINS * 100;
    const width = Math.max(0, d.previewEndMin - d.previewStartMin) / TOTAL_MINS * 100;
    return { left: left + '%', width: width + '%', top: '4px', height: (ROW_H - 8) + 'px' };
}

function selectionStyle(sel) {
    if (!sel) return {};
    const s = Math.min(sel.startMin, sel.endMin);
    const e = Math.max(sel.startMin, sel.endMin);
    const left  = Math.max(0, s) / TOTAL_MINS * 100;
    const width = Math.max(0, e - s) / TOTAL_MINS * 100;
    return { left: left + '%', width: width + '%', top: '4px', height: (ROW_H - 8) + 'px' };
}

// ─────────────────────────────────────────────────────────────────
//  マウスX → 分変換
// ─────────────────────────────────────────────────────────────────
function clientXToMins(clientX) {
    if (!timelineAreaRef.value) return 0;
    const rect = timelineAreaRef.value.getBoundingClientRect();
    const relX = clientX - rect.left;
    return clampMins(snapMins(relX / timelineW.value * TOTAL_MINS));
}

// ─────────────────────────────────────────────────────────────────
//  ドラッグ開始（既存ブロック）
// ─────────────────────────────────────────────────────────────────
function onBlockMouseDown(e, reservation, type) {
    e.preventDefault();
    e.stopPropagation();

    const rowIndex = displayMembers.value.findIndex(m => m.id === reservation.operator_user_id);
    drag.value = {
        reservationId:   reservation.id,
        type,
        origStartMin:    isoToMinutes(reservation.starts_at),
        origEndMin:      isoToMinutes(reservation.ends_at),
        origUserId:      reservation.operator_user_id,
        currentUserId:   reservation.operator_user_id,
        previewStartMin: isoToMinutes(reservation.starts_at),
        previewEndMin:   isoToMinutes(reservation.ends_at),
        startClientX:    e.clientX,
        startClientY:    e.clientY,
        rowIndex,
    };
}

function onTimelineMouseDown(e, member) {
    if (e.button !== 0) return;
    if (e.target.closest('.reservation-block')) return;
    const mins = clientXToMins(e.clientX);
    selecting.value = { memberId: member.id, startMin: mins, endMin: mins, startClientX: e.clientX };
}

function onMouseMove(e) {
    if (drag.value) {
        const dx = e.clientX - drag.value.startClientX;
        const deltaMins = snapMins(dx / pxPerMin.value);
        const duration = drag.value.origEndMin - drag.value.origStartMin;

        if (drag.value.type === 'move') {
            let newStart = clampMins(snapMins(drag.value.origStartMin + deltaMins));
            let newEnd   = newStart + duration;
            if (newEnd > TOTAL_MINS) { newEnd = TOTAL_MINS; newStart = newEnd - duration; }
            drag.value.previewStartMin = newStart;
            drag.value.previewEndMin   = newEnd;

            if (timelineAreaRef.value) {
                const containerEl = timelineAreaRef.value.closest('.timeline-wrapper');
                if (containerEl) {
                    const rows = containerEl.querySelectorAll('.member-row');
                    for (let i = 0; i < rows.length; i++) {
                        const rect = rows[i].getBoundingClientRect();
                        if (e.clientY >= rect.top && e.clientY <= rect.bottom) {
                            drag.value.currentUserId = displayMembers.value[i]?.id ?? drag.value.origUserId;
                            drag.value.rowIndex = i;
                            break;
                        }
                    }
                }
            }
        } else if (drag.value.type === 'resize') {
            const newEnd = clampMins(snapMins(drag.value.origEndMin + deltaMins));
            drag.value.previewEndMin = Math.max(drag.value.previewStartMin + SNAP, newEnd);
        }
    }

    if (selecting.value) {
        selecting.value.endMin = clientXToMins(e.clientX);
    }
}

async function onMouseUp() {
    if (drag.value) {
        const d = drag.value;
        drag.value = null;

        const reservation = localReservations.value.find(r => r.id === d.reservationId);
        if (!reservation) return;

        const newStartsAt = minutesToIso(currentDate.value, d.previewStartMin);
        const newEndsAt   = minutesToIso(currentDate.value, d.previewEndMin);
        const newUserId   = d.currentUserId;

        reservation.starts_at = newStartsAt;
        reservation.ends_at   = newEndsAt;
        reservation.operator_user_id = newUserId;

        try {
            await apiPut(route('coordinator.operator_calendar.reservations.update', { operatorReservation: d.reservationId }), {
                starts_at: newStartsAt,
                ends_at:   newEndsAt,
                operator_user_id: newUserId,
            });
        } catch (err) {
            alert('更新に失敗しました: ' + err.message);
        }
    }

    if (selecting.value) {
        const sel = selecting.value;
        selecting.value = null;

        const s = Math.min(sel.startMin, sel.endMin);
        const e = Math.max(sel.startMin, sel.endMin);
        if (e - s < SNAP) return;

        formMode.value = 'create';
        creationVia.value = 'drag';
        formData.value = {
            id: null,
            operator_user_id: sel.memberId,
            reserved_by_user_id: authUserId.value,
            job_name: '',
            memo: '',
            startMin: s,
            endMin: e,
            conflicting_reservation_id: null,
            conflicting_job_name: '',
            conflicting_reserved_by_name: '',
        };
        showFormModal.value = true;
    }
}

// ツールバー「予約作成」ボタン: ドラッグせず、セレクターだけで予約作成モーダルを開く
function openCreateViaButton() {
    formMode.value = 'create';
    creationVia.value = 'button';
    const defaultOperatorId = displayMembers.value[0]?.id ?? null;
    btnStartHour.value = START_HOUR;
    btnStartMinute.value = 0;
    btnEndHour.value = START_HOUR + 1;
    btnEndMinute.value = 0;
    formData.value = {
        id: null,
        operator_user_id: defaultOperatorId,
        reserved_by_user_id: authUserId.value,
        job_name: '',
        memo: '',
        startMin: 0,
        endMin: 60,
        conflicting_reservation_id: null,
        conflicting_job_name: '',
        conflicting_reserved_by_name: '',
    };
    showFormModal.value = true;
}

function onBlockClick(e, reservation) {
    if (drag.value) return;
    formMode.value = 'edit';
    formData.value = {
        id: reservation.id,
        operator_user_id: reservation.operator_user_id,
        reserved_by_user_id: reservation.reserved_by_user_id,
        job_name: reservation.job_name,
        memo: reservation.memo ?? '',
        startMin: isoToMinutes(reservation.starts_at),
        endMin: isoToMinutes(reservation.ends_at),
        conflicting_reservation_id: null,
        conflicting_job_name: '',
        conflicting_reserved_by_name: '',
    };
    showFormModal.value = true;
}

// 編集モーダルから「リクエストを作成」: 表示中の予約を対象にリクエスト送信画面へ切り替える
// デフォルトは相手の予約と同じ開始/終了時刻。時間セレクターで一部だけ借りる指定も可能にする
function openRequestFromEdit() {
    const f = formData.value;
    formMode.value = 'request';
    setBtnTimeFromMinutes(f.startMin, f.endMin);
    formData.value = {
        id: null,
        operator_user_id: f.operator_user_id,
        reserved_by_user_id: authUserId.value,
        job_name: '',
        memo: '',
        startMin: f.startMin,
        endMin: f.endMin,
        conflicting_reservation_id: f.id,
        conflicting_job_name: f.job_name,
        conflicting_reserved_by_name: assignableUserName(f.reserved_by_user_id),
    };
}

// ─────────────────────────────────────────────────────────────────
//  予約 保存・削除
// ─────────────────────────────────────────────────────────────────
async function saveReservation() {
    const f = formData.value;
    if (!f.job_name?.trim()) { alert('案件名を入力してください。'); return; }
    if (!f.reserved_by_user_id) { alert('予約者を選択してください。'); return; }

    const body = {
        operator_user_id: f.operator_user_id,
        reserved_by_user_id: f.reserved_by_user_id,
        job_name: f.job_name.trim(),
        memo: f.memo?.trim() || null,
        starts_at: minutesToIso(currentDate.value, f.startMin),
        ends_at: minutesToIso(currentDate.value, f.endMin),
    };

    try {
        if (formMode.value === 'create') {
            const result = await apiPost(route('coordinator.operator_calendar.reservations.store'), body);
            localReservations.value.push(result);
        } else {
            const result = await apiPut(route('coordinator.operator_calendar.reservations.update', { operatorReservation: f.id }), body);
            const idx = localReservations.value.findIndex(r => r.id === f.id);
            if (idx >= 0) localReservations.value[idx] = result;
        }
        showFormModal.value = false;
    } catch (err) {
        alert('保存に失敗しました: ' + err.message);
    }
}

async function sendRequest() {
    const f = formData.value;
    if (!f.job_name?.trim()) { alert('案件名を入力してください。'); return; }
    if (!f.reserved_by_user_id) { alert('予約者を選択してください。'); return; }

    const conflictingId = formMode.value === 'request' ? f.conflicting_reservation_id : conflicts.value[0]?.id;
    if (!conflictingId) return;

    const body = {
        conflicting_reservation_id: conflictingId,
        operator_user_id: f.operator_user_id,
        job_name: f.job_name.trim(),
        memo: f.memo?.trim() || null,
        starts_at: minutesToIso(currentDate.value, f.startMin),
        ends_at: minutesToIso(currentDate.value, f.endMin),
    };

    try {
        await apiPost(route('coordinator.operator_calendar.requests.store'), body);
        showFormModal.value = false;
        alert('リクエストを送信しました。相手の承諾をお待ちください。');
        await changeDate(currentDate.value);
    } catch (err) {
        alert('リクエスト送信に失敗しました: ' + err.message);
    }
}

async function deleteReservation() {
    const f = formData.value;
    if (!f.id) return;
    if (!confirm('この予約を削除しますか？')) return;
    try {
        await apiDelete(route('coordinator.operator_calendar.reservations.destroy', { operatorReservation: f.id }));
        localReservations.value = localReservations.value.filter(r => r.id !== f.id);
        showFormModal.value = false;
    } catch (err) {
        alert('削除に失敗しました: ' + err.message);
    }
}

function operatorName(userId) {
    return displayMembers.value.find(m => m.id === userId)?.name ?? '—';
}

function assignableUserName(userId) {
    return props.assignableUsers.find(u => u.id === userId)?.name ?? '—';
}

// 色担当一覧表示用: フルネームから苗字のみを取り出す（製版ボードのカードと同じ表記）
function colorUserFamilyName(userId) {
    const u = props.assignableUsers.find(u => u.id === userId);
    if (!u) return null;
    return u.name.split(/[\s　]+/)[0];
}

// ─────────────────────────────────────────────────────────────────
//  メンバー追加・削除
// ─────────────────────────────────────────────────────────────────
async function addMember(user) {
    try {
        const result = await apiPost(route('coordinator.operator_calendar.members.store'), { user_id: user.id });
        localMembers.value.push({ id: result.user_id, name: result.name });
        localCandidates.value = localCandidates.value.filter(u => u.id !== user.id);
    } catch (err) {
        alert('メンバー追加に失敗しました: ' + err.message);
    }
}

async function removeMember(member) {
    if (!confirm(`${member.name} をオペレーターカレンダーから削除しますか？（本人の予定には影響しません）`)) return;
    try {
        const result = await apiDelete(route('coordinator.operator_calendar.members.destroy', { user: member.id }));
        localMembers.value = localMembers.value.filter(m => m.id !== member.id);
        localCandidates.value.push({ id: result.user_id, name: result.name });
        localCandidates.value.sort((a, b) => a.name.localeCompare(b.name, 'ja'));
    } catch (err) {
        alert('メンバー削除に失敗しました: ' + err.message);
    }
}

// カレンダー行の並べ替え（登録済みメンバーのみ対象。未登録行は常に末尾に表示される）
function isFirstMember(member) {
    return localMembers.value.length > 0 && localMembers.value[0].id === member.id;
}
function isLastMember(member) {
    return localMembers.value.length > 0 && localMembers.value[localMembers.value.length - 1].id === member.id;
}
function moveMemberUp(member) {
    const idx = localMembers.value.findIndex(m => m.id === member.id);
    if (idx <= 0) return;
    const arr = [...localMembers.value];
    [arr[idx - 1], arr[idx]] = [arr[idx], arr[idx - 1]];
    localMembers.value = arr;
    persistMemberOrder();
}
function moveMemberDown(member) {
    const idx = localMembers.value.findIndex(m => m.id === member.id);
    if (idx === -1 || idx >= localMembers.value.length - 1) return;
    const arr = [...localMembers.value];
    [arr[idx + 1], arr[idx]] = [arr[idx], arr[idx + 1]];
    localMembers.value = arr;
    persistMemberOrder();
}
async function persistMemberOrder() {
    try {
        await apiPut(route('coordinator.operator_calendar.members.reorder'), {
            order: localMembers.value.map(m => m.id),
        });
    } catch (err) {
        alert('並べ替えの保存に失敗しました: ' + err.message);
    }
}

// ─────────────────────────────────────────────────────────────────
//  色設定
// ─────────────────────────────────────────────────────────────────
async function setColorUser(colorKey, userId) {
    const a = localColorAssignments.value.find(a => a.color_key === colorKey);
    if (!a) return;
    const prevUserId = a.user_id;
    a.user_id = userId ? Number(userId) : null;

    try {
        await apiPatch(route('coordinator.operator_calendar.color_assignments.update', { colorKey }), { user_id: a.user_id });
    } catch (err) {
        a.user_id = prevUserId;
        alert('色設定の更新に失敗しました: ' + err.message);
    }
}

// ─────────────────────────────────────────────────────────────────
//  二重予約リクエストの通知（ジョブ通知と同様のメッセージ型スタイル）
// ─────────────────────────────────────────────────────────────────
const responseMessages = ref({}); // notification.id -> 承諾/拒否時に添えるメッセージの下書き

const NOTIF_TYPE_META = {
    request_created:  { label: 'リクエスト', cls: 'bg-orange-100 text-orange-800' },
    request_approved: { label: '承諾',       cls: 'bg-green-100 text-green-800' },
    request_rejected: { label: '拒否',       cls: 'bg-red-100 text-red-800' },
};
function notifTypeMeta(type) {
    return NOTIF_TYPE_META[type] ?? { label: type, cls: 'bg-gray-100 text-gray-700' };
}

function notifGroupKey(iso) {
    return new Date(iso).toLocaleDateString('sv-SE', { timeZone: 'Asia/Tokyo' }); // YYYY-MM-DD
}
function formatGroupLabel(key) {
    const [y, m, d] = key.split('-');
    return `${y}/${m}/${d}`;
}
function formatNotifTime(iso) {
    return new Date(iso).toLocaleTimeString('ja-JP', { timeZone: 'Asia/Tokyo', hour: '2-digit', minute: '2-digit', hour12: false });
}

const groupedNotifications = computed(() => {
    const map = {};
    for (const n of notifications.value) {
        const key = notifGroupKey(n.created_at);
        if (!map[key]) map[key] = [];
        map[key].push(n);
    }
    return Object.fromEntries(Object.entries(map).sort(([a], [b]) => (a < b ? 1 : a > b ? -1 : 0)));
});

async function loadNotifications() {
    try {
        const data = await apiFetch(route('coordinator.operator_calendar.notifications.index'));
        notifications.value = data.notifications;
    } catch (err) {
        console.error('Failed to load notifications', err);
    }
}

function toggleNotifDropdown() {
    showNotifDropdown.value = !showNotifDropdown.value;
}

async function respondNotification(n, decision, extra = {}) {
    try {
        await apiPut(route('coordinator.operator_calendar.requests.respond', { operatorReservationRequest: n.request.id }), {
            decision,
            response_message: responseMessages.value[n.id]?.trim() || null,
            ...extra,
        });
        delete responseMessages.value[n.id];
        notifications.value = notifications.value.filter(x => x.id !== n.id);
        await changeDate(currentDate.value);
    } catch (err) {
        if (err.status === 409) {
            // 既に処理済みのリクエスト（二重クリック等）。通知を既読にして一覧から消すだけにする
            delete responseMessages.value[n.id];
            notifications.value = notifications.value.filter(x => x.id !== n.id);
            markNotificationRead(n).catch(() => {});
            alert('このリクエストは既に処理済みでした。通知を削除します。');
            return;
        }
        alert('処理に失敗しました: ' + err.message);
    }
}

// ─────────────────────────────────────────────────────────────────
//  承認時のスケジュール調整モーダル（相手の新規予約／自分の既存予約の残す時間帯を編集してから承認する）
// ─────────────────────────────────────────────────────────────────
const showApproveModal = ref(false);
const approveNotif     = ref(null);
const approveDate      = ref('');
const approveForm      = ref({
    newStartHour: START_HOUR, newStartMinute: 0, newEndHour: START_HOUR, newEndMinute: 0,
    beforeEnabled: false, beforeStartHour: START_HOUR, beforeStartMinute: 0, beforeEndHour: START_HOUR, beforeEndMinute: 0,
    afterEnabled: false, afterStartHour: START_HOUR, afterStartMinute: 0, afterEndHour: START_HOUR, afterEndMinute: 0,
});

function isoToJstHM(iso) {
    const [h, m] = new Date(iso)
        .toLocaleTimeString('ja-JP', { timeZone: 'Asia/Tokyo', hour: '2-digit', minute: '2-digit', hour12: false })
        .split(':')
        .map(Number);
    return { hour: h, minute: m };
}

function hourMinuteToIso(date, hour, minute) {
    return `${date} ${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}:00`;
}

function hmToMinutes(hour, minute) {
    return hour * 60 + minute;
}

function openApproveModal(n) {
    approveNotif.value = n;
    approveDate.value = notifGroupKey(n.request.starts_at);

    const reqStart = isoToJstHM(n.request.starts_at);
    const reqEnd   = isoToJstHM(n.request.ends_at);
    approveForm.value.newStartHour   = reqStart.hour;
    approveForm.value.newStartMinute = reqStart.minute;
    approveForm.value.newEndHour     = reqEnd.hour;
    approveForm.value.newEndMinute   = reqEnd.minute;

    const conflict = n.conflicting_reservation;
    if (conflict) {
        const cStart = isoToJstHM(conflict.starts_at);
        const cEnd   = isoToJstHM(conflict.ends_at);

        // 前半（既存予約の開始 〜 リクエスト開始）：リクエストが既存予約の開始より後ろから始まる場合のみ残る
        approveForm.value.beforeEnabled     = new Date(n.request.starts_at) > new Date(conflict.starts_at);
        approveForm.value.beforeStartHour   = cStart.hour;
        approveForm.value.beforeStartMinute = cStart.minute;
        approveForm.value.beforeEndHour     = reqStart.hour;
        approveForm.value.beforeEndMinute   = reqStart.minute;

        // 後半（リクエスト終了 〜 既存予約の終了）：リクエストが既存予約の終了より前で終わる場合のみ残る
        approveForm.value.afterEnabled     = new Date(n.request.ends_at) < new Date(conflict.ends_at);
        approveForm.value.afterStartHour   = reqEnd.hour;
        approveForm.value.afterStartMinute = reqEnd.minute;
        approveForm.value.afterEndHour     = cEnd.hour;
        approveForm.value.afterEndMinute   = cEnd.minute;
    } else {
        approveForm.value.beforeEnabled = false;
        approveForm.value.afterEnabled  = false;
    }

    showApproveModal.value = true;
}

async function confirmApprove() {
    const n = approveNotif.value;
    if (!n) return;
    const f = approveForm.value;

    if (hmToMinutes(f.newEndHour, f.newEndMinute) <= hmToMinutes(f.newStartHour, f.newStartMinute)) {
        alert('相手の予約時間: 終了は開始より後にしてください。');
        return;
    }
    if (f.beforeEnabled && hmToMinutes(f.beforeEndHour, f.beforeEndMinute) <= hmToMinutes(f.beforeStartHour, f.beforeStartMinute)) {
        alert('既存予約（前半）: 終了は開始より後にしてください。');
        return;
    }
    if (f.afterEnabled && hmToMinutes(f.afterEndHour, f.afterEndMinute) <= hmToMinutes(f.afterStartHour, f.afterStartMinute)) {
        alert('既存予約（後半）: 終了は開始より後にしてください。');
        return;
    }

    const segments = [];
    if (f.beforeEnabled) {
        segments.push({
            starts_at: hourMinuteToIso(approveDate.value, f.beforeStartHour, f.beforeStartMinute),
            ends_at:   hourMinuteToIso(approveDate.value, f.beforeEndHour, f.beforeEndMinute),
        });
    }
    if (f.afterEnabled) {
        segments.push({
            starts_at: hourMinuteToIso(approveDate.value, f.afterStartHour, f.afterStartMinute),
            ends_at:   hourMinuteToIso(approveDate.value, f.afterEndHour, f.afterEndMinute),
        });
    }

    await respondNotification(n, 'approved', {
        new_starts_at:        hourMinuteToIso(approveDate.value, f.newStartHour, f.newStartMinute),
        new_ends_at:          hourMinuteToIso(approveDate.value, f.newEndHour, f.newEndMinute),
        conflicting_segments: segments,
    });

    showApproveModal.value = false;
    approveNotif.value = null;
}

async function markNotificationRead(n) {
    try {
        await apiPut(route('coordinator.operator_calendar.notifications.read', { operatorReservationNotification: n.id }), {});
        notifications.value = notifications.value.filter(x => x.id !== n.id);
    } catch (err) {
        console.error('Failed to mark notification read', err);
    }
}


// ─────────────────────────────────────────────────────────────────
//  案件一覧トグルパネル
// ─────────────────────────────────────────────────────────────────
async function loadAllReservations() {
    jobListLoading.value = true;
    try {
        const data = await apiFetch(route('coordinator.operator_calendar.all'));
        allReservations.value = data.reservations;
    } catch (err) {
        console.error('Failed to load reservation list', err);
    } finally {
        jobListLoading.value = false;
    }
}

function toggleJobListPanel() {
    showJobListPanel.value = !showJobListPanel.value;
}

// 今週（月曜〜金曜、JST）の日付範囲を "YYYY-MM-DD" 文字列で返す
function getThisWeekRange() {
    const jstNow = new Date(new Date().toLocaleString('en-US', { timeZone: 'Asia/Tokyo' }));
    const day = jstNow.getDay(); // 0=日, 1=月, ... 6=土
    const diffToMonday = day === 0 ? -6 : 1 - day;
    const monday = new Date(jstNow);
    monday.setDate(jstNow.getDate() + diffToMonday);
    const friday = new Date(monday);
    friday.setDate(monday.getDate() + 4);
    const fmt = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    return { start: fmt(monday), end: fmt(friday) };
}

const filteredAllReservations = computed(() => {
    if (jobListRange.value === 'all') return allReservations.value;
    const { start, end } = getThisWeekRange();
    return allReservations.value.filter(r => {
        const d = r.start_display.slice(0, 10);
        return d >= start && d <= end;
    });
});

// 予約一覧テーブルのソート（開始日時／終了日時／予約者）
const jobListSortKey = ref('start_display');
const jobListSortDir = ref('asc');

const sortedFilteredReservations = computed(() => {
    const key = jobListSortKey.value;
    const dir = jobListSortDir.value === 'asc' ? 1 : -1;
    return [...filteredAllReservations.value].sort((a, b) => {
        const av = a[key] ?? '';
        const bv = b[key] ?? '';
        if (av < bv) return -1 * dir;
        if (av > bv) return 1 * dir;
        return 0;
    });
});

function toggleJobListSort(key) {
    if (jobListSortKey.value === key) {
        jobListSortDir.value = jobListSortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        jobListSortKey.value = key;
        jobListSortDir.value = 'asc';
    }
}

function jobListSortIcon(key) {
    if (jobListSortKey.value !== key) return '↕';
    return jobListSortDir.value === 'asc' ? '▲' : '▼';
}

function openListDetail(reservation) {
    listDetailReservation.value = reservation;
    showListDetailModal.value = true;
}

async function deleteListReservation() {
    const r = listDetailReservation.value;
    if (!r) return;
    if (!confirm('この予約を削除しますか？')) return;
    try {
        await apiDelete(route('coordinator.operator_calendar.reservations.destroy', { operatorReservation: r.id }));
        allReservations.value = allReservations.value.filter(x => x.id !== r.id);
        localReservations.value = localReservations.value.filter(x => x.id !== r.id);
        showListDetailModal.value = false;
        listDetailReservation.value = null;
    } catch (err) {
        alert('削除に失敗しました: ' + err.message);
    }
}

// ─────────────────────────────────────────────────────────────────
//  日付ナビゲーション
// ─────────────────────────────────────────────────────────────────
async function changeDate(newDate) {
    currentDate.value = newDate;
    try {
        const data = await apiFetch(route('coordinator.operator_calendar.data') + '?date=' + newDate);
        localReservations.value = data.reservations.map(r => ({ ...r }));
        pendingConflictIds.value = data.pendingRequestReservationIds ?? [];
        localPendingRequests.value = (data.pendingRequests ?? []).map(r => ({ ...r }));
    } catch (err) {
        console.error('Failed to load reservations', err);
    }
}

function addDays(dateStr, days) {
    const [y, m, d] = dateStr.split('-').map(Number);
    const date = new Date(y, m - 1, d + days);
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

function prevDay() { changeDate(addDays(currentDate.value, -1)); }
function nextDay() { changeDate(addDays(currentDate.value, 1)); }
function onDatePickerChange(e) { if (e.target.value) changeDate(e.target.value); }

// ─────────────────────────────────────────────────────────────────
//  API ユーティリティ
// ─────────────────────────────────────────────────────────────────
function getCsrf() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

async function apiFetch(url) {
    const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
    if (!res.ok) throw new Error(await res.text());
    return res.json();
}

async function apiSend(method, url, body) {
    const res = await fetch(url, {
        method,
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCsrf(),
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
        },
        body: body !== undefined ? JSON.stringify(body) : undefined,
    });
    if (!res.ok) {
        const err = new Error(await res.text());
        err.status = res.status;
        throw err;
    }
    return res.json();
}

const apiPost  = (url, body) => apiSend('POST', url, body);
const apiPut   = (url, body) => apiSend('PUT', url, body);
const apiPatch = (url, body) => apiSend('PATCH', url, body);
const apiDelete = (url) => apiSend('DELETE', url);

// ─────────────────────────────────────────────────────────────────
//  ライフサイクル
// ─────────────────────────────────────────────────────────────────
let resizeObserver = null;

onMounted(() => {
    window.addEventListener('mousemove', onMouseMove);
    window.addEventListener('mouseup', onMouseUp);

    if (showJobListPanel.value) loadAllReservations();
    loadNotifications();

    nextTick(() => {
        if (timelineAreaRef.value) {
            resizeObserver = new ResizeObserver(entries => {
                for (const entry of entries) {
                    timelineW.value = entry.contentRect.width;
                }
            });
            resizeObserver.observe(timelineAreaRef.value);
            timelineW.value = timelineAreaRef.value.clientWidth;
        }
    });
});

onUnmounted(() => {
    window.removeEventListener('mousemove', onMouseMove);
    window.removeEventListener('mouseup', onMouseUp);
    resizeObserver?.disconnect();
});
</script>

<template>
    <AppLayout title="オペレーターカレンダー">
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">オペレーターカレンダー</h2>
        </template>
        <template #tabs>
            <CoordinatorNavigationTabs active="operator_calendar" />
        </template>

        <div class="-mx-4 sm:-mx-6 lg:-mx-8">

            <!-- ─── ツールバー ──────────────────────────────────────── -->
            <div class="flex flex-wrap items-center gap-3 bg-white px-4 py-3 shadow-sm sm:px-6 lg:px-8">
                <button @click="prevDay" class="rounded border border-gray-300 px-3 py-1.5 text-sm hover:bg-gray-50">◀ 前日</button>
                <span class="min-w-[180px] text-center text-sm font-semibold text-gray-800">{{ displayDate }}</span>
                <button @click="nextDay" class="rounded border border-gray-300 px-3 py-1.5 text-sm hover:bg-gray-50">翌日 ▶</button>
                <input type="date" :value="currentDate" @change="onDatePickerChange" class="rounded border-gray-300 text-sm" />

                <span class="mx-1 text-gray-300">|</span>

                <button @click="openCreateViaButton"
                        class="rounded bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700">
                    ＋予約作成
                </button>

                <button @click="showAddMemberPanel = !showAddMemberPanel"
                        class="rounded px-3 py-1.5 text-sm font-medium"
                        :class="showAddMemberPanel ? 'bg-green-700 text-white' : 'bg-green-100 text-green-800 hover:bg-green-200'">
                    ＋メンバー
                </button>
                <button @click="sortMode = !sortMode" title="カレンダー行の並べ替え"
                        class="rounded px-3 py-1.5 text-sm font-medium"
                        :class="sortMode ? 'bg-purple-700 text-white' : 'bg-purple-100 text-purple-800 hover:bg-purple-200'">
                    並べ替え{{ sortMode ? '終了' : '' }}
                </button>
                <div class="relative ml-auto">
                    <button @click="toggleNotifDropdown" title="通知"
                            class="relative rounded px-3 py-1.5 text-sm font-medium bg-orange-100 text-orange-800 hover:bg-orange-200">
                        🔔
                        <span v-if="notifications.length > 0"
                              class="absolute -right-1 -top-1 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white">
                            {{ notifications.length }}
                        </span>
                    </button>
                    <div v-if="showNotifDropdown" class="absolute right-0 z-30 mt-1 w-96 rounded border border-gray-200 bg-white shadow-lg">
                        <div class="border-b border-blue-100 bg-blue-50 px-3 py-1.5 text-xs text-blue-600">
                            ※ 結果通知はクリックすると既読になります。
                        </div>
                        <div v-if="notifications.length === 0" class="p-4 text-center text-xs text-gray-400">通知はありません</div>
                        <div v-else class="max-h-96 overflow-y-auto">
                            <div v-for="(list, groupKey) in groupedNotifications" :key="groupKey" class="border-b border-gray-100 last:border-b-0">
                                <div class="bg-gray-50 px-3 py-1">
                                    <h4 class="text-xs font-semibold text-gray-500">{{ formatGroupLabel(groupKey) }}</h4>
                                </div>
                                <ul class="divide-y divide-gray-100">
                                    <li v-for="n in list" :key="n.id"
                                        @click="n.type !== 'request_created' && markNotificationRead(n)"
                                        class="flex items-start gap-2 bg-blue-50/50 px-3 py-2.5"
                                        :class="n.type !== 'request_created' ? 'cursor-pointer hover:bg-blue-100/60' : ''">
                                        <!-- 未読アイコン（封筒） -->
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="mt-0.5 h-4 w-4 shrink-0 text-blue-500">
                                            <path d="M1.5 8.67v8.58a3 3 0 003 3h15a3 3 0 003-3V8.67l-8.928 5.493a3 3 0 01-3.144 0L1.5 8.67z" />
                                            <path d="M22.5 6.908V6.75a3 3 0 00-3-3h-15a3 3 0 00-3 3v.158l9.714 5.978a1.5 1.5 0 001.572 0L22.5 6.908z" />
                                        </svg>
                                        <div class="min-w-0 flex-1 text-xs">
                                            <div class="mb-1 flex flex-wrap items-center gap-2">
                                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium" :class="notifTypeMeta(n.type).cls">
                                                    {{ notifTypeMeta(n.type).label }}
                                                </span>
                                                <span class="text-gray-400">{{ formatNotifTime(n.created_at) }}</span>
                                            </div>

                                            <template v-if="n.type === 'request_created'">
                                                <p class="font-medium text-gray-900">
                                                    <span class="font-semibold">{{ n.request.requested_by_name }}</span>さんから
                                                    <span class="font-semibold">{{ n.request.operator_name }}</span>さんの枠について
                                                    「{{ n.request.job_name }}」のリクエストが届いています。
                                                </p>
                                                <p v-if="n.request.memo" class="mt-1 rounded bg-white px-2 py-1 text-gray-600">💬 {{ n.request.memo }}</p>
                                                <input v-model="responseMessages[n.id]" type="text" placeholder="返信メッセージ（任意）例: 明日ならOK"
                                                       class="mt-1.5 w-full rounded border border-gray-300 px-2 py-1 text-xs focus:border-indigo-400 focus:outline-none" />
                                                <div class="mt-1.5 flex gap-2">
                                                    <button @click="openApproveModal(n)"
                                                            class="rounded bg-green-600 px-2 py-1 text-white hover:bg-green-700">承諾</button>
                                                    <button @click="respondNotification(n, 'rejected')"
                                                            class="rounded bg-gray-500 px-2 py-1 text-white hover:bg-gray-600">拒否</button>
                                                </div>
                                            </template>
                                            <template v-else>
                                                <p class="font-medium text-gray-900">
                                                    あなたのリクエスト「{{ n.request.job_name }}」は
                                                    <span :class="n.type === 'request_approved' ? 'font-semibold text-green-600' : 'font-semibold text-red-600'">
                                                        {{ n.type === 'request_approved' ? '承諾' : '拒否' }}
                                                    </span>されました。
                                                </p>
                                                <p v-if="n.request.response_message" class="mt-1 rounded bg-white px-2 py-1 text-gray-600">💬 {{ n.request.response_message }}</p>
                                                <p class="mt-1 text-xs text-gray-400">クリックで既読にする</p>
                                            </template>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <button @click="toggleJobListPanel"
                        class="rounded px-4 py-2 text-sm font-medium"
                        :class="showJobListPanel ? 'bg-gray-700 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'">
                    案件一覧
                </button>
            </div>

            <!-- ─── メンバー追加パネル ─────────────────────────────── -->
            <div v-if="showAddMemberPanel" class="border-b border-gray-200 bg-green-50 px-4 py-3 sm:px-6 lg:px-8">
                <div class="mb-2 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-700">メンバー追加</h3>
                    <button @click="showAddMemberPanel = false" class="text-xs text-gray-500 hover:underline">閉じる</button>
                </div>
                <div v-if="localCandidates.length === 0" class="text-sm text-gray-400">追加できるユーザーがいません。</div>
                <div class="flex flex-wrap gap-2">
                    <button v-for="u in localCandidates" :key="u.id" @click="addMember(u)"
                            class="rounded-full border border-green-300 bg-white px-3 py-1 text-xs text-green-700 hover:bg-green-100">
                        ＋ {{ u.name }}
                    </button>
                </div>
            </div>

            <!-- ─── 色設定パネル ──────────────────────────────────── -->
            <div v-if="showColorPanel" class="border-b border-gray-200 bg-indigo-50 px-4 py-3 sm:px-6 lg:px-8">
                <div class="mb-2 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-700">予約者の色設定</h3>
                    <button @click="showColorPanel = false" class="text-xs text-gray-500 hover:underline">閉じる</button>
                </div>
                <div class="flex flex-wrap gap-x-3 gap-y-2">
                    <div v-for="a in localColorAssignments" :key="a.color_key"
                         class="flex items-center gap-1.5 rounded border border-gray-200 bg-white px-2 py-1">
                        <span class="h-3.5 w-3.5 shrink-0 rounded-full" :style="{ backgroundColor: COLOR_HEX[a.color_key] }"></span>
                        <select :value="a.user_id ?? ''" @change="setColorUser(a.color_key, $event.target.value)"
                                class="rounded border border-gray-200 py-0.5 pl-1.5 pr-5 text-xs text-gray-700 focus:border-indigo-400 focus:outline-none">
                            <option value="">— 未選択 —</option>
                            <option v-for="u in assignableUsers" :key="u.id" :value="u.id">{{ u.name }}</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- ─── 案件一覧パネル ─────────────────────────────────── -->
            <div v-if="showJobListPanel" class="border-b border-gray-200 bg-gray-50 px-4 py-3 sm:px-6 lg:px-8">
                <div class="mb-2 flex flex-wrap items-center gap-2">
                    <h3 class="text-sm font-semibold text-gray-700">予約一覧（全オペレーター）</h3>
                    <select v-model="jobListRange" class="rounded border border-gray-300 py-1 pl-2 pr-6 text-xs">
                        <option value="this_week">今週（月〜金）</option>
                        <option value="all">全期間</option>
                    </select>
                </div>
                <div v-if="jobListLoading" class="py-4 text-center text-sm text-gray-400">読み込み中...</div>
                <div v-else class="max-h-80 overflow-y-auto overflow-x-auto">
                    <table class="min-w-full border text-sm">
                        <thead class="sticky top-0 bg-gray-100">
                            <tr>
                                <th class="cursor-pointer select-none border px-3 py-1.5 text-left text-xs font-medium text-gray-500 hover:bg-gray-200"
                                    @click="toggleJobListSort('start_display')">開始日時 <span class="ml-0.5">{{ jobListSortIcon('start_display') }}</span></th>
                                <th class="cursor-pointer select-none border px-3 py-1.5 text-left text-xs font-medium text-gray-500 hover:bg-gray-200"
                                    @click="toggleJobListSort('end_display')">終了日時 <span class="ml-0.5">{{ jobListSortIcon('end_display') }}</span></th>
                                <th class="cursor-pointer select-none border px-3 py-1.5 text-left text-xs font-medium text-gray-500 hover:bg-gray-200"
                                    @click="toggleJobListSort('reserved_by_name')">予約者 <span class="ml-0.5">{{ jobListSortIcon('reserved_by_name') }}</span></th>
                                <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">案件名</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="r in sortedFilteredReservations" :key="r.id" class="cursor-pointer bg-white hover:bg-gray-50"
                                @click="openListDetail(r)">
                                <td class="border px-3 py-2 text-gray-700">{{ r.start_display }}</td>
                                <td class="border px-3 py-2 text-gray-700">{{ r.end_display }}</td>
                                <td class="border px-3 py-2 text-gray-700">{{ r.reserved_by_name }}</td>
                                <td class="border px-3 py-2 font-medium text-gray-900">{{ r.job_name }}</td>
                            </tr>
                            <tr v-if="sortedFilteredReservations.length === 0">
                                <td colspan="4" class="border px-3 py-4 text-center text-xs text-gray-400">予約はありません</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ─── 色担当一覧（製版ボードのカードと同じ表記） ───────── -->
            <div class="flex flex-col items-end gap-1 border-b border-gray-200 bg-white px-4 py-3 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-end gap-3">
                    <div v-for="a in localColorAssignments" :key="a.color_key"
                         class="flex flex-col items-center gap-0.5">
                        <span class="h-5 w-5 rounded-full border-2 border-white shadow"
                              :style="{ backgroundColor: COLOR_HEX[a.color_key] }"
                              :title="colorUserFamilyName(a.user_id) ?? a.color_key"></span>
                        <span v-if="colorUserFamilyName(a.user_id)"
                              :title="colorUserFamilyName(a.user_id)"
                              class="max-w-[3.5rem] truncate text-center text-[10px] leading-none text-gray-500">
                            {{ colorUserFamilyName(a.user_id) }}
                        </span>
                    </div>
                </div>
                <button type="button" class="text-[11px] text-gray-400 underline hover:text-gray-600"
                        @click="showColorPanel = !showColorPanel">
                    担当色変更
                </button>
            </div>

            <!-- ─── タイムライン ───────────────────────────────────── -->
            <div class="timeline-wrapper overflow-x-auto" style="user-select: none;">
                <div :style="{ minWidth: (MEMBER_W + 700) + 'px' }">

                    <!-- 時刻ヘッダー -->
                    <div class="flex border-b border-gray-200 bg-gray-50" :style="{ height: HEADER_H + 'px' }">
                        <div class="flex-shrink-0 border-r border-gray-200 bg-gray-50" :style="{ width: MEMBER_W + 'px' }"></div>
                        <div class="relative flex-1" ref="timelineAreaRef">
                            <div v-for="h in hours" :key="h" class="absolute top-0 flex h-full items-center"
                                 :style="{ left: ((h - START_HOUR) * 60 / TOTAL_MINS * 100) + '%' }">
                                <span class="pl-1 text-xs text-gray-500 whitespace-nowrap">{{ h }}:00</span>
                                <div v-if="h < END_HOUR" class="absolute top-0 h-full w-px bg-gray-200" :style="{ left: '0' }"></div>
                            </div>
                        </div>
                    </div>

                    <!-- メンバー行（登録メンバー ＋ 未登録だが当日予約があるオペレーター） -->
                    <div v-for="(member, idx) in displayMembers" :key="member.id"
                         class="member-row flex border-b border-gray-100"
                         :style="{ height: ROW_H + 'px' }"
                         :class="drag && drag.currentUserId === member.id ? 'bg-green-50' : idx % 2 === 0 ? 'bg-white' : 'bg-gray-50/50'">

                        <div class="sticky left-0 z-10 flex flex-shrink-0 items-center justify-between gap-1 border-r border-gray-200 px-3"
                             :style="{ width: MEMBER_W + 'px' }"
                             :class="idx % 2 === 0 ? 'bg-white' : 'bg-gray-50'">
                            <div v-if="sortMode && member.registered" class="flex shrink-0 flex-col leading-none">
                                <button @click="moveMemberUp(member)" :disabled="isFirstMember(member)" title="上へ"
                                        class="text-[10px] text-gray-400 hover:text-gray-700 disabled:opacity-20">▲</button>
                                <button @click="moveMemberDown(member)" :disabled="isLastMember(member)" title="下へ"
                                        class="text-[10px] text-gray-400 hover:text-gray-700 disabled:opacity-20">▼</button>
                            </div>
                            <span class="truncate text-sm font-medium text-gray-700" :class="!member.registered && 'italic text-gray-400'">
                                {{ member.name }}
                            </span>
                            <button v-if="member.registered" @click="removeMember(member)" title="カレンダーから削除"
                                    class="shrink-0 text-xs text-gray-300 hover:text-red-500">×</button>
                            <button v-else @click="addMember(member)" title="メンバーとして登録（予約はあるが未登録）"
                                    class="shrink-0 rounded-full border border-green-300 px-1.5 text-xs text-green-600 hover:bg-green-50">＋登録</button>
                        </div>

                        <div class="relative flex-1 cursor-crosshair" @mousedown="onTimelineMouseDown($event, member)">
                            <div v-for="h in filteredHoursForLines" :key="h"
                                 class="pointer-events-none absolute top-0 h-full w-px bg-gray-100"
                                 :style="{ left: ((h - START_HOUR) * 60 / TOTAL_MINS * 100) + '%' }"></div>
                            <div v-for="h in filteredHoursFor30Mins" :key="'h30-' + h"
                                 class="pointer-events-none absolute top-0 h-full w-px bg-gray-100/60"
                                 :style="{ left: (((h - START_HOUR) * 60 + 30) / TOTAL_MINS * 100) + '%' }"></div>

                            <!-- 予約ブロック -->
                            <div v-for="reservation in reservationsForMember(member.id)" :key="reservation.id"
                                 class="reservation-block absolute z-10 flex cursor-grab flex-col overflow-hidden rounded border px-1.5 py-0.5 text-white shadow-sm active:cursor-grabbing"
                                 :class="pendingConflictIds.includes(reservation.id) ? 'animate-pulse ring-2 ring-red-500' : ''"
                                 :style="{ ...blockStyle(reservation), backgroundColor: blockBg(reservation), borderColor: blockBg(reservation), opacity: drag && drag.reservationId === reservation.id ? 0.3 : 1 }"
                                 @mousedown.stop="onBlockMouseDown($event, reservation, 'move')"
                                 @click.stop="onBlockClick($event, reservation)">
                                <span class="truncate text-xs font-semibold leading-tight">{{ reservation.job_name }}</span>
                                <span class="truncate text-xs leading-tight opacity-80">{{ assignableUserName(reservation.reserved_by_user_id) }}</span>
                                <div class="absolute right-0 top-0 h-full w-2 cursor-ew-resize"
                                     @mousedown.stop="onBlockMouseDown($event, reservation, 'resize')"></div>
                            </div>

                            <!-- 保留中リクエストの薄いオーバーレイ表示（クリックは下の実ブロックへ透過） -->
                            <div v-for="preq in pendingRequestsForMember(member.id)" :key="'preq-' + preq.id"
                                 class="pointer-events-none absolute z-20 flex flex-col overflow-hidden rounded border-2 border-dashed border-orange-500 bg-orange-200/60 px-1.5 py-0.5"
                                 :style="blockStyle(preq)">
                                <span class="truncate text-xs font-semibold leading-tight text-orange-900">リクエスト中: {{ preq.job_name }}</span>
                                <span class="truncate text-xs leading-tight text-orange-800">{{ preq.requested_by_name }}</span>
                            </div>

                            <div v-if="drag && drag.currentUserId === member.id"
                                 class="pointer-events-none absolute z-20 rounded border-2 border-green-400 bg-green-100 opacity-80"
                                 :style="previewStyle(drag)">
                                <span class="px-1 text-xs font-semibold text-green-800">
                                    {{ minsToTimeStr(drag.previewStartMin) }}–{{ minsToTimeStr(drag.previewEndMin) }}
                                </span>
                            </div>

                            <div v-if="selecting && selecting.memberId === member.id"
                                 class="pointer-events-none absolute z-20 rounded border-2 border-blue-400 bg-blue-50 opacity-70"
                                 :style="selectionStyle(selecting)">
                                <span class="px-1 text-xs text-blue-700">
                                    {{ minsToTimeStr(Math.min(selecting.startMin, selecting.endMin)) }}–
                                    {{ minsToTimeStr(Math.max(selecting.startMin, selecting.endMin)) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div v-if="displayMembers.length === 0" class="flex items-center justify-center py-16 text-gray-400">
                        「＋メンバー」からオペレーターを追加してください。
                    </div>
                </div>
            </div><!-- /timeline-wrapper -->
        </div><!-- /-mx -->

        <!-- ─── 予約作成・編集モーダル ─────────────────────────────── -->
        <Teleport to="body">
            <div v-if="showFormModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="showFormModal = false">
                <div class="relative w-full max-w-lg rounded-lg bg-white p-6 shadow-xl">
                    <h3 class="mb-4 text-lg font-semibold text-gray-800">
                        {{ formMode === 'edit' ? '予約を編集' : formMode === 'request' ? 'リクエストを送る' : '予約を作成' }}
                    </h3>

                    <!-- ドラッグ作成・編集: 対象/時間を固定表示 -->
                    <div v-if="(formMode === 'create' && creationVia === 'drag') || formMode === 'edit'"
                         class="mb-3 text-sm text-gray-600">
                        <span class="font-medium">対象:</span> {{ operatorName(formData.operator_user_id) }}
                        &nbsp;|&nbsp;
                        <span class="font-medium">時間:</span>
                        {{ minsToTimeStr(formData.startMin) }}–{{ minsToTimeStr(formData.endMin) }}
                    </div>

                    <!-- ボタンからの新規作成: オペレーター選択＋日付固定表示＋時刻セレクター -->
                    <template v-if="formMode === 'create' && creationVia === 'button'">
                        <div class="mb-3">
                            <label class="mb-1 block text-xs font-medium text-gray-500">対象オペレーター</label>
                            <select v-model.number="formData.operator_user_id"
                                    class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none">
                                <option v-for="m in displayMembers" :key="m.id" :value="m.id">{{ m.name }}</option>
                            </select>
                        </div>
                        <div class="mb-3 text-sm text-gray-600">
                            <span class="font-medium">日付:</span> {{ displayDate }}
                        </div>
                        <div class="mb-3 flex flex-wrap items-center gap-2 text-sm">
                            <span class="font-medium text-gray-500">開始</span>
                            <select v-model.number="btnStartHour" class="w-16 rounded border border-gray-300 py-1.5 pl-2 pr-6 text-sm">
                                <option v-for="h in hours" :key="h" :value="h">{{ h }}</option>
                            </select>:
                            <select v-model.number="btnStartMinute" class="w-16 rounded border border-gray-300 py-1.5 pl-2 pr-6 text-sm">
                                <option v-for="m in MINUTE_OPTIONS" :key="m" :value="m">{{ String(m).padStart(2, '0') }}</option>
                            </select>
                            <span class="mx-1 text-gray-400">〜</span>
                            <span class="font-medium text-gray-500">終了</span>
                            <select v-model.number="btnEndHour" class="w-16 rounded border border-gray-300 py-1.5 pl-2 pr-6 text-sm">
                                <option v-for="h in hours" :key="h" :value="h">{{ h }}</option>
                            </select>:
                            <select v-model.number="btnEndMinute" class="w-16 rounded border border-gray-300 py-1.5 pl-2 pr-6 text-sm">
                                <option v-for="m in MINUTE_OPTIONS" :key="m" :value="m">{{ String(m).padStart(2, '0') }}</option>
                            </select>
                        </div>
                    </template>

                    <!-- リクエストモード: 対象の既存予約を表示＋時間は一部だけ借りられるよう編集可能 -->
                    <template v-if="formMode === 'request'">
                        <div class="mb-3 rounded border border-orange-200 bg-orange-50 p-2 text-sm text-orange-800">
                            <span class="font-medium">{{ operatorName(formData.operator_user_id) }}</span>さんの
                            <span class="font-medium">{{ formData.conflicting_reserved_by_name }}</span>さんの予約
                            「{{ formData.conflicting_job_name }}」に対してリクエストします。
                        </div>
                        <div class="mb-3 flex flex-wrap items-center gap-2 text-sm">
                            <span class="font-medium text-gray-500">開始</span>
                            <select v-model.number="btnStartHour" class="w-16 rounded border border-gray-300 py-1.5 pl-2 pr-6 text-sm">
                                <option v-for="h in hours" :key="h" :value="h">{{ h }}</option>
                            </select>:
                            <select v-model.number="btnStartMinute" class="w-16 rounded border border-gray-300 py-1.5 pl-2 pr-6 text-sm">
                                <option v-for="m in MINUTE_OPTIONS" :key="m" :value="m">{{ String(m).padStart(2, '0') }}</option>
                            </select>
                            <span class="mx-1 text-gray-400">〜</span>
                            <span class="font-medium text-gray-500">終了</span>
                            <select v-model.number="btnEndHour" class="w-16 rounded border border-gray-300 py-1.5 pl-2 pr-6 text-sm">
                                <option v-for="h in hours" :key="h" :value="h">{{ h }}</option>
                            </select>:
                            <select v-model.number="btnEndMinute" class="w-16 rounded border border-gray-300 py-1.5 pl-2 pr-6 text-sm">
                                <option v-for="m in MINUTE_OPTIONS" :key="m" :value="m">{{ String(m).padStart(2, '0') }}</option>
                            </select>
                        </div>
                        <p class="mb-3 text-xs text-gray-400">初期値は相手の予約と同じ時間です。一部の時間だけリクエストする場合は変更してください。</p>
                    </template>

                    <div class="mb-3">
                        <label class="mb-1 block text-xs font-medium text-gray-500">{{ formMode === 'request' ? '申請者' : '予約者' }}</label>
                        <select v-model.number="formData.reserved_by_user_id"
                                class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none">
                            <option v-for="u in assignableUsers" :key="u.id" :value="u.id">{{ u.name }}</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="mb-1 block text-xs font-medium text-gray-500">案件名 <span class="text-red-500">*</span></label>
                        <input v-model="formData.job_name" type="text" placeholder="案件名"
                               class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none" />
                    </div>

                    <div v-if="formMode === 'request'" class="mb-4">
                        <label class="mb-1 block text-xs font-medium text-gray-500">リクエストメッセージ（任意）</label>
                        <input v-model="formData.memo" type="text" placeholder="例: 1時間だけお借りしたい／ダメなら明日でもOK"
                               class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none" />
                    </div>
                    <div v-else class="mb-4">
                        <label class="mb-1 block text-xs font-medium text-gray-500">メモ（任意）</label>
                        <textarea v-model="formData.memo" rows="3"
                                  class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none"></textarea>
                    </div>

                    <div v-if="formMode === 'create' && conflicts.length > 0" class="mb-4 rounded border border-orange-300 bg-orange-50 p-2 text-xs text-orange-800">
                        <p class="font-semibold">この時間帯には既に予約があります:</p>
                        <ul class="ml-4 list-disc">
                            <li v-for="c in conflicts" :key="c.id">
                                {{ assignableUserName(c.reserved_by_user_id) }}: {{ c.job_name }}
                            </li>
                        </ul>
                        <p class="mt-1">「そのまま保存」で重ねて登録するか、「リクエストを送る」で相手に確認を求められます。</p>
                    </div>

                    <div class="flex items-center justify-between gap-2">
                        <button v-if="formMode === 'edit'" @click="deleteReservation"
                                class="rounded bg-red-50 px-3 py-1.5 text-sm text-red-600 hover:bg-red-100">削除</button>
                        <div class="ml-auto flex flex-wrap justify-end gap-2">
                            <button v-if="formMode === 'edit' && formData.reserved_by_user_id !== authUserId"
                                    @click="openRequestFromEdit"
                                    class="rounded border border-orange-300 px-3 py-1.5 text-sm font-medium text-orange-700 hover:bg-orange-50">
                                リクエストを作成
                            </button>
                            <button @click="showFormModal = false"
                                    class="rounded border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">キャンセル</button>
                            <button v-if="formMode === 'request'" @click="sendRequest"
                                    class="rounded bg-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-orange-700">リクエストを送る</button>
                            <template v-else>
                                <button v-if="formMode === 'create' && conflicts.length > 0" @click="sendRequest"
                                        class="rounded bg-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-orange-700">リクエストを送る</button>
                                <button @click="saveReservation"
                                        class="rounded bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">
                                    {{ formMode === 'create' && conflicts.length > 0 ? 'そのまま保存' : '保存' }}
                                </button>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ─── 承認時のスケジュール調整モーダル ─────────────────────── -->
        <Teleport to="body">
            <div v-if="showApproveModal && approveNotif" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
                 @click.self="showApproveModal = false">
                <div class="relative w-full max-w-lg rounded-lg bg-white p-6 shadow-xl">
                    <h3 class="mb-1 text-lg font-semibold text-gray-800">スケジュールを調整しますか？</h3>
                    <p class="mb-4 text-xs text-gray-500">承認すると、以下の内容で予約が確定します。必要に応じて時間を調整してください。</p>

                    <div class="mb-4 rounded border border-green-200 bg-green-50 p-3">
                        <p class="mb-2 text-sm font-medium text-green-800">
                            {{ approveNotif.request.requested_by_name }}さんの新規予約「{{ approveNotif.request.job_name }}」
                        </p>
                        <div class="flex flex-wrap items-center gap-2 text-sm">
                            <span class="font-medium text-gray-500">開始</span>
                            <select v-model.number="approveForm.newStartHour" class="w-16 rounded border border-gray-300 py-1.5 pl-2 pr-6 text-sm">
                                <option v-for="h in hours" :key="h" :value="h">{{ h }}</option>
                            </select>:
                            <select v-model.number="approveForm.newStartMinute" class="w-16 rounded border border-gray-300 py-1.5 pl-2 pr-6 text-sm">
                                <option v-for="m in MINUTE_OPTIONS" :key="m" :value="m">{{ String(m).padStart(2, '0') }}</option>
                            </select>
                            <span class="mx-1 text-gray-400">〜</span>
                            <span class="font-medium text-gray-500">終了</span>
                            <select v-model.number="approveForm.newEndHour" class="w-16 rounded border border-gray-300 py-1.5 pl-2 pr-6 text-sm">
                                <option v-for="h in hours" :key="h" :value="h">{{ h }}</option>
                            </select>:
                            <select v-model.number="approveForm.newEndMinute" class="w-16 rounded border border-gray-300 py-1.5 pl-2 pr-6 text-sm">
                                <option v-for="m in MINUTE_OPTIONS" :key="m" :value="m">{{ String(m).padStart(2, '0') }}</option>
                            </select>
                        </div>
                    </div>

                    <div v-if="approveNotif.conflicting_reservation" class="mb-4 rounded border border-gray-200 bg-gray-50 p-3">
                        <p class="mb-2 text-sm font-medium text-gray-700">
                            あなたの既存予約「{{ approveNotif.conflicting_reservation.job_name }}」の調整
                        </p>

                        <div class="mb-2 flex items-center gap-2">
                            <input type="checkbox" v-model="approveForm.beforeEnabled" id="approve-before-enabled" class="rounded border-gray-300" />
                            <label for="approve-before-enabled" class="text-xs font-medium text-gray-500">前半を残す</label>
                        </div>
                        <div v-if="approveForm.beforeEnabled" class="mb-3 flex flex-wrap items-center gap-2 text-sm">
                            <select v-model.number="approveForm.beforeStartHour" class="w-16 rounded border border-gray-300 py-1.5 pl-2 pr-6 text-sm">
                                <option v-for="h in hours" :key="h" :value="h">{{ h }}</option>
                            </select>:
                            <select v-model.number="approveForm.beforeStartMinute" class="w-16 rounded border border-gray-300 py-1.5 pl-2 pr-6 text-sm">
                                <option v-for="m in MINUTE_OPTIONS" :key="m" :value="m">{{ String(m).padStart(2, '0') }}</option>
                            </select>
                            <span class="mx-1 text-gray-400">〜</span>
                            <select v-model.number="approveForm.beforeEndHour" class="w-16 rounded border border-gray-300 py-1.5 pl-2 pr-6 text-sm">
                                <option v-for="h in hours" :key="h" :value="h">{{ h }}</option>
                            </select>:
                            <select v-model.number="approveForm.beforeEndMinute" class="w-16 rounded border border-gray-300 py-1.5 pl-2 pr-6 text-sm">
                                <option v-for="m in MINUTE_OPTIONS" :key="m" :value="m">{{ String(m).padStart(2, '0') }}</option>
                            </select>
                        </div>

                        <div class="mb-2 flex items-center gap-2">
                            <input type="checkbox" v-model="approveForm.afterEnabled" id="approve-after-enabled" class="rounded border-gray-300" />
                            <label for="approve-after-enabled" class="text-xs font-medium text-gray-500">後半を残す</label>
                        </div>
                        <div v-if="approveForm.afterEnabled" class="flex flex-wrap items-center gap-2 text-sm">
                            <select v-model.number="approveForm.afterStartHour" class="w-16 rounded border border-gray-300 py-1.5 pl-2 pr-6 text-sm">
                                <option v-for="h in hours" :key="h" :value="h">{{ h }}</option>
                            </select>:
                            <select v-model.number="approveForm.afterStartMinute" class="w-16 rounded border border-gray-300 py-1.5 pl-2 pr-6 text-sm">
                                <option v-for="m in MINUTE_OPTIONS" :key="m" :value="m">{{ String(m).padStart(2, '0') }}</option>
                            </select>
                            <span class="mx-1 text-gray-400">〜</span>
                            <select v-model.number="approveForm.afterEndHour" class="w-16 rounded border border-gray-300 py-1.5 pl-2 pr-6 text-sm">
                                <option v-for="h in hours" :key="h" :value="h">{{ h }}</option>
                            </select>:
                            <select v-model.number="approveForm.afterEndMinute" class="w-16 rounded border border-gray-300 py-1.5 pl-2 pr-6 text-sm">
                                <option v-for="m in MINUTE_OPTIONS" :key="m" :value="m">{{ String(m).padStart(2, '0') }}</option>
                            </select>
                        </div>
                        <p class="mt-2 text-xs text-gray-400">チェックを外した区間は削除されます（相手にその時間を譲る扱いになります）。</p>
                    </div>

                    <div class="flex justify-end gap-2">
                        <button @click="showApproveModal = false"
                                class="rounded border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">キャンセル</button>
                        <button @click="confirmApprove"
                                class="rounded bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">この内容で承認する</button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ─── 案件一覧：予約詳細モーダル ─────────────────────────── -->
        <Teleport to="body">
            <div v-if="showListDetailModal && listDetailReservation"
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
                 @click.self="showListDetailModal = false">
                <div class="relative w-full max-w-sm rounded-lg bg-white p-6 shadow-xl">
                    <h3 class="mb-3 text-base font-semibold text-gray-800">{{ listDetailReservation.job_name }}</h3>
                    <dl class="space-y-1.5 text-sm">
                        <div>
                            <dt class="text-xs text-gray-400">対象オペレーター</dt>
                            <dd class="text-gray-700">{{ listDetailReservation.operator_name }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-400">予約者</dt>
                            <dd class="text-gray-700">{{ listDetailReservation.reserved_by_name }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-400">日時</dt>
                            <dd class="text-gray-700">{{ listDetailReservation.start_display }} – {{ listDetailReservation.end_display }}</dd>
                        </div>
                        <div v-if="listDetailReservation.memo">
                            <dt class="text-xs text-gray-400">メモ</dt>
                            <dd class="whitespace-pre-wrap text-gray-700">{{ listDetailReservation.memo }}</dd>
                        </div>
                    </dl>
                    <div class="mt-5 flex items-center justify-between gap-2">
                        <button @click="deleteListReservation"
                                class="rounded bg-red-50 px-3 py-1.5 text-sm text-red-600 hover:bg-red-100">削除</button>
                        <button @click="showListDetailModal = false"
                                class="rounded border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">閉じる</button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
