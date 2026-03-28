<script setup>
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    departments: [
        {
            id: null,
            name: '',
            assignments: [{ id: null, name: '' }],
        },
    ],
});

const addDepartment = () => {
    form.departments.push({ id: null, name: '', assignments: [{ id: null, name: '' }] });
};

const removeDepartment = (depIdx) => {
    if (confirm('部署を削除すると、その担当もすべて削除されます。よろしいですか？')) {
        form.departments.splice(depIdx, 1);
    }
};

const addAssignment = (depIdx) => {
    form.departments[depIdx].assignments.push({ id: null, name: '' });
};

const removeAssignment = (depIdx, assignmentIdx) => {
    if (confirm('担当を削除します。よろしいですか？')) {
        form.departments[depIdx].assignments.splice(assignmentIdx, 1);
    }
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
    form.post(route('superadmin.companies.store'));
};
</script>

<template>
    <AppLayout title="会社新規登録">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">会社新規登録</h2>
        </template>

        <div class="rounded bg-white p-6 shadow">
            <div class="mx-auto max-w-2xl">
                <form @submit.prevent="submit">
                    <!-- 会社名 -->
                    <div class="mb-6">
                        <InputLabel for="name" value="会社名" />
                        <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" required autofocus />
                    </div>

                    <!-- 部署 -->
                    <div class="mb-2 text-sm font-medium text-gray-700">部署・担当</div>
                    <div class="mb-4 divide-y divide-gray-200 rounded border border-gray-200">
                        <div v-for="(department, depIdx) in form.departments" :key="depIdx" class="p-4">
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
                                        @click="addAssignment(depIdx)"
                                        class="rounded border border-indigo-300 px-2 py-0.5 text-xs text-indigo-600 hover:bg-indigo-50"
                                    >
                                        ＋追加
                                    </button>
                                </div>
                                <div
                                    v-for="(assignment, assignmentIdx) in department.assignments"
                                    :key="assignmentIdx"
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
                                        @click="removeAssignment(depIdx, assignmentIdx)"
                                        class="rounded border border-red-300 px-2 py-0.5 text-xs text-red-600 hover:bg-red-50"
                                    >
                                        削除
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 部署追加 -->
                    <div class="mb-6">
                        <button
                            type="button"
                            @click="addDepartment"
                            class="rounded border border-indigo-300 px-3 py-1 text-sm text-indigo-600 hover:bg-indigo-50"
                        >
                            ＋部署追加
                        </button>
                    </div>

                    <!-- ボタン -->
                    <div class="flex items-center justify-end gap-3">
                        <Link :href="route('superadmin.companies.index')" class="text-sm text-gray-600 hover:underline">
                            戻る
                        </Link>
                        <PrimaryButton type="submit" :disabled="form.processing">登録</PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
