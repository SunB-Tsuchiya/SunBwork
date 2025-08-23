<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import ApplicationMark from '@/Components/ApplicationMark.vue';
import Banner from '@/Components/Banner.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import TeamSwitcher from '@/Components/TeamSwitcher.vue';
import Toast from '@/Components/Toast.vue';

defineProps({
    title: String,
    // user: {
    //     type: Object,
    //     required: true,
    // },
});

const showingNavigationDropdown = ref(false);

const switchToTeam = (team) => {
    router.put(route('current-team.update'), {
        team_id: team.id,
    }, {
        preserveState: false,
    });
};

const logout = () => {
    router.post(route('logout'));
};

import { usePage } from '@inertiajs/vue3';


const page = usePage();
const user = page.props.user; // これを追加
// console.log('AppLayout $page.props:', page.props);
// console.log('AppLayout user:', page.props.user);
// console.log('AppLayout auth:', page.props.auth);

</script>

<template>
    <div>
        <Head :title="title" />
        <Head>
        <meta name="csrf-token" :content="$page.props.csrf_token" />
        </Head>
        <Banner />

        <div class="min-h-screen bg-gray-100">
            <nav class="bg-white border-b border-gray-100">
                <!-- Primary Navigation Menu -->
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between h-16">
                        <div class="flex">
                            <!-- Logo -->
                            <div class="shrink-0 flex items-center">
                                <Link :href="route('dashboard')">
                                    <ApplicationMark class="block h-9 w-auto" />
                                </Link>
                            </div>

                            <!-- Navigation Links -->
                            <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                                <!-- SuperAdmin用ナビゲーション -->
                                <template v-if="$page.props.auth.user.is_superadmin && (typeof route === 'function' ? route().has('superadmin.dashboard') : false)">
                                    <NavLink :href="route('superadmin.dashboard')" :active="route().current('superadmin.dashboard')">
                                        <span class="text-yellow-600">SuperAdmin</span>
                                    </NavLink>
                                    <NavLink :href="(user && user.is_superadmin) ? route('superadmin.dashboard') : route('admin.dashboard')"
                                             :active="route().current('admin.dashboard') || route().current('superadmin.dashboard')">
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
                                    <NavLink :href="route('dashboard')" :active="route().current('dashboard')">
                                        Dashboard
                                    </NavLink>
                                </template>
                            </div>
                        </div>

                        <div class="hidden sm:flex sm:items-center sm:ms-6">
                            <!-- TeamSwitcher -->
                            <div class="ms-3 relative">
                                <TeamSwitcher 
                                    v-if="user && user.available_teams"
                                    :user="user" 
                                    :current-team="user.current_team"
                                    :available-teams="user.available_teams || { department: [], personal: [] }" 
                                />
                            </div>

                            <!-- Settings Dropdown -->
                            <div class="ms-3 relative">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <button v-if="$page.props.jetstream.managesProfilePhotos" class="flex text-sm border-2 border-transparent rounded-full focus:outline-none focus:border-gray-300 transition">
                                            <img class="size-8 rounded-full object-cover" :src="$page.props.auth.user.profile_photo_url" :alt="$page.props.auth.user.name">
                                        </button>

                                        <span v-else class="inline-flex rounded-md">
                                            <button type="button" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none focus:bg-gray-50 active:bg-gray-50 transition ease-in-out duration-150">
                                                {{ $page.props.auth.user.name }}

                                                <svg class="ms-2 -me-0.5 size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                                </svg>
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <!-- Account Management -->
                                        <div class="block px-4 py-2 text-xs text-gray-400">
                                            Manage Account
                                        </div>

                                        <DropdownLink :href="route('profile.show')">
                                            Profile
                                        </DropdownLink>

                                        <DropdownLink v-if="$page.props.jetstream.hasApiFeatures" :href="route('api-tokens.index')">
                                            API Tokens
                                        </DropdownLink>

                                        <div class="border-t border-gray-200" />

                                        <!-- Authentication -->
                                        <form @submit.prevent="logout">
                                            <DropdownLink as="button">
                                                Log Out
                                            </DropdownLink>
                                        </form>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <!-- Hamburger -->
                        <div class="-me-2 flex items-center sm:hidden">
                            <button class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out" @click="showingNavigationDropdown = ! showingNavigationDropdown">
                                <svg
                                    class="size-6"
                                    stroke="currentColor"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        :class="{'hidden': showingNavigationDropdown, 'inline-flex': ! showingNavigationDropdown }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        :class="{'hidden': ! showingNavigationDropdown, 'inline-flex': showingNavigationDropdown }"
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
                <div :class="{'block': showingNavigationDropdown, 'hidden': ! showingNavigationDropdown}" class="sm:hidden">
                    <div class="pt-2 pb-3 space-y-1">
                        <!-- SuperAdmin用レスポンシブナビゲーション -->
                        <template v-if="$page.props.auth.user.is_superadmin && (typeof route === 'function' ? route().has('superadmin.dashboard') : false)">
                            <ResponsiveNavLink :href="route('superadmin.dashboard')" :active="route().current('superadmin.dashboard')">
                                <span class="text-yellow-600">SuperAdmin Dashboard</span>
                            </ResponsiveNavLink>
                            <ResponsiveNavLink :href="(user && user.is_superadmin) ? route('superadmin.dashboard') : route('admin.dashboard')" :active="route().current('admin.dashboard') || route().current('superadmin.dashboard')">
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
                            <ResponsiveNavLink :href="route('dashboard')" :active="route().current('dashboard')">
                                Dashboard
                            </ResponsiveNavLink>
                        </template>
                    </div>

                    <!-- Responsive Settings Options -->
                    <div class="pt-4 pb-1 border-t border-gray-200">
                        <div class="flex items-center px-4">
                            <div v-if="$page.props.jetstream.managesProfilePhotos" class="shrink-0 me-3">
                                <img class="size-10 rounded-full object-cover" :src="$page.props.auth.user.profile_photo_url" :alt="$page.props.auth.user.name">
                            </div>

                            <div>
                                <div class="font-medium text-base text-gray-800">
                                    {{ $page.props.auth.user.name }}
                                </div>
                                <div class="font-medium text-sm text-gray-500">
                                    {{ $page.props.auth.user.email }}
                                </div>
                            </div>
                        </div>

                        <div class="mt-3 space-y-1">
                            <ResponsiveNavLink :href="route('profile.show')" :active="route().current('profile.show')">
                                Profile
                            </ResponsiveNavLink>

                            <ResponsiveNavLink v-if="$page.props.jetstream.hasApiFeatures" :href="route('api-tokens.index')" :active="route().current('api-tokens.index')">
                                API Tokens
                            </ResponsiveNavLink>

                            <!-- Authentication -->
                            <form method="POST" @submit.prevent="logout">
                                <ResponsiveNavLink as="button">
                                    Log Out
                                </ResponsiveNavLink>
                            </form>

                            <!-- Team Management -->
                            <template v-if="$page.props.jetstream.hasTeamFeatures">
                                <div class="border-t border-gray-200" />

                                <div class="block px-4 py-2 text-xs text-gray-400">
                                    Manage Team
                                </div>

                                <!-- Team Settings -->
                                <ResponsiveNavLink v-if="$page.props.auth.user.current_team" :href="route('teams.show', $page.props.auth.user.current_team)" :active="route().current('teams.show')">
                                    Team Settings
                                </ResponsiveNavLink>

                                <ResponsiveNavLink v-if="$page.props.jetstream.canCreateTeams" :href="route('teams.create')" :active="route().current('teams.create')">
                                    Create New Team
                                </ResponsiveNavLink>

                                <!-- Team Switcher -->
                                <template v-if="$page.props.auth.user.all_teams && $page.props.auth.user.all_teams.length > 1">
                                    <div class="border-t border-gray-200" />

                                    <div class="block px-4 py-2 text-xs text-gray-400">
                                        Switch Teams
                                    </div>

                                    <template v-for="team in ($page.props.auth.user.all_teams || [])" :key="team.id">
                                        <form @submit.prevent="switchToTeam(team)">
                                            <ResponsiveNavLink as="button">
                                                <div class="flex items-center">
                                                    <svg v-if="team.id == $page.props.auth.user.current_team_id" class="me-2 size-5 text-green-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
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
                <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- Page Content -->
            <!-- Toasts -->
            <Toast />

            <main>
                <slot />
            </main>
        </div>
    </div>
</template>
