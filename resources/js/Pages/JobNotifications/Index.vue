<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { ref, computed } from 'vue';

const props = defineProps({
    notifications: Array,
    filters: Object,
});

// ---- ホバー既読 ----
const localNotifications = ref((props.notifications || []).map(n => ({ ...n })));

const hoverTimers = {};

function onMouseEnter(n) {
    if (n.read_at) return;
    hoverTimers[n.id] = setTimeout(() => {
        markReadHover(n);
    }, 500);
}

function onMouseLeave(n) {
    clearTimeout(hoverTimers[n.id]);
    delete hoverTimers[n.id];
}

function markReadHover(n) {
    const found = localNotifications.value.find(x => x.id === n.id);
    if (!found || found.read_at) return;

    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    fetch(route('job-notifications.markRead', { jobNotification: n.id }), {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': token,
            'Accept': 'application/json',
        },
        credentials: 'same-origin',
    }).then(res => {
        if (res.ok) {
            found.read_at = new Date().toISOString();
        }
    }).catch(() => {});
}
// ---- /ホバー既読 ----

const viewMode      = ref(props.filters?.group === 'month' ? 'month' : 'day');
const selectedDays  = ref(props.filters?.days ?? 30);
const unreadOnly    = ref(false);

function applyFilters() {
    router.get(route('job-notifications.index'), {
        group: viewMode.value,
        days:  selectedDays.value,
    }, { preserveState: false });
}

const groupedNotifications = computed(() => {
    const map = {};
    const source = unreadOnly.value
        ? (localNotifications.value || []).filter(n => !n.read_at)
        : (localNotifications.value || []);
    source.forEach((n) => {
        let raw = '不明';
        if (n.created_at) {
            const d = toJST(n.created_at);
            if (d) {
                raw = d.toLocaleDateString('sv-SE', { timeZone: 'Asia/Tokyo' }); // YYYY-MM-DD
            } else {
                raw = String(n.created_at).substring(0, 10);
            }
        }
        const key = viewMode.value === 'month' ? raw.substring(0, 7) : raw;
        if (!map[key]) map[key] = [];
        map[key].push(n);
    });
    return Object.fromEntries(
        Object.entries(map).sort(([a], [b]) => (a < b ? 1 : a > b ? -1 : 0))
    );
});

function formatGroupKey(key) {
    if (!key || key === '不明') return '不明';
    if (viewMode.value === 'month') {
        const [y, m] = key.split('-');
        return `${y}年${m}月`;
    }
    const [y, m, d] = key.split('-');
    return `${y}/${m}/${d}`;
}

// UTC文字列をJST (Asia/Tokyo) に変換するヘルパー
function toJST(dateStr) {
    if (!dateStr) return null;
    try {
        // 末尾の Z や +offset を除去してから Z を付与（二重 Z を防ぐ）
        const normalized = String(dateStr).replace(' ', 'T').replace(/[Zz]$|[+\-]\d{2}:\d{2}$/, '') + 'Z';
        const d = new Date(normalized);
        return isNaN(d.getTime()) ? null : d;
    } catch {
        return null;
    }
}

function formatTime(dateStr) {
    if (!dateStr) return '';
    const d = toJST(dateStr);
    if (!d) return '';
    const h = String(d.toLocaleString('ja-JP', { timeZone: 'Asia/Tokyo', hour: '2-digit', hour12: false })).padStart(2, '0');
    const m = String(d.toLocaleString('ja-JP', { timeZone: 'Asia/Tokyo', minute: '2-digit' })).padStart(2, '0');
    return `${h}:${m}`;
}

const TYPE_LABELS = {
    new_job:             { label: '新規依頼',   cls: 'bg-blue-100 text-blue-800' },
    new_job_info:        { label: '依頼情報',   cls: 'bg-indigo-100 text-indigo-800' },
    completed:           { label: '完了報告',   cls: 'bg-yellow-100 text-yellow-800' },
    completed_info:      { label: '完了情報',   cls: 'bg-amber-100 text-amber-800' },
    progress_registered: { label: '進行表登録', cls: 'bg-green-100 text-green-800' },
    progress_completed:  { label: '進行表完了', cls: 'bg-teal-100 text-teal-800' },
};

function typeMeta(type) {
    return TYPE_LABELS[type] ?? { label: type, cls: 'bg-gray-100 text-gray-700' };
}
</script>

<template>
    <AppLayout title="ジョブ通知">
        <div class="rounded bg-white shadow">

            <!-- ヘッダー + フィルター -->
            <div class="border-b px-6 py-4">
                <div class="flex flex-wrap items-center gap-3">
                    <h2 class="text-lg font-semibold text-gray-800">ジョブ通知</h2>
                    <div class="ml-auto flex flex-wrap items-center gap-2">
                        <label class="text-sm text-gray-600">表示:</label>
                        <select v-model="viewMode" class="rounded border px-2 py-1 text-sm">
                            <option value="day">日別表示</option>
                            <option value="month">月別表示</option>
                        </select>

                        <label class="text-sm text-gray-600">期間:</label>
                        <select v-model.number="selectedDays" class="rounded border px-2 py-1 text-sm">
                            <option :value="7">7日分</option>
                            <option :value="30">30日分</option>
                            <option :value="90">90日分</option>
                        </select>

                        <button
                            @click="unreadOnly = !unreadOnly"
                            class="rounded border px-3 py-1 text-xs transition"
                            :class="unreadOnly ? 'border-blue-600 bg-blue-600 text-white' : 'border-gray-300 bg-white text-gray-600 hover:bg-gray-50'"
                        >
                            未読のみ
                        </button>

                        <button
                            @click="applyFilters"
                            class="rounded bg-blue-600 px-3 py-1 text-xs text-white hover:bg-blue-700"
                        >
                            適用
                        </button>
                    </div>
                </div>
            </div>

            <!-- 注意書き -->
            <div class="border-b bg-blue-50 px-6 py-2 text-xs text-blue-600">
                ※ 通知の上にマウスを0.5秒ホバーすると自動で既読になります。
            </div>

            <!-- 通知リスト（グループ別） -->
            <div v-if="localNotifications.length > 0">
                <div
                    v-for="(list, groupKey) in groupedNotifications"
                    :key="groupKey"
                    class="border-b last:border-b-0"
                >
                    <!-- グループヘッダー -->
                    <div class="bg-gray-50 px-6 py-2">
                        <h3 class="text-sm font-semibold text-gray-600">{{ formatGroupKey(groupKey) }}</h3>
                    </div>

                    <!-- 通知行 -->
                    <ul class="divide-y divide-gray-100">
                        <li
                            v-for="n in list"
                            :key="n.id"
                            @mouseenter="onMouseEnter(n)"
                            @mouseleave="onMouseLeave(n)"
                        >
                            <Link
                                :href="route('job-notifications.show', { jobNotification: n.id })"
                                class="flex items-start gap-3 px-6 py-3 hover:bg-gray-50"
                                :class="n.read_at ? 'bg-white' : 'bg-blue-50'"
                            >
                                <!-- 未読/既読アイコン -->
                                <div class="mt-0.5 flex-shrink-0">
                                    <!-- 未読: 青い封筒（filled） -->
                                    <svg
                                        v-if="!n.read_at"
                                        xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 24 24"
                                        fill="currentColor"
                                        class="h-5 w-5 text-blue-500"
                                    >
                                        <path d="M1.5 8.67v8.58a3 3 0 003 3h15a3 3 0 003-3V8.67l-8.928 5.493a3 3 0 01-3.144 0L1.5 8.67z" />
                                        <path d="M22.5 6.908V6.75a3 3 0 00-3-3h-15a3 3 0 00-3 3v.158l9.714 5.978a1.5 1.5 0 001.572 0L22.5 6.908z" />
                                    </svg>
                                    <!-- 既読: グレーの開封封筒（outline） -->
                                    <svg
                                        v-else
                                        xmlns="http://www.w3.org/2000/svg"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke-width="1.5"
                                        stroke="currentColor"
                                        class="h-5 w-5 text-gray-300"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 9v.906a2.25 2.25 0 01-1.183 1.981l-6.478 3.488M2.25 9v.906a2.25 2.25 0 001.183 1.981l6.478 3.488m8.839 2.51l-4.66-2.51m0 0l-1.023-.55a2.25 2.25 0 00-2.134 0l-1.022.55m0 0l-4.661 2.51m16.5 1.615a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V8.844a2.25 2.25 0 011.183-1.981l7.5-4.039a2.25 2.25 0 012.134 0l7.5 4.039a2.25 2.25 0 011.183 1.98V19.5z" />
                                    </svg>
                                </div>

                                <!-- 本文 -->
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span
                                            class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="typeMeta(n.type).cls"
                                        >
                                            {{ typeMeta(n.type).label }}
                                        </span>
                                        <span class="text-xs text-gray-400">{{ formatTime(n.created_at) }}</span>
                                        <span v-if="!n.read_at" class="text-xs font-semibold text-blue-600">● 未読</span>
                                    </div>
                                    <p
                                        class="mt-1 text-sm"
                                        :class="n.read_at ? 'text-gray-600' : 'font-medium text-gray-900'"
                                    >
                                        {{ n.message }}
                                    </p>
                                    <p v-if="n.project_job?.title" class="mt-0.5 text-xs text-gray-400">
                                        案件: {{ n.project_job.title }}
                                    </p>
                                </div>
                            </Link>
                        </li>
                    </ul>
                </div>
            </div>

            <div v-else class="px-6 py-12 text-center text-sm text-gray-500">
                この期間に通知はありません
            </div>
        </div>
    </AppLayout>
</template>
