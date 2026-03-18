<template>
    <AppLayout :title="タイトル">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">【進行管理】{{ $page.props.auth.user.name || 'ユーザー' }}さんのページ</h2>
        </template>
        <template>
            <AppLayout title="予定表">
                <template #header>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">予定表</h2>
                </template>
                <main>
                    <div class="py-2">
                        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">コンテンツ</div>
                    </div>
                </main>
            </AppLayout>
        </template>
        <script setup></script>

        <style scoped></style>
    </AppLayout>
</template>
