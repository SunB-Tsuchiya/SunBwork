<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

const props = defineProps({ event: Object, hide_edit: { type: Boolean, default: false } });

// Determine whether the event/linked assignment is already completed
function isEventCompleted() {
    try {
        if (!props.event) return false;
        if (props.event.title && String(props.event.title).indexOf('【完了】') === 0) return true;
        if (props.event.project_job_assignment_id && props.event.project_job_assignment && props.event.project_job_assignment.completed) return true;
        if (props.event.project_job_assignment && props.event.project_job_assignment.status) {
            const s = props.event.project_job_assignment.status;
            if (s.key === 'completed' || String(s.name || '').indexOf('完了') !== -1) return true;
        }
        return false;
    } catch (e) {
        return false;
    }
}

function formatJstDateTime(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    d.setHours(d.getHours() + 9);
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    const hh = String(d.getHours()).padStart(2, '0');
    const min = String(d.getMinutes()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd} ${hh}:${min}`;
}

// remove debug logs

function confirmDelete() {
    if (!confirm('この予定を削除しますか？')) return;
    // call delete endpoint and redirect to calendar on success
    // Let the server return an Inertia redirect; no client-side onSuccess navigation needed
    router.delete(route('events.destroy', { event: props.event.id }));
}

function submitComplete() {
    if (!confirm('このジョブを完了としてマークしますか？')) return;
    router.post(route('events.complete', { event: props.event.id }));
}

// 完了詳細関連は表示しないため削除
</script>

<template>
    <AppLayout title="イベント表示">
        <div class="mx-auto max-w-2xl rounded bg-white p-6 shadow">
            <h1 class="mb-4 text-2xl font-bold">イベント {{ props.event.title }}</h1>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700">日時</label>
                <div class="mt-1 text-sm text-gray-900">
                    開始: {{ formatJstDateTime(props.event.start) }}<br />
                    終了: {{ formatJstDateTime(props.event.end) }}
                </div>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700">詳細</label>
                <!-- 保持されている改行を反映するため、プレーンテキストで表示し CSS の whitespace-pre-wrap を適用 -->
                <div class="whitespace-pre-wrap text-sm text-gray-900">{{ props.event.description || '-' }}</div>
            </div>
            <!-- 添付ファイルはイベントに付与しないため関連 UI を削除しました -->
            <div class="flex space-x-4">
                <Link v-if="!props.hide_edit" :href="route('events.edit', props.event.id)" class="rounded bg-blue-600 px-4 py-2 text-white"
                    >編集</Link
                >
                <button @click="confirmDelete" class="rounded bg-red-600 px-4 py-2 text-white">削除</button>
                <!-- 完了ボタン: イベントがジョブ割り当てに紐づいている場合のみ表示 -->
                <template v-if="props.event.project_job_assignment_id">
                    <form @submit.prevent="submitComplete">
                        <button
                            type="submit"
                            :class="
                                isEventCompleted()
                                    ? 'cursor-not-allowed rounded bg-yellow-800 px-4 py-2 text-white opacity-80'
                                    : 'rounded bg-yellow-600 px-4 py-2 text-white'
                            "
                            :disabled="isEventCompleted()"
                        >
                            {{ isEventCompleted() ? '完了済み' : '完了する' }}
                        </button>
                    </form>
                </template>
                <Link :href="route('calendar.index')" class="rounded bg-gray-200 px-4 py-2 text-gray-700">戻る</Link>
            </div>
        </div>
    </AppLayout>
</template>

<!-- merged script above -->
