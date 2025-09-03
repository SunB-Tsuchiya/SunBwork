<template>
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="fixed inset-0 bg-black/40" @click="close"></div>
        <div class="z-10 w-3/4 max-w-2xl overflow-auto rounded-lg bg-white shadow-lg">
            <div class="flex items-center justify-between border-b p-4">
                <h3 class="font-semibold">アドレス帳</h3>
                <button @click="close" class="text-gray-500">閉じる</button>
            </div>
            <div class="space-y-2 p-4">
                <input v-model="query" @input="search" placeholder="検索（名前/会社/部署/役割/割当）" class="w-full rounded border px-3 py-2" />
                <ul>
                    <li v-for="u in users" :key="u.id" class="flex items-center justify-between p-2 hover:bg-gray-50">
                        <div>
                            <div class="font-medium">{{ u.name }}</div>
                            <div class="text-xs text-gray-500">
                                {{ u.company_name }} / {{ u.department_name }} / {{ u.role_name }} / {{ u.assignment_name }}
                            </div>
                        </div>
                        <div>
                            <button @click="$emit('select', u)" class="text-blue-600">選択</button>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>

<script setup>
import axios from 'axios';
import { ref, watch } from 'vue';
const props = defineProps({ show: Boolean, companyId: [Number, null] });
const emit = defineEmits(['close', 'select']);
const query = ref('');
const users = ref([]);

async function search() {
    try {
        const res = await axios.get(route('users.search'), { params: { q: query.value, company_id: props.companyId } });
        users.value = res.data;
    } catch (e) {
        console.error(e);
    }
}

watch(
    () => props.show,
    (v) => {
        if (v) {
            query.value = '';
            users.value = [];
        }
    },
);
</script>
