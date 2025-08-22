<script setup>
// ...existing code...

import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import AdminNavigationTabs from '@/Components/AdminNavigationTabs.vue';
import { router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import DialogModal from '@/Components/DialogModal.vue';

// ...existing code... (copied from Admin/Users/Index.vue with route names adjusted)

// ユーザー削除処理
function deleteUser(id) {
    if (confirm('本当にこのユーザーを削除しますか？')) {
        router.delete(route('superadmin.users.destroy', id), {
            onSuccess: () => {
                router.visit(route('superadmin.users.index'));
            }
        });
    }
}

// ...existing code continues, but route('admin.*') を route('superadmin.*') に置換しています

</script>

<template>
    <AppLayout title="ユーザー管理">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    ユーザー管理
                </h2>
                <template v-if="myuser?.is_superadmin">
                    <Link :href="route('superadmin.users.create')" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">
                        新規ユーザー登録
                    </Link>
                </template>
            </div>
        </template>

        <!-- ...existing template content, with admin routes replaced by superadmin routes... -->
    </AppLayout>
</template>
