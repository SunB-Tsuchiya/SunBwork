<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SuperAdminNavigationTabs from '@/Components/Tabs/SuperAdminNavigationTabs.vue';
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const props = defineProps({
    membership: { type: Object, required: true },
    companies: { type: Array, default: () => [] },
});

const form = useForm({
    company_id: props.membership.company_id ? String(props.membership.company_id) : '',
    department_id: props.membership.department_id ? String(props.membership.department_id) : '',
});

const departments = computed(() => {
    const company = props.companies.find((item) => String(item.id) === form.company_id);
    return company?.departments ?? [];
});

watch(() => form.company_id, (companyId, previousCompanyId) => {
    if (previousCompanyId !== undefined && companyId !== previousCompanyId) {
        form.department_id = '';
    }
});

const submit = () => {
    form.put(route('superadmin.membership.update'), { preserveScroll: true });
};
</script>

<template>
    <AppLayout title="会社・部署設定">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">会社・部署設定</h2>
        </template>

        <template #tabs>
            <SuperAdminNavigationTabs active="membership" />
        </template>

        <div class="rounded bg-white p-6 shadow">
            <div class="max-w-2xl">
                <p class="text-sm text-gray-600">
                    SuperAdmin 本人がメンバーとして所属する会社と部署を設定します。
                    保存後は会社・部署のメンバー一覧に表示されます。
                </p>

                <form class="mt-6 space-y-5" @submit.prevent="submit">
                    <div>
                        <InputLabel for="company_id" value="所属会社" />
                        <select
                            id="company_id"
                            v-model="form.company_id"
                            required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-yellow-500 focus:ring-yellow-500"
                        >
                            <option value="">-- 会社を選択してください --</option>
                            <option v-for="company in companies" :key="company.id" :value="String(company.id)">
                                {{ company.name }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.company_id" />
                    </div>

                    <div>
                        <InputLabel for="department_id" value="所属部署" />
                        <select
                            id="department_id"
                            v-model="form.department_id"
                            :disabled="!form.company_id"
                            required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-yellow-500 focus:ring-yellow-500 disabled:bg-gray-100"
                        >
                            <option value="">-- 部署を選択してください --</option>
                            <option v-for="department in departments" :key="department.id" :value="String(department.id)">
                                {{ department.name }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.department_id" />
                    </div>

                    <div class="flex justify-end">
                        <PrimaryButton
                            class="bg-yellow-500 hover:bg-yellow-600"
                            :class="{ 'opacity-25': form.processing }"
                            :disabled="form.processing"
                        >
                            設定を保存
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
