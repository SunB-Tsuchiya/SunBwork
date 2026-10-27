<script setup>
defineEmits(['close']);
</script>

<template>
    <Teleport to="body">
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="$emit('close')">
            <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-lg bg-white shadow-xl">
                <div class="flex items-center justify-between border-b px-5 py-3">
                    <h3 class="text-base font-semibold text-gray-800">経由地の入れ方</h3>
                    <button type="button" class="text-xl leading-none text-gray-400 hover:text-gray-600" @click="$emit('close')">✕</button>
                </div>
                <div class="space-y-4 px-5 py-4 text-sm text-gray-700">
                    <p>経由地は、<strong>鉄道会社が変わる乗換駅</strong>（JR → 東京メトロ → 都営地下鉄 → 東武鉄道 など）を入れます。<strong>入れなくても、申請できます</strong>。入れると、総務・経理が、区間ごとに運賃を確認しやすくなります。</p>

                    <div class="rounded border border-blue-200 bg-blue-50 p-3">
                        <p class="mb-1 font-semibold text-blue-900">① 会社が変わる駅を、乗った順に入れます</p>
                        <p class="text-gray-700">例：JR で田端から池袋へ行き、池袋で東武東上線に乗り換えて川越へ行ったとき</p>
                        <p class="mt-1 rounded bg-white px-2 py-1 text-gray-800">田端 － <strong>池袋</strong>（経由地） － 川越</p>
                        <p class="mt-1 text-xs text-gray-600">乗換の駅名が、会社が違っても同じとき（池袋など）は、その駅名を 1 つ入れます。</p>
                    </div>

                    <div class="rounded border border-amber-200 bg-amber-50 p-3">
                        <p class="mb-1 font-semibold text-amber-900">② 乗換の駅名が違うとき（いったん改札を出て、歩く乗換）</p>
                        <p class="text-gray-700"><strong>前の会社で降りた駅</strong>の名前を、1 つ入れます（乗る駅の名前ではありません）。</p>
                        <p class="mt-1 text-gray-700">例：JR の御茶ノ水で降りて、東京メトロ千代田線の新御茶ノ水まで歩いて乗り換えたとき</p>
                        <p class="mt-1 rounded bg-white px-2 py-1 text-gray-800">○○ － <strong>御茶ノ水</strong>（経由地） － △△</p>
                        <p class="mt-1 text-xs text-gray-600">乗換案内の経路に出てくる、「降りる駅」の名前に合わせてください。</p>
                    </div>

                    <div class="rounded border border-gray-200 bg-gray-50 p-3">
                        <p class="mb-1 font-semibold text-gray-800">③ 入れなくてよいとき・迷ったとき</p>
                        <ul class="list-inside list-disc space-y-1">
                            <li>同じ会社の中の乗換（東京メトロの線から別の東京メトロの線など）は、運賃が通しで計算されるので、<strong>入れなくて大丈夫</strong>です。</li>
                            <li>乗換が 2 回以上あるときは、順番に入れます（最大 5 か所）。</li>
                            <li>迷ったときは、<strong>入れなくてもかまいません</strong>。総務・経理に聞いてください。</li>
                        </ul>
                    </div>

                    <p class="text-xs text-gray-500">金額は、実際に払った金額（割引があるときは、割引のあとの金額）を、そのまま書いてください。たとえば、東京メトロと都営地下鉄の乗継割引があっても、金額は、実際に払った合計で大丈夫です。</p>
                </div>
                <div class="flex justify-end border-t px-5 py-3">
                    <button type="button" class="rounded border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" @click="$emit('close')">閉じる</button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
