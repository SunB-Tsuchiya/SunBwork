<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps({
    team:     { type: Object, required: true },
    minute:   { type: Object, required: true },
    members:  { type: Array, default: () => [] },
    canEdit:  { type: Boolean, default: false },
});

// コメントフォーム
const commentForm = useForm({ comment: '' });

function submitComment() {
    commentForm.post(
        route('team-rooms.minutes.comments.store', { team: props.team.id, minute: props.minute.id }),
        { onSuccess: () => { commentForm.reset(); } }
    );
}

function deleteComment(comment) {
    if (! confirm('コメントを削除しますか？')) return;
    router.delete(route('team-rooms.minutes.comments.destroy', {
        team: props.team.id, minute: props.minute.id, comment: comment.id,
    }));
}

function formatDate(d) {
    if (!d) return '';
    return String(d).slice(0, 10);
}

function formatDateTime(d) {
    if (!d) return '';
    return String(d).replace('T', ' ').slice(0, 16);
}


</script>

<template>
    <AppLayout :title="`${minute.title} — 会議記録`">
        <template #header>
            <div class="flex items-center gap-3">
                <Link
                    :href="route('team-rooms.show', { team: team.id }) + '?tab=minutes'"
                    class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300 whitespace-nowrap"
                >← チームルームに戻る</Link>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ minute.title }}</h2>
            </div>
        </template>

        <template v-if="canEdit" #headerExtras>
            <Link
                :href="route('team-rooms.minutes.edit', { team: team.id, minute: minute.id })"
                class="rounded bg-yellow-600 px-4 py-2 text-sm font-medium text-white hover:bg-yellow-700"
            >編集</Link>
        </template>

        <div class="mx-auto max-w-3xl space-y-5">

            <!-- 基本情報 -->
            <div class="rounded bg-white p-6 shadow">
                <dl class="space-y-3 text-sm">
                    <div class="flex gap-3">
                        <dt class="w-20 shrink-0 text-xs font-semibold text-gray-500">開催日</dt>
                        <dd class="text-gray-800">{{ formatDate(minute.held_at) }}</dd>
                    </div>
                    <div class="flex gap-3">
                        <dt class="w-20 shrink-0 text-xs font-semibold text-gray-500">作成者</dt>
                        <dd class="text-gray-800">{{ minute.user?.name }}</dd>
                    </div>
                    <div v-if="minute.attendee_users && minute.attendee_users.length > 0" class="flex gap-3">
                        <dt class="w-20 shrink-0 text-xs font-semibold text-gray-500">参加者</dt>
                        <dd class="flex flex-wrap gap-1">
                            <span
                                v-for="u in minute.attendee_users"
                                :key="u.id"
                                class="rounded-full border border-blue-200 bg-blue-50 px-2.5 py-0.5 text-xs text-blue-800"
                            >{{ u.name }}</span>
                        </dd>
                    </div>
                </dl>
            </div>

            <!-- 本文 -->
            <div class="rounded bg-white p-6 shadow">
                <h3 class="mb-3 font-semibold text-gray-800">内容</h3>
                <div
                    class="prose prose-sm max-w-none"
                    v-html="minute.content || '（内容なし）'"
                ></div>
            </div>

            <!-- 添付ファイル -->
            <div v-if="minute.attachments && minute.attachments.length > 0" class="rounded bg-white p-6 shadow">
                <h3 class="mb-3 font-semibold text-gray-800">添付ファイル</h3>
                <ul class="space-y-2">
                    <li v-for="att in minute.attachments" :key="att.id" class="flex items-center gap-3">
                        <img v-if="att.thumb_url" :src="att.thumb_url" class="h-10 w-10 rounded object-cover" />
                        <a :href="att.url" target="_blank"
                            class="text-sm text-indigo-600 hover:underline"
                        >{{ att.original_name }}</a>
                    </li>
                </ul>
            </div>

            <!-- コメント -->
            <div class="rounded bg-white p-6 shadow">
                <h3 class="mb-4 font-semibold text-gray-800">コメント</h3>

                <div v-if="minute.comments && minute.comments.length > 0" class="mb-5 space-y-3">
                    <div
                        v-for="c in minute.comments"
                        :key="c.id"
                        class="flex items-start gap-3 rounded-lg bg-gray-50 px-4 py-3"
                    >
                        <div class="min-w-0 flex-1">
                            <div class="mb-1 flex items-center gap-2 text-xs text-gray-500">
                                <span class="font-medium text-gray-700">{{ c.user_name }}</span>
                                <span>{{ formatDateTime(c.created_at) }}</span>
                            </div>
                            <p class="whitespace-pre-wrap text-sm text-gray-800">{{ c.comment }}</p>
                        </div>
                        <button
                            v-if="c.user_id === $page.props.auth?.user?.id || $page.props.auth?.user?.user_role === 'superadmin'"
                            type="button"
                            class="shrink-0 text-xs text-red-400 hover:text-red-600"
                            @click="deleteComment(c)"
                        >削除</button>
                    </div>
                </div>

                <div v-else class="mb-4 text-sm text-gray-400">コメントはまだありません</div>

                <!-- コメント投稿 -->
                <form @submit.prevent="submitComment" class="flex gap-2">
                    <textarea
                        v-model="commentForm.comment"
                        rows="2"
                        placeholder="コメントを入力..."
                        maxlength="2000"
                        class="flex-1 rounded border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none"
                    ></textarea>
                    <button
                        type="submit"
                        :disabled="commentForm.processing || !commentForm.comment.trim()"
                        class="self-end rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-60"
                    >投稿</button>
                </form>
            </div>

        </div>
    </AppLayout>
</template>
