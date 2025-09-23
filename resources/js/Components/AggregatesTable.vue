<script setup>
import { defineProps } from 'vue';

const props = defineProps({
    // row: object that contains aggregates.assigned | aggregates.self
    row: { type: Object, required: true },
    mode: { type: String, default: 'self' }, // 'self' or 'assigned' — determines which aggregates to show
});

function formatHour(h) {
    if (typeof h === 'undefined' || h === null) return 0;
    const n = Number(h) || 0;
    return Number.isInteger(n) ? String(n) : String(Math.round(n * 10) / 10);
}

function diffMinutes(estimated, actual) {
    const e = Number(estimated) || 0;
    const a = Number(actual) || 0;
    const diffHours = e - a;
    const diff = Math.round(diffHours * 60);
    return diff;
}

const key = props.mode === 'assigned' ? 'assigned' : 'self';
</script>

<template>
    <table class="mt-2 w-full table-fixed text-sm">
        <thead>
            <tr>
                <th class="px-2 py-2 text-left text-xs text-gray-500" style="width: 10ch; min-width: 10ch">総ページ</th>
                <th class="px-2 py-2 text-left text-xs text-gray-500" style="width: 10ch; min-width: 10ch">作業時間(時)</th>
                <th class="px-2 py-2 text-left text-xs text-gray-500" style="width: 10ch; min-width: 10ch">見込時間(時)</th>
                <th class="px-2 py-2 text-left text-xs text-gray-500" style="width: 10ch; min-width: 10ch">差(分)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="px-2 py-2" style="width: 10ch; min-width: 10ch">
                    {{ row.aggregates?.[key]?.pages ?? 0 }}
                </td>
                <td class="px-2 py-2" style="width: 10ch; min-width: 10ch">
                    {{ formatHour(row.aggregates?.[key]?.work_hours) }}
                </td>
                <td class="px-2 py-2" style="width: 10ch; min-width: 10ch">
                    {{ formatHour(row.aggregates?.[key]?.desired_hours) }}
                </td>
                <td class="px-2 py-2" style="width: 10ch; min-width: 10ch">
                    {{ diffMinutes(row.aggregates?.[key]?.desired_hours, row.aggregates?.[key]?.work_hours) }}
                </td>
            </tr>
        </tbody>
    </table>
</template>
