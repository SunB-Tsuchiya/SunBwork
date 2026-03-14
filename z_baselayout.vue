<template>
    <AppLayout :title="`ジョブ割り当て一覧 - ${projectJob.title}`">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">【進行管理】{{ $page.props.auth.user.name || 'ユーザー' }}さんのページ</h2>
        </template>

        <div class="mx-auto max-w-6xl rounded bg-white p-6 shadow">
            <h1 class="mb-4 text-2xl font-bold">ジョブ割り当て一覧：{{ projectJob.title }}</h1>
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div class="mb-4 flex items-center gap-2">contents</div>
            </div>
            <div class="overflow-x-auto"></div>
        </div>
    </AppLayout>
</template>

<style scoped></style>
