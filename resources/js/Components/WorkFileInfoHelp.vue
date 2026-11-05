<script setup>
defineProps({ legacyQuantity: { type: Boolean, default: false } });
</script>

<template>
    <component :is="legacyQuantity ? 'details' : 'div'" class="mb-3 rounded border border-blue-200 bg-white p-3 text-xs leading-relaxed text-gray-700">
        <component :is="legacyQuantity ? 'summary' : 'p'" class="font-semibold text-blue-900" :class="{ 'cursor-pointer': legacyQuantity }">ファイルを選ぶとどうなりますか？</component>
        <p class="mt-1">一度に50件まで選択できます。選択したファイルから、ファイル名・ページ数・用紙サイズ・容量などを読み取り、作業ファイルの一覧を作ります。読み取れる項目はファイル形式によって異なり、100MBを超えるファイルは容量などの基本情報のみを記録します。</p>
        <p class="mt-1">ファイル本体はサーバーに保存・共有されません。ここからファイルをダウンロードすることはできません。読み取った情報は、この画面で保存するとジョブに登録されます。</p>
        <p v-if="legacyQuantity" class="mt-1">読み取り結果は、従来どおり数量（ページ数またはファイル数）に自動反映されます。用紙サイズは案件の設定などに応じて補完されます。保存前に内容を確認してください。</p>
        <p v-else class="mt-1">ファイルの総ページ数と作業の実績数量は別です。登録済みの実績数量・予定時刻は、自動で変更しません。</p>
    </component>
</template>
