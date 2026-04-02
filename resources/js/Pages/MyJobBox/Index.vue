<template>
    <AppLayout :title="`MyJobBox - ${props.projectJob?.name || ''}`">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">MyJobBox — マイジョブ</h2>
        </template>

        <div class="mx-auto max-w-6xl rounded bg-white p-6 shadow">
            <h1 class="mb-4 text-2xl font-bold">MyJobBox：{{ props.projectJob?.name || '' }}</h1>

            <!-- 検索・フィルター行 -->
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div class="flex items-center gap-2">
                    <input
                        v-model="page.props.q_model"
                        @keyup.enter="search"
                        placeholder="タイトル/詳細で検索"
                        class="w-72 rounded border px-3 py-2 text-sm"
                    />
                    <button class="rounded bg-blue-600 px-3 py-2 text-white" @click.prevent="search">検索</button>
                    <button class="ml-2 rounded border px-3 py-2" @click.prevent="clearSearch">クリア</button>
                </div>
                <div>
                    <Link
                        :href="typeof route === 'function' ? route('user.project_jobs.assignments.create') : '/project_jobs/assignments/create-user'"
                        class="rounded bg-blue-600 px-4 py-2 text-white"
                        >新規ジョブ作成</Link
                    >
                </div>
            </div>

            <!-- 月セレクター -->
            <div class="mt-3 flex flex-wrap items-center gap-4">
                <div class="flex items-center gap-2">
                    <label class="text-sm text-gray-700">年月:</label>
                    <select
                        v-model="page.props.period_model"
                        @change="search"
                        class="rounded border px-3 py-2 text-sm"
                        style="width: 9.5em"
                    >
                        <option value="all">全期間</option>
                        <option v-for="m in monthOptions" :key="m.value" :value="m.value">
                            {{ m.label }}
                        </option>
                    </select>
                </div>
            </div>

            <!-- 日グループ表示 -->
            <div class="mt-4 overflow-x-auto">
                <div v-if="displayGroups.length === 0" class="py-8 text-center text-sm text-gray-400">
                    表示するデータがありません。
                </div>

                <template v-for="group in displayGroups" :key="group.date">
                    <!-- 日付ヘッダー -->
                    <div class="mt-4 rounded bg-gray-100 px-4 py-1.5 text-sm font-semibold text-gray-700 first:mt-0">
                        {{ group.label }}
                        <span class="ml-2 text-xs font-normal text-gray-500">{{ group.items.length }} 件</span>
                    </div>

                    <table class="min-w-full border">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">時間</th>
                                <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">タイトル</th>
                                <th class="border px-3 py-1.5 text-left text-xs font-medium text-gray-500">クライアント</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="m in group.items"
                                :key="m.id"
                                :class="['cursor-pointer hover:bg-gray-100', m.__is_new ? 'new-highlight' : '']"
                                @click.prevent="rowClick(m, $event)"
                                role="button"
                            >
                                <td class="border px-3 py-2 text-sm text-gray-600">{{ getStartTime(m) }}</td>
                                <td class="border px-3 py-2 text-sm">{{ m.title || '-' }}</td>
                                <td class="border px-3 py-2 text-sm text-gray-600">
                                    {{ m.projectJob?.client?.name || m.project_job?.client?.name || '-' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </template>
            </div>

            <!-- 件数 -->
            <div class="mt-4 flex items-center justify-between">
                <div class="text-sm text-gray-600">
                    表示中 {{ totalDisplayCount }} 件
                </div>
            </div>

            <div class="mt-4">
                <Link :href="getBackLink()" class="rounded bg-gray-200 px-4 py-2">戻る</Link>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({ projectJob: Object, messages: Object, myAssignments: Object });
const page = usePage();
page.props.q_model = page.props.q || '';
page.props.period_model = page.props.period ?? '';
const monthOptions = computed(() => (Array.isArray(page.props.monthOptions) ? page.props.monthOptions : []));

// ローカル状態（完了ボタンで即時更新するため）
// Inertia プロキシをシャローコピーして純粋な JS オブジェクトにする（Vue リアクティビティのため）
const toPlain = (arr) => (Array.isArray(arr) ? arr.map((item) => ({ ...item })) : []);
const localAssignments = ref(toPlain(props.myAssignments?.data));

// Inertia が props を更新した際（DB 最新値）に反映する
watch(() => props.myAssignments?.data, (newData) => {
    localAssignments.value = toPlain(newData);
});

// ===== ユーティリティ =====

function formatDateLabel(dateStr) {
    if (!dateStr) return '日付なし';
    try {
        const d = new Date(dateStr + 'T00:00:00');
        const y = d.getFullYear();
        const mo = d.getMonth() + 1;
        const day = d.getDate();
        const dow = ['日', '月', '火', '水', '木', '金', '土'][d.getDay()];
        return `${y}年${mo}月${day}日（${dow}）`;
    } catch (e) {
        return dateStr;
    }
}

function getDateKey(m) {
    // desired_at is a datetime field; extract date part
    const da = m.desired_at ? String(m.desired_at).split('T')[0].split(' ')[0] : null;
    const de = m.desired_end_date ? String(m.desired_end_date).split('T')[0] : null;
    const firstEvent = getFirstEvent(m);
    const ev = firstEvent ? String(firstEvent.start || firstEvent.starts_at || '').split('T')[0] : null;
    return da || de || ev || (m.created_at ? String(m.created_at).split('T')[0] : '') || '';
}

function getStartTime(m) {
    const firstEvent = getFirstEvent(m);
    if (firstEvent) {
        const s = firstEvent.start || firstEvent.starts_at || '';
        if (s.includes('T')) return s.split('T')[1]?.slice(0, 5) || '-';
        if (s.includes(' ')) return s.split(' ')[1]?.slice(0, 5) || '-';
    }
    const t = m.desired_time || '';
    if (!t) return '-';
    return String(t).slice(0, 5);
}

function getTimeKey(m) {
    const firstEvent = getFirstEvent(m);
    if (firstEvent) {
        const s = firstEvent.start || firstEvent.starts_at || '';
        if (s.includes('T')) return s.split('T')[1]?.slice(0, 5) || '00:00';
        if (s.includes(' ')) return s.split(' ')[1]?.slice(0, 5) || '00:00';
    }
    return m.desired_time ? String(m.desired_time).slice(0, 5) : '00:00';
}

function getFirstEvent(m) {
    try {
        if (m.events) {
            if (Array.isArray(m.events) && m.events.length) return m.events[0];
            if (m.events.data && Array.isArray(m.events.data) && m.events.data.length) return m.events.data[0];
        }
    } catch (e) {}
    return null;
}

// ===== 表示データ =====

// 日グループ（日付降順、同日内は開始時刻昇順）
const displayGroups = computed(() => {
    let assignments = Array.isArray(localAssignments.value) ? localAssignments.value : [];

    const grouped = new Map();
    for (const m of assignments) {
        const dk = getDateKey(m);
        if (!grouped.has(dk)) grouped.set(dk, []);
        grouped.get(dk).push(m);
    }

    for (const items of grouped.values()) {
        items.sort((a, b) => getTimeKey(a).localeCompare(getTimeKey(b)));
    }

    const sortedKeys = Array.from(grouped.keys()).sort((a, b) => {
        if (!a) return 1;
        if (!b) return -1;
        return b.localeCompare(a);
    });

    return sortedKeys.map((dk) => ({
        date: dk,
        label: formatDateLabel(dk),
        items: grouped.get(dk),
    }));
});

const totalDisplayCount = computed(() => displayGroups.value.reduce((sum, g) => sum + g.items.length, 0));

// ===== ナビゲーション =====

function search() {
    try {
        router.get(route('user.myjobbox.index'), { q: page.props.q_model, period: page.props.period_model }, { preserveState: false });
    } catch (err) {
        const params = new URLSearchParams();
        params.set('q', page.props.q_model || '');
        params.set('period', page.props.period_model === undefined ? '' : page.props.period_model);
        window.location.href = route('user.myjobbox.index') + '?' + params.toString();
    }
}

function clearSearch() {
    page.props.q_model = '';
    search();
}

function getBackLink() {
    try {
        if (props.projectJob?.id) {
            return typeof route === 'function' ? route('project_jobs.show', props.projectJob.id) : `/project_jobs/${props.projectJob.id}`;
        }
        return typeof route === 'function' ? route('dashboard') : '/';
    } catch (e) {
        return '/';
    }
}

function getAssignmentLink(m) {
    try {
        return typeof route === 'function' ? route('user.myjobbox.show', { assignment: m.id }) : `/myjobbox/${m.id}`;
    } catch (e) {
        return '#';
    }
}

async function rowClick(m, event) {
    const tag = event.target?.tagName?.toLowerCase() || '';
    if (tag === 'a' || tag === 'button' || event.target.closest?.('a,button')) return;

    // Try to navigate to linked event first
    try {
        const assId = m.id;
        const userId = m.user?.id || m.user_id || page.props.auth?.user?.id || '';
        if (assId) {
            let eventsUrl = null;
            try {
                eventsUrl = typeof route === 'function' ? route('events.index') : '/events';
                const query = [];
                if (userId) query.push('user_id=' + encodeURIComponent(userId));
                if (assId) query.push('job=' + encodeURIComponent(assId));
                if (query.length) eventsUrl += '?' + query.join('&');
            } catch (e) {
                eventsUrl = '/events?job=' + encodeURIComponent(assId);
            }
            try {
                const res = await fetch(eventsUrl, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (res.ok) {
                    const payload = await res.json();
                    if (Array.isArray(payload) && payload.length > 0) {
                        const ev = payload[0];
                        const evId = ev.id || ev.event_id || ev.extendedProps?.event_id || ev.extendedProps?.id;
                        if (evId) {
                            try { router.get(typeof route === 'function' ? route('events.show', evId) : '/events/' + evId); return; } catch {}
                            try { window.location.href = route('events.show', { event: evId }); return; } catch {}
                        }
                    }
                }
            } catch {}
        }
    } catch {}

    const url = getAssignmentLink(m);
    if (url && url !== '#') {
        try { router.visit(url, { preserveState: false }); } catch (e) { window.location.href = url; }
    }
}

</script>

<style scoped>
.new-highlight { background-color: #fff7cc; }
</style>
