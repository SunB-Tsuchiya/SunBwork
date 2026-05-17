<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';

const props = defineProps({
    active:       { type: String, default: '' },
    pendingCount: { type: Number, default: 0 },
});

// ProofCoordinator カラー: pink
const tab = (key) => [
    'rounded-md px-3 py-2 text-sm font-medium',
    props.active === key
        ? 'bg-pink-100 text-pink-700'
        : 'border border-pink-200 text-pink-600 hover:bg-pink-50 hover:text-pink-800',
];

function tryRoute(name) {
    try { return route(name); } catch { return null; }
}

const tabs = computed(() => [
    { key: 'dashboard', href: tryRoute('proof_coordinator.dashboard'), label: 'ダッシュボード' },
    {
        key: 'inbox',
        href: tryRoute('proof_coordinator.inbox'),
        label: '校正依頼受信' + (props.pendingCount > 0 ? ` (${props.pendingCount}件)` : ''),
    },
    { key: 'jobs', href: tryRoute('proof_coordinator.jobs'), label: 'ジョブ管理' },
    { key: 'calendar', href: tryRoute('proof_coordinator.calendar'), label: '校正カレンダー' },
    { key: 'workload', href: tryRoute('proof_coordinator.workload'), label: '校正員作業量' },
    { key: 'team', href: tryRoute('proof_coordinator.team.index'), label: '校正チーム管理' },
    { key: 'dispatchers', href: tryRoute('proof_coordinator.dispatchers.index'), label: '単発派遣管理' },
].filter(t => t.href));

function onMobileSelect(e) {
    const href = e.target.value;
    if (href) router.get(href);
}
</script>

<template>
    <div class="mb-6">
        <!-- モバイル: ドロップダウン -->
        <div class="sm:hidden">
            <select
                @change="onMobileSelect"
                class="w-full rounded-md border border-pink-300 bg-white px-3 py-2 text-sm text-pink-700 shadow-sm focus:border-pink-500 focus:outline-none focus:ring-1 focus:ring-pink-500"
            >
                <option value="">— ページを選択 —</option>
                <option
                    v-for="t in tabs"
                    :key="t.key"
                    :value="t.href"
                    :selected="active === t.key"
                >{{ t.label }}</option>
            </select>
        </div>

        <!-- デスクトップ: タブ -->
        <nav class="hidden sm:flex flex-wrap gap-2" aria-label="Tabs">
            <Link :href="route('proof_coordinator.dashboard')" :class="tab('dashboard')">
                ダッシュボード
            </Link>
            <Link :href="route('proof_coordinator.inbox')" :class="tab('inbox')" class="relative">
                校正依頼受信
                <span
                    v-if="pendingCount > 0"
                    class="ml-1 inline-flex items-center rounded-full bg-red-500 px-1.5 py-0.5 text-xs font-bold text-white"
                >
                    {{ pendingCount }}
                </span>
            </Link>
            <Link :href="route('proof_coordinator.jobs')" :class="tab('jobs')">
                ジョブ管理
            </Link>
            <Link :href="route('proof_coordinator.calendar')" :class="tab('calendar')">
                校正カレンダー
            </Link>
            <Link :href="route('proof_coordinator.workload')" :class="tab('workload')">
                校正員作業量
            </Link>
            <Link :href="route('proof_coordinator.team.index')" :class="tab('team')">
                校正チーム管理
            </Link>
            <Link :href="route('proof_coordinator.dispatchers.index')" :class="tab('dispatchers')">
                単発派遣管理
            </Link>
        </nav>
    </div>
</template>
