<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import AdminNavigationTabs from '@/Components/Tabs/AdminNavigationTabs.vue';
import { useForm } from '@inertiajs/vue3';
import useToasts from '@/Composables/useToasts';

const props = defineProps({
    setting: { type: Object, required: true },
});

const { addToast } = useToasts();

const roleOptions = [
    { value: 'coordinator', label: 'Coordinator 以上' },
    { value: 'leader',      label: 'Leader 以上' },
    { value: 'admin',       label: 'Admin 以上' },
    { value: 'superadmin',  label: 'SuperAdmin のみ' },
];

const form = useForm({
    can_add_to_others_min_role: props.setting.can_add_to_others_min_role ?? 'coordinator',
});

const submit = () => {
    form.put(route('admin.schedule-settings.update'), {
        onSuccess: () => addToast('権限設定を更新しました', 'success'),
        onError:   () => addToast('保存に失敗しました', 'error'),
    });
};
</script>

<template>
    <AppLayout title="予定表権限設定">
        <template #header>
            <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">予定表権限設定</h2>
        </template>
        <template #tabs>
            <AdminNavigationTabs />
        </template>

        <div class="rounded bg-white px-4 py-6 sm:p-6 shadow">
            <div class="mx-auto max-w-lg">
                <form @submit.prevent="submit">
                    <div class="rounded-md border border-gray-200 divide-y divide-gray-100">
                        <!-- 設定項目 -->
                        <div class="px-4 py-5">
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                他のユーザーを参加者に追加できる最低ロール
                            </label>
                            <p class="text-xs text-gray-500 mb-3">
                                予定を作成・編集する際に、他のメンバーを参加者として招待できるロールの下限を設定します。<br>
                                設定より低いロールのユーザーが参加者を指定した場合、自動的に無視されます。
                            </p>
                            <select
                                v-model="form.can_add_to_others_min_role"
                                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-red-500 focus:outline-none focus:ring-1 focus:ring-red-500"
                            >
                                <option
                                    v-for="opt in roleOptions"
                                    :key="opt.value"
                                    :value="opt.value"
                                >{{ opt.label }}</option>
                            </select>
                            <p v-if="form.errors.can_add_to_others_min_role" class="mt-1 text-xs text-red-600">
                                {{ form.errors.can_add_to_others_min_role }}
                            </p>
                        </div>
                    </div>

                    <!-- ボタン -->
                    <div class="mt-6 flex justify-end">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded bg-red-600 px-5 py-2 text-sm font-bold text-white hover:bg-red-700 disabled:opacity-50"
                        >
                            保存
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
