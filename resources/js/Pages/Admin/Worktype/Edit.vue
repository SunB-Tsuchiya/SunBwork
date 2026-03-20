<script setup>
import AdminNavigationTabs from '@/Components/Tabs/AdminNavigationTabs.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

const props = defineProps({
    worktypes:    { type: Array,  default: () => [] },
    company_id:   { type: Number, default: null },
    company_name: { type: String, default: null },
});

const hours   = Array.from({ length: 24 }, (_, i) => String(i).padStart(2, '0'));
const minutes = ['00', '15', '30', '45'];

function parseTime(timeStr) {
    const parts = (timeStr || '00:00').substring(0, 5).split(':');
    return { hour: parts[0] || '00', minute: parts[1] || '00' };
}

const state = reactive({
    rows: props.worktypes.length
        ? props.worktypes.map((wt) => {
              const s = parseTime(wt.start_time);
              const e = parseTime(wt.end_time);
              return {
                  id:           wt.id,
                  name:         wt.name,
                  start_hour:   s.hour,
                  start_minute: s.minute,
                  end_hour:     e.hour,
                  end_minute:   e.minute,
                  sort_order:   wt.sort_order ?? 0,
                  _deleted:     false,
              };
          })
        : [{ id: null, name: '', start_hour: '09', start_minute: '00', end_hour: '18', end_minute: '00', sort_order: 0, _deleted: false }],
});

const sortedRows = computed(() =>
    [...state.rows].sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0)),
);

function moveUp(item) {
    const sorted = sortedRows.value;
    const idx = sorted.indexOf(item);
    if (idx <= 0) return;
    const prev = sorted[idx - 1];
    const temp = item.sort_order;
    item.sort_order = prev.sort_order;
    prev.sort_order = temp;
}

function moveDown(item) {
    const sorted = sortedRows.value;
    const idx = sorted.indexOf(item);
    if (idx >= sorted.length - 1) return;
    const next = sorted[idx + 1];
    const temp = item.sort_order;
    item.sort_order = next.sort_order;
    next.sort_order = temp;
}

function addRow() {
    const maxOrder = state.rows.length ? Math.max(...state.rows.map((r) => r.sort_order ?? 0)) : 0;
    state.rows.push({
        id: null, name: '', start_hour: '08', start_minute: '00',
        end_hour: '18', end_minute: '00', sort_order: maxOrder + 1, _deleted: false,
    });
}

function markDelete(item) { item._deleted = true; }
function undoDelete(item) { item._deleted = false; }

function submit() {
    const payload = state.rows.map((r) => ({
        id:         r.id,
        name:       r.name,
        start_time: `${r.start_hour}:${r.start_minute}`,
        end_time:   `${r.end_hour}:${r.end_minute}`,
        sort_order: r.sort_order,
        _deleted:   r._deleted,
    }));
    router.post(route('admin.worktypes.update'), { company_id: props.company_id, rows: payload });
}
</script>

<template>
    <AppLayout title="勤務形態設定 - 編集">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                勤務形態設定 - 編集
                <span v-if="company_name" class="ml-2 text-base font-normal text-gray-500">{{ company_name }}</span>
            </h2>
        </template>
        <template #tabs>
            <AdminNavigationTabs active="worktypes" />
        </template>

        <div class="rounded bg-white p-6 shadow">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="w-28 px-3 py-2 text-left font-medium text-gray-600">順序</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">名称</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">始業時間</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">終業時間</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr
                            v-for="(item, idx) in sortedRows"
                            :key="item.id ?? 'new-' + idx"
                            :class="item._deleted ? 'bg-red-50 opacity-60' : 'hover:bg-gray-50'"
                        >
                            <!-- 順序列 -->
                            <td class="px-3 py-2">
                                <div class="flex items-center gap-1">
                                    <span class="w-6 text-right text-gray-700">{{ (item.sort_order ?? 0) }}</span>
                                    <div class="flex flex-col">
                                        <button
                                            type="button"
                                            :disabled="idx === 0 || !!item._deleted"
                                            class="flex h-5 w-5 items-center justify-center rounded text-gray-500 hover:bg-gray-200 disabled:cursor-not-allowed disabled:opacity-30"
                                            @click="moveUp(item)"
                                        >▲</button>
                                        <button
                                            type="button"
                                            :disabled="idx === sortedRows.length - 1 || !!item._deleted"
                                            class="flex h-5 w-5 items-center justify-center rounded text-gray-500 hover:bg-gray-200 disabled:cursor-not-allowed disabled:opacity-30"
                                            @click="moveDown(item)"
                                        >▼</button>
                                    </div>
                                </div>
                            </td>
                            <!-- 名称 -->
                            <td class="px-3 py-2">
                                <input
                                    v-model="item.name"
                                    type="text"
                                    :disabled="!!item._deleted"
                                    class="w-32 rounded border border-gray-300 px-2 py-1 text-sm disabled:bg-gray-100 disabled:text-gray-400"
                                />
                            </td>
                            <!-- 始業時間 -->
                            <td class="px-3 py-2">
                                <div class="flex items-center gap-1">
                                    <select v-model="item.start_hour" :disabled="!!item._deleted" class="rounded border border-gray-300 px-2 py-1 text-sm disabled:bg-gray-100">
                                        <option v-for="h in hours" :key="h" :value="h">{{ parseInt(h) }}時</option>
                                    </select>
                                    <select v-model="item.start_minute" :disabled="!!item._deleted" class="rounded border border-gray-300 px-2 py-1 text-sm disabled:bg-gray-100">
                                        <option v-for="m in minutes" :key="m" :value="m">{{ m }}分</option>
                                    </select>
                                </div>
                            </td>
                            <!-- 終業時間 -->
                            <td class="px-3 py-2">
                                <div class="flex items-center gap-1">
                                    <select v-model="item.end_hour" :disabled="!!item._deleted" class="rounded border border-gray-300 px-2 py-1 text-sm disabled:bg-gray-100">
                                        <option v-for="h in hours" :key="h" :value="h">{{ parseInt(h) }}時</option>
                                    </select>
                                    <select v-model="item.end_minute" :disabled="!!item._deleted" class="rounded border border-gray-300 px-2 py-1 text-sm disabled:bg-gray-100">
                                        <option v-for="m in minutes" :key="m" :value="m">{{ m }}分</option>
                                    </select>
                                </div>
                            </td>
                            <!-- 操作 -->
                            <td class="whitespace-nowrap px-3 py-2">
                                <button v-if="!item._deleted" type="button" class="text-red-600 hover:underline" @click="markDelete(item)">削除</button>
                                <button v-else type="button" class="text-green-600 hover:underline" @click="undoDelete(item)">元に戻す</button>
                            </td>
                        </tr>
                        <tr v-if="state.rows.length === 0">
                            <td colspan="5" class="px-3 py-4 text-center text-gray-400">登録がありません</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex items-center justify-between">
                <button type="button" @click="addRow" class="rounded border border-gray-400 px-3 py-1 text-sm text-gray-700 hover:bg-gray-100">
                    追加する
                </button>
                <div class="flex gap-4">
                    <button type="button" @click="submit" class="rounded bg-red-600 px-4 py-2 text-sm font-bold text-white hover:bg-red-700">
                        保存する
                    </button>
                    <Link :href="route('admin.worktypes.index')" class="rounded bg-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-300">
                        保存せずに戻る
                    </Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
