<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AdminGeneralNavigationTabs from './AdminGeneralNavigationTabs.vue';
import AdminSunbrainNavigationTabs from './AdminSunbrainNavigationTabs.vue';

defineProps({
    active: { type: String, default: '' },
});

const isSunbrain = computed(() => usePage().props.auth?.companyType === 'sunbrain');
</script>

<template>
    <AdminSunbrainNavigationTabs v-if="isSunbrain" :active="active" />
    <AdminGeneralNavigationTabs v-else :active="active" />
</template>
