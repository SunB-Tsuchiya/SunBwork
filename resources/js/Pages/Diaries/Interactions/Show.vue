<script setup>
import TimelineDiary from '@/Components/TimelineDiary.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import axios from 'axios';
import { computed, onMounted, ref } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps({
    diary: Object,
    routePrefix: { type: String, default: 'diaries' },
    workRecord: { type: Object, default: null },
});

// 残業を「○時間○分」形式に変換
const overtimeLabel = computed(() => {
    const min = props.workRecord?.overtime_minutes ?? 0;
    if (!min) return null;
    const h = Math.floor(min / 60);
    const m = min % 60;
    return h > 0 ? `${h}時間${m > 0 ? m + '分' : ''}` : `${m}分`;
});

// 超過残業: 240分（4時間）以上
const isExcessOvertime = computed(() => (props.workRecord?.overtime_minutes ?? 0) >= 240);

// 勤務記録の開始・終了時刻からタイムライン範囲を決定
const timelineStartHour = computed(() => {
    const t = props.workRecord?.start_time; // "HH:MM"
    if (!t) return 8;
    return Math.max(0, Math.floor(parseInt(t.split(':')[0], 10)) - 1);
});
const timelineEndHour = computed(() => {
    const t = props.workRecord?.end_time; // "HH:MM"
    if (!t) return 20;
    const h = parseInt(t.split(':')[0], 10);
    const m = parseInt(t.split(':')[1] ?? '0', 10);
    return Math.min(24, h + (m > 0 ? 2 : 1)); // 終了時刻 + 余裕
});

const comment = ref('');

// 追加: 既読ユーザー名を安全に取り出すヘルパー
const readerNames = computed(() => {
    const rb = props.diary?.read_by || [];
    if (!Array.isArray(rb) || rb.length === 0) return [];

    return rb
        .map((item) => {
            if (!item && item !== 0) return String(item);
            // 期待される形: { id, name } や { user_id, user_name } など複数パターンに対応

            if (typeof item === 'object') {
                if (item.name) return item.name;
                if (item.user_name) return item.user_name;
                if (item.admin_name) return item.admin_name;
                if (item.full_name) return item.full_name;
                // フォールバック: id として表示
                if (item.id) return `user#${item.id}`;
                return JSON.stringify(item);
            }
            // 単純な文字列/数値の場合
            return String(item);
        })
        .filter(Boolean);
});

// 日付を「YY年M月D日（曜）」形式で表示するヘルパー例: 25年9月30日（火）
const formattedDate = computed(() => {
    const d = props.diary?.date;
    if (!d) return '';
    const dt = new Date(d);
    if (isNaN(dt)) return String(d);

    const year = dt.getFullYear() % 100; // 2桁表示
    const month = dt.getMonth() + 1;
    const day = dt.getDate();
    const weekdays = ['日', '月', '火', '水', '木', '金', '土'];
    const wk = weekdays[dt.getDay()] || '';
    return `${String(year)}年${month}月${day}日（${wk}）`;
});

function routeForIndex(date) {
    const prefix = props.routePrefix || 'diaries';
    if (prefix === 'diaries') return 'diaryinteractions.index';
    return `${prefix}.diaryinteractions.index`;
}

function markRead() {
    // Post mark-read (and optional comment) then navigate to the index page for the diary's date
    const prefix = props.routePrefix || 'diaries';
    const markRouteName = prefix === 'diaries' ? 'diaryinteractions.mark_read' : `${prefix}.diaryinteractions.mark_read`;

    let target;
    try {
        target = route(markRouteName, props.diary.id);
    } catch (e) {
        console.warn('Ziggy route resolution failed for', markRouteName, e);
        const id = props.diary?.id;
        if (prefix === 'admin') target = `/admin/diaryinteractions/${id}/mark-read`;
        else if (prefix === 'leader') target = `/leader/diaryinteractions/${id}/mark-read`;
        else target = `/diaryinteractions/${id}/mark-read`;
    }

    (async () => {
        try {
            const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
            const csrf = match ? decodeURIComponent(match[1]) : null;

            const res = await fetch(target, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    ...(csrf ? { 'X-XSRF-TOKEN': csrf } : {}),
                },
                body: JSON.stringify({ comment: comment.value }),
            });

            if (!res.ok) {
                console.error('markRead POST failed', res.status, await res.text());
                return;
            }

            // optimistic store
            try {
                const key = 'optimistic_reads';
                const cur = JSON.parse(sessionStorage.getItem(key) || '[]');
                if (!cur.includes(props.diary.id)) {
                    cur.push(props.diary.id);
                    sessionStorage.setItem(key, JSON.stringify(cur));
                }
            } catch (e) {
                console.warn('optimistic read store failed', e);
            }

            // Redirect to the correct interactions index depending on prefix
            try {
                let redirectUrl = '/diaryinteractions/interactions';
                if (prefix === 'admin') redirectUrl = '/admin/diaryinteractions';
                else if (prefix === 'leader') redirectUrl = '/leader/diaryinteractions';
                window.location.href = redirectUrl;
            } catch (e) {
                window.location.href = '/diaryinteractions/interactions';
            }
        } catch (e) {
            console.error('markRead fetch error', e);
        }
    })();
}

function goIndex() {
    // Navigate back to the diary interactions index for the given prefix
    const prefix = props.routePrefix || 'diaries';
    const routeName = prefix === 'diaries' ? 'diaryinteractions.index' : `${prefix}.diaryinteractions.index`;
    try {
        // prefer Ziggy route resolution when available on the page
        const url = route(routeName);
        window.location.href = url;
        return;
    } catch (e) {
        // fallback to explicit prefix paths
    }

    let redirectUrl = `/diaryinteractions/interactions`;
    if (prefix === 'admin') redirectUrl = `/admin/diaryinteractions`;
    else if (prefix === 'leader') redirectUrl = `/leader/diaryinteractions`;
    window.location.href = redirectUrl;
}

// --- events timetable state for interactions show (read-only) ---
const events = ref([]);

// helper to format YYYY-MM-DD in app/JST timezone (keep consistent with Diaries/Show.vue)
function formatJstDate(dateStr) {
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

// fetch events for this diary's date (read-only display)
onMounted(async () => {
    try {
        const date = formatJstDate(props.diary.date);
        // include the diary owner's user_id so leaders/admins can fetch that user's events
        const resp = await axios.get('/events', { params: { date, user_id: props.diary.user_id } });
        events.value = (resp.data || []).map((e) => ({
            id: e.id ?? e.event_id ?? e._id ?? null,
            title: e.title || e.name || '(無題)',
            start: e.start,
            end: e.end || e.start,
            allDay: !!e.allDay || !!e.all_day || false,
            color: e.color || e.backgroundColor || '#2563eb',
            description: e.description || e.extendedProps?.description || '',
        }));
    } catch (err) {
        // ignore fetch errors for now
        console.warn('Failed to load events for diary interaction show', err);
    }
});

// when timeline emits open-edit in read-only view, navigate to events.show (no edit)
function onTimelineOpenEdit(payload) {
    if (!payload || !payload.id) return;
    const id = payload.id;
    try {
        // include diary id so the event show page can render a back link
        const url = route('diaryinteractions.events.show', { event: id }) + `?diary=${encodeURIComponent(props.diary.id)}`;
        window.location.href = url;
        return;
    } catch (e) {
        // fallback to prefixed path
        window.location.href = `/diaryinteractions/events/${id}`;
        return;
    }
}

async function deleteComment(commentId, idx) {
    if (!confirm('コメントを削除してよいですか？')) return;

    const prefix = props.routePrefix || 'diaries';
    let target;
    try {
        target = route('diary_comments.destroy', commentId);
    } catch (e) {
        console.warn('Ziggy route failed for diary_comments.destroy', e);
        target = `/diary-comments/${commentId}`;
    }

    try {
        const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
        const csrf = match ? decodeURIComponent(match[1]) : null;
        const res = await fetch(target, {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                ...(csrf ? { 'X-XSRF-TOKEN': csrf } : {}),
            },
        });

        if (!res.ok) {
            console.error('deleteComment failed', res.status, await res.text());
            alert('コメントの削除に失敗しました');
            return;
        }

        // remove from local diary comments array (if present)
        try {
            const arr = props.diary.comments || [];
            if (arr[idx] && (arr[idx].id === commentId || arr[idx].id === Number(commentId))) {
                arr.splice(idx, 1);
            } else {
                // fallback: find by id
                const found = arr.findIndex((x) => x.id === commentId || x.id === Number(commentId));
                if (found >= 0) arr.splice(found, 1);
            }
        } catch (e) {
            console.warn('local comment array update failed', e);
        }
    } catch (e) {
        console.error('deleteComment error', e);
        alert('コメントの削除中にエラーが発生しました');
    }
}
</script>

<template>
    <AppLayout title="日報表示">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">日報一覧</h2>
        </template>

        <div class="rounded bg-white p-6 shadow">
            <div class="mb-3 flex flex-wrap items-baseline gap-x-4 gap-y-1">
                <h1 class="text-2xl font-bold">日報 {{ formatJstDate(props.diary.date) }}</h1>
                <span class="text-sm text-gray-600">
                    <span v-if="diary.department_name">{{ diary.department_name }}：</span>{{ diary.user_name }}
                </span>
            </div>

            <!-- 勤務情報バー -->
            <div v-if="workRecord" class="mb-4 flex flex-wrap items-center gap-x-5 gap-y-1 rounded border px-4 py-2 text-sm"
                 :class="isExcessOvertime ? 'border-red-300 bg-red-50' : 'border-gray-200 bg-gray-50'">
                <span v-if="workRecord.work_style" class="font-medium text-gray-700">
                    {{ workRecord.work_style }}
                </span>
                <span v-if="workRecord.start_time" class="text-gray-600">
                    開始 <span class="font-semibold text-gray-800">{{ workRecord.start_time }}</span>
                </span>
                <span v-if="workRecord.end_time" class="text-gray-600">
                    終了 <span class="font-semibold text-gray-800">{{ workRecord.end_time }}</span>
                </span>
                <span v-if="overtimeLabel" :class="isExcessOvertime ? 'font-bold text-red-600' : 'text-gray-600'">
                    残業
                    <span class="font-semibold">{{ overtimeLabel }}</span>
                    <span v-if="isExcessOvertime" class="ml-1 rounded bg-red-600 px-1.5 py-0.5 text-xs text-white">超過</span>
                </span>
                <span v-if="!overtimeLabel && (workRecord.start_time || workRecord.end_time)" class="text-gray-400">残業なし</span>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <!-- 左列: 日報内容 + 既読 + コメント -->
                <div class="flex flex-col gap-3">
                    <!-- 日報本文（長い場合はスクロール） -->
                    <div class="prose max-h-52 overflow-y-auto rounded border p-3 text-sm" v-html="props.diary.content"></div>

                    <!-- 既読ユーザー -->
                    <div v-if="readerNames.length" class="text-sm text-gray-600">
                        <strong class="mr-1">既読:</strong>{{ readerNames.join(', ') }}
                    </div>

                    <!-- 保存済みコメント -->
                    <div>
                        <h3 class="mb-1 text-sm font-semibold text-gray-700">コメント</h3>
                        <div v-if="!(props.diary.comments || []).length" class="text-sm text-gray-400">コメントはありません</div>
                        <div
                            v-for="(c, idx) in props.diary.comments || []"
                            :key="c.id || idx"
                            class="mb-1 flex items-start justify-between rounded border p-2"
                        >
                            <div class="text-sm text-gray-700">
                                <strong>{{ c.user_name }}</strong>：
                                <span class="whitespace-pre-wrap">{{ c.comment }}</span>
                            </div>
                            <div v-if="c.user_id === $page.props.auth?.user?.id" class="ml-3 shrink-0">
                                <button @click.prevent="deleteComment(c.id, idx)" class="text-sm text-red-600 hover:underline">削除</button>
                            </div>
                        </div>
                    </div>

                    <!-- コメント入力 -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700">コメント（任意）</label>
                        <textarea v-model="comment" rows="2" class="mt-1 block w-full rounded border px-3 py-2 text-sm"></textarea>
                    </div>
                </div>

                <!-- 右列: 当日の予定 -->
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700">当日の予定</label>
                    <TimelineDiary
                        :date="formatJstDate(props.diary.date)"
                        :events="events"
                        :startHour="timelineStartHour"
                        :endHour="timelineEndHour"
                        :editable="false"
                        @open-edit="onTimelineOpenEdit"
                    />
                </div>
            </div>

            <div class="mt-4 flex justify-end space-x-2">
                <button @click="goIndex" class="rounded bg-gray-200 px-4 py-2 text-gray-700">一覧へ</button>
                <button @click="markRead" class="rounded bg-blue-600 px-4 py-2 text-white">既読にする</button>
            </div>
        </div>
    </AppLayout>
</template>
