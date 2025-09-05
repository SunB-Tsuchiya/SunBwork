<template>
    <div class="relative">
        <!-- 現在のチーム表示 -->
        <Dropdown align="right" width="60">
            <template #trigger>
                <span class="inline-flex rounded-md">
                    <button
                        type="button"
                        class="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition duration-150 ease-in-out hover:text-gray-700 focus:bg-gray-50 focus:outline-none active:bg-gray-50"
                    >
                        <div class="flex items-center">
                            <!-- チームアイコン -->
                            <div class="mr-3 flex-shrink-0">
                                <div
                                    v-if="currentTeam && currentTeam.team_type === 'department'"
                                    class="flex h-6 w-6 items-center justify-center rounded-full bg-blue-100"
                                >
                                    <svg class="h-4 w-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-4m-5 0H3m2 0h4m7 0v-3a1 1 0 00-1-1h-4a1 1 0 00-1 1v3m9 0V9a1 1 0 00-1-1h-4a1 1 0 00-1 1v8"
                                        ></path>
                                    </svg>
                                </div>
                                <div v-else class="flex h-6 w-6 items-center justify-center rounded-full bg-green-100">
                                    <svg class="h-4 w-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                                        ></path>
                                    </svg>
                                </div>
                            </div>

                            <!-- チーム情報 -->
                            <div class="text-left">
                                <div class="text-sm font-medium text-gray-900">
                                    {{ currentTeam ? currentTeam.name : '' }}
                                </div>
                                <div class="text-xs text-gray-500" v-if="currentTeam && currentTeam.company_name">
                                    {{ currentTeam.company_name }}
                                    <span v-if="currentTeam.department_name"> - {{ currentTeam.department_name }}</span>
                                </div>
                            </div>
                        </div>

                        <svg
                            class="-mr-0.5 ml-2 h-4 w-4"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="1.5"
                            stroke="currentColor"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 15L12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9" />
                        </svg>
                    </button>
                </span>
            </template>

            <template #content>
                <!-- チーム一覧 -->
                <div class="w-60">
                    <div class="block border-b border-gray-100 px-4 py-2 text-xs text-gray-400">チーム切り替え</div>

                    <!-- 部署チーム -->
                    <div v-if="availableTeams && availableTeams.department && availableTeams.department.length > 0">
                        <div class="block bg-gray-50 px-4 py-2 text-xs font-semibold text-gray-700">部署チーム</div>
                        <template v-for="team in availableTeams.department" :key="`dept-${team.id}`">
                            <button
                                @click="switchTeam(team.id)"
                                class="block w-full px-4 py-2 text-left text-sm text-gray-700 transition duration-150 ease-in-out hover:bg-gray-100"
                                :class="{ 'bg-gray-100 font-medium': team.id === (currentTeam ? currentTeam.id : null) }"
                            >
                                <div class="flex items-center">
                                    <div class="mr-3 flex h-6 w-6 items-center justify-center rounded-full bg-blue-100">
                                        <svg class="h-4 w-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-4m-5 0H3m2 0h4m7 0v-3a1 1 0 00-1-1h-4a1 1 0 00-1 1v3m9 0V9a1 1 0 00-1-1h-4a1 1 0 00-1 1v8"
                                            ></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="font-medium">{{ team.name }}</div>
                                        <div class="text-xs text-gray-500">{{ team.company_name }} - {{ team.department_name }}</div>
                                        <!-- <div class="text-xs text-blue-600">役割: {{ team.pivot.role }}</div> -->
                                    </div>
                                </div>
                            </button>
                        </template>
                    </div>

                    <!-- 個人チーム -->
                    <div v-if="availableTeams && availableTeams.personal && availableTeams.personal.length > 0">
                        <div class="block border-t border-gray-100 bg-gray-50 px-4 py-2 text-xs font-semibold text-gray-700">個人チーム</div>
                        <template v-for="team in availableTeams.personal" :key="`personal-${team.id}`">
                            <button
                                @click="switchTeam(team.id)"
                                class="block w-full px-4 py-2 text-left text-sm text-gray-700 transition duration-150 ease-in-out hover:bg-gray-100"
                                :class="{ 'bg-gray-100 font-medium': team.id === (currentTeam ? currentTeam.id : null) }"
                            >
                                <div class="flex items-center">
                                    <div class="mr-3 flex h-6 w-6 items-center justify-center rounded-full bg-green-100">
                                        <svg class="h-4 w-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                                            ></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="font-medium">{{ team.name }}</div>
                                        <div class="text-xs text-gray-500">個人ワークスペース</div>
                                        <div class="text-xs text-green-600">
                                            役割: {{ team.pivot && team.pivot.role ? team.pivot.role : 'オーナー' }}
                                        </div>
                                    </div>
                                </div>
                            </button>
                        </template>
                    </div>
                </div>
            </template>
        </Dropdown>
    </div>
</template>

<script setup>
import Dropdown from '@/Components/Dropdown.vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    user: {
        type: Object,
        required: true,
    },
    currentTeam: {
        type: Object,
        required: true,
    },
    availableTeams: {
        type: Object,
        required: true,
        default: () => ({ department: [], personal: [] }),
    },
});

const switchTeam = (teamId) => {
    router.put(
        route('current-team.update'),
        {
            team_id: teamId,
        },
        {
            preserveState: false,
        },
    );
};
</script>
