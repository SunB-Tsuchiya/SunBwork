<script setup>
import ApplicationMark from '@/Components/ApplicationMark.vue';
import Banner from '@/Components/Banner.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import AdminNavigationTabs from '@/Components/Tabs/AdminNavigationTabs.vue';
import CoordinatorNavigationTabs from '@/Components/Tabs/CoordinatorNavigationTabs.vue';
import LeaderNavigationTabs from '@/Components/Tabs/LeaderNavigationTabs.vue';
import SuperAdminNavigationTabs from '@/Components/Tabs/SuperAdminNavigationTabs.vue';
import UserNavigationTabs from '@/Components/Tabs/UserNavigationTabs.vue';
import ToastUnified from '@/Components/ToastUnified.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, provide, ref } from 'vue';

defineProps({
    title: String,
    // user: {
    //     type: Object,
    //     required: true,
    // },
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
import { onBeforeUnmount, onMounted, useSlots, ref as vueRef, watch } from 'vue';

const page = usePage();
// shared toast API (shared composable)
const { showToast } = useToasts();
// Keep `user` available for templates (many pages pass a `user` prop).
// Use `authUser` for realtime subscriptions to avoid subscribing to the
// resource being viewed when it's not the logged-in user.
const user = page.props.user;
const authUser = page.props.auth && page.props.auth.user ? page.props.auth.user : page.props.user;

// Provide `authUser` and `user` to descendant components via Vue's provide/inject
// so components like AssignmentForm_user.vue can access them even when
// $page props aren't available due to different mounting contexts.
try {
    provide('authUser', authUser);
    provide('user', user);
    // Provide without verbose debug logging in production
} catch (e) {
    // provide may fail in some SSR or test contexts; non-fatal
}
// Use unread_messages_count as the single notification source; job_requests are being
// migrated to Messages so we stop subscribing to jobrequests channel here.
const inboxCount = vueRef(0); // legacy placeholder
const inboxToast = vueRef('');
const unreadMessages = vueRef(page.props.user?.unread_messages_count || 0);
// job-specific unread count provided by server when available
const unreadJobMessages = vueRef(page.props.user?.unread_job_messages_count || 0);

// keep reactive when Inertia page props update
watch(
    () => page.props.user && page.props.user.unread_job_messages_count,
    (v) => {
        unreadJobMessages.value = v || 0;
    },
);
// compute a safe href for the Job link to avoid complex inline ternaries in template
const jobLink = computed(() => {
    try {
        // Prefer the named Ziggy route for the JobBox index if available
        if (typeof route === 'function' && route().has && route().has('project_jobs.index')) {
            try {
                return route('project_jobs.index');
            } catch (e) {
                // ignore and fall back to literal path
            }
        }

        // Fallback to a literal path that maps to Pages/JobBox/Index.vue in the SPA router
        return '/jobbox';
    } catch (e) {
        // final fallback
    }

    try {
        return route('dashboard');
    } catch (e) {
        return '#';
    }
});
// handle job link clicks with error trapping so we can surface the actual exception
const handleJobClick = (e) => {
    try {
        // Let the browser handle the actual navigation (do not prevent default)
    } catch (err) {
        // swallow errors to avoid unwinding Vue native handler
    }
};
let echoChannel = null;

onMounted(() => {
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
                } catch (err) {}
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
                } catch (err) {
                    // non-fatal
                }
            });
            window.Echo.private('jobmessages.' + authUser.id).listen('JobMessageRead', (e) => {
                try {
                    unreadJobMessages.value = Math.max(0, (unreadJobMessages.value || 0) - 1);
                } catch (err) {}
            });
            // AssignmentStatusToast is handled centrally by ToastUnified.vue (subscribe to "toasts" channel)
        }
    } catch (err) {
        // Echo subscribe failed (non-fatal)
    }
});

onBeforeUnmount(() => {
    try {
        if (echoChannel && window.Echo) {
            window.Echo.leavePrivate('jobrequests.' + authUser.id);
            window.Echo.leavePrivate('messages.' + authUser.id);
            echoChannel = null;
        }
    } catch (err) {}
});
const slots = useSlots();
const hasTabsSlot = !!slots.tabs;
// Debug logs removed for production

// Determine an "active" key for top tabs based on current route name
const getTopTabActive = () => {
    try {
        const r = route().current();
        if (!r) return '';
        // map some route name fragments to the tab keys used by components
        if (r.includes('users') || r.includes('adminusers') || r.includes('superadmin.users')) return 'users';
        if (r.includes('companies')) return 'companies';
        if (r.includes('debug') || r.includes('api')) return 'debug';
        if (r.includes('teams')) return 'teams';
        if (r.includes('clients')) return 'clients';
        return '';
    } catch (e) {
        return '';
    }
};
// compute active key for coordinator tabs (projects vs jobs)
const computeCoordinatorActive = () => {
    try {
        const r = route().current();
        if (!r) return '';
        // if route name includes 'assignments' treat as jobs list/assignments area
        if (r.includes('assignments')) return 'jobs';
        // otherwise default to projects
        return 'projects';
    } catch (e) {
        return 'projects';
    }
};
</script>

<template>
    <div>
        <Head :title="title" />
        <Head>
            <meta name="csrf-token" :content="$page.props.csrf_token" />
        </Head>
        <Banner />

        <div class="min-h-screen bg-gray-100">
            <nav class="border-b border-gray-100 bg-white">
                <!-- Primary Navigation Menu -->
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex h-16 justify-between">
                        <div class="flex">
                            <!-- Logo -->
                            <div class="flex shrink-0 items-center">
                                <Link :href="route('dashboard')">
                                    <ApplicationMark class="block h-9 w-auto" />
                                </Link>
                            </div>

                            <!-- Navigation Links -->
                            <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                                <!-- SuperAdmin用ナビゲーション -->
                                <template
                                    v-if="
                                        $page.props.auth.user.user_role === 'superadmin' &&
                                        (typeof route === 'function' ? route().has('superadmin.dashboard') : false)
                                    "
                                >
                                    <NavLink :href="route('superadmin.dashboard')" :active="route().current('superadmin.dashboard')">
                                        <span class="text-yellow-600">SuperAdmin</span>
                                    </NavLink>
                                    <NavLink :href="route('admin.dashboard')" :active="route().current('admin.dashboard')">
                                        <span class="text-red-600">Admin</span>
                                    </NavLink>
                                    <NavLink :href="route('leader.dashboard')" :active="route().current('leader.dashboard')">
                                        <span class="text-orange-600">Leader</span>
                                    </NavLink>
                                    <NavLink :href="route('coordinator.dashboard')" :active="route().current('coordinator.dashboard')">
                                        <span class="text-green-600">Coordinator</span>
                                    </NavLink>
                                    <NavLink :href="route('user.dashboard')" :active="route().current('user.dashboard')">
                                        <span class="text-blue-600">User</span>
                                    </NavLink>
                                </template>

                                <!-- Admin用ナビゲーション -->
                                <template v-else-if="$page.props.auth.user.user_role === 'admin'">
                                    <NavLink :href="route('admin.dashboard')" :active="route().current('admin.dashboard')">
                                        <span class="text-red-600">Admin</span>
                                    </NavLink>
                                    <NavLink :href="route('leader.dashboard')" :active="route().current('leader.dashboard')">
                                        <span class="text-orange-600">Leader</span>
                                    </NavLink>
                                    <NavLink :href="route('coordinator.dashboard')" :active="route().current('coordinator.dashboard')">
                                        <span class="text-green-600">Coordinator</span>
                                    </NavLink>
                                    <NavLink :href="route('user.dashboard')" :active="route().current('user.dashboard')">
                                        <span class="text-blue-600">User</span>
                                    </NavLink>
                                </template>

                                <!-- Leader用ナビゲーション -->
                                <template v-else-if="$page.props.auth.user.user_role === 'leader'">
                                    <NavLink :href="route('leader.dashboard')" :active="route().current('leader.dashboard')">
                                        <span class="text-orange-600">Leader</span>
                                    </NavLink>
                                    <NavLink :href="route('coordinator.dashboard')" :active="route().current('coordinator.dashboard')">
                                        <span class="text-green-600">Coordinator</span>
                                    </NavLink>
                                    <NavLink :href="route('user.dashboard')" :active="route().current('user.dashboard')">
                                        <span class="text-blue-600">User</span>
                                    </NavLink>
                                </template>

                                <!-- Coordinator用ナビゲーション -->
                                <template v-else-if="$page.props.auth.user.user_role === 'coordinator'">
                                    <NavLink :href="route('coordinator.dashboard')" :active="route().current('coordinator.dashboard')">
                                        <span class="text-green-600">Coordinator</span>
                                    </NavLink>
                                    <NavLink :href="route('user.dashboard')" :active="route().current('user.dashboard')">
                                        <span class="text-blue-600">User</span>
                                    </NavLink>
                                </template>

                                <!-- 一般ユーザー用ナビゲーション -->
                                <template v-else>
                                    <NavLink :href="route('dashboard')" :active="route().current('dashboard')"> Dashboard </NavLink>
                                </template>
                            </div>
                        </div>

                        <div class="hidden sm:ms-6 sm:flex sm:items-center">
                            <!-- Chat link (replaces previous Inbox) -->
                            <!-- チャットメール閉じる必要になったら再開 -->
                            <!-- <div class="relative ms-3 flex items-center">
                                <Link :href="route('chat.rooms.index')" class="flex items-center text-sm text-gray-600 hover:text-gray-800">
                                    <span>チャット</span>
                                    <span
                                        v-if="unreadMessages && unreadMessages > 0"
                                        class="ms-2 inline-flex items-center justify-center rounded-full bg-blue-500 px-2 py-0.5 text-xs text-white"
                                        >{{ unreadMessages }}</span
                                    >
                                </Link>
                            </div> -->
                            <!-- Messages link -->
                            <!-- <div class="relative ms-3 flex items-center">
                                <Link :href="route('messages.index')" class="flex items-center text-sm text-gray-600 hover:text-gray-800">
                                    <span>メール</span>
                                    <span
                                        v-if="unreadMessages && unreadMessages > 0"
                                        class="ms-2 inline-flex items-center justify-center rounded-full bg-blue-500 px-2 py-0.5 text-xs text-white"
                                        >{{ unreadMessages }}</span
                                    >
                                </Link>
                            </div> -->
                            <!-- JobBox link -->
                            <!-- <div class="relative ms-3 flex items-center">
                                <Link :href="jobLink" class="flex items-center text-sm text-gray-600 hover:text-gray-800" @click="handleJobClick">
                                    <span>ジョブ</span>
                                    <span
                                        v-if="unreadJobMessages && unreadJobMessages > 0"
                                        class="ms-2 inline-flex items-center justify-center rounded-full bg-blue-500 px-2 py-0.5 text-xs text-white"
                                        >{{ unreadJobMessages }}</span
                                    >
                                </Link>
                            </div> -->
                            <!-- TeamSwitcher -->
                            <!-- チームスイッチャーは機能してないので切ります。 -->
                            <!-- <div class="relative ms-3">
                                <TeamSwitcher
                                    v-if="user && user.available_teams"
                                    :user="user"
                                    :current-team="user.current_team"
                                    :available-teams="user.available_teams || { department: [], personal: [] }"
                                />
                            </div> -->

                            <!-- Settings Dropdown -->
                            <div class="relative ms-3">
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
                                                class="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition duration-150 ease-in-out hover:text-gray-700 focus:bg-gray-50 focus:outline-none active:bg-gray-50"
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
                                        <div class="block px-4 py-2 text-xs text-gray-400">Manage Account</div>

                                        <DropdownLink :href="route('profile.show')"> Profile </DropdownLink>

                                        <DropdownLink v-if="$page.props.jetstream.hasApiFeatures" :href="route('api-tokens.index')">
                                            API Tokens
                                        </DropdownLink>

                                        <div class="border-t border-gray-200" />

                                        <!-- Authentication -->
                                        <form @submit.prevent="logout">
                                            <DropdownLink as="button"> Log Out </DropdownLink>
                                        </form>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <!-- Hamburger -->
                        <div class="-me-2 flex items-center sm:hidden">
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
                        </template>

                        <!-- 一般ユーザー用レスポンシブナビゲーション -->
                        <template v-else>
                            <ResponsiveNavLink :href="route('dashboard')" :active="route().current('dashboard')"> Dashboard </ResponsiveNavLink>
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
                            <ResponsiveNavLink :href="route('profile.show')" :active="route().current('profile.show')"> Profile </ResponsiveNavLink>

                            <ResponsiveNavLink
                                v-if="$page.props.jetstream.hasApiFeatures"
                                :href="route('api-tokens.index')"
                                :active="route().current('api-tokens.index')"
                            >
                                API Tokens
                            </ResponsiveNavLink>

                            <!-- Authentication -->
                            <form method="POST" @submit.prevent="logout">
                                <ResponsiveNavLink as="button"> Log Out </ResponsiveNavLink>
                            </form>

                            <!-- Team Management -->
                            <template v-if="$page.props.jetstream.hasTeamFeatures">
                                <div class="border-t border-gray-200" />

                                <div class="block px-4 py-2 text-xs text-gray-400">Manage Team</div>

                                <!-- Team Settings -->
                                <ResponsiveNavLink
                                    v-if="$page.props.auth.user.current_team"
                                    :href="route('teams.show', $page.props.auth.user.current_team)"
                                    :active="route().current('teams.show')"
                                >
                                    Team Settings
                                </ResponsiveNavLink>

                                <ResponsiveNavLink
                                    v-if="$page.props.jetstream.canCreateTeams"
                                    :href="route('teams.create')"
                                    :active="route().current('teams.create')"
                                >
                                    Create New Team
                                </ResponsiveNavLink>

                                <!-- Team Switcher -->
                                <template v-if="$page.props.auth.user.all_teams && $page.props.auth.user.all_teams.length > 1">
                                    <div class="border-t border-gray-200" />

                                    <div class="block px-4 py-2 text-xs text-gray-400">Switch Teams</div>

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
                <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-6 sm:px-6 lg:px-8">
                    <div class="flex-1">
                        <slot name="header" />
                    </div>
                    <div class="ml-4 flex-shrink-0">
                        <slot name="headerExtras" />
                    </div>
                </div>
            </header>
            <!-- Page Content -->
            <!-- Toasts -->
            <ToastUnified />
            <div class="py-12">
                <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <!-- Role-specific tabs (centralized for Admin/SuperAdmin to avoid duplicates) -->
                    <div class="mx-auto mt-6 max-w-7xl sm:px-6 lg:px-8">
                        <!-- Allow pages to provide their own tabs via named slot 'tabs'. Falls back to centralized role tabs. -->
                        <slot name="tabs">
                            <!-- Role-specific navigation tabs (centralized) -->
                            <div
                                v-if="
                                    typeof route === 'function' &&
                                    (route().current('superadmin.dashboard') ||
                                        route().current('admin.dashboard') ||
                                        route().current('leader.dashboard') ||
                                        route().current('coordinator.dashboard') ||
                                        route().current('dashboard') ||
                                        route().current('user.dashboard'))
                                "
                                class=""
                            >
                                <SuperAdminNavigationTabs v-if="route().current('superadmin.dashboard')" active="users" />
                                <AdminNavigationTabs v-else-if="route().current('admin.dashboard')" active="users" />
                                <LeaderNavigationTabs v-else-if="route().current('leader.dashboard')" active="clients" />
                                <CoordinatorNavigationTabs
                                    v-else-if="route().current('coordinator.dashboard')"
                                    :projectJob="page.props.projectJob"
                                    :active="computeCoordinatorActive()"
                                />
                                <UserNavigationTabs v-else-if="route().current('dashboard') || route().current('user.dashboard')" active="profile" />
                            </div>
                        </slot>
                    </div>
                    <main>
                        <slot />
                    </main>
                </div>
            </div>
        </div>
    </div>
</template>
