<script setup>
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    title: { type: String, default: '入試データ デモ' },
    isGuest: { type: Boolean, default: false },
});

const csrfToken = computed(() => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '');
</script>

<template>
    <Head :title="`${props.title} | N_DB SAMPLE`" />

    <div class="min-h-screen bg-gray-100 text-gray-800">
        <header class="bg-[#1a3a6b] text-white shadow-sm">
            <div class="mx-auto flex max-w-[1100px] flex-wrap items-center gap-x-5 gap-y-3 px-4 py-3">
                <h1 class="whitespace-nowrap text-[1.05rem] font-bold tracking-wider">N_DB SAMPLE - 入試データ デモ</h1>
                <nav class="flex items-center gap-4 text-sm" aria-label="デモ内ナビゲーション">
                    <a :href="route('n-demo.index')" class="whitespace-nowrap text-blue-200 hover:text-white hover:underline">学校一覧</a>
                    <a :href="route('n-demo.search')" class="whitespace-nowrap text-blue-200 hover:text-white hover:underline">全文検索</a>
                </nav>

                <div v-if="$slots.headerSearch" class="order-3 w-full md:order-none md:ml-auto md:w-auto md:flex-1">
                    <slot name="headerSearch" />
                </div>

                <form v-if="props.isGuest" :action="route('n-guest.logout')" method="post" class="ml-auto md:ml-0">
                    <input type="hidden" name="_token" :value="csrfToken" />
                    <input type="hidden" name="slug" value="n-demo" />
                    <button
                        type="submit"
                        class="whitespace-nowrap rounded border border-white/40 px-3 py-1 text-xs text-blue-100 hover:bg-white/10"
                    >
                        ログアウト
                    </button>
                </form>
            </div>
        </header>

        <div class="mx-auto max-w-[1100px] px-4 py-6">
            <slot />
        </div>
    </div>
</template>
