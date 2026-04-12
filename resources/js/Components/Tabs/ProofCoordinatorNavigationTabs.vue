<script setup>
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    active:           { type: String, default: '' },
    pendingCount:     { type: Number, default: 0 },
});

// ProofCoordinator カラー: pink
const tab = (key) => [
    'rounded-md px-3 py-2 text-sm font-medium',
    props.active === key
        ? 'bg-pink-100 text-pink-700'
        : 'border border-pink-200 text-pink-600 hover:bg-pink-50 hover:text-pink-800',
];
</script>

<template>
    <div class="mb-6">
        <nav class="flex flex-wrap gap-2" aria-label="Tabs">
            <Link :href="route('proof_coordinator.inbox')" :class="tab('inbox')" class="relative">
                校正依頼受信
                <span
                    v-if="pendingCount > 0"
                    class="ml-1 inline-flex items-center rounded-full bg-red-500 px-1.5 py-0.5 text-xs font-bold text-white"
                >
                    {{ pendingCount }}
                </span>
            </Link>
            <Link :href="route('proof_coordinator.assignments')" :class="tab('assignments')">
                割り振り管理
            </Link>
            <Link :href="route('proof_coordinator.calendar')" :class="tab('calendar')">
                校正カレンダー
            </Link>
            <Link :href="route('proof_coordinator.workload')" :class="tab('workload')">
                校正員作業量
            </Link>
            <Link :href="route('proof_coordinator.history')" :class="tab('history')">
                案件校正履歴
            </Link>
            <Link :href="route('proof_coordinator.team.index')" :class="tab('team')">
                校正チーム管理
            </Link>
        </nav>
    </div>
</template>
