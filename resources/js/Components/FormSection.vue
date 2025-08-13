<script setup>
import { computed, useSlots } from 'vue';
import SectionTitle from './SectionTitle.vue';

defineEmits(['submitted']);

const hasActions = computed(() => !! useSlots().actions);
</script>

<template>
    <div class="max-w-2xl mx-auto">
        <form @submit.prevent="$emit('submitted')">
            <div class="px-4 py-5 bg-white sm:p-6 shadow sm:rounded-md">
                <div class="grid grid-cols-6 gap-6">
                    <slot name="form" />
                </div>
            </div>
            <div v-if="hasActions" class="flex items-center justify-end px-4 py-3 bg-gray-50 text-end sm:px-6 shadow sm:rounded-b-md">
                <slot name="actions" />
            </div>
        </form>
    </div>
</template>
