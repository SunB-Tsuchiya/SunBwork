<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import IrukaBoard from '@/Components/Iruka/IrukaBoard.vue';
import { Link } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const user = page.props.user;

defineProps({
    departments:  { type: Array,  default: () => [] },
    pendingCount: { type: Number, default: 0 },
});
</script>

<template>
    <AppLayout title="Proof Admin Dashboard">
        <template #header>
            <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">
                校正管理 ダッシュボード
            </h2>
        </template>

        <div class="space-y-4">
            <!-- 受信トレイへのクイックリンク -->
            <div v-if="pendingCount > 0" class="flex items-center gap-3">
                <Link
                    :href="route('proof_coordinator.inbox')"
                    class="inline-flex items-center gap-2 rounded-lg bg-pink-600 px-4 py-2 text-sm font-medium text-white hover:bg-pink-700"
                >
                    📥 未受理の校正依頼
                    <span class="inline-flex items-center rounded-full bg-white px-2 py-0.5 text-xs font-bold text-pink-600">
                        {{ pendingCount }}
                    </span>
                </Link>
            </div>
            <div v-else class="flex items-center gap-3">
                <Link
                    :href="route('proof_coordinator.inbox')"
                    class="inline-flex items-center gap-2 rounded-lg border border-pink-200 px-4 py-2 text-sm font-medium text-pink-600 hover:bg-pink-50"
                >
                    📥 校正依頼受信トレイ
                </Link>
            </div>

            <!-- 在席ボード -->
            <IrukaBoard :departments="departments" />
        </div>
    </AppLayout>
</template>
