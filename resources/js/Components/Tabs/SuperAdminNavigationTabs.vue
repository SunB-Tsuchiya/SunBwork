<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import SuperAdminGeneralNavigationTabs from './SuperAdminGeneralNavigationTabs.vue';
import SuperAdminSunbrainNavigationTabs from './SuperAdminSunbrainNavigationTabs.vue';

defineProps({
    active: { type: String, default: '' },
});

const isSunbrain = computed(() => usePage().props.auth?.companyType === 'sunbrain');
</script>

<template>
    <SuperAdminSunbrainNavigationTabs v-if="isSunbrain" :active="active" />
    <SuperAdminGeneralNavigationTabs v-else :active="active" />
</template>
