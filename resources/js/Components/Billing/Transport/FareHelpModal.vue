<script setup>
defineEmits(['close']);
</script>

<template>
    <Teleport to="body">
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="$emit('close')">
            <div class="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-lg bg-white shadow-xl">
                <div class="flex items-center justify-between border-b px-5 py-3">
                    <h3 class="text-base font-semibold text-gray-800">定期区間外の駅から記載してください</h3>
                    <button type="button" class="text-xl leading-none text-gray-400 hover:text-gray-600" @click="$emit('close')">✕</button>
                </div>
                <div class="space-y-4 px-5 py-4 text-sm text-gray-700">
                    <p>通勤定期券で乗れる区間（定期区間）の運賃は、会社から支給されています。申請には、<strong>定期区間の外にかかった運賃だけ</strong>を記載します。</p>

                    <div class="rounded border border-blue-200 bg-blue-50 p-3">
                        <p class="mb-1 font-semibold text-blue-900">例：定期区間が「田端 － 池袋」で、新宿へ出かけたとき</p>
                        <ul class="list-inside list-disc space-y-1">
                            <li>田端から池袋までは定期区間内なので、<strong>記載しません</strong>。</li>
                            <li><strong>池袋 － 新宿</strong>の運賃を記載します。</li>
                            <li>区間の欄は「<strong>池袋 － 新宿</strong>」、金額は池袋から新宿までの運賃です。</li>
                        </ul>
                    </div>

                    <div class="rounded border border-gray-200 bg-gray-50 p-3">
                        <p class="mb-1 font-semibold text-gray-800">往復のとき</p>
                        <p>用件を「往復」にして、池袋 － 新宿の<strong>片道</strong>の金額を入れ、「片道の金額を×2」にチェックを入れると、往復の金額になります。</p>
                    </div>

                    <div class="rounded border border-gray-200 bg-gray-50 p-3">
                        <p class="mb-1 font-semibold text-gray-800">定期区間と、まったく関係のない場所へ行くとき</p>
                        <p>家の最寄り駅など、定期区間の駅を通らない場合は、乗った駅から降りた駅までを、そのまま記載します。</p>
                    </div>

                    <p class="text-xs text-gray-500">わからないときは、総務・経理に確認してください。</p>
                </div>
                <div class="flex justify-end border-t px-5 py-3">
                    <button type="button" class="rounded border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" @click="$emit('close')">閉じる</button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
