<script setup>
import { Link } from '@inertiajs/vue3';
const props = defineProps({
    active: { type: String, default: '' },
});
</script>

<template>
    <div class="mb-6">
        <nav class="flex space-x-8" aria-label="Tabs">
            <Link
                v-if="typeof route === 'function' && route().has('admin.companies.index')"
                :href="route('admin.companies.index')"
                class="rounded-md border border-green-200 px-3 py-2 text-sm font-medium text-green-600 hover:bg-green-50 hover:text-green-800"
            >
                会社管理
            </Link>
            <Link
                :href="route('admin.users.index')"
                :class="[
                    'rounded-md px-3 py-2 text-sm font-medium',
                    active === 'users' ? 'bg-red-100 text-red-700' : 'border border-red-200 text-red-600 hover:bg-red-50 hover:text-red-800',
                ]"
            >
                ユーザー管理
            </Link>

            <Link
                :href="route('debug.api')"
                class="rounded-md border border-blue-200 px-3 py-2 text-sm font-medium text-blue-600 hover:bg-blue-50 hover:text-blue-800"
            >
                APIデバッグページ
            </Link>
            <Link
                :href="route('admin.teams.index')"
                class="rounded-md border border-green-200 px-3 py-2 text-sm font-medium text-green-600 hover:bg-green-50 hover:text-green-800"
            >
                チーム管理
            </Link>
            <Link
                :href="route('admin.diaries.index')"
                class="rounded-md border border-green-200 px-3 py-2 text-sm font-medium text-green-600 hover:bg-green-50 hover:text-green-800"
            >
                日報管理
            </Link>
            <Link
                :href="route('admin.clients.index')"
                class="rounded-md border border-green-200 px-3 py-2 text-sm font-medium text-green-600 hover:bg-green-50 hover:text-green-800"
            >
                クライアント管理
            </Link>
            <!-- <Link :href="route('leader.dashboard')"
        class="text-orange-600 hover:text-orange-800 px-3 py-2 font-medium text-sm rounded-md border border-orange-200 hover:bg-orange-50">
        リーダーモードに切り替え
      </Link>
      <Link :href="route('user.dashboard')"
        class="text-blue-600 hover:text-blue-800 px-3 py-2 font-medium text-sm rounded-md border border-blue-200 hover:bg-blue-50">
        ユーザーモードに切り替え
      </Link> -->
        </nav>
    </div>
</template>
