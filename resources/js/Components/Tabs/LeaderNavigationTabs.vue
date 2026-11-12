<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import LeaderGeneralNavigationTabs from './LeaderGeneralNavigationTabs.vue';
import LeaderSunbrainNavigationTabs from './LeaderSunbrainNavigationTabs.vue';

defineProps({
    active: { type: String, default: '' },
});

const isSunbrain = computed(() => usePage().props.auth?.companyType === 'sunbrain');
</script>

<template>
    <LeaderSunbrainNavigationTabs v-if="isSunbrain" :active="active" />
    <LeaderGeneralNavigationTabs v-else :active="active" />
</template>
