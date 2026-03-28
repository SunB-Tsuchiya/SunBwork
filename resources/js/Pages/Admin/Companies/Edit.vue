<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { useForm, Link } from '@inertiajs/vue3';

const props = defineProps({
    company: {
        type: Object,
        required: true,
    },
    adminUsers: {
        type: Array,
        default: () => [],
    },
    leaderUsers: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    name: props.company.name,
    representative_id: props.company.representative_id ?? null,
    representative_leader_id: props.company.representative_leader_id ?? null,
    departments: props.company.departments?.map(dep => ({
        id: dep.id,
        name: dep.name,
        assignments: dep.assignments?.map(assignment => ({ id: assignment.id, name: assignment.name })) || [],
    })) || [],
});

const addRole = (depIdx) => {
    form.departments[depIdx].assignments.push({ id: null, name: '' });
};

const submit = () => {
    for (const dep of form.departments) {
        if (!dep.name.trim()) {
            alert('部署名を入力してください');
            return;
        }
        for (const assignment of dep.assignments) {
            if (!assignment.name.trim()) {
                alert('担当名を入力してください');
                return;
            }
        }
    }
    form.put(route('admin.companies.update', props.company.id));
};

const removeDepartment = (depIdx) => {
    if (confirm('部署を削除すると、その担当もすべて削除されます。よろしいですか？')) {
        form.departments.splice(depIdx, 1);
    }
};

const removeRole = (depIdx, assignmentIdx) => {
    if (confirm('担当を削除します。よろしいですか？')) {
        form.departments[depIdx].assignments.splice(assignmentIdx, 1);
    }
};
</script>

<template>
    <AppLayout title="会社編集">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">会社編集</h2>
        </template>

        <div class="rounded bg-white p-6 shadow">
            <div class="mx-auto max-w-2xl">
                <form @submit.prevent="submit">
                    <!-- 会社名 -->
                    <div class="mb-6">
                        <InputLabel for="name" value="会社名" />
                        <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" required autofocus />
                    </div>

                    <!-- 代表者 -->
                    <div class="mb-6 rounded border border-gray-200">
                        <div class="border-b border-gray-200 bg-gray-50 px-4 py-2 text-sm font-medium text-gray-700">
                            代表者
                        </div>
                        <div class="p-4">
                            <div v-if="adminUsers.length === 0" class="text-sm text-gray-400">
                                この会社に所属する Admin ユーザーがいません。
                            </div>
                            <div v-else class="space-y-2">
                                <label class="flex items-center gap-2 text-sm">
                                    <input
                                        type="radio"
                                        :value="null"
                                        v-model="form.representative_id"
                                        class="accent-red-600"
                                    />
                                    <span class="text-gray-400">未設定</span>
                                </label>
                                <label
                                    v-for="admin in adminUsers"
                                    :key="admin.id"
                                    class="flex items-center gap-2 text-sm"
                                >
                                    <input
                                        type="radio"
                                        :value="admin.id"
                                        v-model="form.representative_id"
                                        class="accent-red-600"
                                    />
                                    <span class="font-medium">{{ admin.name }}</span>
                                    <span class="text-gray-400">{{ admin.email }}</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- 代表者リーダー -->
                    <div class="mb-6 rounded border border-gray-200">
                        <div class="border-b border-gray-200 bg-gray-50 px-4 py-2 text-sm font-medium text-gray-700">
                            代表者リーダー
                        </div>
                        <div class="p-4">
                            <div v-if="leaderUsers.length === 0" class="text-sm text-gray-400">
                                この会社に所属する Leader ユーザーがいません。
                            </div>
                            <div v-else class="space-y-2">
                                <label class="flex items-center gap-2 text-sm">
                                    <input
                                        type="radio"
                                        :value="null"
                                        v-model="form.representative_leader_id"
                                        class="accent-red-600"
                                    />
                                    <span class="text-gray-400">未設定</span>
                                </label>
                                <label
                                    v-for="leader in leaderUsers"
                                    :key="leader.id"
                                    class="flex items-center gap-2 text-sm"
                                >
                                    <input
                                        type="radio"
                                        :value="leader.id"
                                        v-model="form.representative_leader_id"
                                        class="accent-red-600"
                                    />
                                    <span class="font-medium">{{ leader.name }}</span>
                                    <span class="text-gray-400">{{ leader.email }}</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- 部署・担当 -->
                    <div class="mb-2 text-sm font-medium text-gray-700">部署・担当</div>
                    <div class="mb-4 divide-y divide-gray-200 rounded border border-gray-200">
                        <div v-for="(department, depIdx) in form.departments" :key="department.id" class="p-4">
                            <!-- 部署名 -->
                            <div class="mb-2 flex items-center gap-2">
                                <span class="w-16 shrink-0 text-sm font-medium text-gray-600">部署名</span>
                                <TextInput
                                    :id="`department-name-${depIdx}`"
                                    v-model="department.name"
                                    type="text"
                                    class="block flex-1"
                                    required
                                />
                                <button
                                    type="button"
                                    @click="removeDepartment(depIdx)"
                                    class="rounded border border-red-300 px-2 py-1 text-xs text-red-600 hover:bg-red-50"
                                >
                                    部署削除
                                </button>
                            </div>

                            <!-- 担当名 -->
                            <div class="ml-8 mt-3 border-l-2 border-gray-200 pl-4">
                                <div class="mb-2 flex items-center gap-2">
                                    <span class="text-xs font-medium text-gray-500">担当名</span>
                                    <button
                                        type="button"
                                        @click="addRole(depIdx)"
                                        class="rounded border border-red-300 px-2 py-0.5 text-xs text-red-600 hover:bg-red-50"
                                    >
                                        ＋追加
                                    </button>
                                </div>
                                <div
                                    v-for="(assignment, assignmentIdx) in department.assignments"
                                    :key="assignment.id ?? assignmentIdx"
                                    class="mb-2 flex items-center gap-2"
                                >
                                    <TextInput
                                        :id="`assignment-name-${depIdx}-${assignmentIdx}`"
                                        v-model="assignment.name"
                                        type="text"
                                        class="block flex-1"
                                        required
                                    />
                                    <button
                                        type="button"
                                        @click="removeRole(depIdx, assignmentIdx)"
                                        class="rounded border border-red-300 px-2 py-0.5 text-xs text-red-600 hover:bg-red-50"
                                    >
                                        削除
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ボタン -->
                    <div class="flex items-center justify-end gap-3">
                        <Link
                            v-if="typeof route === 'function' && route().has('admin.companies.index')"
                            :href="route('admin.companies.index')"
                            class="text-sm text-gray-600 hover:underline"
                        >
                            戻る
                        </Link>
                        <PrimaryButton type="submit" :disabled="form.processing">更新</PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
