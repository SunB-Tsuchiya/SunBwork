<script setup>
import axios from 'axios';
import { computed, ref, watch } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps({ mode: { type: String, required: true } });
const emit = defineEmits(['cancel', 'selected']);
const searchMode = ref('date');
const dateRange = ref('yesterday');
const hideCompleted = ref(false);
const selectedClientId = ref('');
const selectedProjectId = ref('');
const records = ref([]);
const clients = ref([]);
const projects = ref([]);
const pendingRecord = ref(null);
const loading = ref(false);
const error = ref('');
let sequence = 0;

const title = computed(() => props.mode === 'past' ? '過去データから流用' : '依頼ジョブを選択');
const queryKey = computed(() => JSON.stringify({
    mode: props.mode,
    searchMode: searchMode.value,
    dateRange: dateRange.value,
    hideCompleted: hideCompleted.value,
    clientId: selectedClientId.value,
    projectId: selectedProjectId.value,
}));

watch(selectedClientId, () => { selectedProjectId.value = ''; });
watch(queryKey, async () => {
    const current = ++sequence;
    loading.value = true;
    error.value = '';
    records.value = [];
    try {
        if (props.mode === 'request') {
            const { data } = await axios.get(route('user.myjobbox.pending_requests'));
            if (current === sequence) records.value = data.records || [];
        } else {
            const params = {
                mode: searchMode.value,
                hide_completed: hideCompleted.value ? 1 : 0,
            };
            if (searchMode.value === 'date') {
                params.date_range = dateRange.value;
            } else {
                if (selectedClientId.value) params.client_id = selectedClientId.value;
                if (selectedProjectId.value) params.project_job_id = selectedProjectId.value;
            }
            const { data } = await axios.get(route('user.myjobbox.past_data'), { params });
            if (current === sequence) {
                records.value = data.records || [];
                clients.value = data.clients || [];
                projects.value = data.projects || [];
            }
        }
    } catch {
        if (current === sequence) error.value = '候補を読み込めませんでした。もう一度開いてください。';
    } finally {
        if (current === sequence) loading.value = false;
    }
}, { immediate: true });

function choose(record, kind) {
    emit('selected', { record, kind });
}
</script>

<template>
    <div>
        <div class="mb-4 flex items-center justify-between gap-3">
            <h3 class="text-lg font-semibold text-gray-800">{{ title }}</h3>
            <button type="button" class="rounded border px-3 py-1.5 text-sm" @click="emit('cancel')">入力へ戻る</button>
        </div>

        <div v-if="pendingRecord" class="space-y-3 rounded border border-indigo-200 bg-indigo-50 p-4">
            <p class="text-sm">選択したジョブ：<strong>{{ pendingRecord.title }}</strong></p>
            <p class="text-sm text-gray-600">引用方法を選択してください。</p>
            <button type="button" class="block w-full rounded bg-orange-500 px-4 py-2 text-left text-sm text-white" @click="choose(pendingRecord, 'continue')">
                ↩ 続きとして設定（元ジョブと連動）
            </button>
            <p class="text-xs text-gray-600">作業時間を合算し、完了時には元ジョブも完了します。</p>
            <button type="button" class="block w-full rounded bg-indigo-600 px-4 py-2 text-left text-sm text-white" @click="choose(pendingRecord, 'new')">
                新規として引用（独立したジョブ）
            </button>
            <p class="text-xs text-gray-600">内容だけを引き継ぎます。</p>
            <button type="button" class="text-sm text-gray-600 underline" @click="pendingRecord = null">一覧へ戻る</button>
        </div>

        <template v-else>
            <p v-if="props.mode === 'request'" class="mb-4 text-sm text-gray-600">
                自分宛の未対応依頼を選ぶと、このマイジョブが依頼への対応済み登録として扱われます。
            </p>
            <template v-else>
                <button type="button" class="mb-3 rounded px-3 py-1.5 text-sm"
                    :class="hideCompleted ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700'"
                    @click="hideCompleted = !hideCompleted">
                    {{ hideCompleted ? '完了を非表示中' : '完了を表示しない' }}
                </button>
                <div class="mb-3 flex gap-2">
                    <button v-for="item in [{ key: 'date', label: '日付から検索' }, { key: 'project', label: '案件から検索' }]"
                        :key="item.key" type="button" class="rounded border px-3 py-1.5 text-sm"
                        :class="searchMode === item.key ? 'bg-blue-50 font-semibold text-blue-700' : 'text-gray-600'"
                        @click="searchMode = item.key">{{ item.label }}</button>
                </div>
                <div v-if="searchMode === 'date'" class="mb-4">
                    <select v-model="dateRange" class="rounded border px-3 py-1.5 text-sm">
                        <option value="yesterday">前日</option>
                        <option value="7days">過去7日</option>
                        <option value="30days">過去30日</option>
                    </select>
                </div>
                <div v-else class="mb-4 flex flex-wrap gap-3">
                    <label class="block text-xs text-gray-600">クライアント
                        <select v-model="selectedClientId" class="mt-1 block min-w-40 rounded border px-3 py-1.5 text-sm">
                            <option value="">すべて</option>
                            <option v-for="client in clients" :key="client.id" :value="String(client.id)">{{ client.name }}</option>
                        </select>
                    </label>
                    <label v-if="selectedClientId" class="block text-xs text-gray-600">案件
                        <select v-model="selectedProjectId" class="mt-1 block min-w-40 rounded border px-3 py-1.5 text-sm">
                            <option value="">すべて</option>
                            <option v-for="project in projects" :key="project.id" :value="String(project.id)">{{ project.title }}</option>
                        </select>
                    </label>
                </div>
            </template>

            <p v-if="error" role="alert" class="mb-3 rounded bg-red-50 p-3 text-sm text-red-700">{{ error }}</p>
            <div class="max-h-[52vh] overflow-auto rounded border">
                <p v-if="loading" class="p-6 text-center text-sm text-gray-500">読み込み中…</p>
                <p v-else-if="!records.length" class="p-6 text-center text-sm text-gray-500">
                    {{ props.mode === 'past' ? '該当データがありません' : '未対応の依頼ジョブはありません' }}
                </p>
                <table v-else class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="sticky top-0 bg-gray-50 text-left text-xs text-gray-600">
                        <tr v-if="props.mode === 'past'">
                            <th class="px-3 py-2">日付</th><th class="px-3 py-2">クライアント</th><th class="px-3 py-2">案件</th><th class="px-3 py-2">タイトル</th><th class="px-3 py-2">種別</th><th class="px-3 py-2">サイズ</th><th class="px-3 py-2">工程</th><th class="px-3 py-2">見積時間</th>
                        </tr>
                        <tr v-else>
                            <th class="px-3 py-2">依頼者</th><th class="px-3 py-2">案件</th><th class="px-3 py-2">タイトル</th><th class="px-3 py-2">締め切り</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="record in records" :key="record.id">
                            <template v-if="props.mode === 'past'">
                                <td class="px-3 py-2">{{ record.created_at || '—' }}</td>
                                <td class="px-3 py-2">{{ record.client_name || '—' }}</td>
                                <td class="px-3 py-2">{{ record.project_job_name || '—' }}</td>
                                <td class="px-3 py-2"><button type="button" class="text-left text-blue-700 underline" @click="pendingRecord = record">{{ record.title || '—' }}</button></td>
                                <td class="px-3 py-2">{{ record.work_item_type || '—' }}</td>
                                <td class="px-3 py-2">{{ record.size || '—' }}</td>
                                <td class="px-3 py-2">{{ record.stage || '—' }}</td>
                                <td class="px-3 py-2">{{ record.estimated_hours != null ? `${record.estimated_hours}h` : '—' }}</td>
                            </template>
                            <template v-else>
                                <td class="px-3 py-2">{{ record.sender_name || '—' }}</td>
                                <td class="px-3 py-2">{{ record.project_job_name || '—' }}</td>
                                <td class="px-3 py-2"><button type="button" class="text-left text-indigo-700 underline" @click="choose(record, 'request')">{{ record.title || '—' }}</button></td>
                                <td class="px-3 py-2">{{ record.desired_end_date || '—' }}</td>
                            </template>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>
    </div>
</template>
