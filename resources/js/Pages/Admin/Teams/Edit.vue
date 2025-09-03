<script setup>
import FormSection from '@/Components/FormSection.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TextInput from '@/Components/TextInput.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import EditForUnits from '@/Pages/Admin/Teams/EditForUnits.vue';
import { useForm, usePage } from '@inertiajs/vue3';

const page = usePage();
const team = page.props.team;
const companies = page.props.companies || [];
const departments = page.props.departments || [];
const roles = page.props.roles || [];

const form = useForm({
    name: team.name || '',
    company_id: team.company_id || '',
    department_id: team.department_id || '',
    role_id: team.role_id || '',
});

const updateTeam = () => {
    form.put(route('admin.teams.update', { team: team.id }), {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout title="チーム編集">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">チーム編集</h2>
        </template>
        <div class="mx-auto max-w-3xl py-10 sm:px-6 lg:px-8">
            <!-- If this is a unit team, render the unit-specific edit form -->
            <component :is="team.team_type === 'unit' ? EditForUnits : 'div'">
                <template v-if="team.team_type !== 'unit'">
                    <div class="mb-8 px-4 sm:px-0">
                        <h3 class="text-lg font-medium text-gray-900">チーム情報</h3>
                        <p class="mt-1 text-sm text-gray-600">チーム名や所属会社・部署などを編集できます。</p>
                    </div>
                    <FormSection @submitted="updateTeam">
                        <template #form>
                            <div class="col-span-6 sm:col-span-4">
                                <InputLabel for="name" value="チーム名" />
                                <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" autofocus />
                                <InputError :message="form.errors.name" class="mt-2" />
                            </div>
                            <div class="col-span-6 sm:col-span-4">
                                <InputLabel for="company_id" value="会社" />
                                <SelectInput
                                    id="company_id"
                                    v-model="form.company_id"
                                    :options="companies.map((c) => ({ value: c.id, label: c.name }))"
                                    class="mt-1 block w-full"
                                />
                                <InputError :message="form.errors.company_id" class="mt-2" />
                            </div>
                            <div class="col-span-6 sm:col-span-4">
                                <InputLabel for="department_id" value="部署" />
                                <SelectInput
                                    id="department_id"
                                    v-model="form.department_id"
                                    :options="departments.map((d) => ({ value: d.id, label: d.name }))"
                                    class="mt-1 block w-full"
                                />
                                <InputError :message="form.errors.department_id" class="mt-2" />
                            </div>
                            <!-- 必要に応じて他のリレーションや属性もここに追加 -->
                        </template>
                        <template #actions>
                            <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing"> 更新 </PrimaryButton>
                        </template>
                    </FormSection>
                </template>
            </component>
        </div>
    </AppLayout>
</template>

<style scoped>
/* 必要に応じてカスタムスタイル */
</style>
