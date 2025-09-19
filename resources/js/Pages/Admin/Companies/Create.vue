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
    // バリデーション: 部署名・担当名が空白の場合は登録不可
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
    const routeName = 'superadmin.companies.store';
    // Debug logging removed
    try {
        const url = route(routeName);
        // Debug logging removed
        form.post(url);
    } catch (e) {
        console.error('[Create.vue][Admin] Ziggy route resolution failed for', routeName, e);
        throw e;
    }
};
</script>

<template>
    <AppLayout title="会社新規登録">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">会社新規登録</h2>
        </template>
        <div class="py-6">
            <form @submit.prevent="submit" class="mx-auto max-w-2xl">
                <div class="mb-6">
                    <InputLabel for="name" value="会社名" />
                    <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" required autofocus />
                </div>
                <div v-for="(department, depIdx) in form.departments" :key="depIdx" class="mb-6 rounded bg-blue-50 p-4">
                    <div class="mb-2 flex items-center">
                        <InputLabel :for="`department-name-${depIdx}`" :value="`部署名`" />
                        <button
                            type="button"
                            @click="removeDepartment(depIdx)"
                            class="ml-4 rounded bg-red-200 px-2 py-1 text-red-800 transition hover:bg-red-300"
                        >
                            削除
                        </button>
                    </div>
                    <TextInput :id="`department-name-${depIdx}`" v-model="department.name" type="text" class="mb-2 mt-1 block w-full" required />
                    <div class="ml-4">
                        <div class="mb-1 flex items-center">
                            <span class="block font-semibold">担当名</span>
                            <button
                                type="button"
                                @click="addAssignment(depIdx)"
                                class="ml-4 rounded bg-blue-200 px-2 py-1 text-blue-800 transition hover:bg-blue-300"
                            >
                                ＋追加
                            </button>
                        </div>
                        <div v-for="(assignment, assignmentIdx) in department.assignments" :key="assignmentIdx" class="mb-2 flex items-center">
                            <TextInput
                                :id="`assignment-name-${depIdx}-${assignmentIdx}`"
                                v-model="assignment.name"
                                type="text"
                                class="mt-1 block w-full"
                                required
                            />
                            <button
                                type="button"
                                @click="removeAssignment(depIdx, assignmentIdx)"
                                class="ml-2 rounded bg-red-200 px-2 py-1 text-red-800 transition hover:bg-red-300"
                            >
                                削除
                            </button>
                        </div>
                    </div>
                </div>
                <div class="mb-6">
                    <button type="button" @click="addDepartment" class="rounded bg-blue-200 px-3 py-1 text-blue-800 transition hover:bg-blue-300">
                        ＋部署追加
                    </button>
                </div>
                <div class="flex justify-end">
                    <PrimaryButton type="submit" :disabled="form.processing"> 登録 </PrimaryButton>
                    <Link :href="route('superadmin.companies.index')" class="ml-4 text-gray-600 hover:underline">戻る</Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
