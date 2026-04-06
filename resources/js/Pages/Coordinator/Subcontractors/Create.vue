<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ coordinators: Array });

const form = useForm({
    name: '',
    email: '',
    phone: '',
    notes: '',
    coordinator_ids: [],
});

// ===== 重複チェック =====
const showDuplicateModal = ref(false);
const duplicates = ref([]);
const isChecking = ref(false);

async function submit() {
    if (!form.name.trim()) return;

    isChecking.value = true;
    try {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
        const res = await fetch(route('coordinator.subcontractors.check_duplicate'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ name: form.name }),
        });
        if (res.ok) {
            const data = await res.json();
            if (data.duplicates && data.duplicates.length > 0) {
                duplicates.value = data.duplicates;
                showDuplicateModal.value = true;
                return;
            }
        }
    } catch {
        // チェック失敗時はそのまま続行
    } finally {
        isChecking.value = false;
    }

    form.post(route('coordinator.subcontractors.store'));
}

function toggleCoordinator(id) {
    const idx = form.coordinator_ids.indexOf(id);
    if (idx >= 0) form.coordinator_ids.splice(idx, 1);
    else form.coordinator_ids.push(id);
}
</script>

<template>
    <AppLayout title="外注先新規作成">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">外注先 新規作成</h2>
                <Link :href="route('coordinator.subcontractors.index')" class="text-gray-600 hover:text-gray-900">← 一覧に戻る</Link>
            </div>
        </template>

        <div class="rounded bg-white p-6 shadow">
            <form @submit.prevent="submit" class="space-y-4 max-w-lg">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">名前 / 会社名 <span class="text-red-500">*</span></label>
                    <p class="mb-1 text-xs text-gray-400">個人名・会社名どちらでも可</p>
                    <input v-model="form.name" type="text" required class="w-full rounded border px-2 py-1 text-sm" />
                    <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">メールアドレス</label>
                    <input v-model="form.email" type="email" class="w-full rounded border px-2 py-1 text-sm" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">電話番号</label>
                    <input v-model="form.phone" type="text" class="w-full rounded border px-2 py-1 text-sm" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">備考</label>
                    <textarea v-model="form.notes" rows="3" class="w-full rounded border px-2 py-1 text-sm"></textarea>
                </div>

                <!-- 管理Coordinator -->
                <div v-if="props.coordinators && props.coordinators.length">
                    <label class="mb-1 block text-sm font-medium text-gray-700">管理担当 Coordinator</label>
                    <div class="flex flex-wrap gap-2 rounded border p-2">
                        <label
                            v-for="co in props.coordinators"
                            :key="co.id"
                            class="flex items-center gap-1 cursor-pointer select-none text-sm"
                        >
                            <input
                                type="checkbox"
                                :value="co.id"
                                :checked="form.coordinator_ids.includes(co.id)"
                                @change="toggleCoordinator(co.id)"
                                class="rounded"
                            />
                            {{ co.name }}
                        </label>
                    </div>
                </div>

                <button
                    type="submit"
                    :disabled="isChecking || form.processing"
                    class="flex items-center gap-2 rounded bg-green-600 px-4 py-2 font-bold text-white hover:bg-green-700 disabled:opacity-60"
                >
                    <svg v-if="isChecking || form.processing" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                    </svg>
                    登録
                </button>
            </form>
        </div>
    </AppLayout>

    <!-- 重複警告モーダル -->
    <Teleport to="body">
        <div v-if="showDuplicateModal" class="fixed inset-0 z-50 flex items-center justify-center">
            <div class="absolute inset-0 bg-black/50" @click="showDuplicateModal = false" />
            <div class="relative z-10 w-full max-w-lg rounded-lg bg-white shadow-xl p-6">
                <div class="mb-4 flex items-center gap-3">
                    <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-yellow-100">
                        <svg class="h-6 w-6 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900">類似外注先が見つかりました</h3>
                </div>
                <p class="mb-3 text-sm text-gray-700">
                    「<strong>{{ form.name }}</strong>」と似た外注先が既に登録されています。
                </p>
                <div class="mb-4 rounded bg-yellow-50 p-3">
                    <ul class="space-y-1">
                        <li v-for="d in duplicates" :key="d.id" class="flex items-center gap-2 text-sm text-gray-800">
                            <span class="h-1.5 w-1.5 rounded-full bg-yellow-500 flex-shrink-0" />
                            <span class="font-medium">{{ d.name }}</span>
                        </li>
                    </ul>
                </div>
                <p class="mb-4 text-sm font-medium text-red-600">名前を変更してから再度登録してください。</p>
                <div class="flex justify-end">
                    <button type="button" @click="showDuplicateModal = false" class="rounded bg-gray-200 px-4 py-2 font-bold text-gray-700 hover:bg-gray-300">
                        閉じる（名前を変更する）
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
