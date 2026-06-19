<script setup>
import SearchFilters from '@/Components/NSystem/SearchFilters.vue';
import SearchPagination from '@/Components/NSystem/SearchPagination.vue';
import SearchResultCard from '@/Components/NSystem/SearchResultCard.vue';
import NSystemDemoLayout from '@/layouts/NSystemDemoLayout.vue';
import { onBeforeUnmount, reactive, ref, watch } from 'vue';

const props = defineProps({
    initialFilters: { type: Object, required: true },
    initialResults: { type: Object, required: true },
    schools: { type: Array, required: true },
    subjectLabels: { type: Object, required: true },
    categories: { type: Array, required: true },
    isGuest: { type: Boolean, default: false },
});

const filters = reactive({ ...props.initialFilters });
const results = ref(props.initialResults);
const loading = ref(false);
const error = ref('');
const composing = ref(false);
let debounceTimer = null;
let activeController = null;

const requestParams = (page = 1) => {
    const params = { q: filters.q, mode: filters.mode, page };
    if (filters.subject) params.subject = filters.subject;
    if (filters.school_id) params.school_id = filters.school_id;
    if (filters.category) params.category = filters.category;
    return params;
};

const updateUrl = (params) => {
    const url = new URL(route('n-demo.search'), window.location.origin);
    Object.entries(params).forEach(([key, value]) => {
        if (value !== null && value !== '' && !(key === 'page' && value === 1)) url.searchParams.set(key, value);
    });
    window.history.replaceState({}, '', url.toString());
};

const runSearch = async (page = 1) => {
    clearTimeout(debounceTimer);
    activeController?.abort();
    error.value = '';

    if (!filters.q.trim()) {
        results.value = {
            items: [],
            pagination: { current_page: 1, last_page: 1, per_page: 20, total: 0, from: null, to: null },
        };
        updateUrl({});
        loading.value = false;
        return;
    }

    const controller = new AbortController();
    activeController = controller;
    loading.value = true;
    const params = requestParams(page);

    try {
        const response = await window.axios.get(route('n-demo.search.results'), {
            params,
            signal: controller.signal,
        });
        results.value = response.data;
        updateUrl(params);
    } catch (requestError) {
        if (requestError?.code !== 'ERR_CANCELED' && requestError?.name !== 'CanceledError') {
            error.value = '検索結果を取得できませんでした。時間をおいて再度お試しください。';
        }
    } finally {
        if (activeController === controller) {
            loading.value = false;
            activeController = null;
        }
    }
};

const scheduleSearch = () => {
    if (composing.value) return;
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => runSearch(1), 300);
};

const updateFilter = ({ key, value }) => {
    filters[key] = value;
};

const finishComposition = () => {
    composing.value = false;
    scheduleSearch();
};

watch(
    () => [filters.q, filters.mode, filters.subject, filters.school_id, filters.category],
    scheduleSearch,
);

onBeforeUnmount(() => {
    clearTimeout(debounceTimer);
    activeController?.abort();
});
</script>

<template>
    <NSystemDemoLayout title="全文検索" :is-guest="isGuest">
        <div class="space-y-5">
            <div>
                <h2 class="text-xl font-bold text-[#1a3a6b]">入試問題 全文検索</h2>
                <p class="mt-1 text-sm text-gray-600">登録された問題本文から、一致箇所とその前後を確認できます。</p>
            </div>

            <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm" aria-labelledby="search-keyword-heading">
                <h2 id="search-keyword-heading" class="mb-2 text-sm font-bold text-[#1a3a6b]">検索キーワード</h2>
                <form class="flex w-full gap-2" role="search" @submit.prevent="runSearch(1)">
                    <label for="n-question-search" class="sr-only">問題本文を検索</label>
                    <input
                        id="n-question-search"
                        v-model="filters.q"
                        type="search"
                        maxlength="100"
                        autocomplete="off"
                        autofocus
                        placeholder="例：平安時代　光合成　方程式"
                        class="min-w-0 flex-1 rounded-md border-gray-300 px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-[#1a3a6b] focus:ring-[#1a3a6b]"
                        @compositionstart="composing = true"
                        @compositionend="finishComposition"
                    />
                    <button
                        type="submit"
                        class="whitespace-nowrap rounded-md bg-[#1a3a6b] px-5 py-2 text-sm font-semibold text-white hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-400 disabled:opacity-60"
                        :disabled="loading"
                    >
                        検索
                    </button>
                </form>
            </section>

            <SearchFilters
                :filters="filters"
                :schools="schools"
                :subject-labels="subjectLabels"
                :categories="categories"
                @update-filter="updateFilter"
            />

            <div aria-live="polite" aria-atomic="true">
                <div v-if="loading" class="rounded-lg border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                    検索しています…
                </div>
                <div v-else-if="error" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ error }}
                </div>
                <p v-else-if="filters.q" class="text-sm text-gray-600">
                    「<strong class="text-gray-900">{{ filters.q }}</strong>」の検索結果:
                    <strong class="text-[#1a3a6b]">{{ results.pagination.total }}</strong>件
                    <span v-if="results.pagination.total">（{{ results.pagination.from }}〜{{ results.pagination.to }}件を表示）</span>
                </p>
            </div>

            <div v-if="!filters.q" class="rounded-lg border border-gray-200 bg-white px-6 py-12 text-center text-gray-500 shadow-sm">
                検索窓にキーワードを入力してください。
            </div>
            <div v-else-if="!loading && !error && results.items.length === 0" class="rounded-lg border border-gray-200 bg-white px-6 py-12 text-center text-gray-500 shadow-sm">
                条件に一致する問題が見つかりませんでした。
            </div>
            <div v-else class="space-y-3">
                <SearchResultCard
                    v-for="item in results.items"
                    :key="item.id"
                    :item="item"
                    :subject-labels="subjectLabels"
                />
            </div>

            <SearchPagination :pagination="results.pagination" :disabled="loading" @change="runSearch" />
        </div>
    </NSystemDemoLayout>
</template>
