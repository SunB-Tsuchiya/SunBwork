<script setup>
import { router } from '@inertiajs/vue3';
import { onMounted } from 'vue';

// このページは廃止済み。Index にリダイレクト。
onMounted(() => {
    router.get(route('workload_setting.index'), {}, { replace: true });
});
</script>

<template>
    <div></div>
</template>
