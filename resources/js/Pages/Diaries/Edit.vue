<script setup>
import useToasts from '@/Composables/useToasts';
import { useDiaryAttachments } from '@/Composables/useDiaryAttachments';
import TimelineDiary from '@/Components/TimelineDiary.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { QuillEditor } from '@vueup/vue-quill';
import '@vueup/vue-quill/dist/vue-quill.snow.css';
import axios from 'axios';
import { onMounted, onUnmounted, ref, watch } from 'vue';
import { route } from 'ziggy-js';
// original toolbar configuration (kept for later use):
// import { defaultToolbar } from '@/config/quillToolbar';

// default-like toolbar (image insertion intentionally removed)
const simpleToolbar = [
    [{ header: [1, 2, 3, false] }],
    ['bold', 'italic', 'underline', 'strike'],
    [{ list: 'ordered' }, { list: 'bullet' }, { indent: '-1' }, { indent: '+1' }],
    ['blockquote', 'code-block'],
    [{ align: [] }],
    ['clean'],
];

const props = defineProps({
    diary:      { type: Object, required: true },
    worktypes:  { type: Array,  default: () => [] },
    workRecord: { type: Object, default: null },
});
const content = ref('');
let editorInstance = null;

const { attachments, previewModal, openPreview, closePreview, uploadAndStage, removeAttachment, attachmentIds } =
    useDiaryAttachments(props.diary.attachments || []);

const hours   = Array.from({ length: 24 }, (_, i) => String(i).padStart(2, '0'));
const minutes = ['00', '15', '30', '45'];

function parseHourMinute(timeStr, defaultHour) {
    if (!timeStr) return { hour: defaultHour, minute: '00' };
    const parts = String(timeStr).split(':');
    return { hour: parts[0]?.padStart(2, '0') || defaultHour, minute: parts[1]?.padStart(2, '0') || '00' };
}

// 初期値: work_record があればそこから、なければ先頭 worktype のデフォルトを使う
const firstWt   = props.worktypes[0] ?? null;
const initStart = props.workRecord?.start_time || firstWt?.start_time || '08:00';
const initEnd   = props.workRecord?.end_time   || firstWt?.end_time   || '17:00';
const initWtId  = props.workRecord?.worktype_id ?? firstWt?.id ?? null;
const initWt    = props.worktypes.find((w) => w.id === initWtId) ?? firstWt;

const st = parseHourMinute(initStart, '08');
const et = parseHourMinute(initEnd,   '17');

// ── タイムライン ────────────────────────────────────────────────
const pageProps = usePage().props;
const timelineEvents = ref([]);

async function fetchDayEvents(date) {
    if (!date) return;
    try {
        const userId = pageProps.auth?.user?.id;
        const resp = await axios.get(route('events.index'), { params: { date, user_id: userId } });
        timelineEvents.value = (resp.data || []).map((e) => ({
            id: e.id ?? e.event_id ?? null,
            title: e.title || e.name || '(無題)',
            start: e.start,
            end: e.end || e.start,
            allDay: !!e.allDay || !!e.all_day || false,
            color: e.color || e.backgroundColor || '#2563eb',
        }));
    } catch {
        // サイレントに無視
    }
}

onMounted(() => {
    fetchDayEvents(props.diary.date?.split('T')[0] ?? props.diary.date);
});

watch(() => form.date, (newDate) => {
    if (newDate) fetchDayEvents(newDate);
});

async function onTimelineUpdate(payload) {
    try {
        await axios.put(`/events/${payload.id}/calendar`, {
            date: payload.date,
            startHour: payload.startHour,
            startMinute: payload.startMinute,
            endHour: payload.endHour,
            endMinute: payload.endMinute,
        });
        await fetchDayEvents(payload.date);
    } catch {
        alert('予定の更新に失敗しました');
    }
}

function onTimelineOpenCreate(payload) {
    const returnTo = window.location.pathname + window.location.search;
    if (payload && payload.minuteOffset !== undefined && payload.minuteOffset !== null) {
        const totalMin = Math.round(payload.minuteOffset / 15) * 15;
        const clamped  = Math.max(0, Math.min(totalMin, 24 * 60 - 1));
        const hh = String(Math.floor(clamped / 60)).padStart(2, '0');
        const mm = String(clamped % 60).padStart(2, '0');
        router.get(route('events.create', {
            date: form.date, startHour: hh, startMinute: mm,
            endHour: String(Math.min(23, parseInt(hh) + 1)).padStart(2, '0'), endMinute: mm,
            return_to: returnTo,
        }));
    } else {
        router.get(route('events.create', { date: form.date, return_to: returnTo }));
    }
}

function onTimelineOpenEdit(payload) {
    if (!payload || !payload.id) return;
    const returnTo = window.location.pathname + window.location.search;
    router.get(route('events.edit', { event: payload.id, return_to: returnTo }));
}

function applyQuillJaTitles(editor) {
    try {
        const toolbar = editor.getModule('toolbar');
        const container = toolbar?.container;
        if (!container) return;
        const btnMap = {
            'ql-bold': '太字', 'ql-italic': '斜体', 'ql-underline': '下線',
            'ql-strike': '取り消し線', 'ql-blockquote': '引用',
            'ql-code-block': 'コードブロック', 'ql-clean': '書式をクリア',
        };
        const listMap  = { ordered: '番号付きリスト', bullet: '箇条書き' };
        const indentMap = { '+1': 'インデントを増やす', '-1': 'インデントを減らす' };
        const alignMap  = { '': '左揃え', center: '中央揃え', right: '右揃え', justify: '両端揃え' };
        container.querySelectorAll('button').forEach((btn) => {
            for (const [cls, title] of Object.entries(btnMap)) {
                if (btn.classList.contains(cls)) { btn.setAttribute('title', title); return; }
            }
            const val = btn.value ?? btn.getAttribute('value') ?? '';
            if (btn.classList.contains('ql-list'))   btn.setAttribute('title', listMap[val]   ?? 'リスト');
            if (btn.classList.contains('ql-indent'))  btn.setAttribute('title', indentMap[val] ?? 'インデント');
            if (btn.classList.contains('ql-align'))   btn.setAttribute('title', alignMap[val]  ?? '配置');
        });
        const headerLabel = container.querySelector('.ql-header .ql-picker-label');
        if (headerLabel) headerLabel.setAttribute('title', '見出し');
    } catch { /* ignore */ }
}

function handleEditorReady(editor) {
    editorInstance = editor;
    applyQuillJaTitles(editor);
    const delta = editor.clipboard.convert(props.diary.content || '<p>初期値がありません</p>');
    editor.setContents(delta);
    try {
        const html = editor.root && editor.root.innerHTML ? editor.root.innerHTML : props.diary.content || '';
        content.value = html;
        if (typeof form !== 'undefined') {
            form.content = html;
        }
    } catch (e) {
        // ignore
    }

    // attach drop/paste handlers to intercept files and upload them
    try {
        const root = editor.root;
        const onDrop = async (e) => {
            if (!e || !e.dataTransfer) return;
            e.preventDefault();
            e.stopPropagation();
            await handleDrop(e);
        };
        const onPaste = async (e) => {
            const items = (e.clipboardData && e.clipboardData.items) || [];
            const files = [];
            for (let i = 0; i < items.length; i++) {
                const it = items[i];
                if (it.kind === 'file') {
                    const f = it.getAsFile();
                    if (f) files.push(f);
                }
            }
            if (files.length) {
                e.preventDefault();
                e.stopPropagation();
                for (const f of files) {
                    try {
                        await uploadAndStage(f);
                    } catch (err) {
                        console.error('paste file upload', err);
                    }
                }
            }
        };
        root.addEventListener('drop', onDrop, true);
        root.addEventListener('paste', onPaste, true);
        editor.__customDropHandler = onDrop;
        editor.__customPasteHandler = onPaste;
    } catch (err) {
        console.error('attach drop/paste handlers failed', err);
    }

    restoreDraftIfAny();
}

const { showToast } = useToasts();

const isSubmitting = ref(false);

const form = useForm({
    date:         props.diary.date,
    work_style:   initWt?.name ?? '',
    start_hour:   st.hour,
    start_minute: st.minute,
    end_hour:     et.hour,
    end_minute:   et.minute,
    start_time:   `${st.hour}:${st.minute}`,
    end_time:     `${et.hour}:${et.minute}`,
    content:      content.value,
    attachment_ids: [],
});

function onWorktypeChange() {
    const wt = props.worktypes.find((w) => w.name === form.work_style);
    if (!wt) return;
    const s = parseHourMinute(wt.start_time, '08');
    const e = parseHourMinute(wt.end_time,   '17');
    form.start_hour   = s.hour;
    form.start_minute = s.minute;
    form.end_hour     = e.hour;
    form.end_minute   = e.minute;
}

// ── 下書き自動保存 ──────────────────────────────────────────────
// セッション切れ等で強制リロード（bootstrap.js の reloadForStaleSession）が
// 発生しても編集中の内容を失わないよう、localStorage に定期的に退避する。
const draftKey = () => `sb_diary_draft:edit:${props.diary.id}`;

function saveDraft() {
    try {
        const html = editorInstance?.root?.innerHTML ?? content.value;
        localStorage.setItem(draftKey(), JSON.stringify({
            content: html,
            work_style: form.work_style,
            start_hour: form.start_hour,
            start_minute: form.start_minute,
            end_hour: form.end_hour,
            end_minute: form.end_minute,
            savedAt: Date.now(),
        }));
    } catch (e) { /* localStorage 利用不可時は無視 */ }
}

function clearDraft() {
    try { localStorage.removeItem(draftKey()); } catch (e) {}
}

function loadDraft() {
    try {
        const raw = localStorage.getItem(draftKey());
        return raw ? JSON.parse(raw) : null;
    } catch (e) { return null; }
}

let draftSaveTimer = null;
function scheduleDraftSave() {
    clearTimeout(draftSaveTimer);
    draftSaveTimer = setTimeout(saveDraft, 800);
}

function restoreDraftIfAny() {
    const draft = loadDraft();
    if (!draft || !draft.content) return;
    if (draft.content === (props.diary.content || '')) {
        clearDraft();
        return;
    }
    if (!confirm('前回保存されなかった下書きが見つかりました。復元しますか？')) {
        clearDraft();
        return;
    }
    content.value = draft.content;
    form.content = draft.content;
    if (editorInstance) {
        try {
            const delta = editorInstance.clipboard.convert(draft.content);
            editorInstance.setContents(delta);
        } catch (e) {
            editorInstance.root.innerHTML = draft.content;
        }
    }
    if (draft.work_style)   form.work_style   = draft.work_style;
    if (draft.start_hour)   form.start_hour   = draft.start_hour;
    if (draft.start_minute) form.start_minute = draft.start_minute;
    if (draft.end_hour)     form.end_hour     = draft.end_hour;
    if (draft.end_minute)   form.end_minute   = draft.end_minute;
}

watch(content, scheduleDraftSave);
watch(() => [form.work_style, form.start_hour, form.start_minute, form.end_hour, form.end_minute], saveDraft);

window.addEventListener('beforeunload', saveDraft);
onUnmounted(() => window.removeEventListener('beforeunload', saveDraft));

// ドロップ／貼り付けされたファイルは本文には埋め込まず、添付ファイル欄にステージングする
async function handleDrop(e) {
    const items = e.dataTransfer?.files || [];
    if (!items.length) return;
    for (let i = 0; i < items.length; i++) {
        try {
            await uploadAndStage(items[i]);
        } catch (err) {
            console.error('drop process error', err);
        }
    }
}

const submit = () => {
    try {
        if (editorInstance && editorInstance.root && editorInstance.root.innerHTML !== undefined) {
            form.content = editorInstance.root.innerHTML;
        } else {
            form.content = content.value;
        }
    } catch (e) {
        form.content = content.value;
    }
    form.start_time = `${form.start_hour}:${form.start_minute}`;
    form.end_time   = `${form.end_hour}:${form.end_minute}`;
    form.attachment_ids = attachmentIds();
    form.put(route('diaries.update', props.diary.id), {
        onStart: () => {
            isSubmitting.value = true;
            try {
                showToast('送信中...', 'info', 1000);
            } catch (e) {}
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
        onSuccess: () => {
            clearDraft();
            try {
                showToast('更新しました', 'success', 1500);
            } catch (e) {}
            setTimeout(() => {
                router.get(route('diaries.index'));
            }, 300);
        },
        onError: (errors) => {
            try {
                const { showValidationErrors } = useToasts();
                showValidationErrors(errors, 6000);
            } catch (e) {
                try {
                    showToast('更新に失敗しました', 'error', 4000);
                } catch (ee) {}
            }
        },
    });
};

// ===== 過去データから流用 =====
const showPastModal = ref(false);
const pastDateRange = ref('last');
const pastLoading = ref(false);
const pastError = ref('');
const pastRecords = ref([]);

const pastRangeOptions = [
    { value: 'last',  label: '前回' },
    { value: 'week',  label: '7日間' },
    { value: 'month', label: '30日間' },
];

function openPastModal() {
    showPastModal.value = true;
    if (pastRecords.value.length === 0) fetchPastDiaries();
}

function closePastModal() {
    showPastModal.value = false;
}

async function fetchPastDiaries() {
    pastLoading.value = true;
    pastError.value = '';
    try {
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const url = route('diaries.past_data') + '?date_range=' + pastDateRange.value;
        const res = await fetch(url, {
            credentials: 'same-origin',
            headers: {
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
        });
        if (!res.ok) throw new Error();
        const data = await res.json();
        pastRecords.value = (data.records || []).map((rec) => {
            const dateObj = new Date(rec.date);
            const year = dateObj.getFullYear();
            const month = dateObj.getMonth() + 1;
            const day = dateObj.getDate();
            return { ...rec, date: `${year}年${month}月${day}日` };
        });
    } catch {
        pastError.value = 'データの取得に失敗しました。';
    } finally {
        pastLoading.value = false;
    }
}

watch(pastDateRange, () => {
    if (showPastModal.value) fetchPastDiaries();
});

function applyPastDiary(rec) {
    const html = rec.content ?? '';
    form.content = html;
    content.value = html;
    if (editorInstance) {
        try {
            const delta = editorInstance.clipboard.convert(html);
            editorInstance.setContents(delta);
        } catch (e) {
            editorInstance.root.innerHTML = html;
        }
    }
    closePastModal();
}

const back = () => {
    try {
        const qp = new URLSearchParams(window.location.search || '');
        const returnTo = qp.get('return_to');
        if (returnTo) {
            window.location.href = decodeURIComponent(returnTo);
            return;
        }
    } catch (e) {
        // ignore
    }
    try {
        if (window.history && window.history.length > 1) {
            window.history.back();
            return;
        }
    } catch (e) {
        // ignore
    }
    try {
        router.get(route('diaries.show', props.diary.id));
        return;
    } catch (e) {
        router.get(route('diaries.index'));
    }
};
</script>

<template>
    <AppLayout title="日報編集">
        <template #header>
            <div class="flex items-center gap-3">
                <button @click="back"
                    class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 whitespace-nowrap hover:bg-gray-300"
                >← 戻る</button>
                <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">日報編集</h2>
            </div>
        </template>

        <template #headerExtras>
            <button type="button" @click="openPastModal"
                class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
            >過去データから流用</button>
        </template>

        <div class="mx-auto max-w-2xl rounded bg-white px-4 py-6 sm:p-6 shadow">
            <form @submit.prevent="submit">
                <!-- 1行目: 日付・勤務形態 -->
                <div class="mb-3 flex flex-wrap gap-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">日付</label>
                        <input type="date" v-model="form.date" class="rounded border p-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">勤務形態</label>
                        <select v-model="form.work_style" class="min-w-[11rem] rounded border p-2 text-sm" @change="onWorktypeChange">
                            <option v-for="wt in worktypes" :key="wt.id" :value="wt.name">{{ wt.name }}</option>
                        </select>
                    </div>
                </div>
                <!-- 2行目: 始業時間・終業時間 -->
                <div class="mb-4 flex flex-wrap gap-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">始業時間</label>
                        <div class="flex items-center gap-1">
                            <select v-model="form.start_hour" class="w-16 rounded border p-2 text-sm">
                                <option v-for="h in hours" :key="h" :value="h">{{ parseInt(h) }}時</option>
                            </select>
                            <select v-model="form.start_minute" class="w-16 rounded border p-2 text-sm">
                                <option v-for="m in minutes" :key="m" :value="m">{{ m }}分</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">終業時間</label>
                        <div class="flex items-center gap-1">
                            <select v-model="form.end_hour" class="w-16 rounded border p-2 text-sm">
                                <option v-for="h in hours" :key="h" :value="h">{{ parseInt(h) }}時</option>
                            </select>
                            <select v-model="form.end_minute" class="w-16 rounded border p-2 text-sm">
                                <option v-for="m in minutes" :key="m" :value="m">{{ m }}分</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="mb-1 block text-sm font-medium text-gray-700">内容</label>
                    <QuillEditor
                        theme="snow"
                        :toolbar="simpleToolbar"
                        content-type="html"
                        style="min-height: 180px; height: 180px; background: #fff"
                        v-model:content="content"
                        @ready="handleEditorReady"
                    />
                </div>
                <div class="mb-4">
                    <label class="mb-1 block text-sm font-medium text-gray-700">添付ファイル</label>
                    <input
                        type="file"
                        multiple
                        @change="(e) => { Array.from(e.target.files).forEach((f) => uploadAndStage(f)); e.target.value = ''; }"
                        class="w-full rounded border p-2"
                    />
                    <ul v-if="attachments.length" class="mt-2 space-y-2">
                        <li
                            v-for="file in attachments"
                            :key="file.id"
                            class="flex items-center justify-between rounded bg-gray-50 p-2"
                        >
                            <div class="flex items-center gap-3">
                                <div v-if="file.url && (file.mime || '').startsWith('image/')" class="h-12 w-16 flex-shrink-0">
                                    <img :src="file.url" class="h-12 w-16 rounded object-cover" alt="thumbnail" />
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-gray-900">{{ file.original_name }}</div>
                                    <div class="text-xs text-gray-500">
                                        {{ file.status === 'ready' ? (file.size ? (file.size / 1024).toFixed(1) + ' KB' : '') : '処理中...' }}
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <button v-if="file.url" type="button" @click.prevent="openPreview(file)" class="text-sm text-blue-600 underline">開く</button>
                                <button type="button" @click.prevent="removeAttachment(file)" class="text-sm text-red-600">削除</button>
                            </div>
                        </li>
                    </ul>
                    <div v-else class="mt-2 text-sm text-gray-500">添付ファイルなし</div>
                </div>
                <div class="mt-4 flex justify-end gap-3">
                    <button :disabled="isSubmitting" type="submit" class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-60">
                        <span v-if="isSubmitting">送信中...</span>
                        <span v-else>更新</span>
                    </button>
                </div>
            </form>

            <!-- 当日タイムライン -->
            <div class="mt-6">
                <label class="mb-2 block text-sm font-medium text-gray-700">当日の予定</label>
                <TimelineDiary
                    :date="form.date"
                    :events="timelineEvents"
                    :startHour="7"
                    :endHour="21"
                    :editable="true"
                    @update:events="onTimelineUpdate"
                    @open-create="onTimelineOpenCreate"
                    @open-edit="onTimelineOpenEdit"
                />
            </div>
        </div>
    </AppLayout>

    <!-- 過去データから流用 モーダル -->
    <div v-if="showPastModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="closePastModal">
        <div class="relative mx-4 flex max-h-[85vh] w-full max-w-2xl flex-col rounded-lg bg-white shadow-xl">
            <div class="flex items-center justify-between border-b px-6 py-4">
                <h2 class="text-lg font-semibold text-gray-800">過去の日報から流用</h2>
                <button @click="closePastModal" class="text-gray-500 hover:text-gray-700">✕</button>
            </div>
            <div class="border-b px-6 py-3">
                <div class="flex gap-2">
                    <button
                        v-for="opt in pastRangeOptions"
                        :key="opt.value"
                        @click="pastDateRange = opt.value"
                        :class="pastDateRange === opt.value ? 'bg-indigo-600 text-white' : 'border text-gray-700 hover:bg-gray-100'"
                        class="rounded px-4 py-1.5 text-sm"
                    >{{ opt.label }}</button>
                </div>
                <button
                    @click="fetchPastDiaries"
                    :disabled="pastLoading"
                    class="mt-3 rounded bg-indigo-600 px-4 py-2 text-sm text-white hover:bg-indigo-700 disabled:opacity-60"
                >{{ pastLoading ? '取得中...' : '検索' }}</button>
            </div>
            <div class="flex-1 overflow-y-auto px-6 py-4">
                <p v-if="pastError" class="mb-3 text-sm text-red-600">{{ pastError }}</p>
                <p v-if="!pastLoading && pastRecords.length === 0" class="py-8 text-center text-sm text-gray-400">該当する日報がありません。</p>
                <div v-if="pastRecords.length > 0" class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 w-28">日付</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">内容（プレビュー）</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr
                                v-for="rec in pastRecords"
                                :key="rec.id"
                                @click="applyPastDiary(rec)"
                                class="cursor-pointer hover:bg-blue-50"
                            >
                                <td class="px-3 py-2 text-gray-600">{{ rec.date }}</td>
                                <td class="px-3 py-2 text-gray-700">{{ rec.content_preview || '（内容なし）' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="border-t px-6 py-3 text-right">
                <button @click="closePastModal" class="rounded bg-gray-200 px-4 py-2 text-sm text-gray-700 whitespace-nowrap hover:bg-gray-300">閉じる</button>
            </div>
        </div>
    </div>

    <!-- 添付ファイル プレビューモーダル -->
    <div v-if="previewModal.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
        <div class="max-h-[90vh] w-full max-w-4xl overflow-auto rounded bg-white p-4">
            <div class="mb-2 flex items-center justify-between">
                <div class="text-sm font-medium">プレビュー: {{ previewModal.filename }}</div>
                <button type="button" @click="closePreview" class="text-gray-600">閉じる</button>
            </div>
            <div class="border p-2">
                <template v-if="previewModal.mime && previewModal.mime.startsWith('image/')">
                    <img :src="previewModal.url" alt="preview" class="h-auto max-w-full" />
                </template>
                <template v-else-if="previewModal.mime === 'application/pdf'">
                    <iframe :src="previewModal.url" class="w-full" style="height: 70vh" frameborder="0"></iframe>
                </template>
                <template v-else>
                    <div class="text-sm">
                        プレビューできません。<a :href="previewModal.url" target="_blank" rel="noopener" class="text-blue-600 underline">新しいタブで開く</a>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>
