<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import CoordinatorGeneralNavigationTabs from './CoordinatorGeneralNavigationTabs.vue';
import CoordinatorSunbrainNavigationTabs from './CoordinatorSunbrainNavigationTabs.vue';

defineProps({
    active: { type: String, default: '' },
    projectJob: { type: Object, default: null },
});

const isSunbrain = computed(() => usePage().props.auth?.companyType === 'sunbrain');
</script>

<template>
    <CoordinatorSunbrainNavigationTabs v-if="isSunbrain" :active="active" :project-job="projectJob" />
    <CoordinatorGeneralNavigationTabs v-else :active="active" :project-job="projectJob" />
</template>
