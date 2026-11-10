<script setup>
import { Link } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
defineProps({ source: { type: Object, default: null } });
</script>

<template>
    <div v-if="source" class="mt-1 break-words text-xs text-gray-600">
        <span>元のマイジョブ：</span>
        <Link v-if="source.entryId" :href="route('user.myjobbox.entries.show', { entry: source.entryId })" @click.stop class="text-blue-700 underline">{{ source.title }}</Link>
        <span v-else>{{ source.title }}</span>
        <div v-if="source.classification || source.projectName">{{ source.classification || source.projectName }}</div>
    </div>
</template>
