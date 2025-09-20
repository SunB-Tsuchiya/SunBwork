<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

const props = defineProps({ event: Object });

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
                <button v-if="props.event.project_job_assignment_id" @click="submitComplete" class="rounded bg-yellow-600 px-4 py-2 text-white">
                    完了する
                </button>
                <button @click="confirmDelete" class="rounded bg-red-600 px-4 py-2 text-white">削除</button>
                <Link :href="route('calendar.index')" class="rounded bg-gray-200 px-4 py-2 text-gray-700">戻る</Link>
            </div>
        </div>
    </AppLayout>
</template>
