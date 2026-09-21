<script setup>
import { computed, reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps({
  batch: { type: Object, required: true },
  batches: { type: Array, default: () => [] },
  rows: { type: Object, required: true },
  summary: { type: Object, required: true },
  mediaOptions: { type: Array, default: () => [] },
  filters: { type: Object, required: true },
});

const form = reactive({
  batch: props.filters.batch,
  search: props.filters.search ?? '',
  media: props.filters.media ?? '',
  status: props.filters.status ?? 'all',
  per_page: props.filters.per_page ?? 25,
});

const displayedRange = computed(() => {
  if (!props.rows.total) return '0件';
  return `${props.rows.from}～${props.rows.to}件 / ${props.rows.total}件`;
});

function search() {
  router.get(route('coordinator.mginbon.import_preview'), { ...form }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
}

function resetFilters() {
  form.search = '';
  form.media = '';
  form.status = 'all';
  form.per_page = 25;
  search();
}

function warningLabel(warning) {
  if (warning === 'missing_both_codes') return '学校コードなし（冊子共通候補）';
  if (warning === 'duplicate_identity_candidate') return '制作単位・媒体の重複候補';
  if (warning.startsWith('non_padded_date:')) return `日付をゼロ埋め: ${warning.split(':')[1]}`;
  if (warning.startsWith('invalid_date:')) return `無効な日付: ${warning.split(':')[1]}`;
  if (warning.startsWith('invalid_quantity:')) return `無効な点数: ${warning.split(':')[1]}`;
  return warning;
}

function paginationLabel(label) {
  return label
    .replace('&laquo; Previous', '前へ')
    .replace('Next &raquo;', '次へ');
}
</script>

<template>
  <AppLayout title="MGinbon 移行プレビュー">
    <template #header>
      <div class="flex flex-wrap items-center gap-3">
        <Link
          :href="route('coordinator.dashboard')"
          class="whitespace-nowrap rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300"
        >← ダッシュボードに戻る</Link>
        <h2 class="text-base font-semibold leading-tight text-gray-800 sm:text-xl">
          MGinbon 取込確認
        </h2>
        <Link
          :href="route('coordinator.mginbon.index')"
          class="ml-auto whitespace-nowrap rounded bg-green-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-green-700"
        >銀本進行・管理台帳へ戻る</Link>
      </div>
    </template>

    <div class="space-y-4">
      <section class="rounded bg-white p-4 shadow sm:p-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <div class="flex flex-wrap items-center gap-2">
              <span class="rounded bg-cyan-100 px-3 py-1 text-lg font-semibold text-cyan-950">
                {{ batch.year }}年 中学入試問題集
              </span>
              <span class="rounded bg-gray-100 px-2 py-1 text-xs text-gray-600">読取専用</span>
              <span class="rounded bg-indigo-50 px-2 py-1 text-xs text-indigo-700">{{ batch.column_count }}項目</span>
            </div>
            <p class="mt-2 text-xs text-gray-500">
              原文と自動変換候補を比較する画面です。この画面ではデータを確定・変更しません。
            </p>
          </div>

          <div class="grid grid-cols-3 gap-2 text-center text-sm">
            <div class="rounded border border-gray-200 bg-gray-50 px-3 py-2">
              <div class="text-xs text-gray-500">全件</div>
              <div class="text-lg font-semibold text-gray-800">{{ summary.total }}</div>
            </div>
            <div class="rounded border border-green-200 bg-green-50 px-3 py-2">
              <div class="text-xs text-green-700">自動候補</div>
              <div class="text-lg font-semibold text-green-800">{{ summary.candidates }}</div>
            </div>
            <div class="rounded border border-orange-200 bg-orange-50 px-3 py-2">
              <div class="text-xs text-orange-700">要確認</div>
              <div class="text-lg font-semibold text-orange-800">{{ summary.review_required }}</div>
            </div>
          </div>
        </div>

        <form class="mt-5 grid grid-cols-1 gap-3 border-t border-gray-100 pt-4 sm:grid-cols-2 lg:grid-cols-7" @submit.prevent="search">
          <label class="block">
            <span class="mb-1 block text-xs font-medium text-gray-600">年度・取込</span>
            <select v-model.number="form.batch" class="w-full rounded border-gray-300 text-sm">
              <option v-for="item in batches" :key="item.id" :value="item.id">{{ item.year }}年 #{{ item.id }}</option>
            </select>
          </label>
          <label class="block lg:col-span-2">
            <span class="mb-1 block text-xs font-medium text-gray-600">学校名・コード</span>
            <input v-model="form.search" type="search" class="w-full rounded border-gray-300 text-sm" placeholder="学校名、みくにコード、日能研コード" />
          </label>
          <label class="block">
            <span class="mb-1 block text-xs font-medium text-gray-600">媒体</span>
            <select v-model="form.media" class="w-full rounded border-gray-300 text-sm">
              <option value="">すべて</option>
              <option v-for="media in mediaOptions" :key="media" :value="media">{{ media }}</option>
            </select>
          </label>
          <label class="block">
            <span class="mb-1 block text-xs font-medium text-gray-600">判定</span>
            <select v-model="form.status" class="w-full rounded border-gray-300 text-sm">
              <option value="all">すべて</option>
              <option value="candidate">自動候補</option>
              <option value="review_required">要確認</option>
            </select>
          </label>
          <label class="block">
            <span class="mb-1 block text-xs font-medium text-gray-600">表示件数</span>
            <select v-model.number="form.per_page" class="w-full rounded border-gray-300 text-sm">
              <option :value="10">10件</option>
              <option :value="25">25件</option>
              <option :value="50">50件</option>
            </select>
          </label>
          <div class="flex items-end gap-2">
            <button type="submit" class="rounded bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">検索</button>
            <button type="button" class="rounded border border-gray-300 px-3 py-2 text-sm text-gray-600 hover:bg-gray-50" @click="resetFilters">解除</button>
          </div>
        </form>
      </section>

      <div class="flex items-center justify-between text-sm text-gray-600">
        <span>{{ displayedRange }}</span>
        <span>取込バッチ #{{ batch.id }} / {{ batch.source_filename }}</span>
      </div>

      <section v-if="rows.data.length" class="space-y-3">
        <article
          v-for="row in rows.data"
          :key="row.id"
          class="overflow-hidden rounded border bg-white shadow-sm"
          :class="row.status === 'review_required' ? 'border-orange-300' : 'border-cyan-200'"
        >
          <div class="grid grid-cols-1 bg-cyan-50 text-sm md:grid-cols-[5rem_6rem_8rem_minmax(16rem,1fr)_8rem]">
            <div class="bg-gray-600 px-3 py-2 font-semibold text-white">行 {{ row.record_number }}</div>
            <div class="border-r border-white px-3 py-2"><span class="text-[10px] text-gray-500">みくに</span><br>{{ row.raw.mikuni_code || '—' }}</div>
            <div class="border-r border-white px-3 py-2"><span class="text-[10px] text-gray-500">日能研</span><br>{{ row.raw.n_code || '—' }}</div>
            <div class="border-r border-white px-3 py-2 text-base font-semibold text-gray-900">{{ row.raw.school_name || '名称なし' }}</div>
            <div class="bg-green-100 px-3 py-2 text-center font-medium text-green-900">{{ row.raw.media_type || '未設定' }}</div>
          </div>

          <div class="flex flex-wrap items-center gap-2 border-b border-gray-100 px-3 py-2 text-xs">
            <span class="rounded bg-gray-100 px-2 py-1">{{ row.raw.n_category || '分類なし' }}</span>
            <span class="rounded bg-gray-100 px-2 py-1">{{ row.raw.school_category || '学校分類なし' }}</span>
            <span class="rounded bg-blue-50 px-2 py-1 text-blue-800">{{ row.raw.subjects || '科目なし' }}</span>
            <span class="rounded bg-purple-50 px-2 py-1 text-purple-800">銀本掲載: {{ row.raw.publication_status || '空欄' }}</span>
            <span
              class="ml-auto rounded px-2 py-1 font-medium"
              :class="row.status === 'review_required' ? 'bg-orange-100 text-orange-800' : 'bg-green-100 text-green-800'"
            >{{ row.status === 'review_required' ? '要確認' : '自動候補' }}</span>
          </div>

          <div v-if="row.warnings.length" class="border-b border-orange-100 bg-orange-50 px-3 py-2">
            <ul class="flex flex-wrap gap-x-5 gap-y-1 text-xs text-orange-800">
              <li v-for="warning in row.warnings" :key="warning">● {{ warningLabel(warning) }}</li>
            </ul>
          </div>

          <div class="overflow-x-auto">
            <table class="min-w-[880px] w-full divide-y divide-gray-200 text-xs">
              <thead class="bg-gray-50 text-gray-600">
                <tr>
                  <th class="px-3 py-2 text-left">正規化候補</th>
                  <th v-for="subject in row.normalized.subjects" :key="subject.code" class="px-3 py-2 text-center">{{ subject.label }}</th>
                  <th class="px-3 py-2 text-left">共通日付</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr>
                  <td class="px-3 py-2 align-top">
                    <div>{{ row.normalized.unit_type === 'book_component' ? '冊子共通' : '学校・入試回' }}</div>
                    <div class="mt-1 text-gray-500">媒体: {{ row.normalized.media_type || '未設定' }}</div>
                  </td>
                  <td v-for="subject in row.normalized.subjects" :key="subject.code" class="px-3 py-2 text-center align-top" :class="subject.active ? 'bg-yellow-50' : 'bg-gray-50 text-gray-400'">
                    <div class="font-medium">{{ subject.active ? '対象' : '対象外' }}</div>
                    <div class="mt-1 whitespace-nowrap">scan 社{{ subject.scan_internal }} / 外{{ subject.scan_subcontracted }}</div>
                    <div class="whitespace-nowrap">作図 社{{ subject.drawing_internal }} / 外{{ subject.drawing_subcontracted }}</div>
                    <div class="mt-1 text-[10px] text-gray-500">担当{{ subject.actor_count }}・日付{{ subject.date_count }}</div>
                  </td>
                  <td class="px-3 py-2 align-top whitespace-nowrap">
                    <div>原本: {{ row.normalized.original_received_on || '—' }}</div>
                    <div>scan UP: {{ row.normalized.original_scan_completed_on || '—' }}</div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </article>
      </section>

      <div v-else class="rounded bg-white p-8 text-center text-sm text-gray-500 shadow">条件に一致するレコードはありません。</div>

      <nav v-if="rows.links.length > 3" class="flex flex-wrap justify-center gap-1" aria-label="ページ移動">
        <template v-for="link in rows.links" :key="`${link.label}-${link.url}`">
          <Link
            v-if="link.url"
            :href="link.url"
            preserve-scroll
            class="rounded border px-3 py-1.5 text-sm"
            :class="link.active ? 'border-green-600 bg-green-600 text-white' : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'"
          >{{ paginationLabel(link.label) }}</Link>
          <span v-else class="rounded border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm text-gray-400">{{ paginationLabel(link.label) }}</span>
        </template>
      </nav>
    </div>
  </AppLayout>
</template>
