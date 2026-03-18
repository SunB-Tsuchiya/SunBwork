<script setup>
import TimelineDiary from '@/Components/TimelineDiary.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import axios from 'axios';
import { computed, onMounted, ref } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps({
    diary: Object,
    routePrefix: { type: String, default: 'diaries' },
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
                    <h1 class="mb-4 text-2xl font-bold">日報 {{ formatJstDate(props.diary.date) }}</h1>

                    <div class="prose mb-6" v-html="props.diary.content"></div>

                    <!-- 追加: 既読ユーザー名を表示 -->
                    <div v-if="readerNames.length" class="mb-4 text-sm text-gray-600">
                        <strong class="mr-2">既読:</strong>
                        <span>{{ readerNames.join(', ') }}</span>
                    </div>
                    <!-- 保存されたコメントをここに表示 -->
                    <div class="mb-4">
                        <h3 class="mb-2 font-semibold">保存されたコメント</h3>
                        <div v-if="!(props.diary.comments || []).length" class="mb-2 text-sm text-gray-600">コメントはありません</div>
                        <div
                            v-for="(c, idx) in props.diary.comments || []"
                            :key="c.id || idx"
                            class="mb-2 flex items-start justify-between rounded border p-3"
                        >
                            <div class="text-sm text-gray-700">
                                <strong>{{ c.user_name || c.user_name }}</strong
                                >： <span class="whitespace-pre-wrap">{{ c.comment }}</span>
                            </div>
                            <div v-if="c.user_id === $page.props.auth?.user?.id" class="ml-4">
                                <button @click.prevent="deleteComment(c.id, idx)" class="text-sm text-red-600 hover:underline">削除</button>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">コメント（任意）</label>
                        <textarea v-model="comment" rows="3" class="mt-1 block w-full rounded border px-3 py-2"></textarea>
                    </div>

                    <!-- 当日の予定（読み取り専用） -->
                    <div class="mb-6">
                        <label class="mb-2 block text-sm font-medium text-gray-700">当日の予定</label>
                        <TimelineDiary
                            :date="formatJstDate(props.diary.date)"
                            :events="events"
                            :startHour="8"
                            :endHour="20"
                            :editable="false"
                            @open-edit="onTimelineOpenEdit"
                        />
                    </div>

                    <div class="flex justify-end space-x-2">
                        <button @click="goIndex" class="rounded bg-gray-200 px-4 py-2 text-gray-700">一覧へ</button>
                        <button @click="markRead" class="rounded bg-blue-600 px-4 py-2 text-white">既読にする</button>
                    </div>
                </div>
    </AppLayout>
</template>
