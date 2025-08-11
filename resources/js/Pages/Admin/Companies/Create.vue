<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { useForm, Link } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    departments: [
        {
            id: null,
            name: '',
            roles: [
                { id: null, name: '' }
            ]
        }
    ]
});

const addDepartment = () => {
    form.departments.push({ id: null, name: '', roles: [{ id: null, name: '' }] });
};

const removeDepartment = (depIdx) => {
    if (confirm('部署を削除すると、その担当もすべて削除されます。よろしいですか？')) {
        form.departments.splice(depIdx, 1);
    }
};

const addRole = (depIdx) => {
    form.departments[depIdx].roles.push({ id: null, name: '' });
};

const removeRole = (depIdx, roleIdx) => {
    if (confirm('担当を削除します。よろしいですか？')) {
        form.departments[depIdx].roles.splice(roleIdx, 1);
    }
};

const submit = () => {
    // バリデーション: 部署名・担当名が空白の場合は登録不可
    for (const dep of form.departments) {
        if (!dep.name.trim()) {
            alert('部署名を入力してください');
            return;
        }
        for (const role of dep.roles) {
            if (!role.name.trim()) {
                alert('担当名を入力してください');
                return;
            }
        }
    }
    form.post(route('admin.companies.store'));
};
</script>

<template>
    <AppLayout title="会社新規登録">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">会社新規登録</h2>
        </template>
        <div class="py-6">
            <form @submit.prevent="submit" class="max-w-2xl mx-auto">
                <div class="mb-6">
                    <InputLabel for="name" value="会社名" />
                    <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" required autofocus />
                </div>
                <div v-for="(department, depIdx) in form.departments" :key="depIdx" class="mb-6 p-4 bg-blue-50 rounded">
                    <div class="flex items-center mb-2">
                        <InputLabel :for="`department-name-${depIdx}`" :value="`部署名`" />
                        <button type="button" @click="removeDepartment(depIdx)" class="ml-4 px-2 py-1 bg-red-200 text-red-800 rounded hover:bg-red-300 transition">削除</button>
                    </div>
                    <TextInput :id="`department-name-${depIdx}`" v-model="department.name" type="text" class="mt-1 block w-full mb-2" required />
                    <div class="ml-4">
                        <div class="flex items-center mb-1">
                            <span class="block font-semibold">担当名</span>
                            <button type="button" @click="addRole(depIdx)" class="ml-4 px-2 py-1 bg-blue-200 text-blue-800 rounded hover:bg-blue-300 transition">＋追加</button>
                        </div>
                        <div v-for="(role, roleIdx) in department.roles" :key="roleIdx" class="mb-2 flex items-center">
                            <TextInput :id="`role-name-${depIdx}-${roleIdx}`" v-model="role.name" type="text" class="mt-1 block w-full" required />
                            <button type="button" @click="removeRole(depIdx, roleIdx)" class="ml-2 px-2 py-1 bg-red-200 text-red-800 rounded hover:bg-red-300 transition">削除</button>
                        </div>
                    </div>
                </div>
                <div class="mb-6">
                    <button type="button" @click="addDepartment" class="px-3 py-1 bg-blue-200 text-blue-800 rounded hover:bg-blue-300 transition">＋部署追加</button>
                </div>
                <div class="flex justify-end">
                    <PrimaryButton type="submit" :disabled="form.processing">
                        登録
                    </PrimaryButton>
                    <Link :href="route('admin.companies.index')" class="ml-4 text-gray-600 hover:underline">戻る</Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
