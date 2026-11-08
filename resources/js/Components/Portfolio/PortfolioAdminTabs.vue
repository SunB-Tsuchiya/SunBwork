<script setup>
import { Link } from '@inertiajs/vue3';

// ポートフォリオ管理（admin エリア）のタブバー。チームルーム（Pages/TeamRoom/Show.vue）と同じ、下線つきの固定タブ。
// タブごとに別のページ（URL）なので、Inertia の Link で移動する（戻る・再読み込みでも同じタブに戻れる）。
// 有効なタブの色は、管理エリアの標準（Admin = 赤）。
defineProps({
    active: { type: String, required: true }, // 'members' | 'permissions'
    canManagePermissions: { type: Boolean, default: false }, // 利用メンバー設定は SuperAdmin のみ
});

const tab = (isActive) => [
    'whitespace-nowrap px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors',
    isActive
        ? 'border-red-500 text-red-700'
        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
];
</script>

<template>
    <!-- スティッキータブバー（チームルームと同じ形） -->
    <div class="sticky top-0 z-20 rounded-t bg-white px-6 pt-4 pb-0 shadow-md">
        <div class="flex gap-1 overflow-x-auto border-b border-gray-200">
            <Link :href="route('admin.portfolio.manage')" :class="tab(active === 'members')">メンバーのポートフォリオ</Link>
            <Link v-if="canManagePermissions" :href="route('admin.portfolio.permissions.index')" :class="tab(active === 'permissions')">利用メンバー設定</Link>
        </div>
    </div>
</template>
