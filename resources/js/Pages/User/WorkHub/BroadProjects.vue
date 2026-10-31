<script setup>
import BroadProjects from '@/Pages/SuperAdmin/BroadProjects/Index.vue';
defineProps({ projects: { type: Array, default: () => [] }, canManage: { type: Boolean, default: false } });
</script>
<template><BroadProjects :projects="projects" :can-manage="canManage" route-prefix="user.work.broad_projects" /></template>
