<script setup>
import { Link } from '@inertiajs/vue3';
import logoUrl from '@/assets/header_logo.png';
</script>

<template>
    <Link :href="'/'">
        <img :src="logoUrl" alt="SB Work" class="size-16" />
    </Link>
</template>
