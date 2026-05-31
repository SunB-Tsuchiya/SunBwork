<script setup>
defineProps({
    show: { type: Boolean, default: false },
})
</script>

<template>
    <div v-if="show" class="rounded-lg border border-yellow-300 bg-yellow-50 p-10 text-center">
        <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto mb-3 h-8 w-8 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
        </svg>
        <p class="text-base font-semibold text-yellow-800">会社を選択してください</p>
        <p class="mt-1 text-sm text-yellow-700">
            グローバル管理モードではこのページを表示できません。<br>
            画面上部の会社切り替えから表示したい会社を選択してください。
        </p>
    </div>
    <slot v-else />
</template>
