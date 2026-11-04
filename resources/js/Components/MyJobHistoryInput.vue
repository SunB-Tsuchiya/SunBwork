<script setup>
import { computed } from 'vue';
import QuickWorkEntryModal from '@/Components/Calendar/QuickWorkEntryModal.vue';
const props = defineProps({ selection: { type: Object, default: null } });
const emit = defineEmits(['close', 'saved']);
const entry = computed(() => {
    if (!props.selection) return null;
    const seed = props.selection.record.work_entry;
    return { ...seed, id: props.selection.kind === 'continue' ? seed.id : undefined,
        work_date: props.selection.date, plan_slot: 'hour', planned_start_time: null, planned_end_time: null,
        actual_quantity: null, result_note: null };
});
const source = computed(() => props.selection?.kind === 'continue' ? { type: 'entry', id: props.selection.record.id } : null);
</script>

<template>
    <QuickWorkEntryModal :show="Boolean(selection)" :entry="entry" :date="selection?.date || ''" :reuse-entry-id="selection?.kind === 'new' ? selection.record.id : null" :continuation-source="source" :start-min="selection?.startMin ?? null" :end-min="selection?.endMin ?? null" entry-point="myjob" @close="emit('close')" @saved="(date, id) => emit('saved', date, id)" />
</template>
