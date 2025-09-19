<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { computed, ref } from 'vue';
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
    // when using default prefix 'diaries' the route is 'diaryinteractions.mark_read', otherwise 'admin.diaryinteractions.mark_read' etc.
    const markRouteName = prefix === 'diaries' ? 'diaryinteractions.mark_read' : `${prefix}.diaryinteractions.mark_read`;
    // markRead click logged in development; suppressed for production
    let target;
    try {
        target = route(markRouteName, props.diary.id);
    } catch (e) {
        console.warn('Ziggy route resolution failed for', markRouteName, e);
        // fallback to constructing a reasonable URL
        const id = props.diary?.id;
        if (prefix === 'admin') target = `/admin/diaries/${id}/mark-read`;
        else if (prefix === 'leader') target = `/leader/diaries/${id}/mark-read`;
        else target = `/diaries/${id}/mark-read`;
    }

    // Use fetch to POST so we can deterministically redirect on success
    (async () => {
        try {
            // read XSRF token from cookie
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

            // After successful POST, do a full redirect to the index for the diary date
            try {
                // store optimistic read so index can reflect change immediately
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

                // Avoid calling Ziggy.route here to prevent runtime errors when the route list
                // isn't available in the current page payload. Build a safe explicit URL
                // based on the prefix and redirect to the diary interactions index path.
                try {
                    // Return to the list root for the prefix (drop the ?date param)
                    let redirectUrl = `/diaryinteractions`;
                    if (prefix === 'admin') redirectUrl = `/admin/diaryinteractions`;
                    else if (prefix === 'leader') redirectUrl = `/leader/diaryinteractions`;
                    window.location.href = redirectUrl;
                } catch (e) {
                    // fallback to a very safe root path
                    window.location.href = `/diaryinteractions`;
                }
            } catch (e) {
                console.warn('routeForIndex failed', e);
                window.location.href = `/diaries?date=${encodeURIComponent(props.diary.date)}`;
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

    let redirectUrl = `/diaryinteractions`;
    if (prefix === 'admin') redirectUrl = `/admin/diaryinteractions`;
    else if (prefix === 'leader') redirectUrl = `/leader/diaryinteractions`;
    window.location.href = redirectUrl;
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
        <div class="mx-auto max-w-2xl rounded bg-white p-6 shadow">
            <h1 class="mb-4 text-xl font-bold">日報 {{ props.diary.user?.name }} — {{ formattedDate }}</h1>

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

            <div class="flex justify-end space-x-2">
                <button @click="goIndex" class="rounded bg-gray-200 px-4 py-2 text-gray-700">一覧へ</button>
                <button @click="markRead" class="rounded bg-blue-600 px-4 py-2 text-white">既読にする</button>
            </div>
        </div>
    </AppLayout>
</template>
