<template>
    <AppLayout title="ガントチャート（PoC）">
        <template #header>
            <h2 class="text-xl font-semibold">ガントチャート PoC</h2>
        </template>

        <div class="p-6">
            <GanttWrapper :tasks="tasks" @update-task="onUpdateTask" />

            <!-- Debug: show tasks payload and button to inject sample -->
            <div class="mt-6">
                <button class="rounded bg-gray-200 px-3 py-1" @click="addSample">Inject sample task</button>
                <pre class="mt-2 max-w-full overflow-auto rounded border bg-white p-3" style="font-size: 12px">{{
                    JSON.stringify(tasks, null, 2)
                }}</pre>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import GanttWrapper from '@/Components/GanttWrapper.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { usePage } from '@inertiajs/vue3';
import { reactive } from 'vue';
const page = usePage();

const rawSchedules = (page.props.schedules || []).map((s) => ({
    id: s.id,
    name: s.name,
    start: s.start_date,
    end: s.end_date,
    progress: s.progress || 0,
}));

// local reactive copy for optimistic updates
const tasks = reactive(rawSchedules);

function addSample() {
    // push a sample task that definitely spans visible dates
    tasks.push({
        id: `sample-${Date.now()}`,
        name: 'Sample Task',
        start: new Date().toISOString().slice(0, 10),
        end: new Date(Date.now() + 1000 * 60 * 60 * 24 * 7).toISOString().slice(0, 10),
        progress: 20,
    });
}

async function onUpdateTask(payload) {
    // payload: { id, start?, end?, progress? }
    const idRaw = payload.id;
    const idNum = Number(idRaw);

    // optimistic update locally — match by string to support sample ids
    const idx = tasks.findIndex((t) => String(t.id) === String(idRaw));
    if (idx !== -1) {
        if (payload.start !== undefined) tasks[idx].start = payload.start;
        if (payload.end !== undefined) tasks[idx].end = payload.end;
        if (payload.progress !== undefined) tasks[idx].progress = payload.progress;
    }

    // If id is not a finite number (e.g. sample-...), skip persistence
    if (!Number.isFinite(idNum)) {
        // non-persistable task (local/sample) — nothing to send to server
        return;
    }

    // send PATCH to server for real schedules
    try {
        const body = {};
        if (payload.start !== undefined) body.start = payload.start;
        if (payload.end !== undefined) body.end = payload.end;
        if (payload.progress !== undefined) body.progress = payload.progress;

        const res = await fetch(route('coordinator.project_schedules.update', { projectSchedule: idNum }), {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(body),
            credentials: 'same-origin',
        });
        if (!res.ok) throw new Error('network');
        const data = await res.json();
        // sync returned schedule if present
        if (data.schedule) {
            const s = data.schedule;
            const i = tasks.findIndex((t) => Number(t.id) === Number(s.id));
            if (i !== -1) {
                tasks[i].start = s.start_date || tasks[i].start;
                tasks[i].end = s.end_date || tasks[i].end;
                tasks[i].progress = s.progress ?? tasks[i].progress;
            }
        }
    } catch (e) {
        console.error('Failed to persist schedule update', e);
        // optionally refetch or rollback optimistic update (omitted for brevity)
    }
}
</script>
