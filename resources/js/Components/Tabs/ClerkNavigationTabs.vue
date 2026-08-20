<script setup>
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';

const props = defineProps({
    active: { type: String, default: '' },
});

const page = usePage();
const isDiaryManager = computed(() => page.props.auth?.isDiaryManager ?? false);

// Clerk カラー: purple
const tab = (key) => [
    'rounded-md px-3 py-2 text-sm font-medium',
    props.active === key
        ? 'bg-purple-100 text-purple-700'
        : 'border border-purple-200 text-purple-600 hover:bg-purple-50 hover:text-purple-800',
];

function tryRoute(name) {
    try { return route(name); } catch { return null; }
}

const tabs = computed(() => [
    { key: 'dashboard', href: tryRoute('clerk.dashboard'), label: 'ダッシュボード' },
    { key: 'announcements', href: tryRoute('clerk.announcements.index'), label: 'お知らせ通知' },
    { key: 'calendar', href: tryRoute('clerk.calendar'), label: 'カレンダー' },
    { key: 'diaries', href: tryRoute('diary_manager.diaryinteractions.index'), label: '日報管理', condition: isDiaryManager.value },
].filter(t => t.condition !== false && t.href));

function onMobileSelect(e) {
    const href = e.target.value;
    if (href) router.get(href);
}
</script>

<template>
    <div class="mb-4">
        <!-- モバイル: ドロップダウン -->
        <div class="sm:hidden">
            <select
                @change="onMobileSelect"
                class="w-full rounded-md border border-purple-300 bg-white px-3 py-2 text-sm text-purple-700 shadow-sm focus:border-purple-500 focus:outline-none focus:ring-1 focus:ring-purple-500"
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
            <Link :href="route('clerk.dashboard')" :class="tab('dashboard')">
                ダッシュボード
            </Link>
            <Link :href="route('clerk.announcements.index')" :class="tab('announcements')">
                お知らせ通知
            </Link>
            <Link :href="route('clerk.calendar')" :class="tab('calendar')">
                カレンダー
            </Link>
            <Link
                v-if="isDiaryManager"
                :href="route('diary_manager.diaryinteractions.index')"
                :class="tab('diaries')"
            >
                日報管理
            </Link>
        </nav>
    </div>
</template>
