<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { useForm } from '@inertiajs/vue3';
import useToasts from '@/Composables/useToasts';

const props = defineProps({
    leaderUser:  { type: Object, required: true },
    permissions: { type: Object, required: true },
    updateRoute: { type: String, default: 'admin.leader_permissions.update' },
    indexRoute:  { type: String, default: 'admin.leader_permissions.index' },
});

const { addToast } = useToasts();

const permItems = [
    { key: 'client_management',      label: 'クライアント管理' },
    { key: 'diary_management',       label: '日報管理' },
    { key: 'workload_analysis',      label: '作業量分析' },
    { key: 'workload_setting',       label: '作業項目設定' },
    { key: 'work_record_management', label: '勤務時間管理' },
    { key: 'dispatch_management',    label: '派遣管理' },
    { key: 'user_management',        label: 'ユーザー管理（部署リーダーのみ有効）' },
];

const form = useForm({
    client_management:      props.permissions.client_management,
    diary_management:       props.permissions.diary_management,
    workload_analysis:      props.permissions.workload_analysis,
    workload_setting:       props.permissions.workload_setting,
    work_record_management: props.permissions.work_record_management,
    dispatch_management:    props.permissions.dispatch_management,
    user_management:        props.permissions.user_management,
});

const submit = () => {
    form.put(route(props.updateRoute, { leaderuser: props.leaderUser.id }), {
        onSuccess: () => {
            addToast('権限設定を保存しました', 'success');
        },
        onError: () => {
            addToast('保存に失敗しました', 'error');
        },
    });
};

const goBack = () => {
    window.location.href = route(props.indexRoute);
};
</script>

<template>
    <AppLayout title="Leader権限設定">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Leader権限設定</h2>
            </div>
        </template>

        <div class="rounded bg-white p-6 shadow">
            <div class="mx-auto max-w-lg">
                <!-- 対象ユーザー情報 -->
                <div class="mb-6 rounded-md bg-gray-50 px-4 py-3">
                    <p class="text-sm font-medium text-gray-700">{{ leaderUser.name }}</p>
                    <p class="text-xs text-gray-500">{{ leaderUser.email }}</p>
                </div>

                <!-- 権限チェックボックス -->
                <form @submit.prevent="submit">
                    <div class="divide-y divide-gray-200">
                        <label
                            v-for="item in permItems"
                            :key="item.key"
                            class="flex cursor-pointer items-center justify-between py-4"
                        >
                            <span class="text-sm font-medium text-gray-700">{{ item.label }}</span>
                            <input
                                v-model="form[item.key]"
                                type="checkbox"
                                class="h-5 w-5 rounded border-gray-300 text-orange-600 focus:ring-orange-500"
                            />
                        </label>
                    </div>

                    <!-- ボタン -->
                    <div class="mt-8 flex items-center justify-end gap-3">
                        <button
                            type="button"
                            @click="goBack"
                            class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            保存せずに戻る
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded bg-orange-600 px-4 py-2 text-sm font-bold text-white hover:bg-orange-700 disabled:opacity-50"
                        >
                            保存
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
