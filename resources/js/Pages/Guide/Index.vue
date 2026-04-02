<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const userRole = computed(() => page.props.auth?.user?.user_role || 'user');

const canSeeCoordinator = computed(() =>
    ['coordinator', 'leader', 'admin', 'superadmin'].includes(userRole.value)
);
const canSeeLeader = computed(() =>
    ['leader', 'admin', 'superadmin'].includes(userRole.value)
);
const canSeeAdmin = computed(() =>
    ['admin', 'superadmin'].includes(userRole.value)
);

const guides = computed(() => {
    const all = [
        {
            key: 'admin',
            title: '管理者向けガイド',
            subtitle: 'Admin',
            description: 'ユーザー・部署・日報・分析など、会社全体を管理するAdmin向けの説明書です。',
            icon: '🛡️',
            route: 'guide.admin',
            bgFrom: 'from-red-50',
            bgTo: 'to-rose-50',
            border: 'border-red-200',
            badge: 'bg-red-100 text-red-700',
            btn: 'bg-red-600 hover:bg-red-700 text-white',
            iconBg: 'bg-red-100',
            visible: canSeeAdmin.value,
        },
        {
            key: 'leader',
            title: 'リーダー向けガイド',
            subtitle: 'Leader',
            description: 'チーム・ユーザー管理、日報確認、作業量分析など、リーダーが使う機能をまとめた説明書です。',
            icon: '👑',
            route: 'guide.leader',
            bgFrom: 'from-orange-50',
            bgTo: 'to-amber-50',
            border: 'border-orange-200',
            badge: 'bg-orange-100 text-orange-700',
            btn: 'bg-orange-500 hover:bg-orange-600 text-white',
            iconBg: 'bg-orange-100',
            visible: canSeeLeader.value,
        },
        {
            key: 'coordinator',
            title: 'コーディネーター向けガイド',
            subtitle: 'Coordinator',
            description: 'クライアント管理、案件登録、ジョブの割り当てなど、進行管理担当者向けの説明書です。',
            icon: '🗂️',
            route: 'guide.coordinator',
            bgFrom: 'from-green-50',
            bgTo: 'to-emerald-50',
            border: 'border-green-200',
            badge: 'bg-green-100 text-green-700',
            btn: 'bg-green-600 hover:bg-green-700 text-white',
            iconBg: 'bg-green-100',
            visible: canSeeCoordinator.value,
        },
        {
            key: 'user',
            title: 'ユーザー向けガイド',
            subtitle: 'User',
            description: 'ジョブの受け取り方、予定表の使い方、日報の書き方など、一般ユーザー向けの説明書です。',
            icon: '📋',
            route: 'guide.user',
            bgFrom: 'from-blue-50',
            bgTo: 'to-sky-50',
            border: 'border-blue-200',
            badge: 'bg-blue-100 text-blue-700',
            btn: 'bg-blue-500 hover:bg-blue-600 text-white',
            iconBg: 'bg-blue-100',
            visible: true,
        },
    ];
    return all.filter((g) => g.visible);
});
</script>

<template>
    <AppLayout title="使い方ガイド">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">使い方ガイド</h2>
        </template>

        <div class="rounded-lg bg-white p-8 shadow">
            <!-- ヘッダー -->
            <div class="mb-10 text-center">
                <div class="mb-3 text-5xl">📖</div>
                <h1 class="mb-2 text-3xl font-bold text-gray-800">SunBWork 使い方ガイド</h1>
                <p class="text-gray-500">あなたの役割に合ったガイドを選んでください。</p>
            </div>

            <!-- ガイドカード -->
            <div
                class="grid gap-6"
                :class="{
                    'grid-cols-1': guides.length === 1,
                    'grid-cols-1 md:grid-cols-2': guides.length === 2,
                    'grid-cols-1 md:grid-cols-3': guides.length === 3,
                    'grid-cols-1 md:grid-cols-2 lg:grid-cols-4': guides.length === 4,
                }"
            >
                <Link
                    v-for="g in guides"
                    :key="g.key"
                    :href="route(g.route)"
                    class="group block rounded-2xl border-2 bg-gradient-to-br p-6 shadow-sm transition-all duration-200 hover:-translate-y-1 hover:shadow-md"
                    :class="[g.bgFrom, g.bgTo, g.border]"
                >
                    <!-- アイコン + バッジ -->
                    <div class="mb-4 flex items-start justify-between">
                        <div class="flex h-14 w-14 items-center justify-center rounded-xl text-3xl" :class="g.iconBg">
                            {{ g.icon }}
                        </div>
                        <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="g.badge">
                            {{ g.subtitle }}
                        </span>
                    </div>

                    <!-- タイトル -->
                    <h2 class="mb-2 text-xl font-bold text-gray-800 group-hover:underline">{{ g.title }}</h2>
                    <p class="mb-6 text-sm leading-relaxed text-gray-500">{{ g.description }}</p>

                    <!-- ボタン -->
                    <div class="flex items-center justify-between">
                        <span class="rounded-lg px-4 py-2 text-sm font-semibold transition-colors" :class="g.btn">
                            ガイドを読む →
                        </span>
                    </div>
                </Link>
            </div>

            <!-- フッターメモ -->
            <p class="mt-10 text-center text-xs text-gray-400">
                表示されるガイドはあなたの権限に応じて異なります。
            </p>
        </div>
    </AppLayout>
</template>
