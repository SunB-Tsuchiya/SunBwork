<script setup>
import BroadProjects from '@/Pages/SuperAdmin/BroadProjects/Index.vue';
defineProps({ projects: { type: Array, default: () => [] } });
</script>
<template><BroadProjects :projects="projects" route-prefix="user.work.broad_projects" /></template>
