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
import { computed } from 'vue';

const page = usePage();
const team = page.props.team;
const companies = page.props.companies || [];
const departments = page.props.departments || [];
const roles = page.props.roles || [];

// 変更: leader_user (person) と description をフォームに追加
const form = useForm({
    name: team.name || '',
    company_id: team.company_id || '',
    department_id: team.department_id || '',
    role_id: team.role_id || '',
    // leader_id は user id または 'superadmin' という特別値を取り得る
    leader_id: team.leader_id || team.leader_user_id || '',
    description: team.description || '',
});

// leader 候補はユーザーから選ぶ。SuperAdmin を特別オプションとして先頭に入れる。
const leaderCandidates = computed(() => {
    // まず候補ユーザーを取得: page.props.users, team.users, 会社の users などを順に参照
    const allUsers = page.props.users || team.users || (team.company && team.company.users) || [];

    // フィルタ: user_role が admin または leader の人のみ
    let list = Array.isArray(allUsers) ? allUsers.slice() : [];

    list = list.filter((u) => {
        const role = String(u.user_role || '').toLowerCase();
        if (role === 'admin' || role === 'leader') {
            // 会社フィルタ
            if (form.company_id && String(u.company_id) !== String(form.company_id)) return false;
            // 部署フィルタがある場合は同じ部署の人のみ
            if (form.department_id && String(u.department_id) !== String(form.department_id)) return false;
            return true;
        }
        return false;
    });

    // map to SelectInput 形式
    const options = list.map((u) => ({ value: u.id, label: `${u.name || u.display_name || u.email || 'ID:' + u.id}` }));

    // 常に SuperAdmin を先頭に追加して、システム管理者をリーダーにする選択を可能にする
    options.unshift({ value: 'superadmin', label: 'Super Admin (全権限)' });

    return options;
});

const updateTeam = () => {
    form.put(route('admin.teams.update', { team: team.id }), {
        preserveScroll: true,
    });
};

// restrict editing for company/department teams: only leader and description editable
const isRestricted = computed(() => {
    return team.team_type === 'company' || team.team_type === 'department';
});
</script>

<template>
    <AppLayout title="チーム編集">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">チーム編集</h2>
        </template>
        <div class="mx-auto max-w-3xl rounded bg-white p-6 shadow">
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
                                <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" :disabled="isRestricted" autofocus />
                                <InputError :message="form.errors.name" class="mt-2" />
                            </div>
                            <div class="col-span-6 sm:col-span-4">
                                <InputLabel for="company_id" value="会社" />
                                <SelectInput
                                    id="company_id"
                                    v-model="form.company_id"
                                    :options="companies.map((c) => ({ value: c.id, label: c.name }))"
                                    :disabled="isRestricted"
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
                                    :disabled="isRestricted"
                                    class="mt-1 block w-full"
                                />
                                <InputError :message="form.errors.department_id" class="mt-2" />
                            </div>

                            <template v-if="isRestricted">
                                <div class="col-span-6 mt-2 sm:col-span-4">
                                    <p class="text-sm text-gray-500">
                                        このチームは会社/部署スコープのチームのため、チーム名・会社・部署の変更はできません。リーダーと詳細のみ更新可能です。
                                    </p>
                                </div>
                            </template>

                            <!-- 変更: リーダー選択を "人" から選ぶ UI にする -->
                            <div class="col-span-6 mt-4 sm:col-span-4">
                                <InputLabel for="leader_id" value="リーダー（ユーザー）" />
                                <SelectInput id="leader_id" v-model="form.leader_id" :options="leaderCandidates" class="mt-1 block w-full" />
                                <p class="mt-1 text-sm text-gray-500">
                                    リーダーを選択してください。候補は Admin / Leader のユーザーで、会社や部署によって絞り込まれます。SuperAdmin
                                    を選ぶと全権限となります。
                                </p>
                                <InputError :message="form.errors.leader_id" class="mt-2" />
                            </div>

                            <!-- 追加: 説明 (description) -->
                            <div class="col-span-6 mt-4 sm:col-span-4">
                                <InputLabel for="description" value="説明" />
                                <textarea
                                    id="description"
                                    v-model="form.description"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    rows="4"
                                ></textarea>
                                <InputError :message="form.errors.description" class="mt-2" />
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
