<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps({ event: Object });

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

function confirmDelete() {
    if (!confirm('この予定を削除しますか？')) return;
    router.delete(route('events.destroy', { event: props.event.id }));
}

function submitComplete() {
    if (!confirm('このジョブを完了としてマークしますか？')) return;
    router.post(route('events.complete', { event: props.event.id }));
}

const showCompleteModal = ref(false);

function formatJstDateTimeShort(dateStr) {
    if (!dateStr) return '';
    try {
        const d = new Date(dateStr);
        d.setHours(d.getHours() + 9);
        const yyyy = d.getFullYear();
        const mm = String(d.getMonth() + 1).padStart(2, '0');
        const dd = String(d.getDate()).padStart(2, '0');
        const hh = String(d.getHours()).padStart(2, '0');
        const min = String(d.getMinutes()).padStart(2, '0');
        return `${yyyy}-${mm}-${dd} ${hh}:${min}`;
    } catch (e) {
        return String(dateStr);
    }
}

function completeInfo() {
    try {
        const ev = props.event || {};
        const when = ev.updated_at || ev.updatedAt || ev.updated || null;
        let by = null;
        if (ev.completed_by) by = ev.completed_by;
        if (!by && ev.project_job_assignment && (ev.project_job_assignment.updated_by || ev.project_job_assignment.completed_by))
            by = ev.project_job_assignment.updated_by || ev.project_job_assignment.completed_by;
        return { when: when ? formatJstDateTimeShort(when) : null, by: by || '不明' };
    } catch (e) {
        return { when: null, by: '不明' };
    }
}
</script>

<template>
    <AppLayout title="イベント（閲覧）">
        <div class="mx-auto max-w-2xl rounded bg-white p-6 shadow">
            <h1 class="mb-4 text-2xl font-bold">イベント（閲覧） {{ props.event.title }}</h1>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700">日時</label>
                <div class="mt-1 text-sm text-gray-900">
                    開始: {{ formatJstDateTime(props.event.start) }}<br />
                    終了: {{ formatJstDateTime(props.event.end) }}
                </div>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700">詳細</label>
                <div class="whitespace-pre-wrap text-sm text-gray-900">{{ props.event.description || '-' }}</div>
            </div>
            <div class="flex space-x-4">
                <!-- 管理者向け閲覧: 編集ボタンは出さない -->
                <div class="flex items-center">
                    <button
                        v-if="props.event.project_job_assignment_id"
                        @click="submitComplete"
                        :class="
                            isEventCompleted()
                                ? 'cursor-not-allowed rounded bg-yellow-800 px-4 py-2 text-white opacity-80'
                                : 'rounded bg-yellow-600 px-4 py-2 text-white'
                        "
                        :disabled="isEventCompleted()"
                    >
                        {{ isEventCompleted() ? '完了済み' : '完了する' }}
                    </button>
                    <button
                        v-if="isEventCompleted()"
                        @click="showCompleteModal = true"
                        class="ml-2 rounded bg-gray-100 px-2 py-1 text-sm text-gray-700"
                    >
                        完了詳細
                    </button>
                </div>
                <button @click="confirmDelete" class="rounded bg-red-600 px-4 py-2 text-white">削除</button>
                <Link :href="route('calendar.index')" class="rounded bg-gray-200 px-4 py-2 text-gray-700">戻る</Link>
            </div>
        </div>
        <!-- 完了詳細モーダル -->
        <div v-if="showCompleteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
            <div class="w-full max-w-md rounded bg-white p-6 shadow-lg">
                <h2 class="mb-4 text-lg font-bold">完了詳細</h2>
                <div class="mb-2"><strong>完了日時:</strong> {{ completeInfo().when || '-' }}</div>
                <div class="mb-4"><strong>完了者:</strong> {{ completeInfo().by || '-' }}</div>
                <div class="flex justify-end">
                    <button @click="showCompleteModal = false" class="rounded bg-gray-300 px-4 py-2">閉じる</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
