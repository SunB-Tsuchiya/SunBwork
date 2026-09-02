<script setup>
import ApplicationMark from '@/Components/ApplicationMark.vue';
import Banner from '@/Components/Banner.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import IrukaStatusBadge from '@/Components/Iruka/IrukaStatusBadge.vue';
import IrukaMobileStatusButton from '@/Components/Iruka/IrukaMobileStatusButton.vue';
import AdminNavigationTabs from '@/Components/Tabs/AdminNavigationTabs.vue';
import ClerkNavigationTabs from '@/Components/Tabs/ClerkNavigationTabs.vue';
import PrepressNavigationTabs from '@/Components/Tabs/PrepressNavigationTabs.vue';
import CoordinatorNavigationTabs from '@/Components/Tabs/CoordinatorNavigationTabs.vue';
import LeaderNavigationTabs from '@/Components/Tabs/LeaderNavigationTabs.vue';
import ProofCoordinatorNavigationTabs from '@/Components/Tabs/ProofCoordinatorNavigationTabs.vue';
import SuperAdminNavigationTabs from '@/Components/Tabs/SuperAdminNavigationTabs.vue';
import UserNavigationTabs from '@/Components/Tabs/UserNavigationTabs.vue';
import ToastUnified from '@/Components/ToastUnified.vue';
import CompanyModuleNavButtons from '@/Components/CompanyModuleNavButtons.vue';
import SuperAdminContextSwitcher from '@/Components/SuperAdminContextSwitcher.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, provide, ref } from 'vue';

defineProps({
    title: String,
    fluid: { type: Boolean, default: false },
});

const showingNavigationDropdown = ref(false);

const switchToTeam = (team) => {
    router.put(
        route('current-team.update'),
        {
            team_id: team.id,
        },
        {
            preserveState: false,
        },
    );
};

const logout = () => {
    router.post(route('logout'));
};

import useToasts from '@/Composables/useToasts';
import { usePage } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref as vueRef, watch } from 'vue';

const page = usePage();
// shared toast API (shared composable)
useToasts();
// Keep `user` available for templates (many pages pass a `user` prop).
// Use `authUser` for realtime subscriptions to avoid subscribing to the
// resource being viewed when it's not the logged-in user.
const user = page.props.user;
const authUser = page.props.auth && page.props.auth.user ? page.props.auth.user : page.props.user;
const canAccessSalesAnalysis = computed(() => page.props.auth?.canAccessSalesAnalysis ?? false);
const canShowPrepressNav = computed(() => {
    if (authUser?.isSuperAdmin) return true;

    return page.props.auth?.companyType === 'sunbrain'
        && (authUser?.isAdmin || authUser?.isPrepressDepartment);
});

// Provide `authUser` and `user` to descendant components via Vue's provide/inject
// so components like AssignmentForm_user.vue can access them even when
// $page props aren't available due to different mounting contexts.
try {
    provide('authUser', authUser);
    provide('user', user);
    // Provide without verbose debug logging in production
} catch {
    // provide may fail in some SSR or test contexts; non-fatal
}
// Use unread_messages_count as the single notification source; job_requests are being
// migrated to Messages so we stop subscribing to jobrequests channel here.
const unreadMessages = vueRef(page.props.user?.unread_messages_count || 0);
// job-specific unread count provided by server when available
const unreadJobMessages = vueRef(page.props.user?.unread_job_messages_count || 0);
// お知らせ未読数
const unreadAnnouncements = vueRef(page.props.unreadAnnouncements || 0);
watch(
    () => page.props.unreadAnnouncements,
    (v) => { unreadAnnouncements.value = v || 0; },
);
// ジョブ通知未読数
const unreadJobNotifications = vueRef(page.props.unreadJobNotifications || 0);
watch(
    () => page.props.unreadJobNotifications,
    (v) => { unreadJobNotifications.value = v || 0; },
);
// 予定表通知未読数
const unreadScheduleNotifications = vueRef(page.props.unreadScheduleNotifications || 0);
watch(
    () => page.props.unreadScheduleNotifications,
    (v) => { unreadScheduleNotifications.value = v || 0; },
);

// keep reactive when Inertia page props update
watch(
    () => page.props.user && page.props.user.unread_job_messages_count,
    (v) => {
        unreadJobMessages.value = v || 0;
    },
);
let echoChannel = null;

onMounted(() => {
    // ゴーストモード中にブラウザ/タブを閉じたとき自動でセッション終了
    if (isGhostMode.value) {
        const handlePageHide = (event) => {
            if (event.persisted) return;
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            if (!csrf) return;
            navigator.sendBeacon(route('coordinator.ghost.exit'), new URLSearchParams({ _token: csrf }));
        };
        window.addEventListener('pagehide', handlePageHide);
        window.__ghostPageHideHandler = handlePageHide;
    }

    // AppLayout mounted
    try {
        // minimal mount handling; avoid verbose console output
        if (window.Echo && authUser && authUser.id) {
            // messages channel (primary notification source)
            window.Echo.private('messages.' + authUser.id).listen('MessageCreated', (e) => {
                // Only increment general unread messages for non-job messages
                if (!e.is_job) {
                    unreadMessages.value = (unreadMessages.value || 0) + 1;
                }
                const msg = (e.from_user_name ? e.from_user_name + 'さんからメールが届きました: ' : '新しいメール: ') + (e.subject || '(件名なし)');
                // include the message id if provided so clients can dedupe identical events
                const mid = e.id || e.message_id || null;
                window.dispatchEvent(new CustomEvent('message:received', { detail: { message: msg, id: mid, origin: 'message' } }));
            });
            // listen for reads to decrement general unread count
            window.Echo.private('messages.' + authUser.id).listen('MessageRead', (e) => {
                try {
                    if (!e.is_job) {
                        unreadMessages.value = Math.max(0, (unreadMessages.value || 0) - 1);
                        window.dispatchEvent(new CustomEvent('message:read', { detail: { message_id: e.message_id } }));
                    }
                } catch {}
            });

            // job-specific channel: separate unread counter and events
            window.Echo.private('jobmessages.' + authUser.id).listen('JobMessageCreated', (e) => {
                // increment only the job-specific badge
                unreadJobMessages.value = (unreadJobMessages.value || 0) + 1;
                try {
                    const from = e.from_user_name ? `${e.from_user_name}さん` : '誰か';
                    const subj = e.subject || '(件名なし)';
                    const msg = `${from} からジョブ関連のメッセージが届きました: ${subj}`;
                    // include jam id or job_assignment_message_id when present so clients can dedupe
                    const jid = e.job_assignment_message_id || e.message_id || (e.jam && e.jam.id) || null;
                    window.dispatchEvent(new CustomEvent('message:received', { detail: { message: msg, id: jid, origin: 'job' } }));
                } catch {
                    // non-fatal
                }
            });
            window.Echo.private('jobmessages.' + authUser.id).listen('JobMessageRead', () => {
                try {
                    unreadJobMessages.value = Math.max(0, (unreadJobMessages.value || 0) - 1);
                } catch {}
            });
            // AssignmentStatusToast is handled centrally by ToastUnified.vue (subscribe to "toasts" channel)
        }
    } catch {
        // Echo subscribe failed (non-fatal)
    }

    // セッション Keep-Alive: 10分ごとにサーバーへ ping してセッションを維持する
    const pingInterval = setInterval(() => {
        window.axios.get(route('ping')).catch(() => {});
    }, 10 * 60 * 1000);
    window.__sessionPingInterval = pingInterval;
});

onBeforeUnmount(() => {
    if (window.__ghostPageHideHandler) {
        window.removeEventListener('pagehide', window.__ghostPageHideHandler);
        delete window.__ghostPageHideHandler;
    }
    try {
        if (echoChannel && window.Echo) {
            window.Echo.leavePrivate('jobrequests.' + authUser.id);
            window.Echo.leavePrivate('messages.' + authUser.id);
            echoChannel = null;
        }
    } catch {}
    clearInterval(window.__sessionPingInterval);
});
// Debug logs removed for production

// Determine an "active" key for top tabs based on current route name
const getTopTabActive = () => {
    try {
        const r = route().current();
        if (!r) return '';
        if (r.includes('admin_permissions')) return 'admin_permissions';
        if (r.includes('position_titles')) return 'position_titles';
        if (r.includes('users') || r.includes('adminusers')) return 'users';
        if (r.includes('companies')) return 'companies';
        if (r.includes('debug') || r.includes('api')) return 'debug';
        if (r.includes('diary_teams')) return 'diary_teams';
        if (r.includes('teams')) return 'teams';
        if (r.includes('clients')) return 'clients';
        if (r.includes('workload_setting')) return 'workload_setting';
        if (r.includes('workload_analyzer')) return 'workload';
        if (r.includes('project_jobs')) return 'project_jobs';
        if (r.includes('diaryinteractions') || r.includes('diaries')) return 'diaries';
        if (r.includes('ai')) return 'ai';
        if (r.includes('calendar')) return 'calendar';
        if (r.startsWith('team-rooms')) return 'team_rooms';
        if (r.includes('myjobbox') || r === 'dashboard') return 'myjob';
        if (r.includes('user.jobbox')) return 'jobbox';
        if (r.includes('announcements')) return 'announcements';
        if (r.includes('profile')) return 'profile';
        if (r.endsWith('.dashboard')) return 'dashboard';
        if (r.includes('presence')) return 'presence_board_settings';
        if (r.includes('proof_jobs')) return 'proof_jobs';
        if (r.includes('user.proof')) return 'proof_status';
        if (r.includes('worktypes')) return 'worktypes';
        if (r.includes('work_records')) return 'work_records';
        if (r.includes('leader_permissions')) return 'leader_permissions';
        if (r.includes('meeting_definitions')) return 'meeting_definitions';
        if (r.includes('meeting-rooms') || r.includes('meeting_rooms')) return 'meeting_rooms';
        if (r.includes('dispatch')) return 'dispatch';
        if (r.startsWith('user.settings')) return 'settings';
        return '';
    } catch {
        return '';
    }
};
// role nav link class — active role gets solid background + white text
function roleNavClass(role) {
    const base = 'inline-flex items-center justify-center rounded-md min-w-[7rem] px-3 py-1 text-sm font-medium transition-colors';
    const activeMap = {
        superadmin:  'bg-yellow-500 text-white font-semibold',
        admin:       'bg-red-500 text-white font-semibold',
        leader:      'bg-orange-500 text-white font-semibold',
        clerk:             'bg-purple-600 text-white font-semibold',
        coordinator:       'bg-green-600 text-white font-semibold',
        proof_coordinator: 'bg-pink-600 text-white font-semibold',
        user:              'bg-blue-500 text-white font-semibold',
        prepress:          'bg-green-700 text-white font-semibold',
    };
    const inactiveMap = {
        superadmin:        'text-yellow-600 hover:text-yellow-800',
        admin:             'text-red-600 hover:text-red-800',
        leader:            'text-orange-600 hover:text-orange-800',
        clerk:             'text-purple-600 hover:text-purple-800',
        coordinator:       'text-green-600 hover:text-green-800',
        proof_coordinator: 'text-pink-600 hover:text-pink-800',
        user:              'text-blue-600 hover:text-blue-800',
        prepress:          'text-green-700 hover:text-green-900',
    };
    return `${base} ${currentRouteContext.value === role ? activeMap[role] : inactiveMap[role]}`;
}

// compute active key for coordinator tabs
const computeCoordinatorActive = () => {
    try {
        const r = route().current();
        if (!r) return '';
        // clients routes → clients tab
        if (r.startsWith('coordinator.clients')) return 'clients';
        // subcontractors routes → subcontractors tab
        if (r.startsWith('coordinator.subcontractors')) return 'subcontractors';
        // jobbox routes → jobs tab
        if (r.includes('jobbox')) return 'jobs';
        // assignments routes → jobs tab
        if (r.includes('assignments')) return 'jobs';
        // calendar → calendar tab
        if (r.includes('calendar')) return 'calendar';
        // explicit project_jobs routes (index/show/create/edit) → projects tab
        if (r.match(/^coordinator\.project_jobs\.(index|show|create|edit|store|update|destroy|complete)$/)) return 'projects';
        if (r.startsWith('coordinator.progress_sheet_list')) return 'progress_sheet_list';
        if (r.startsWith('coordinator.progress_report')) return 'progress_report';
        if (r.startsWith('coordinator.settings')) return 'settings';
        if (r.startsWith('coordinator.ghost_users')) return 'ghost_users';
        if (r === 'coordinator.dashboard') return 'dashboard';
        return '';
    } catch {
        return '';
    }
};

const isGhostMode = computed(() => !!page.props.user?.is_ghost);

const canAccessScripts = computed(() => page.props.auth?.canAccessScripts ?? false);

// Determine which role "area" the current route belongs to.
// SuperAdmin/Admin can navigate to lower-role areas; use route prefix to detect.
const currentRouteContext = computed(() => {
    try {
        const r = route().current();
        if (!r) return page.props.auth?.user?.user_role || 'user';
        if (r.startsWith('superadmin.')) return 'superadmin';
        if (r.startsWith('admin.')) return 'admin';
        if (r.startsWith('leader.') || r.startsWith('workload_setting.')) return 'leader';
        if (r.startsWith('coordinator.')) return 'coordinator';
        if (r.startsWith('proof_coordinator.')) return 'proof_coordinator';
        if (r.startsWith('clerk.')) return 'clerk';
        if (r.startsWith('prepress.')) return 'prepress';
        if (r.startsWith('diary_manager.')) return page.props.auth?.user?.user_role || 'user';
        // user.project_jobs.* / user.jobbox.* は user エリア
        // それ以外の user.* も user エリア
        return 'user';
    } catch {
        return page.props.auth?.user?.user_role || 'user';
    }
});

// ロール別ダッシュボードルート名
const ROLE_DASHBOARD_ROUTE = {
    superadmin:       'superadmin.dashboard',
    admin:            'admin.dashboard',
    leader:           'leader.dashboard',
    coordinator:      'coordinator.dashboard',
    proof_coordinator:'proof_coordinator.dashboard',
    clerk:            'clerk.dashboard',
    user:             'user.dashboard',
    prepress:         'prepress.dashboard',
};

// プレフィックスなしの /dashboard はロール判定用の中継ルートで、
// アクセスするとサーバー側でユーザーのuser_roleに応じて自動的に
// role別ダッシュボードへ302リダイレクトされる。このURLを「最後にいた
// ページ」として保存・使用してしまうと、常に元のロールへ戻される
// リダイレクトループのように見える不具合になるため除外する。
function pathnameOf(url) {
    try { return new URL(url, window.location.origin).pathname; } catch { return url; }
}

// ページ遷移時に現ロールの最終URLをlocalStorageに保存
watch(
    () => page.url,
    (url) => {
        const role = currentRouteContext.value;
        if (role && url && pathnameOf(url) !== '/dashboard') {
            try { localStorage.setItem(`lastTab_${role}`, url); } catch {}
        }
    },
    { immediate: true },
);

// ロールボタン押下: 保存済みURL → なければdashboard
// ただし現在いるロールエリア自身のタブを押した場合、lastTabは直前の
// watchで現在URLに上書きされ「今のページに戻る」だけになり無反応に見えるため、
// 常にそのロールのダッシュボードへ遷移させる
function navigateToRole(role) {
    try {
        if (role === currentRouteContext.value) {
            router.get(route(ROLE_DASHBOARD_ROUTE[role]));
            return;
        }
        const saved = localStorage.getItem(`lastTab_${role}`);
        // 過去に汚染されて保存された /dashboard は使わず、自己修復する
        if (saved && pathnameOf(saved) !== '/dashboard') {
            router.get(saved);
        } else {
            router.get(route(ROLE_DASHBOARD_ROUTE[role]));
        }
    } catch {
        router.get('/');
    }
}
</script>

<template>
    <div>
        <Head :title="title" />
        <Head>
            <meta name="csrf-token" :content="$page.props.csrf_token" />
        </Head>
        <Banner />

        <!-- ゴーストモードバナー -->
        <div v-if="isGhostMode" class="sticky top-0 z-50 flex items-center justify-between bg-amber-400 px-4 py-2 text-sm font-medium text-amber-900">
            <span>テストモード中（{{ page.props.user.name }} として操作中）</span>
            <button
                type="button"
                class="rounded bg-amber-700 px-3 py-1 text-white hover:bg-amber-800"
                @click="router.post(route('coordinator.ghost.exit'))"
            >
                Coordinator に戻る
            </button>
        </div>

        <div class="min-h-screen bg-gray-100">
            <nav class="relative z-10 border-b border-gray-100 bg-white">
                <!-- Primary Navigation Menu -->
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <!-- Row 1: Logo + Icons + Profile -->
                    <div class="relative flex h-11 items-center justify-between">
                        <!-- Logo -->
                        <div class="flex shrink-0 items-center">
                            <Link :href="route('dashboard')">
                                <ApplicationMark class="block h-8 w-auto" />
                            </Link>
                        </div>

                        <!-- モバイルのみ: ヘッダー中央に在席ステータス -->
                        <div class="absolute left-1/2 -translate-x-1/2 sm:hidden">
                            <IrukaMobileStatusButton />
                        </div>

                        <!-- Right side: icon buttons + profile dropdown -->
                        <div class="hidden sm:flex sm:items-center sm:gap-1">
                            <!-- イルカ在席バッジ -->
                            <IrukaStatusBadge class="mr-1" />

                            <!-- お知らせ -->
                            <div class="group relative">
                                <Link :href="route('announcements.index')" class="relative flex h-8 w-8 cursor-pointer items-center justify-center rounded-full text-gray-500 hover:bg-gray-100 hover:text-gray-700">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                                    </svg>
                                    <span v-if="unreadAnnouncements > 0" class="absolute -right-0.5 -top-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[10px] text-white pointer-events-none">{{ unreadAnnouncements }}</span>
                                </Link>
                                <div class="pointer-events-none absolute right-0 top-9 z-50 w-36 rounded-md bg-gray-800 px-3 py-2 text-xs text-white opacity-0 shadow-lg transition-opacity group-hover:opacity-100">
                                    <p class="font-medium">お知らせ</p>
                                    <p class="text-gray-300">通知・連絡事項</p>
                                </div>
                            </div>

                            <!-- ジョブ通知 -->
                            <div class="group relative">
                                <Link :href="route('job-notifications.index')" class="relative flex h-8 w-8 cursor-pointer items-center justify-center rounded-full text-gray-500 hover:bg-gray-100 hover:text-gray-700">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                                    </svg>
                                    <span v-if="unreadJobNotifications > 0" class="absolute -right-0.5 -top-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-blue-500 text-[10px] text-white pointer-events-none">{{ unreadJobNotifications }}</span>
                                </Link>
                                <div class="pointer-events-none absolute right-0 top-9 z-50 w-36 rounded-md bg-gray-800 px-3 py-2 text-xs text-white opacity-0 shadow-lg transition-opacity group-hover:opacity-100">
                                    <p class="font-medium">ジョブ通知</p>
                                    <p class="text-gray-300">依頼・完了通知</p>
                                </div>
                            </div>

                            <!-- 予定表 -->
                            <div class="group relative">
                                <Link :href="route('schedule.index')" class="relative flex h-8 w-8 cursor-pointer items-center justify-center rounded-full text-gray-500 hover:bg-gray-100 hover:text-gray-700">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                                    </svg>
                                    <span v-if="unreadScheduleNotifications > 0" class="absolute -right-0.5 -top-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-green-500 text-[10px] text-white pointer-events-none">{{ unreadScheduleNotifications > 9 ? '9+' : unreadScheduleNotifications }}</span>
                                </Link>
                                <div class="pointer-events-none absolute right-0 top-9 z-50 w-32 rounded-md bg-gray-800 px-3 py-2 text-xs text-white opacity-0 shadow-lg transition-opacity group-hover:opacity-100">
                                    <p class="font-medium">予定表</p>
                                    <p class="text-gray-300">スケジュール管理</p>
                                </div>
                            </div>

                            <!-- 使い方ガイド -->
                            <div class="group relative">
                                <Link :href="route('guide.index')" class="flex h-8 w-8 cursor-pointer items-center justify-center rounded-full text-gray-500 hover:bg-gray-100 hover:text-gray-700">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
                                    </svg>
                                </Link>
                                <div class="pointer-events-none absolute right-0 top-9 z-50 w-44 rounded-md bg-gray-800 px-3 py-2 text-xs text-white opacity-0 shadow-lg transition-opacity group-hover:opacity-100">
                                    <p class="font-medium">使い方ガイド</p>
                                    <p class="text-gray-300">操作方法・ヘルプ</p>
                                </div>
                            </div>

                            <!-- スクリプトツール -->
                            <div v-if="canAccessScripts" class="group relative">
                                <Link :href="route('scripts.index')" class="flex h-8 w-8 cursor-pointer items-center justify-center rounded-full text-gray-500 hover:bg-gray-100 hover:text-gray-700">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 7.5l3 2.25-3 2.25m4.5 0h3m-9 8.25h13.5A2.25 2.25 0 0021 18V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v12a2.25 2.25 0 002.25 2.25z" />
                                    </svg>
                                </Link>
                                <div class="pointer-events-none absolute right-0 top-9 z-50 w-44 rounded-md bg-gray-800 px-3 py-2 text-xs text-white opacity-0 shadow-lg transition-opacity group-hover:opacity-100">
                                    <p class="font-medium">スクリプト</p>
                                    <p class="text-gray-300">業務ツール・自動化</p>
                                </div>
                            </div>

                            <!-- 売上分析 -->
                            <div v-if="canAccessSalesAnalysis" class="group relative">
                                <Link :href="route('sales_analysis.dashboard')" class="flex h-8 w-8 cursor-pointer items-center justify-center rounded-full text-gray-500 hover:bg-gray-100 hover:text-gray-700">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.5l3.75-3.75L11 14l6-6m0 0h-4.5M17 8v4.5M4.5 19.5h15A1.5 1.5 0 0021 18V6a1.5 1.5 0 00-1.5-1.5h-15A1.5 1.5 0 003 6v12a1.5 1.5 0 001.5 1.5z" />
                                    </svg>
                                </Link>
                                <div class="pointer-events-none absolute right-0 top-9 z-50 w-44 rounded-md bg-gray-800 px-3 py-2 text-xs text-white opacity-0 shadow-lg transition-opacity group-hover:opacity-100">
                                    <p class="font-medium">売上分析</p>
                                    <p class="text-gray-300">売上取込・比較・出力</p>
                                </div>
                            </div>

                            <!-- 更新ログ -->
                            <div class="group relative">
                                <Link :href="route('changelogs.index')" class="flex h-8 w-8 cursor-pointer items-center justify-center rounded-full text-gray-500 hover:bg-gray-100 hover:text-gray-700">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </Link>
                                <div class="pointer-events-none absolute right-0 top-9 z-50 w-44 rounded-md bg-gray-800 px-3 py-2 text-xs text-white opacity-0 shadow-lg transition-opacity group-hover:opacity-100">
                                    <p class="font-medium">更新ログ</p>
                                    <p class="text-gray-300">機能追加・不具合修正の履歴</p>
                                </div>
                            </div>

                            <!-- SuperAdmin コンテキスト切り替え -->
                            <SuperAdminContextSwitcher
                                v-if="$page.props.auth.user.isSuperAdmin"
                                class="mr-1"
                            />

                            <!-- Settings Dropdown -->
                            <div class="relative ms-1">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <button
                                            v-if="$page.props.jetstream.managesProfilePhotos"
                                            class="flex rounded-full border-2 border-transparent text-sm transition focus:border-gray-300 focus:outline-none"
                                        >
                                            <img
                                                class="size-8 rounded-full object-cover"
                                                :src="$page.props.auth.user.profile_photo_url"
                                                :alt="$page.props.auth.user.name"
                                            />
                                        </button>

                                        <span v-else class="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                class="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-1.5 text-sm font-medium leading-4 text-gray-500 transition duration-150 ease-in-out hover:text-gray-700 focus:bg-gray-50 focus:outline-none active:bg-gray-50"
                                            >
                                                {{ $page.props.auth.user.name }}

                                                <svg
                                                    class="-me-0.5 ms-2 size-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    fill="none"
                                                    viewBox="0 0 24 24"
                                                    stroke-width="1.5"
                                                    stroke="currentColor"
                                                >
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                                </svg>
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <!-- Account Management -->
                                        <div class="block px-4 py-2 text-xs text-gray-400">アカウント管理</div>

                                        <DropdownLink :href="route('profile.show')"> プロフィール </DropdownLink>

                                        <DropdownLink v-if="$page.props.jetstream.hasApiFeatures" :href="route('api-tokens.index')">
                                            API トークン
                                        </DropdownLink>

                                        <div class="border-t border-gray-200" />

                                        <!-- Authentication -->
                                        <form @submit.prevent="logout">
                                            <DropdownLink as="button"> ログアウト </DropdownLink>
                                        </form>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <!-- Hamburger -->
                        <div class="-me-2 flex items-center gap-1 sm:hidden">
                            <!-- モバイル通知アイコン -->
                            <Link :href="route('announcements.index')" class="relative flex h-9 w-9 items-center justify-center rounded-full text-gray-500 hover:bg-gray-100">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                                </svg>
                                <span v-if="unreadAnnouncements > 0" class="absolute -right-0.5 -top-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[10px] text-white pointer-events-none">{{ unreadAnnouncements }}</span>
                            </Link>
                            <Link :href="route('job-notifications.index')" class="relative flex h-9 w-9 items-center justify-center rounded-full text-gray-500 hover:bg-gray-100">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                                </svg>
                                <span v-if="unreadJobNotifications > 0" class="absolute -right-0.5 -top-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-blue-500 text-[10px] text-white pointer-events-none">{{ unreadJobNotifications }}</span>
                            </Link>

                            <!-- 予定表（モバイル） -->
                            <Link :href="route('schedule.index')"
                                class="relative flex h-9 w-9 items-center justify-center rounded-full text-gray-500 hover:bg-gray-100">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                                </svg>
                                <span v-if="unreadScheduleNotifications > 0" class="absolute -right-0.5 -top-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-green-500 text-[10px] text-white pointer-events-none">{{ unreadScheduleNotifications > 9 ? '9+' : unreadScheduleNotifications }}</span>
                            </Link>

                            <button
                                class="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-500 focus:bg-gray-100 focus:text-gray-500 focus:outline-none"
                                @click="showingNavigationDropdown = !showingNavigationDropdown"
                            >
                                <svg class="size-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                    <path
                                        :class="{ hidden: showingNavigationDropdown, 'inline-flex': !showingNavigationDropdown }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        :class="{ hidden: !showingNavigationDropdown, 'inline-flex': showingNavigationDropdown }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Row 2: Role navigation links -->
                    <div class="hidden border-t border-gray-100 pb-1.5 pt-1 sm:flex sm:items-center sm:space-x-4">
                        <!-- SuperAdmin用ナビゲーション -->
                        <template
                            v-if="
                                $page.props.auth.user.user_role === 'superadmin' &&
                                (typeof route === 'function' ? route().has('superadmin.dashboard') : false)
                            "
                        >
                            <button type="button" @click="navigateToRole('superadmin')" :class="roleNavClass('superadmin')">SuperAdmin</button>
                            <button type="button" @click="navigateToRole('admin')" :class="roleNavClass('admin')">Admin</button>
                            <button type="button" @click="navigateToRole('leader')" :class="roleNavClass('leader')">Leader</button>
                            <button type="button" @click="navigateToRole('clerk')" :class="roleNavClass('clerk')">Clerk</button>
                            <button type="button" @click="navigateToRole('coordinator')" :class="roleNavClass('coordinator')">Coordinator</button>
                            <CompanyModuleNavButtons group="beforeUser" :auth="$page.props.auth" :roleNavClass="roleNavClass" @navigate="navigateToRole" />
                            <button type="button" @click="navigateToRole('user')" :class="roleNavClass('user')">User</button>
                            <CompanyModuleNavButtons group="afterUser" :auth="$page.props.auth" :roleNavClass="roleNavClass" @navigate="navigateToRole" />
                        </template>

                        <!-- Admin用ナビゲーション -->
                        <template v-else-if="$page.props.auth.user.user_role === 'admin'">
                            <button type="button" @click="navigateToRole('admin')" :class="roleNavClass('admin')">Admin</button>
                            <button type="button" @click="navigateToRole('leader')" :class="roleNavClass('leader')">Leader</button>
                            <button type="button" @click="navigateToRole('clerk')" :class="roleNavClass('clerk')">Clerk</button>
                            <button type="button" @click="navigateToRole('coordinator')" :class="roleNavClass('coordinator')">Coordinator</button>
                            <CompanyModuleNavButtons group="beforeUser" :auth="$page.props.auth" :roleNavClass="roleNavClass" @navigate="navigateToRole" />
                            <button type="button" @click="navigateToRole('user')" :class="roleNavClass('user')">User</button>
                            <CompanyModuleNavButtons group="afterUser" :auth="$page.props.auth" :roleNavClass="roleNavClass" @navigate="navigateToRole" />
                        </template>

                        <!-- Leader用ナビゲーション（部署リーダーはClerk/ProofCoordinator も表示） -->
                        <template v-else-if="$page.props.auth.user.user_role === 'leader'">
                            <button type="button" @click="navigateToRole('leader')" :class="roleNavClass('leader')">Leader</button>
                            <button
                                v-if="$page.props.auth.user.isDepartmentLeader"
                                type="button" @click="navigateToRole('clerk')"
                                :class="roleNavClass('clerk')"
                            >Clerk</button>
                            <button type="button" @click="navigateToRole('coordinator')" :class="roleNavClass('coordinator')">Coordinator</button>
                            <CompanyModuleNavButtons group="beforeUser" :auth="$page.props.auth" :roleNavClass="roleNavClass" @navigate="navigateToRole" />
                            <button type="button" @click="navigateToRole('user')" :class="roleNavClass('user')">User</button>
                            <CompanyModuleNavButtons group="afterUser" :auth="$page.props.auth" :roleNavClass="roleNavClass" @navigate="navigateToRole" />
                        </template>

                        <!-- Clerk用ナビゲーション（Coordinator+User権限を持つ） -->
                        <template v-else-if="$page.props.auth.user.user_role === 'clerk'">
                            <button type="button" @click="navigateToRole('clerk')" :class="roleNavClass('clerk')">Clerk</button>
                            <button type="button" @click="navigateToRole('coordinator')" :class="roleNavClass('coordinator')">Coordinator</button>
                            <CompanyModuleNavButtons group="beforeUser" :auth="$page.props.auth" :roleNavClass="roleNavClass" @navigate="navigateToRole" />
                            <button type="button" @click="navigateToRole('user')" :class="roleNavClass('user')">User</button>
                            <CompanyModuleNavButtons group="afterUser" :auth="$page.props.auth" :roleNavClass="roleNavClass" @navigate="navigateToRole" />
                        </template>

                        <!-- Coordinator用ナビゲーション -->
                        <template v-else-if="$page.props.auth.user.user_role === 'coordinator'">
                            <button type="button" @click="navigateToRole('coordinator')" :class="roleNavClass('coordinator')">Coordinator</button>
                            <CompanyModuleNavButtons group="beforeUser" :auth="$page.props.auth" :roleNavClass="roleNavClass" @navigate="navigateToRole" />
                            <button type="button" @click="navigateToRole('user')" :class="roleNavClass('user')">User</button>
                            <CompanyModuleNavButtons group="afterUser" :auth="$page.props.auth" :roleNavClass="roleNavClass" @navigate="navigateToRole" />
                        </template>

                        <!-- ProofCoordinator用ナビゲーション -->
                        <template v-else-if="$page.props.auth.user.user_role === 'proof_coordinator'">
                            <button type="button" @click="navigateToRole('proof_coordinator')" :class="roleNavClass('proof_coordinator')">Proof Admin</button>
                            <button type="button" @click="navigateToRole('user')" :class="roleNavClass('user')">User</button>
                        </template>

                        <!-- 一般ユーザー用ナビゲーション -->
                        <template v-else>
                            <button type="button" @click="navigateToRole('user')" :class="roleNavClass('user')">Dashboard</button>
                            <CompanyModuleNavButtons group="afterUser" :auth="$page.props.auth" :roleNavClass="roleNavClass" @navigate="navigateToRole" />
                        </template>

                    </div>
                </div>

                <!-- Responsive Navigation Menu -->
                <div :class="{ block: showingNavigationDropdown, hidden: !showingNavigationDropdown }" class="sm:hidden">
                    <div class="space-y-1 pb-3 pt-2">
                        <!-- SuperAdmin用レスポンシブナビゲーション -->
                        <template
                            v-if="
                                $page.props.auth.user.user_role === 'superadmin' &&
                                (typeof route === 'function' ? route().has('superadmin.dashboard') : false)
                            "
                        >
                            <div class="flex items-center gap-2 px-4 py-2">
                                <span class="text-xs font-medium text-gray-500">会社切替:</span>
                                <SuperAdminContextSwitcher />
                            </div>
                            <ResponsiveNavLink :href="route('superadmin.dashboard')" :active="route().current('superadmin.dashboard')">
                                <span class="text-yellow-600">SuperAdmin Dashboard</span>
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                :href="user && user.user_role === 'superadmin' ? route('superadmin.dashboard') : route('admin.dashboard')"
                                :active="route().current('admin.dashboard') || route().current('superadmin.dashboard')"
                            >
                                <span class="text-red-600">Admin Dashboard</span>
                            </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('leader.dashboard')" :active="route().current('leader.dashboard')">
                                <span class="text-orange-600">Leader Dashboard</span>
                            </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('coordinator.dashboard')" :active="route().current('coordinator.dashboard')">
                                <span class="text-green-600">Coordinator Dashboard</span>
                            </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('user.dashboard')" :active="route().current('user.dashboard')">
                                <span class="text-blue-600">User Dashboard</span>
                            </ResponsiveNavLink>
                            <ResponsiveNavLink v-if="canShowPrepressNav" :href="route('prepress.dashboard')" :active="route().current('prepress.*')">
                                <span class="text-green-700">Prepress Dashboard</span>
                            </ResponsiveNavLink>
                        </template>

                        <!-- Admin用レスポンシブナビゲーション -->
                        <template v-else-if="$page.props.auth.user.user_role === 'admin'">
                            <ResponsiveNavLink :href="route('admin.dashboard')" :active="route().current('admin.dashboard')">
                                <span class="text-red-600">Admin Dashboard</span>
                            </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('leader.dashboard')" :active="route().current('leader.dashboard')">
                                <span class="text-orange-600">Leader Dashboard</span>
                            </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('coordinator.dashboard')" :active="route().current('coordinator.dashboard')">
                                <span class="text-green-600">Coordinator Dashboard</span>
                            </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('user.dashboard')" :active="route().current('user.dashboard')">
                                <span class="text-blue-600">User Dashboard</span>
                            </ResponsiveNavLink>
                            <ResponsiveNavLink v-if="canShowPrepressNav" :href="route('prepress.dashboard')" :active="route().current('prepress.*')">
                                <span class="text-green-700">Prepress Dashboard</span>
                            </ResponsiveNavLink>
                        </template>

                        <!-- Leader用レスポンシブナビゲーション -->
                        <template v-else-if="$page.props.auth.user.user_role === 'leader'">
                            <ResponsiveNavLink :href="route('leader.dashboard')" :active="route().current('leader.dashboard')">
                                <span class="text-orange-600">Leader Dashboard</span>
                            </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('coordinator.dashboard')" :active="route().current('coordinator.dashboard')">
                                <span class="text-green-600">Coordinator Dashboard</span>
                            </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('user.dashboard')" :active="route().current('user.dashboard')">
                                <span class="text-blue-600">User Dashboard</span>
                            </ResponsiveNavLink>
                        </template>

                        <!-- Coordinator用レスポンシブナビゲーション -->
                        <template v-else-if="$page.props.auth.user.user_role === 'coordinator'">
                            <ResponsiveNavLink :href="route('coordinator.dashboard')" :active="route().current('coordinator.dashboard')">
                                <span class="text-green-600">Coordinator Dashboard</span>
                            </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('user.dashboard')" :active="route().current('user.dashboard')">
                                <span class="text-blue-600">User Dashboard</span>
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                v-if="canShowPrepressNav"
                                :href="route('prepress.dashboard')"
                                :active="route().current('prepress.*')"
                            >
                                <span class="text-green-700">Prepress</span>
                            </ResponsiveNavLink>
                        </template>

                        <!-- 一般ユーザー用レスポンシブナビゲーション -->
                        <template v-else>
                            <ResponsiveNavLink :href="route('dashboard')" :active="route().current('dashboard')"> Dashboard </ResponsiveNavLink>
                            <ResponsiveNavLink
                                v-if="canShowPrepressNav"
                                :href="route('prepress.dashboard')"
                                :active="route().current('prepress.*')"
                            >
                                <span class="text-green-700">Prepress</span>
                            </ResponsiveNavLink>
                        </template>
                    </div>

                    <!-- Responsive Settings Options -->
                    <div class="border-t border-gray-200 pb-1 pt-4">
                        <div class="flex items-center px-4">
                            <div v-if="$page.props.jetstream.managesProfilePhotos" class="me-3 shrink-0">
                                <img
                                    class="size-10 rounded-full object-cover"
                                    :src="$page.props.auth.user.profile_photo_url"
                                    :alt="$page.props.auth.user.name"
                                />
                            </div>

                            <div>
                                <div class="text-base font-medium text-gray-800">
                                    {{ $page.props.auth.user.name }}
                                </div>
                                <div class="text-sm font-medium text-gray-500">
                                    {{ $page.props.auth.user.email }}
                                </div>
                            </div>
                        </div>

                        <div class="mt-3 space-y-1">
                            <ResponsiveNavLink :href="route('profile.show')" :active="route().current('profile.show')"> プロフィール </ResponsiveNavLink>

                            <ResponsiveNavLink
                                v-if="$page.props.jetstream.hasApiFeatures"
                                :href="route('api-tokens.index')"
                                :active="route().current('api-tokens.index')"
                            >
                                API トークン
                            </ResponsiveNavLink>

                            <div class="border-t border-gray-200 my-1" />

                            <ResponsiveNavLink :href="route('guide.index')" :active="route().current('guide.index')"> 使い方ガイド </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('changelogs.index')" :active="route().current('changelogs.index')"> 更新ログ </ResponsiveNavLink>
                            <ResponsiveNavLink v-if="canAccessScripts" :href="route('scripts.index')" :active="route().current('scripts.*')"> スクリプトツール </ResponsiveNavLink>
                            <ResponsiveNavLink v-if="canAccessSalesAnalysis" :href="route('sales_analysis.dashboard')" :active="route().current('sales_analysis.*')"> 売上分析 </ResponsiveNavLink>

                            <div class="border-t border-gray-200 my-1" />

                            <!-- Authentication -->
                            <form method="POST" @submit.prevent="logout">
                                <ResponsiveNavLink as="button"> ログアウト </ResponsiveNavLink>
                            </form>

                            <!-- Team Management -->
                            <template v-if="$page.props.jetstream.hasTeamFeatures">
                                <div class="border-t border-gray-200" />

                                <div class="block px-4 py-2 text-xs text-gray-400">チーム管理</div>

                                <!-- Team Settings -->
                                <ResponsiveNavLink
                                    v-if="$page.props.auth.user.current_team"
                                    :href="route('teams.show', $page.props.auth.user.current_team)"
                                    :active="route().current('teams.show')"
                                >
                                    チーム設定
                                </ResponsiveNavLink>

                                <ResponsiveNavLink
                                    v-if="$page.props.jetstream.canCreateTeams"
                                    :href="route('teams.create')"
                                    :active="route().current('teams.create')"
                                >
                                    新しいチームを作成
                                </ResponsiveNavLink>

                                <!-- Team Switcher -->
                                <template v-if="$page.props.auth.user.all_teams && $page.props.auth.user.all_teams.length > 1">
                                    <div class="border-t border-gray-200" />

                                    <div class="block px-4 py-2 text-xs text-gray-400">チームを切り替える</div>

                                    <template v-for="team in $page.props.auth.user.all_teams || []" :key="team.id">
                                        <form @submit.prevent="switchToTeam(team)">
                                            <ResponsiveNavLink as="button">
                                                <div class="flex items-center">
                                                    <svg
                                                        v-if="team.id == $page.props.auth.user.current_team_id"
                                                        class="me-2 size-5 text-green-400"
                                                        xmlns="http://www.w3.org/2000/svg"
                                                        fill="none"
                                                        viewBox="0 0 24 24"
                                                        stroke-width="1.5"
                                                        stroke="currentColor"
                                                    >
                                                        <path
                                                            stroke-linecap="round"
                                                            stroke-linejoin="round"
                                                            d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                                        />
                                                    </svg>
                                                    <div>{{ team.name }}</div>
                                                </div>
                                            </ResponsiveNavLink>
                                        </form>
                                    </template>
                                </template>
                            </template>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Page Heading -->
            <header v-if="$slots.header" class="bg-white shadow">
                <div class="mx-auto flex flex-col gap-y-2 max-w-7xl px-4 py-3 sm:min-h-[4.5rem] sm:flex-row sm:items-center sm:justify-between sm:gap-x-4 sm:py-4 sm:px-6 lg:px-8">
                    <div class="min-w-0 flex-1">
                        <slot name="header" />
                    </div>
                    <div class="flex-shrink-0">
                        <slot name="headerExtras" />
                    </div>
                </div>
            </header>
            <!-- Page Content -->
            <!-- Toasts -->
            <ToastUnified />
            <div class="py-2">
                <!-- ここにページ固有のタブが入ります（必要なければ中央の役割ベースタブが表示されます） -->
                <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <!-- Role-specific tabs (centralized for Admin/SuperAdmin to avoid duplicates) -->
                    <div class="mx-auto mt-1 max-w-7xl sm:px-6 lg:px-8">
                        <!-- Allow pages to provide their own tabs via named slot 'tabs'. Falls back to centralized role tabs. -->
                        <slot name="tabs">
                            <!-- Role-specific navigation tabs (centralized, based on current route area) -->
                            <div v-if="page.props.auth && page.props.auth.user" class="">
                                <SuperAdminNavigationTabs v-if="currentRouteContext === 'superadmin'" :active="getTopTabActive()" />
                                <AdminNavigationTabs v-else-if="currentRouteContext === 'admin'" :active="getTopTabActive()" />
                                <LeaderNavigationTabs v-else-if="currentRouteContext === 'leader'" :active="getTopTabActive()" />
                                <ClerkNavigationTabs v-else-if="currentRouteContext === 'clerk'" :active="getTopTabActive()" />
                                <PrepressNavigationTabs v-else-if="currentRouteContext === 'prepress'" :active="getTopTabActive()" />
                                <ProofCoordinatorNavigationTabs v-else-if="currentRouteContext === 'proof_coordinator'" :active="getTopTabActive()" />
                                <CoordinatorNavigationTabs
                                    v-else-if="currentRouteContext === 'coordinator'"
                                    :projectJob="page.props.projectJob"
                                    :active="computeCoordinatorActive()"
                                />
                                <UserNavigationTabs v-else :active="getTopTabActive()" />
                            </div>
                        </slot>
                    </div>
                    <!-- 通常レイアウト: max-w-7xl 内 -->
                    <main v-if="!fluid">
                        <slot />
                    </main>
                </div>
                <!-- 全幅レイアウト: max-w-7xl 外 -->
                <main v-if="fluid" class="mt-2 px-3 sm:px-4">
                    <slot />
                </main>
            </div>
        </div>
    </div>
</template>
